<?php
// dues.php
session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: ../login.php');
	exit;
}
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
$todayWhere = [];
$todayParams = [];
if ($from_date) { $todayWhere[] = 'paid_date >= ?'; $todayParams[] = $from_date; }
if ($to_date) { $todayWhere[] = 'paid_date <= ?'; $todayParams[] = $to_date; }
$todayWhere[] = 'paid_date = ?';
$todayParams[] = $today;
$todayWhereSql = $todayWhere ? 'WHERE ' . implode(' AND ', $todayWhere) : '';
$stat = $pdo->prepare("SELECT COALESCE(SUM(paid_amount),0) FROM payments $todayWhereSql");
$stat->execute($todayParams);
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
ORDER BY ((COALESCE(SUM(i.total_amount),0) - COALESCE(SUM(i.paid_amount),0)) > 0) DESC, (COALESCE(SUM(i.total_amount),0) - COALESCE(SUM(i.paid_amount),0)) DESC, c.name ASC
LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Split into two arrays: with dues and zero dues
$with_dues = [];
$zero_dues = [];
foreach ($rows as $row) {
	if (floatval($row['unpaid_dues']) > 0) {
		$with_dues[] = $row;
	} else {
		$zero_dues[] = $row;
	}
}


$total = $pdo->prepare("SELECT COUNT(DISTINCT c.id) FROM customers c LEFT JOIN invoices i ON i.customer_id = c.id $whereSql");
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
	<link rel="stylesheet" href="../includes/responsive-tables.css">
	<style>
		body { margin: 0; background: #f7f7fa; font-family: 'Segoe UI', Arial, sans-serif; }
		.dues-container { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(128,0,0,0.07); padding:32px 24px; max-width:1400px; margin:32px auto; }
		h1 { color:#800000; margin-bottom:18px; font-size:1.5em; }
		.filter-bar { display:flex; gap:18px; flex-wrap:wrap; align-items:center; margin-bottom:22px; }
		.filter-bar input { padding:8px 10px; border:1px solid #ccc; border-radius:4px; }
		table { width:100%; border-collapse:collapse; margin-top:10px; table-layout: auto; font-size: 0.85em; }
		th, td { padding:8px 6px; border-bottom:1px solid #f3caca; text-align:left; white-space: nowrap; }
		th { background:#f9eaea; color:#800000; font-weight:600; font-size: 0.9em; }
		td { font-size: 0.95em; }
		tbody tr:hover { background: #f3f7fa; }
		td { font-size: 0.95em; }
		tr:last-child td { border-bottom:none; }
		tr:hover { background: #f9f9f9; }
		.action-btn { padding:6px 12px; border:none; border-radius:4px; font-weight:600; cursor:pointer; margin-right:6px; text-decoration:none; font-size: 0.85em; }
		.collect-btn { background:#007bff; color:#fff; }
		.paid-link { color:#28a745; cursor:pointer; text-decoration:underline; }
		.invoices-link { color:#800000; cursor:pointer; text-decoration:underline; }
		.pagination { margin-top:22px; text-align:right; }
		.pagination a { display:inline-block; padding:7px 14px; margin:0 2px; border-radius:4px; background:#f4f4f4; color:#800000; text-decoration:none; font-weight:600; }
		.pagination a.active, .pagination a:hover { background:#800000; color:#fff; }
		table th:nth-child(1), table td:nth-child(1) { width: 12%; }
		table th:nth-child(2), table td:nth-child(2) { width: 10%; }
		table th:nth-child(3), table td:nth-child(3) { width: 12%; max-width: 150px; white-space: normal; word-wrap: break-word; }
		table th:nth-child(4), table td:nth-child(4) { width: 8%; text-align: center; }
		table th:nth-child(5), table td:nth-child(5) { width: 10%; }
		table th:nth-child(6), table td:nth-child(6) { width: 10%; }
		table th:nth-child(7), table td:nth-child(7) { width: 10%; }
		table th:nth-child(8), table td:nth-child(8) { width: 10%; }
		table th:nth-child(9), table td:nth-child(9) { width: 18%; }
		@media (max-width: 1200px) {
			table { overflow-x: auto; display: block; }
		}
	</style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/top-menu.php'; ?>
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
		<input type="text" name="search" id="searchInput" placeholder="Search customer, mobile, address..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="min-width:200px;">
		<label for="from_date">From:</label>
		<input type="date" name="from_date" id="from_date" value="<?= htmlspecialchars($from_date) ?>">
		<label for="to_date">To:</label>
		<input type="date" name="to_date" id="to_date" value="<?= htmlspecialchars($to_date) ?>">
	</form>
	<div style="margin-bottom:18px; display:flex; gap:10px; align-items:center;">
		<button type="button" onclick="sendReminderToAll()" style="background:#25D366;color:#fff;border:none;border-radius:8px;padding:10px 20px;font-weight:600;cursor:pointer;font-size:0.98em;">
			<span class="remind-all-text">📲 Send Reminder to All</span>
		</button>
		<span id="remindAllStatus" style="font-size:0.9em; color:#888; display:none;"></span>
	</div>
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
			<th>MSG</th>
		</tr>
		<?php if (empty($with_dues)): ?>
			<tr><td colspan="9" style="text-align:center; color:#888;">No customers with dues.</td></tr>
		<?php else: foreach ($with_dues as $row): ?>
			<tr>
				<td><?= htmlspecialchars($row['name']) ?></td>
				<td><?= htmlspecialchars($row['mobile']) ?></td>
				<td><?= htmlspecialchars($row['address']) ?></td>
				<td><a href="view-customer-invoices.php?id=<?= $row['id'] ?>" class="invoices-link"><?= $row['total_invoices'] ?></a></td>
				<td><a href="view-customer-invoices.php?id=<?= $row['id'] ?>" class="invoices-link">₹<?= number_format($row['total_invoiced'],2) ?></a></td>
				<td><a href="view-customer-payments.php?id=<?= $row['id'] ?>" class="paid-link">₹<?= number_format($row['paid_till_date'],2) ?></a></td>
				<td style="color:#b30000; font-weight:700;">₹<?= number_format($row['unpaid_dues'],2) ?></td>
				<td>
					<button type="button" class="action-btn collect-btn" onclick="openCollectModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>', <?= $row['unpaid_dues'] ?>)">Collect</button>
				</td>
			<td style="display:flex; gap:6px; flex-wrap:wrap;">
				<button type="button" class="action-btn" style="background:#25D366;color:#fff;" onclick="sendDueReminder(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>', '<?= htmlspecialchars($row['mobile']) ?>', <?= $row['unpaid_dues'] ?>, this)">
					<span class="btn-text">Send Reminder</span>
				</button>
				<button type="button" class="action-btn" style="background:#007bff;color:#fff;" onclick="openCustomMsgModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>', '<?= htmlspecialchars($row['mobile']) ?>')">
					<span class="btn-text">Custom Msg</span>
				</button>
			</td>
			</tr>
		<?php endforeach; endif; ?>
	</table>

<!-- Table for customers with zero dues -->
<h2 style="margin-top:36px; color:#228B22; font-size:1.15em;">Customers with No Dues</h2>
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
	<?php if (empty($zero_dues)): ?>
		<tr><td colspan="8" style="text-align:center; color:#888;">No customers with zero dues.</td></tr>
	<?php else: foreach ($zero_dues as $row): ?>
		<tr>
			<td><?= htmlspecialchars($row['name']) ?></td>
			<td><?= htmlspecialchars($row['mobile']) ?></td>
			<td><?= htmlspecialchars($row['address']) ?></td>
			<td><a href="view-customer-invoices.php?id=<?= $row['id'] ?>" class="invoices-link"><?= $row['total_invoices'] ?></a></td>
			<td><a href="view-customer-invoices.php?id=<?= $row['id'] ?>" class="invoices-link">₹<?= number_format($row['total_invoiced'],2) ?></a></td>
			<td><a href="view-customer-payments.php?id=<?= $row['id'] ?>" class="paid-link">₹<?= number_format($row['paid_till_date'],2) ?></a></td>
			<td style="color:#228B22; font-weight:700;">₹0.00</td>
			<td></td>
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

<!-- Collect Payment Modal (Modern Design) -->
<div id="collectModalBg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:1000; align-items:center; justify-content:center;">
	<div id="collectModal" style="background:#fff; border-radius:12px; box-shadow:0 2px 16px #80000033; padding:32px 28px 18px 28px; min-width:340px; max-width:95vw; width:370px; text-align:left; position:relative;">
		<div style="font-size:1.18em;color:#800000;font-weight:700;margin-bottom:10px;">Collect Payment</div>
		<form id="collectForm" autocomplete="off">
			<input type="hidden" name="customer_id" id="collectCustomerId">
			<div style="margin-bottom:10px;color:#444;"><b>Customer:</b> <span id="collectCustomerName"></span></div>
			<div style="margin-bottom:10px;color:#444;"><b>Due Amount:</b> ₹<span id="collectDueAmount"></span></div>
			<div style="margin-bottom:10px;">Amount: <input type="number" name="amount" id="collectAmount" min="1" step="0.01" style="width:120px;padding:5px 8px;border-radius:6px;border:1px solid #ccc;" required></div>
			<div style="margin-bottom:10px;">Method: 
				<select name="pay_method" id="collectPayMethod" style="padding:5px 8px;border-radius:6px;border:1px solid #ccc;" required>
					<option value="Cash">Cash</option>
					<option value="UPI">UPI</option>
					<option value="Bank">Bank</option>
					<option value="Other">Other</option>
				</select>
			</div>
			<div style="margin-bottom:10px;">Date: <input type="date" name="pay_date" id="collectPayDate" value="<?= date('Y-m-d') ?>" style="padding:5px 8px;border-radius:6px;border:1px solid #ccc;" required></div>
			<div style="margin-bottom:10px;">Transaction/Ref: <input type="text" name="transaction_details" id="collectTransactionDetails" style="width:180px;padding:5px 8px;border-radius:6px;border:1px solid #ccc;"></div>
			<div style="margin-bottom:10px;">Note: <input type="text" name="note" id="collectNote" style="width:180px;padding:5px 8px;border-radius:6px;border:1px solid #ccc;"></div>
			<div style="margin-top:18px;text-align:center;">
				<button type="submit" style="background:#1a8917;color:#fff;padding:8px 24px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Collect & Mark Paid</button>
				&nbsp;
				<button type="button" onclick="closeCollectModal()" style="background:#800000;color:#fff;padding:8px 24px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
			</div>
			<div id="collectMsg" style="margin-top:10px; color:#c00; display:none;"></div>
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
</script>

<!-- Custom Message Modal -->
<div id="customMsgModalBg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:1000; align-items:center; justify-content:center;">
	<div id="customMsgModal" style="background:#fff; border-radius:12px; box-shadow:0 2px 16px #80000033; padding:32px 28px 18px 28px; min-width:340px; max-width:95vw; width:420px; text-align:left; position:relative;">
		<div style="font-size:1.18em;color:#007bff;font-weight:700;margin-bottom:10px;">Send Custom Message</div>
		<form id="customMsgForm" autocomplete="off">
			<input type="hidden" name="customer_id" id="customMsgCustomerId">
			<input type="hidden" name="customer_name" id="customMsgCustomerNameInput">
			<input type="hidden" name="mobile" id="customMsgCustomerMobileInput">
			<input type="hidden" name="action" value="custom_msg">
			<div style="margin-bottom:10px;color:#444;"><b>Customer:</b> <span id="customMsgCustomerName"></span></div>
			<div style="margin-bottom:10px;color:#444;"><b>Mobile:</b> <span id="customMsgCustomerMobile"></span></div>
			<div style="margin-bottom:10px;">
				<label for="customMsgText" style="display:block; margin-bottom:5px;"><b>Message:</b></label>
				<textarea name="message" id="customMsgText" style="width:100%;height:100px;padding:8px;border-radius:6px;border:1px solid #ccc;font-family:Arial,sans-serif;resize:vertical;" placeholder="Enter your custom message..." required></textarea>
			</div>
			<div style="margin-top:18px;text-align:center;">
				<button type="submit" style="background:#007bff;color:#fff;padding:8px 24px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Send Message</button>
				&nbsp;
				<button type="button" onclick="closeCustomMsgModal()" style="background:#800000;color:#fff;padding:8px 24px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
			</div>
			<div id="customMsgMsg" style="margin-top:10px; color:#c00; display:none;"></div>
		</form>
	</div>
</div>
<script>
function openCustomMsgModal(id, name, mobile) {
	document.getElementById('customMsgCustomerId').value = id;
	document.getElementById('customMsgCustomerName').textContent = name;
	document.getElementById('customMsgCustomerMobile').textContent = mobile;
	document.getElementById('customMsgCustomerNameInput').value = name;
	document.getElementById('customMsgCustomerMobileInput').value = mobile;
	document.getElementById('customMsgText').value = '';
	document.getElementById('customMsgMsg').textContent = '';
	document.getElementById('customMsgModalBg').style.display = 'flex';
}
function closeCustomMsgModal() {
	document.getElementById('customMsgModalBg').style.display = 'none';
}
</script>
<script>
document.getElementById('customMsgForm').addEventListener('submit', function(e) {
	e.preventDefault();
	var formData = new FormData(this);
	var msg = document.getElementById('customMsgMsg');
	var btn = document.querySelector('#customMsgForm button[type="submit"]');
	msg.textContent = '';
	btn.disabled = true;
	
	fetch('send_due_reminder.php', {
		method: 'POST',
		body: formData
	})
	.then(res => res.json())
	.then(data => {
		if (data.success) {
			msg.style.color = '#28a745';
			msg.style.display = 'block';
			msg.textContent = 'Message sent successfully!';
			setTimeout(() => { 
				closeCustomMsgModal();
				btn.disabled = false;
				alert('Custom message sent successfully');
			}, 900);
		} else {
			msg.style.color = '#800000';
			msg.style.display = 'block';
			msg.textContent = data.message || 'Failed to send message.';
			btn.disabled = false;
		}
	})
	.catch(() => {
		msg.style.color = '#800000';
		msg.style.display = 'block';
		msg.textContent = 'Failed to send message.';
		btn.disabled = false;
	});
});
</script>
<script>
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

// --- LIVE AJAX SEARCH/FILTER ---
const searchInput = document.getElementById('searchInput');
const fromDateInput = document.getElementById('from_date');
const toDateInput = document.getElementById('to_date');

function fetchDuesTable() {
	const search = searchInput.value;
	const from_date = fromDateInput.value;
	const to_date = toDateInput.value;
	const params = new URLSearchParams({search, from_date, to_date});
	fetch(window.location.pathname + '?' + params.toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
		.then(res => res.text())
		.then(html => {
			// Extract only the dues-container content
			const parser = new DOMParser();
			const doc = parser.parseFromString(html, 'text/html');
			const newContainer = doc.querySelector('.dues-container');
			if (newContainer) {
				document.querySelector('.dues-container').innerHTML = newContainer.innerHTML;
			}
		});
}

searchInput.addEventListener('input', fetchDuesTable);
fromDateInput.addEventListener('input', fetchDuesTable);
toDateInput.addEventListener('input', fetchDuesTable);

// Send Due Reminder via WhatsApp
function sendDueReminder(customerId, customerName, mobile, dueAmount, btn) {
	const btnText = btn.querySelector('.btn-text');
	const originalText = btnText.textContent;
	btnText.textContent = 'Sending...';
	btn.disabled = true;
	btn.style.opacity = '0.6';
	
	const formData = new FormData();
	formData.append('action', 'send_due_reminder');
	formData.append('customer_id', customerId);
	formData.append('customer_name', customerName);
	formData.append('mobile', mobile);
	formData.append('due_amount', dueAmount);
	
	fetch('send_due_reminder.php', {
		method: 'POST',
		body: formData
	})
	.then(res => res.json())
	.then(data => {
		if (data.success) {
			btnText.textContent = '✓ Sent';
			btn.style.background = '#1a8917';
			setTimeout(() => {
				btnText.textContent = originalText;
				btn.disabled = false;
				btn.style.opacity = '1';
				btn.style.background = '#25D366';
			}, 3000);
		} else {
			btnText.textContent = '✗ Failed';
			btn.style.background = '#c00';
			setTimeout(() => {
				btnText.textContent = originalText;
				btn.disabled = false;
				btn.style.opacity = '1';
				btn.style.background = '#25D366';
			}, 3000);
			alert(data.message || 'Failed to send reminder');
		}
	})
	.catch(err => {
		console.error(err);
		btnText.textContent = '✗ Error';
		btn.style.background = '#c00';
		setTimeout(() => {
			btnText.textContent = originalText;
			btn.disabled = false;
			btn.style.opacity = '1';
			btn.style.background = '#25D366';
		}, 3000);
		alert('Error sending reminder');
	});
}

// Send Due Reminder to All customers with dues
function sendReminderToAll() {
	if (!confirm('Are you sure you want to send reminder to all customers with dues? This may take a moment.')) {
		return;
	}
	
	const btn = event.target.closest('button');
	const btnText = btn.querySelector('.remind-all-text');
	const statusEl = document.getElementById('remindAllStatus');
	const originalText = btnText.textContent;
	
	btnText.textContent = '⏳ Sending to all...';
	statusEl.textContent = 'Processing...';
	statusEl.style.display = 'inline';
	btn.disabled = true;
	btn.style.opacity = '0.6';
	
	fetch('send_due_reminder.php', {
		method: 'POST',
		headers: {'Content-Type': 'application/x-www-form-urlencoded'},
		body: 'action=send_to_all'
	})
	.then(res => res.json())
	.then(data => {
		if (data.success) {
			btnText.textContent = '✓ Sent to All';
			statusEl.textContent = `Successfully sent to ${data.count} customer(s)`;
			statusEl.style.color = '#1a8917';
			btn.style.background = '#1a8917';
			setTimeout(() => {
				btnText.textContent = originalText;
				btn.disabled = false;
				btn.style.opacity = '1';
				btn.style.background = '#25D366';
				statusEl.style.display = 'none';
			}, 4000);
		} else {
			btnText.textContent = '✗ Failed';
			statusEl.textContent = data.message || 'Failed to send reminders';
			statusEl.style.color = '#c00';
			btn.style.background = '#c00';
			setTimeout(() => {
				btnText.textContent = originalText;
				btn.disabled = false;
				btn.style.opacity = '1';
				btn.style.background = '#25D366';
				statusEl.style.display = 'none';
			}, 4000);
		}
	})
	.catch(err => {
		console.error(err);
		btnText.textContent = '✗ Error';
		statusEl.textContent = 'Error processing request';
		statusEl.style.color = '#c00';
		btn.style.background = '#c00';
		setTimeout(() => {
			btnText.textContent = originalText;
			btn.disabled = false;
			btn.style.opacity = '1';
			btn.style.background = '#25D366';
			statusEl.style.display = 'none';
		}, 4000);
	});
}
</script>
<script src="../includes/responsive-tables.js"></script>
</body>
</html>
