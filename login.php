<?php
include 'db.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Invalid login attempt.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? null;
    $password = $data['password'] ?? null;

    if (empty($username) || empty($password)) {
        $response['message'] = 'Please enter username and password.';
        echo json_encode($response);
        exit;
    }

    $stmt = $conn->prepare("SELECT user_id, username, password_hash FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $response['success'] = true;
            $response['message'] = 'Login successful!';
        } else {
            $response['message'] = 'Incorrect password.';
        }
    } else {
        $response['message'] = 'Username not found.';
    }
    $stmt->close();
} else {
     $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>