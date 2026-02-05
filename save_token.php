<?php
// save_token.php
// Receives FCM token and stores it in database (no duplicates)

header('Content-Type: application/json');

require_once __DIR__ . '/config/db.php';

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$token = isset($payload['token']) ? trim($payload['token']) : '';

if ($token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token is required']);
    exit;
}

try {
    // Create table if not exists (safe on shared hosting)
    $pdo->exec("CREATE TABLE IF NOT EXISTS fcm_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token TEXT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL,
        last_seen DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $hash = hash('sha256', $token);
    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("INSERT INTO fcm_tokens (token, token_hash, is_active, created_at, last_seen)
        VALUES (?, ?, 1, ?, ?)
        ON DUPLICATE KEY UPDATE token = VALUES(token), is_active = 1, last_seen = VALUES(last_seen)");
    $stmt->execute([$token, $hash, $now, $now]);

    echo json_encode(['success' => true, 'message' => 'Token saved']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
