<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../Frontend/teacher_login.html");
    exit();
}

$teacher_id = trim($_POST['teacher_id'] ?? '');
$password   = $_POST['password'] ?? '';

if (empty($teacher_id) || empty($password)) {
    header("Location: ../Frontend/teacher_login.html?error=empty");
    exit();
}

/* Fetch teacher - ensuring isfirstlogin is selected */
$stmt = $conn->prepare("
    SELECT teacher_id, teacher_name, email, password, isfirstlogin
    FROM teachers
    WHERE teacher_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: ../Frontend/teacher_login.html?error=invalid");
    exit();
}

$teacher = $result->fetch_assoc();

/* Verify password */
if (!password_verify($password, $teacher['password'])) {
    header("Location: ../Frontend/teacher_login.html?error=invalid");
    exit();
}

/* Secure session */
session_regenerate_id(true);

$_SESSION['login']        = true;
$_SESSION['teacher_id']   = $teacher['teacher_id'];
$_SESSION['teacher_name'] = $teacher['teacher_name'];
$_SESSION['email']        = $teacher['email'];

/* Check HOD role */
$role = 'teacher';
$hod_stmt = $conn->prepare("SELECT 1 FROM teacher_department WHERE teacher_id = ? AND ishod = 1 LIMIT 1");
$hod_stmt->bind_param("s", $teacher_id);
$hod_stmt->execute();
$hod_result = $hod_stmt->get_result();

if ($hod_result->num_rows === 1) {
    $role = 'hod';
}
$_SESSION['role'] = $role;

/* MANDATORY FIRST LOGIN CHECK */
if ($teacher['isfirstlogin'] == 1) {
    // Redirect to change_password.php before they can access any dashboard
    header("Location: change_password.php");
    exit();
}

/* Normal Redirect if not first login */
if ($role === 'hod') {
    header("Location: hod_dashboard.php");
} else {
    header("Location: teacher_dashboard.php");
}
exit();
?>