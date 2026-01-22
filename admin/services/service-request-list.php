<?php
// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Service Request List</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
            .filter-bar {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                margin-bottom: 18px;
                align-items: center;
            }
            .filter-bar label {
                font-weight: 600;
                color: #800000;
                margin-right: 2px;
                font-size: 1em;
            }
            .filter-bar select,
            .filter-bar input[type="text"],
            .filter-bar button {
                padding: 7px 12px;
                border-radius: 6px;
                font-size: 1em;
                border: 1px solid #e0bebe;
                background: #fff;
                margin-right: 2px;
            }
            .filter-bar input[type="text"] {
                min-width: 200px;
                border: 1.5px solid #e0bebe;
                background: #fffdfa;
                color: #800000;
                transition: border 0.2s;
            }
            .filter-bar input[type="text"]:focus {
                border: 1.5px solid #800000;
                outline: none;
                background: #fff7f0;
            }
            .filter-bar select {
                background: #fffdfa;
                color: #800000;
                border: 1.5px solid #e0bebe;
                transition: border 0.2s;
            }
            .filter-bar select:focus {
                border: 1.5px solid #800000;
                outline: none;
            }
            .filter-bar button {
                background: #800000;
                color: #fff;
                border: none;
                cursor: pointer;
                font-weight: 600;
                transition: background 0.2s;
            }
            .filter-bar button:hover {
                background: #a00000;
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
    body {
        font-family: 'Segoe UI', Arial, sans-serif;
        background: #f7f7fa;
        margin: 0;
    }
    .admin-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 28px 16px 32px 16px;
    }
    h1 {
        color: #800000;
        margin-bottom: 22px;
        font-size: 2.1em;
        letter-spacing: 0.5px;
        font-family: 'Segoe UI Semibold', Arial, sans-serif;
    }
    .summary-cards {
        display: flex;
        gap: 18px;
        margin-bottom: 28px;
        flex-wrap: wrap;
    }
    .summary-card {
        flex: 1 1 180px;
        background: #fffbe7;
        border-radius: 14px;
        padding: 18px 0 14px 0;
        text-align: center;
        box-shadow: 0 2px 8px #e0bebe22;
        transition: box-shadow 0.25s ease;
    }
    .summary-card:hover {
        box-shadow: 0 4px 12px #e0bebe33;
    }
    .summary-count {
        font-size: 2.3em;
        font-weight: 700;
        color: #800000;
        margin-bottom: 4px;
        font-family: 'Segoe UI Bold', Arial, sans-serif;
    }
    .summary-label {
        font-size: 1.05em;
        color: #444;
        letter-spacing: 0.2px;
    }
    .service-table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        box-shadow: 0 2px 12px #e0bebe22;
        border-radius: 12px;
        margin-bottom: 18px;
        table-layout: auto;
        font-size: 0.85em;
    }
    .service-table th, .service-table td {
        padding: 8px 6px;
        border-bottom: 1px solid #f3caca;
        text-align: left;
        white-space: nowrap;
    }
    .service-table th {
        background: #f9eaea;
        color: #800000;
        font-weight: 600;
        letter-spacing: 0.3px;
        font-size: 0.9em;
    }
    .service-table td {
        font-size: 0.95em;
    }
    .service-table tbody tr:hover {
        background: #f3f7fa;
        cursor: pointer;
    }
    .service-table th:nth-child(1), .service-table td:nth-child(1) { width: 8%; }
    .service-table th:nth-child(2), .service-table td:nth-child(2) { width: 10%; }
    .service-table th:nth-child(3), .service-table td:nth-child(3) { width: 10%; }
    .service-table th:nth-child(4), .service-table td:nth-child(4) { width: 9%; }
    .service-table th:nth-child(5), .service-table td:nth-child(5) { width: 15%; max-width: 180px; white-space: normal; word-wrap: break-word; }
    .service-table th:nth-child(6), .service-table td:nth-child(6) { width: 12%; max-width: 120px; white-space: normal; word-wrap: break-word; }
    .service-table th:nth-child(7), .service-table td:nth-child(7) { width: 8%; }
    .service-table th:nth-child(8), .service-table td:nth-child(8) { width: 8%; }
    .service-table th:nth-child(9), .service-table td:nth-child(9) { width: 8%; }
    .service-table th:nth-child(10), .service-table td:nth-child(10) { width: 8%; }
    .service-table th:nth-child(11), .service-table td:nth-child(11) { width: 9%; }
    .status-badge {
        padding: 4px 10px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.9em;
        display: inline-block;
        min-width: 70px;
        text-align: center;
    }
    .view-btn {
        background: #800000;
        color: #fff;
        padding: 6px 12px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9em;
        transition: background 0.2s;
        white-space: nowrap;
    }
    .view-btn:hover {
        background: #a00000;
    }
    .no-data {
        text-align: center;
        color: #777;
        padding: 28px;
        font-size: 1.1em;
    }
    .pagination {
        display: flex;
        gap: 8px;
        justify-content: center;
        margin: 22px 0 0 0;
        flex-wrap: wrap;
    }
    .pagination a, .pagination span {
        padding: 7px 14px;
        border-radius: 6px;
        border: 1px solid #ccc;
        background: #fff;
        cursor: pointer;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        color: #333;
        font-size: 1em;
        transition: background 0.2s, color 0.2s;
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
    @media (max-width: 768px) {
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 10px;
            margin-bottom: 16px;
        }
        .summary-card {
            flex: 1 1 auto;
            padding: 12px 10px;
            border-radius: 10px;
            min-width: 0;
        }
        .summary-count {
            font-size: 1.6em;
            margin-bottom: 3px;
        }
        .summary-label {
            font-size: 0.9em;
        }
        .admin-container { padding: 12px 2vw; }
        .service-table th, .service-table td { padding: 10px 6px; font-size: 0.97em; }
    }
    @media (max-width: 600px) {
        .summary-cards {
            gap: 8px;
            margin-bottom: 12px;
        }
        .summary-card {
            padding: 10px 8px;
            border-radius: 8px;
        }
        .summary-count {
            font-size: 1.4em;
            margin-bottom: 2px;
        }
        .summary-label {
            font-size: 0.85em;
            line-height: 1.3;
        }
    }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<?php
