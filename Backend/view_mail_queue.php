<?php
session_start();
require_once 'db.php';

// 1. SESSION & ACCESS CONTROL
if (!isset($_SESSION['login'])) {
    header("Location: ../Frontend/teacher_login.html");
    exit();
}

$user_role = $_SESSION['role'] ?? 'hod'; 
$teacher_id = $_SESSION['teacher_id'] ?? null;

// Initialize HOD department variables
$hod_departments = [];
$selected_dept_id = $_GET['dept_filter'] ?? null;

if ($user_role === 'hod') {
    // Fetch all departments where this teacher is HOD
    $dept_stmt = $conn->prepare("SELECT d.department_id, d.department_name 
                                FROM departments d 
                                JOIN teacher_department td ON d.department_id = td.department_id 
                                WHERE td.teacher_id = ? AND td.ishod = 1");
    $dept_stmt->bind_param("s", $teacher_id);
    $dept_stmt->execute();
    $dept_res = $dept_stmt->get_result();
    while ($row = $dept_res->fetch_assoc()) {
        $hod_departments[] = $row;
    }
    $dept_stmt->close();

    // Default to the first department if none selected
    if (is_null($selected_dept_id) && !empty($hod_departments)) {
        $selected_dept_id = $hod_departments[0]['department_id'];
    }
}

// ACTION: Clear History
if (isset($_POST['clear_history']) && $user_role === 'admin') {
    $conn->query("TRUNCATE TABLE mail_log");
    header("Location: view_mail_queue.php?msg=History Cleared");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail Delivery Logs | SAMS</title>
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
            --warning: #f59e0b;
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg-dark); 
            background-image: radial-gradient(circle at 100% 0%, rgba(20, 184, 166, 0.08) 0%, transparent 40%);
            color: var(--text-main);
            margin: 0; 
            padding: 20px; 
            min-height: 100vh;
        }

        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: var(--card-bg); 
            backdrop-filter: blur(12px);
            padding: 30px; 
            border-radius: 24px; 
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3);
        }

        h2 { 
            color: var(--primary); 
            font-weight: 800; 
            letter-spacing: -1px; 
            display: flex; 
            align-items: center; 
            gap: 12px;
            margin-top: 0;
        }

        .back-btn { 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            margin-bottom: 25px; 
            text-decoration: none; 
            color: var(--text-dim); 
            font-weight: 600; 
            font-size: 0.9rem;
            transition: 0.3s;
        }
        .back-btn:hover { color: var(--primary); }
        
        /* Filter & Action Bar */
        .action-bar { 
            margin: 25px 0; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
            gap: 20px;
            background: rgba(0,0,0,0.2);
            padding: 15px 20px;
            border-radius: 16px;
        }

        .dept-selector { 
            background: rgba(15, 23, 42, 0.8);
            color: var(--text-main);
            padding: 10px 15px; 
            border-radius: 10px; 
            border: 1px solid rgba(255,255,255,0.1); 
            outline: none;
            font-family: inherit;
        }

        .btn-group { display: flex; gap: 12px; }
        
        .btn {
            border: none; 
            padding: 12px 20px; 
            border-radius: 12px; 
            cursor: pointer; 
            font-weight: 700; 
            display: flex; 
            align-items: center; 
            gap: 8px;
            transition: 0.3s;
            font-size: 0.85rem;
        }

        .btn-process { background: var(--primary); color: white; }
        .btn-process:hover { background: var(--primary-dark); transform: translateY(-2px); }
        
        .btn-clear { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid var(--danger); }
        .btn-clear:hover { background: var(--danger); color: white; }

        /* Table Styling */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 8px; margin-top: 10px; }
        
        th { 
            text-align: left; 
            padding: 15px; 
            color: var(--text-dim); 
            font-size: 0.75rem; 
            text-transform: uppercase; 
            letter-spacing: 1px;
            font-weight: 800;
        }

        td { 
            padding: 15px; 
            background: rgba(255,255,255,0.03);
            font-size: 0.9rem;
        }

        td:first-child { border-radius: 12px 0 0 12px; }
        td:last-child { border-radius: 0 12px 12px 0; }
        
        tr:hover td { background: rgba(255,255,255,0.07); }

        .badge { 
            padding: 6px 12px; 
            border-radius: 8px; 
            font-size: 0.7rem; 
            font-weight: 800; 
            text-transform: uppercase; 
        }
        .status-sent { background: rgba(20, 184, 166, 0.15); color: #2dd4bf; border: 1px solid rgba(20, 184, 166, 0.3); }
        .status-pending { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .status-error, .status-failed { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

        .email-text { color: var(--text-dim); font-family: monospace; font-size: 0.85rem; }

        @media (max-width: 768px) {
            .action-bar { flex-direction: column; align-items: stretch; }
            .btn-group { flex-direction: column; }
            td, th { padding: 10px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="javascript:history.back()" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    
    <h2><i class="fas fa-list-check"></i> Email Delivery Logs</h2>
    
    <div class="action-bar">
        <div>
            <?php if ($user_role === 'admin'): ?>
                <span style="color: var(--primary); font-weight: 700;"><i class="fas fa-globe"></i> Global Logs (All Depts)</span>
            <?php elseif (count($hod_departments) > 1): ?>
                <form method="GET" id="deptFilterForm">
                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-dim); margin-right: 10px;">DEPARTMENT VIEW:</label>
                    <select name="dept_filter" class="dept-selector" onchange="document.getElementById('deptFilterForm').submit()">
                        <?php foreach ($hod_departments as $hd): ?>
                            <option value="<?php echo $hd['department_id']; ?>" <?php echo ($selected_dept_id == $hd['department_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($hd['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php else: ?>
                <span style="color: var(--primary); font-weight: 700;"><i class="fas fa-building"></i> Dept: <?php echo htmlspecialchars($hod_departments[0]['department_name'] ?? 'N/A'); ?></span>
            <?php endif; ?>
        </div>
        
        <div class="btn-group">
            <?php if ($user_role === 'admin'): ?>
            <form method="POST" onsubmit="return confirm('Permanently clear mail history?');">
                <button type="submit" name="clear_history" class="btn btn-clear">
                    <i class="fas fa-trash-can"></i> Wipe History
                </button>
            </form>
            <?php endif; ?>

            <button id="sendMailBtn" class="btn btn-process">
                <i class="fas fa-bolt"></i> Run Mail Worker
            </button>
        </div>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Recipient</th>
                    <th>Email Address</th>
                    <th>Status</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($user_role === 'admin') {
                    $query = "(SELECT recipient_name as name, recipient_email as email, status, created_at as dt FROM mail_queue)
                              UNION ALL
                              (SELECT 'History' as name, recipient_email as email, status, sent_at as dt FROM mail_log)
                              ORDER BY dt DESC LIMIT 200";
                    $result = $conn->query($query);
                } else {
                    $query = "(SELECT mq.recipient_name as name, mq.recipient_email as email, mq.status, mq.created_at as dt 
                                FROM mail_queue mq 
                                JOIN student_table1 s ON mq.recipient_email = s.email 
                                WHERE s.department_structure_id IN (SELECT structure_id FROM department_structure WHERE department_id = ?))
                              UNION ALL
                              (SELECT 'History' as name, ml.recipient_email as email, ml.status, ml.sent_at as dt 
                                FROM mail_log ml 
                                JOIN student_table1 s ON ml.recipient_email = s.email 
                                WHERE s.department_structure_id IN (SELECT structure_id FROM department_structure WHERE department_id = ?))
                              ORDER BY dt DESC LIMIT 200";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ii", $selected_dept_id, $selected_dept_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                }

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $status = strtolower($row['status']);
                        echo "<tr>
                                <td style='font-weight: 700;'>" . htmlspecialchars($row['name']) . "</td>
                                <td class='email-text'>" . htmlspecialchars($row['email']) . "</td>
                                <td><span class='badge status-$status'>$status</span></td>
                                <td style='color: var(--text-dim);'>" . date('d M, Y | h:i A', strtotime($row['dt'])) . "</td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='4' style='text-align:center; padding:60px; color:var(--text-dim);'>
                            <i class='fas fa-inbox' style='font-size: 2rem; display:block; margin-bottom:10px; opacity:0.3;'></i>
                            No activity logs found.
                          </td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById('sendMailBtn').addEventListener('click', function() {
    const btn = this;
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.style.opacity = "0.6";
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Dispatching...';

    fetch('mail_worker.php')
        .then(response => response.text())
        .then(data => {
            btn.innerHTML = '<i class="fas fa-check"></i> Completed';
            setTimeout(() => location.reload(), 1000);
        })
        .catch(error => {
            console.error('Error:', error);
            alert("Worker Failed. Check connectivity.");
            btn.disabled = false;
            btn.style.opacity = "1";
            btn.innerHTML = originalText;
        });
});
</script>
</body>
</html>