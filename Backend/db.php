<?php
#this is to practice purpose . 
#connect the database testdb in which the database writting using php will conduct

   $host='localhost';
   $user='root';
   $password='';
   $database='college_db'; #for upload use testdb and login college_db
  #this will connect the datbase to the php code .the database is running inthe server
   $conn=mysqli_connect($host,$user,$password,$database);
    #check the database is connected or not
    if(!$conn){
    die('databse is not connected'.musqli_connect_error());
    }  
    // Add this to the bottom of your db.php
        if (!function_exists('queueSystemMail')) {
    function queueSystemMail($conn, $email, $subject, $body) {
        // We use prepare to prevent SQL injection errors
        $stmt = $conn->prepare("INSERT INTO mail_queue (recipient_email, subject, body, status) VALUES (?, ?, ?, 'pending')");
        if ($stmt) {
            $stmt->bind_param("sss", $email, $subject, $body);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
            }
        }
?>