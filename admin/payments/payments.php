<?php
// admin/payments/payments.php
// Sample Payments Page – Structure and Design Reference from Appointments
require_once __DIR__ . '/../../config/db.php';

// --- DATE FILTER LOGIC ---
$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-t');

// --- FILTERED STATS ---
$where = "payment_status = 'Paid' AND payment_id != '' AND payment_id IS NOT NULL AND tracking_id NOT LIKE 'SR%' AND DATE(created_at) BETWEEN :from AND :to";

// Number of payments
$stmt = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE $where");
$stmt->execute(['from' => $from, 'to' => $to]);
$totalPayments = (int)$stmt->fetchColumn();

// Total amount collected
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM service_requests WHERE $where");
$stmt->execute(['from' => $from, 'to' => $to]);
$totalAmount = (float)$stmt->fetchColumn();

// --- FILTERED DATA WITH SEARCH ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchSql = '';
$params = ['from' => $from, 'to' => $to];
if ($search !== '') {
    $searchSql = " AND (payment_id LIKE :q OR tracking_id LIKE :q OR customer_name LIKE :q OR mobile LIKE :q OR email LIKE :q OR service_status LIKE :q OR payment_status LIKE :q)";
    $params['q'] = "%$search%";
}
$stmt = $pdo->prepare("SELECT id, payment_id, tracking_id, customer_name, mobile, email, total_amount, payment_status, service_status, created_at FROM service_requests WHERE $where $searchSql ORDER BY created_at DESC LIMIT 100");
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payments Overview</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../../assets/css/style.css">
<style>
body { font-family: Arial, sans-serif; background: #f7f7fa; margin: 0; }
.admin-container { max-width: 1100px; margin: 0 auto; padding: 24px 12px; }
h1 { color: #800000; margin-bottom: 18px; }
.summary-cards { display: flex; gap: 18px; margin-bottom: 24px; flex-wrap: wrap; }
.summary-card { flex: 1 1 180px; background: #fffbe7; border-radius: 14px; padding: 16px; text-align: center; box-shadow: 0 2px 8px #e0bebe22; }
.summary-count { font-size: 2.2em; font-weight: 700; color: #800000; }
.summary-label { font-size: 1em; color: #444; }
.payments-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 12px #e0bebe22; border-radius: 12px; overflow: hidden; }
.payments-table th, .payments-table td { padding: 12px 10px; border-bottom: 1px solid #f3caca; text-align: left; }
.payments-table th { background: #f9eaea; color: #800000; }
.payments-table tbody tr:hover { background: #f3f7fa; }
.no-data { text-align: center; color: #777; padding: 24px; }
.filter-bar { display: flex; gap: 12px; margin-bottom: 20px; align-items: center; }
.filter-bar label { color: #444; }
.filter-bar input[type="date"], .filter-bar input[type="text"] { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
.filter-bar .btn-main { padding: 8px 16px; background: #800000; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
.filter-bar .btn-main:hover { background: #700000; }
@media (max-width: 700px) { .summary-cards { flex-direction: column; } }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
<h1>Payments Overview</h1>

<!-- Date Filter & Search Bar (AJAX) -->
<form id="paymentsFilterForm" class="filter-bar" onsubmit="return false;">
    <label for="from">From:
        <input type="date" id="from" name="from" value="<?= htmlspecialchars($from) ?>">
    </label>
    <label for="to">To:
        <input type="date" id="to" name="to" value="<?= htmlspecialchars($to) ?>">
    </label>
    <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search payments...">
    <button type="submit" class="btn-main">Search</button>
</form>
<div id="paymentsAjaxResult">
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-count"><?= $totalPayments ?></div>
            <div class="summary-label">Number of Payments</div>
        </div>
        <div class="summary-card">
            <div class="summary-count">₹<?= number_format($totalAmount,2) ?></div>
            <div class="summary-label">Total Amount Collected</div>
        </div>
    </div>
    <table class="payments-table">
        <thead>
            <tr>
                <th>Payment ID</th>
                <th>Tracking ID</th>
                <th>Customer Name</th>
                <th>Mobile</th>
                <th>Email</th>
                <th>Amount</th>
                <th>Payment Status</th>
                <th>Service Status</th>
                <th>Payment Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="9" class="no-data">No payments found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['payment_id'] ?? '') ?></td>
                        <td><?= htmlspecialchars($p['tracking_id']) ?></td>
                        <td><?= htmlspecialchars($p['customer_name']) ?></td>
                        <td><?= htmlspecialchars($p['mobile']) ?></td>
                        <td><?= htmlspecialchars($p['email']) ?></td>
                        <td>₹<?= number_format((float)($p['total_amount'] ?? 0),2) ?></td>
                        <td><span class="status-badge payment-paid">Paid</span></td>
                        <td><span class="status-badge status-<?= strtolower($p['service_status']) ?>"><?= htmlspecialchars($p['service_status']) ?></span></td>
                        <td><?= htmlspecialchars($p['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
function loadPaymentsAjax() {
    var data = $('#paymentsFilterForm').serialize();
    $.get(window.location.pathname, data + '&ajax=1', function(res) {
        var html = $(res).find('#paymentsAjaxResult').html();
        $('#paymentsAjaxResult').html(html);
    });
}
$('#paymentsFilterForm input, #paymentsFilterForm').on('input change', function() {
    loadPaymentsAjax();
});
$('#paymentsFilterForm').on('submit', function() {
    loadPaymentsAjax();
    return false;
});
</script>
</div>
</body>
</html>
