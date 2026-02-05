<?php
// send_notification.php
// Sends push notifications to all active FCM tokens using Firebase Cloud Messaging HTTP v1 API

header('Content-Type: application/json');

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/fcm_config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Google\Auth\Credentials\ServiceAccountCredentials;

/**
 * Log notification activity to file
 */
function logFCM($message, $level = 'INFO') {
    $logFile = __DIR__ . '/logs/fcm_notifications.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($title === '' || $message === '') {
    logFCM('Notification request rejected: Missing title or message', 'ERROR');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title and message are required']);
    exit;
}

logFCM("New notification request - Title: '{$title}', Message: '{$message}'", 'INFO');

if (!file_exists(FCM_SERVICE_ACCOUNT_PATH)) {
    logFCM('FCM service account file not found at: ' . FCM_SERVICE_ACCOUNT_PATH, 'ERROR');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Firebase service account file not found. Please download from Firebase Console.']);
    exit;
}

/**
 * Get OAuth 2.0 access token for FCM v1 API
 * @return string|null Access token or null on failure
 */
function getFCMAccessToken() {
    try {
        $credentials = new ServiceAccountCredentials(
            FCM_SCOPE,
            FCM_SERVICE_ACCOUNT_PATH
        );
        
        $token = $credentials->fetchAuthToken();
        return $token['access_token'] ?? null;
    } catch (Exception $e) {
        error_log('FCM token generation failed: ' . $e->getMessage());
        return null;
    }
}

// Get OAuth 2.0 access token
logFCM('Generating OAuth 2.0 access token...', 'INFO');
$accessToken = getFCMAccessToken();

if (!$accessToken) {
    logFCM('Failed to generate OAuth access token', 'ERROR');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to generate access token']);
    exit;
}

logFCM('Access token generated successfully', 'SUCCESS');

try {
    $stmt = $pdo->prepare("SELECT token FROM fcm_tokens WHERE is_active = 1");
    $stmt->execute();
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tokens)) {
        logFCM('No active FCM tokens found in database', 'WARNING');
        echo json_encode(['success' => false, 'message' => 'No active tokens']);
        exit;
    }

    logFCM('Found ' . count($tokens) . ' active FCM tokens', 'INFO');

    $successCount = 0;
    $failureCount = 0;
    $startTime = microtime(true);

    logFCM('Starting to send notifications to ' . count($tokens) . ' devices...', 'INFO');

    // FCM v1 API requires individual messages (no batch registration_ids)
    foreach ($tokens as $token) {
        // Build FCM v1 message structure
        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $message
                ],
                'webpush' => [
                    'notification' => [
                        'icon' => '/assets/images/logo/logo-iconpwa192.png',
                        'badge' => '/assets/images/logo/logo-iconpwa192.png'
                    ],
                    'fcm_options' => [
                        'link' => '/'
                    ]
                ]
            ]
        ];

        $ch = curl_init(FCM_API_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $successCount++;
        } else {
            $failureCount++;
            
            // Check if token is invalid and deactivate it
            $response = json_decode($result, true);
            $errorCode = $response['error']['status'] ?? '';
            $errorMessage = $response['error']['message'] ?? 'Unknown error';
            
            logFCM("Failed to send to token (HTTP {$httpCode}): {$errorCode} - {$errorMessage}", 'ERROR');
            
            if (in_array($errorCode, ['NOT_FOUND', 'INVALID_ARGUMENT', 'UNREGISTERED'], true)) {
                $hash = hash('sha256', $token);
                $upd = $pdo->prepare("UPDATE fcm_tokens SET is_active = 0 WHERE token_hash = ?");
                $upd->execute([$hash]);
                logFCM("Deactivated invalid token (error: {$errorCode})", 'INFO');
            }
        }
    }

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    logFCM("Notification batch complete - Sent: {$successCount}, Failed: {$failureCount}, Duration: {$duration}s", 'SUCCESS');

    echo json_encode([
        'success' => true,
        'sent' => $successCount,
        'failed' => $failureCount,
        'total' => count($tokens),
        'duration' => $duration
    ]);
} catch (Throwable $e) {
    logFCM('Exception occurred: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 'CRITICAL');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
