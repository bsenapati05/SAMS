<?php
require 'db.php';
require 'sendmail.php';
session_start();
date_default_timezone_set('Asia/Kolkata'); 

$message = "";
$showOtpField = false;

// --- RESET LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['step'])) {
    unset($_SESSION['otp_sent']);
    unset($_SESSION['otp_user_id']);
    unset($_SESSION['otp_user_type']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ------------------- SEND OTP -------------------
    if (isset($_POST['send_otp'])) {
        $user_type = trim($_POST['user_type'] ?? '');
        $user_id   = trim($_POST['user_id'] ?? '');
        $email     = trim($_POST['email'] ?? '');

        if ($user_type === 'student') {
            $stmt = $conn->prepare("SELECT * FROM student_table1 WHERE student_id=? AND email=?");
        } elseif ($user_type === 'teacher') {
            $stmt = $conn->prepare("SELECT * FROM teachers WHERE teacher_id=? AND email=?");
        } elseif ($user_type === 'admin') {
            $stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id=? AND email=?");
        } else {
            $message = "Invalid user type selected.";
            $stmt = null;
        }

        if ($stmt) {
            $stmt->bind_param("ss", $user_id, $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));

                $stmt_del = $conn->prepare("DELETE FROM otp_table WHERE user_id=? AND user_type=?");
                $stmt_del->bind_param("ss", $user_id, $user_type);
                $stmt_del->execute();

                $stmt_insert = $conn->prepare("INSERT INTO otp_table (user_id, user_type, otp, expiry_time) VALUES (?, ?, ?, ?)");
                $stmt_insert->bind_param("ssss", $user_id, $user_type, $otp, $expiry);
                $stmt_insert->execute();

                $subject = "Password Reset OTP";
                $body = "Hello,\n\nYour OTP is: $otp\nExpires in 5 minutes.\nDo not reply.";
                $result_mail = sendMail($email, $subject, $body);

                if ($result_mail === true) {
                    $_SESSION['otp_user_type'] = $user_type;
                    $_SESSION['otp_user_id'] = $user_id;
                    $_SESSION['otp_sent'] = true; 
                    $message = "OTP sent to your email.";
                    $showOtpField = true;
                } else {
                    $message = "Failed to send OTP.";
                }
            } else {
                $message = "Invalid user ID or email.";
            }
        }
    }

    // ------------------- VERIFY OTP -------------------
    if (isset($_POST['verify_otp'])) {
        $user_id   = $_SESSION['otp_user_id'] ?? '';
        $user_type = $_SESSION['otp_user_type'] ?? '';
        $entered_otp = trim($_POST['otp']);
        $currentTime = date("Y-m-d H:i:s"); 

        if (!$user_id || !$user_type) {
            $message = "Session expired. Please start over.";
        } elseif (!preg_match('/^\d{6}$/', $entered_otp)) {
            $message = "OTP must be 6 digits.";
            $showOtpField = true;
        } else {
            $stmt = $conn->prepare("SELECT * FROM otp_table WHERE user_id=? AND user_type=? AND otp=? AND expiry_time >= ?");
            $stmt->bind_param("ssss", $user_id, $user_type, $entered_otp, $currentTime);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['otp_verified'] = true;
                $stmt_del = $conn->prepare("DELETE FROM otp_table WHERE user_id=? AND user_type=?");
                $stmt_del->bind_param("ss", $user_id, $user_type);
                $stmt_del->execute();
                unset($_SESSION['otp_sent']);
                header("Location: change_password.php");
                exit();
            } else {
                $message = "Invalid or expired OTP. Try again.";
                $showOtpField = true;
            }
        }
    }
}

if (!$showOtpField && isset($_SESSION['otp_sent']) && $_SESSION['otp_sent'] === true) {
    $showOtpField = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Security Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0f766e; /* Teal Theme */
            --primary-light: #14b8a6;
            --bg-dark: #0f172a;
            --card-bg: rgba(255, 255, 255, 0.98);
        }

        * { box-sizing: border-box; transition: all 0.3s ease; }
        
        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-dark);
            background-image: radial-gradient(circle at 50% 50%, #1e293b 0%, #0f172a 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            padding: 20px;
        }

        .container {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 24px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .icon-box {
            width: 60px;
            height: 60px;
            background: #f0fdfa;
            color: var(--primary);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 20px;
        }

        h2 { 
            color: #1e293b; 
            margin-bottom: 8px; 
            font-weight: 800; 
            font-size: 1.5rem; 
            letter-spacing: -0.5px;
        }

        p.subtitle {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 30px;
        }

        .input-group {
            text-align: left;
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input, select {
            width: 100%;
            padding: 14px 16px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            font-size: 15px;
            font-weight: 500;
            color: #1e293b;
            outline: none;
            background: #f8fafc;
        }

        input:focus, select:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.1);
        }

        button {
            width: 100%;
            background: var(--primary);
            color: #fff;
            padding: 15px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        button:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(15, 118, 110, 0.3);
        }

        .message {
            background: #fff1f2;
            color: #e11d48;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid #ffe4e6;
            animation: shake 0.4s ease-in-out;
        }

        .message-success {
            background: #f0fdf4;
            color: #16a34a;
            border-color: #dcfce7;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 25px;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            text-decoration: none;
        }

        .back-link:hover { color: var(--primary); }

        .otp-input {
            letter-spacing: 8px;
            text-align: center;
            font-size: 24px;
            font-weight: 800;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="icon-box">
        <i class="fas fa-shield-alt"></i>
    </div>
    
    <h2>Account Recovery</h2>
    <p class="subtitle">Securely reset your portal password</p>

    <?php if($message): ?>
        <div class="message <?php echo ($result_mail === true || strpos($message, 'sent') !== false) ? 'message-success' : ''; ?>">
            <i class="fas <?php echo (strpos($message, 'Invalid') !== false) ? 'fa-triangle-exclamation' : 'fa-circle-info'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if(!$showOtpField): ?>
    <form method="POST">
        <div class="input-group">
            <label>I am a</label>
            <select name="user_type" required>
                <option value="">Select User Type</option>
                <option value="student">Student</option>
                <option value="teacher">Teacher</option>
                <option value="admin">Administrator</option>
            </select>
        </div>
        
        <div class="input-group">
            <label>Identification ID</label>
            <input type="text" name="user_id" placeholder="Enter your ID" required>
        </div>

        <div class="input-group">
            <label>Official Email</label>
            <input type="email" name="email" placeholder="example@college.com" required>
        </div>

        <button type="submit" name="send_otp">
            Get Verification Code <i class="fas fa-arrow-right"></i>
        </button>
        <a href="../index.html" class="back-link"><i class="fas fa-chevron-left"></i> Back to Login</a>
    </form>
    <?php else: ?>
    <form method="POST">
        <p style="font-size:13px; color:#64748b; margin-bottom:20px; line-height:1.5;">
            We've sent a 6-digit verification code to your registered email address.
        </p>
        
        <div class="input-group">
            <label>6-Digit OTP</label>
            <input type="text" name="otp" class="otp-input" placeholder="000000" maxlength="6" required autofocus autocomplete="one-time-code">
        </div>

        <button type="submit" name="verify_otp">
            Verify & Proceed <i class="fas fa-check-circle"></i>
        </button>
        
        <a href="forget_password.php" class="back-link">
            <i class="fas fa-user-pen"></i> Wrong ID? Try again
        </a>
    </form>
    <?php endif; ?>
</div>
</body>
</html>