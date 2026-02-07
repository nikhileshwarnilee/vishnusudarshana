<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE token_bookings SET status = 'start' WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}
echo json_encode(['success' => false]);
exit;
