<?php
/**
 * Send FCM Notifications
 * Endpoint for sending notifications to devices, topics, or broadcast
 * Can be called from admin panel or backend processes
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/firebase_messaging_helper.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

try {
    // For HTTP POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = $_POST;
        }
    } else {
        $data = $_REQUEST;
    }

    // Validate required fields
    $requiredFields = ['title', 'body'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            throw new Exception("$field is required");
        }
    }

    // Validate recipient type (device, topic, or broadcast)
    $recipientType = $data['recipient_type'] ?? 'broadcast'; // default to broadcast
    $validTypes = ['device', 'topic', 'broadcast'];
    
    if (!in_array($recipientType, $validTypes)) {
        throw new Exception("Invalid recipient_type. Must be: " . implode(', ', $validTypes));
    }

    // Validate recipient based on type
    if ($recipientType === 'device' && empty($data['device_token'])) {
        throw new Exception('device_token is required for device type notifications');
    }

    if ($recipientType === 'topic' && empty($data['topic'])) {
        throw new Exception('topic is required for topic type notifications');
    }

    // Initialize Firebase Messaging
    $serviceAccountPath = __DIR__ . '/../../firebase-service-account.json';
    if (!file_exists($serviceAccountPath)) {
        throw new Exception('Firebase service account file is missing at project root: firebase-service-account.json');
    }

    if (!$connection) {
        throw new Exception('Database connection is not available');
    }

    $fcm = new FirebaseMessaging(
        'vishnusudarshana-cfcf7',
        $serviceAccountPath,
        $connection
    );

    $title = sanitize($data['title']);
    $body = sanitize($data['body']);
    $additionalData = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
    $options = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];

    $result = null;

    switch ($recipientType) {
        case 'device':
            $token = sanitize($data['device_token']);
            $result = $fcm->sendToDevice($token, $title, $body, $additionalData, $options);
            break;

        case 'topic':
            $topic = sanitize($data['topic']);
            $result = $fcm->sendToTopic($topic, $title, $body, $additionalData, $options);
            break;

        case 'broadcast':
            $result = $fcm->sendToAllDevices($title, $body, $additionalData, $options);
            break;
    }

    // Add metadata to response
    $result['timestamp'] = date('Y-m-d H:i:s');
    $result['recipient_type'] = $recipientType;

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

?>
