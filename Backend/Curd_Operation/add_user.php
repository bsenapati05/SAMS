<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once "../db.php";
require_once "access_control.php";

// Security: Check if user is HOD or Admin
if(isTeacher() && !isHOD()) die(json_encode(["status" => "error", "message" => "Access Denied"])); 

$role_view = isset($_GET['role']) ? $_GET['role'] : 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header("Content-Type: application/json");
    $type = $_POST['type'] ?? '';
    
    $is_admin = ($_SESSION['role'] === 'admin');
    $dept_struct_id = null;
    $honourse = '';

    if (!$is_admin) {
        $hod_id = $_SESSION['teacher_id'] ?? '';
        $struct_sql = "SELECT ds.structure_id, d.department_name, d.department_id 
                       FROM department_structure ds 
                       JOIN departments d ON ds.department_id = d.department_id
                       JOIN teacher_department td ON d.department_id = td.department_id
                       WHERE td.teacher_id = ? AND td.ishod = 1 LIMIT 1";
        
        $stmt_s = $conn->prepare($struct_sql);
        $stmt_s->bind_param("s", $hod_id);
        $stmt_s->execute();
        $struct_res = $stmt_s->get_result()->fetch_assoc();
        
        $dept_struct_id = $struct_res['structure_id'] ?? null;
        $honourse = $struct_res['department_name'] ?? '';
        $context_dept_id = $struct_res['department_id'] ?? null;
    } else {
        $selected_dept_id = $_POST['department_id'] ?? '';
        $admin_map_sql = "SELECT ds.structure_id, d.department_name 
                          FROM department_structure ds 
                          JOIN departments d ON ds.department_id = d.department_id
                          WHERE ds.department_id = ? LIMIT 1";
        
        $stmt_a = $conn->prepare($admin_map_sql);
        $stmt_a->bind_param("i", $selected_dept_id);
        $stmt_a->execute();
        $admin_res = $stmt_a->get_result()->fetch_assoc();
        
        $dept_struct_id = $admin_res['structure_id'] ?? null;
        $honourse = $admin_res['department_name'] ?? '';
        $context_dept_id = $selected_dept_id;
    }

    $mail_sql = "INSERT INTO mail_queue (recipient_name, recipient_email, subject, message, status) VALUES (?, ?, ?, ?, 'pending')";
    $stmtMail = $conn->prepare($mail_sql);

    if($type == "student") {
        if(!$dept_struct_id) {
            die(json_encode(["status" => "error", "message" => "Department context not found."]));
        }

        $adm_date = $_POST['admission_date'];
        $adm_year = (int)date('Y', strtotime($adm_date));
        $cur_month = (int)date('m');
        $cur_year = (int)date('Y');
        $eff_year = ($cur_month < 6) ? ($cur_year - 1) : $cur_year;
        $diff = ($eff_year - $adm_year) + 1;
        $year_label = ($diff <= 1) ? "1st" : (($diff == 2) ? "2nd" : (($diff == 3) ? "3rd" : "4th"));

        $mobile_raw = $_POST['mobile'];
        $password_plain = $mobile_raw . "@" . $year_label;
        $password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);
        $student_name = $_POST['student_name'];
        $email = $_POST['email'];
        $student_id = $_POST['student_id'];

        $sql = "INSERT INTO student_table1 
        (student_id, barcode_no, student_name, father_name, mother_name, mobile, email, DOB, gender, blood_group, 
        aadhaar_number, religion, social_catagory, address, previous_board, 
        program, honourse, year, department_structure_id, admission_date, 
        CLC_serial_no, CLC_date, mark_secured, mark_with_weightage, phase_of_admission, 
        state, PWD_status, Hostel_allot, TC_date, amount, readmission, 
        password, is_first_login, is_profile_complete) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0)";

        try {
            $stmt = $conn->prepare($sql);
            $tc = (!empty($_POST['TC_date'])) ? $_POST['TC_date'] : NULL;
            $stmt->bind_param("ssssssssssssssssssisssssssssssss", 
                $student_id, $_POST['barcode_no'], $student_name, $_POST['father_name'], $_POST['mother_name'], 
                $mobile_raw, $email, $_POST['dob'], $_POST['gender'], $_POST['blood_group'], 
                $_POST['aadhaar_number'], $_POST['religion'], $_POST['social_catagory'], $_POST['address'], 
                $_POST['previous_board'], $_POST['program'], $honourse, $year_label, $dept_struct_id, 
                $adm_date, $_POST['CLC_serial_no'], $_POST['CLC_date'], $_POST['mark_secured'], 
                $_POST['mark_with_weightage'], $_POST['phase_of_admission'], $_POST['state'], 
                $_POST['PWD_status'], $_POST['Hostel_allot'], $tc, $_POST['amount'], 
                $_POST['readmission'], $password_hashed
            );

            if($stmt->execute()) {
                if(!empty($email)) {
                    $subject = "Student Portal Registration - Dhenkanal Autonomous College";
                    $msg_content = "Hello $student_name,\n\nYour student account has been created successfully.\nLogin ID: $student_id\nTemporary Password: $password_plain\n\nPlease login and complete your profile.";
                    $stmtMail->bind_param("ssss", $student_name, $email, $subject, $msg_content);
                    $stmtMail->execute();
                    @file_get_contents("http://" . $_SERVER['HTTP_HOST'] . "/SMS/Backend/mail_worker.php");
                }
                echo json_encode(["status" => "success", "message" => "Student Added. Password: $password_plain"]);
            }
            else echo json_encode(["status" => "error", "message" => "DB Error: " . $conn->error]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit;
    } 

    if($type == "teacher") {
        $t_id = trim($_POST['teacher_id']);
        $t_name = $_POST['teacher_name'];
        $t_email = $_POST['email'];
        
        $check_stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE teacher_id = ?");
        $check_stmt->bind_param("s", $t_id);
        $check_stmt->execute();
        if($check_stmt->get_result()->num_rows > 0) {
            die(json_encode(["status" => "error", "message" => "Teacher ID '$t_id' is already registered."]));
        }

        $mobile = $_POST['mobile'];
        $password_plain = $mobile . "@" . date('Y');
        $password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);
        $role = $_POST['role'] ?? 'teacher';

        $sql = "INSERT INTO teachers 
                (teacher_id, teacher_name, email, mobile, role, date_of_joining, experience_years, 
                qualification, teaching_area, research_area, publications, achievements, 
                orientation_courses, password, isfirstlogin) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssisssssss", 
            $t_id, $t_name, $t_email, $mobile, $role,
            $_POST['date_of_joining'], $_POST['experience_years'], $_POST['qualification'], 
            $_POST['teaching_area'], $_POST['research_area'], $_POST['publications'], 
            $_POST['achievements'], $_POST['orientation_courses'], $password_hashed
        );

        if($stmt->execute()) {
            $is_hod_val = ($role === 'hod') ? 1 : 0;
            $map_stmt = $conn->prepare("INSERT INTO teacher_department (teacher_id, department_id, ishod) VALUES (?, ?, ?)");
            $map_stmt->bind_param("sii", $t_id, $context_dept_id, $is_hod_val);
            $map_stmt->execute();

            if(!empty($t_email)) {
                $subject = "Faculty Account Created - DAC SMS";
                $msg_content = "Hello $t_name,\n\nYour faculty profile has been initialized.\nEmployee ID: $t_id\nTemporary Password: $password_plain\n\nAccess the dashboard to manage your department activities.";
                $stmtMail->bind_param("ssss", $t_name, $t_email, $subject, $msg_content);
                $stmtMail->execute();
                @file_get_contents("http://" . $_SERVER['HTTP_HOST'] . "/SMS/Backend/mail_worker.php");
            }

            echo json_encode(["status" => "success", "message" => "Faculty Added. Password: $password_plain"]);
        } else {
            echo json_encode(["status" => "error", "message" => "DB Error: " . $conn->error]);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration | DAC SMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #14b8a6;
            --primary-glow: rgba(20, 184, 166, 0.2);
            --bg: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.1);
            --input-bg: rgba(15, 23, 42, 0.5);
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: radial-gradient(circle at top left, #1e293b, #0f172a);
            color: var(--text-main);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }

        .card { 
            background: var(--card-bg); 
            backdrop-filter: blur(12px);
            width: 100%; 
            max-width: 950px; 
            padding: 40px; 
            border-radius: 28px; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid var(--border);
            margin-top: 20px;
        }

        .header-section {
            margin-bottom: 35px;
            text-align: center;
        }

        h2 { 
            font-size: 28px; 
            font-weight: 700; 
            margin: 0; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            gap: 15px;
            color: #fff;
        }

        h2 i { 
            background: linear-gradient(135deg, var(--primary), #5eead4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .admin-panel {
            background: rgba(20, 184, 166, 0.05);
            border: 1px dashed var(--primary);
            padding: 24px;
            border-radius: 20px;
            margin-bottom: 35px;
        }

        .admin-title {
            font-size: 11px;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
            gap: 24px; 
            margin-bottom: 24px;
        }

        .form-group { display: flex; flex-direction: column; }

        label { 
            font-size: 11px; 
            font-weight: 700; 
            color: var(--text-muted); 
            margin-bottom: 10px; 
            text-transform: uppercase;
            letter-spacing: 1px;
            padding-left: 4px;
        }

        input, select, textarea { 
            width: 100%; 
            padding: 14px 18px; 
            border: 1px solid var(--border); 
            border-radius: 14px; 
            font-size: 14px; 
            font-family: inherit;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-sizing: border-box;
            background: var(--input-bg);
            color: white;
        }

        input:focus, select:focus, textarea:focus { 
            outline: none; 
            border-color: var(--primary); 
            box-shadow: 0 0 0 4px var(--primary-glow);
            background: rgba(15, 23, 42, 0.8);
        }

        textarea { resize: vertical; }

        select option { background: #1e293b; color: white; }

        .btn-submit { 
            width: 100%; 
            background: linear-gradient(135deg, var(--primary), #0d9488);
            color: white; 
            border: none; 
            padding: 18px; 
            border-radius: 16px; 
            cursor: pointer; 
            font-weight: 700; 
            font-size: 16px;
            margin-top: 10px; 
            transition: all 0.4s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            box-shadow: 0 10px 20px -5px rgba(20, 184, 166, 0.3);
        }

        .btn-submit:hover:not(:disabled) { 
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -5px rgba(20, 184, 166, 0.4);
            filter: brightness(1.1);
        }

        .btn-submit:active { transform: translateY(0); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

        #success-view { 
            display: none; 
            text-align: center; 
            padding: 40px 10px;
        }

        .success-icon {
            width: 90px;
            height: 90px;
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 45px;
            margin: 0 auto 30px;
            border: 2px solid rgba(34, 197, 94, 0.2);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
            70% { transform: scale(1.05); box-shadow: 0 0 0 15px rgba(34, 197, 94, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }

        .secondary-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            color: var(--text-main);
        }

        @media (max-width: 640px) {
            .card { padding: 25px; }
            .grid { grid-template-columns: 1fr; }
            h2 { font-size: 22px; }
        }
    </style>
</head>
<body>

<div class="card" id="form-view">
    <div class="header-section">
        <?php if($role_view === 'student'): ?>
            <h2><i class="fas fa-graduation-cap"></i> Student Enrollment</h2>
        <?php else: ?>
            <h2><i class="fas fa-user-tie"></i> Faculty Onboarding</h2>
        <?php endif; ?>
        <p style="color: var(--text-muted); font-size: 14px; margin-top: 8px;">Create a new secure profile in the departmental database</p>
    </div>

    <form id="userForm">
        <input type="hidden" name="type" value="<?php echo $role_view; ?>">

        <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <div class="admin-panel">
                <div class="admin-title">
                    <i class="fas fa-shield-halved"></i> Global Administrator Override
                </div>
                <div class="form-group">
                    <label>Target Assignment Department</label>
                    <select name="department_id" required>
                        <option value="" disabled selected>Choose department...</option>
                        <?php 
                        $depts = $conn->query("SELECT department_id, department_name FROM departments ORDER BY department_name ASC");
                        while($d = $depts->fetch_assoc()) echo "<option value='{$d['department_id']}'>{$d['department_name']}</option>";
                        ?>
                    </select>
                </div>
            </div>
        <?php endif; ?>

        <?php if($role_view === 'student'): ?>
            <div class="grid">
                <div class="form-group"><label>University ID</label><input type="text" name="student_id" required placeholder="e.g. STU12345"></div>
                <div class="form-group"><label>Barcode / Serial</label><input type="text" name="barcode_no" placeholder="Unique identifier"></div>
                <div class="form-group"><label>Full Legal Name</label><input type="text" name="student_name" required placeholder="As per official docs"></div>
            </div>
            <div class="grid">
                <div class="form-group"><label>Admission Date</label><input type="date" name="admission_date" required></div>
                <div class="form-group"><label>Program Level</label>
                    <select name="program"><option value="UG">Undergraduate (UG)</option><option value="PG">Postgraduate (PG)</option></select>
                </div>
                <div class="form-group"><label>Primary Mobile</label><input type="text" name="mobile" required placeholder="10-digit number"></div>
            </div>
            <div class="grid">
                <div class="form-group"><label>Father's Name</label><input type="text" name="father_name"></div>
                <div class="form-group"><label>Mother's Name</label><input type="text" name="mother_name"></div>
                <div class="form-group"><label>Email Address</label><input type="email" name="email" placeholder="example@college.com"></div>
            </div>
            <div class="grid">
                <div class="form-group"><label>Aadhaar Card</label><input type="text" name="aadhaar_number" maxlength="12" placeholder="XXXX XXXX XXXX"></div>
                <div class="form-group"><label>Date of Birth</label><input type="date" name="dob"></div>
                <div class="form-group"><label>Blood Group</label><input type="text" name="blood_group" placeholder="e.g. O+"></div>
            </div>
            <div class="form-group" style="margin-bottom: 25px;">
                <label>Permanent Residential Address</label>
                <textarea name="address" rows="3" placeholder="Enter complete home address details..."></textarea>
            </div>
            <button type="submit" class="btn-submit">Finalize Enrollment <i class="fas fa-chevron-right"></i></button>

        <?php elseif($role_view === 'teacher'): ?>
            <div class="grid">
                <div class="form-group"><label>Employee ID</label><input type="text" name="teacher_id" required placeholder="e.g. EMP_101"></div>
                <div class="form-group"><label>Full Name</label><input type="text" name="teacher_name" required placeholder="Dr./Prof. Full Name"></div>
                <div class="form-group"><label>Institutional Email</label><input type="email" name="email" required placeholder="staff@college.edu"></div>
            </div>
            <div class="grid">
                <div class="form-group"><label>Mobile Number</label><input type="text" name="mobile" placeholder="Personal contact"></div>
                <div class="form-group"><label>Access Control Role</label>
                    <select name="role">
                        <option value="teacher">Department Faculty</option>
                        <option value="hod">Head of Dept (HOD)</option>
                        <option value="admin">System Administrator</option>
                    </select>
                </div>
                <div class="form-group"><label>Date of Joining</label><input type="date" name="date_of_joining"></div>
            </div>
            <div class="grid">
                <div class="form-group"><label>Total Experience</label><input type="number" name="experience_years" placeholder="In years"></div>
                <div class="form-group" style="grid-column: span 2;"><label>Highest Academic Qualification</label><input type="text" name="qualification" placeholder="e.g. PhD in Computer Science"></div>
            </div>
            <div class="form-group" style="margin-bottom:20px;"><label>Specialized Teaching Area</label><input type="text" name="teaching_area" placeholder="Key subjects handled"></div>
            
            <div class="grid">
                <div class="form-group"><label>Key Publications</label><textarea name="publications" rows="3" placeholder="Journals, books, papers..."></textarea></div>
                <div class="form-group"><label>Primary Research</label><textarea name="research_area" rows="3" placeholder="Current field of research..."></textarea></div>
                <div class="form-group"><label>Major Achievements</label><textarea name="achievements" rows="3" placeholder="Awards and recognition..."></textarea></div>
            </div>
            <button type="submit" class="btn-submit">Initialize Faculty Profile <i class="fas fa-user-plus"></i></button>
        <?php endif; ?>
    </form>
</div>

<div class="card" id="success-view">
    <div class="success-icon"><i class="fas fa-check"></i></div>
    <h2 style="margin-bottom: 15px;">System Updated Successfully</h2>
    <p id="msg" style="color: var(--text-muted); line-height: 1.8; font-size: 15px;"></p>
    
    <div style="margin-top: 40px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
        <button class="btn-submit" style="width: auto; margin-top: 0; padding: 14px 25px;" onclick="location.reload()">
            <i class="fas fa-plus"></i> Enroll Another
        </button>
        <button class="btn-submit secondary-btn" style="width: auto; margin-top: 0; padding: 14px 25px;" onclick="window.history.back()">
            <i class="fas fa-table-columns"></i> Dashboard
        </button>
    </div>
</div>

<script>
document.getElementById('userForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button');
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Encrypting Data...';

    fetch('add_user.php<?php echo "?role=".$role_view; ?>', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            document.getElementById('form-view').style.display = 'none';
            document.getElementById('success-view').style.display = 'block';
            document.getElementById('msg').innerHTML = `The profile was securely integrated into the system.<br><div style='margin-top:15px; padding:15px; background:rgba(20,184,166,0.1); border-radius:12px; color:#5eead4; border:1px solid rgba(20,184,166,0.2)'><strong>Credential Issued:</strong> ${data.message}</div>`;
        } else {
            alert('Validation Error: ' + data.message);
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(err => {
        alert('Connectivity Error: Could not reach the API.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});
</script>
</body>
</html>