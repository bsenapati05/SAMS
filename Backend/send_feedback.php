<?php
/**
 * CRITICAL: We disable all error reporting to the screen. 
 * If a database error occurs, we don't want PHP to echo it as HTML.
 */
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once 'db.php'; // Ensure db.php does NOT contain any 'echo' statements

$response = ["status" => "error", "message" => "An internal server error occurred."];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input
    $name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name'])) : '';
    $email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
    $message = isset($_POST['message']) ? htmlspecialchars(trim($_POST['message'])) : '';
    
    if (empty($name) || empty($email) || empty($message)) {
        $response = ["status" => "error", "message" => "All fields are required."];
        echo json_encode($response);
        exit();
    }

    $date = date("Y-m-d H:i:s");
    $fileId = time() . "_" . rand(1000, 9999);
    $imageName = "";

    // Directory path
    $feedbackDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'feedback' . DIRECTORY_SEPARATOR;

    // Ensure directory exists
    if (!is_dir($feedbackDir)) {
        mkdir($feedbackDir, 0777, true);
    }

    // Handle Image Upload
    if (isset($_FILES['feedback_image']) && $_FILES['feedback_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['feedback_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $imageName = "IMG_" . $fileId . "." . $ext;
            move_uploaded_file($_FILES['feedback_image']['tmp_name'], $feedbackDir . $imageName);
        }
    }

    // Structure the data for your Dashboard (Admin View)
    $feedbackData = [
        "name" => $name,
        "email" => $email,
        "message" => $message,
        "image" => $imageName,
        "date" => $date
    ];

    // Save JSON file
    $jsonFilePath = $feedbackDir . $fileId . ".json";
    
    if (file_put_contents($jsonFilePath, json_encode($feedbackData))) {
        $response = ["status" => "success", "message" => "Feedback submitted successfully!"];
    } else {
        $response = ["status" => "error", "message" => "Permission denied: Could not save feedback file."];
    }
}

// Ensure this is the ONLY thing sent to the browser
echo json_encode($response);
exit();

$dataFiles = glob($feedbackDir . "*.json");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SAMS | Feedback Intelligence</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #020617; color: #f8fafc; font-family: 'Inter', sans-serif; }
        .glass-card { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .reply-modal { display: none; background: rgba(2, 6, 23, 0.85); backdrop-filter: blur(10px); }
    </style>
</head>
<body class="p-8">

    <div class="max-w-7xl mx-auto">
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black tracking-tighter text-white uppercase">Feedback Terminal</h1>
                <p class="text-slate-500 text-sm italic">Direct Communication Portal • Dhenkanal Autonomous College</p>
            </div>
            <a href="admin_dashboard.php" class="bg-slate-800 hover:bg-slate-700 text-white px-6 py-2 rounded-full text-xs font-bold transition border border-white/5">BACK TO SAMS</a>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php 
            rsort($dataFiles);
            foreach ($dataFiles as $jsonFile): 
                $fileId = basename($jsonFile, ".json");
                $data = json_decode(file_get_contents($jsonFile), true);
            ?>
            <div class="glass-card rounded-3xl overflow-hidden flex flex-col hover:border-teal-500/30 transition group shadow-2xl">
                <div class="p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div class="h-10 w-10 bg-teal-500/10 text-teal-400 rounded-2xl flex items-center justify-center font-bold">
                            <?php echo strtoupper(substr($data['name'], 0, 1)); ?>
                        </div>
                        <span class="text-[10px] bg-slate-800 px-2 py-1 rounded text-slate-400 font-mono"><?php echo date("d M Y", strtotime($data['date'])); ?></span>
                    </div>
                    <h3 class="font-bold text-white"><?php echo $data['name']; ?></h3>
                    <p class="text-xs text-slate-500 mb-4 truncate"><?php echo $data['email']; ?></p>
                    <div class="bg-black/20 p-4 rounded-2xl text-sm text-slate-300 italic mb-4 border border-white/5">
                        "<?php echo $data['message']; ?>"
                    </div>
                </div>
                
                <div class="p-4 mt-auto flex gap-2 border-t border-white/5 bg-slate-900/20">
                    <button onclick='openReply(<?php echo json_encode($data); ?>)' class="flex-grow bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold py-3 rounded-xl transition shadow-lg shadow-teal-900/20">REPLY</button>
                    <a href="?delete=<?php echo $fileId; ?>" onclick="return confirm('Delete all data?')" class="w-12 bg-rose-500/10 hover:bg-rose-500 text-rose-500 hover:text-white flex items-center justify-center rounded-xl transition"><i class="fas fa-trash-alt"></i></a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="replyModal" class="fixed inset-0 z-50 reply-modal items-center justify-center p-4">
        <div class="glass-card w-full max-w-lg rounded-[2rem] border border-white/10 shadow-3xl">
            <div class="p-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-white">Direct Reply</h2>
                    <button onclick="closeReply()" class="text-slate-500 hover:text-white"><i class="fas fa-times"></i></button>
                </div>

                <form action="sendmail.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="recipient_email" id="m_email">
                    <input type="hidden" name="recipient_name" id="m_name">

                    <div class="bg-teal-500/5 p-4 rounded-2xl border border-teal-500/10">
                        <label class="text-[10px] font-black text-teal-500 uppercase">To Recipient</label>
                        <p id="m_display" class="text-sm text-white font-medium"></p>
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-slate-500 uppercase ml-1">Admin Identity</label>
                        <input type="text" name="admin_name" value="<?php echo $admin_sender_name; ?>" class="w-full bg-slate-800/50 border border-white/5 rounded-xl p-3 text-sm text-white outline-none focus:border-teal-500" readonly>
                    </div>

                    <div>
                        <label class="text-[10px] font-black text-slate-500 uppercase ml-1">Message Content</label>
                        <textarea name="reply_message" rows="4" class="w-full bg-slate-800/50 border border-white/5 rounded-xl p-3 text-sm text-white outline-none focus:border-teal-500" placeholder="Type your official response here..." required></textarea>
                    </div>

                    <button type="submit" class="w-full bg-teal-600 hover:bg-teal-500 text-white font-black py-4 rounded-2xl transition tracking-widest text-xs shadow-xl shadow-teal-900/40 uppercase">Send Instant Mail</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openReply(data) {
            document.getElementById('m_email').value = data.email;
            document.getElementById('m_name').value = data.name;
            document.getElementById('m_display').innerText = data.name + " (" + data.email + ")";
            document.getElementById('replyModal').style.display = 'flex';
        }
        function closeReply() { document.getElementById('replyModal').style.display = 'none'; }
    </script>
</body>
</html>