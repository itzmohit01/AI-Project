<?php
include 'db.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Not logged in.', 'data' => null];

if (!isset($_SESSION['user_id'])) {
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$stmt = $conn->prepare("SELECT username, current_streak, last_workout_date FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

if ($user_data) {
     if ($user_data['last_workout_date'] !== null && $user_data['last_workout_date'] < $yesterday) {
         $user_data['current_streak'] = 0;
         $stmt_update = $conn->prepare("UPDATE users SET current_streak = 0 WHERE user_id = ?");
         $stmt_update->bind_param("i", $user_id);
         $stmt_update->execute();
         $stmt_update->close();
    }

    $response['success'] = true;
    $response['message'] = 'Data fetched successfully.';
    $response['data'] = [
        'username' => $user_data['username'],
        'current_streak' => $user_data['current_streak'] ?? 0
    ];
} else {
    $response['message'] = 'User data not found.';
     unset($_SESSION['user_id']);
     unset($_SESSION['username']);
}


$conn->close();
echo json_encode($response);
?>