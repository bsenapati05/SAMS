<?php
// ============================================================
// INITIAL SETTINGS
// ============================================================
set_time_limit(0);               // Remove execution time limit for large CSV uploads
ini_set('memory_limit', '1024M'); // Increase memory limit

require_once __DIR__ . '/db.php'; // Include database connection
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Enable MySQLi error reporting

session_start(); // Start session for authorization and CSRF protection


// ============================================================
// STEP 0: AUTHORIZATION + CSRF PROTECTION
// ============================================================

// if (!isset($_SESSION['admin_logged_in'])) {
//     die("Unauthorized Access");
// }

// if (!isset($_POST['csrf_token']) || 
//     $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
//     die("Invalid CSRF Token");
// }


// ============================================================
// STEP 1: VALIDATE INPUT
// ============================================================
if (!isset($_FILES['excel']))
    die("No file uploaded");

if (!isset($_POST['program']))
    die("Program type missing");

$programType = strtoupper(trim($_POST['program'])); // Standardize program type to uppercase


// ============================================================
// STEP 2: FILE VALIDATION
// ============================================================
$allowedTypes = ['text/csv', 'application/vnd.ms-excel'];

if (!in_array($_FILES['excel']['type'], $allowedTypes)) {
    die("Invalid file type. Only CSV allowed.");
}

$extension = strtolower(pathinfo($_FILES['excel']['name'], PATHINFO_EXTENSION));
if ($extension !== 'csv') {
    die("Only .csv files allowed.");
}

$file = $_FILES['excel']['tmp_name'];
$handle = fopen($file, "r");

if (!$handle)
    die("Cannot open CSV file");


// ============================================================
// STEP 3: READ HEADER ROW
// ============================================================
$headerRow = fgetcsv($handle);
if (!$headerRow)
    die("Header row missing in CSV");

$headers = array_map(function($h){
    $h = strtolower(trim($h));
    $h = str_replace([' ', '.', '-', '/'], '_', $h);
    return $h;
}, $headerRow);

// /* ===== DEBUG: SEE ACTUAL HEADERS ===== */
// echo "<pre>";
// print_r($headers);
// exit;


// ============================================================
// STEP 4: HEADER ALIAS MAPPING (Excel → DB Column)
// ============================================================
$headerAliasMap = [
    'roll number' => 'student_id', 'roll_number' => 'student_id', 'student_id' => 'student_id',
    'barcode_no' => 'barcode_no', 'barcode' => 'barcode_no',
    'student_name' => 'student_name', 'applicants_name' => 'student_name', 'applicant_name' => 'student_name',
    'father_name' => 'father_name', 'fathers_name' => 'father_name',
    'mother_name' => 'mother_name', 'mothers_name' => 'mother_name',
    'mobile' => 'mobile', 'mobile_number' => 'mobile', 'mobile_no' => 'mobile', 'phone_number' => 'mobile',
    'email' => 'email', 'email_id' => 'email', 'e_mail' => 'email', 'e_mail_id' => 'email',
    'aadhaar_number' => 'aadhaar_number', 'aadhaar' => 'aadhaar_number',
    'stream' => 'stream',
    '12th_board' => 'previous_board', 'graduation_board' => 'previous_board',
    'gender' => 'gender',
    'dob' => 'dob', 'date_of_birth' => 'dob', 'birth_date' => 'dob',
    'blood_group' => 'blood_group', 'address' => 'address',
    'catagory' => 'social_catagory', 'social_catagory' => 'social_catagory', 'social_category' => 'social_catagory', 'category' => 'social_catagory',
    'religion' => 'religion',
    'school_living_cirtificate_number' => 'clc_serial_no', 'college_living_cirtificate_number' => 'clc_serial_no', 'slc_number' =>'clc_serial_no',
    'school_living_cirtificate_date' => 'clc_date', 'graduation_year_of_passing' => 'clc_date', 'slc_date' => 'clc_date',
    'mark_secured' => 'mark_secured', 'mark_of_entrance' => 'mark_secured',
    'percentage' => 'mark_with_weightage', 'marks_percentage' => 'mark_with_weightage', 'mark_precentage' => 'mark_with_weightage', 'mark_with_weightage' => 'mark_with_weightage', 'mark_%' => 'mark_with_weightage',
    'addmission_date' => 'admission_date', 'date_of_admission' => 'admission_date', 'admission_date' => 'admission_date',
    'type_of_addmission' => 'phase_of_addmission', 'status_phase_selection' => 'phase_of_addmission', 'type_of_admission' => 'phase_of_addmission', 'admission_phase' => 'phase_of_addmission',
    'hostel_allot' => 'hostel_allot', 'hostel' => 'hostel_allot',
    'honours' => 'honourse', 'course' => 'honourse',
    'tc_date' => 'tc_date', 'ammount' => 'amount', 'amount' => 'amount',
    'pwd_status' => 'pwd_status',
    'state' => 'state',
    'exam_roll' => 'exam_roll', 'examination_roll_number' => 'exam_roll',
    'readdmission' => 'readmission', 'readmission_status' => 'readmission'
];

