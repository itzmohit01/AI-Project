<?php
$db_host = 'localhost';
$db_user = 'root'; // Replace with your database username
$db_pass = '';     // Replace with your database password
$db_name = 'Fitness';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();
?>