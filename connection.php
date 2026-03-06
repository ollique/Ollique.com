<?php
$host = "localhost:3306";
$user = "root"; 
$pass = "root"; 
$db   = "ollique"; // change to your DB name

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

?>