$columnIndex = [];
foreach ($headers as $index => $excelHeader) {
    if (isset($headerAliasMap[$excelHeader])) {
        $dbColumn = $headerAliasMap[$excelHeader];
        $columnIndex[$dbColumn] = $index;
    }
}

//test to see the mapped during upload
// echo "<pre>";
// print_r($headers);
// print_r($columnIndex);
// exit;


// ============================================================
// STEP 5: REQUIRED HEADER VALIDATION
// ============================================================
$requiredHeaders = ['student_id','mobile','honourse','admission_date'];
foreach ($requiredHeaders as $req) {
    if (!isset($columnIndex[$req])) {
        die("Required header missing: " . $req);
    }
}


// ============================================================
// STEP 6: READ ALL DATA FIRST
// ============================================================
$students = [];

while (($row = fgetcsv($handle)) !== FALSE) {
    if (count($row) < 2) continue;
    $students[] = $row;
}

//debug of header data missmatch during insert
// fclose($handle);
// echo "<pre>";
// print_r($columnIndex);
// print_r($students[0]);
// exit;


// ============================================================
// STEP 7: ERROR LOG SYSTEM
// ============================================================
if (!is_dir("logs")) {
    mkdir("logs", 0777, true);
}

function logError($roll, $message)
{
    $file = "logs/upload_errors_" . date("Y_m_d") . ".txt";

    $entry = "[" . date("Y-m-d H:i:s") . "] ";
    $entry .= "Roll: " . $roll . " | ";
    $entry .= "Error: " . $message . PHP_EOL;

    file_put_contents($file, $entry, FILE_APPEND);
}


// ============================================================
// STEP 8: LOAD DEPARTMENT STRUCTURE + STREAM MAPPING
// ============================================================
$deptMap = [];      // key = program|honourse_lower
$streamMap = [];    // key = program|honourse_lower -> stream_name

