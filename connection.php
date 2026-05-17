<?php
$servername = "localhost";
$username = "root";
$password = ""; // XAMPP default root password is empty
$dbname = "menubar";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Database connection failed. Start MySQL in XAMPP and verify credentials. Error: " . $conn->connect_error);
}

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>