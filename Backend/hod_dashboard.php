<?php
session_start();
require 'db.php';

// prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['login'])) {
    header("Location: ../Frontend/teacher_login.html");
    exit();
}
$teacher_id = $_SESSION['teacher_id'];

// --- BROADCAST & FILE UPLOAD LOGIC ---
$show_modal = false;
$queued_count = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_broadcast'])) {
    $title = $_POST['title'];
    $message = $_POST['message'];
    $target_dept = $_POST['dept_id'];
    $course_type = $_POST['course_type'];
    $year_val = $_POST['year'];
    
    $attachment = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = time() . '_' . basename($_FILES['attachment']['name']);
        $target_file = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
            $attachment = $target_file;
        }
    }

    $sql = "SELECT s.student_name, s.email FROM student_table1 s 
            JOIN department_structure ds ON s.department_structure_id = ds.structure_id 
            JOIN programs p ON ds.program_id = p.program_id 
            WHERE ds.department_id = ?";
    
    $params = [$target_dept];
    $types = "i";

    if ($course_type !== 'all') {
        $sql .= " AND p.program_name = ?";
        $params[] = $course_type; $types .= "s";
    }
    if ($year_val !== 'all') {
        $sql .= " AND s.year = ?";
        $params[] = $year_val; $types .= "s";
    }

    $stmt_b = $conn->prepare($sql);
    $stmt_b->bind_param($types, ...$params);
    $stmt_b->execute();
    $students = $stmt_b->get_result();

    $q_stmt = $conn->prepare("INSERT INTO mail_queue (recipient_name, recipient_email, subject, message, attachment_path, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    while ($s = $students->fetch_assoc()) {
        $q_stmt->bind_param("sssss", $s['student_name'], $s['email'], $title, $message, $attachment);
        $q_stmt->execute();
        $queued_count++;
    }
    if ($queued_count > 0) $show_modal = true;
}

// 1. CHECK IF HOD IS ALSO AN ADMIN
$check_admin = $conn->prepare("SELECT admin_id FROM admin WHERE admin_id = ?");
$check_admin->bind_param("s", $teacher_id);
$check_admin->execute();
$admin_result = $check_admin->get_result();
$is_also_admin = $admin_result->num_rows > 0;

if ($is_also_admin) {
    $_SESSION['admin_id'] = $teacher_id;
    $_SESSION['admin_name'] = $_SESSION['teacher_name'];
}
$check_admin->close();

