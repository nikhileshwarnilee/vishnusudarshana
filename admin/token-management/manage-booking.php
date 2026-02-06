<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id > 0 && in_array($action, ['delete', 'complete'])) {
        $stmt = $pdo->prepare('DELETE FROM token_bookings WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }
}
echo json_encode(['success' => false]);
exit;