<?php
// view-customer-payments.php
session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: ../login.php');
	exit;
}
require_once __DIR__ . '/../../config/db.php';

$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customer = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$customer->execute([$customer_id]);
$customer = $customer->fetch();
if (!$customer) { echo '<div style=\'padding:40px; color:#800000;\'>Customer not found.</div>'; exit; }

// For now, show summary from invoices (if payment table is not present)
$stmt = $pdo->prepare("SELECT paid_date, paid_amount, note, method, transaction_details FROM payments WHERE customer_id = ? ORDER BY paid_date DESC, id DESC");
$stmt->execute([$customer_id]);
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang='en'>
<head>
	<meta charset='UTF-8'>
	<title>Payments for <?= htmlspecialchars($customer['name']) ?></title>
	<link rel='stylesheet' href='../../assets/css/style.css'>
	<style>
		body { margin: 0; background: #f7f7fa; }
		.pay-list-container { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(128,0,0,0.07); padding:32px 24px; max-width:900px; margin:32px auto; }
		table { width:100%; border-collapse:collapse; margin-top:10px; }
		th, td { padding:12px 10px; border-bottom:1px solid #eee; text-align:left; }
		th { background:#faf6f6; color:#800000; font-weight:700; }
		tr:last-child td { border-bottom:none; }
	</style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/top-menu.php'; ?>
<div class='pay-list-container'>
	<h1 style='margin-bottom:18px; color:#800000; font-size:1.3em;'>Payments for <?= htmlspecialchars($customer['name']) ?> (<?= htmlspecialchars($customer['mobile']) ?>)</h1>
	<table>
		<tr>
			<th>Paid Date</th>
			<th>Paid Amount</th>
			<th>Note</th>
			<th>Method</th>
			<th>Transaction Details</th>
		</tr>
		<?php if (empty($payments)): ?>
			<tr><td colspan='5' style='text-align:center; color:#888;'>No payments found.</td></tr>
		<?php else: foreach ($payments as $pay): ?>
			<tr>
				<td><?= htmlspecialchars($pay['paid_date']) ?></td>
				<td>₹<?= number_format($pay['paid_amount'],2) ?></td>
				<td><?= htmlspecialchars($pay['note']) ?></td>
				<td><?= htmlspecialchars($pay['method']) ?></td>
				<td><?= htmlspecialchars($pay['transaction_details']) ?></td>
			</tr>
		<?php endforeach; endif; ?>
	</table>
</div>
</body>
</html>
