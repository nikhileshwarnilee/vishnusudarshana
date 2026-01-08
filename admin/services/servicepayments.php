<?php
// servicepayments.php
session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: ../login.php');
	exit;
}
require_once __DIR__ . '/../includes/top-menu.php';
require_once __DIR__ . '/../../config/db.php';

// Only show Service Request payments
$source_filter = 'Service Request';

// Filters
$where = [];
$params = [];
if (!empty($_GET['customer_id'])) {
	$where[] = 'p.customer_id = ?';
	$params[] = (int)$_GET['customer_id'];
}
if (!empty($_GET['from_date'])) {
	$where[] = 'p.paid_date >= ?';
	$params[] = $_GET['from_date'];
}
if (!empty($_GET['to_date'])) {
	$where[] = 'p.paid_date <= ?';
	$params[] = $_GET['to_date'];
}
if (!empty($_GET['search'])) {
	$where[] = '(c.name LIKE ? OR c.mobile LIKE ? OR p.note LIKE ? OR p.method LIKE ? OR p.transaction_details LIKE ?)';
	$searchTerm = '%' . $_GET['search'] . '%';
	array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Fetch customers for filter dropdown
$customers = $pdo->query("SELECT id, name FROM customers ORDER BY name")->fetchAll();

// Get payments (invoices) and service request payments (products)
$whereSqlPayments = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$whereSqlSR = [];
$paramsSR = [];
$whereSqlSR[] = "sr.payment_status = 'Paid'";
$whereSqlSR[] = "sr.total_amount > 0";
if (!empty($_GET['from_date'])) {
	$whereSqlSR[] = 'DATE(sr.created_at) >= ?';
	$paramsSR[] = $_GET['from_date'];
}
if (!empty($_GET['to_date'])) {
	$whereSqlSR[] = 'DATE(sr.created_at) <= ?';
	$paramsSR[] = $_GET['to_date'];
}
if (!empty($_GET['search'])) {
	$whereSqlSR[] = '(sr.customer_name LIKE ? OR sr.mobile LIKE ? OR sr.tracking_id LIKE ? OR sr.category_slug LIKE ? OR sr.selected_products LIKE ?)';
	$searchTerm = '%' . $_GET['search'] . '%';
	array_push($paramsSR, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}
$whereSqlSR = $whereSqlSR ? 'WHERE ' . implode(' AND ', $whereSqlSR) : '';

// Only show Service Request payments
$unionSourceWhere = 'WHERE source = "Service Request"';

$unionSql = "
	SELECT * FROM (
		SELECT p.id, p.pay_date as payment_date, p.amount as paid_amount, p.pay_method as method, p.note, p.transaction_details, NULL as customer_name, NULL as mobile, 'Service Payment' as source, NULL as products_json
		FROM service_payments p
		UNION ALL
		SELECT sr.id, sr.payment_date, sr.total_amount as paid_amount, sr.payment_method as method, sr.payment_note as note, sr.transaction_details, sr.customer_name, sr.mobile, 'Service Request' as source, sr.selected_products as products_json
		FROM service_requests sr $whereSqlSR
	) t $unionSourceWhere
	ORDER BY payment_date DESC, id DESC
	LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($unionSql);
$stmt->execute(array_merge($params, $paramsSR));
$rows = $stmt->fetchAll();

// For pagination (approximate, not exact due to UNION)
$totalPayments = $pdo->prepare("SELECT COUNT(*) FROM payments p LEFT JOIN customers c ON p.customer_id = c.id $whereSqlPayments");
$totalPayments->execute($params);
$countPayments = $totalPayments->fetchColumn();
$totalSR = $pdo->prepare("SELECT COUNT(*) FROM service_requests sr $whereSqlSR");
$totalSR->execute($paramsSR);
$countSR = $totalSR->fetchColumn();
$totalRows = $countPayments + $countSR;
$queryStr = http_build_query(array_diff_key($_GET, ['page' => '']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Service Request Payments</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="../../assets/css/style.css">
	<style>
		.payments-container { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(128,0,0,0.07); padding:32px 24px; max-width:1100px; margin:32px auto; }
		.filter-bar { display:flex; gap:18px; flex-wrap:wrap; align-items:center; margin-bottom:22px; }
		.filter-bar input, .filter-bar select { padding:8px 10px; border:1px solid #ccc; border-radius:4px; }
		table { width:100%; border-collapse:collapse; margin-top:10px; }
		th, td { padding:12px 10px; border-bottom:1px solid #eee; text-align:left; }
		th { background:#faf6f6; color:#800000; font-weight:700; }
		tr:last-child td { border-bottom:none; }
		.pagination { margin-top:22px; text-align:right; }
		.pagination a { display:inline-block; padding:7px 14px; margin:0 2px; border-radius:4px; background:#f4f4f4; color:#800000; text-decoration:none; font-weight:600; }
		.pagination a.active, .pagination a:hover { background:#800000; color:#fff; }
		.filter-btn {
			display: flex;
			align-items: center;
			gap: 7px;
			background: #800000;
			color: #fff;
			border: none;
			border-radius: 5px;
			padding: 9px 22px;
			font-weight: 700;
			font-size: 1em;
			box-shadow: 0 2px 8px rgba(128,0,0,0.08);
			cursor: pointer;
			transition: background 0.2s;
		}
		.filter-btn:hover {
			background: #a00000;
		}
	</style>
</head>
<body>
<div class="payments-container">
	<h1 style="margin-bottom:18px; color:#800000; font-size:1.5em;">Service Request Payments</h1>
	<form class="filter-bar" method="get">
		<input type="text" name="search" placeholder="Search customer, mobile, note, method, transaction..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="min-width:180px;">
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
		<button type="submit" class="action-btn filter-btn" style="margin-left:10px;">
			<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" style="vertical-align:middle;"><path fill="#fff" d="M3 5a1 1 0 0 1 1-1h16a1 1 0 0 1 .8 1.6l-5.6 7.47V19a1 1 0 0 1-1.45.89l-4-2A1 1 0 0 1 9 17v-4.93L3.2 6.6A1 1 0 0 1 3 5Zm3.2 1 5.3 7.07a1 1 0 0 1 .2.6V17.4l2 1V13.2a1 1 0 0 1 .2-.6L20.8 6H3.2Z"/></svg>
			<span>Filter</span>
		</button>
	</form>
	<table>
		<tr>
			<th>Source</th>
			<th>Customer Name</th>
			<th>Mobile</th>
			<th>Paid Date</th>
			<th>Paid Amount</th>
			<th>Method</th>
			<th>Note</th>
			<th>Transaction Details</th>
			<th>Products</th>
		</tr>
		<?php if (empty($rows)): ?>
			<tr><td colspan="9" style="text-align:center; color:#888;">No payments found.</td></tr>
		<?php else: foreach ($rows as $row): ?>
			<tr>
				<td><?= htmlspecialchars($row['source']) ?></td>
				<td><?= htmlspecialchars($row['customer_name']) ?></td>
				<td><?= htmlspecialchars($row['mobile']) ?></td>
				   <td><?= htmlspecialchars($row['payment_date'] ?? '') ?></td>
				<td>₹<?= number_format($row['paid_amount'],2) ?></td>
				   <td><?= htmlspecialchars($row['method'] ?? '') ?></td>
				   <td><?= htmlspecialchars($row['note'] ?? '') ?></td>
				   <td><?= htmlspecialchars($row['payment_date'] ?? '') ?></td>
				   <td><?php
					   $td = $row['transaction_details'] ?? '';
					   // Remove date if present (e.g., if transaction_details contains a date string)
					   // This regex removes YYYY-MM-DD or DD-MM-YYYY or similar date patterns
					   $td = preg_replace('/\b\d{4}-\d{2}-\d{2}\b|\b\d{2}-\d{2}-\d{4}\b/', '', $td);
					   echo htmlspecialchars(trim($td));
				   ?></td>
				<td>
					<?php if ($row['source'] === 'Service Request' && !empty($row['products_json'])): ?>
						<?php 
						$products = json_decode($row['products_json'], true);
						if ($products && is_array($products)) {
							foreach ($products as $prod) {
								$pname = isset($prod['name']) ? $prod['name'] : (isset($prod['product_name']) ? $prod['product_name'] : '');
								$qty = isset($prod['qty']) ? $prod['qty'] : 1;
								$price = isset($prod['price']) ? $prod['price'] : 0;
								echo htmlspecialchars($pname) . ' x' . $qty . ' (₹' . number_format($price,2) . ')<br>';
							}
						}
						?>
					<?php endif; ?>
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
