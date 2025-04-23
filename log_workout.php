<?php
include 'db.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Not logged in.', 'new_streak' => 0];

if (!isset($_SESSION['user_id'])) {
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$conn->begin_transaction();

try {
    $stmt_check = $conn->prepare("SELECT workout_id FROM workouts WHERE user_id = ? AND workout_date = ?");
    $stmt_check->bind_param("is", $user_id, $today);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
         $response['message'] = 'Workout already logged for today.';
         $stmt_user = $conn->prepare("SELECT current_streak FROM users WHERE user_id = ?");
         $stmt_user->bind_param("i", $user_id);
         $stmt_user->execute();
         $result_user = $stmt_user->get_result();
         $user_data = $result_user->fetch_assoc();
         $response['new_streak'] = $user_data['current_streak'] ?? 0;
         $stmt_user->close();
         $stmt_check->close();
         $conn->rollback();
         echo json_encode($response);
         exit;
    }
    $stmt_check->close();

    $stmt_insert_log = $conn->prepare("INSERT INTO workouts (user_id, workout_date) VALUES (?, ?)");
    $stmt_insert_log->bind_param("is", $user_id, $today);
    $stmt_insert_log->execute();
    $stmt_insert_log->close();

    $stmt_user = $conn->prepare("SELECT current_streak, last_workout_date FROM users WHERE user_id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $user_data = $result_user->fetch_assoc();
    $stmt_user->close();

    $current_streak = $user_data['current_streak'] ?? 0;
    $last_workout_date = $user_data['last_workout_date'];

    if ($last_workout_date == $yesterday) {
        $new_streak = $current_streak + 1;
    } elseif ($last_workout_date == $today) {
         $new_streak = $current_streak; // Should not happen due to check above, but safe
    }
    else {
        $new_streak = 1; // Reset streak if missed a day or first workout
    }

    $stmt_update_streak = $conn->prepare("UPDATE users SET current_streak = ?, last_workout_date = ? WHERE user_id = ?");
    $stmt_update_streak->bind_param("isi", $new_streak, $today, $user_id);
    $stmt_update_streak->execute();

    if ($stmt_update_streak->affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Workout logged successfully!';
        $response['new_streak'] = $new_streak;
        $conn->commit();
    } else {
        throw new Exception("Failed to update streak.");
    }
     $stmt_update_streak->close();

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Error logging workout: ' . $e->getMessage();
    error_log("Workout Log Error for user $user_id: " . $e->getMessage());
}

$conn->close();
echo json_encode($response);
?>