<?php

set_time_limit(0);
ini_set('memory_limit', '1024M');

require_once "../db.php";
session_start();

header("Content-Type: application/json");

// ================= AUTH =================
if (!isset($_SESSION['teacher_id'])) {
    echo json_encode(["status"=>"error","message"=>"Unauthorized"]);
    exit;
}

$hod_id = $_SESSION['teacher_id'];
$department_id = $_POST['department_id'] ?? null;

if (!$department_id) {
    echo json_encode(["status"=>"error","message"=>"Department required"]);
    exit;
}

// ================= VALIDATE HOD =================
$check = $conn->prepare("
    SELECT 1 FROM teacher_department
    WHERE teacher_id=? AND department_id=? AND ishod=1
");
$check->bind_param("si", $hod_id, $department_id);
$check->execute();

if ($check->get_result()->num_rows === 0) {
    echo json_encode(["status"=>"error","message"=>"Invalid department access"]);
    exit;
}

// ================= FILE VALIDATION =================
if (!isset($_FILES['excel'])) {
    echo json_encode(["status"=>"error","message"=>"No file uploaded"]);
    exit;
}

$file = $_FILES['excel']['tmp_name'];
$handle = fopen($file, "r");

if (!$handle) {
    echo json_encode(["status"=>"error","message"=>"Cannot read file"]);
    exit;
}

// ================= HEADER PROCESS =================
$headerRow = fgetcsv($handle);

$headers = array_map(function($h){
    $h = strtolower(trim($h));
    $h = str_replace([' ', '.', '-', '/'], '_', $h);
    return $h;
}, $headerRow);

// Remove BOM
$headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

// ================= HEADER MAPPING =================
$map = [
    'teacher_id' => 'teacher_id',
    'teacher_code' => 'teacher_id',
    'teacher_name' => 'teacher_name',
    'name' => 'teacher_name',
    'email' => 'email',
    'email_id' => 'email',
    'mobile' => 'mobile',
    'phone' => 'mobile',
    'role' => 'role',
    'date_of_joining' => 'date_of_joining',
    'joining_date' => 'date_of_joining',
    'experience' => 'experience_years',
    'experience_years' => 'experience_years',
    'qualification' => 'qualification',
    'teaching_area' => 'teaching_area',
    'research_area' => 'research_area'
];

$columnIndex = [];
foreach ($headers as $i => $h) {
    if (isset($map[$h])) {
        $columnIndex[$map[$h]] = $i;
    }
}

// ================= REQUIRED FIELDS =================
$required = ['teacher_id','teacher_name','email'];

foreach ($required as $r) {
    if (!isset($columnIndex[$r])) {
        echo json_encode(["status"=>"error","message"=>"Missing column: $r"]);
        exit;
    }
}

// ================= PREPARE QUERIES =================
$conn->begin_transaction();

$teacher_sql = "
INSERT INTO teacher_table
(teacher_id, teacher_name, email, mobile, role, date_of_joining,
experience_years, qualification, teaching_area, research_area, password)
VALUES (?,?,?,?,?,?,?,?,?,?,?)
ON DUPLICATE KEY UPDATE
teacher_name=VALUES(teacher_name),
mobile=VALUES(mobile),
experience_years=VALUES(experience_years)
";

$dept_sql = "
INSERT INTO teacher_department (teacher_id, department_id, ishod)
VALUES (?, ?, 0)
ON DUPLICATE KEY UPDATE teacher_id=teacher_id
";

// Prepare mail queue query
$mail_sql = "INSERT INTO mail_queue (recipient_name, recipient_email, subject, message, status) VALUES (?, ?, ?, ?, 'pending')";

$stmtTeacher = $conn->prepare($teacher_sql);
$stmtDept = $conn->prepare($dept_sql);
$stmtMail = $conn->prepare($mail_sql);

// ================= PROCESS =================
$inserted=0; $updated=0; $failed=0; $skipped=0;

try {
    while (($row = fgetcsv($handle)) !== FALSE) {
        try {
            $teacher_id = trim($row[$columnIndex['teacher_id']] ?? '');
            $teacher_name = trim($row[$columnIndex['teacher_name']] ?? '');
            $email = trim($row[$columnIndex['email']] ?? '');
            $mobile = trim($row[$columnIndex['mobile']] ?? '');
            $role = trim($row[$columnIndex['role']] ?? 'teacher');
            $doj = trim($row[$columnIndex['date_of_joining']] ?? null);
            $exp = trim($row[$columnIndex['experience_years']] ?? null);
            $qualification = trim($row[$columnIndex['qualification']] ?? '');
            $teaching = trim($row[$columnIndex['teaching_area']] ?? '');
            $research = trim($row[$columnIndex['research_area']] ?? '');

            // ===== VALIDATION =====
            if ($teacher_id=='' || $email=='') {
                $skipped++; continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed++; continue;
            }

            // ===== PASSWORD =====
            $plain_password = bin2hex(random_bytes(4));
            $password = password_hash($plain_password, PASSWORD_DEFAULT);

            // ===== INSERT TEACHER =====
            $stmtTeacher->bind_param(
                "sssssssssss",
                $teacher_id, $teacher_name, $email, $mobile, $role,
                $doj, $exp, $qualification, $teaching, $research, $password
            );

            $stmtTeacher->execute();

            $is_new = ($stmtTeacher->affected_rows == 1);
            if ($is_new) $inserted++;
            elseif ($stmtTeacher->affected_rows == 2) $updated++;

            // ===== MAP DEPARTMENT =====
            $stmtDept->bind_param("si", $teacher_id, $department_id);
            $stmtDept->execute();

            // ===== QUEUE WELCOME MAIL (Only for new inserts) =====
            if ($is_new) {
                $subject = "Welcome to Student Management System - Teacher Portal";
                $message = "Hello $teacher_name,\n\nYour account has been created. Here are your credentials:\nUser ID: $teacher_id\nPassword: $plain_password\n\nPlease login and change your password.";
                $stmtMail->bind_param("ssss", $teacher_name, $email, $subject, $message);
                $stmtMail->execute();
            }

        } catch (Exception $e) {
            $failed++;
        }
    }

    $conn->commit();

    echo json_encode([
        "status"=>"success",
        "inserted"=>$inserted,
        "updated"=>$updated,
        "failed"=>$failed,
        "skipped"=>$skipped
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "status"=>"error",
        "message"=>"Upload failed: " . $e->getMessage(),
        "inserted"=>$inserted,
        "updated"=>$updated,
        "failed"=>$failed,
        "skipped"=>$skipped
    ]);
}