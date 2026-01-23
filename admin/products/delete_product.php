<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id > 0) {
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
}
