<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
$category = $_GET['category'] ?? '';
if (!$category) {
    echo json_encode(['success' => false, 'error' => 'No category']);
    exit;
}
$stmt = $pdo->prepare('SELECT id, product_name, short_description, price FROM products WHERE category_slug = ? AND is_active = 1 ORDER BY price ASC');
$stmt->execute([$category]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['success' => true, 'products' => $products]);

