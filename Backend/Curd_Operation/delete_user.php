<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "../db.php";
require_once "access_control.php"; 

header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? ''; // Can be a string or an array for bulk
    $type = $_POST['type'] ?? ''; 
    $batch_year = $_POST['batch_year'] ?? ''; // For batch delete
    $dept_id = $_POST['dept_id'] ?? '';      // For batch delete

    // 1. Determine Table & Config
    $config = [
        'student' => ['table' => 'student_table1', 'id' => 'student_id', 'name' => 'student_name'],
        'teacher' => ['table' => 'teachers', 'id' => 'teacher_id', 'name' => 'teacher_name'],
        'admin'   => ['table' => 'admin_table', 'id' => 'admin_id', 'name' => 'admin_name']
    ];

    if (!isset($config[$type])) {
        echo json_encode(["status" => "error", "message" => "Invalid type"]);
        exit;
    }
    
    $meta = $config[$type];

    // 2. Permission Check
    if (!isAdmin()) {
        echo json_encode(["status" => "error", "message" => "Unauthorized"]);
        exit;
    }

    $conn->query("SET FOREIGN_KEY_CHECKS = 0;");

    // --- CASE A: BATCH DELETE (By Year and Department) ---
    if (!empty($batch_year) && !empty($dept_id) && $type === 'student') {
        $sql = "DELETE s FROM student_table1 s 
                JOIN department_structure ds ON s.department_structure_id = ds.structure_id 
                WHERE s.year = ? AND ds.department_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $batch_year, $dept_id);
        
        if ($stmt->execute()) {
            $count = $stmt->affected_rows;
            $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
            echo json_encode(["status" => "success", "message" => "Successfully removed $count students from Year $batch_year."]);
        } else {
            $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
            echo json_encode(["status" => "error", "message" => "Batch delete failed."]);
        }
        exit;
    }

    // --- CASE B: BULK DELETE (Array of IDs) ---
    if (is_array($id)) {
        $placeholders = implode(',', array_fill(0, count($id), '?'));
        $sql = "DELETE FROM {$meta['table']} WHERE {$meta['id']} IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $types = str_repeat('s', count($id));
        $stmt->bind_param($types, ...$id);
        
        if ($stmt->execute()) {
            $count = $stmt->affected_rows;
            $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
            echo json_encode(["status" => "success", "message" => "Bulk deleted $count records."]);
        } else {
            $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
            echo json_encode(["status" => "error", "message" => "Bulk delete failed."]);
        }
        exit;
    }

    // --- CASE C: SINGLE DELETE (Your original working code) ---
    if (empty($id)) {
        echo json_encode(["status" => "error", "message" => "Missing data"]);
        exit;
    }

    // Get User Data for Email (Before Deletion)
    $info_stmt = $conn->prepare("SELECT {$meta['name']} as name, email FROM {$meta['table']} WHERE {$meta['id']} = ?");
    $info_stmt->bind_param("s", $id);
    $info_stmt->execute();
    $user_to_notify = $info_stmt->get_result()->fetch_assoc();

    $sql = "DELETE FROM {$meta['table']} WHERE {$meta['id']} = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $id);

    if ($stmt->execute()) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
        
        if ($stmt->affected_rows > 0) {
            try {
                if ($user_to_notify && !empty($user_to_notify['email'])) {
                    $subject = "Account Deactivated | DAC SMS";
                    $message = "Hello " . $user_to_notify['name'] . ",\n\nYour account (" . $id . ") has been deactivated by the administrator.";
                    queueSystemMail($conn, $user_to_notify['email'], $subject, $message);
                }
            } catch (Exception $e) {}

            echo json_encode(["status" => "success", "message" => ucfirst($type) . " deleted successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "User not found."]);
        }
    } else {
        $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
        echo json_encode(["status" => "error", "message" => "Database error."]);
    }
    exit;
}