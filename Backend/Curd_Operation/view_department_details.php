<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "../db.php";
require_once "access_control.php";

$dept_id = $_GET['id'] ?? '';
$view = $_GET['view'] ?? 'teacher'; // default view

// Fetch Dept Name for the header
$dept_stmt = $conn->prepare("SELECT department_name FROM departments WHERE department_id = ?");
$dept_stmt->bind_param("s", $dept_id);
$dept_stmt->execute();
$dept_name = $dept_stmt->get_result()->fetch_assoc()['department_name'] ?? 'Unknown Department';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage <?php echo $dept_name; ?> | SAMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { 
            --primary: #14b8a6; 
            --danger: #f43f5e;
            --bg-dark: #0f172a; 
            --card-bg: rgba(30, 41, 59, 0.7); 
            --text-main: #f8fafc; 
            --text-dim: #94a3b8; 
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg-dark); 
            background-image: radial-gradient(circle at 0% 0%, rgba(20, 184, 166, 0.05) 0%, transparent 50%);
            color: var(--text-main); 
            margin: 0; 
            padding: 30px; 
            min-height: 100vh;
        }

        .container { max-width: 1200px; margin: 0 auto; }

        /* Modern Toolbar */
        .toolbar { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            background: var(--card-bg); 
            padding: 20px 30px; 
            border-radius: 20px; 
            margin-bottom: 30px; 
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
        }

        .dept-title h2 { 
            margin: 0; 
            font-size: 1.5rem; 
            font-weight: 800; 
            color: white;
        }
        
        .dept-title small { 
            color: var(--primary); 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            font-weight: 700;
            font-size: 0.7rem;
        }

        /* View Tabs */
        .tab-group { 
            display: flex; 
            background: rgba(15, 23, 42, 0.5); 
            padding: 6px; 
            border-radius: 14px; 
            gap: 5px; 
        }

        .tab { 
            padding: 10px 20px; 
            border-radius: 10px; 
            cursor: pointer; 
            border: none; 
            font-weight: 700; 
            font-size: 0.85rem;
            background: transparent; 
            color: var(--text-dim);
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        }

        .tab:hover { color: white; background: rgba(255,255,255,0.05); }

        .tab.active { 
            background: var(--primary); 
            color: white; 
            box-shadow: 0 4px 12px rgba(20, 184, 166, 0.3);
        }
        
        /* Table Layout */
        .table-container { 
            background: var(--card-bg); 
            border-radius: 24px; 
            overflow: hidden; 
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(12px);
        }

        /* Bulk Actions Bar */
        .bulk-actions { 
            padding: 15px 30px; 
            background: rgba(244, 63, 94, 0.1); 
            border-bottom: 1px solid rgba(244, 63, 94, 0.2);
            display: none; 
            align-items: center; 
            gap: 20px; 
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .bulk-btn { 
            padding: 8px 16px; 
            border-radius: 8px; 
            font-weight: 700; 
            font-size: 0.75rem;
            cursor: pointer;
            border: none;
            transition: 0.2s;
        }

        .btn-danger { background: var(--danger); color: white; }
        .btn-outline { background: transparent; border: 1px solid var(--glass-border); color: white; }
        .btn-outline:hover { background: rgba(255,255,255,0.1); }

        #selectedCount { font-size: 0.85rem; font-weight: 700; color: var(--danger); }

        /* Loader */
        .loader-wrapper { padding: 80px; text-align: center; color: var(--primary); }

        /* Shared Badge Style (for use in fetch_list.php) */
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 800; }
        /* Table and Data Fixes for Dark Theme */
#dataTable table { 
    width: 100%; 
    border-collapse: separate; 
    border-spacing: 0; 
    color: var(--text-main); 
    background: transparent; /* Removes white background */
}

#dataTable th { 
    background: rgba(15, 23, 42, 0.6); 
    color: var(--primary); 
    padding: 15px; 
    font-size: 0.75rem; 
    text-transform: uppercase; 
    letter-spacing: 1px;
    border-bottom: 1px solid var(--glass-border);
}

#dataTable td { 
    padding: 16px 15px; 
    border-bottom: 1px solid rgba(255, 255, 255, 0.05); 
    background: transparent;
    font-size: 0.9rem;
    color: var(--text-main); /* Forces text to be visible */
}

#dataTable tr:hover td { 
    background: rgba(255, 255, 255, 0.02); 
}

/* Fix for checkboxes and inputs inside the table */
.row-check {
    accent-color: var(--primary);
    transform: scale(1.2);
    cursor: pointer;
}

/* Action Button Overrides */
.btn-action {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    color: var(--text-main);
    padding: 6px 10px;
    border-radius: 8px;
    transition: 0.2s;
}

.btn-action:hover {
    background: var(--primary);
    color: white;
}

/* Fix for the white box issue specifically */
.table-container { 
    background: var(--card-bg) !important; /* Forces the container to be dark teal */
    border: 1px solid var(--glass-border);
}
    </style>
</head>
<body>

