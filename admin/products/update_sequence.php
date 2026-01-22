<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if (!isset($_POST['id']) || !isset($_POST['order'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$id = (int)$_POST['id'];
$order = (int)$_POST['order'];

try {
    $stmt = $pdo->prepare("UPDATE products SET display_order = ? WHERE id = ?");
    $stmt->execute([$order, $id]);
    
    echo json_encode(['success' => true, 'message' => 'Sequence updated']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
