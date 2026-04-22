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
    <title>SAMS | Student Management</title>
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

        /* Header & Navigation Area */
        .header-section { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 40px; 
            background: var(--glass);
            padding: 20px 30px;
            border-radius: 20px;
            border: 1px solid var(--border);
            backdrop-filter: blur(10px);
        }

        .header-section h2 { margin: 0; font-size: 1.5rem; letter-spacing: -0.5px; }

        .controls { display: flex; align-items: center; gap: 15px; }

        /* SaaS Search Box */
        .search-box-wrapper {
            display: flex; align-items: center; background: #0f172a;
            border: 1px solid var(--border); border-radius: 12px; padding: 4px;
            width: 48px; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden; position: relative;
        }
        .search-box-wrapper.expanded { width: 320px; border-color: var(--accent-mint); box-shadow: 0 0 15px rgba(99, 217, 176, 0.2); }
        .search-input { border: none; outline: none; padding: 10px 10px 10px 40px; width: 100%; font-size: 0.9rem; background: transparent; color: white; }
        .search-trigger { position: absolute; left: 16px; cursor: pointer; color: var(--text-muted); z-index: 2; font-size: 1.1rem; }

        .btn-add {
            background: var(--accent-mint); color: #0f172a; border: none; padding: 12px 24px;
            border-radius: 12px; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; gap: 10px; transition: 0.3s;
            text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px;
        }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(99, 217, 176, 0.3); }

        /* Modern Data Table */
        .table-card { 
            background: var(--bg-card); 
            border-radius: 24px; 
            border: 1px solid var(--border);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3); 
            overflow: hidden; 
        }
        table { width: 100%; border-collapse: collapse; }
        th { background: rgba(0,0,0,0.2); padding: 20px; text-align: left; font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 1px; font-weight: 800; }
        td { padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 0.95rem; vertical-align: middle; transition: 0.2s; }
        
        tr:hover td { background: rgba(255,255,255,0.02); }
        tr:last-child td { border-bottom: none; }

        .id-b { color: var(--accent-mint); font-family: monospace; font-size: 1rem; }
        .year-badge { background: rgba(79, 70, 229, 0.15); color: #a5b4fc; padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 0.75rem; border: 1px solid rgba(79, 70, 229, 0.3); }
        
        .student-pic { width: 42px; height: 42px; border-radius: 10px; object-fit: cover; border: 2px solid var(--border); }
        
        .btn-view {
            background: transparent; color: var(--accent-mint); border: 1px solid rgba(99, 217, 176, 0.3);
            padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s;
        }
        .btn-view:hover { background: var(--accent-mint); color: #0f172a; }

        /* Modal / Detail View */
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(2, 6, 23, 0.85);
            backdrop-filter: blur(12px); z-index: 1000; justify-content: center; align-items: center;
            padding: 20px;
        }
        .modal-card {
            background: var(--bg-card); width: 100%; max-width: 750px; border-radius: 28px; 
            border: 1px solid var(--border); overflow: hidden; position: relative;
            box-shadow: 0 30px 60px rgba(0,0,0,0.6);
            animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .modal-header { background: rgba(0,0,0,0.2); padding: 40px; display: flex; align-items: center; gap: 25px; border-bottom: 1px solid var(--border); }
        .modal-body { padding: 40px; max-height: 50vh; overflow-y: auto; }
        
        .data-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
        .data-item b { display: block; font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 1px; }
        .data-item span { font-weight: 500; color: #fff; font-size: 1.05rem; }
        
        .close-btn { position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: white; width: 40px; height: 40px; border-radius: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10; transition: 0.2s; }
        .close-btn:hover { background: var(--danger); }

        .modal-footer { padding: 30px 40px; background: rgba(0,0,0,0.1); border-top: 1px solid var(--border); display: flex; gap: 15px; }
        .btn-modal { flex: 1; padding: 15px; border-radius: 12px; border: none; font-weight: 700; cursor: pointer; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; }
        .btn-upd { background: var(--accent-mint); color: #0f172a; }
        .btn-del { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }

        .no-results { text-align: center; padding: 60px !important; color: var(--text-muted); }
        .no-results i { display: block; font-size: 3rem; margin-bottom: 20px; color: var(--border); }

        /* Custom Scrollbar for Modal */
        .modal-body::-webkit-scrollbar { width: 6px; }
        .modal-body::-webkit-scrollbar-track { background: transparent; }
        .modal-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-section">
        <div>
            <h2 style="display:flex; align-items:center; gap:12px;">
                <i class="fa-solid fa-users-viewfinder" style="color:var(--accent-mint)"></i> 
                Students Database
            </h2>
        </div>
        <div class="controls">
            <div class="search-box-wrapper" id="searchWrapper">
                <i class="fa-solid fa-magnifying-glass search-trigger" onclick="toggleSearch()"></i>
                <input type="text" class="search-input" id="searchInput" placeholder="Type student name or ID..." onkeyup="clientSearch()">
            </div>
            <button class="btn-add" onclick="showAddForm()"><i class="fa-solid fa-plus"></i> Add Student</button>
        </div>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Record ID</th>
                    <th>Full Name</th>
                    <th>Academic Year</th>
                    <th>Profile</th>
                    <th style="text-align:right">Action</th>
                </tr>
            </thead>
            <tbody id="studentTable">
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
    if (wrapper.classList.contains('expanded')) input.focus();
    else { input.value = ""; clientSearch(); }
}

function clientSearch() {
    const filter = document.getElementById("searchInput").value.toUpperCase();
    const table = document.getElementById("studentTable");
    const rows = table.getElementsByTagName("tr");
    let visibleCount = 0;

    const existingNoRes = table.querySelector('.no-results-row');
    if (existingNoRes) existingNoRes.remove();

    for (let i = 0; i < rows.length; i++) {
        if (rows[i].classList.contains('no-results-row')) continue;
        const textContent = rows[i].textContent || rows[i].innerText;
        if (textContent.toUpperCase().indexOf(filter) > -1) {
            rows[i].style.display = "";
            visibleCount++;
        } else {
            rows[i].style.display = "none";
        }
    }

    if (visibleCount === 0 && filter !== "") {
        const noResRow = document.createElement("tr");
        noResRow.className = "no-results-row";
        noResRow.innerHTML = `<td colspan="5" class="no-results"><i class="fa-solid fa-magnifying-glass"></i>No matches found for "<b>${document.getElementById("searchInput").value}</b>"</td>`;
        table.appendChild(noResRow);
    }
}

function loadStudents(){
    fetch("/SMS/Backend/Curd_Operation/fetch_user.php?type=student") 
    .then(res => res.json())
    .then(data => {
        let table = document.getElementById("studentTable");
        table.innerHTML = "";
        let students = Array.isArray(data) ? data : (data.data || []);
        
        if (students.length === 0) {
            table.innerHTML = "<tr><td colspan='5' class='no-results'>No students recorded yet.</td></tr>";
            return;
        }

        students.forEach(s => {
            table.innerHTML += `
                <tr>
                    <td class="id-b">${s.student_id}</td>
                    <td style="font-weight:600">${s.student_name}</td>
                    <td><span class="year-badge">${s.year || 'N/A'}</span></td>
                    <td><img class="student-pic" src="${s.photo || 'https://via.placeholder.com/50'}"></td>
                    <td style="text-align:right">
                        <button class="btn-view" onclick='viewStudent(${JSON.stringify(s)})'>View Profile</button>
                    </td>
                </tr>
            `;
        });
    })
    .catch(err => {
        document.getElementById("studentTable").innerHTML = "<tr><td colspan='5' style='color:var(--danger); text-align:center; padding:20px;'>Connection error. Check backend path.</td></tr>";
    });
}

function showAddForm() { window.location.href = "add_user.php?role=student"; }
function editStudent(id) { window.location.href = "update_user.php?role=student&id=" + id; }

function deleteStudent(id) {
    if (!confirm("Permanently delete record ID: " + id + "?")) return;
    let formData = new FormData();
    formData.append("id", id);
    formData.append("type", "student");

    fetch("delete_user.php", { method: "POST", body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === "success") {
            closeModal();
            loadStudents(); 
        } else alert("Error: " + data.message);
    });
}

function viewStudent(s){
    document.getElementById('modalOverlay').style.display = 'flex';
    document.getElementById('details').innerHTML = `
        <button class="close-btn" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <img src="${s.photo || 'https://via.placeholder.com/100'}" style="width:90px; height:90px; border-radius:18px; object-fit:cover; border:3px solid var(--accent-mint);">
            <div>
                <h2 style="margin:0; font-size:1.8rem;">${s.student_name}</h2>
                <p style="margin:8px 0 0; color:var(--accent-mint); font-weight:700; font-family:monospace;">${s.student_id} • ${s.program || 'GENERAL'}</p>
            </div>
        </div>
        <div class="modal-body">
            <div class="data-grid">
                <div class="data-item"><b>Identification (Barcode)</b><span>${s.barcode_no || 'N/A'}</span></div>
                <div class="data-item"><b>Government ID (Redacted)</b><span>[Aadhaar Redacted]</span></div>
                <div class="data-item"><b>Contact Mobile</b><span>${s.mobile}</span></div>
                <div class="data-item"><b>Institutional Email</b><span>${s.email || 'N/A'}</span></div>
                <div class="data-item"><b>Parental Information</b><span>${s.father_name} / ${s.mother_name}</span></div>
                <div class="data-item"><b>Major / Honours</b><span>${s.honourse}</span></div>
                <div class="data-item"><b>Admission Year</b><span>${s.year}</span></div>
                <div class="data-item"><b>Admission Date</b><span>${s.admission_date}</span></div>
            </div>
            <div class="data-item" style="margin-top:30px; padding:20px; background:rgba(0,0,0,0.1); border-radius:15px;">
                <b>Full Address</b>
                <span>${s.address || 'No address provided'}</span>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-modal btn-upd" onclick="editStudent('${s.student_id}')"><i class="fa-solid fa-pen-to-square"></i> Modify Record</button>
            <button class="btn-modal btn-del" onclick="deleteStudent('${s.student_id}')"><i class="fa-solid fa-trash-can"></i> Purge Student</button>
        </div>
    `;
}
   
function closeModal() { document.getElementById('modalOverlay').style.display = 'none'; }
loadStudents();
</script>
</body>
</html>