<div class="container">
    <div class="toolbar">
        <div class="dept-title">
            <h2><?php echo $dept_name; ?></h2>
            <small>Personnel Management • <?php echo ucfirst($view); ?>s</small>
        </div>
        
        <div class="tab-group">
            <button class="tab <?php echo $view=='teacher'?'active':''; ?>" onclick="switchView('teacher')">Faculty</button>
            <button class="tab <?php echo $view=='ug'?'active':''; ?>" onclick="switchView('ug')">UG Students</button>
            <button class="tab <?php echo $view=='pg'?'active':''; ?>" onclick="switchView('pg')">PG Students</button>
        </div>
    </div>

    <div id="bulkBar" class="bulk-actions">
        <div style="display:flex; align-items:center; gap:10px;">
            <i class="fas fa-check-double" style="color:var(--danger)"></i>
            <span id="selectedCount">0 Selected Items</span>
        </div>
        <div style="height: 20px; width: 1px; background: rgba(255,255,255,0.1);"></div>
        <button class="bulk-btn btn-danger" onclick="bulkDelete()">
            <i class="fas fa-trash-alt"></i> Delete Selected
        </button>
        <?php if($view != 'teacher'): ?>
        <button class="bulk-btn btn-outline" onclick="bulkMove()">
            <i class="fas fa-exchange-alt"></i> Change Batch/Year
        </button>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <div id="listLoader" class="loader-wrapper">
            <i class="fas fa-circle-notch fa-spin fa-3x"></i>
            <p style="margin-top:15px; font-weight:600; font-size:0.9rem;">Synchronizing data...</p>
        </div>
        <div id="dataTable">
            </div>
    </div>
</div>

<script>
const DEPT_ID = "<?php echo $dept_id; ?>";
let CURRENT_VIEW = "<?php echo $view; ?>";

// 1. Fetch data on load
document.addEventListener('DOMContentLoaded', () => loadList(CURRENT_VIEW));

function switchView(v) {
    window.location.href = `view_department_details.php?id=${DEPT_ID}&view=${v}`;
}

function loadList(type) {
    const tableDiv = document.getElementById('dataTable');
    const loader = document.getElementById('listLoader');
    loader.style.display = 'block';
    tableDiv.style.opacity = '0.3';
    
    fetch(`fetch_list.php?dept_id=${DEPT_ID}&type=${type}`)
        .then(res => res.text())
        .then(html => {
            loader.style.display = 'none';
            tableDiv.style.opacity = '1';
            tableDiv.innerHTML = html;
        });
}

// 2. Select All Logic
function toggleAll(master) {
    const checkboxes = document.querySelectorAll('.row-check');
    checkboxes.forEach(cb => {
        cb.checked = master.checked;
        // Visual feedback for row selection
        const row = cb.closest('tr');
        if(row) row.style.background = cb.checked ? 'rgba(20, 184, 166, 0.05)' : '';
    });
    updateBulkBar();
}

function updateBulkBar() {
    const count = document.querySelectorAll('.row-check:checked').length;
    const bar = document.getElementById('bulkBar');
    document.getElementById('selectedCount').innerText = `${count} Records Selected`;
    bar.style.display = count > 0 ? 'flex' : 'none';
}

// All Logic Below remains exactly as provided
function deleteSingle(type, id) {
    Swal.fire({
        title: 'Confirm Deletion',
        text: "This record will be permanently purged from SAMS.",
        icon: 'warning',
        background: '#1e293b',
        color: '#fff',
        showCancelButton: true,
        confirmButtonColor: '#f43f5e',
        cancelButtonColor: 'rgba(255,255,255,0.1)',
        confirmButtonText: 'Confirm Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            let fd = new FormData();
            fd.append('id', id);
            fd.append('type', type);
            
            fetch('delete_user.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Removed', background: '#1e293b', color: '#fff' });
                    loadList(CURRENT_VIEW);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message, background: '#1e293b', color: '#fff' });
                }
            });
        }
    });
}

function bulkDelete() {
    const selected = Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
    const type = CURRENT_VIEW === 'teacher' ? 'teacher' : 'student';

    Swal.fire({
        title: `Delete ${selected.length} records?`,
        text: "This batch action is irreversible!",
        icon: 'warning',
        background: '#1e293b',
        color: '#fff',
        showCancelButton: true,
        confirmButtonColor: '#f43f5e',
        confirmButtonText: 'Purge Selected'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            selected.forEach(id => fd.append('id[]', id));
            fd.append('type', type);

            fetch('delete_user.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Purged', background: '#1e293b', color: '#fff' }).then(() => loadList(CURRENT_VIEW));
                    document.getElementById('bulkBar').style.display = 'none';
                }
            });
        }
    });
}

function deleteBatch(year) {
    Swal.fire({
        title: `Wipe ${year} Batch?`,
        text: `Permanently delete all ${CURRENT_VIEW.toUpperCase()} students in Year ${year}?`,
        icon: 'warning',
        background: '#1e293b',
        color: '#fff',
        showCancelButton: true,
        confirmButtonColor: '#f43f5e',
        confirmButtonText: 'Yes, Wipe Batch'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('type', 'student');
            fd.append('batch_year', year);
            fd.append('dept_id', DEPT_ID);

            fetch('delete_user.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Wiped', background: '#1e293b', color: '#fff' }).then(() => loadList(CURRENT_VIEW));
                }
            });
        }
    });
}

function upgradeBatch(currentYear) {
    Swal.fire({
        title: 'Promote Students?',
        text: `Upgrade all Year ${currentYear} students to Year ${parseInt(currentYear) + 1}?`,
        icon: 'question',
        background: '#1e293b',
        color: '#fff',
        showCancelButton: true,
        confirmButtonColor: '#14b8a6',
        confirmButtonText: 'Promote Now'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'upgrade_batch');
            fd.append('year', currentYear);
            fd.append('dept_id', DEPT_ID);

            fetch('batch_operations.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Promoted', background: '#1e293b', color: '#fff' }).then(() => loadList(CURRENT_VIEW));
                }
            });
        }
    });
}
</script>
</body>
</html>