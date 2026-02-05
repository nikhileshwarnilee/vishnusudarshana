<?php
// send_notification.php
// Sends push notifications to all active FCM tokens using Firebase legacy HTTP API

header('Content-Type: application/json');

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/fcm_config.php';

$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($title === '' || $message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title and message are required']);
    exit;
}

if (!defined('FCM_SERVER_KEY') || FCM_SERVER_KEY === 'YOUR_FCM_SERVER_KEY') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'FCM server key not configured']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT token FROM fcm_tokens WHERE is_active = 1");
    $stmt->execute();
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tokens)) {
        echo json_encode(['success' => false, 'message' => 'No active tokens']);
        exit;
    }

    $chunks = array_chunk($tokens, 500);
    $successCount = 0;
    $failureCount = 0;

    foreach ($chunks as $chunk) {
        $payload = [
            'registration_ids' => $chunk,
            'notification' => [
                'title' => $title,
                'body' => $message,
                'icon' => '/assets/images/icon-192.png'
            ],
            'data' => [
                'click_action' => '/'
            ]
        ];

        $ch = curl_init('https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: key=' . FCM_SERVER_KEY,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $failureCount += count($chunk);
            continue;
        }

        $response = json_decode($result, true);
        $successCount += $response['success'] ?? 0;
        $failureCount += $response['failure'] ?? 0;

        // Deactivate invalid tokens
        if (!empty($response['results'])) {
            foreach ($response['results'] as $idx => $res) {
                if (isset($res['error']) && in_array($res['error'], ['NotRegistered', 'InvalidRegistration'], true)) {
                    $badToken = $chunk[$idx] ?? null;
                    if ($badToken) {
                        $hash = hash('sha256', $badToken);
                        $upd = $pdo->prepare("UPDATE fcm_tokens SET is_active = 0 WHERE token_hash = ?");
                        $upd->execute([$hash]);
                    }
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'sent' => $successCount,
        'failed' => $failureCount
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
