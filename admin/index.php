
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/includes/top-menu.php';
// Database connection
require_once __DIR__ . '/../config/db.php';

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
            font-family: Arial, sans-serif;
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
        @media (max-width: 700px) {
            .summary-cards {
                flex-direction: column;
            }
        }
        </style>
</head>
<body>
<div class="admin-container" style="max-width:1100px;margin:0 auto;padding:24px 12px;">
    <h1 style="color:#800000;margin-bottom:18px;">Admin Dashboard</h1>
    <div style="text-align:center;color:#666;font-size:1.08rem;margin-bottom:28px;">Overview of appointments, services, and payments</div>

    <!-- SECTION B: Stat Cards -->
    <div class="summary-cards" style="gap:18px;margin-bottom:24px;flex-wrap:wrap;">
        <div class="summary-card" onclick="window.location.href='services/appointments.php'" style="cursor:pointer;">
            <div class="summary-count" style="font-size:2.2em;font-weight:700;color:#800000;"><?php echo $totalAppointments; ?></div>
            <div class="summary-label" style="font-size:1em;color:#444;">Total Appointments</div>
        </div>
        <div class="summary-card" onclick="window.location.href='services/appointments.php?status=Received'" style="cursor:pointer;">
            <div class="summary-count" style="font-size:2.2em;font-weight:700;color:#800000;"><?php echo $pendingAppointments; ?></div>
            <div class="summary-label" style="font-size:1em;color:#444;">Pending Appointments</div>
        </div>
        <div class="summary-card" onclick="window.location.href='services/accepted-appointments.php'" style="cursor:pointer;">
            <div class="summary-count" style="font-size:2.2em;font-weight:700;color:#800000;"><?php echo $acceptedAppointments; ?></div>
            <div class="summary-label" style="font-size:1em;color:#444;">Accepted Appointments</div>
        </div>
        <div class="summary-card" onclick="window.location.href='services/completed-appointments.php'" style="cursor:pointer;">
            <div class="summary-count" style="font-size:2.2em;font-weight:700;color:#800000;"><?php echo $completedAppointments; ?></div>
            <div class="summary-label" style="font-size:1em;color:#444;">Completed Appointments</div>
        </div>
        <div class="summary-card" onclick="window.location.href='services/index.php'" style="cursor:pointer;">
            <div class="summary-count" style="font-size:2.2em;font-weight:700;color:#800000;"><?php echo $totalServiceRequests; ?></div>
            <div class="summary-label" style="font-size:1em;color:#444;">Total Service Requests</div>
        </div>
        <div class="summary-card" onclick="window.location.href='cif/index.php'" style="cursor:pointer;background:#e5f0ff;">
            <div class="summary-count" style="font-size:2.2em;font-weight:700;color:#0056b3;"><span style="font-size:1.2em;">📄</span></div>
            <div class="summary-label" style="font-size:1em;color:#0056b3;">CIF Home</div>
        </div>
        <div class="summary-card" onclick="window.location.href='services/service-request-list.php'" style="cursor:pointer;background:#fffbe7;">
            <div class="summary-count" style="font-size:2.2em;font-weight:700;color:#b36b00;"><span style="font-size:1.2em;">🛠️</span></div>
            <div class="summary-label" style="font-size:1em;color:#b36b00;">Service Request List</div>
        </div>
        <div class="summary-card" onclick="window.location.href='services/accepted-appointments.php'" style="cursor:pointer;background:#e5ffe5;">
            <div class="summary-count" style="font-size:2.2em;font-weight:700;color:#1a8917;"><span style="font-size:1.2em;">✅</span></div>
            <div class="summary-label" style="font-size:1em;color:#1a8917;">Accepted Appointments</div>
        </div>
    </div>

    <!-- SECTION C: Today Snapshot -->

    <div class="summary-cards" style="gap:18px;margin-bottom:32px;flex-wrap:wrap;">
        <div class="summary-card">
            <div class="summary-count" style="font-size:2.2em;font-weight:700;color:#800000;"><?php echo $todayAppointments; ?></div>
            <div class="summary-label" style="font-size:1em;color:#444;">Today's Appointments</div>
        </div>
        <div class="summary-card">
            <div class="summary-count" style="font-size:2.2em;font-weight:700;color:#800000;"><?php echo $todayServices; ?></div>
            <div class="summary-label" style="font-size:1em;color:#444;">Today's Services</div>
        </div>
        <div class="summary-card">
            <div class="summary-count" style="font-size:2.2em;font-weight:700;color:#800000;"><?php echo $todayPayments; ?></div>
            <div class="summary-label" style="font-size:1em;color:#444;">Today's Payments (Paid)</div>
        </div>
        <div class="summary-card">
            <div class="summary-count" style="font-size:2.2em;font-weight:700;color:#800000;">₹<?php echo number_format($todayOnlinePayment, 2); ?></div>
            <div class="summary-label" style="font-size:1em;color:#444;">Online Payment Collected Today</div>
        </div>
    </div>

    <!-- SECTION D: Recent Activity Table -->
    <div style="margin-bottom:36px;">
        <div class="section-title" style="font-size:22px;color:#800000;font-weight:700;margin-bottom:16px;text-align:center;">Recent Activity</div>
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

    <!-- SECTION E: Quick Links -->
    <div class="summary-cards" style="gap:18px;flex-wrap:wrap;margin-bottom:0;">
        <div class="summary-card" onclick="window.location.href='services/appointments.php'" style="cursor:pointer;min-width:180px;">
            <div class="card-icon">📅</div>
            <div class="summary-label">Manage Appointments</div>
        </div>
        <div class="summary-card" onclick="window.location.href='services/accepted-appointments.php'" style="cursor:pointer;min-width:180px;">
            <div class="card-icon">✅</div>
            <div class="summary-label">Accepted Appointments</div>
        </div>
        <div class="summary-card" onclick="window.location.href='services/completed-appointments.php'" style="cursor:pointer;min-width:180px;">
            <div class="card-icon">✔️</div>
            <div class="summary-label">Completed Appointments</div>
        </div>
        <div class="summary-card" onclick="window.location.href='services/index.php'" style="cursor:pointer;min-width:180px;">
            <div class="card-icon">🛎️</div>
            <div class="summary-label">Service Requests</div>
        </div>
        <div class="summary-card" onclick="window.location.href='../admin/products/index.php'" style="cursor:pointer;min-width:180px;">
            <div class="card-icon">📦</div>
            <div class="summary-label">Products</div>
        </div>
        <div class="summary-card" onclick="window.location.href='../payment-success.php'" style="cursor:pointer;min-width:180px;">
            <div class="card-icon">💳</div>
            <div class="summary-label">Payments</div>
        </div>
    </div>
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
