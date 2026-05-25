<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('DELETE FROM token_bookings WHERE token_date < ?');
    $stmt->execute([$today]);

    echo json_encode([
        'success' => true,
        'deleted_count' => $stmt->rowCount(),
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to delete previous booked tokens.',
    ]);
}

