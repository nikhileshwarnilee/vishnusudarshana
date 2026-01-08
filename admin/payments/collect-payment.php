<?php
// collect-payment.php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
	echo json_encode(['success'=>false, 'error'=>'Not logged in.']);
	exit;
}
require_once __DIR__ . '/../../config/db.php';

$customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$note = trim($_POST['note'] ?? '');
$pay_method = trim($_POST['pay_method'] ?? '');
$pay_date = $_POST['pay_date'] ?? date('Y-m-d');
$transaction_details = trim($_POST['transaction_details'] ?? '');

if ($customer_id <= 0 || $amount <= 0) {
	echo json_encode(['success'=>false, 'error'=>'Invalid customer or amount.']);
	exit;
}

// Find unpaid invoices for this customer, oldest first
$invoices = $pdo->prepare("SELECT * FROM invoices WHERE customer_id = ? AND total_amount > paid_amount ORDER BY invoice_date ASC, id ASC");
$invoices->execute([$customer_id]);
$invoices = $invoices->fetchAll();

$remaining = $amount;
$paid_any = false;
foreach ($invoices as $inv) {
	$due = $inv['total_amount'] - $inv['paid_amount'];
	$pay = min($due, $remaining);
	if ($pay > 0) {
		$pdo->prepare("UPDATE invoices SET paid_amount = paid_amount + ? WHERE id = ?")
			->execute([$pay, $inv['id']]);
		$pdo->prepare("INSERT INTO payments (customer_id, invoice_id, paid_date, paid_amount, note, method, transaction_details) VALUES (?,?,?,?,?,?,?)")
			->execute([
				$customer_id,
				$inv['id'],
				$pay_date,
				$pay,
				$note,
				$pay_method,
				$transaction_details
			]);
		$remaining -= $pay;
		$paid_any = true;
	}
	if ($remaining <= 0) break;
}

if ($amount > 0 && $paid_any) {
	echo json_encode(['success'=>true]);
} else {
	echo json_encode(['success'=>false, 'error'=>'No dues found or nothing paid.']);
}
