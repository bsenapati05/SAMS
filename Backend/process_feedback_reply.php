<?php
session_start();
require_once 'db.php';
require_once 'mail_templates.php'; // Ensure your queueFeedbackReply function is here

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $toEmail      = $_POST['recipient_email'];
    $toName       = $_POST['recipient_name'];
    $adminName    = $_POST['admin_name'];
    $replyMessage = $_POST['reply_message'];

    // 1. Add to Database Queue using your existing addToQueue logic
    if (queueFeedbackReply($toEmail, $toName, $adminName, $replyMessage)) {
        
        // 2. Trigger the worker instantly in the background
        // Windows (XAMPP)
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen("start /B php mail_worker.php", "r")); 
        } else {
            // Linux/Ubuntu
            exec("php mail_worker.php > /dev/null 2>&1 &");
        }

        echo "<script>
                alert('Reply has been queued and sent instantly via Mail Worker!');
                window.location.href='admin_view_feedback.php';
              </script>";
    } else {
        echo "<script>alert('Error saving to mail queue.'); window.location.href='admin_view_feedback.php';</script>";
    }
}