<?php
session_start();
require_once '../db.php';

// 1. SESSION & ACCESS CONTROL - EXACT ORIGINAL LOGIC
if (!isset($_SESSION['login']) || !in_array($_SESSION['role'], ['admin', 'hod'])) {
    die("Access Denied: Admin session required.");
}

// 2. FETCH ADMINS - EXACT ORIGINAL LOGIC
$admin_res = $conn->query("SELECT * FROM admin ORDER BY admin_name ASC");

// 3. FETCH HODs - EXACT ORIGINAL LOGIC
$hod_sql = "SELECT t.teacher_id, t.teacher_name, t.email 
            FROM teachers t
            JOIN teacher_department td ON t.teacher_id = td.teacher_id
            WHERE td.ishod = 1 
            AND t.teacher_id NOT IN (SELECT admin_id FROM admin)";
$hod_res = $conn->query($hod_sql);

// 4. ACTIVE ID LOGIC - EXACT ORIGINAL LOGIC
$active_id = ($_SESSION['role'] === 'admin') ? $_SESSION['admin_id'] : $_SESSION['teacher_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Administrators | DAC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary: #14b8a6; 
            --bg: #0f172a; 
            --card: rgba(30, 41, 59, 0.7); 
            --text: #f8fafc; 
            --dim: #94a3b8; 
        }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg); 
            background-image: radial-gradient(circle at 0% 0%, rgba(20, 184, 166, 0.05) 0%, transparent 50%);
            color: var(--text); 
            padding: 30px; 
            margin: 0; 
        }
        .grid-container { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; max-width: 1200px; margin: 0 auto; }
        .card { 
            background: var(--card); 
            padding: 25px; 
            border-radius: 20px; 
            backdrop-filter: blur(12px); 
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2); 
        }
        h2 { color: var(--primary); border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; font-size: 1.2rem; font-weight: 800; text-transform: uppercase; }
        
        table { width: 100%; border-collapse: separate; border-spacing: 0 8px; margin-top: 15px; }
        th { text-align: left; padding: 12px; color: var(--dim); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; }
        td { padding: 12px; background: rgba(255,255,255,0.03); font-size: 0.9rem; }
        td:first-child { border-radius: 10px 0 0 10px; }
        td:last-child { border-radius: 0 10px 10px 0; }
        
        .btn { padding: 12px 18px; border-radius: 10px; border: none; cursor: pointer; font-weight: 700; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-add { background: var(--primary); color: white; width: 100%; margin-top: 10px; justify-content: center; }
        .btn-promote { background: rgba(16, 185, 129, 0.1); color: #10b981; font-size: 0.8rem; border: 1px solid rgba(16, 185, 129, 0.2); }
        .btn-promote:hover { background: #10b981; color: white; }
        .btn-del { color: #ef4444; background: none; font-size: 1rem; }
        
        input { 
            width: 100%; padding: 12px; margin: 10px 0; 
            background: rgba(15, 23, 42, 0.6); 
            border: 1px solid rgba(255,255,255,0.1); 
            border-radius: 10px; color: white; outline: none; box-sizing: border-box; 
        }

        /* Modal - EXACT UI preserved */
        .modal-overlay { 
            display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.8); 
            backdrop-filter: blur(8px); z-index: 10000; justify-content: center; align-items: center; 
        }
        .success-card { 
            background: #1e293b; width: 90%; max-width: 400px; padding: 40px; border-radius: 24px; text-align: center; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); transform: scale(0.9); transition: 0.3s; 
            border: 1px solid rgba(255,255,255,0.1);
        }
        .modal-overlay.active .success-card { transform: scale(1); }
        .icon-circle { width: 80px; height: 80px; background: rgba(20, 184, 166, 0.1); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 20px; }
        .pass-container { background: rgba(0,0,0,0.2); border: 2px dashed var(--primary); padding: 15px; border-radius: 12px; margin: 20px 0; }
        .pass-label { font-size: 0.75rem; color: var(--dim); text-transform: uppercase; }
        .pass-value { font-family: monospace; font-size: 1.3rem; font-weight: bold; color: white; display: block; margin-top: 5px; }
        .copy-btn { background: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; width: 100%; }

        @media (max-width: 850px) { .grid-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="grid-container">
    <div>
        <div class="card">
            <h2><i class="fas fa-user-shield"></i> Add Office Admin</h2>
            <form id="manualAdminForm">
                <input type="text" name="admin_id" placeholder="Admin/Staff ID" required>
                <input type="text" name="admin_name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email Address" required>
                <button type="submit" class="btn btn-add">Save Admin Account</button>
            </form>
        </div>

        <div class="card" style="margin-top:25px">
            <h2><i class="fas fa-list-ul"></i> Admin List</h2>
            <table>
                <thead>
                    <tr><th>Admin ID</th><th>Name</th><th style="text-align:right">Action</th></tr>
                </thead>
                <tbody>
                    <?php while($a = $admin_res->fetch_assoc()): ?>
                    <tr>
                        <td><b><?php echo htmlspecialchars($a['admin_id']); ?></b></td>
                        <td><?php echo htmlspecialchars($a['admin_name']); ?></td>
                        <td style="text-align:right">
                            <?php if($a['admin_id'] !== $active_id): ?>
                            <button class="btn-del" onclick="deleteAdmin('<?php echo $a['admin_id']; ?>')">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                            <?php else: ?>
                                <small style="color: var(--primary); font-weight: 800;">ACTIVE</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2><i class="fas fa-user-tie"></i> Promote HOD to Admin</h2>
        <p style="font-size: 0.85rem; color: var(--dim);">HODs currently without Admin access.</p>
        <table>
            <thead>
                <tr><th>Faculty Name</th><th>Teacher ID</th><th style="text-align:right">Action</th></tr>
            </thead>
            <tbody>
                <?php if($hod_res->num_rows > 0): ?>
                    <?php while($h = $hod_res->fetch_assoc()): ?>
                    <tr>
                        <td><b><?php echo htmlspecialchars($h['teacher_name']); ?></b></td>
                        <td><?php echo htmlspecialchars($h['teacher_id']); ?></td>
                        <td style="text-align:right">
                            <button class="btn btn-promote" onclick="promoteHOD('<?php echo $h['teacher_id']; ?>', '<?php echo addslashes($h['teacher_name']); ?>', '<?php echo $h['email']; ?>')">
                                <i class="fas fa-level-up-alt"></i> Grant Admin
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3" style="text-align:center; padding:30px; color:var(--dim)">No eligible HODs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modern-success-modal" class="modal-overlay">
    <div class="success-card">
        <div class="icon-circle"><i class="fas fa-check-circle"></i></div>
        <h2 style="margin:0; color:white; border:none; justify-content:center;">Access Granted!</h2>
        <p id="success-msg" style="color:var(--dim); margin-top:10px;"></p>
        <div class="pass-container" id="password-display-box" style="display:none;">
            <span class="pass-label">Temporary Password</span>
            <span class="pass-value" id="temp-pass-text"></span>
        </div>
        <button class="copy-btn" onclick="closeAndReload()">Done</button>
    </div>
</div>

<script>
// ALL ORIGINAL JAVASCRIPT LOGIC PRESERVED
function promoteHOD(id, name, email) {
    if(!confirm('Grant admin privileges to HOD: ' + name + '?')) return;
    const fd = new FormData();
    fd.append('action', 'promote_hod');
    fd.append('admin_id', id); fd.append('admin_name', name); fd.append('email', email);
    sendRequest(fd);
}

document.getElementById('manualAdminForm').onsubmit = function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fd.append('action', 'add_manual');
    sendRequest(fd);
};

function deleteAdmin(id) {
    if(!confirm('Permanently remove admin access for ' + id + '?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_admin'); fd.append('admin_id', id);
    sendRequest(fd);
}

function sendRequest(fd) {
    fetch('admin_actions.php', { method: 'POST', body: fd })
    .then(async response => {
        const text = await response.text();
        try { return JSON.parse(text); } 
        catch (err) { throw new Error(text); }
    })
    .then(data => {
        if(data.status === 'success') {
            const modal = document.getElementById('modern-success-modal');
            const msg = document.getElementById('success-msg');
            const passBox = document.getElementById('password-display-box');
            const passText = document.getElementById('temp-pass-text');
            msg.innerText = data.message;
            if(data.temp_pass) {
                passBox.style.display = 'block';
                passText.innerText = data.temp_pass;
            } else { passBox.style.display = 'none'; }
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('active'), 10);
        } else { alert('Failed: ' + data.message); }
    })
    .catch(error => { alert(error.message); });
}

function closeAndReload() { location.reload(); }
</script>
</body>
</html>