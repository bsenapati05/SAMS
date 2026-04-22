<?php
require_once "../db.php"; 
$q = $_GET['q'] ?? '';

if (empty($q)) exit;

$searchTerm = "%$q%";

// JOINing tables - Column name fixed to s.department_structure_id
$sql = "SELECT 
            s.student_id, 
            s.student_name, 
            s.photo, 
            s.year,
            d.department_name, 
            st.stream_name, 
            p.program_name 
        FROM student_table1 s
        JOIN department_structure ds ON s.department_structure_id = ds.structure_id
        JOIN departments d ON ds.department_id = d.department_id
        JOIN streams st ON ds.stream_id = st.stream_id
        JOIN programs p ON ds.program_id = p.program_id
        WHERE s.student_name LIKE ? 
           OR s.student_id LIKE ? 
           OR d.department_name LIKE ? 
           OR p.program_name LIKE ? 
           OR st.stream_name LIKE ? 
           OR s.year LIKE ?
        ORDER BY s.student_name ASC 
        LIMIT 20";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $photoFile = !empty($row['photo']) ? $row['photo'] : 'default.png';
        $photoPath = (strpos($photoFile, 'uploads/') === 0) 
                     ? "/SMS/Backend/" . $photoFile 
                     : "/SMS/Backend/uploads/profile_photo/students/" . $photoFile;

        echo "
        <div class='dept-card' onclick='window.location.href=\"student_profile.php?id={$row['student_id']}\"' 
             style='margin-bottom:15px; border-left: 4px solid var(--primary);'>
            <div style='display:flex; align-items:center; gap:20px;'>
                
                <div style='position:relative;'>
                    <img src='$photoPath' style='width:60px; height:60px; border-radius:18px; object-fit:cover; border: 2px solid rgba(20, 184, 166, 0.3);'>
                    <div style='position:absolute; bottom:-5px; right:-5px; width:15px; height:15px; background:#10b981; border: 2px solid var(--bg-dark); border-radius:50%;'></div>
                </div>

                <div style='flex:1;'>
                    <h4 style='margin:0; color:white; font-size:1.1rem; font-weight:700;'>".htmlspecialchars($row['student_name'])."</h4>
                    <div style='color:var(--text-dim); font-size:0.75rem; margin-top:4px;'>
                        <span style='color:var(--primary); font-weight:600;'>ID: {$row['student_id']}</span> 
                        <span style='margin:0 8px; opacity:0.3;'>|</span> 
                        {$row['stream_name']} ({$row['program_name']})
                    </div>
                </div>

                <div style='text-align:right;'>
                    <div style='background:rgba(20, 184, 166, 0.1); color:var(--primary); padding:4px 12px; border-radius:8px; font-size:0.7rem; font-weight:800; text-transform:uppercase;'>
                        {$row['department_name']}
                    </div>
                    <div style='color:var(--text-dim); font-size:0.7rem; margin-top:8px; font-weight:600;'>
                        YEAR: {$row['year']}
                    </div>
                </div>

            </div>
        </div>";
    }
} else {
    echo "
    <div style='grid-column:1/-1; padding:60px 20px; text-align:center; background:var(--card-bg); border-radius:24px; border:1px dashed var(--glass-border);'>
        <i class='fas fa-user-slash' style='font-size:2.5rem; color:var(--text-dim); opacity:0.3; margin-bottom:15px; display:block;'></i>
        <span style='color:var(--text-dim); font-weight:600;'>No student matches found for '".htmlspecialchars($q)."'</span>
    </div>";
}
?>