// 2. FETCH HOD DEPARTMENTS AND IDs
$stmt = $conn->prepare("
    SELECT d.department_id, d.department_name
    FROM teacher_department td
    INNER JOIN departments d ON td.department_id = d.department_id
    WHERE td.teacher_id = ? AND td.ishod = 1
");
$stmt->bind_param("s", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$hod_departments = [];
$hod_dept_ids = [];
$dept_data = []; 
while ($row = $result->fetch_assoc()) {
    $hod_departments[] = $row['department_name'];
    $hod_dept_ids[] = $row['department_id'];
    $dept_data[] = $row;
}
$stmt->close();

if (empty($hod_departments)) {
    session_destroy();
    header("Location: ../Frontend/teacher_login.html");
    exit();
}

// 3. FETCH MAIL STATUS FOR HOD DEPARTMENTS
$mail_stats = ['pending' => 0, 'sent' => 0, 'failed' => 0];
if (!empty($hod_dept_ids)) {
    $placeholders = implode(',', array_fill(0, count($hod_dept_ids), '?'));
    
    $q_sql = "SELECT COUNT(*) as count FROM mail_queue mq 
              JOIN student_table1 s ON mq.recipient_email = s.email 
              WHERE s.department_structure_id IN (SELECT structure_id FROM department_structure WHERE department_id IN ($placeholders)) AND mq.status='pending'";
    $q_stmt = $conn->prepare($q_sql);
    $q_stmt->bind_param(str_repeat('i', count($hod_dept_ids)), ...$hod_dept_ids);
    $q_stmt->execute();
    $mail_stats['pending'] = $q_stmt->get_result()->fetch_assoc()['count'];

    $l_sql = "SELECT ml.status, COUNT(*) as count FROM mail_log ml 
              JOIN student_table1 s ON ml.recipient_email = s.email 
              WHERE s.department_structure_id IN (SELECT structure_id FROM department_structure WHERE department_id IN ($placeholders)) GROUP BY ml.status";
    $l_stmt = $conn->prepare($l_sql);
    $l_stmt->bind_param(str_repeat('i', count($hod_dept_ids)), ...$hod_dept_ids);
    $l_stmt->execute();
    $l_res = $l_stmt->get_result();
    while($r = $l_res->fetch_assoc()) {
        $status = ($r['status'] == 'error') ? 'failed' : $r['status'];
        $mail_stats[$status] = $r['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HOD Control Panel</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --primary: #3b82f6;
    --primary-dark: #2563eb;
    --bg-dark: #0f172a;
    --sidebar-bg: #1e293b;
    --card-bg: rgba(255, 255, 255, 0.03);
    --card-border: rgba(255, 255, 255, 0.1);
    --text-main: #f8fafc;
    --text-dim: #94a3b8;
    --glass: rgba(30, 41, 59, 0.7);
}

* { box-sizing: border-box; transition: all 0.2s ease; }
body { 
    margin: 0; 
    font-family: 'Plus Jakarta Sans', sans-serif; 
    background-color: var(--bg-dark); 
    background-image: radial-gradient(circle at 100% 0%, rgba(37, 99, 235, 0.08) 0%, transparent 30%);
    color: var(--text-main);
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: 280px;
    background: var(--sidebar-bg);
    border-right: 1px solid var(--card-border);
    padding: 2rem 1.5rem;
    display: flex;
    flex-direction: column;
    position: fixed;
    height: 100vh;
    z-index: 1001;
}

.sidebar h2 { 
    font-size: 1.1rem; 
    font-weight: 800;
    margin-bottom: 2.5rem; 
    display: flex; align-items: center; gap: 10px;
    color: white;
}
.sidebar h2 i { color: var(--primary); }

.nav-links { list-style: none; padding: 0; flex-grow: 1; display: flex; flex-direction: column; gap: 8px; }

.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    color: var(--text-dim);
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
}

.nav-link:hover, .nav-link.active {
    background: rgba(255,255,255,0.05);
    color: white;
}
.nav-link.active { background: var(--primary); color: white; box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.2); }

.admin-switch-link {
    background: rgba(239, 68, 68, 0.1) !important;
    border: 1px solid rgba(239, 68, 68, 0.2);
    color: #f87171 !important;
    margin-top: 20px;
}

.logout-section { border-top: 1px solid var(--card-border); padding-top: 20px; }

.btn-logout {
    background: rgba(239, 68, 68, 0.1);
    color: #f87171;
    display: flex;
    align-items: center; gap: 10px;
    justify-content: center;
    padding: 12px;
    text-decoration: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.9rem;
}
.btn-logout:hover { background: #ef4444; color: white; }

/* Main Area */
.main-content {
    margin-left: 280px;
    width: calc(100% - 280px);
    padding: 2.5rem;
}

.top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2.5rem;
}
.top-bar h1 { font-weight: 800; letter-spacing: -1px; font-size: 1.8rem; }

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 2rem;
}

.card {
    background: var(--glass);
    backdrop-filter: blur(10px);
    border: 1px solid var(--card-border);
    border-radius: 24px;
    padding: 2rem;
}

/* Profile Card */
.profile-card { text-align: center; }
.avatar-circle {
    width: 90px; height: 90px;
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    color: white;
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    margin: 0 auto 1.2rem;
    font-weight: 800;
    box-shadow: 0 15px 30px rgba(0,0,0,0.3);
}

.dept-tag {
    background: rgba(59, 130, 246, 0.1);
    color: #93c5fd;
    border: 1px solid rgba(59, 130, 246, 0.2);
    padding: 6px 14px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 700;
    display: inline-block;
    margin: 4px;
}

/* Mail Status Table */
.mail-stat-row {
    display: flex;
    justify-content: space-between;
    padding: 14px 0;
    border-bottom: 1px solid var(--card-border);
}
.mail-stat-row:last-child { border-bottom: none; }
.mail-stat-row span { font-weight: 600; color: var(--text-dim); }
.mail-count { font-weight: 800; color: white; font-size: 1.1rem; }

/* Broadcast Form */
.broadcast-form label { font-size: 0.75rem; font-weight: 700; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px; }
.broadcast-form input, .broadcast-form select, .broadcast-form textarea {
    width: 100%; padding: 12px; margin-top: 6px; 
    background: rgba(15, 23, 42, 0.5);
    border: 1px solid var(--card-border); border-radius: 10px;
    color: white; font-family: inherit;
}
.broadcast-form input:focus, .broadcast-form select:focus { border-color: var(--primary); outline: none; }

.btn-broadcast {
    background: var(--primary); color: white; border: none; padding: 15px; border-radius: 12px; 
    font-weight: 700; cursor: pointer; margin-top: 20px; width: 100%;
    box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3);
}
.btn-broadcast:hover { transform: translateY(-2px); background: var(--primary-dark); }

