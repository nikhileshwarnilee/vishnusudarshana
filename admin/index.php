<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['user_id'] != 1) {
    header('Location: staff-dashboard.php');
    exit;
}
require_once __DIR__ . '/includes/top-menu.php';
// Database connection
require_once __DIR__ . '/../config/db.php';
$today = date('Y-m-d');
// Today's Service Request payments (source: Service Request, not Invoice)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM service_requests WHERE payment_status = 'Paid' AND (tracking_id IS NULL OR tracking_id = '' OR tracking_id NOT LIKE 'VDSK%') AND DATE(created_at) = ?");
$stmt->execute([$today]);
$today_sr_offline = $stmt->fetchColumn();

function getCount($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $count = $stmt->fetchColumn();
    return $count !== false ? (int)$count : 0;
}


// STAT CARDS
$totalAppointments = getCount($pdo, "SELECT COUNT(*) FROM service_requests WHERE category_slug = ?", ['appointment']);
$pendingAppointments = getCount($pdo, "SELECT COUNT(*) FROM service_requests WHERE category_slug = ? AND service_status = ?", ['appointment', 'Received']);
$acceptedAppointments = getCount($pdo, "SELECT COUNT(*) FROM service_requests WHERE category_slug = ? AND service_status = ?", ['appointment', 'Accepted']);
$completedAppointments = getCount($pdo, "SELECT COUNT(*) FROM service_requests WHERE category_slug = ? AND service_status = ?", ['appointment', 'Completed']);
$totalServiceRequests = getCount($pdo, "SELECT COUNT(*) FROM service_requests WHERE category_slug != ?", ['appointment']);

// PAYMENT SUMMARY CARDS
$totalInvoices = getCount($pdo, "SELECT COUNT(*) FROM invoices");


// --- MATCH dues.php STATS LOGIC ---
$from_date = '';
$to_date = '';
// Total invoiced
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as total_invoiced FROM invoices");
$stmt->execute();
$total_invoiced = $stmt->fetchColumn();
// Total paid
$stmt = $pdo->prepare("SELECT COALESCE(SUM(paid_amount),0) as total_paid FROM invoices");
$stmt->execute();
$total_paid = $stmt->fetchColumn();
// Total unpaid
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount - paid_amount),0) as total_unpaid FROM invoices");
$stmt->execute();
$total_unpaid = $stmt->fetchColumn();
// Today's collection (from payments table, like dues.php)
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COALESCE(SUM(paid_amount),0) as offline, COALESCE(SUM(CASE WHEN method='Razorpay' THEN paid_amount ELSE 0 END),0) as razorpay FROM payments WHERE paid_date = ?");
$stmt->execute([$today]);
$todayRow = $stmt->fetch();
$todays_invoice_offline = $todayRow['offline'] ? $todayRow['offline'] : 0;
$todays_razorpay = $todayRow['razorpay'] ? $todayRow['razorpay'] : 0;
// Add today's service request offline payments (after discount)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount - COALESCE(discount, 0)),0) FROM service_requests WHERE payment_status = 'Paid' AND (tracking_id IS NULL OR tracking_id = '' OR tracking_id NOT LIKE 'VDSK%') AND DATE(created_at) = ?");
$stmt->execute([$today]);
$today_sr_offline = $stmt->fetchColumn();
$todays_offline = $todays_invoice_offline + $today_sr_offline;

// Today's online payment collection (Razorpay/online) (after discount)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount - COALESCE(discount, 0)),0) FROM service_requests WHERE payment_status = 'Paid' AND tracking_id LIKE 'VDSK%' AND DATE(created_at) = ?");
$stmt->execute([$today]);
$todays_razorpay = $stmt->fetchColumn();

// All time payment method breakdown
$stmt = $pdo->query("SELECT COALESCE(SUM(paid_amount),0) as offline, COALESCE(SUM(CASE WHEN method='Razorpay' THEN paid_amount ELSE 0 END),0) as razorpay FROM payments");
$allRow = $stmt->fetch();
$all_offline = $allRow['offline'] ? $allRow['offline'] : 0;
$all_razorpay = $allRow['razorpay'] ? $allRow['razorpay'] : 0;


