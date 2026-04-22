<?php

ini_set('display_errors', '0');
error_reporting(0);

session_start();
require_once "../db.php";
require_once "access_control.php";

header("Content-Type: application/json");

$type = $_GET['type'] ?? '';
// temporary fallback for development/testing: allow teacher_id by query param when session not set
if (empty($_SESSION['role']) && !empty($_GET['teacher_id'])) {
    $_SESSION['teacher_id'] = $_GET['teacher_id'];
    $_SESSION['role']       = 'teacher';
}
$data = [];

if($type=="student"){

// role-based student fetch with exact table name and permissions
if(isAdmin() || isHOD() || isTeacher()){
    $teacher_id = $_SESSION['teacher_id'] ?? null;
    $sql = "SELECT s.*, d.department_name\nFROM student_table1 s\nJOIN department_structure ds ON s.department_structure_id = ds.structure_id\nJOIN departments d ON ds.department_id = d.department_id\n";
    $params = [];
    $types = "";

    if(isHOD() || isTeacher()){
        if (!$teacher_id) deny(401);
        $sql .= "JOIN teacher_department td ON ds.department_id = td.department_id\n";
        $sql .= "WHERE td.teacher_id = ?";
        $params[] = $teacher_id;
        $types .= "s";
        if(isHOD()){
            $sql .= " AND td.ishod = 1";
        }
    }

    $stmt = $conn->prepare($sql);
    if(!$stmt){
        http_response_code(500);
        echo json_encode(["status"=>"DB error", "error"=>$conn->error]);
        exit();
    }

    if(!empty($params)){
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while($row=$result->fetch_assoc()){
        $photo = trim($row['photo'] ?? '');
        if ($photo !== '') {
            if (preg_match('#^(https?:)?//#i', $photo)) {
                $row['photo'] = $photo;
            } elseif (str_starts_with($photo, '/')) {
                $row['photo'] = $photo;
            } elseif (strpos($photo, 'uploads/profile_photo/students/') === 0) {
                $row['photo'] = '/SMS/Backend/' . ltrim($photo, '/');
            } elseif (strpos($photo, 'uploads/profile_photo/') === 0) {
                $row['photo'] = '/SMS/Backend/' . ltrim($photo, '/');
            } elseif (strpos($photo, 'students/') === 0) {
                $row['photo'] = '/SMS/Backend/uploads/profile_photo/' . ltrim($photo, '/');
            } else {
                $row['photo'] = '/SMS/Backend/uploads/profile_photo/students/' . ltrim($photo, '/');
            }
        } else {
            $row['photo'] = '/SMS/Backend/uploads/profile_photo/default.png';
        }
        $data[] = $row;
    }

    echo json_encode($data);
    exit();
}

// nobody authorized
deny();

}



if($type=="teacher"){

if(isAdmin()){

$sql="SELECT t.*, d.department_name
FROM teachers t
LEFT JOIN teacher_department td
ON t.teacher_id = td.teacher_id
LEFT JOIN departments d
ON td.department_id = d.department_id";

}

elseif(isHOD()){

$teacher_id=$_SESSION['teacher_id'];

$sql="SELECT t.*, d.department_name
FROM teachers t

JOIN teacher_department td
ON t.teacher_id = td.teacher_id

JOIN departments d
ON td.department_id = d.department_id

WHERE td.department_id IN
(
SELECT department_id
FROM teacher_department
WHERE teacher_id=? AND ishod=1
)";

$stmt=$conn->prepare($sql);
$stmt->bind_param("s",$teacher_id);
$stmt->execute();
$result=$stmt->get_result();

while($row=$result->fetch_assoc()){
$data[]=$row;
}

echo json_encode($data);
exit();

}

else{
deny();
}

$result=$conn->query($sql);

while($row=$result->fetch_assoc()){
$data[]=$row;
}

}



if($type=="admin"){

if(!isAdmin()) deny();

$sql="SELECT * FROM admin_table";

$result=$conn->query($sql);

while($row=$result->fetch_assoc()){
$data[]=$row;
}

}

echo json_encode($data);

?>