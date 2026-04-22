<?php
// Start session to store student login info
session_start();

// Include DB connection
require 'db.php';

// -------------------------------
//  Check if login form is submitted
// -------------------------------
if (!isset($_POST['login'], $_POST['password'])) {
    die("Invalid request");
}

// Read submitted form values
$login = trim($_POST['login']);
$password_entered = trim($_POST['password']);
// password entered by student

// -------------------------------
// Prepare query to fetch only necessary columns
//    Use prepared statements to prevent SQL injection
// -------------------------------
$sql = "SELECT student_id, student_name, email, password, is_first_login
        FROM student_table1
        WHERE student_id = ? LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $login);
mysqli_stmt_execute($stmt);

// -------------------------------
//  Bind results directly for faster access
// -------------------------------
mysqli_stmt_bind_result($stmt, $student_id_db, $student_name_db, $email_db, $hashed_password_db, $is_first_login_db);

// -------------------------------
// Fetch the row if exists
// -------------------------------
if (mysqli_stmt_fetch($stmt)) {

    // -------------------------------
    // Verify password using password_verify
    // -------------------------------
    if (password_verify($password_entered, $hashed_password_db)) {

        // -------------------------------
        //  Start session and store info
        //    Prevent session fixation
        // -------------------------------
        session_regenerate_id(true);
        $_SESSION['student_id']   = $student_id_db;
        $_SESSION['student_name'] = $student_name_db;
        $_SESSION['role']         = "student";
        $_SESSION['login']        = true;

        // -------------------------------
        //  Check if this is the first login
        //    Redirect to change password page if yes
        // -------------------------------
        if ($is_first_login_db == 1) {
            // First login detected → redirect to change password page
            header("Location: change_password.php?student_id=" . urlencode($student_id_db));
            exit(); // Stop further execution after redirect
        }

        // -------------------------------
        // Normal login: redirect to student dashboard
        // -------------------------------
        header("Location: student_dashboard.php");
        exit();

    } else {
        // Password incorrect
        header("Location: ../Frontend/student_login.html?error=" . urlencode("Login failed. Incorrect ID or password."));
        exit();
    }

} else {
    // Student ID not found
    header("Location: ../Frontend/student_login.html?error=" . urlencode("Login failed. Incorrect ID or password."));
    exit();
}

//  Close statement
mysqli_stmt_close($stmt);
?>