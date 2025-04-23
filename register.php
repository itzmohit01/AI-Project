<?php
include 'db.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? null;
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    if (empty($username) || empty($email) || empty($password)) {
        $response['message'] = 'Please fill in all fields.';
        echo json_encode($response);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format.';
        echo json_encode($response);
        exit;
    }

    $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
    $stmt_check->bind_param("ss", $username, $email);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $response['message'] = 'Username or email already exists.';
    } else {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt_insert = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("sss", $username, $email, $password_hash);

        if ($stmt_insert->execute()) {
            $response['success'] = true;
            $response['message'] = 'Registration successful! Please login.';
        } else {
            $response['message'] = 'Registration failed. Please try again.';
        }
        $stmt_insert->close();
    }
    $stmt_check->close();
} else {
     $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>