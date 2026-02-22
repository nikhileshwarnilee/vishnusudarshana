<?php
/**
 * Subscribe to FCM Topic
 * Subscribes a device token to a specific topic for targeted notifications
 */

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['token']) || empty($data['topic'])) {
        throw new Exception('Token and topic are required');
    }

    $token = sanitize($data['token']);
    $topic = sanitize($data['topic']);

    // Validate topic name (alphanumeric, hyphen, underscore only)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $topic)) {
        throw new Exception('Invalid topic name');
    }


    // Check if token exists in fcm_tokens table
    $checkTokenSQL = "SELECT id FROM fcm_tokens WHERE token = ?";
    $checkTokenStmt = mysqli_prepare($connection, $checkTokenSQL);
    mysqli_stmt_bind_param($checkTokenStmt, 's', $token);
    mysqli_stmt_execute($checkTokenStmt);
    $tokenResult = mysqli_stmt_get_result($checkTokenStmt);
    
    if (mysqli_num_rows($tokenResult) === 0) {
        throw new Exception('Token not found. Please initialize FCM first.');
    }

    $tokenRow = mysqli_fetch_assoc($tokenResult);
    $token_id = $tokenRow['id'];

    // Subscribe to topic
    $subscribeSQL = "INSERT IGNORE INTO fcm_topic_subscriptions (token_id, token, topic) 
                     VALUES (?, ?, ?)";
    $subscribeStmt = mysqli_prepare($connection, $subscribeSQL);
    mysqli_stmt_bind_param($subscribeStmt, 'iss', $token_id, $token, $topic);
    
    if (mysqli_stmt_execute($subscribeStmt)) {
        // Log subscription
        error_log("Device subscribed to topic: $topic");
        
        echo json_encode([
            'success' => true,
            'message' => "Successfully subscribed to topic: $topic"
        ]);
    } else {
        throw new Exception('Failed to subscribe to topic: ' . mysqli_error($connection));
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}
?>
