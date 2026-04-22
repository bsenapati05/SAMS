<?php
session_start();
require_once 'db.php';

// 1. ACCESS CONTROL
if (!isset($_SESSION['login'])) {
    header("Location: ../Frontend/teacher_login.html");
    exit();
}

$user_role = $_SESSION['role'] ?? 'hod';
$user_id = $_SESSION['teacher_id'] ?? '';

// Identify HOD's Department automatically
$hod_dept_id = null;
$hod_dept_name = "";
if ($user_role === 'hod') {
    $stmt_hod = $conn->prepare("SELECT d.department_id, d.department_name FROM departments d 
                                JOIN teacher_department td ON d.department_id = td.department_id 
                                WHERE td.teacher_id = ? AND td.ishod = 1 LIMIT 1");
    $stmt_hod->bind_param("s", $user_id);
    $stmt_hod->execute();
    $res_hod = $stmt_hod->get_result()->fetch_assoc();
    if ($res_hod) {
        $hod_dept_id = $res_hod['department_id'];
        $hod_dept_name = $res_hod['department_name'];
    }
}

$show_modal = false;
$queued_count = 0;

// 2. HANDLE BROADCAST LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_broadcast'])) {
    $title = $_POST['title'];
    $message = $_POST['message'];
    $scope = $_POST['scope'] ?? 'dept'; 
    $course_type = $_POST['course_type'] ?? 'all'; // UG or PG
    $year_val = $_POST['year'] ?? 'all'; // 1st, 2nd, etc.
    $target_dept = ($user_role === 'admin' && $scope !== 'all') ? $_POST['dept_id'] : $hod_dept_id;

    // Build query with Joins to filter by Program (UG/PG) and Department
    $sql = "SELECT s.student_name, s.email FROM student_table1 s 
            JOIN department_structure ds ON s.department_structure_id = ds.structure_id 
            JOIN programs p ON ds.program_id = p.program_id";
    
    $where = [];
    $params = [];
    $types = "";

    if ($user_role === 'admin' && $scope === 'all') {
        // No filters needed for entire college
    } else {
        if ($target_dept) {
            $where[] = "ds.department_id = ?";
            $params[] = $target_dept;
            $types .= "i";
        }
        if ($course_type !== 'all') {
            $where[] = "p.program_name = ?";
            $params[] = $course_type;
            $types .= "s";
        }
        if ($year_val !== 'all') {
            $where[] = "s.year = ?";
            $params[] = $year_val;
            $types .= "s";
        }
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $students = $stmt->get_result();

    // Push to Mail Queue
    $q_stmt = $conn->prepare("INSERT INTO mail_queue (recipient_name, recipient_email, subject, message, status) VALUES (?, ?, ?, ?, 'pending')");
    while ($s = $students->fetch_assoc()) {
        $q_stmt->bind_param("ssss", $s['student_name'], $s['email'], $title, $message);
        $q_stmt->execute();
        $queued_count++;
    }

    if ($queued_count > 0) { $show_modal = true; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Broadcast Center | DAC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h2 { color: #1e3c72; margin-top: 0; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; }
        input[type="text"], textarea, select { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        .btn-send { background: #1e3c72; color: white; border: none; padding: 15px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; font-size: 1rem; }
        .btn-send:hover { background: #2a5298; }
        .info-box { background: #e0f2fe; color: #0369a1; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.9rem; }
        
        /* Modal Style */
        .modal-bg { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); display: <?php echo $show_modal ? 'flex' : 'none'; ?>; align-items: center; justify-content: center; z-index: 1000; }
        .modal { background: #fff; padding: 40px; border-radius: 12px; text-align: center; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .modal i { font-size: 4rem; color: #22c55e; margin-bottom: 20px; }
        .modal h3 { margin: 0 0 10px; color: #1e293b; }
        .modal p { color: #64748b; margin-bottom: 25px; }
        .modal-btn { background: #22c55e; color: white; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="modal-bg" id="successModal">
    <div class="modal">
        <i class="fas fa-check-circle"></i>
        <h3>Broadcast Queued!</h3>
        <p>Successfully added <b><?php echo $queued_count; ?></b> messages to the queue. You can now send them from the logs.</p>
        <a href="javascript:void(0)" onclick="closeModal()" class="modal-btn">Done</a>
    </div>
</div>

<div class="container">
    <a href="javascript:history.back()" style="text-decoration:none; color:#64748b;"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    <h2><i class="fas fa-bullhorn"></i> Broadcast Notification</h2>

    <form method="POST" action="">
        <div class="form-group">
            <label>Notice Title / Subject</label>
            <input type="text" name="title" placeholder="Enter subject of the notice" required>
        </div>

        <?php if($user_role === 'admin'): ?>
        <div class="form-group">
            <label>Target Scope</label>
            <select name="scope" id="scopeSelect" onchange="toggleAdminFilters()">
                <option value="all">Entire College (All Students)</option>
                <option value="dept">Specific Department</option>
            </select>
        </div>
        <?php endif; ?>

        <div id="filterSection" style="<?php echo ($user_role === 'admin') ? 'display:none;' : ''; ?>">
            <?php if($user_role === 'admin'): ?>
            <div class="form-group">
                <label>Select Department</label>
                <select name="dept_id">
                    <?php
                    $depts = $conn->query("SELECT department_id, department_name FROM departments");
                    while($d = $depts->fetch_assoc()) {
                        echo "<option value='{$d['department_id']}'>{$d['department_name']}</option>";
                    }
                    ?>
                </select>
            </div>
            <?php else: ?>
                <div class="info-box"><i class="fas fa-building"></i> Broadcasting to: <b><?php echo $hod_dept_name; ?></b></div>
            <?php endif; ?>

            <div class="form-group">
                <label>Course Type</label>
                <select name="course_type" id="courseType" onchange="updateBatchOptions()">
                    <option value="all">All (UG & PG)</option>
                    <option value="UG">Undergraduate (UG)</option>
                    <option value="PG">Postgraduate (PG)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Select Batch / Year</label>
                <select name="year" id="yearSelect">
                    <option value="all">All Years</option>
                    </select>
            </div>
        </div>

        <div class="form-group">
            <label>Message Content</label>
            <textarea name="message" rows="6" placeholder="Write your announcement here..." required></textarea>
        </div>

        <button type="submit" name="send_broadcast" class="btn-send">
            <i class="fas fa-tasks"></i> Queue Broadcast Notice
        </button>
    </form>
</div>

<script>
function toggleAdminFilters() {
    const scope = document.getElementById('scopeSelect').value;
    document.getElementById('filterSection').style.display = (scope === 'all') ? 'none' : 'block';
}

function updateBatchOptions() {
    const course = document.getElementById('courseType').value;
    const yearSelect = document.getElementById('yearSelect');
    
    // Clear existing
    yearSelect.innerHTML = '<option value="all">All Years</option>';
    
    let options = [];
    if (course === 'UG') {
        options = ['1st', '2nd', '3rd', '4th'];
    } else if (course === 'PG') {
        options = ['1st', '2nd'];
    } else {
        options = ['1st', '2nd', '3rd', '4th'];
    }

    options.forEach(val => {
        let opt = document.createElement('option');
        opt.value = val;
        opt.innerHTML = val + ' Year';
        yearSelect.appendChild(opt);
    });
}

function closeModal() {
    document.getElementById('successModal').style.display = 'none';
}

// Initialize Batch options on load
updateBatchOptions();
</script>
</body>
</html>