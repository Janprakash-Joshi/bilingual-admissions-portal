<?php 
$host = 'localhost'; 
$username = 'root'; 
$password = ''; 
$db = 'college-portal'; 

$conn = new mysqli($host, $username, $password, $db);

if ($conn->connect_error) {     
    die("Connection failed: " . $conn->connect_error); 
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();  // Start session only if it's not already started
}
?>
