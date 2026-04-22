<?php

function isAdmin(){
    return isset($_SESSION['role']) && strtolower(trim($_SESSION['role']))=="admin";
}

function isHOD(){
    return isset($_SESSION['role']) && strtolower(trim($_SESSION['role']))=="hod";
}

function isTeacher(){
    return isset($_SESSION['role']) && strtolower(trim($_SESSION['role']))=="teacher";
}

function deny($code = 403){
    http_response_code($code);
    echo json_encode(["status"=>"Access Denied"]);
    exit();
}

function hodHasDepartmentAccess($conn,$teacher_id,$department_id){

$sql="SELECT 1 
FROM teacher_department 
WHERE teacher_id=? AND department_id=? AND ishod=1";

$stmt=$conn->prepare($sql);
$stmt->bind_param("si",$teacher_id,$department_id);
$stmt->execute();

$res=$stmt->get_result();

return $res->num_rows>0;

}
?>