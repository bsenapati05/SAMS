<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once "../db.php";
require_once "access_control.php";

// Security Check: Only HOD or Admin can update users
if(isTeacher() && !isHOD()) die(json_encode(["status" => "error", "message" => "Access Denied"]));

$hod_dept = $_SESSION['department_structure_id'] ?? null;

function formatForInput($dateString) {
    if (empty($dateString) || $dateString == '0000-00-00' || $dateString == 'NULL') return "";
    $cleanDate = preg_replace('/(\d+)(st|nd|rd|th)/i', '$1', $dateString);
    $timestamp = strtotime($cleanDate);
    return $timestamp ? date('Y-m-d', $timestamp) : "";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header("Content-Type: application/json");
    $type = $_POST['type'] ?? '';
    $id = $_POST['id'] ?? '';

    if($type == "student"){
        $sql = "UPDATE student_table1 SET 
                barcode_no=?, student_name=?, father_name=?, mother_name=?, mobile=?, email=?, 
                DOB=?, gender=?, blood_group=?, social_catagory=?, religion=?, 
                address=?, previous_board=?, CLC_serial_no=?, CLC_date=?, 
                mark_secured=?, mark_with_weightage=?, state=?, PWD_status=?, 
                Hostel_allot=?, TC_date=?, amount=?, readmission=?, aadhaar_number=?
                WHERE student_id=?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssssssssssssssssss", 
            $_POST['barcode_no'], $_POST['student_name'], $_POST['father_name'], $_POST['mother_name'], 
            $_POST['mobile'], $_POST['email'], $_POST['DOB'], $_POST['gender'], 
            $_POST['blood_group'], $_POST['social_catagory'], $_POST['religion'], 
            $_POST['address'], $_POST['previous_board'], $_POST['CLC_serial_no'], $_POST['CLC_date'], 
            $_POST['mark_secured'], $_POST['mark_with_weightage'], $_POST['state'], $_POST['PWD_status'], 
            $_POST['Hostel_allot'], $_POST['TC_date'], $_POST['amount'], $_POST['readmission'], 
            $_POST['aadhaar_number'], $id
        );

        if($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Student Profile Updated Successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => $conn->error]);
        }
        exit;
    } 
    
    elseif($type == "teacher") {
        $sql = "UPDATE teachers SET 
                teacher_name=?, email=?, mobile=?, role=?, date_of_joining=?, 
                experience_years=?, qualification=?, teaching_area=?, research_area=?, 
                publications=?, achievements=?, orientation_courses=?
                WHERE teacher_id=?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssssss", 
            $_POST['teacher_name'], $_POST['email'], $_POST['mobile'], $_POST['role'], 
            $_POST['date_of_joining'], $_POST['experience_years'], $_POST['qualification'], 
            $_POST['teaching_area'], $_POST['research_area'], $_POST['publications'], 
            $_POST['achievements'], $_POST['orientation_courses'], $id
        );

        if($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Teacher Profile Updated Successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => $conn->error]);
        }
        exit;
    }
}

$type = $_GET['type'] ?? 'student';
$id = $_GET['id'] ?? '';
$user = [];

