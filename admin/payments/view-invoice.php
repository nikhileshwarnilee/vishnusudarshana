<?php
// view-invoice.php
session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: ../login.php');
	exit;
}
require_once __DIR__ . '/../includes/top-menu.php';
require_once __DIR__ . '/../../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT i.*, c.name as customer_name, c.mobile, c.address FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id WHERE i.id = ?");
$stmt->execute([$id]);
$inv = $stmt->fetch();
if (!$inv) { echo '<div style="padding:40px; color:#800000;">Invoice not found.</div>'; exit; }
$items = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$items->execute([$id]);
$items = $items->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>View Invoice</title>
	<link rel="stylesheet" href="../../assets/css/style.css">
	<style>
		.view-invoice-box { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(128,0,0,0.07); padding:32px 24px; max-width:700px; margin:32px auto; }
		.inv-title { color:#800000; font-size:1.4em; font-weight:700; margin-bottom:18px; }
		.inv-section { margin-bottom:18px; }
		.inv-label { font-weight:600; color:#333; }
		table { width:100%; border-collapse:collapse; margin-top:10px; }
		th, td { padding:10px 8px; border-bottom:1px solid #eee; text-align:left; }
		th { background:#faf6f6; color:#800000; font-weight:700; }
		tr:last-child td { border-bottom:none; }
	</style>
</head>
<body>
<div class="view-invoice-box">
	<div class="inv-title">Invoice #<?= $inv['id'] ?></div>
	<div class="inv-section"><span class="inv-label">Date:</span> <?= htmlspecialchars($inv['invoice_date']) ?></div>
	<div class="inv-section"><span class="inv-label">Customer:</span> <?= htmlspecialchars($inv['customer_name']) ?> (<?= htmlspecialchars($inv['mobile']) ?>)<br><span style="color:#888;">Address:</span> <?= htmlspecialchars($inv['address']) ?></div>
	<div class="inv-section"><span class="inv-label">Note:</span> <?= nl2br(htmlspecialchars($inv['notes'])) ?></div>
	<table>
		<tr><th>Product/Service</th><th>Qty</th><th>Amount</th><th>Total</th></tr>
		<?php foreach ($items as $item): ?>
		<tr>
			<td><?= htmlspecialchars($item['product_name']) ?></td>
			<td><?= $item['qty'] ?></td>
			<td>₹<?= number_format($item['amount'],2) ?></td>
			<td>₹<?= number_format($item['qty'] * $item['amount'],2) ?></td>
		</tr>
		<?php endforeach; ?>
		<tr style="font-weight:700; background:#f8f8f8;"><td colspan="2">Total</td><td><?= $inv['total_qty'] ?></td><td>₹<?= number_format($inv['total_amount'],2) ?></td></tr>
	</table>
</div>
</body>
</html>
