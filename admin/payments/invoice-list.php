<?php
// invoice-list.php
session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: ../login.php');
	exit;
}
require_once __DIR__ . '/../includes/top-menu.php';
require_once __DIR__ . '/../../config/db.php';

// Fetch customers for filter dropdown
$customers = $pdo->query("SELECT id, name FROM customers ORDER BY name")->fetchAll();


// Handle filters
$where = [];
$params = [];
if (!empty($_GET['customer_id'])) {
	$where[] = 'i.customer_id = ?';
	$params[] = (int)$_GET['customer_id'];
}
if (!empty($_GET['from_date'])) {
	$where[] = 'i.invoice_date >= ?';
	$params[] = $_GET['from_date'];
}
if (!empty($_GET['to_date'])) {
	$where[] = 'i.invoice_date <= ?';
	$params[] = $_GET['to_date'];
}
if (!empty($_GET['search'])) {
	$where[] = "(
		c.name LIKE ? OR
		i.notes LIKE ? OR
		i.id IN (SELECT invoice_id FROM invoice_items WHERE product_name LIKE ?)
	)";
	$searchTerm = '%' . $_GET['search'] . '%';
	array_push($params, $searchTerm, $searchTerm, $searchTerm);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Always join customers for search to avoid SQL error
$join = "LEFT JOIN customers c ON i.customer_id = c.id";

$total = $pdo->prepare("SELECT COUNT(*) FROM invoices i $join $whereSql");
$total->execute($params);
$totalRows = $total->fetchColumn();

$sql = "SELECT i.*, c.name as customer_name FROM invoices i $join $whereSql ORDER BY i.invoice_date DESC, i.id DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// For pagination links
$queryStr = http_build_query(array_diff_key($_GET, ['page' => '']));

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Invoice List</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="../../assets/css/style.css">
	<style>
		.invoice-list-container { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(128,0,0,0.07); padding:32px 24px; max-width:1100px; margin:32px auto; }
		.filter-bar { display:flex; gap:18px; flex-wrap:wrap; align-items:center; margin-bottom:22px; }
		.filter-bar label { font-weight:600; color:#333; margin-right:6px; }
		.filter-bar input, .filter-bar select { padding:8px 10px; border:1px solid #ccc; border-radius:4px; }
		table { width:100%; border-collapse:collapse; margin-top:10px; }
		th, td { padding:12px 10px; border-bottom:1px solid #eee; text-align:left; }
		th { background:#faf6f6; color:#800000; font-weight:700; }
		tr:last-child td { border-bottom:none; }
		.action-btn { padding:7px 16px; border:none; border-radius:4px; font-weight:600; cursor:pointer; margin-right:6px; text-decoration: none; }
		.view-btn { background:#007bff; color:#fff; }
		.edit-btn { background:#ffc107; color:#333; }
		.delete-btn { background:#dc3545; color:#fff; }
		.pagination { margin-top:22px; text-align:right; }
		.pagination a { display:inline-block; padding:7px 14px; margin:0 2px; border-radius:4px; background:#f4f4f4; color:#800000; text-decoration:none; font-weight:600; }
		.pagination a.active, .pagination a:hover { background:#800000; color:#fff; }
	</style>
</head>
<body>
<div class="invoice-list-container">
	<h1 style="margin-bottom:18px; color:#800000; font-size:1.5em;">Invoices</h1>
	<form class="filter-bar" method="get" style="flex-wrap:wrap;">
		<input type="text" name="search" placeholder="Search invoice, customer, product..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="min-width:180px;">
		<label for="from_date">From:</label>
		<input type="date" name="from_date" id="from_date" value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>">
		<label for="to_date">To:</label>
		<input type="date" name="to_date" id="to_date" value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>">
		<label for="customer_id">Customer:</label>
		<select name="customer_id" id="customer_id">
			<option value="">All</option>
			<?php foreach ($customers as $c): ?>
				<option value="<?= $c['id'] ?>" <?= (isset($_GET['customer_id']) && $_GET['customer_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="action-btn view-btn" style="margin-left:10px;">Filter</button>
	</form>
	<table>
		<tr>
			<th>Invoice Number</th>
			<th>Date</th>
			<th>Customer</th>
			<th>Total Qty</th>
			<th>Total Amount</th>
			<th>Note</th>
			<th>Actions</th>
		</tr>
		<?php if (empty($invoices)): ?>
			<tr><td colspan="7" style="text-align:center; color:#888;">No invoices found.</td></tr>
		<?php else: foreach ($invoices as $inv): ?>
			<tr>
				<td><?= $inv['id'] ?></td>
				<td><?= htmlspecialchars($inv['invoice_date']) ?></td>
				<td><?= htmlspecialchars($inv['customer_name']) ?></td>
				<td><?= $inv['total_qty'] ?></td>
				<td>₹<?= number_format($inv['total_amount'],2) ?></td>
				<td><?= htmlspecialchars($inv['notes']) ?></td>
				<td>
					<a href="view-invoice.php?id=<?= $inv['id'] ?>" class="action-btn view-btn">View</a>
					<a href="edit-invoice.php?id=<?= $inv['id'] ?>" class="action-btn edit-btn">Edit</a>
					<a href="delete-invoice.php?id=<?= $inv['id'] ?>" class="action-btn delete-btn" onclick="return confirm('Delete this invoice?')">Delete</a>
				</td>
			</tr>
		<?php endforeach; endif; ?>
	</table>
	<?php if ($totalRows > $perPage): ?>
	<div class="pagination">
		<?php for ($p = 1; $p <= ceil($totalRows/$perPage); $p++): ?>
			<a href="?<?= $queryStr . ($queryStr ? '&' : '') . 'page=' . $p ?>" class="<?= $p == $page ? 'active' : '' ?>"> <?= $p ?> </a>
		<?php endfor; ?>
	</div>
	<?php endif; ?>
</div>
</body>
</html>
