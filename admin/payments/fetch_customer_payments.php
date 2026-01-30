<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$customer_id) { echo json_encode([]); exit; }

$sql = "SELECT paid_date, paid_amount, method, note, transaction_details FROM payments WHERE customer_id = :cid ORDER BY paid_date DESC, id DESC LIMIT 20";
$stmt = $pdo->prepare($sql);
$stmt->execute(['cid' => $customer_id]);
$out = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $out[] = [
        'date' => $row['paid_date'],
        'amount' => $row['paid_amount'],
        'method' => $row['method'],
        'note' => $row['note'],
        'ref' => $row['transaction_details']
    ];
}
echo json_encode($out);