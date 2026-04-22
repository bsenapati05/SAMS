<?php

session_start();
require_once "../db.php";

header("Content-Type: application/json");

// ================= SESSION + ROLE CHECK =================
if (!isset($_SESSION['teacher_id'], $_SESSION['role']) || strtolower($_SESSION['role']) !== "hod") {
    echo json_encode([]);
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

// ================= QUERY =================
$sql = "
SELECT d.department_id, d.department_name
FROM teacher_department td
JOIN departments d ON td.department_id = d.department_id
WHERE td.teacher_id=? AND td.ishod=1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([]);
    exit();
}

$stmt->bind_param("s", $teacher_id);
$stmt->execute();

$result = $stmt->get_result();

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data ?? []);