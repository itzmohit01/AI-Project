<?php
include 'db.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Error processing chat.', 'reply' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Not logged in.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$user_message = $data['message'] ?? '';
$user_id = $_SESSION['user_id'];

if (empty($user_message)) {
    $response['message'] = 'Message cannot be empty.';
    echo json_encode($response);
    exit;
}

$stmt_user = $conn->prepare("SELECT username, current_streak FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user_data = $result_user->fetch_assoc();
$stmt_user->close();
$conn->close();

$username = $user_data['username'] ?? 'User';
$current_streak = $user_data['current_streak'] ?? 0;

$apiKey = 'AIzaSyBfsPGitJV3p2vwYFp8i_TJD4g8G4BkzKY'; // Your API Key Here
$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey;

$system_prompt = "You are Sparky, an energetic and highly motivational AI fitness coach. Your goal is to keep users consistent with their workouts. The user's name is {$username} and their current workout streak is {$current_streak} days. Be encouraging, positive, and provide concise, actionable fitness tips or motivation related to their query or their streak. Keep responses relatively short and conversational.";

$payload = json_encode([
    'contents' => [
        [
            'role' => 'user',
            'parts' => [
                ['text' => $system_prompt . "\n\nUser's message: " . $user_message]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.7,
        'topK' => 1,
        'topP' => 1,
        'maxOutputTokens' => 200,
        'stopSequences' => []
    ],
     'safetySettings' => [ // Add safety settings
        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE']
    ]
]);


$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($payload)
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Keep true for production

$api_response_raw = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);


if ($curl_error) {
    $response['message'] = 'cURL Error: ' . $curl_error;
    error_log("cURL Error calling Gemini API: " . $curl_error);
} elseif ($http_code >= 200 && $http_code < 300) {
    $api_response = json_decode($api_response_raw, true);

     if (isset($api_response['candidates'][0]['content']['parts'][0]['text'])) {
        $response['success'] = true;
        $response['message'] = 'Reply received.';
        $response['reply'] = trim($api_response['candidates'][0]['content']['parts'][0]['text']);
    } elseif (isset($api_response['promptFeedback']['blockReason'])) {
         $response['message'] = 'Request blocked by safety settings.';
         $response['reply'] = 'Sorry, I cannot respond to that request due to safety guidelines.';
         error_log("Gemini API Safety Block: " . $api_response['promptFeedback']['blockReason']);
    }
     else {
        $response['message'] = 'Unexpected API response format.';
        error_log("Unexpected Gemini API response: " . $api_response_raw);
    }
} else {
    $response['message'] = "API Error (HTTP {$http_code})";
    error_log("Gemini API HTTP Error {$http_code}: " . $api_response_raw);
     $api_response_decoded = json_decode($api_response_raw, true);
     if (isset($api_response_decoded['error']['message'])) {
         $response['message'] .= ": " . $api_response_decoded['error']['message'];
     }
     $response['reply'] = 'Sorry, I encountered an error trying to respond. Please try again later.';
}


echo json_encode($response);
?>