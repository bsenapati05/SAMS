<?php
require_once "../db.php";
$dept_id = $_GET['dept_id'] ?? '';
$type = $_GET['type'] ?? 'teacher';

// Updated Styles for Dark Theme Compatibility
echo "<style>
    .img-circle {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        object-fit: cover;
        border: 2px solid rgba(20, 184, 166, 0.3);
        display: block;
        margin: auto;
    }
    /* Transparent Background & Light Text */
    table { width: 100%; border-collapse: collapse; background: transparent; color: var(--text-main); }
    
    th { 
        background: rgba(15, 23, 42, 0.6); 
        color: var(--primary); 
        font-size: 0.75rem; 
        text-transform: uppercase; 
        letter-spacing: 1px;
    }

    td { padding: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); text-align: center; font-size: 0.9rem; }
    
    tr:hover td { background: rgba(255, 255, 255, 0.02); }

    .badge { 
        padding: 4px 10px; 
        background: rgba(20, 184, 166, 0.1); 
        color: var(--primary); 
        border-radius: 6px; 
        font-size: 0.75rem; 
        font-weight: 700;
    }

    /* Modern Action Buttons */
    .btn-upgrade { background: var(--primary); color: white; border: none; padding: 6px 12px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.8rem; }
    .btn-delete { background: var(--danger); color: white; border: none; padding: 6px 12px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.8rem; }
    .btn-edit { background: rgba(255,255,255,0.05); color: white; border: 1px solid var(--glass-border); padding: 6px 12px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.8rem; }

    /* Batch Box Enhancement */
    .batch-container { padding: 20px; background: rgba(15, 23, 42, 0.4); border-radius: 16px; margin-bottom: 20px; border: 1px solid var(--glass-border); }
    .batch-box { 
        border: 1px solid var(--glass-border); 
        padding: 12px; 
        border-radius: 12px; 
        background: rgba(30, 41, 59, 0.5); 
        display: inline-flex; 
        align-items: center;
        gap: 10px;
        margin-right: 10px; 
        margin-top: 5px;
    }
    
    input[type='checkbox'] { accent-color: var(--primary); transform: scale(1.2); cursor: pointer; }
</style>";

// Helper function to resolve photo path (Logic Unchanged)
function resolvePhotoPath($photo, $userType) {
    $photo = trim($photo ?? '');
    $basePath = '/SMS/Backend/uploads/profile_photo/';
    if ($photo === '') return $basePath . 'default.png';
    if (preg_match('#^(https?:)?//#i', $photo) || str_starts_with($photo, '/')) return $photo;

    if ($userType === 'student') {
        if (strpos($photo, 'uploads/profile_photo/students/') === 0) return '/SMS/Backend/' . ltrim($photo, '/');
        if (strpos($photo, 'students/') === 0) return $basePath . ltrim($photo, '/');
        return $basePath . 'students/' . ltrim($photo, '/');
    } else {
        if (strpos($photo, 'uploads/profile_photo/teachers/') === 0) return '/SMS/Backend/' . ltrim($photo, '/');
        return $basePath . 'teachers/' . ltrim($photo, '/');
    }
}

if ($type === 'teacher') {
    $sql = "SELECT t.* FROM teachers t 
            JOIN teacher_department td ON t.teacher_id = td.teacher_id 
            WHERE td.department_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $dept_id);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<table><thead><tr>
          <th><input type='checkbox' onclick='toggleAll(this)'></th>
          <th>Photo</th><th>ID</th><th>Name</th><th>Role</th><th>Actions</th>
          </tr></thead><tbody>";
    
    while($row = $result->fetch_assoc()) {
        $photoPath = resolvePhotoPath($row['photo'], 'teacher');
        echo "<tr>
                <td><input type='checkbox' class='row-check' value='{$row['teacher_id']}' onclick='updateBulkBar()'></td>
                <td><img src='{$photoPath}' class='img-circle'></td>
                <td><span style='color:var(--primary); font-weight:700;'>{$row['teacher_id']}</span></td>
                <td style='font-weight:600;'>{$row['teacher_name']}</td>
                <td><span class='badge'>{$row['role']}</span></td>
                <td>
                    <button class='btn-edit' onclick='editUser(\"teacher\", \"{$row['teacher_id']}\")'><i class='fas fa-edit'></i></button>
                    <button class='btn-delete' onclick='deleteSingle(\"teacher\", \"{$row['teacher_id']}\")'><i class='fas fa-trash'></i></button>
                </td>
              </tr>";
    }
    echo "</tbody></table>";

} else {
    $program = strtoupper($type); 
    $sql = "SELECT s.* FROM student_table1 s 
            JOIN department_structure ds ON s.department_structure_id = ds.structure_id 
            WHERE ds.department_id = ? AND s.program = ?
            ORDER BY s.year ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $dept_id, $program);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<div class='batch-container'>
            <strong style='display:block; margin-bottom:12px; color:var(--primary); font-size:0.8rem; letter-spacing:1px; text-transform:uppercase;'>Batch Management:</strong>";
            for ($i = 1; $i <= 3; $i++) {
                echo "<div class='batch-box'>
                        <span style='font-weight:700; font-size:0.85rem;'>Year $i</span> 
                        <button class='btn-upgrade' onclick='upgradeBatch($i)' title='Upgrade Batch'><i class='fas fa-level-up-alt'></i></button>
                        <button class='btn-delete' onclick='deleteBatch($i)' title='Wipe Batch'><i class='fas fa-eraser'></i></button>
                      </div>";
            }
    echo "</div>";

    echo "<table><thead><tr>
          <th><input type='checkbox' onclick='toggleAll(this)'></th>
          <th>Photo</th><th>ID</th><th>Name</th><th>Year</th><th>Actions</th>
          </tr></thead><tbody>";
    
    while($row = $result->fetch_assoc()) {
        $photoPath = resolvePhotoPath($row['photo'], 'student');
        echo "<tr>
                <td><input type='checkbox' class='row-check' value='{$row['student_id']}' onclick='updateBulkBar()'></td>
                <td><img src='{$photoPath}' class='img-circle'></td>
                <td><span style='color:var(--primary); font-weight:700;'>{$row['student_id']}</span></td>
                <td style='font-weight:600;'>{$row['student_name']}</td>
                <td><span class='badge'>Year {$row['year']}</span></td>
                <td>
                    <button class='btn-edit' onclick='editUser(\"student\", \"{$row['student_id']}\")'><i class='fas fa-edit'></i></button>
                    <button class='btn-delete' onclick='deleteSingle(\"student\", \"{$row['student_id']}\")'><i class='fas fa-trash'></i></button>
                </td>
              </tr>";
    }
    echo "</tbody></table>";
}
?>