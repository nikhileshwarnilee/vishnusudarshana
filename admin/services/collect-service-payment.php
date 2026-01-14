<?php
// collect-service-payment.php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
	echo json_encode(['success'=>false, 'error'=>'Not logged in.']);
	exit;
}
require_once __DIR__ . '/../../config/db.php';

$service_request_id = isset($_POST['service_request_id']) ? (int)$_POST['service_request_id'] : 0;
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$note = trim($_POST['note'] ?? '');
$pay_method = trim($_POST['pay_method'] ?? '');

$pay_date = $_POST['pay_date'] ?? date('Y-m-d');
$transaction_details = trim($_POST['transaction_details'] ?? '');
$discount = isset($_POST['discount']) ? (float)$_POST['discount'] : 0;


if ($service_request_id <= 0 || $amount <= 0) {
	echo json_encode(['success'=>false, 'error'=>'Invalid service request or amount.']);
	exit;
}

try {
	// Fetch the service request
	$stmt = $pdo->prepare('SELECT * FROM service_requests WHERE id = ?');
	$stmt->execute([$service_request_id]);
	$sr = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$sr) {
		error_log('collect-service-payment.php: Service request not found for id ' . $service_request_id);
		echo json_encode(['success'=>false, 'error'=>'Service request not found.']);
		exit;
	}
	if ($sr['payment_status'] === 'Paid') {
		error_log('collect-service-payment.php: Payment already collected for id ' . $service_request_id);
		echo json_encode(['success'=>false, 'error'=>'Payment already collected.']);
		exit;
	}

	if ($amount < ($sr['total_amount'] - $discount)) {
		error_log('collect-service-payment.php: Amount less than due for id ' . $service_request_id . ', amount: ' . $amount . ', due: ' . ($sr['total_amount'] - $discount));
		echo json_encode(['success'=>false, 'error'=>'Amount less than due (after discount).']);
		exit;
	}

	// Only mark as paid if currently unpaid

	$pdo->prepare('UPDATE service_requests SET payment_status = ?, payment_method = ?, payment_note = ?, payment_date = ?, transaction_details = ?, discount = ? WHERE id = ? AND payment_status != "Paid"')
		->execute(['Paid', $pay_method, $note, $pay_date, $transaction_details, $discount, $service_request_id]);

	// Insert payment record into service_payments table (with all details)
	$stmt = $pdo->prepare('INSERT INTO service_payments (service_request_id, amount, discount, created_at, pay_method, note, pay_date, transaction_details, collected_by) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)');
	$stmt->execute([
		$service_request_id,
		$amount,
		$discount,
		$pay_method,
		$note,
		$pay_date,
		$transaction_details,
		isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
	]);

	echo json_encode(['success'=>true]);
} catch (Throwable $e) {
	$logMsg = 'collect-service-payment.php ERROR: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine();
	error_log($logMsg);
	// Try to read last 10 lines of error log (if readable)
	$logFile = ini_get('error_log') ?: (PHP_OS_FAMILY === 'Windows' ? getenv('SystemDrive') . '\xampp\php\logs\php_error_log' : '/var/log/php_error.log');
	$logContent = '';
	if ($logFile && file_exists($logFile)) {
		$lines = @file($logFile);
		if ($lines !== false) {
			$logContent = implode("", array_slice($lines, -10));
		}
	}
	// Only show error if not duplicate payment
	if (strpos($e->getMessage(), 'Payment already collected') === false) {
		echo json_encode([
			'success'=>false,
			'error'=>$e->getMessage(),
			'log'=>$logMsg,
			'log_tail'=>$logContent
		]);
	}
}