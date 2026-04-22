<?php
require_once "../db.php";

$search = isset($_POST['query']) ? trim($_POST['query']) : '';
$type = $_POST['type'] ?? 'student'; // student, teacher, or admin
$term = "%$search%";

if ($type === 'student') {
    // Student Search: Joins Department name via Structure
    $sql = "SELECT s.student_id as id, s.student_name as name, s.mobile, d.department_name as context
            FROM student_table1 s
            LEFT JOIN department_structure ds ON s.department_structure_id = ds.structure_id
            LEFT JOIN departments d ON ds.department_id = d.department_id
            WHERE (s.student_id LIKE ? OR s.student_name LIKE ? OR s.mobile LIKE ? OR d.department_name LIKE ?)
            LIMIT 20";
} else {
    // Teacher/Admin Search: Filters by the 'role' column
    // Joins Department via the teacher_department mapping table
    $role_filter = ($type === 'admin') ? 'admin' : 'teacher';
    
    $sql = "SELECT t.teacher_id as id, t.teacher_name as name, t.mobile, d.department_name as context
            FROM teachers t
            LEFT JOIN teacher_department td ON t.teacher_id = td.teacher_id
            LEFT JOIN departments d ON td.department_id = d.department_id
            WHERE t.role = '$role_filter' 
            AND (t.teacher_id LIKE ? OR t.teacher_name LIKE ? OR t.mobile LIKE ? OR d.department_name LIKE ?)
            LIMIT 20";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $term, $term, $term, $term);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td><strong>{$row['id']}</strong></td>
                <td>{$row['name']}</td>
                <td><span class='badge'>{$row['context']}</span></td>
                <td>{$row['mobile']}</td>
                <td>
                    <button onclick='editUser(\"{$row['id']}\", \"$type\")' class='btn-action'><i class='fas fa-edit'></i></button>
                </td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='5' style='text-align:center; padding:20px;'>No $type records found.</td></tr>";
}