<?php
session_start();
require 'db.php';

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['student_id']) || !isset($_SESSION['login']) || $_SESSION['role'] !== 'student') {
    header("Location: student_login.html");
    exit();
}

$student_id = $_SESSION['student_id'];

$sql = "SELECT *, social_catagory AS social_category, Hostel_allot AS hostel_allot, PWD_status AS pwd_status FROM student_table1 WHERE student_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student = mysqli_fetch_assoc($result);

if ($student) {
    if (!empty($student['photo'])) {
        $student['photo'] = preg_replace('#^\.\./#', '', $student['photo']);
    }
} else {
    session_destroy();
    header("Location: student_login.html");
    exit();
}

$update_fields = [
    'mobile'=>'Mobile',
    'email'=>'Email',
    'address'=>'Address',
    'religion'=>'Religion',
    'social_category'=>'Social Category',
    'state'=>'State',
    'photo'=>'Profile Photo'
];

$show_pwd = false;
$prog = strtoupper($student['program'] ?? '');
if ($prog == 'UG') {
    $show_pwd = true;
} elseif ($prog == 'PG' && empty($student['pwd_status'])) {
    $show_pwd = true;
}

$fields_to_fill = $update_fields;
if ($student['is_profile_complete'] == 0) {
    $fields_to_fill['hostel_allot'] = 'Hostel Allotment';
}
if ($show_pwd) {
    $fields_to_fill['pwd_status'] = 'PWD Status';
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])){
    $update_data = [];
    $upload_errors = [];

    foreach($fields_to_fill as $field => $label){
        if (isset($_POST[$field])) {
            $update_data[$field] = $_POST[$field];
        }
        
        if($field === 'photo' && !empty($_FILES[$field]['tmp_name'])){
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES[$field]['tmp_name']);
            finfo_close($finfo);

            if(!in_array($mime,['image/jpeg','image/jpg','image/png','image/webp'])){
                $upload_errors[$field] = "Invalid image";
                continue;
            }

            $server_dir = __DIR__ . '/uploads/profile_photo/students/';
            if (!is_dir($server_dir)) { mkdir($server_dir, 0777, true); }

            $old_files = glob($server_dir . $student_id . '.*');
            foreach ($old_files as $file) { if (file_exists($file)) unlink($file); }

            $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
            $filename = $student_id . '.' . $ext;
            $server_path = $server_dir . $filename;

            move_uploaded_file($_FILES[$field]['tmp_name'], $server_path);
            $update_data['photo'] = 'uploads/profile_photo/students/' . $filename;
        }
    }

    if(empty($upload_errors) && !empty($update_data)){
        $set_parts = []; $params = []; $types = '';
        foreach($update_data as $col => $val){
            $db_col = ($col=='social_category') ? 'social_catagory' : (($col=='hostel_allot') ? 'Hostel_allot' : (($col=='pwd_status') ? 'PWD_status' : $col));
            $set_parts[] = "$db_col=?"; $params[] = $val; $types .= 's';
        }
        $sql = "UPDATE student_table1 SET ".implode(',',$set_parts).", is_profile_complete=1 WHERE student_id=?";
        $params[] = $student_id; $types .= 's';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        echo json_encode(['status'=>'success']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --accent: #facc15;
            --bg-dark: #0f172a;
            --sidebar-bg: #1e293b;
            --card-bg: rgba(255, 255, 255, 0.03);
            --card-border: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --glass: rgba(30, 41, 59, 0.7);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--bg-dark); 
            background-image: radial-gradient(circle at 0% 0%, rgba(37, 99, 235, 0.1) 0%, transparent 25%),
                              radial-gradient(circle at 100% 100%, rgba(124, 58, 237, 0.1) 0%, transparent 25%);
            color: var(--text-main); 
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Sidebar Customization */
        .sidebar { 
            width: 280px; 
            height: 100vh; 
            background: var(--sidebar-bg); 
            position: fixed; 
            left: 0; top: 0; 
            padding: 2rem 1.5rem; 
            z-index: 1001;
            border-right: 1px solid var(--card-border);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .sidebar-brand { 
            font-size: 1.25rem; font-weight: 800; 
            letter-spacing: -0.5px;
            color: var(--text-main); 
            margin-bottom: 3rem; 
            display: flex; align-items: center; gap: 10px;
        }
        .sidebar-brand i { color: var(--primary); font-size: 1.5rem; }

        .nav-group { display: flex; flex-direction: column; gap: 8px; }
        .nav-btn { 
            width: 100%; padding: 14px 18px; border-radius: 12px; border: none; 
            background: transparent; color: var(--text-dim); text-align: left; 
            cursor: pointer; display: flex; align-items: center; gap: 14px; 
            font-weight: 600; font-size: 0.95rem; transition: 0.2s; 
        }
        .nav-btn:hover { background: rgba(255,255,255,0.05); color: var(--text-main); }
        .nav-btn.active { background: var(--primary); color: white; box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3); }

        .logout-box {
            position: absolute; bottom: 2rem; left: 1.5rem; right: 1.5rem;
            padding-top: 1.5rem; border-top: 1px solid var(--card-border);
        }
        .logout-link { text-decoration: none; color: #ef4444; font-weight: 700; display: flex; align-items: center; gap: 12px; font-size: 0.95rem; }

        /* Main Content */
        .main { margin-left: 280px; padding: 2.5rem; transition: 0.3s; width: calc(100% - 280px); }

        /* Top Hero Card */
        .hero { 
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid var(--card-border);
            border-radius: 24px; padding: 3rem; 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 2.5rem; position: relative; overflow: hidden;
        }
        .hero::after {
            content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px;
            background: var(--primary); filter: blur(120px); opacity: 0.15; z-index: 0;
        }
        .hero-text { z-index: 1; }
        .hero-text h1 { font-size: 2.2rem; font-weight: 800; margin-bottom: 8px; color: white; }
        .hero-text p { color: var(--text-dim); font-size: 1.1rem; }
        .profile-avatar { 
            width: 120px; height: 120px; border-radius: 30px; 
            border: 4px solid var(--card-border); overflow: hidden; 
            background: #334155; flex-shrink: 0; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3);
            z-index: 1;
        }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }

        /* Modern Panels */
        .panel { 
            background: var(--glass); 
            backdrop-filter: blur(12px);
            border: 1px solid var(--card-border);
            border-radius: 24px; padding: 2rem; margin-bottom: 2rem; 
            display: none; animation: slideUp 0.5s ease;
        }
        .panel.active { display: block; }

        .section-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid var(--card-border);
        }
        .section-title { font-size: 1.25rem; font-weight: 700; color: white; display: flex; align-items: center; gap: 12px; }
        .section-title i { color: var(--primary); }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: var(--card-bg); padding: 1.5rem; border-radius: 16px; border: 1px solid var(--card-border); }
        .stat-card label { display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-dim); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 1px; }
        .stat-card span { font-size: 1.1rem; font-weight: 600; color: white; }

        /* Form Styling */
        .grid-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .field-group { display: flex; flex-direction: column; gap: 8px; }
        .field-group label { font-size: 0.85rem; font-weight: 600; color: var(--text-dim); }
        .form-input { 
            background: rgba(15, 23, 42, 0.6); 
            border: 1px solid var(--card-border); 
            border-radius: 12px; padding: 14px; 
            color: white; font-family: inherit; transition: 0.3s;
        }
        .form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }

        .btn-primary { 
            background: var(--primary); color: white; padding: 14px 28px; 
            border: none; border-radius: 12px; font-weight: 700; cursor: pointer; 
            transition: 0.3s; display: inline-flex; align-items: center; gap: 10px;
        }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2); }

        /* Info Table */
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
        .data-table tr { background: var(--card-bg); }
        .data-table th { 
            text-align: left; padding: 15px 20px; color: var(--text-dim); 
            font-size: 0.9rem; font-weight: 600; border-radius: 12px 0 0 12px; 
        }
        .data-table td { padding: 15px 20px; font-weight: 500; color: white; border-radius: 0 12px 12px 0; }

        /* Mobile Adjustments */
        .mobile-header { 
            display: none; position: fixed; top: 0; left: 0; width: 100%; 
            background: var(--glass); backdrop-filter: blur(10px); 
            padding: 15px 25px; z-index: 1002; border-bottom: 1px solid var(--card-border);
            justify-content: space-between; align-items: center;
        }

        @media (max-width: 1024px) {
            .sidebar { left: -280px; }
            .sidebar.open { left: 0; box-shadow: 20px 0 50px rgba(0,0,0,0.5); }
            .main { margin-left: 0; width: 100%; padding: 1.5rem; padding-top: 6rem; }
            .mobile-header { display: flex; }
            .hero { flex-direction: column; text-align: center; gap: 20px; padding: 2rem; }
            .hero-text h1 { font-size: 1.75rem; }
        }

        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <header class="mobile-header">
        <div style="font-weight: 800; font-size: 1.1rem; letter-spacing: -0.5px;">STUDENT<span style="color:var(--primary)">HUB</span></div>
        <button style="background:none; border:none; color:white; font-size:1.5rem;" onclick="toggleSidebar()"><i class="fas fa-bars-staggered"></i></button>
    </header>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-graduation-cap"></i>
            <span>STUDENT HUB</span>
        </div>
        
        <div class="nav-group">
            <button class="nav-btn active" onclick="showSection('dashboard', this)">
                <i class="fas fa-grid-2"></i> Dashboard
            </button>
            <button class="nav-btn" onclick="showSection('fullProfile', this)">
                <i class="fas fa-user-check"></i> Detailed Profile
            </button>
        </div>

        <div class="logout-box">
            <a href="logout.php" class="logout-link">
                <i class="fas fa-arrow-right-from-bracket"></i>
                <span>Sign Out Account</span>
            </a>
        </div>
    </nav>

    <main class="main">
        <div class="hero">
            <div class="hero-text">
                <h1>Hi, <?php echo htmlspecialchars($student['student_name']); ?>!</h1>
                <p>Welcome back to your academic portal.</p>
                <div style="margin-top: 15px; display: flex; gap: 15px;">
                    <span style="background: rgba(59, 130, 246, 0.2); padding: 5px 12px; border-radius: 8px; font-size: 0.85rem; color: #93c5fd; font-weight: 600; border: 1px solid rgba(59, 130, 246, 0.3);">
                        ID: <?php echo $student['student_id']; ?>
                    </span>
                    <span style="background: rgba(250, 204, 21, 0.15); padding: 5px 12px; border-radius: 8px; font-size: 0.85rem; color: var(--accent); font-weight: 600; border: 1px solid rgba(250, 204, 21, 0.2);">
                        <?php echo $student['program']; ?>
                    </span>
                </div>
            </div>
            <div class="profile-avatar">
                <?php if(!empty($student['photo'])): ?>
                    <img src="<?php echo $student['photo'].'?v='.time(); ?>" alt="Profile">
                <?php else: ?>
                    <div style="height:100%; display:flex; align-items:center; justify-content:center; color:white; font-size:2.5rem; font-weight:800; background: var(--primary);">
                        <?php echo substr($student['student_name'],0,1); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="dashboardSection" class="panel active">
            <div class="section-header">
                <h3 class="section-title"><i class="fas fa-circle-info"></i> Academic Summary</h3>
                <button class="btn-primary" style="padding: 10px 20px; font-size: 0.85rem;" onclick="toggleUpdate()">
                    <i class="fas fa-user-pen"></i> Edit Profile
                </button>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <label>Assigned Stream</label>
                    <span><?php echo $student['stream']; ?></span>
                </div>
                <div class="stat-card">
                    <label>Honours Subject</label>
                    <span><?php echo $student['honourse']; ?></span>
                </div>
                <div class="stat-card">
                    <label>Roll Number</label>
                    <span><?php echo $student['student_id']; ?></span>
                </div>
            </div>

            <div id="updateFormPanel" style="display: none; border-top: 1px solid var(--card-border); padding-top: 2rem; margin-top: 1rem;">
                <h3 class="section-title" style="margin-bottom:1.5rem;">Update Personal Information</h3>
                <form id="updateForm">
                    <div class="grid-form">
                        <?php foreach($fields_to_fill as $f => $l): ?>
                            <div class="field-group">
                                <label><?php echo $l; ?></label>
                                <?php if($f == 'pwd_status'): ?>
                                    <select name="pwd_status" class="form-input">
                                        <option value="No" <?php echo ($student['pwd_status']=='No')?'selected':''; ?>>No</option>
                                        <option value="Yes" <?php echo ($student['pwd_status']=='Yes')?'selected':''; ?>>Yes</option>
                                    </select>
                                <?php elseif($f == 'photo'): ?>
                                    <div style="position:relative;">
                                        <input type="file" name="photo" class="form-input" style="padding: 10px;">
                                    </div>
                                <?php else: ?>
                                    <input type="text" name="<?php echo $f;?>" value="<?php echo $student[$f];?>" class="form-input" placeholder="Enter <?php echo $l; ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 2rem;">
                        <button type="submit" class="btn-primary">Confirm & Save Changes</button>
                        <button type="button" class="nav-btn" style="display:inline-block; width:auto; margin-left:10px;" onclick="toggleUpdate()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="fullProfileSection" class="panel">
            <div class="section-header">
                <h3 class="section-title"><i class="fas fa-file-invoice"></i> Official Record</h3>
            </div>
            <table class="data-table">
                <tr><th>Full Legal Name</th><td><?php echo $student['student_name']; ?></td></tr>
                <tr><th>Father's Name</th><td><?php echo $student['father_name']; ?></td></tr>
                <tr><th>Academic Program</th><td><?php echo $student['program']; ?></td></tr>
                <tr><th>Social Category</th><td><?php echo $student['social_category'] ?: 'General'; ?></td></tr>
                <tr><th>PWD Status</th><td><span style="color: var(--accent)"><?php echo $student['pwd_status'] ?: 'Not Declared'; ?></span></td></tr>
                <tr><th>Mobile Contact</th><td><?php echo $student['mobile']; ?></td></tr>
                <tr><th>Email Address</th><td><?php echo $student['email']; ?></td></tr>
                <tr><th>Residential Address</th><td><?php echo $student['address']; ?></td></tr>
            </table>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }

        function showSection(id, btn) {
            document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(id + 'Section').classList.add('active');
            btn.classList.add('active');
            if(window.innerWidth <= 1024) {
                setTimeout(toggleSidebar, 150);
            }
        }

        function toggleUpdate() {
            const panel = document.getElementById('updateFormPanel');
            const isHidden = panel.style.display === 'none';
            panel.style.display = isHidden ? 'block' : 'none';
            if(isHidden) panel.scrollIntoView({ behavior: 'smooth' });
        }

        document.getElementById('updateForm').onsubmit = function(e) {
            e.preventDefault();
            const btn = this.querySelector('.btn-primary');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            btn.disabled = true;

            fetch(window.location.href, {
                method: 'POST',
                body: new FormData(this),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if(data.status==='success') {
                    location.reload();
                }
            })
            .catch(err => {
                alert('An error occurred. Please check your connection.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        };
    </script>
</body>
</html>