<?php
// capture-payments.php
session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: ../login.php');
	exit;
}
require_once __DIR__ . '/../includes/top-menu.php';
require_once __DIR__ . '/../../config/db.php';

// Fetch all online payments from service_requests table
$sql = "SELECT id, payment_id, customer_name, selected_products, total_amount, payment_status, created_at, payment_date FROM service_requests WHERE payment_id IS NOT NULL AND payment_id != '' AND payment_status = 'Paid' ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Capture Payments</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="../../assets/css/style.css">
	<style>
		.capture-container { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(128,0,0,0.07); padding:32px 24px; max-width:1100px; margin:32px auto; }
		table { width:100%; border-collapse:collapse; margin-top:10px; }
		th, td { padding:12px 10px; border-bottom:1px solid #eee; text-align:left; }
		th { background:#faf6f6; color:#800000; font-weight:700; }
		tr:last-child td { border-bottom:none; }
		.capture-btn { padding:7px 16px; border:none; border-radius:4px; font-weight:600; cursor:pointer; background:#007bff; color:#fff; }
	</style>
</head>
<body>
<div class="capture-container">
	<h1 style="margin-bottom:18px; color:#800000; font-size:1.5em;">Collected Online Payments</h1>
	<table>
		<tr>
			<th>Payment ID</th>
			<th>Customer Name</th>
			<th>Product/Service(s)</th>
			<th>Amount</th>
			<th>Status</th>
			<th>Date</th>
			<th>Action</th>
		</tr>
		<?php if (empty($payments)): ?>
			<tr><td colspan="7" style="text-align:center; color:#888;">No online payments found.</td></tr>
		<?php else: foreach ($payments as $row): ?>
			<tr>
				<td><?= htmlspecialchars($row['payment_id']) ?></td>
				<td><?= htmlspecialchars($row['customer_name']) ?></td>
				<td>
					<?php 
					// selected_products is a JSON array of objects with 'name'
					$products = [];
					if (!empty($row['selected_products'])) {
						$items = json_decode($row['selected_products'], true);
						if (is_array($items)) {
							foreach ($items as $item) {
								if (isset($item['name'])) $products[] = $item['name'];
							}
						}
					}
					echo htmlspecialchars(implode(', ', $products) ?: '-');
					?>
				</td>
				<td>₹<?= number_format($row['total_amount'],2) ?></td>
				<td><?= htmlspecialchars($row['payment_status']) ?></td>
				<td><?= htmlspecialchars($row['payment_date'] ?? $row['created_at']) ?></td>
				<td><button class="capture-btn">Capture Payment</button></td>
			</tr>
		<?php endforeach; endif; ?>
	</table>
</div>
</body>
</html>