require_once __DIR__ . '/../../config/db.php';
// Dynamic summary counts (same as index.php)
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
$onlineCount = $pdo->query("SELECT COUNT(*) FROM service_requests WHERE tracking_id LIKE 'VDSK%' AND category_slug != 'appointment'")->fetchColumn();
$offlineCount = $pdo->query("SELECT COUNT(*) FROM service_requests WHERE tracking_id LIKE 'SR%' AND category_slug != 'appointment'")->fetchColumn();
include __DIR__ . '/../includes/top-menu.php';
?>
<div class="admin-container">
    <h1>Service Request List</h1>
    <form class="filter-bar" method="get" style="margin-bottom:22px;">
        <label>Request Type</label>
        <select name="request_type" id="requestTypeSelect">
            <option value="all" <?= (($_GET['request_type'] ?? 'all') === 'all') ? 'selected' : '' ?>>All</option>
            <option value="online" <?= (($_GET['request_type'] ?? 'all') === 'online') ? 'selected' : '' ?>>Online</option>
            <option value="offline" <?= (($_GET['request_type'] ?? 'all') === 'offline') ? 'selected' : '' ?>>Offline</option>
        </select>
        <label>Category</label>
        <select name="category" id="categorySelect">
            <?php
            $categoryOptions = [
                'All' => 'All Categories',
                'birth-child' => 'Birth & Child Services',
                'marriage-matching' => 'Marriage & Matching',
                'astrology-consultation' => 'Astrology Consultation',
                'muhurat-event' => 'Muhurat & Event Guidance',
                'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
            ];
            $selectedCategory = $_GET['category'] ?? 'All';
            foreach ($categoryOptions as $k => $v): ?>
                <option value="<?= $k ?>" <?= $selectedCategory === $k ? 'selected' : '' ?>>
                    <?= $v ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label>Status</label>
        <select name="status" id="statusSelect">
            <?php
            $statusOptions = ['All', 'Received', 'In Progress', 'Completed'];
            $selectedStatus = $_GET['status'] ?? 'All';
            foreach ($statusOptions as $s): ?>
                <option value="<?= $s ?>" <?= $selectedStatus === $s ? 'selected' : '' ?>>
                    <?= $s ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search by Tracking ID, Mobile, or Customer Name" style="min-width:200px;" />
    </form>
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
    <?php
    // Pagination logic
    $perPage = 10;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $perPage;
    $where = ["category_slug != 'appointment'"];
    $params = [];
    $requestType = $_GET['request_type'] ?? 'all';
    if ($requestType === 'online') {
        $where[] = "tracking_id LIKE 'VDSK%'";
    } elseif ($requestType === 'offline') {
        $where[] = "tracking_id LIKE 'SR%'";
    }
    $selectedCategory = $_GET['category'] ?? 'All';
    if ($selectedCategory !== 'All') {
        $where[] = 'category_slug = ?';
        $params[] = $selectedCategory;
    }
    $selectedStatus = $_GET['status'] ?? 'All';
    if ($selectedStatus !== 'All') {
        $where[] = 'service_status = ?';
        $params[] = $selectedStatus;
    }
    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $where[] = '(tracking_id LIKE ? OR mobile LIKE ? OR customer_name LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $countSql = "SELECT COUNT(*) FROM service_requests $whereSql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalRecords / $perPage));
    $sql = "SELECT id, tracking_id, customer_name, mobile, category_slug, total_amount, discount, payment_status, service_status, created_at, selected_products FROM service_requests $whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <table class="service-table">
        <thead>
            <tr>
                <th>Action</th>
                <th>Tracking ID</th>
                <th>Customer</th>
                <th>Mobile</th>
                <th>Product(s)</th>
                <th>Category</th>
                <th>Amount</th>
                <th>Collected</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody id="serviceTableBody">
        <?php if (!$requests): ?>
            <tr><td colspan="11" class="no-data">No service requests found.</td></tr>
        <?php else: ?>
            <?php foreach ($requests as $row): ?>
            <tr>
                <td><a class="view-btn" href="view.php?id=<?= $row['id'] ?>">View</a></td>
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
                    $discount = isset($row['discount']) ? (float)$row['discount'] : 0;
                    $collected = $row['total_amount'] - $discount;
                    echo '₹' . number_format($collected, 2);
                    ?>
                </td>
                <td>
                    <?php
                    $payClass = 'payment-' . strtolower(str_replace(' ', '-', $row['payment_status']));
                    $trackingId = $row['tracking_id'];
                    if (strtolower($row['payment_status']) === 'paid') {
                        echo '<span class="status-badge payment-paid" style="background:#e5ffe5;color:#1a8917;">Paid</span>';
                    } elseif (strpos($trackingId, 'SR') === 0) {
                        echo '<button class="btn-pay" data-id="'.(int)$row['id'].'" style="background:#c00;color:#fff;padding:6px 14px;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Unpaid</button>';
                    } elseif (strpos($trackingId, 'VDSK') === 0) {
                        echo '<span class="status-badge payment-paid" style="background:#e5ffe5;color:#1a8917;">Paid</span>';
                    } else {
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
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    <div id="pagination" class="pagination" style="margin-top:24px;">
        <?php
        // Always show pagination bar, even for 1 page
        // Prev
        if ($page > 1) {
            echo '<a href="?page=' . ($page - 1) . '" class="page-link">&laquo; Previous</a>';
        } else {
            echo '<span class="page-link disabled">&laquo; Previous</span>';
        }
        // Page numbers (show up to 5 pages)
        $start = max(1, $page - 2);
        $end = min($totalPages, $start + 4);
        if ($end - $start < 4) $start = max(1, $end - 4);
        for ($i = $start; $i <= $end; $i++) {
            if ($i == $page) {
                echo '<span class="page-link current">' . $i . '</span>';
            } else {
                echo '<a href="?page=' . $i . '" class="page-link">' . $i . '</a>';
            }
        }
        // Next
        if ($page < $totalPages) {
            echo '<a href="?page=' . ($page + 1) . '" class="page-link">Next &raquo;</a>';
        } else {
            echo '<span class="page-link disabled">Next &raquo;</span>';
        }
        ?>
    </div>
</div>
<script>
// Debounce function for instant search
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

const searchInput = document.querySelector('input[name="search"]');
if (searchInput) {
    let lastValue = searchInput.value;
    searchInput.addEventListener('input', debounce(function(e) {
        const value = this.value;
        if (value === lastValue) return;
        lastValue = value;
        const form = this.form;
        const params = new URLSearchParams();
        for (const el of form.elements) {
            if (!el.name) continue;
            if (el.type === 'submit') continue;
            if (el === this) {
                params.set(el.name, value);
            } else {
                params.set(el.name, el.value);
            }
        }
        params.set('page', '1');
        window.location.search = params.toString();
    }, 400));
    // Prevent form submit on Enter for search
    searchInput.form.addEventListener('submit', function(e) {
        e.preventDefault();
    });
}
document.getElementById('requestTypeSelect').addEventListener('change', function() {
    // Preserve all current filters when changing request type
    const form = this.form;
    const params = new URLSearchParams();
    for (const el of form.elements) {
        if (!el.name) continue;
        if (el.type === 'submit') continue;
        if (el === this) {
            params.set(el.name, this.value);
        } else {
            params.set(el.name, el.value);
        }
    }
    params.set('page', '1');
    window.location.search = params.toString();
});
document.getElementById('categorySelect').addEventListener('change', function() {
    const form = this.form;
    const params = new URLSearchParams();
    for (const el of form.elements) {
        if (!el.name) continue;
        if (el.type === 'submit') continue;
        if (el === this) {
            params.set(el.name, this.value);
        } else {
            params.set(el.name, el.value);
        }
    }
    params.set('page', '1');
    window.location.search = params.toString();
});
document.getElementById('statusSelect').addEventListener('change', function() {
    const form = this.form;
    const params = new URLSearchParams();
    for (const el of form.elements) {
        if (!el.name) continue;
        if (el.type === 'submit') continue;
        if (el === this) {
            params.set(el.name, this.value);
        } else {
            params.set(el.name, el.value);
        }
    }
    params.set('page', '1');
    window.location.search = params.toString();
});
$(document).on('click', '.btn-pay', function(e){
    e.preventDefault();
    var id = $(this).data('id');
    var row = $(this).closest('tr');
    // Fetch amount and customer name from row
    var amount = row.find('td:nth-child(6)').text().replace(/[^\d.]/g, '');
    var customer = row.find('td:nth-child(2)').text();
    var popup = $('<div id="payPopupOverlay" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.35);z-index:9999;display:flex;align-items:center;justify-content:center;"></div>');
    var box = $('<div style="background:#fff;padding:32px 28px 18px 28px;border-radius:12px;box-shadow:0 2px 16px #80000033;min-width:340px;max-width:95vw;text-align:left;position:relative;">'
        +'<div style="font-size:1.18em;color:#800000;font-weight:700;margin-bottom:10px;">Collect Payment</div>'
        +'<div style="margin-bottom:10px;color:#444;">Customer: <b>'+customer+'</b></div>'
        +'<form id="collectServicePaymentForm">'
            +'<input type="hidden" name="service_request_id" value="'+id+'">'
            +'<div style="margin-bottom:10px;">Amount: <input type="number" id="origAmount" value="'+amount+'" min="1" step="0.01" style="width:120px;padding:5px 8px;border-radius:6px;border:1px solid #ccc;" readonly></div>'
            +'<div style="margin-bottom:10px;">Discount: <input type="number" id="discountInput" name="discount" value="0" min="0" step="0.01" style="width:120px;padding:5px 8px;border-radius:6px;border:1px solid #ccc;" placeholder="Discount"></div>'
            +'<div style="margin-bottom:10px;">Amount to be Collected: <input type="number" id="amountCollected" name="amount" value="'+amount+'" min="0" step="0.01" style="width:120px;padding:5px 8px;border-radius:6px;border:1px solid #ccc;" required readonly></div>'
            +'<div style="margin-bottom:10px; color:#1a8917; font-weight:600;">Amount after Discount: <span id="afterDiscount">'+amount+'</span></div>'
            +'<div style="margin-bottom:10px;">Method: '
                +'<select name="pay_method" style="padding:5px 8px;border-radius:6px;border:1px solid #ccc;" required>'
                    +'<option value="Cash">Cash</option>'
                    +'<option value="UPI">UPI</option>'
                    +'<option value="Card">Card</option>'
                    +'<option value="Bank">Bank</option>'
                +'</select>'
            +'</div>'
            +'<div style="margin-bottom:10px;">Date: <input type="date" name="pay_date" value="'+(new Date().toISOString().slice(0,10))+'" style="padding:5px 8px;border-radius:6px;border:1px solid #ccc;" required></div>'
            +'<div style="margin-bottom:10px;">Transaction/Ref: <input type="text" name="transaction_details" style="width:180px;padding:5px 8px;border-radius:6px;border:1px solid #ccc;"></div>'
            +'<div style="margin-bottom:10px;">Note: <input type="text" name="note" style="width:180px;padding:5px 8px;border-radius:6px;border:1px solid #ccc;"></div>'
            +'<div style="margin-top:18px;text-align:center;">'
                +'<button type="submit" style="background:#1a8917;color:#fff;padding:8px 24px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Collect & Mark Paid</button>'
                +'&nbsp;'
                +'<button type="button" id="closePayPopup" style="background:#800000;color:#fff;padding:8px 24px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>'
            +'</div>'
            +'<div id="payErrorMsg" style="color:#c00;margin-top:10px;display:none;"></div>'
        +'</form>'
        +'</div>');
    // JS to update amount after discount and set amount to be collected
    setTimeout(function(){
        var $discount = $('#discountInput');
        var $origAmount = $('#origAmount');
        var $amountCollected = $('#amountCollected');
        var $afterDiscount = $('#afterDiscount');
        function updateAmount() {
            var orig = parseFloat($origAmount.val()) || 0;
            var disc = parseFloat($discount.val()) || 0;
            var after = Math.max(orig - disc, 0).toFixed(2);
            $afterDiscount.text(after);
            $amountCollected.val(after);
        }
        $discount.on('input', updateAmount);
        $origAmount.on('input', updateAmount);
        updateAmount();
    }, 100);
    popup.append(box);
    $('body').append(popup);
    $('#closePayPopup').on('click', function(){ $('#payPopupOverlay').remove(); });
    popup.on('click', function(e){ if(e.target === this) $(this).remove(); });
    $('#collectServicePaymentForm').on('submit', function(ev){
        ev.preventDefault();
        var form = $(this);
        var btn = form.find('button[type="submit"]');
        btn.prop('disabled', true).text('Processing...');
        $('#payErrorMsg').hide();
        $.post('collect-service-payment.php', form.serialize(), function(resp){
            btn.prop('disabled', false).text('Collect & Mark Paid');
            if(resp.success){
                // Update payment status in table (Payment column is 8th)
                row.find('td:nth-child(8)').html('<span class="status-badge payment-paid" style="background:#e5ffe5;color:#1a8917;">Paid</span>');
                // Update collected amount column (7th) with new value after discount
                var origAmt = parseFloat($('#origAmount').val()) || 0;
                var disc = parseFloat($('#discountInput').val()) || 0;
                var collected = (origAmt - disc).toFixed(2);
                row.find('td:nth-child(7)').html('₹' + collected);
                $('#payPopupOverlay').remove();
                // Optionally reload or show toast
            }else{
                var msg = resp.error||'Failed to collect payment.';
                if(resp.log){ msg += "\n[Log] " + resp.log; }
                if(resp.log_tail){ msg += "\n--- Error Log ---\n" + resp.log_tail; }
                $('#payErrorMsg').text(msg).show();
            }
        },'json').fail(function(xhr){
            btn.prop('disabled', false).text('Collect & Mark Paid');
            var msg = 'Server error. Please try again.';
            if(xhr && xhr.responseText){
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if(resp.error) msg = resp.error;
                    if(resp.log) msg += "\n[Log] " + resp.log;
                    if(resp.log_tail) msg += "\n--- Error Log ---\n" + resp.log_tail;
                } catch(e){}
            }
            $('#payErrorMsg').text(msg).show();
        });
    });
});
</script>
</body>
</html>
