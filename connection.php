<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "menubar";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT ID, MENU FROM menu where ID = 1";

$query = $conn->query($sql);

$menubar = mysqli_fetch_assoc($query);
?>