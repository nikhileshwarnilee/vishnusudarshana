<?php
// view-customer-invoices.php
session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: ../login.php');
	exit;
}
require_once __DIR__ . '/../includes/top-menu.php';
require_once __DIR__ . '/../../config/db.php';

$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customer = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$customer->execute([$customer_id]);
$customer = $customer->fetch();
if (!$customer) { echo '<div style="padding:40px; color:#800000;">Customer not found.</div>'; exit; }

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$total = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE customer_id = ?");
$total->execute([$customer_id]);
$totalRows = $total->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM invoices WHERE customer_id = ? ORDER BY invoice_date DESC, id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute([$customer_id]);
$invoices = $stmt->fetchAll();
$totalAmount = 0;
foreach ($invoices as $inv) {
	$totalAmount += $inv['total_amount'];
}
$queryStr = http_build_query(array_diff_key($_GET, ['page' => '']));
?>
<!DOCTYPE html>
<html lang='en'>
<head>
	<meta charset='UTF-8'>
	<title>Invoices for <?= htmlspecialchars($customer['name']) ?></title>
	<link rel='stylesheet' href='../../assets/css/style.css'>
	<style>
		.inv-list-container { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(128,0,0,0.07); padding:32px 24px; max-width:900px; margin:32px auto; }
		table { width:100%; border-collapse:collapse; margin-top:10px; }
		th, td { padding:12px 10px; border-bottom:1px solid #eee; text-align:left; }
		th { background:#faf6f6; color:#800000; font-weight:700; }
		tr:last-child td { border-bottom:none; }
		.action-btn { padding:7px 16px; border:none; border-radius:4px; font-weight:600; cursor:pointer; margin-right:6px; text-decoration:none; }
		.view-btn { background:#007bff; color:#fff; }
		.pagination { margin-top:22px; text-align:right; }
		.pagination a { display:inline-block; padding:7px 14px; margin:0 2px; border-radius:4px; background:#f4f4f4; color:#800000; text-decoration:none; font-weight:600; }
		.pagination a.active, .pagination a:hover { background:#800000; color:#fff; }
	</style>
</head>
<body>
<div class='inv-list-container'>
	<h1 style='margin-bottom:18px; color:#800000; font-size:1.3em;'>Invoices for <?= htmlspecialchars($customer['name']) ?> (<?= htmlspecialchars($customer['mobile']) ?>)</h1>
	<table>
		<tr>
			<th>Invoice Number</th>
			<th>Date</th>
			<th>Total Amount</th>
			<th>Action</th>
		</tr>
		<?php if (empty($invoices)): ?>
			<tr><td colspan='4' style='text-align:center; color:#888;'>No invoices found.</td></tr>
		<?php else: foreach ($invoices as $inv): ?>
			<tr>
				<td><?= $inv['id'] ?></td>
				<td><?= htmlspecialchars($inv['invoice_date']) ?></td>
				<td>₹<?= number_format($inv['total_amount'],2) ?></td>
				<td><a href='view-invoice.php?id=<?= $inv['id'] ?>' class='action-btn view-btn'>View</a></td>
			</tr>
		<?php endforeach; ?>
		<tr style="font-weight:700; background:#f8f8f8;">
			<td colspan="2">Total</td>
			<td>₹<?= number_format($totalAmount,2) ?></td>
			<td></td>
		</tr>
		<?php endif; ?>
	</table>
	<?php if ($totalRows > $perPage): ?>
	<div class='pagination'>
		<?php for ($p = 1; $p <= ceil($totalRows/$perPage); $p++): ?>
			<a href='?<?= $queryStr . ($queryStr ? '&' : '') . 'page=' . $p ?>&id=<?= $customer_id ?>' class='<?= $p == $page ? 'active' : '' ?>'> <?= $p ?> </a>
		<?php endfor; ?>
	</div>
	<?php endif; ?>
</div>
</body>
</html>
