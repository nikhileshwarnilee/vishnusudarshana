<?php
// fetch_customers.php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo json_encode([]);
    exit;
}


$stmt = $pdo->prepare("SELECT id, name, mobile, address FROM customers WHERE name LIKE :q OR mobile LIKE :q LIMIT 15");
$stmt->execute(['q' => "%$q%"]);
$results = $stmt->fetchAll();

// For each customer, fetch dues
foreach ($results as &$row) {
    $cid = (int)$row['id'];
    // Total invoiced
    $stmt2 = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) as total_invoiced FROM invoices WHERE customer_id = ?');
    $stmt2->execute([$cid]);
    $total_invoiced = $stmt2->fetchColumn();
    // Total paid
    $stmt3 = $pdo->prepare('SELECT COALESCE(SUM(paid_amount),0) as total_paid FROM payments WHERE customer_id = ?');
    $stmt3->execute([$cid]);
    $total_paid = $stmt3->fetchColumn();
    $row['dues'] = (float)$total_invoiced - (float)$total_paid;
}
unset($row);
echo json_encode($results);
