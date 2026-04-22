<?php
require 'db.php';
require 'sendmail.php'; // Your working PHPMailer wrapper

// Fetch pending mails (Limit 20 to avoid Gmail rate limits)
$result = $conn->query("SELECT * FROM mail_queue WHERE status='pending' LIMIT 20");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $mail_id = $row['mail_id'];
        $email = $row['recipient_email'];
        $subject = $row['subject'];
        $message = $row['message']; // Using 'message' column from queue
        
        // Attempt to send via your existing sendmail.php logic
        $status = sendMail($email, $subject, $message);

        if ($status === true) {
            // 1. Move to mail_log as 'sent'
            $log_stmt = $conn->prepare("INSERT INTO mail_log (recipient_email, subject, status) VALUES (?, ?, 'sent')");
            $log_stmt->bind_param("ss", $email, $subject);
            $log_stmt->execute();
            $log_stmt->close();

            // 2. Delete from active mail_queue to keep it clean
            $del_stmt = $conn->prepare("DELETE FROM mail_queue WHERE mail_id = ?");
            $del_stmt->bind_param("i", $mail_id);
            $del_stmt->execute();
            $del_stmt->close();
        } else {
            // Handle SMTP Failure
            $error_info = is_string($status) ? $status : "Unknown SMTP Error";
            $log_stmt = $conn->prepare("INSERT INTO mail_log (recipient_email, subject, status, error_message) VALUES (?, ?, 'error', ?)");
            $log_stmt->bind_param("sss", $email, $subject, $error_info);
            $log_stmt->execute();
            $log_stmt->close();

            // Update status in queue to 'failed'
            $stmt = $conn->prepare("UPDATE mail_queue SET status='failed' WHERE mail_id=?");
            $stmt->bind_param("i", $mail_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>