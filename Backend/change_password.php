<?php
require_once 'db.php';
session_start();

// ---------------------- Determine user flow ----------------------
// We check for OTP flow first, then for individual role sessions for first-time login
if (isset($_SESSION['otp_verified']) && $_SESSION['otp_verified'] === true) {
    // 1. OTP reset flow (for student, teacher, admin)
    $user_type = $_SESSION['otp_user_type'] ?? '';
    $user_id   = $_SESSION['otp_user_id'] ?? '';
    $redirect_login = true; 
} elseif (isset($_SESSION['student_id'])) {
    // 2. First-time login: Student
    $user_type = 'student';
    $user_id   = $_SESSION['student_id'];
    $redirect_login = false;
} elseif (isset($_SESSION['teacher_id'])) {
    // 3. First-time login: Teacher
    $user_type = 'teacher';
    $user_id   = $_SESSION['teacher_id'];
    $redirect_login = false;
} elseif (isset($_SESSION['admin_id'])) {
    // 4. First-time login: Admin
    $user_type = 'admin';
    $user_id   = $_SESSION['admin_id'];
    $redirect_login = false;
} else {
    // No valid session - Redirect to a generic login or main page
    header("Location: ../Frontend/index.html");
    exit();
}

// Extra safety check
if (!$user_id || !$user_type) {
    header("Location: ../Frontend/index.html");
    exit();
}

// ---------------------- Handle password update ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password     = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update DB based on role - Now updating 'isfirstlogin' for everyone
        if ($user_type === 'student') {
            // Note: I kept your table name 'student_table1' and column 'is_first_login' as per your original code
            $stmt = $conn->prepare("UPDATE student_table1 SET password=?, is_first_login=0 WHERE student_id=?");
        } elseif ($user_type === 'teacher') {
            $stmt = $conn->prepare("UPDATE teachers SET password=?, isfirstlogin=0 WHERE teacher_id=?");
        } elseif ($user_type === 'admin') {
            $stmt = $conn->prepare("UPDATE admin SET password=?, isfirstlogin=0 WHERE admin_id=?");
        } else {
            $error = "Invalid user type.";
        }

        if (!isset($error)) {
            $stmt->bind_param("ss", $hashed_password, $user_id);

            if ($stmt->execute()) {
                // Clear all security sessions to force a fresh login
                unset($_SESSION['otp_verified'], $_SESSION['otp_user_id'], $_SESSION['otp_user_type']);
                // Don't unset student_id/teacher_id/admin_id yet if you want them to stay logged in
                
                if ($redirect_login) {
                    // Redirect to login page after OTP reset
                    header("Location: ../Frontend/index.html?status=success");
                } else {
                    // Redirect to their specific dashboard after first-time login reset
                    if ($user_type === 'student') header("Location: student_dashboard.php?status=success");
                    elseif ($user_type === 'teacher') header("Location: teacher_dashboard.php?status=success");
                    elseif ($user_type === 'admin') header("Location: admin_dashboard.php?status=success");
                }
                exit();
            } else {
                $error = "Database error. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #6366f1; --danger: #ef4444; }
        body { 
            margin:0; font-family:'Inter',sans-serif; 
            display:flex; justify-content:center; align-items:center; 
            height:100vh; background:radial-gradient(circle at top left,#4f46e5,#7c3aed); 
            color:#1e293b; 
        }
        .container { 
            background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); 
            padding: 40px; width: 100%; max-width:400px; 
            border-radius:20px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); 
        }
        h2 { text-align:center; font-weight:600; margin-bottom:8px; }
        .note { font-size:13px; color:#64748b; text-align:center; margin-bottom:24px; }
        .input-group { margin-bottom:18px; position:relative; }
        label { display:block; margin-bottom:8px; font-size:14px; font-weight:600; }
        input { width:100%; padding:12px 16px; border-radius:10px; border:2px solid #e2e8f0; font-size:15px; outline:none; }
        input:focus { border-color: var(--primary); }
        .eye-icon { position:absolute; right:14px; top:40px; cursor:pointer; font-size:18px; opacity:0.6; }
        button { width:100%; padding:14px; background:var(--primary); color:white; border:none; border-radius:10px; cursor:pointer; font-size:16px; font-weight:600; margin-top:10px; }
        button:hover { background:#4f46e5; }
        button:disabled { background:#cbd5e1; cursor:not-allowed; }
        .strength-meter { height:4px; width:100%; background:#e2e8f0; margin-top:8px; border-radius:2px; overflow:hidden; }
        .strength-bar { height:100%; width:0; transition:width 0.4s ease, background-color 0.4s ease; }
        .error-text { color: var(--danger); font-size:12px; margin-top:4px; display:none; }
    </style>
</head>
<body>
<div class="container">
    <h2>Set New Password</h2>
    <p class="note">Welcome! Please update your temporary password to secure your account.</p>

    <?php if(isset($error)): ?>
    <p style="color:red; text-align:center; font-size:14px;"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="post" id="passwordForm">
        <div class="input-group">
            <label>New Password</label>
            <input type="password" name="new_password" id="new_password" placeholder="••••••••" required>
            <span class="eye-icon" onclick="togglePassword('new_password', this)">👁️</span>
            <div class="strength-meter"><div id="strengthBar" class="strength-bar"></div></div>
            <small id="strengthText" style="font-size:11px; color:#64748b;"></small>
        </div>

        <div class="input-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" placeholder="••••••••" required disabled>
            <div id="matchError" class="error-text">Passwords do not match</div>
        </div>

        <button type="submit" id="submitBtn" disabled>Update & Continue</button>
    </form>
</div>

<script>
    const newPass = document.getElementById("new_password");
    const confPass = document.getElementById("confirm_password");
    const strengthBar = document.getElementById("strengthBar");
    const strengthText = document.getElementById("strengthText");
    const submitBtn = document.getElementById("submitBtn");
    const matchError = document.getElementById("matchError");

    function togglePassword(id, el) {
        const field = document.getElementById(id);
        field.type = field.type === "password" ? "text" : "password";
        el.textContent = field.type === "password" ? "👁️" : "🙈";
    }

    newPass.addEventListener("input", () => {
        const val = newPass.value;
        let score = 0;
        if(val.length>=8) score++;
        if(/[A-Z]/.test(val)) score++;
        if(/[0-9]/.test(val)) score++;
        if(/[^A-Za-z0-9]/.test(val)) score++;

        const colors=['#ef4444','#f59e0b','#10b981','#059669'];
        const widths=['25%','50%','75%','100%'];
        const texts=['Very Weak','Weak','Good','Strong'];

        if(val.length>0){
            strengthBar.style.width = widths[score-1]||'10%';
            strengthBar.style.backgroundColor = colors[score-1]||colors[0];
            strengthText.textContent = texts[score-1]||texts[0];
            confPass.disabled=false;
        } else {
            strengthBar.style.width='0';
            confPass.disabled=true;
        }
        validate();
    });

    confPass.addEventListener("input", validate);

    function validate(){
        const isMatch = newPass.value===confPass.value && newPass.value!==""; 
        const isStrongEnough = newPass.value.length>=8;
        if(confPass.value.length>0){
            matchError.style.display=isMatch?"none":"block";
            confPass.style.borderColor=isMatch?"#10b981":"#ef4444";
        }
        submitBtn.disabled=!(isMatch && isStrongEnough);
    }
</script>
</body>
</html>