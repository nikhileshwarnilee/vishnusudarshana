<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$customer_id) { echo json_encode([]); exit; }

$sql = "SELECT id, invoice_date, total_amount, paid_amount, payment_status FROM invoices WHERE customer_id = :cid ORDER BY invoice_date DESC, id DESC LIMIT 20";
$stmt = $pdo->prepare($sql);
$stmt->execute(['cid' => $customer_id]);
$out = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $out[] = [
        'id' => $row['id'],
        'invoice_no' => 'INV-' . $row['id'],
        'date' => $row['invoice_date'],
        'amount' => $row['total_amount'],
        'paid_amount' => $row['paid_amount'],
        'status' => ($row['payment_status'] === 'captured' || $row['paid_amount'] >= $row['total_amount']) ? 'Paid' : 'Due'
    ];
}
echo json_encode($out);