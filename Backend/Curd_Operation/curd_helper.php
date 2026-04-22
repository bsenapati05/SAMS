<?php

function insertRecord($conn,$table,$data){

$columns = implode(",",array_keys($data));
$placeholders = implode(",",array_fill(0,count($data),'?'));

$sql="INSERT INTO $table ($columns) VALUES ($placeholders)";
$stmt=$conn->prepare($sql);

$types=str_repeat("s",count($data));
$stmt->bind_param($types,...array_values($data));

return $stmt->execute();
}

function updateRecord($conn,$table,$data,$whereColumn,$whereValue){

$set=[];

foreach($data as $col=>$val){
$set[]="$col=?";
}

$setClause=implode(",",$set);

$sql="UPDATE $table SET $setClause WHERE $whereColumn=?";
$stmt=$conn->prepare($sql);

$types=str_repeat("s",count($data))."s";

$params=array_merge(array_values($data),[$whereValue]);

$stmt->bind_param($types,...$params);

return $stmt->execute();
}

function deleteRecord($conn,$table,$column,$value){

$sql="DELETE FROM $table WHERE $column=?";
$stmt=$conn->prepare($sql);

$stmt->bind_param("s",$value);

return $stmt->execute();
}
?>