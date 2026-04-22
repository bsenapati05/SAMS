<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['login']) || !in_array($_SESSION['role'], ['admin', 'hod'])) {
    header("Location: admin_login.html");
    exit();
}

$admin_sender_name = ($_SESSION['role'] === 'admin') ? ($_SESSION['admin_name'] ?? 'Administrator') : ($_SESSION['teacher_name'] ?? 'Department Head');
$feedbackDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'feedback' . DIRECTORY_SEPARATOR;

// --- DELETE LOGIC ---
if (isset($_GET['delete'])) {
    $fileId = preg_replace("/[^a-zA-Z0-9_]/", "", $_GET['delete']);
    $jsonFile = $feedbackDir . $fileId . ".json";
    if (file_exists($jsonFile)) {
        $data = json_decode(file_get_contents($jsonFile), true);
        if (!empty($data['image']) && file_exists($feedbackDir . $data['image'])) unlink($feedbackDir . $data['image']);
        unlink($jsonFile);
        header("Location: admin_view_feedback.php?success=deleted");
        exit();
    }
}

$dataFiles = glob($feedbackDir . "*.json");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SAMS | Feedback Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #020617; color: #f8fafc; font-family: 'Inter', sans-serif; }
        .glass-card { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .modal-bg { display: none; background: rgba(2, 6, 23, 0.95); backdrop-filter: blur(15px); }
        .img-full { max-height: 80vh; object-fit: contain; }
    </style>
</head>
<body class="p-8">

    <div class="max-w-7xl mx-auto">
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black tracking-tighter text-white uppercase">Feedback Terminal</h1>
                <p class="text-slate-500 text-sm italic">Image Inspection & Response Portal</p>
            </div>
            <a href="admin_dashboard.php" class="bg-slate-800 hover:bg-slate-700 text-white px-6 py-2 rounded-full text-xs font-bold transition border border-white/5">BACK TO SAMS</a>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php 
            rsort($dataFiles);
            foreach ($dataFiles as $jsonFile): 
                $fileId = basename($jsonFile, ".json");
                $data = json_decode(file_get_contents($jsonFile), true);
                $hasImage = !empty($data['image']) && file_exists($feedbackDir . $data['image']);
            ?>
            <div class="glass-card rounded-3xl overflow-hidden flex flex-col hover:border-teal-500/40 transition-all duration-300 shadow-2xl relative">
                <div class="h-48 bg-slate-900/50 relative group cursor-pointer" onclick='openImageViewer(<?php echo json_encode($data); ?>)'>
                    <?php if ($hasImage): ?>
                        <img src="uploads/feedback/<?php echo $data['image']; ?>" class="w-full h-full object-cover opacity-60 group-hover:opacity-100 transition duration-500">
                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 bg-black/40 transition">
                            <span class="bg-white text-black px-4 py-2 rounded-full text-xs font-bold uppercase tracking-widest"><i class="fas fa-expand-alt mr-2"></i> View Full Image</span>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center justify-center h-full text-slate-800 text-4xl"><i class="fas fa-image"></i></div>
                    <?php endif; ?>
                </div>

                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-white truncate"><?php echo $data['name']; ?></h3>
                        <span class="text-[10px] text-slate-500 font-mono"><?php echo date("d M Y", strtotime($data['date'])); ?></span>
                    </div>
                    <p class="text-[11px] text-teal-500 font-medium mb-3 truncate"><?php echo $data['email']; ?></p>
                    <div class="bg-black/30 p-4 rounded-2xl text-sm text-slate-300 italic mb-4 border border-white/5 line-clamp-3">
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

    <div id="imageViewer" class="fixed inset-0 z-[60] modal-bg hidden items-center justify-center p-6" onclick="closeImageViewer()">
        <div class="max-w-6xl w-full flex flex-col md:flex-row bg-slate-900 rounded-[2.5rem] overflow-hidden shadow-3xl border border-white/10" onclick="event.stopPropagation()">
            <div class="md:w-2/3 bg-black flex items-center justify-center p-4">
                <img id="v_img" src="" class="img-full rounded-xl">
                <div id="v_no_img" class="hidden text-slate-700 flex flex-col items-center">
                    <i class="fas fa-image text-8xl mb-4"></i>
                    <p class="text-sm font-bold uppercase">No Attachment Found</p>
                </div>
            </div>
            <div class="md:w-1/3 p-10 flex flex-col">
                <div class="mb-8">
                    <label class="text-[10px] text-teal-500 font-black uppercase tracking-widest">Sender Information</label>
                    <h2 id="v_name" class="text-3xl font-black text-white mt-1"></h2>
                    <p id="v_email" class="text-slate-400 text-sm"></p>
                </div>
                <div class="flex-grow">
                    <label class="text-[10px] text-slate-500 font-black uppercase tracking-widest">Message Text</label>
                    <p id="v_message" class="text-slate-200 mt-2 text-lg italic leading-relaxed"></p>
                </div>
                <button onclick="closeImageViewer()" class="mt-8 bg-slate-800 hover:bg-slate-700 text-white py-4 rounded-2xl font-bold transition">Close Viewer</button>
            </div>
        </div>
    </div>

    <div id="replyModal" class="fixed inset-0 z-50 modal-bg hidden items-center justify-center p-4">
        <div class="glass-card w-full max-w-lg rounded-[2rem] border border-white/10 shadow-3xl">
            <div class="p-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-white uppercase tracking-tighter">Compose Reply</h2>
                    <button onclick="closeReply()" class="text-slate-500 hover:text-white"><i class="fas fa-times"></i></button>
                </div>
                <form action="sendmail.php" method="POST" class="space-y-4">
                    <input type="hidden" name="recipient_email" id="m_email">
                    <input type="hidden" name="recipient_name" id="m_name">
                    <div class="bg-teal-500/5 p-4 rounded-2xl border border-teal-500/10">
                        <p id="m_display" class="text-sm text-white font-medium"></p>
                    </div>
                    <textarea name="reply_message" rows="5" class="w-full bg-slate-800/50 border border-white/5 rounded-xl p-3 text-sm text-white outline-none focus:border-teal-500" placeholder="Type response..." required></textarea>
                    <button type="submit" class="w-full bg-teal-600 hover:bg-teal-500 text-white font-black py-4 rounded-2xl transition tracking-widest text-xs uppercase shadow-xl shadow-teal-900/40">Send Email</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Image Viewer Logic
        function openImageViewer(data) {
            document.getElementById('v_name').innerText = data.name;
            document.getElementById('v_email').innerText = data.email;
            document.getElementById('v_message').innerText = "“" + data.message + "”";
            
            const vImg = document.getElementById('v_img');
            const vNoImg = document.getElementById('v_no_img');

            if(data.image) {
                vImg.src = "uploads/feedback/" + data.image;
                vImg.classList.remove('hidden');
                vNoImg.classList.add('hidden');
            } else {
                vImg.classList.add('hidden');
                vNoImg.classList.remove('hidden');
            }

            document.getElementById('imageViewer').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeImageViewer() {
            document.getElementById('imageViewer').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Reply Logic
        function openReply(data) {
            document.getElementById('m_email').value = data.email;
            document.getElementById('m_name').value = data.name;
            document.getElementById('m_display').innerText = "To: " + data.name + " (" + data.email + ")";
            document.getElementById('replyModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeReply() {
            document.getElementById('replyModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Global Esc Key Close
        document.addEventListener('keydown', (e) => {
            if(e.key === "Escape") { closeImageViewer(); closeReply(); }
        });
    </script>
</body>
</html>