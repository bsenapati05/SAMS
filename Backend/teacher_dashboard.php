<?php
session_start();
require 'db.php';

// prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../Frontend/teacher_login.html");
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

// --- PROFILE PHOTO UPLOAD LOGIC ---
$upload_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $target_dir = "Uploads/profile_photo/teachers/";
    
    // Create directory if not present
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION));
    // Hardcoding to .jpg as requested (or you can keep the original extension)
    $target_file = $target_dir . $teacher_id . ".jpg"; 

    $check = getimagesize($_FILES["profile_photo"]["tmp_name"]);
    if($check !== false) {
        if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
            $upload_message = "Profile updated successfully!";
        } else {
            $upload_message = "Error uploading image.";
        }
    } else {
        $upload_message = "File is not an image.";
    }
}

// Fetch Departments
$stmt = $conn->prepare("
    SELECT d.department_name
    FROM teacher_department td
    INNER JOIN departments d ON td.department_id = d.department_id
    WHERE td.teacher_id = ?
");
$stmt->bind_param("s", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$departments = [];
while ($row = $result->fetch_assoc()) { $departments[] = $row['department_name']; }
$stmt->close();

// Check if profile photo exists
$photo_path = "uploads/profile_photo/teachers/" . $teacher_id . ".jpg";
$has_photo = file_exists($photo_path);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Dashboard | SAMS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --primary: #14b8a6;
    --primary-dark: #0f766e;
    --bg-dark: #0f172a;
    --card-bg: rgba(30, 41, 59, 0.7);
    --text-main: #f8fafc;
    --text-dim: #94a3b8;
}

* { box-sizing: border-box; transition: all 0.2s ease; }
body { 
    margin: 0; 
    font-family: 'Plus Jakarta Sans', sans-serif; 
    background: var(--bg-dark); 
    background-image: radial-gradient(circle at 100% 0%, rgba(20, 184, 166, 0.1) 0%, transparent 40%);
    color: var(--text-main);
    min-height: 100vh;
}

/* Sidebar-like Header */
.header { 
    background: #1e293b; 
    padding: 1.5rem 5%; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.logout-btn { 
    background: #ef4444; color: white; padding: 10px 20px; 
    text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 0.9rem;
}

.container { padding: 3rem 5%; max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; }

.card { 
    background: var(--card-bg); 
    backdrop-filter: blur(10px);
    padding: 2.5rem; border-radius: 24px; 
    border: 1px solid rgba(255,255,255,0.1);
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);
}

/* Profile Image Styles */
.profile-img-container {
    width: 120px; height: 120px;
    margin: 0 auto 1.5rem;
    border-radius: 30px;
    overflow: hidden;
    border: 3px solid var(--primary);
    background: #334155;
    display: flex; align-items: center; justify-content: center;
}
.profile-img-container img { width: 100%; height: 100%; object-fit: cover; }
.profile-placeholder { font-size: 3rem; font-weight: 800; color: var(--primary); }

.dept-tag {
    background: rgba(20, 184, 166, 0.1);
    color: var(--primary);
    padding: 6px 14px; border-radius: 8px; font-size: 0.8rem; font-weight: 700;
    display: inline-block; margin: 4px; border: 1px solid rgba(20, 184, 166, 0.2);
}

.btn-action {
    background: var(--primary); color: white; border: none; padding: 14px 20px;
    border-radius: 12px; font-weight: 700; cursor: pointer; width: 100%;
    margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 10px;
}
.btn-outline { background: transparent; border: 1px solid var(--primary); color: var(--primary); }
.btn-outline:hover { background: var(--primary); color: white; }

/* Modal Styles */
.modal {
    display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); align-items: center; justify-content: center;
}
.modal-content { 
    background: #1e293b; padding: 2rem; border-radius: 20px; width: 90%; max-width: 450px; 
    border: 1px solid rgba(255,255,255,0.1); text-align: center;
}
input[type="file"] { margin: 20px 0; color: var(--text-dim); }

