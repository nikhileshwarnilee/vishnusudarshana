<?php
// get_customer_dues.php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$customer_id) {
    echo json_encode(['error' => 'Invalid customer ID']);
    exit;
}
// Get customer name
$stmt = $pdo->prepare('SELECT name FROM customers WHERE id = ?');
$stmt->execute([$customer_id]);
$customer_name = $stmt->fetchColumn();
// Total invoiced
$stmt = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) as total_invoiced FROM invoices WHERE customer_id = ?');
$stmt->execute([$customer_id]);
$total_invoiced = $stmt->fetchColumn();
// Total paid
$stmt = $pdo->prepare('SELECT COALESCE(SUM(paid_amount),0) as total_paid FROM payments WHERE customer_id = ?');
$stmt->execute([$customer_id]);
$total_paid = $stmt->fetchColumn();
// Dues
$total_dues = $total_invoiced - $total_paid;
echo json_encode([
    'name' => $customer_name,
    'total_invoiced' => (float)$total_invoiced,
    'total_paid' => (float)$total_paid,
    'total_dues' => (float)$total_dues
]);