// TODAY SNAPSHOT
$today = date('Y-m-d');
$todayAppointments = getCount($pdo, "SELECT COUNT(*) FROM service_requests WHERE category_slug = ? AND DATE(created_at) = ?", ['appointment', $today]);
$todayServices = getCount($pdo, "SELECT COUNT(*) FROM service_requests WHERE category_slug != ? AND DATE(created_at) = ?", ['appointment', $today]);
$todayPayments = getCount($pdo, "SELECT COUNT(*) FROM service_requests WHERE payment_status = ? AND DATE(created_at) = ?", ['Paid', $today]);
// Total online payment collected today (Paid, Online)
$stmtOnlineAmt = $pdo->prepare("SELECT SUM(total_amount) FROM service_requests WHERE payment_status = 'Paid' AND tracking_id LIKE 'VDSK%' AND DATE(created_at) = ?");
$stmtOnlineAmt->execute([$today]);
$todayOnlinePayment = $stmtOnlineAmt->fetchColumn();
if ($todayOnlinePayment === null) $todayOnlinePayment = 0;

// RECENT ACTIVITY
$recentSql = "SELECT id, created_at, category_slug, customer_name, tracking_id, service_status FROM service_requests ORDER BY created_at DESC LIMIT 10";
$recentStmt = $pdo->prepare($recentSql);
$recentStmt->execute();
$recentRows = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
            position: relative;
            z-index: 1;
        }
        h1 {
            color: #800000;
            margin-bottom: 18px;
        }
        /* SUMMARY CARDS - Now in responsive-cards.css */
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
        /* Mobile styles now in responsive-cards.css */
        </style>
</head>
<body>
<div class="admin-container" style="max-width:1100px;margin:0 auto;padding:24px 12px;">
    <h1 style="color:var(--maroon);margin-bottom:18px;">Admin Dashboard</h1>
    <div style="text-align:center;color:#666;font-size:1.08rem;margin-bottom:28px;">Overview of appointments, services, and payments</div>


    <!-- SECTION A: Dashboard Stats by Category (Grouped) -->
    <div class="dashboard-groups">
        <!-- Appointments Group: Only Today's Appointments and Pending -->
        <div class="stat-group" style="background:#f8faff;">
            <h3 style="color:#0056b3;">Appointments (Today)</h3>
            <div class="summary-card" style="background:#e5f0ff; border:2px solid #0056b3; margin-bottom:10px;">
                <div class="summary-count" style="color:#0056b3;"><?php echo $todayAppointments; ?></div>
                <div class="summary-label">Today's Appointments</div>
            </div>
            <div class="summary-card" style="background:#e5f0ff; border:2px solid #0056b3;">
                <div class="summary-count" style="color:#0056b3;"><?php echo getCount($pdo, "SELECT COUNT(*) FROM service_requests WHERE category_slug = ? AND service_status = ? AND DATE(created_at) = ?", ['appointment', 'Received', $today]); ?></div>
                <div class="summary-label">Today's Pending</div>
            </div>
        </div>
        <!-- Payments Group -->
        <div class="stat-group" style="background:#f0f7ff;">
            <h3 style="color:#007bff;">Payments (Today)</h3>
            <div class="summary-card" style="background:#e5f0ff; border:2px solid #007bff; margin-bottom:10px;">
                <div class="summary-count" style="color:#007bff;">₹<?php echo number_format($todays_offline,2); ?></div>
                <div class="summary-label">Offline Collection</div>
            </div>
            <div class="summary-card" style="background:#e5f0ff; border:2px solid #007bff;">
                <div class="summary-count" style="color:#007bff;">₹<?php echo number_format($todays_razorpay,2); ?></div>
                <div class="summary-label">Razorpay Collection</div>
            </div>
        </div>
        <!-- Service Requests Group: Pending and Today's -->
        <div class="stat-group" style="background:#f8f6f2;">
            <h3 style="color:#800000;">Service Requests</h3>
            <div class="summary-card" style="background:#f8f6f2; border:2px solid #800000; margin-bottom:10px;">
                <div class="summary-count" style="color:#800000;">
                    <?php echo getCount($pdo, "SELECT COUNT(*) FROM service_requests WHERE category_slug != ? AND service_status = ?", ['appointment', 'Received']); ?>
                </div>
                <div class="summary-label">Pending Requests</div>
            </div>
            <div class="summary-card" style="background:#f8f6f2; border:2px solid #28a745;">
                <div class="summary-count" style="color:#28a745;">
                    <?php echo getCount($pdo, "SELECT COUNT(*) FROM service_requests WHERE category_slug != ? AND DATE(created_at) = ?", ['appointment', $today]); ?>
                </div>
                <div class="summary-label">Today's Requests</div>
            </div>
        </div>
    </div>


    <!-- QUICK ACCESS CARDS -->
    <div class="summary-cards">
        <div class="summary-card quick-access-card" onclick="window.location.href='cif/index.php'" style="cursor:pointer;background:#e5f0ff;">
            <div class="summary-count" style="color:#0056b3;"><span style="font-size:1.2em;">📄</span></div>
            <div class="summary-label" style="color:#0056b3;">CIF Home</div>
        </div>
        <div class="summary-card quick-access-card" onclick="window.location.href='services/service-request-list.php'" style="cursor:pointer;background:#fffbe7;">
            <div class="summary-count" style="color:#b36b00;"><span style="font-size:1.2em;">🛠️</span></div>
            <div class="summary-label" style="color:#b36b00;">Service Request List</div>
        </div>
        <div class="summary-card quick-access-card" onclick="window.location.href='services/accepted-appointments.php'" style="cursor:pointer;background:#e5ffe5;">
            <div class="summary-count" style="color:#1a8917;"><span style="font-size:1.2em;">✅</span></div>
            <div class="summary-label" style="color:#1a8917;">Accepted Appointments</div>
        </div>
    </div>



    <!-- SECTION D: Recent Activity Table -->
    <div style="margin-bottom:36px;">
        <div class="section-title" style="font-size:22px;color:var(--maroon);font-weight:700;margin-bottom:16px;text-align:center;">Recent Activity</div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <input type="text" id="searchInput" placeholder="Search..." style="padding:7px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;max-width:220px;">
            <div style="font-size:0.98em;color:#888;">Showing max 10 records</div>
        </div>
        <div style="overflow-x:auto;">
            <table class="service-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Customer</th>
                            <th>Tracking ID</th>
                            <th>Mobile</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Action</th>
                    </tr>
                </thead>
                <tbody id="activityTableBody">
                <!-- AJAX loaded rows -->
                </tbody>
            </table>
            <div id="dashboardPagination" class="pagination" style="margin:18px 0;justify-content:center;gap:8px;"></div>
        </div>
    </div>
