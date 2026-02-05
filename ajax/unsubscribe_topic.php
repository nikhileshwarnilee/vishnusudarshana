<?php
/**
 * Unsubscribe from FCM Topic
 * Removes a device token from a specific topic
 */

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['token']) || empty($data['topic'])) {
        throw new Exception('Token and topic are required');
    }

    $token = sanitize($data['token']);
    $topic = sanitize($data['topic']);

    // Validate topic name
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $topic)) {
        throw new Exception('Invalid topic name');
    }

    // Unsubscribe from topic
    $unsubscribeSQL = "DELETE FROM fcm_topic_subscriptions WHERE token = ? AND topic = ?";
    $unsubscribeStmt = mysqli_prepare($connection, $unsubscribeSQL);
    mysqli_stmt_bind_param($unsubscribeStmt, 'ss', $token, $topic);
    
    if (mysqli_stmt_execute($unsubscribeStmt)) {
        $affectedRows = mysqli_stmt_affected_rows($unsubscribeStmt);
        
        if ($affectedRows > 0) {
            error_log("Device unsubscribed from topic: $topic");
            echo json_encode([
                'success' => true,
                'message' => "Successfully unsubscribed from topic: $topic"
            ]);
        } else {
            // Not subscribed to this topic, but return success
            echo json_encode([
                'success' => true,
                'message' => "Not subscribed to topic: $topic"
            ]);
        }
    } else {
        throw new Exception('Failed to unsubscribe from topic: ' . mysqli_error($connection));
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