if ($id) {
    $table = ($type == 'student') ? 'student_table1' : 'teachers';
    $col = ($type == 'student') ? 'student_id' : 'teacher_id';
    $stmt = $conn->prepare("SELECT * FROM $table WHERE $col = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Record Update | SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-body: #121921;
            --bg-card: #1c252e;
            --accent-mint: #63d9b0;
            --text-main: #e2e8f0;
            --text-muted: #94a3b8;
            --input-bg: #161e26;
            --border: #2d3748;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
        }

        .main-container {
            width: 100%;
            max-width: 980px;
            background: var(--bg-card);
            border-radius: 20px;
            padding: 45px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        /* Header Style */
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            background: rgba(99, 217, 176, 0.1);
            color: var(--accent-mint);
            font-size: 1.8rem;
            border-radius: 14px;
            margin-bottom: 15px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 600;
        }

        .page-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin: 10px 0 0;
        }

        /* Dash Border Section (Admin Override Style) */
        .admin-box {
            border: 1px dashed rgba(99, 217, 176, 0.35);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 40px;
            position: relative;
        }

        .box-tag {
            position: absolute;
            top: -10px;
            left: 20px;
            background: var(--bg-card);
            padding: 0 10px;
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--accent-mint);
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }

        /* Form Layout */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input, select, textarea {
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            color: #fff;
            font-size: 0.95rem;
            transition: 0.2s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--accent-mint);
            box-shadow: 0 0 0 4px rgba(99, 217, 176, 0.1);
        }

        .readonly-field {
            opacity: 0.5;
            cursor: not-allowed;
            background: #0d131a;
        }

        .section-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-muted);
            margin: 35px 0 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-label::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* Footer Buttons */
        .form-footer {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .btn {
            padding: 14px 45px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            font-size: 0.85rem;
            text-transform: uppercase;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-mint {
            background: var(--accent-mint);
            color: #121921;
        }

        .btn-mint:hover {
            background: #4ec49d;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(99, 217, 176, 0.3);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border);
        }

        .btn-outline:hover {
            color: #fff;
            background: rgba(255,255,255,0.05);
        }

        @media (max-width: 850px) { .form-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="main-container">
    <div class="page-header">
        <div class="header-icon"><i class="fas fa-user-edit"></i></div>
        <h1>Update <?php echo ucfirst($type); ?> Profile</h1>
        <p>Modify secure profile data in the departmental database</p>
    </div>

    <form id="updateUserForm">
        <input type="hidden" name="type" value="<?php echo $type; ?>">
        <input type="hidden" name="id" value="<?php echo $id; ?>">

        <div class="admin-box">
            <span class="box-tag"><i class="fas fa-shield-alt"></i> Global Record Identity</span>
            <div class="form-grid" style="margin-bottom: 0;">
                <div class="form-group">
                    <label>Internal System ID</label>
                    <input type="text" class="readonly-field" value="<?php echo $id; ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Barcode / Serial</label>
                    <input type="text" name="barcode_no" value="<?php echo $user['barcode_no'] ?? ''; ?>" placeholder="Unique identifier">
                </div>
                <div class="form-group">
                    <label>Aadhaar Number</label>
                    <input type="text" name="aadhaar_number" value="[Aadhaar Redacted]" placeholder="12-digit number">
                </div>
            </div>
        </div>

        <?php if($type == 'student'): ?>
            <div class="section-label">Primary Information</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Full Legal Name</label>
                    <input type="text" name="student_name" value="<?php echo $user['student_name'] ?? ''; ?>" placeholder="As per official docs" required>
                </div>
                <div class="form-group">
                    <label>Primary Mobile</label>
                    <input type="text" name="mobile" value="<?php echo $user['mobile'] ?? ''; ?>" placeholder="10-digit number">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?php echo $user['email'] ?? ''; ?>" placeholder="example@college.com">
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="DOB" value="<?php echo formatForInput($user['DOB'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="Male" <?= ($user['gender']=='Male')?'selected':'' ?>>Male</option>
                        <option value="Female" <?= ($user['gender']=='Female')?'selected':'' ?>>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Blood Group</label>
                    <input type="text" name="blood_group" value="<?php echo $user['blood_group'] ?? ''; ?>">
                </div>
            </div>

            <div class="section-label">Academic Credentials</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>CLC Serial No</label>
                    <input type="text" name="CLC_serial_no" value="<?php echo $user['CLC_serial_no'] ?? ''; ?>">
                </div>
                <div class="form-group">
                    <label>CLC Issuance Date</label>
                    <input type="date" name="CLC_date" value="<?php echo formatForInput($user['CLC_date'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>TC Issuance Date</label>
                    <input type="date" name="TC_date" value="<?php echo formatForInput($user['TC_date'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group" style="margin-top: 25px;">
                <label>Current Residential Address</label>
                <textarea name="address" rows="3"><?php echo $user['address'] ?? ''; ?></textarea>
            </div>

            <input type="hidden" name="father_name" value="<?php echo $user['father_name'] ?? ''; ?>">
            <input type="hidden" name="mother_name" value="<?php echo $user['mother_name'] ?? ''; ?>">
            <input type="hidden" name="religion" value="<?php echo $user['religion'] ?? ''; ?>">
            <input type="hidden" name="social_catagory" value="<?php echo $user['social_catagory'] ?? ''; ?>">
            <input type="hidden" name="previous_board" value="<?php echo $user['previous_board'] ?? ''; ?>">
            <input type="hidden" name="mark_secured" value="<?php echo $user['mark_secured'] ?? ''; ?>">
            <input type="hidden" name="mark_with_weightage" value="<?php echo $user['mark_with_weightage'] ?? ''; ?>">
            <input type="hidden" name="state" value="<?php echo $user['state'] ?? ''; ?>">
            <input type="hidden" name="PWD_status" value="<?php echo $user['PWD_status'] ?? ''; ?>">
            <input type="hidden" name="Hostel_allot" value="<?php echo $user['Hostel_allot'] ?? ''; ?>">
            <input type="hidden" name="amount" value="<?php echo $user['amount'] ?? ''; ?>">
            <input type="hidden" name="readmission" value="<?php echo $user['readmission'] ?? ''; ?>">

        <?php else: ?>
            <div class="section-label">Professional Profile</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="teacher_name" value="<?php echo $user['teacher_name'] ?? ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Assigned Role</label>
                    <select name="role">
                        <option value="teacher" <?= ($user['role']=='teacher')?'selected':'' ?>>Teacher</option>
                        <option value="hod" <?= ($user['role']=='hod')?'selected':'' ?>>HOD</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Employment Email</label>
                    <input type="email" name="email" value="<?php echo $user['email'] ?? ''; ?>" required>
                </div>
            </div>
            <?php endif; ?>

        <div class="form-footer">
            <button type="button" class="btn btn-outline" onclick="window.history.back()">Discard Changes</button>
            <button type="submit" class="btn btn-mint" id="saveBtn">Confirm Update</button>
        </div>
    </form>
</div>

<script>
document.getElementById('updateUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('saveBtn');
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Processing...';
    btn.disabled = true;

    fetch('update_user.php', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            alert(data.message);
            window.history.back();
        } else {
            alert('Error: ' + data.message);
            btn.innerHTML = 'Confirm Update';
            btn.disabled = false;
        }
    })
    .catch(err => {
        alert('Network error occurred.');
        btn.disabled = false;
    });
});
</script>

</body>
</html>