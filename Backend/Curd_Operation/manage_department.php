<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "../db.php";
require_once "access_control.php";

// Restrict to Admin only
if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments | Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { 
            --primary: #14b8a6; 
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
        
        /* Modern Header */
        .header-flex { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 40px; 
            gap: 25px; 
        }

        .page-title h1 { 
            margin: 0; 
            font-size: 1.8rem; 
            font-weight: 800; 
            background: linear-gradient(to right, #fff, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Search Bar Enhancement */
        .search-box { flex-grow: 1; position: relative; max-width: 600px; }
        .search-box input { 
            width: 100%; 
            padding: 14px 20px 14px 50px; 
            border-radius: 16px; 
            border: 1px solid var(--glass-border); 
            background: rgba(30, 41, 59, 0.5);
            color: white;
            font-size: 0.95rem; 
            backdrop-filter: blur(10px);
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
            box-sizing: border-box;
        }
        .search-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(20, 184, 166, 0.15);
            background: rgba(30, 41, 59, 0.8);
        }
        .search-box i { 
            position: absolute; 
            left: 20px; 
            top: 50%; 
            transform: translateY(-50%); 
            color: var(--primary);
            font-size: 1.1rem;
        }

        /* Department Cards */
        .dept-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); 
            gap: 25px; 
        }

        .dept-card { 
            background: var(--card-bg); 
            border-radius: 24px; 
            padding: 25px; 
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(12px);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
            cursor: pointer; 
            position: relative; 
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .dept-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 4px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
            opacity: 0; transition: 0.3s;
        }

        .dept-card:hover { 
            transform: translateY(-8px); 
            border-color: rgba(20, 184, 166, 0.4);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
        }

        .dept-card:hover::before { opacity: 1; }

        .dept-name { 
            font-size: 1.3rem; 
            font-weight: 800; 
            color: white; 
            margin: 0 0 20px 0; 
            line-height: 1.3;
        }
        
        .stats-row { 
            display: flex; 
            justify-content: space-between; 
            background: rgba(15, 23, 42, 0.4);
            padding: 15px; 
            border-radius: 18px;
            margin-top: 10px; 
        }

        .stat-item { text-align: center; flex: 1; }
        .stat-val { 
            display: block; 
            font-weight: 800; 
            color: var(--primary); 
            font-size: 1.2rem; 
        }
        .stat-label { 
            font-size: 0.65rem; 
            color: var(--text-dim); 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            font-weight: 600;
        }

        /* Loading shimmer for search results */
        .loading-text { text-align: center; padding: 50px; color: var(--text-dim); }

        @media (max-width: 768px) {
            .header-flex { flex-direction: column; align-items: flex-start; }
            .search-box { width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-flex">
        <div class="page-title">
            <h1>Academic Departments</h1>
        </div>
        <div class="search-box">
            <i class="fa fa-search"></i>
            <input type="text" id="universalSearch" placeholder="Search departments, students, or programs..." onkeyup="handleSearch(this.value)">
        </div>
    </div>

    <div id="displayArea" class="dept-grid">
        <?php
        // EXACT ORIGINAL SQL LOGIC
        $sql = "SELECT d.department_id, d.department_name,
        (SELECT COUNT(*) FROM teacher_department td WHERE td.department_id = d.department_id) as t_count,
        
        (SELECT COUNT(*) FROM student_table1 s 
         JOIN department_structure ds ON s.department_structure_id = ds.structure_id 
         JOIN programs p ON ds.program_id = p.program_id
         WHERE ds.department_id = d.department_id AND p.program_name = 'UG') as ug_count,
        
        (SELECT COUNT(*) FROM student_table1 s 
         JOIN department_structure ds ON s.department_structure_id = ds.structure_id 
         JOIN programs p ON ds.program_id = p.program_id
         WHERE ds.department_id = d.department_id AND p.program_name = 'PG') as pg_count
        
        FROM departments d";

        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                ?>
                <div class="dept-card" onclick="viewDept('<?php echo $row['department_id']; ?>')">
                    <h3 class="dept-name"><?php echo $row['department_name']; ?></h3>
                    <div class="stats-row">
                        <div class="stat-item">
                            <span class="stat-val"><?php echo $row['t_count']; ?></span>
                            <span class="stat-label">Teachers</span>
                        </div>
                        <div class="stat-item" style="border-left: 1px solid rgba(255,255,255,0.05); border-right: 1px solid rgba(255,255,255,0.05);">
                            <span class="stat-val"><?php echo $row['ug_count']; ?></span>
                            <span class="stat-label">UG Students</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-val"><?php echo $row['pg_count']; ?></span>
                            <span class="stat-label">PG Students</span>
                        </div>
                    </div>
                </div>
                <?php
            }
        } else {
            echo "<div style='grid-column: 1/-1; text-align:center; padding: 50px;'>No departments found.</div>";
        }
        ?>
    </div>
</div>

<script>
let debounceTimer;

function handleSearch(query) {
    const displayArea = document.getElementById('displayArea');
    
    // EXACT ORIGINAL CLEAR LOGIC
    if (query.length < 2) {
        if (query.length === 0) {
            location.reload(); 
        }
        return;
    }

    clearTimeout(debounceTimer);

    // EXACT ORIGINAL DEBOUNCE TIMER
    debounceTimer = setTimeout(() => {
        // EXACT ORIGINAL FETCH PATH
        fetch(`universal_search_handler.php?q=${encodeURIComponent(query)}`)
            .then(res => {
                if (!res.ok) throw new Error('File not found');
                return res.text();
            })
            .then(html => {
                displayArea.innerHTML = html;
            })
            .catch(err => {
                console.error("Search Error:", err);
                displayArea.innerHTML = "<div class='dept-card' style='grid-column: 1/-1; text-align:center;'>Search handler not found.</div>";
            });
    }, 300);
}

function viewDept(id) {
    window.location.href = `view_department_details.php?id=${id}`;
}
</script>

</body>
</html>