<script>
function loadDashboardActivity(page = 1, search = '') {
    const tbody = document.getElementById('activityTableBody');
    const pag = document.getElementById('dashboardPagination');
    fetch('ajax_dashboard_activity.php?page=' + page + '&search=' + encodeURIComponent(search))
        .then(r => r.text())
        .then(html => {
            // Extract <script> for pagination
            const scriptMatch = html.match(/<script[^>]*>[\s\S]*?<\/script>/i);
            if (scriptMatch) {
                html = html.replace(scriptMatch[0], '');
                eval(scriptMatch[0].replace('<script>', '').replace('<\/script>', ''));
            }
            tbody.innerHTML = html;
            renderDashboardPagination();
        });
}

function renderDashboardPagination() {
    const pag = document.getElementById('dashboardPagination');
    if (!window.dashboardPagination) return;
    const totalPages = window.dashboardPagination.totalPages || 1;
    const currentPage = window.dashboardPagination.currentPage || 1;
    let html = '';
    if (currentPage > 1) {
        html += '<a href="#" class="page-link" data-page="' + (currentPage - 1) + '">&laquo; Previous</a> ';
    } else {
        html += '<span class="page-link disabled">&laquo; Previous</span> ';
    }
    for (let i = 1; i <= totalPages; i++) {
        if (i === currentPage) {
            html += '<span class="page-link current">' + i + '</span> ';
        } else {
            html += '<a href="#" class="page-link" data-page="' + i + '">' + i + '</a> ';
        }
    }
    if (currentPage < totalPages) {
        html += '<a href="#" class="page-link" data-page="' + (currentPage + 1) + '">Next &raquo;</a>';
    } else {
        html += '<span class="page-link disabled">Next &raquo;</span>';
    }
    pag.innerHTML = html;
    pag.querySelectorAll('.page-link[data-page]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const page = parseInt(link.getAttribute('data-page'), 10) || 1;
            loadDashboardActivity(page, document.getElementById('searchInput').value);
        });
    });
}

document.getElementById('searchInput').addEventListener('input', function() {
    loadDashboardActivity(1, this.value);
});

// Initial load
loadDashboardActivity(1, '');
</script>

</div>
<script>
// Table search filter
document.getElementById('searchInput').addEventListener('keyup', function() {
    var filter = this.value.toLowerCase();
    var rows = document.querySelectorAll('#activityTableBody tr');
    rows.forEach(function(row) {
        var text = row.textContent.toLowerCase();
        row.style.display = text.indexOf(filter) > -1 ? '' : 'none';
    });
});
</script>
</body>
</html>
