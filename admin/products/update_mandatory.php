<?php
require_once '../../config/db.php';
header('Content-Type: application/json');

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$is_mandatory = isset($_POST['is_mandatory']) ? intval($_POST['is_mandatory']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE products SET is_mandatory = :is_mandatory WHERE id = :id');
    $stmt->execute([
        ':is_mandatory' => $is_mandatory,
        ':id' => $id
    ]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
