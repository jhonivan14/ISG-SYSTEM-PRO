<?php
$host = "localhost";
$user = "root";
$pass = "dbpassword";
$dbname = "isg_system";

$conn = new mysqli($user, $host, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