/* Modal */
.modal-bg { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.8); display: <?php echo $show_modal ? 'flex' : 'none'; ?>; align-items: center; justify-content: center; z-index: 2000; backdrop-filter: blur(5px); }
.modal { background: #1e293b; padding: 40px; border-radius: 24px; text-align: center; max-width: 400px; border: 1px solid var(--card-border); }
.modal i { font-size: 4rem; color: #10b981; margin-bottom: 20px; }
.modal-btn { background: #10b981; color: white; padding: 12px 35px; border-radius: 10px; text-decoration: none; font-weight: 800; display: inline-block; margin-top: 20px; }

@media (max-width: 1024px) {
    .sidebar { width: 85px; padding: 2rem 0.5rem; }
    .sidebar h2 span, .nav-link span, .btn-logout span { display: none; }
    .main-content { margin-left: 85px; width: calc(100% - 85px); }
    .dashboard-grid { grid-template-columns: 1fr; }
}
</style>
</head>

<body>

<div class="modal-bg" id="successModal">
    <div class="modal">
        <i class="fas fa-circle-check"></i>
        <h2 style="margin-bottom:10px;">Broadcast Queued</h2>
        <p style="color:var(--text-dim);">Successfully added <b><?php echo $queued_count; ?></b> students to the mailing delivery system.</p>
        <a href="javascript:void(0)" onclick="document.getElementById('successModal').style.display='none'" class="modal-btn">Dismiss</a>
    </div>
</div>

<nav class="sidebar">
    <h2><i class="fas fa-shield-halved"></i> <span>HOD PORTAL</span></h2>
    <ul class="nav-links">
        <li><a href="#" class="nav-link active"><i class="fas fa-chart-pie"></i> <span>Overview</span></a></li>
        <li><a href="../Backend/Curd_Operation/manage_teacher.php" class="nav-link"><i class="fas fa-user-tie"></i> <span>Teachers</span></a></li>
        <li><a href="../Backend/Curd_Operation/manage_student.php" class="nav-link"><i class="fas fa-users-viewfinder"></i> <span>Students</span></a></li>

        <?php if ($is_also_admin): ?>
            <li>
                <a href="admin_dashboard.php" class="nav-link admin-switch-link">
                     <i class="fas fa-key"></i> <span>Admin Access</span>
                </a>
            </li>
        <?php endif; ?>

        <li><a href="view_mail_queue.php" class="nav-link"><i class="fas fa-clock-rotate-left"></i> <span>Mail Logs</span></a></li>
    </ul>
    <div class="logout-section">
        <a href="logout.php" class="btn-logout">
            <i class="fas fa-power-off"></i>
            <span>Logout</span>
        </a>
    </div>
</nav>

<main class="main-content">
    <div class="top-bar">
        <h1>Dashboard</h1>
        <div id="date-now" style="font-weight: 700; color: var(--text-dim); font-size: 0.9rem;"></div>
    </div>

    <div class="dashboard-grid">
        <div class="card profile-card">
            <div class="avatar-circle">
                <?php echo strtoupper(substr($_SESSION['teacher_name'] ?? 'U', 0, 1)); ?>
            </div>
            <h2 style="margin-bottom:5px; font-weight:800;"><?php echo htmlspecialchars($_SESSION['teacher_name'] ?? 'User'); ?></h2>
            <p style="color:var(--text-dim); font-size:0.9rem; margin-bottom: 20px;">
                HOD ID: <?php echo $teacher_id; ?>
                <?php if($is_also_admin) echo ' • <span style="color:#f87171; font-weight:800;">Administrator</span>'; ?>
            </p>
            <div style="padding-top:20px; border-top:1px solid var(--card-border);">
                <label style="font-size:0.7rem; text-transform:uppercase; color:var(--text-dim); letter-spacing:1px; display:block; margin-bottom:10px;">Managed Departments</label>
                <?php foreach ($hod_departments as $dept): ?>
                    <span class="dept-tag"><?php echo htmlspecialchars($dept); ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-top:0; font-size:1rem; display:flex; align-items:center; gap:10px; color:var(--primary);">
                <i class="fas fa-server"></i> Delivery Status
            </h3>
            <p style="font-size: 0.8rem; color: var(--text-dim); margin-bottom: 25px;">Tracking automated department communications.</p>
            
            <div class="mail-stat-row">
                <span>Pending Queue</span>
                <span class="mail-count" style="color: var(--primary);"><?php echo $mail_stats['pending']; ?></span>
            </div>
            <div class="mail-stat-row">
                <span>Successful</span>
                <span class="mail-count" style="color: #10b981;"><?php echo $mail_stats['sent']; ?></span>
            </div>
            <div class="mail-stat-row">
                <span>Failed Logs</span>
                <span class="mail-count" style="color: #ef4444;"><?php echo $mail_stats['failed']; ?></span>
            </div>

            <a href="view_mail_queue.php" style="display: block; text-align: center; margin-top: 25px; text-decoration: none; color: white; background:rgba(255,255,255,0.05); padding:10px; border-radius:10px; font-weight: 700; font-size: 0.85rem;">
                Audit Full Logs <i class="fas fa-arrow-right" style="font-size: 0.7rem; margin-left: 8px;"></i>
            </a>
        </div>

        <div class="card" style="grid-column: span 2;">
            <h3 style="margin-top:0; color:var(--primary); display:flex; align-items:center; gap:10px;">
                <i class="fas fa-bullhorn"></i> Department Broadcast
            </h3>
            <form method="POST" enctype="multipart/form-data" class="broadcast-form" style="margin-top:20px;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div>
                        <label>Target Department</label>
                        <select name="dept_id" required>
                            <?php foreach($dept_data as $d): ?>
                                <option value="<?php echo $d['department_id']; ?>"><?php echo $d['department_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Level</label>
                        <select name="course_type" id="courseType" onchange="updateBatchOptions()">
                            <option value="all">Mixed (UG/PG)</option>
                            <option value="UG">Undergraduate</option>
                            <option value="PG">Postgraduate</option>
                        </select>
                    </div>
                    <div>
                        <label>Batch Year</label>
                        <select name="year" id="yearSelect">
                            <option value="all">All Academic Years</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
                    <div>
                        <label>Subject / Headline</label>
                        <input type="text" name="title" placeholder="Important notice regarding..." required>
                    </div>
                    <div>
                        <label>File Attachment</label>
                        <input type="file" name="attachment" style="padding: 8px;">
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <label>Notice Description</label>
                    <textarea name="message" rows="4" placeholder="Draft your detailed announcement here..." required></textarea>
                </div>
                
                <button type="submit" name="send_broadcast" class="btn-broadcast">
                    <i class="fas fa-paper-plane"></i> Launch Broadcast
                </button>
            </form>
        </div>
    </div>
</main>

<script>
document.getElementById('date-now').innerText =
    new Date().toLocaleDateString('en-US', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    }).toUpperCase();

function updateBatchOptions() {
    const course = document.getElementById('courseType').value;
    const yearSelect = document.getElementById('yearSelect');
    yearSelect.innerHTML = '<option value="all">All Academic Years</option>';
    
    let options = (course === 'PG') ? ['1st', '2nd'] : ['1st', '2nd', '3rd', '4th'];

    options.forEach(val => {
        let opt = document.createElement('option');
        opt.value = val;
        opt.innerHTML = val + ' Year';
        yearSelect.appendChild(opt);
    });
}
updateBatchOptions();
</script>

</body>
</html>