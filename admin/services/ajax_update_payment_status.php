<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';
if (!$id || !in_array($status, ['Paid', 'Partial Paid'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}
$stmt = $pdo->prepare('UPDATE service_requests SET payment_status = ? WHERE id = ?');
$stmt->execute([$status, $id]);
echo json_encode(['success' => true]);
