<?php
require_once 'db.php';

/**
 * Helper function to push any mail into the background queue
 */
function addToQueue($email, $subject, $message) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO mail_queue (recipient_email, subject, message, status) VALUES (?, ?, ?, 'pending')");
    $stmt->bind_param("sss", $email, $subject, $message);
    return $stmt->execute();
}

/* ===============================
   Template: Welcome Mail
================================ */
function queueWelcomeMail($email, $name, $userid, $password) {
    $subject = "Welcome to Student Management System | DAC";
    $body = "Hello $name,\n\n"
          . "Your account has been successfully created. Please find your login details below:\n\n"
          . "----------------------------------\n"
          . "User ID:  $userid\n"
          . "Password: $password\n"
          . "----------------------------------\n\n"
          . "Login Here: http://localhost/SMS/Frontend/index.html\n\n"
          . "NOTE: For your security, please update your password immediately after logging in.\n\n"
          . "Regards,\nAdmin Office";

    return addToQueue($email, $subject, $body);
}

/* ===============================
   Template: Account Deletion
================================ */
function queueAccountDeleteMail($email, $name, $userid) {
    $subject = "Account Deletion Notice";
    $body = "Hello $name,\n\n"
          . "This is to inform you that your account (User ID: $userid) has been removed from the Student Management System.\n\n"
          . "If you believe this was done in error or have questions regarding this action, please contact the Department Head or Admin Office immediately.\n\n"
          . "Regards,\nAdministration";

    return addToQueue($email, $subject, $body);
}

/* ===============================
   Template: General Notice/Notification
================================ */
function queueGeneralNotice($email, $name, $notice_title, $notice_content) {
    $subject = "New Notification: $notice_title";
    $body = "Dear $name,\n\n"
          . "There is a new update regarding: $notice_title\n"
          . "----------------------------------\n\n"
          . $notice_content . "\n\n"
          . "----------------------------------\n"
          . "You can view full details by logging into your portal.\n\n"
          . "Regards,\nSystem Administrator";

    return addToQueue($email, $subject, $body);
}
/* ===============================
   Template: Feedback Reply
================================ */
function queueFeedbackReply($email, $name, $admin_name, $reply_content) {
    $subject = "Official Response: Your Feedback to DAC";
    $body = "Dear $name,\n\n"
          . "This is an official response from the Administration regarding the feedback you submitted.\n\n"
          . "----------------------------------\n"
          . "ADMINISTRATOR: $admin_name\n"
          . "MESSAGE:\n" . $reply_content . "\n"
          . "----------------------------------\n\n"
          . "If you have further questions, please reply to this email or visit the Admin Office.\n\n"
          . "Regards,\n$admin_name\nDhenkanal Autonomous College";

    return addToQueue($email, $subject, $body);
}
?>