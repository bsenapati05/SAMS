<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../Frontend/admin_login.html");
    exit();
}

$admin_id = trim($_POST['admin_id'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($admin_id) || empty($password)) {
    header("Location: ../Frontend/admin_login.html?error=empty");
    exit();
}

/* Fetch Admin - Added isfirstlogin to SELECT */
$stmt = $conn->prepare("SELECT admin_id, admin_name, email, password, isfirstlogin FROM admin WHERE admin_id = ? LIMIT 1");
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: ../Frontend/admin_login.html?error=invalid");
    exit();
}

$admin = $result->fetch_assoc();

/* Verify password */
if (!password_verify($password, $admin['password'])) {
    header("Location: ../Frontend/admin_login.html?error=invalid");
    exit();
}

/* Secure session */
session_regenerate_id(true);

$_SESSION['login']      = true;
$_SESSION['admin_id']    = $admin['admin_id'];
$_SESSION['admin_name']  = $admin['admin_name'];
$_SESSION['role']       = 'admin';

/* NEW: Mandatory Password Change Check */
if ($admin['isfirstlogin'] == 1) {
    header("Location: change_password.php");
    exit();
}

/* Normal Redirect */
header("Location: admin_dashboard.php");
exit();