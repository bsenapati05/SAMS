<?php
session_start();
require_once 'db.php';

// Prevent browser caching after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if logged in - Now allowing both admin and hod to use this dashboard
if (!isset($_SESSION['login']) || !in_array($_SESSION['role'], ['admin', 'hod'])) {
    header("Location: admin_login.html");
    exit();
}

$user_role  = $_SESSION['role'];

// Logic to identify the ID and Name based on who is logged in
if ($user_role === 'admin') {
    $admin_id   = $_SESSION['admin_id'];
    $admin_name = $_SESSION['admin_name'];
} else {
    $admin_id   = $_SESSION['teacher_id'];
    $admin_name = $_SESSION['teacher_name'];
}

// If HOD, fetch their department context
$my_dept = "";
$my_dept_id = null;
if ($user_role === 'hod') {
    $stmt = $conn->prepare("SELECT d.department_id, d.department_name FROM departments d 
                            JOIN teacher_department td ON d.department_id = td.department_id 
                            WHERE td.teacher_id = ? AND td.ishod = 1");
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if($row = $res->fetch_assoc()) {
        $my_dept = $row['department_name'];
        $my_dept_id = $row['department_id'];
    }
}

// --- MAIL STATUS LOGIC ---
$mail_stats = ['pending' => 0, 'sent' => 0, 'failed' => 0];
if ($user_role === 'admin') {
    $res = $conn->query("SELECT status, COUNT(*) as count FROM mail_queue GROUP BY status");
    while($r = $res->fetch_assoc()) { $mail_stats[$r['status']] = $r['count']; }
} else {
    $m_stmt = $conn->prepare("SELECT mq.status, COUNT(*) as count 
                            FROM mail_queue mq 
                            JOIN student_table1 s ON mq.recipient_email = s.email 
                            WHERE s.department_structure_id IN (SELECT structure_id FROM department_structure WHERE department_id = ?) 
                            GROUP BY mq.status");
    $m_stmt->bind_param("i", $my_dept_id);
    $m_stmt->execute();
    $m_res = $m_stmt->get_result();
    while($r = $m_res->fetch_assoc()) { $mail_stats[$r['status']] = $r['count']; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($user_role); ?> Dashboard | SAMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #14b8a6;
            --primary-dark: #0f766e;
            --bg-dark: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --danger: #ef4444;
            --feedback-accent: #8b5cf6;
        }

        * { box-sizing: border-box; transition: all 0.2s ease; }
        
        body { 
            margin: 0; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg-dark); 
            background-image: radial-gradient(circle at 100% 0%, rgba(20, 184, 166, 0.08) 0%, transparent 40%);
            color: var(--text-main);
            min-height: 100vh;
        }

        .navbar { 
            background: #1e293b; 
            padding: 1.2rem 5%; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 1px solid rgba(255,255,255,0.1);
            position: sticky; top: 0; z-index: 100;
        }
        
        .navbar h2 { margin: 0; font-size: 1.2rem; font-weight: 800; letter-spacing: -1px; }
        .navbar h2 i { color: var(--primary); margin-right: 8px; }

        .logout-btn { 
            background: var(--danger); color: #fff; padding: 8px 16px; 
            text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 0.85rem;
        }

        .container { 
            padding: 2rem 5%; 
            max-width: 1400px; 
            margin: 0 auto; 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); 
            gap: 1.5rem; 
        }
        
        .card { 
            background: var(--card-bg); 
            backdrop-filter: blur(12px);
            padding: 2rem; border-radius: 24px; 
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);
        }
        
        .card h3 { 
            margin-top: 0; color: var(--primary); font-size: 1.1rem; font-weight: 700;
            display: flex; align-items: center; gap: 10px; border-bottom: 1px solid rgba(255,255,255,0.05);
            padding-bottom: 12px; margin-bottom: 20px;
        }

        .mail-stats-container { display: grid; gap: 8px; margin: 15px 0; }
        .mail-stat { 
            display: flex; justify-content: space-between; padding: 12px; 
            background: rgba(255,255,255,0.03); border-radius: 12px; font-size: 0.9rem;
        }
        .stat-count { font-weight: 800; }
        .text-pending { color: #f59e0b; }
        .text-sent { color: var(--primary); }
        .text-failed { color: var(--danger); }

        .broadcast-form input, .broadcast-form select, .broadcast-form textarea {
            width: 100%; padding: 12px; margin-top: 10px; border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px; background: rgba(0,0,0,0.2); color: white; font-family: inherit;
        }
        .broadcast-form input:focus { border-color: var(--primary); outline: none; }

        .btn-primary { 
            background: var(--primary); color: white; border: none; padding: 14px; 
            border-radius: 12px; cursor: pointer; width: 100%; font-weight: 700; margin-top: 15px;
        }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }

        .manage-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }
        .btn-manage { 
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); color: var(--text-main); 
            padding: 20px; border-radius: 18px; cursor: pointer; font-weight: 700; font-size: 0.85rem;
            display: flex; flex-direction: column; align-items: center; gap: 10px;
        }
        .btn-manage i { font-size: 1.5rem; color: var(--primary); }
        .btn-manage:hover { background: var(--primary); border-color: var(--primary); color: white; transform: translateY(-3px); }
        .btn-manage:hover i { color: white; }

        .btn-feedback { border-color: var(--feedback-accent); }
        .btn-feedback i { color: var(--feedback-accent); }
        .btn-feedback:hover { background: var(--feedback-accent); border-color: var(--feedback-accent); }

        .upload-trigger-btn { 
            background: transparent; border: 1px solid var(--primary); color: var(--primary); 
            padding: 12px; border-radius: 12px; cursor: pointer; width: 100%; font-weight: 700;
        }
        .upload-trigger-btn:hover { background: var(--primary); color: white; }

        .upload-area { display: none; margin-top: 15px; background: rgba(20, 184, 166, 0.05); border: 1px dashed var(--primary); border-radius: 12px; padding: 15px; }

        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(8px); }
        .modal-content { background: #1e293b; padding: 30px; border-radius: 24px; text-align: center; width: 90%; max-width: 400px; border: 1px solid rgba(255,255,255,0.1); }
        
        #modal-body { white-space: pre-line; line-height: 1.5; color: var(--text-dim); }

        .view-mail-link { color: var(--primary); text-decoration: none; font-weight: 700; font-size: 0.85rem; display: block; margin-top: 10px; text-align: right; }

        @media (max-width: 600px) {
            .container { padding: 1rem; grid-template-columns: 1fr; }
            .navbar h2 { font-size: 1rem; }
            .manage-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <h2><i class="fas fa-shield-halved"></i> SAMS <?php echo strtoupper($user_role); ?></h2>
    <a href="logout.php" class="logout-btn"><i class="fas fa-power-off"></i> Logout</a>
</div>

<div class="container">

    <div class="card">
        <h3><i class="fas fa-user-gear"></i> System Identity</h3>
        <p style="margin: 0;"><strong>Name:</strong> <?php echo htmlspecialchars($admin_name); ?></p>
        <p style="margin: 5px 0;"><strong>UID:</strong> <span style="color:var(--primary); font-family:monospace;"><?php echo htmlspecialchars($admin_id); ?></span></p>
        <?php if($my_dept): ?>
            <p style="margin: 0;"><strong>Dept:</strong> <span class="text-sent"><?php echo $my_dept; ?></span></p>
        <?php endif; ?>

        <h3 style="margin-top:25px;"><i class="fas fa-envelope-open-text"></i> Mail Status</h3>
        <div class="mail-stats-container">
            <div class="mail-stat"><span>Pending:</span> <span class="stat-count text-pending"><?php echo $mail_stats['pending']; ?></span></div>
            <div class="mail-stat"><span>Dispatched:</span> <span class="stat-count text-sent"><?php echo $mail_stats['sent']; ?></span></div>
            <div class="mail-stat"><span>Failed:</span> <span class="stat-count text-failed"><?php echo $mail_stats['failed']; ?></span></div>
        </div>
        <a href="view_mail_queue.php" class="view-mail-link">Detailed Logs <i class="fas fa-arrow-right"></i></a>
    </div>

    <div class="card">
        <h3><i class="fas fa-bullhorn"></i> Global Broadcast</h3>
        <form id="broadcastNoticeForm" action="broadcast_notice.php" method="POST" enctype="multipart/form-data" class="broadcast-form">
            <?php if ($user_role === 'admin'): ?>
                <select name="scope" id="scopeSelect" onchange="toggleAdminDept()">
                    <option value="all">Entire College</option>
                    <option value="dept">Specific Department</option>
                </select>
                <div id="adminDeptSelect" style="display:none;">
                    <select name="dept_id">
                        <?php 
                        $depts = $conn->query("SELECT department_id, department_name FROM departments");
                        while($d = $depts->fetch_assoc()) echo "<option value='{$d['department_id']}'>{$d['department_name']}</option>";
                        ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="scope" value="dept">
                <input type="hidden" name="dept_id" value="<?php echo $my_dept_id; ?>">
                <p style="font-size: 0.8rem; color: var(--text-dim); margin: 10px 0 0 5px;">Target: <strong><?php echo $my_dept; ?></strong></p>
            <?php endif; ?>

            <div style="display:flex; gap:10px;">
                <select name="course_type">
                    <option value="all">All Programs</option>
                    <option value="UG">UG</option>
                    <option value="PG">PG</option>
                </select>

                <select name="batch">
                    <option value="all">All Batches</option>
                    <option value="1st">1st Year</option>
                    <option value="2nd">2nd Year</option>
                    <option value="3rd">3rd Year</option>
                    <option value="4th">4th Year</option>
                </select>
            </div>

            <input type="text" name="title" placeholder="Notice Subject" required>
            <textarea name="message" rows="3" placeholder="Write your message here..." required></textarea>
            <input type="file" name="attachment" style="font-size: 0.8rem; background:transparent;">
            
            <button type="submit" name="send_broadcast" class="btn-primary">Queue Broadcast</button>
        </form>
    </div>

    <div class="card">
        <h3><i class="fas fa-file-csv"></i> Data Integration</h3>
        <p style="font-size: 0.85rem; color: var(--text-dim); margin-top:-10px;">Bulk record synchronization.</p>
        
        <button class="upload-trigger-btn" id="btn-show-student-upload" style="margin-bottom:10px;">
            <i class="fas fa-user-graduate"></i> Student Upload Tool
        </button>
        <div id="student-upload-container" class="upload-area"></div>
        
        <button class="upload-trigger-btn" id="btn-show-teacher-upload">
            <i class="fas fa-user-tie"></i> Teacher Upload Tool
        </button>
        <div id="teacher-upload-container" class="upload-area"></div>
    </div>

    <div class="card" style="grid-column: 1 / -1;">
        <h3><i class="fas fa-folder-tree"></i> System Resources</h3>
        <div class="manage-grid">
            <button class="btn-manage" onclick="location.href='Curd_Operation/manage_student.php'">
                <i class="fas fa-user-graduate"></i> Students
            </button>
            <button class="btn-manage" onclick="location.href='Curd_Operation/manage_teacher.php'">
                <i class="fas fa-chalkboard-teacher"></i> Teachers
            </button>
            <button class="btn-manage" onclick="location.href='Curd_Operation/manage_admin.php'">
                <i class="fas fa-user-shield"></i> Admins
            </button>
            <button class="btn-manage" onclick="location.href='Curd_Operation/manage_department.php'">
                <i class="fas fa-sitemap"></i> Departments
            </button>
            <button class="btn-manage btn-feedback" onclick="location.href='admin_view_feedback.php'">
                <i class="fas fa-comment-dots"></i> Site Feedback
            </button>
        </div>
    </div>

</div>

<div id="success-modal" class="modal-overlay">
    <div class="modal-content">
        <h2 id="modal-title" style="color: var(--primary); margin:0;">Success!</h2>
        <hr style="border:0; border-top:1px solid rgba(255,255,255,0.1); margin: 15px 0;">
        <div id="modal-body"></div>
        <button class="btn-primary" onclick="closeModal()">Continue</button>
    </div>
</div>

<script>
function toggleAdminDept() {
    const scope = document.getElementById('scopeSelect').value;
    document.getElementById('adminDeptSelect').style.display = (scope === 'dept') ? 'block' : 'none';
}

// Ajax Broadcast
document.getElementById('broadcastNoticeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.innerText = "Queuing...";
    btn.disabled = true;

    fetch(this.action, { method: 'POST', body: new FormData(this) })
        .then(r => r.text())
        .then(() => {
            btn.innerText = "Queue Broadcast";
            btn.disabled = false;
            showModal("Broadcast Success", "The notice has been successfully added to the system mail queue.");
            this.reset();
        });
});

function toggleUpload(btnId, containerId, isTeacher = false) {
    const btn = document.getElementById(btnId);
    const container = document.getElementById(containerId);
    
    btn.addEventListener('click', function() {
        if (container.style.display === 'block') {
            container.style.display = 'none';
            this.innerText = isTeacher ? 'Teacher Upload Tool' : 'Student Upload Tool';
        } else {
            this.innerText = 'Close Tool';
            if (container.innerHTML === "") {
                fetch('../Frontend/upload_excel.html').then(r => r.text()).then(html => {
                    let tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    if(isTeacher) {
                        let f = tempDiv.querySelector('form');
                        if(f) f.action = "upload_teacher_excel.php";
                        let p = tempDiv.querySelector('select[name="program"]');
                        if(p) (p.closest('div') || p.parentElement).remove();
                    }
                    container.innerHTML = tempDiv.innerHTML;
                    container.style.display = 'block';
                    attachUploadHandler(containerId);
                });
            } else { container.style.display = 'block'; }
        }
    });
}

toggleUpload('btn-show-student-upload', 'student-upload-container', false);
toggleUpload('btn-show-teacher-upload', 'teacher-upload-container', true);

function attachUploadHandler(cid) {
    const container = document.getElementById(cid);
    const f = container.querySelector('form');
    if(!f) return;

    // --- FILENAME DISPLAY LOGIC ---
    const fileInput = f.querySelector('input[type="file"]');
    if(fileInput) {
        fileInput.addEventListener('change', function() {
            // Find the display span specifically within this container
            const nameDisplay = container.querySelector('#file-name-display');
            if(nameDisplay && this.files.length > 0) {
                nameDisplay.innerHTML = `Selected: <b style="color:#14b8a6">${this.files[0].name}</b>`;
            }
        });
    }

    f.addEventListener('submit', function(e) {
        e.preventDefault();
        const b = f.querySelector('button[type="submit"]');
        b.innerText = "Processing...";
        b.disabled = true;

        fetch(f.action, { method: 'POST', body: new FormData(this) })
            .then(r => r.json())
            .then(d => {
                b.innerText = "Upload File";
                b.disabled = false;
                
                // Construct Summary including Skipped Rows
                let message = `Inserted: ${d.inserted}\nUpdated: ${d.updated}\nFailed: ${d.failed}`;
                if (d.skipped_rows && d.skipped_rows.length > 0) {
                    message += `\n\n⚠️ Skipped Row IDs: ${d.skipped_rows.join(', ')}`;
                }

                showModal("Import Summary", message);
                f.reset();
                
                // Reset filename text
                const nameDisplay = container.querySelector('#file-name-display');
                if(nameDisplay) nameDisplay.innerText = "Click to browse or drag & drop CSV";
            })
            .catch(err => {
                b.innerText = "Start Upload Process";
                b.disabled = false;
                showModal("Error", "Server returned an invalid response. Check the CSV format.");
            });
    });
}

function showModal(title, body) {
    document.getElementById('modal-title').innerText = title;
    document.getElementById('modal-body').innerText = body;
    document.getElementById('success-modal').style.display = 'flex';
}
function closeModal() { document.getElementById('success-modal').style.display = 'none'; }

window.addEventListener('pageshow', (e) => { if (e.persisted) window.location.reload(); });
</script>
</body>
</html>