@media (max-width: 900px) { .container { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<div class="header">
    <h2 style="margin:0; font-weight:800; letter-spacing:-1px;"><i class="fas fa-graduation-cap" style="color:var(--primary);"></i> SAMS TEACHER</h2>
    <a class="logout-btn" href="logout.php"><i class="fas fa-power-off"></i> Logout</a>
</div>

<div class="container">
    <div class="card" style="text-align: center;">
        <div class="profile-img-container">
            <?php if($has_photo): ?>
                <img src="<?php echo $photo_path . '?t=' . time(); ?>" alt="Profile">
            <?php else: ?>
                <div class="profile-placeholder"><?php echo strtoupper(substr($_SESSION['teacher_name'], 0, 1)); ?></div>
            <?php endif; ?>
        </div>
        
        <h3 style="margin-bottom:5px; font-weight:800;"><?php echo htmlspecialchars($_SESSION['teacher_name']); ?></h3>
        <p style="color:var(--text-dim); margin-top:0;">ID: <?php echo htmlspecialchars($teacher_id); ?></p>
        
        <div style="margin: 1.5rem 0;">
            <button class="btn-action" onclick="location.href='./Curd_Operation/manage_student.php'">
                <i class="fas fa-users-gear"></i> Manage Students
            </button>
            <button class="btn-action btn-outline" onclick="document.getElementById('updateModal').style.display='flex'">
                <i class="fas fa-user-pen"></i> Update Info
            </button>
        </div>

        <?php if($upload_message): ?>
            <p style="font-size:0.8rem; color:var(--primary); font-weight:700;"><?php echo $upload_message; ?></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 style="color:var(--primary); margin-top:0;"><i class="fas fa-circle-info"></i> Full Professional Details</h3>
        <hr style="border:0; border-top:1px solid rgba(255,255,255,0.1); margin:1.5rem 0;">
        
        <div style="display:grid; gap:1.5rem;">
            <div>
                <label style="color:var(--text-dim); font-size:0.75rem; text-transform:uppercase; font-weight:700;">Registered Email</label>
                <p style="margin:5px 0; font-weight:600; font-size:1.1rem;"><?php echo htmlspecialchars($_SESSION['email'] ?? 'Not Available'); ?></p>
            </div>
            
            <div>
                <label style="color:var(--text-dim); font-size:0.75rem; text-transform:uppercase; font-weight:700;">Associated Departments</label>
                <div style="margin-top:10px;">
                    <?php if(!empty($departments)): ?>
                        <?php foreach ($departments as $dept): ?>
                            <span class="dept-tag"><?php echo htmlspecialchars($dept); ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:var(--text-dim);">No departments assigned.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div style="background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: 15px; border-left: 4px solid var(--primary);">
                <p style="margin:0; font-size:0.85rem; color:var(--text-dim); line-height:1.6;">
                    <i class="fas fa-lightbulb" style="color:var(--primary);"></i> 
                    Quick Tip: Use the "Update Info" button to change your profile picture. Only .jpg images are supported for standardized ID generation.
                </p>
            </div>
        </div>
    </div>
</div>

<div id="updateModal" class="modal">
    <div class="modal-content">
        <h3 style="margin-top:0;">Update Profile Photo</h3>
        <p style="color:var(--text-dim); font-size:0.9rem;">Select a new photo to refresh your dashboard identity.</p>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="profile_photo" accept="image/jpeg" required>
            <button type="submit" class="btn-action">Upload & Save Photo</button>
            <button type="button" class="btn-action btn-outline" style="margin-top:10px;" onclick="document.getElementById('updateModal').style.display='none'">Cancel</button>
        </form>
    </div>
</div>

<script>
// Close modal if clicking outside of content
window.onclick = function(event) {
    if (event.target == document.getElementById('updateModal')) {
        document.getElementById('updateModal').style.display = "none";
    }
}

window.addEventListener('pageshow', function(event) {
    if (event.persisted) { window.location.reload(); }
});
</script>

</body>
</html>