<?php
ob_start(); // Start buffering to catch accidental output
session_start();
require_once '../db.php';

// Clear any whitespace/warnings before sending JSON
ob_clean(); 
header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    // 1. Logic for Adding Manual Admin or Promoting HOD
    if ($action === 'add_manual' || $action === 'promote_hod') {
        $id = trim($_POST['admin_id']);
        $name = trim($_POST['admin_name']);
        $email = trim($_POST['email']);
        
        if(empty($id) || empty($name)) throw new Exception("ID and Name are required.");

        // Generate temporary password (e.g., John@2026)
        $firstName = explode(' ', $name)[0];
        $temp_pass = $firstName . "@" . date('Y');
        $hashed_pass = password_hash($temp_pass, PASSWORD_DEFAULT);

        // Check if ID already exists in the 'admin' table
        $check = $conn->prepare("SELECT admin_id FROM admin WHERE admin_id = ?");
        $check->bind_param("s", $id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            throw new Exception("This User ID is already registered as an Admin.");
        }

        // Insert into 'admin' table
        
       // Update the INSERT statement to include isfirstlogin
        $stmt = $conn->prepare("INSERT INTO admin (admin_id, admin_name, email, password, role, isfirstlogin) VALUES (?, ?, ?, ?, 'admin', 1)");
        $stmt->bind_param("ssss", $id, $name, $email, $hashed_pass);
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success', 
                'message' => "Successfully added $name as Admin.",
                'temp_pass' => $temp_pass
            ]);
        } else {
            throw new Exception("Execution failed: " . $conn->error);
        }
    }

    // 2. Logic for Deleting Admin
    if ($action === 'delete_admin') {
        $id = $_POST['admin_id'];
        
        if($id === $_SESSION['admin_id']) throw new Exception("Security Alert: You cannot delete your own account.");

        $stmt = $conn->prepare("DELETE FROM admin WHERE admin_id = ?");
        $stmt->bind_param("s", $id);
        
        if($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Admin removed successfully.']);
        } else {
            throw new Exception("Delete operation failed.");
        }
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit;