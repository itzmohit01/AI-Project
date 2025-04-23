<?php
include 'db.php';
header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    echo json_encode(['authenticated' => true, 'username' => $_SESSION['username']]);
} else {
    echo json_encode(['authenticated' => false]);
}
?>