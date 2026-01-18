<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin – Service Requests</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="/assets/css/style.css">
<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f7f7fa;
    margin: 0;
}
.admin-container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 24px 12px;
}
h1 {
    color: #800000;
    margin-bottom: 18px;
}

/* SUMMARY CARDS */
.summary-cards {
    display: flex;
    gap: 18px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.summary-card {
    flex: 1 1 180px;
    background: #fffbe7;
    border-radius: 14px;
    padding: 16px;
    text-align: center;
    box-shadow: 0 2px 8px #e0bebe22;
}
.summary-count {
    font-size: 2.2em;
    font-weight: 700;
    color: #800000;
}
.summary-label {
    font-size: 1em;
    color: #444;
}

/* FILTER BAR */
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
}
.filter-bar label {
    font-weight: 600;
}
.filter-bar select,
.filter-bar button {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 1em;
}
.filter-bar button {
    background: #800000;
    color: #fff;
    border: none;
    cursor: pointer;
}


/* TABLE */
.service-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    box-shadow: 0 2px 12px #e0bebe22;
    border-radius: 12px;
    overflow: hidden;
}
.service-table th,
.service-table td {
    padding: 12px 10px;
    border-bottom: 1px solid #f3caca;
    text-align: left;
}
.service-table th {
    background: #f9eaea;
    color: #800000;
}
.service-table tbody tr:hover {
    background: #f3f7fa;
    cursor: pointer;
}
.status-badge {
    padding: 4px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9em;
    display: inline-block;
    min-width: 80px;
    text-align: center;
}
/* Service Status Colors */
.status-received { background: #e5f0ff; color: #0056b3; }
.status-in-progress { background: #fffbe5; color: #b36b00; }
.status-completed { background: #e5ffe5; color: #1a8917; }
.status-cancelled { background: #ffeaea; color: #c00; }
/* Payment Status Colors */
.payment-paid { background: #e5ffe5; color: #1a8917; }
.payment-pending { background: #f7f7f7; color: #b36b00; }
.payment-failed { background: #ffeaea; color: #c00; }

.view-btn {
    background: #800000;
    color: #fff;
    padding: 6px 14px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
}

.no-data {
    text-align: center;
    color: #777;
    padding: 24px;
}

/* PAGINATION */
.pagination {
    display: flex;
    gap: 8px;
    justify-content: center;
    margin: 18px 0;
    flex-wrap: wrap;
}

.pagination a,
.pagination span {
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
    background: #fff;
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    color: #333;
}

.pagination .page-link.current {
    background: #800000;
    color: #fff;
    border-color: #800000;
}

.pagination .page-link.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* Popup overlay and box for Unpaid button */
#payPopupOverlay { position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.35);z-index:9999;display:flex;align-items:center;justify-content:center; }
#payPopupOverlay .pay-popup-box { background:#fff;padding:32px 28px;border-radius:12px;box-shadow:0 2px 16px #80000033;min-width:320px;max-width:90vw;text-align:center;position:relative; }
#payPopupOverlay .pay-popup-close { background:#800000;color:#fff;padding:8px 24px;border:none;border-radius:8px;font-weight:600;cursor:pointer; }

@media (max-width: 700px) {
    .summary-cards {
        flex-direction: column;
    }
}
</style>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<?php
require_once __DIR__ . '/../../config/db.php';

/* ==============================
   SUMMARY COUNTS
   VISIBILITY CONTROL: Exclude appointment records from dashboard stats
   Appointments are managed separately in appointmentmanagement.php
   Filter: category_slug != 'appointment' excludes all appointment data
============================== */
$todayCount = $pdo->query(
    "SELECT COUNT(*) FROM service_requests WHERE DATE(created_at) = CURDATE() AND category_slug != 'appointment'"
)->fetchColumn();

$receivedCount = $pdo->query(
    "SELECT COUNT(*) FROM service_requests WHERE service_status = 'Received' AND category_slug != 'appointment'"
)->fetchColumn();

$inProgressCount = $pdo->query(
    "SELECT COUNT(*) FROM service_requests WHERE service_status = 'In Progress' AND category_slug != 'appointment'"
)->fetchColumn();

$completedCount = $pdo->query(
    "SELECT COUNT(*) FROM service_requests WHERE service_status = 'Completed' AND category_slug != 'appointment'"
)->fetchColumn();

// Stats counters for online and offline service requests (VDSK for online, SR for offline)
$onlineCount = $pdo->query("SELECT COUNT(*) FROM service_requests WHERE tracking_id LIKE 'VDSK%' AND category_slug != 'appointment'")->fetchColumn();
$offlineCount = $pdo->query("SELECT COUNT(*) FROM service_requests WHERE tracking_id LIKE 'SR%' AND category_slug != 'appointment'")->fetchColumn();

/* ==============================
   FILTERS
============================== */
$statusOptions = ['All', 'Received', 'In Progress', 'Completed'];
$categoryOptions = [
    'All' => 'All Categories',
    'birth-child' => 'Birth & Child Services',
    'marriage-matching' => 'Marriage & Matching',
    'astrology-consultation' => 'Astrology Consultation',
    'muhurat-event' => 'Muhurat & Event Guidance',
    'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
];


$selectedStatus   = $_GET['status']   ?? 'All';
$selectedCategory = $_GET['category'] ?? 'All';
$search           = trim($_GET['search'] ?? '');


$where  = [];
$params = [];

// Pagination setup
$perPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;


if ($selectedStatus !== 'All') {
    $where[]  = 'service_status = ?';
    $params[] = $selectedStatus;
}
if ($selectedCategory !== 'All') {
    $where[]  = 'category_slug = ?';
    $params[] = $selectedCategory;
} else {
    // VISIBILITY CONTROL: When viewing "All Categories", explicitly exclude appointments
    // Appointments with category_slug = 'appointment' are now stored in service_requests table
    // but must be hidden from this generic service list and managed only in appointmentmanagement.php
    // This filter ensures they don't appear in the main services list or search results
    $where[] = "category_slug != 'appointment'";
}
if ($search !== '') {
    $where[] = '(tracking_id LIKE ? OR mobile LIKE ? OR customer_name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// PHP: Add logic to filter requests by tracking_id prefix (VDSK for online, SR for offline)
$requestType = $_GET['request_type'] ?? 'all';
if ($requestType === 'online') {
    $where[] = "(tracking_id LIKE 'VDSK%')";
} elseif ($requestType === 'offline') {
    $where[] = "(tracking_id LIKE 'SR%')";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM service_requests $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $perPage));

// Main paginated query
$sql = "
    SELECT id, tracking_id, customer_name, mobile, category_slug,
           total_amount, payment_status, service_status, created_at, selected_products
    FROM service_requests
    $whereSql
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Service Requests</h1>


<!-- SUMMARY CARDS -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-count"><?= $todayCount ?></div>
        <div class="summary-label">Today's Requests</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?= $onlineCount ?></div>
        <div class="summary-label">Online Requests</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?= $offlineCount ?></div>
        <div class="summary-label">Offline Requests</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?= $receivedCount ?></div>
        <div class="summary-label">Pending</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?= $inProgressCount ?></div>
        <div class="summary-label">In Progress</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?= $completedCount ?></div>
        <div class="summary-label">Completed</div>
    </div>
</div>

<!-- FILTERS -->
<form class="filter-bar" method="get">
    <label>Request Type</label>
    <select name="request_type" id="requestTypeSelect">
        <option value="all" <?= ($requestType === 'all') ? 'selected' : '' ?>>All</option>
        <option value="online" <?= ($requestType === 'online') ? 'selected' : '' ?>>Online</option>
        <option value="offline" <?= ($requestType === 'offline') ? 'selected' : '' ?>>Offline</option>
    </select>

    <label>Category</label>
    <select name="category">
        <?php foreach ($categoryOptions as $k => $v): ?>
            <option value="<?= $k ?>" <?= $selectedCategory === $k ? 'selected' : '' ?>>
                <?= $v ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Status</label>
    <select name="status">
        <?php foreach ($statusOptions as $s): ?>
            <option value="<?= $s ?>" <?= $selectedStatus === $s ? 'selected' : '' ?>>
                <?= $s ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by Tracking ID, Mobile, or Customer Name" style="min-width:200px;" />
    <button type="submit">Apply</button>
</form>


<!-- LEGEND -->


<!-- TABLE -->

<table class="service-table">
<thead>
<tr>
    <th>Tracking ID</th>
    <th>Customer</th>
    <th>Mobile</th>
    <th>Product(s)</th>
    <th>Category</th>
    <th>Amount</th>
    <th>Payment</th>
    <th>Status</th>
    <th>Date</th>
    <th>Action</th>
</tr>
</thead>
<tbody id="serviceTableBody">
<?php if (!$requests): ?>
<tr>
    <td colspan="10" class="no-data">No service requests found.</td>
</tr>
<?php else: ?>
<?php foreach ($requests as $row): ?>
<tr>
    <td><?= htmlspecialchars($row['tracking_id']) ?></td>
    <td><?= htmlspecialchars($row['customer_name']) ?></td>
    <td><?= htmlspecialchars($row['mobile']) ?></td>
    <td>
        <?php
        $products = '-';
        $decoded = json_decode($row['selected_products'], true);
        if (is_array($decoded) && count($decoded)) {
            $names = [];
            foreach ($decoded as $prod) {
                if (isset($prod['id'])) {
                    // Fetch product name from DB (cache for performance)
                    static $productNameCache = [];
                    $pid = (int)$prod['id'];
                    if (!isset($productNameCache[$pid])) {
                        $pstmt = $pdo->prepare('SELECT product_name FROM products WHERE id = ?');
                        $pstmt->execute([$pid]);
                        $prow = $pstmt->fetch();
                        $productNameCache[$pid] = $prow ? $prow['product_name'] : 'Product#'.$pid;
                    }
                    $names[] = htmlspecialchars($productNameCache[$pid]);
                }
            }
            if ($names) {
                $products = implode(', ', $names);
            }
        }
        echo $products;
        ?>
    </td>
    <td>
        <?php
        $catMap = [
            'birth-child' => 'Birth & Child Services',
            'marriage-matching' => 'Marriage & Matching',
            'astrology-consultation' => 'Astrology Consultation',
            'muhurat-event' => 'Muhurat & Event Guidance',
            'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
        ];
        $catSlug = $row['category_slug'];
        echo isset($catMap[$catSlug]) ? htmlspecialchars($catMap[$catSlug]) : htmlspecialchars($catSlug);
        ?>
    </td>
    <td>₹<?= number_format($row['total_amount'], 2) ?></td>
    <td>
        <?php
        $payClass = 'payment-' . strtolower(str_replace(' ', '-', $row['payment_status']));
        $isOffline = !empty($row['selected_products']);
        // Use tracking_id prefix to determine online/offline (VDSK for online, SR for offline)
        $trackingId = $row['tracking_id'];
        if (strpos($trackingId, 'SR') === 0) {
            // Offline: Always show Unpaid in red
            echo '<button class="btn-pay" data-id="'.(int)$row['id'].'" style="background:#c00;color:#fff;padding:6px 14px;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Unpaid</button>';
        } elseif (strpos($trackingId, 'VDSK') === 0) {
            // Online: Always show Paid in green
            echo '<span class="status-badge payment-paid" style="background:#e5ffe5;color:#1a8917;">Paid</span>';
        } else {
            // Unknown/other: show as blank or custom
            echo '<span class="status-badge" style="background:#eee;color:#888;">-</span>';
        }
        ?>
    </td>
    <td>
        <?php
        $statusClass = 'status-' . strtolower(str_replace(' ', '-', $row['service_status']));
        ?>
        <span class="status-badge <?= $statusClass ?>">
            <?= htmlspecialchars($row['service_status']) ?>
        </span>
    </td>
    <td><?= date('d-m-Y', strtotime($row['created_at'])) ?></td>
    <td><a class="view-btn" href="view.php?id=<?= $row['id'] ?>">View</a></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>

<!-- Static Pagination Sample -->

<div id="pagination" class="pagination" style="margin-top:24px;"></div>

<script>
function loadServicePage(page = 1) {
    const form = document.querySelector('.filter-bar');
    const tableBody = document.getElementById('serviceTableBody');
    const paginationContainer = document.getElementById('pagination');
    const fd = new FormData(form);
    const params = new URLSearchParams(fd.entries());
    params.set('page', page);
    fetch('ajax_service_pagination.php?' + params.toString())
        .then(r => r.text())
        .then(html => {
            // Extract pagination info from script
            let match = html.match(/<script>window\.ajaxPagination\s*=\s*({[\s\S]*?})<\/script>/);
            let pagination = { currentPage: 1, totalPages: 1 };
            if (match && match[1]) {
                try {
                    pagination = Function('return ' + match[1])();
                } catch (e) {}
                html = html.replace(match[0], '');
            }
            tableBody.innerHTML = html;
            renderPagination(pagination.currentPage, pagination.totalPages);
        });
}

function renderPagination(currentPage, totalPages) {
    const paginationContainer = document.getElementById('pagination');
    let html = '';
    currentPage = parseInt(currentPage);
    totalPages = parseInt(totalPages);
    if (totalPages <= 1) {
        paginationContainer.innerHTML = '';
        return;
    }
    // Prev
    if (currentPage > 1) {
        html += '<a href="#" class="page-link" data-page="' + (currentPage - 1) + '">&laquo; Previous</a>';
    } else {
        html += '<span class="page-link disabled">&laquo; Previous</span>';
    }
    // Page numbers (show up to 5 pages)
    let start = Math.max(1, currentPage - 2);
    let end = Math.min(totalPages, start + 4);
    if (end - start < 4) start = Math.max(1, end - 4);
    for (let i = start; i <= end; i++) {
        if (i === currentPage) {
            html += '<span class="page-link current">' + i + '</span>';
        } else {
            html += '<a href="#" class="page-link" data-page="' + i + '">' + i + '</a>';
        }
    }
    // Next
    if (currentPage < totalPages) {
        html += '<a href="#" class="page-link" data-page="' + (currentPage + 1) + '">Next &raquo;</a>';
    } else {
        html += '<span class="page-link disabled">Next &raquo;</span>';
    }
    paginationContainer.innerHTML = html;
    paginationContainer.querySelectorAll('.page-link[data-page]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const page = parseInt(link.getAttribute('data-page'));
            loadServicePage(page);
        });
    });
}

// Initial load
document.addEventListener('DOMContentLoaded', function() {
    loadServicePage(1);
    // Filter/search submit triggers reload
    document.querySelector('.filter-bar').addEventListener('submit', function(e) {
        e.preventDefault();
        loadServicePage(1);
    });
});



<script>
$(document).on('click', '.btn-pay', function(e){
    e.preventDefault();
    var id = $(this).data('id');
    // Simple popup for Unpaid
    var popup = $('<div id="payPopupOverlay" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.35);z-index:9999;display:flex;align-items:center;justify-content:center;"></div>');
    var box = $('<div style="background:#fff;padding:32px 28px;border-radius:12px;box-shadow:0 2px 16px #80000033;min-width:320px;max-width:90vw;text-align:center;position:relative;">'
        +'<div style="font-size:1.15em;color:#c00;font-weight:600;margin-bottom:12px;">This service request is currently unpaid.</div>'
        +'<div style="color:#555;margin-bottom:18px;">Please collect payment offline and update the status accordingly in the system.</div>'
        +'<button id="closePayPopup" style="background:#800000;color:#fff;padding:8px 24px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Close</button>'
        +'</div>');
    popup.append(box);
    $('body').append(popup);
    $('#closePayPopup').on('click', function(){ $('#payPopupOverlay').remove(); });
    popup.on('click', function(e){ if(e.target === this) $(this).remove(); });
});
</script>
<script>
document.getElementById('requestTypeSelect').addEventListener('change', function() {
    const url = new URL(window.location.href);
    url.searchParams.set('request_type', this.value);
    window.location.href = url.toString();
});
</script>
