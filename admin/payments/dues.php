<?php
// dues.php
session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: ../login.php');
	exit;
}
require_once __DIR__ . '/../includes/top-menu.php';
require_once __DIR__ . '/../../config/db.php';


// Date filters
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

// Filters
$where = [];
$params = [];
if (!empty($_GET['search'])) {
	$where[] = '(c.name LIKE ? OR c.mobile LIKE ? OR c.address LIKE ?)';
	$searchTerm = '%' . $_GET['search'] . '%';
	array_push($params, $searchTerm, $searchTerm, $searchTerm);
}
if ($from_date) {
	$where[] = 'i.invoice_date >= ?';
	$params[] = $from_date;
}
if ($to_date) {
	$where[] = 'i.invoice_date <= ?';
	$params[] = $to_date;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Stats queries
$statWhere = [];
$statParams = [];
if ($from_date) { $statWhere[] = 'invoice_date >= ?'; $statParams[] = $from_date; }
if ($to_date) { $statWhere[] = 'invoice_date <= ?'; $statParams[] = $to_date; }
$statWhereSql = $statWhere ? 'WHERE ' . implode(' AND ', $statWhere) : '';

// Total invoiced
$stat = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as total_invoiced FROM invoices $statWhereSql");
$stat->execute($statParams);
$total_invoiced = $stat->fetchColumn();
// Total paid
$stat = $pdo->prepare("SELECT COALESCE(SUM(paid_amount),0) as total_paid FROM invoices $statWhereSql");
$stat->execute($statParams);
$total_paid = $stat->fetchColumn();
// Total unpaid
$stat = $pdo->prepare("SELECT COALESCE(SUM(total_amount - paid_amount),0) as total_unpaid FROM invoices $statWhereSql");
$stat->execute($statParams);
$total_unpaid = $stat->fetchColumn();
// Today's collection
$today = date('Y-m-d');
$todayWhere = $statWhere;
$todayParams = $statParams;
$todayWhere[] = 'paid_date = ?';
$todayParams[] = $today;
$stat = $pdo->prepare("SELECT COALESCE(SUM(paid_amount),0) FROM payments WHERE ".($from_date||$to_date?implode(' AND ',array_map(function($w){return str_replace('invoice_date','paid_date',$w);},$statWhere)).' AND ':'').'paid_date = ?');
$stat->execute([$today]);
$todays_collection = $stat->fetchColumn();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get customers with invoice/dues summary
$sql = "SELECT c.id, c.name, c.mobile, c.address,
	COUNT(i.id) as total_invoices,
	COALESCE(SUM(i.total_amount),0) as total_invoiced,
	COALESCE(SUM(i.paid_amount),0) as paid_till_date,
	COALESCE(SUM(i.total_amount),0) - COALESCE(SUM(i.paid_amount),0) as unpaid_dues
FROM customers c
LEFT JOIN invoices i ON i.customer_id = c.id
$whereSql
GROUP BY c.id
ORDER BY c.name ASC
LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// For pagination
$total = $pdo->prepare("SELECT COUNT(*) FROM customers c $whereSql");
$total->execute($params);
$totalRows = $total->fetchColumn();
$queryStr = http_build_query(array_diff_key($_GET, ['page' => '']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Customer Dues</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="../../assets/css/style.css">
	<style>
		.dues-container { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(128,0,0,0.07); padding:32px 24px; max-width:1100px; margin:32px auto; }
		.filter-bar { display:flex; gap:18px; flex-wrap:wrap; align-items:center; margin-bottom:22px; }
		.filter-bar input { padding:8px 10px; border:1px solid #ccc; border-radius:4px; }
		table { width:100%; border-collapse:collapse; margin-top:10px; }
		th, td { padding:12px 10px; border-bottom:1px solid #eee; text-align:left; }
		th { background:#faf6f6; color:#800000; font-weight:700; }
		tr:last-child td { border-bottom:none; }
		.action-btn { padding:7px 16px; border:none; border-radius:4px; font-weight:600; cursor:pointer; margin-right:6px; text-decoration:none; }
		.collect-btn { background:#007bff; color:#fff; }
		.paid-link { color:#28a745; cursor:pointer; text-decoration:underline; }
		.invoices-link { color:#800000; cursor:pointer; text-decoration:underline; }
		.pagination { margin-top:22px; text-align:right; }
		.pagination a { display:inline-block; padding:7px 14px; margin:0 2px; border-radius:4px; background:#f4f4f4; color:#800000; text-decoration:none; font-weight:600; }
		.pagination a.active, .pagination a:hover { background:#800000; color:#fff; }
	</style>
</head>
<body>
<div class="dues-container">
	<h1 style="margin-bottom:18px; color:#800000; font-size:1.5em;">Customer Dues</h1>
	<!-- Stats Cards -->
	<div style="display:flex; gap:18px; flex-wrap:wrap; margin-bottom:22px;">
		<div style="flex:1; min-width:180px; background:#f8f6f2; border-radius:8px; padding:18px 16px; box-shadow:0 1px 4px rgba(128,0,0,0.04);">
			<div style="color:#888; font-size:0.98em;">Total Invoiced</div>
			<div style="font-size:1.3em; color:#800000; font-weight:700;">₹<?= number_format($total_invoiced,2) ?></div>
		</div>
		<div style="flex:1; min-width:180px; background:#f8f6f2; border-radius:8px; padding:18px 16px; box-shadow:0 1px 4px rgba(128,0,0,0.04);">
			<div style="color:#888; font-size:0.98em;">Total Paid</div>
			<div style="font-size:1.3em; color:#28a745; font-weight:700;">₹<?= number_format($total_paid,2) ?></div>
		</div>
		<div style="flex:1; min-width:180px; background:#f8f6f2; border-radius:8px; padding:18px 16px; box-shadow:0 1px 4px rgba(128,0,0,0.04);">
			<div style="color:#888; font-size:0.98em;">Total Unpaid</div>
			<div style="font-size:1.3em; color:#b30000; font-weight:700;">₹<?= number_format($total_unpaid,2) ?></div>
		</div>
		<div style="flex:1; min-width:180px; background:#f8f6f2; border-radius:8px; padding:18px 16px; box-shadow:0 1px 4px rgba(128,0,0,0.04);">
			<div style="color:#888; font-size:0.98em;">Today's Collection</div>
			<div style="font-size:1.3em; color:#007bff; font-weight:700;">₹<?= number_format($todays_collection,2) ?></div>
		</div>
	</div>
	<form class="filter-bar" method="get">
		<input type="text" name="search" placeholder="Search customer, mobile, address..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="min-width:200px;">
		<label for="from_date">From:</label>
		<input type="date" name="from_date" id="from_date" value="<?= htmlspecialchars($from_date) ?>">
		<label for="to_date">To:</label>
		<input type="date" name="to_date" id="to_date" value="<?= htmlspecialchars($to_date) ?>">
		<button type="submit" class="action-btn collect-btn">Filter</button>
	</form>
	<table>
		<tr>
			<th>Customer Name</th>
			<th>Mobile</th>
			<th>Address</th>
			<th>Total Invoices</th>
			<th>Total Invoiced Amount</th>
			<th>Paid Till Date</th>
			<th>Unpaid Dues</th>
			<th>Action</th>
		</tr>
		<?php if (empty($rows)): ?>
			<tr><td colspan="8" style="text-align:center; color:#888;">No records found.</td></tr>
		<?php else: foreach ($rows as $row): ?>
			<tr>
				<td><?= htmlspecialchars($row['name']) ?></td>
				<td><?= htmlspecialchars($row['mobile']) ?></td>
				<td><?= htmlspecialchars($row['address']) ?></td>
				<td><a href="view-customer-invoices.php?id=<?= $row['id'] ?>" class="invoices-link"><?= $row['total_invoices'] ?></a></td>
				<td><a href="view-customer-invoices.php?id=<?= $row['id'] ?>" class="invoices-link">₹<?= number_format($row['total_invoiced'],2) ?></a></td>
				<td><a href="view-customer-payments.php?id=<?= $row['id'] ?>" class="paid-link">₹<?= number_format($row['paid_till_date'],2) ?></a></td>
				<td style="color:#b30000; font-weight:700;">₹<?= number_format($row['unpaid_dues'],2) ?></td>
				<td>
					<?php if ($row['unpaid_dues'] > 0): ?>
						<button type="button" class="action-btn collect-btn" onclick="openCollectModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>', <?= $row['unpaid_dues'] ?>)">Collect</button>
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

<!-- Collect Payment Modal -->
<div id="collectModalBg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:1000; align-items:center; justify-content:center;">
	<div id="collectModal" style="background:#fff; border-radius:8px; box-shadow:0 2px 16px rgba(0,0,0,0.13); padding:32px 28px 22px 28px; min-width:320px; max-width:95vw; width:370px;">
		<h2 style="margin-top:0; color:#800000; font-size:1.2em;">Collect Payment</h2>
		<form id="collectForm" autocomplete="off">
			<input type="hidden" name="customer_id" id="collectCustomerId">
			<div style="margin-bottom:10px;"><b>Customer:</b> <span id="collectCustomerName"></span></div>
			<div style="margin-bottom:10px;"><b>Due Amount:</b> ₹<span id="collectDueAmount"></span></div>
			<label>Amount to Collect</label>
			<input type="number" name="amount" id="collectAmount" min="1" step="0.01" required>
			<label>Note</label>
			<textarea name="note" id="collectNote" rows="2"></textarea>
			<label>Payment Method</label>
			<select name="pay_method" id="collectPayMethod" required>
				<option value="Cash">Cash</option>
				<option value="UPI">UPI</option>
				<option value="Bank">Bank</option>
				<option value="Other">Other</option>
			</select>
			<label>Date</label>
			<input type="date" name="pay_date" id="collectPayDate" value="<?= date('Y-m-d') ?>" required>
			<label>Transaction Details</label>
			<input type="text" name="transaction_details" id="collectTransactionDetails">
			<div style="margin-top:18px; text-align:right;">
				<button type="button" onclick="closeCollectModal()" style="background:#ccc; color:#333; border:none; border-radius:4px; padding:8px 16px;">Cancel</button>
				<button type="submit" style="background:#28a745; color:#fff; border:none; border-radius:4px; padding:8px 16px; font-weight:600;">Submit</button>
			</div>
			<div id="collectMsg" style="margin-top:8px; color:#800000; font-size:0.98em;"></div>
		</form>
	</div>
</div>
<script>
function openCollectModal(id, name, due) {
	document.getElementById('collectCustomerId').value = id;
	document.getElementById('collectCustomerName').textContent = name;
	document.getElementById('collectDueAmount').textContent = due;
	document.getElementById('collectAmount').value = due;
	document.getElementById('collectMsg').textContent = '';
	document.getElementById('collectModalBg').style.display = 'flex';
}
function closeCollectModal() {
	document.getElementById('collectModalBg').style.display = 'none';
}
document.getElementById('collectForm').addEventListener('submit', function(e) {
	e.preventDefault();
	var formData = new FormData(this);
	var msg = document.getElementById('collectMsg');
	msg.textContent = '';
	fetch('collect-payment.php', {
		method: 'POST',
		body: formData
	})
	.then(res => res.json())
	.then(data => {
		if (data.success) {
			msg.style.color = '#28a745';
			msg.textContent = 'Payment collected!';
			setTimeout(() => { location.reload(); }, 900);
		} else {
			msg.style.color = '#800000';
			msg.textContent = data.error || 'Failed to collect payment.';
		}
	})
	.catch(() => {
		msg.style.color = '#800000';
		msg.textContent = 'Failed to collect payment.';
	});
});
</script>
</body>
</html>
