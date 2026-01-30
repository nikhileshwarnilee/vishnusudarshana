<?php
// payments.php
session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: ../login.php');
	exit;
}
require_once __DIR__ . '/../../config/db.php';

// Only show Invoice payments
$source_filter = 'Invoice';



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
// Build WHERE for both queries
$whereSqlPayments = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$whereSqlSR = [];
$paramsSR = [];
// Always require paid status and amount for service_requests
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

// Source filter logic for union
if ($source_filter === 'Invoice') {
	$unionSourceWhere = 'WHERE source = "Invoice"';
} elseif ($source_filter === 'Service Request') {
	$unionSourceWhere = 'WHERE source = "Service Request"';
} else {
	$unionSourceWhere = '';
}

// Main query: UNION payments and service_requests
$unionSql = "
	SELECT * FROM (
		SELECT p.id, p.paid_date, p.paid_amount, p.method, p.note, p.transaction_details, c.name as customer_name, c.mobile, 'Invoice' as source, NULL as products_json
		FROM payments p LEFT JOIN customers c ON p.customer_id = c.id $whereSqlPayments
		UNION ALL
		SELECT sr.id, DATE(sr.created_at) as paid_date, sr.total_amount as paid_amount, 
				IF(sr.tracking_id LIKE 'VDSK%', 'Online', 'Offline') as method, sr.category_slug as note, sr.tracking_id as transaction_details, 
				sr.customer_name as customer_name, sr.mobile, 'Service Request' as source, sr.selected_products as products_json
			FROM service_requests sr
			$whereSqlSR
	) t $unionSourceWhere
	ORDER BY paid_date DESC, id DESC
	LIMIT $perPage OFFSET $offset
";

try {
	$stmt = $pdo->prepare($unionSql);
	$stmt->execute(array_merge($params, $paramsSR));
	$rows = $stmt->fetchAll();
} catch (Exception $e) {
	$rows = [];
	error_log("Payments query error: " . $e->getMessage());
}

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
	<title>All Payments</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="../../assets/css/style.css">	<link rel="stylesheet" href="../includes/responsive-tables.css">	<style>
	body { margin: 0; background: #f7f7fa; font-family: 'Segoe UI', Arial, sans-serif; }
	.payments-container { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(128,0,0,0.07); padding:32px 24px; max-width:1400px; margin:32px auto; }
		h1 { color:#800000; margin-bottom:18px; font-size:1.5em; }
		.filter-bar { display:flex; gap:18px; flex-wrap:wrap; align-items:center; margin-bottom:22px; }
		.filter-bar input, .filter-bar select { padding:8px 10px; border:1px solid #ccc; border-radius:4px; }
		table { width:100%; border-collapse:collapse; margin-top:10px; table-layout: auto; font-size: 0.85em; }
		th, td { padding:8px 6px; border-bottom:1px solid #f3caca; text-align:left; white-space: nowrap; }
		th { background:#f9eaea; color:#800000; font-weight:600; font-size: 0.9em; }
		td { font-size: 0.95em; }
		tr:last-child td { border-bottom:none; }
		tr:hover { background: #f3f7fa; }
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
		@media (max-width: 1200px) {
			table { overflow-x: auto; display: block; }
		}
	</style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/top-menu.php'; ?>
<div class="payments-container">
	<h1 style="margin-bottom:18px; color:#800000; font-size:1.5em;">All Payments</h1>
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
		<!-- Source filter removed: always showing Invoice payments only -->
		<button type="submit" class="action-btn filter-btn" style="margin-left:10px;">
			<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" style="vertical-align:middle;"><path fill="#fff" d="M3 5a1 1 0 0 1 1-1h16a1 1 0 0 1 .8 1.6l-5.6 7.47V19a1 1 0 0 1-1.45.89l-4-2A1 1 0 0 1 9 17v-4.93L3.2 6.6A1 1 0 0 1 3 5Zm3.2 1 5.3 7.07a1 1 0 0 1 .2.6V17.4l2 1V13.2a1 1 0 0 1 .2-.6L20.8 6H3.2Z"/></svg>
			<span>Filter</span>
		</button>
	</form>
	<table>
		</tr>
		<tr>
		<th>Source</th>
		<th>Customer Name</th>
		<th>Mobile</th>
		<th>Paid Date</th>
		<th>Paid Amount</th>
		<th>Method</th>
		<th>Note</th>
		<th>Transaction Details</th>
		<th>Delete</th>
		</tr>
		</tr>
		<?php if (empty($rows)): ?>
			<tr><td colspan="9" style="text-align:center; color:#888;">No payments found.</td></tr>
		<?php else: foreach ($rows as $row): ?>
			<tr>
			<td><?= htmlspecialchars($row['source']) ?></td>
			<td><?= htmlspecialchars($row['customer_name']) ?></td>
			<td><?= htmlspecialchars($row['mobile']) ?></td>
			<td><?= htmlspecialchars($row['paid_date']) ?></td>
			<td>₹<?= number_format($row['paid_amount'],2) ?></td>
			<td><?= htmlspecialchars($row['method']) ?></td>
			<td><?= htmlspecialchars($row['note']) ?></td>
			<td><?= htmlspecialchars($row['transaction_details']) ?></td>
			<td>
				<?php if ($row['source'] === 'Invoice'): ?>
					<form method="post" onsubmit="return confirm('Delete this payment?');" style="display:inline;">
						<input type="hidden" name="delete_payment_id" value="<?= (int)$row['id'] ?>">
						<button type="submit" style="background:#c00;color:#fff;border:none;padding:6px 14px;border-radius:5px;cursor:pointer;">Delete</button>
					</form>
				<?php elseif ($row['source'] === 'Service Request'): ?>
					<form method="post" onsubmit="return confirm('Delete this service request payment?');" style="display:inline;">
						<input type="hidden" name="delete_sr_id" value="<?= (int)$row['id'] ?>">
						<button type="submit" style="background:#c00;color:#fff;border:none;padding:6px 14px;border-radius:5px;cursor:pointer;">Delete</button>
					</form>
				<?php endif; ?>
			</td>
			</tr>
		<?php
		// Handle delete actions
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			if (!empty($_POST['delete_payment_id'])) {
				$delid = (int)$_POST['delete_payment_id'];
				$pdo->prepare('DELETE FROM payments WHERE id = ?')->execute([$delid]);
				echo "<script>location.href=location.href;</script>";
				exit;
			}
			if (!empty($_POST['delete_sr_id'])) {
				$delid = (int)$_POST['delete_sr_id'];
				$pdo->prepare('DELETE FROM service_requests WHERE id = ?')->execute([$delid]);
				echo "<script>location.href=location.href;</script>";
				exit;
			}
		}
		?>
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
<script src="../includes/responsive-tables.js"></script>
</html>
