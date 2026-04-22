<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAMS | Faculty Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-deep: #0f172a;
            --bg-card: #1e293b;
            --accent-mint: #63d9b0;
            --primary: #4f46e5;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --danger: #ef4444;
            --glass: rgba(255, 255, 255, 0.03);
            --border: rgba(255, 255, 255, 0.1);
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg-deep); 
            color: var(--text-main); 
            margin: 0; 
            padding: 40px 20px; 
            min-height: 100vh;
        }

        .container { max-width: 1200px; margin: 0 auto; }

        /* SaaS Glass Header */
        .header-section { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 40px; 
            background: var(--glass);
            padding: 24px 32px;
            border-radius: 24px;
            border: 1px solid var(--border);
            backdrop-filter: blur(12px);
        }

        .header-title h2 { margin: 0; font-size: 1.6rem; letter-spacing: -0.5px; }
        .header-title p { color: var(--text-muted); margin: 4px 0 0; font-size: 0.9rem; }

        .controls { display: flex; align-items: center; gap: 15px; }

        /* Dynamic Search Bar */
        .search-box-wrapper {
            display: flex; align-items: center; background: #0f172a;
            border: 1px solid var(--border); border-radius: 14px; padding: 4px;
            width: 50px; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden; position: relative;
        }
        .search-box-wrapper.expanded { width: 320px; border-color: var(--accent-mint); box-shadow: 0 0 20px rgba(99, 217, 176, 0.15); }
        .search-input { border: none; outline: none; padding: 10px 10px 10px 45px; width: 100%; font-size: 0.9rem; background: transparent; color: white; }
        .search-trigger { position: absolute; left: 18px; cursor: pointer; color: var(--text-muted); z-index: 2; font-size: 1.1rem; }

        .btn-add {
            background: var(--accent-mint); color: #0f172a; border: none; padding: 14px 28px;
            border-radius: 14px; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; gap: 10px; transition: 0.3s;
            text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px;
        }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(99, 217, 176, 0.3); }

        /* Professional Table Design */
        .table-card { 
            background: var(--bg-card); 
            border-radius: 28px; 
            border: 1px solid var(--border);
            box-shadow: 0 25px 50px rgba(0,0,0,0.4); 
            overflow: hidden; 
        }
        table { width: 100%; border-collapse: collapse; }
        th { background: rgba(0,0,0,0.2); padding: 22px; text-align: left; font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 1.2px; font-weight: 800; border-bottom: 1px solid var(--border); }
        td { padding: 18px 22px; border-bottom: 1px solid var(--border); font-size: 0.95rem; vertical-align: middle; transition: 0.2s; }
        
        tr:hover td { background: rgba(255,255,255,0.02); }
        tr:last-child td { border-bottom: none; }

        .id-label { color: var(--accent-mint); font-family: 'JetBrains Mono', monospace; font-weight: 600; font-size: 0.95rem; }
        .role-badge { background: rgba(99, 217, 176, 0.1); color: var(--accent-mint); padding: 6px 14px; border-radius: 10px; font-weight: 700; font-size: 0.7rem; border: 1px solid rgba(99, 217, 176, 0.2); text-transform: uppercase; }
        
        .btn-view {
            background: rgba(255,255,255,0.05); color: #fff; border: 1px solid var(--border);
            padding: 10px 18px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 0.8rem;
        }
        .btn-view:hover { background: var(--text-main); color: var(--bg-deep); border-color: var(--text-main); }

        /* Profile Modal */
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(2, 6, 23, 0.9);
            backdrop-filter: blur(15px); z-index: 1000; justify-content: center; align-items: center;
            padding: 20px;
        }
        .modal-card {
            background: var(--bg-card); width: 100%; max-width: 750px; border-radius: 32px; 
            border: 1px solid var(--border); overflow: hidden; position: relative;
            box-shadow: 0 40px 80px rgba(0,0,0,0.7);
            animation: slideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .modal-header { background: rgba(0,0,0,0.25); padding: 45px; border-bottom: 1px solid var(--border); }
        .modal-body { padding: 45px; max-height: 55vh; overflow-y: auto; }
        
        .data-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 28px; }
        .data-item b { display: block; font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 1px; font-weight: 800; }
        .data-item span { font-weight: 500; color: #fff; font-size: 1.1rem; display: block; }
        
        .close-btn { position: absolute; top: 25px; right: 25px; background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: white; width: 45px; height: 45px; border-radius: 15px; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10; transition: 0.2s; }
        .close-btn:hover { background: var(--danger); border-color: var(--danger); }

        .modal-footer { padding: 35px 45px; background: rgba(0,0,0,0.15); border-top: 1px solid var(--border); display: flex; gap: 20px; }
        .btn-modal { flex: 1; padding: 18px; border-radius: 16px; border: none; font-weight: 700; cursor: pointer; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.8px; transition: 0.3s; }
        .btn-upd { background: var(--accent-mint); color: #0f172a; }
        .btn-del { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }
        .btn-modal:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.3); }

        /* Custom UI States */
        .empty-state { text-align: center; padding: 80px 0; color: var(--text-muted); }
        .empty-state i { font-size: 4rem; margin-bottom: 20px; opacity: 0.2; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-section">
        <div class="header-title">
            <h2><i class="fa-solid fa-chalkboard-user" style="color:var(--accent-mint); margin-right:12px;"></i>Faculty Directory</h2>
            <p>Institutional Academic Staff Records</p>
        </div>
        <div class="controls">
            <div class="search-box-wrapper" id="searchWrapper">
                <i class="fa-solid fa-magnifying-glass search-trigger" onclick="toggleSearch()"></i>
                <input type="text" class="search-input" id="searchInput" placeholder="Search by name, ID or department..." onkeyup="clientSearch()">
            </div>
            <button class="btn-add" onclick="window.location.href='add_user.php?role=teacher'">
                <i class="fa-solid fa-plus"></i> Add Faculty
            </button>
        </div>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Faculty ID</th>
                    <th>Full Name</th>
                    <th>Department</th>
                    <th>Position / Role</th>
                    <th style="text-align:right">Management</th>
                </tr>
            </thead>
            <tbody id="teacherTable">
                </tbody>
        </table>
    </div>
</div>

<div id="modalOverlay" class="modal-overlay" onclick="if(event.target == this) closeModal()">
    <div class="modal-card" id="details"></div>
</div>

<script>
function toggleSearch() {
    const wrapper = document.getElementById('searchWrapper');
    const input = document.getElementById('searchInput');
    wrapper.classList.toggle('expanded');
    if(wrapper.classList.contains('expanded')) input.focus();
}

function clientSearch() {
    let filter = document.getElementById("searchInput").value.toUpperCase();
    let tr = document.getElementById("teacherTable").getElementsByTagName("tr");
    for (let i = 0; i < tr.length; i++) {
        let txtValue = tr[i].textContent || tr[i].innerText;
        tr[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? "" : "none";
    }
}

function loadTeachers(){
    fetch("/SMS/Backend/Curd_Operation/fetch_user.php?type=teacher")
    .then(res => res.json())
    .then(data => {
        let table = document.getElementById("teacherTable");
        table.innerHTML = "";
        let teachers = Array.isArray(data) ? data : (data.data || []);

        if(teachers.length === 0) {
            table.innerHTML = "<tr><td colspan='5' class='empty-state'><i class='fa-solid fa-folder-open'></i><br>No faculty records found.</td></tr>";
            return;
        }

        teachers.forEach(t => {
            table.innerHTML += `
                <tr>
                    <td class="id-label">${t.teacher_id}</td>
                    <td style="font-weight:600; color:#fff;">${t.teacher_name}</td>
                    <td style="color:var(--text-muted)">${t.department_name || 'Unassigned'}</td>
                    <td><span class="role-badge">${t.role}</span></td>
                    <td style="text-align:right">
                        <button class="btn-view" onclick='viewTeacher(${JSON.stringify(t)})'>VIEW PROFILE</button>
                    </td>
                </tr>
            `;
        });
    });
}

function viewTeacher(t){
    document.getElementById('modalOverlay').style.display = 'flex';
    document.getElementById('details').innerHTML = `
        <button class="close-btn" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <h2 style="margin:0; font-size:2rem; color:#fff;">${t.teacher_name}</h2>
            <p style="margin:12px 0 0; color:var(--accent-mint); font-weight:700; letter-spacing:1px; font-family:monospace;">
                FACULTY ID: ${t.teacher_id} &nbsp; // &nbsp; ${t.role.toUpperCase()}
            </p>
        </div>
        <div class="modal-body">
            <div class="data-grid">
                <div class="data-item"><b>Primary Email</b><span>${t.email}</span></div>
                <div class="data-item"><b>Mobile Contact</b><span>${t.mobile}</span></div>
                <div class="data-item"><b>Academic Qualification</b><span>${t.qualification || 'Not Documented'}</span></div>
                <div class="data-item"><b>Years of Experience</b><span>${t.experience_years ? t.experience_years + ' Academic Years' : 'N/A'}</span></div>
                <div class="data-item" style="grid-column: span 2; background:rgba(255,255,255,0.03); padding:20px; border-radius:16px; border:1px solid var(--border)">
                    <b>Core Teaching / Specialization</b>
                    <span>${t.teaching_area || 'Information Not Provided'}</span>
                </div>
                <div class="data-item"><b>Affiliated Department</b><span>${t.department_name || 'N/A'}</span></div>
                <div class="data-item"><b>Research & Contributions</b><span>${t.research_area || 'None Documented'}</span></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-modal btn-upd" onclick="editTeacher('${t.teacher_id}')">
                <i class="fa-solid fa-user-pen" style="margin-right:8px"></i> Update Faculty
            </button>
            <button class="btn-modal btn-del" onclick="deleteTeacher('${t.teacher_id}')">
                <i class="fa-solid fa-user-slash" style="margin-right:8px"></i> Terminate Record
            </button>
        </div>
    `;
}

function editTeacher(id) {
    window.location.href = `update_user.php?type=teacher&id=${id}`;
}

function deleteTeacher(id){
    if(!confirm("CONFIRM TERMINATION: This faculty record will be permanently purged from the SAMS database. Continue?")) return;
    
    let formData = new FormData();
    formData.append("id", id);
    formData.append("type", "teacher");

    fetch("delete_user.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === "success") {
            closeModal();
            loadTeachers();
        } else {
            alert("Error: " + data.message);
        }
    });
}

function closeModal() { document.getElementById('modalOverlay').style.display = 'none'; }
loadTeachers();
</script>
</body>
</html>