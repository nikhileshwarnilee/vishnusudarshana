<?php
/**
 * Get FCM Notification History
 * Returns list of sent notifications for monitoring/logging
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
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $limit = max(1, min($limit, 200));
    $filterType = isset($_GET['type']) ? sanitize($_GET['type']) : null;
    $filterStatus = isset($_GET['status']) ? sanitize($_GET['status']) : null;

    // Initialize Firebase Messaging
    $fcm = new FirebaseMessaging(
        'vishnusudarshana-cfcf7',
        null,
        $connection
    );

    $history = $fcm->getNotificationHistory($limit, $filterType, $filterStatus);

    echo json_encode([
        'success' => true,
        'count' => count($history),
        'notifications' => $history,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

?>
