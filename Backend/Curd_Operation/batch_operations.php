<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "../db.php";
require_once "access_control.php";

header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $action = $_POST['action'] ?? '';
    $year = $_POST['year'] ?? '';
    $dept_id = $_POST['dept_id'] ?? '';

    if ($action === 'upgrade_batch') {
        // SQL to increment the year for a specific department and current year
        $sql = "UPDATE student_table1 s 
                JOIN department_structure ds ON s.department_structure_id = ds.structure_id 
                SET s.year = s.year + 1 
                WHERE s.year = ? AND ds.department_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $year, $dept_id);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Batch promoted to Year " . ($year + 1)]);
        } else {
            echo json_encode(["status" => "error", "message" => "Upgrade failed: " . $conn->error]);
        }
    }
    exit;
}