$result = $conn->query("
    SELECT 
        ds.structure_id,
        p.program_name,
        d.department_name,
        s.stream_name
    FROM department_structure ds
    JOIN programs p ON ds.program_id = p.program_id
    JOIN departments d ON ds.department_id = d.department_id
    JOIN streams s ON d.stream_id = s.stream_id
");

if (!$result) {
    die("Department structure query failed: " . $conn->error);
}

while ($r = $result->fetch_assoc()) {
    $program_clean  = strtolower(trim($r['program_name']));
    $department_clean = strtolower(trim($r['department_name']));
    $stream_clean   = trim($r['stream_name']);

    $key = $program_clean . "|" . $department_clean;
    $deptMap[$key] = [
        'structure_id' => $r['structure_id'],
        'stream'       => $stream_clean
    ];

    $streamMap[$program_clean . "|" . $department_clean] = $stream_clean;
}

// ================== FUNCTION TO GET DEPT ID & STREAM ==================
function getDeptStream($program, $honourse, $deptMap, $stream_excel) {
    $program_clean  = strtolower(trim($program));
    $honourse_clean = strtolower(trim(preg_replace('/\s+/', ' ', $honourse)));
    $key = $program_clean . "|" . $honourse_clean;

    if (isset($deptMap[$key])) {
        return [
            'department_structure_id' => $deptMap[$key]['structure_id'],
            'stream'                  => $deptMap[$key]['stream']
        ];
    } else {
        if ($program_clean == "ug") {
            return [
                'department_structure_id' => null,
                'stream'                  => $stream_excel
            ];
        } else {
            return null;
        }
    }
}


// ============================================================
// STEP 9: PREPARE INSERT QUERY
// ============================================================
// ============================================================
// STEP 9: PREPARE INSERT QUERY
// ============================================================
$sql = "
INSERT INTO student_table1
(
student_id, barcode_no, student_name, father_name, mother_name,
mobile, email, aadhaar_number, gender, dob, blood_group,
address, social_catagory, religion, previous_board,
mark_secured, mark_with_weightage,
stream, program, honourse,
admission_date, phase_of_admission, state, pwd_status,
clc_serial_no, clc_date,
hostel_allot, tc_date, amount,
year, department_structure_id,
password, is_first_login
)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
ON DUPLICATE KEY UPDATE
mobile = VALUES(mobile),
year = VALUES(year),
department_structure_id = VALUES(department_structure_id)
";

$stmt = $conn->prepare($sql);

// Added mail_stmt to match your table structure exactly
$mail_sql = "INSERT INTO mail_queue 
(recipient_email,recipient_name,subject,message,mail_type) 
VALUES (?,?,?,?,?)";

$mail_stmt = $conn->prepare($mail_sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// ============================================================
// STEP 10: PROCESS DATA (UG & PG Safe)
// ============================================================
$inserted = 0;
$updated  = 0;
$failed   = 0;
$skipped  = 0;
$currentYear = date("Y");
$conn->begin_transaction();
$batchSize = 500;
$count = 0;

foreach ($students as $row) {
    try {
        // ================== BASIC FIELDS ==================
        $student_id      = trim($row[$columnIndex['student_id']] ?? '');
        $barcode_no      = trim($row[$columnIndex['barcode_no']] ?? '');
        $student_name    = trim($row[$columnIndex['student_name']] ?? '');
        $father_name     = trim($row[$columnIndex['father_name']] ?? '');
        $mother_name     = trim($row[$columnIndex['mother_name']] ?? '');
        $mobile          = trim($row[$columnIndex['mobile']] ?? '');
        $mobile          = preg_replace('/[^0-9]/', '', $mobile);
        if (strlen($mobile) == 12 && substr($mobile, 0, 2) == "91") $mobile = substr($mobile, 2);
        $email           = trim($row[$columnIndex['email']] ?? '');
        $aadhaar_number  = trim($row[$columnIndex['aadhaar_number']] ?? '');
        $gender          = trim($row[$columnIndex['gender']] ?? '');
        $dob             = trim($row[$columnIndex['dob']] ?? '');
        $blood_group     = trim($row[$columnIndex['blood_group']] ?? '');
        $address         = trim($row[$columnIndex['address']] ?? '');
        $social_catagory = trim($row[$columnIndex['social_catagory']] ?? '');
        $religion        = isset($columnIndex['religion']) && isset($row[$columnIndex['religion']]) ? trim($row[$columnIndex['religion']]) : '';
        $mark_with_weightage = trim($row[$columnIndex['mark_with_weightage']] ?? '');
        $phase_of_admission  = trim($row[$columnIndex['phase_of_addmission']] ?? '');
        $state      = isset($columnIndex['state']) && isset($row[$columnIndex['state']])? trim($row[$columnIndex['state']]) : '';
        $pwd_status = isset($columnIndex['pwd_status']) && isset($row[$columnIndex['pwd_status']]) ? trim($row[$columnIndex['pwd_status']]) : '';
        $clc_serial_no       = isset($columnIndex['clc_serial_no']) && isset($row[$columnIndex['clc_serial_no']]) ? trim($row[$columnIndex['clc_serial_no']]) : '';
        $clc_date            = isset($columnIndex['clc_date']) && isset($row[$columnIndex['clc_date']]) ? trim($row[$columnIndex['clc_date']]) : '';
        $hostel_allot        = isset($columnIndex['hostel_allot']) && isset($row[$columnIndex['hostel_allot']]) ? trim($row[$columnIndex['hostel_allot']]) : '';
        $tc_date             = isset($columnIndex['tc_date']) && isset($row[$columnIndex['tc_date']]) ? trim($row[$columnIndex['tc_date']]) : '';
        $amount              = isset($columnIndex['amount']) && isset($row[$columnIndex['amount']]) ? trim($row[$columnIndex['amount']]) : '';
        $mark_secured        = trim($row[$columnIndex['mark_secured']] ?? '');
        $honourse            = trim($row[$columnIndex['honourse']] ?? '');
        $admission_date      = trim($row[$columnIndex['admission_date']] ?? '');
        $previous_board      = isset($columnIndex['previous_board']) && isset($row[$columnIndex['previous_board']]) ? trim($row[$columnIndex['previous_board']]) : '';
        $stream_excel        = isset($columnIndex['stream']) && isset($row[$columnIndex['stream']]) ? trim($row[$columnIndex['stream']]) : '';
        $program             = $programType;

        // ================== VALIDATION ==================
        if ($student_id == '' || $mobile == '') {
            logError($student_id, "Roll or Mobile missing");
            $skipped++; continue;
        }

        if (!preg_match('/^[A-Za-z0-9\-\/]+$/', $student_id)) {
            logError($student_id, "Invalid Roll Number");
            $failed++; continue;
        }

        if (!preg_match('/^[0-9]{10}$/', $mobile)) {
            logError($student_id, "Invalid Mobile Number");
            $failed++; continue;
        }

        if ($email != '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            logError($student_id, "Invalid Email");
            $failed++; continue;
        }

        if (!strtotime($admission_date)) {
            logError($student_id, "Invalid Admission Date");
            $failed++; continue;
        }

        // ================== DEPARTMENT MAPPING ==================
        $honourse_clean = preg_replace('/\s+/', ' ', strtolower(trim($honourse)));
        $program_clean  = strtolower(trim($program));
        $deptKey        = $program_clean . "|" . $honourse_clean;

        if (isset($deptMap[$deptKey])) {
            $department_structure_id = $deptMap[$deptKey]['structure_id'];
            $stream = $deptMap[$deptKey]['stream'];
        } else {
            if ($program_clean == "ug") {
                $stream = $stream_excel;
                $department_structure_id = null;
            } else {
                logError($student_id, "Department structure not found for PG: $honourse_clean");
                $failed++; continue;
            }
        }

        // ================== YEAR CALCULATION ==================
        $admissionYear = date("Y", strtotime($admission_date));
        $diff = $currentYear - $admissionYear;

        if ($program_clean == "ug") {
            if ($diff >= 3) $year = "3rd";
            elseif ($diff == 2) $year = "2nd";
            else $year = "1st";
        } else {
            $year = ($diff >= 2) ? "2nd" : "1st";
        }

        // ================== PASSWORD ==================
        $plain_password   = $mobile ."@". $admissionYear;
        $hashed_password  = password_hash($plain_password, PASSWORD_DEFAULT);
        $is_first_login   = 1;

        // ================== BIND & EXECUTE ==================
        $stmt->bind_param(
            "sssssssssssssssssssssssssssssssss",
            $student_id, $barcode_no, $student_name, $father_name, $mother_name,
            $mobile, $email, $aadhaar_number, $gender, $dob, $blood_group,
            $address, $social_catagory, $religion, $previous_board,
            $mark_secured, $mark_with_weightage,
            $stream, $program, $honourse,
            $admission_date, $phase_of_admission, $state, $pwd_status,
            $clc_serial_no, $clc_date,
            $hostel_allot, $tc_date, $amount,
            $year, $department_structure_id,
            $hashed_password, $is_first_login
        );

        if (!$stmt->execute()) {
            logError($student_id, "MySQL Execute Error: " . $stmt->error);
            $failed++; continue;
        }

        if ($stmt->affected_rows == 1) {

    $inserted++;

    // ================== ADD TO MAIL QUEUE ==================

    if (!empty($email)) {

        $subject = "Welcome to Student Management System";

        $message = "Hello $student_name,

Welcome to the Student Management System.

Your login credentials:

Student ID: $student_id
Password: $plain_password

Please login and change your password after first login.

Regards,
Administration";

        $type = "welcome_student";

        $mail_stmt->bind_param(
            "sssss",
            $email,
            $student_name,
            $subject,
            $message,
            $type
        );

        $mail_stmt->execute();
    }

}
elseif ($stmt->affected_rows == 2) {
    $updated++;
}
else {
    $skipped++;
}
        $count++;
        if ($count % $batchSize == 0) {
            $conn->commit();
            $conn->begin_transaction();
        }

    } catch (Exception $e) {
        logError("Unknown", $e->getMessage());
        $failed++;
    }
}

$conn->commit();
$conn->commit();
$conn->commit();

$stmt->close();
$mail_stmt->close();
$conn->close();

// ============================================================
// SUMMARY OUTPUT (Updated for AJAX)
// ============================================================
header('Content-Type: application/json');
echo json_encode([
    'status'   => 'success',
    'inserted' => $inserted,
    'updated'  => $updated,
    'skipped'  => $skipped,
    'failed'   => $failed
]);
exit;

?>