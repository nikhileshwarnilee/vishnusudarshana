<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

try {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("DELETE FROM token_management WHERE token_date < ?");
    $stmt->execute([$today]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
