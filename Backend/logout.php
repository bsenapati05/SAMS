<?php
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Store role before destroying session
$role = $_SESSION['role'] ?? null;

// Clear session
$_SESSION = array();
session_unset();
session_destroy();

// Redirect based on role
if ($role === "student") {
    header("Location: ../Frontend/student_login.html");
}
elseif ($role === "teacher") {
    header("Location: ../Frontend/teacher_login.html");
}
elseif ($role === "admin") {
    header("Location: ../Frontend/admin_login.html");
}
else {
    header("Location: ../Frontend/index.html");
}

exit();
?>
