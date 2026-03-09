<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');

date_default_timezone_set('Asia/Kolkata');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/event_module.php';

vs_event_ensure_tables($pdo);

$today = date('Y-m-d');

$totalEvents = (int)$pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$activeEvents = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status = 'Active'")->fetchColumn();
$totalRegistrations = (int)$pdo->query("SELECT COUNT(*) FROM event_registrations")->fetchColumn();
$paidRegistrations = (int)$pdo->query("SELECT COUNT(*) FROM event_registrations WHERE payment_status = 'Paid'")->fetchColumn();
$pendingPayments = (int)$pdo->query("SELECT COUNT(*)
    FROM event_registrations
    WHERE payment_status IN ('Unpaid', 'Pending Verification')")->fetchColumn();

$upcomingCountStmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE status = 'Active' AND event_date >= ?");
$upcomingCountStmt->execute([$today]);
$upcomingEventsCount = (int)$upcomingCountStmt->fetchColumn();

$revenueStmt = $pdo->query("SELECT COALESCE(SUM(CASE
    WHEN r.payment_status = 'Paid' THEN COALESCE(ep.amount, (p.price * r.persons))
    ELSE 0
END), 0)
FROM event_registrations r
INNER JOIN event_packages p ON p.id = r.package_id
LEFT JOIN event_payments ep ON ep.registration_id = r.id");
$totalRevenue = (float)$revenueStmt->fetchColumn();

$registrationsPerEventStmt = $pdo->query("SELECT
    e.title,
    COUNT(r.id) AS total
FROM events e
LEFT JOIN event_registrations r ON r.event_id = e.id
GROUP BY e.id
ORDER BY total DESC, e.event_date DESC
LIMIT 12");
$registrationsPerEvent = $registrationsPerEventStmt->fetchAll(PDO::FETCH_ASSOC);

$revenuePerEventStmt = $pdo->query("SELECT
    e.title,
    COALESCE(SUM(CASE
        WHEN r.payment_status = 'Paid' THEN COALESCE(ep.amount, (p.price * r.persons))
        ELSE 0
    END), 0) AS revenue
FROM events e
LEFT JOIN event_registrations r ON r.event_id = e.id
LEFT JOIN event_packages p ON p.id = r.package_id
LEFT JOIN event_payments ep ON ep.registration_id = r.id
GROUP BY e.id
ORDER BY revenue DESC, e.event_date DESC
LIMIT 12");
$revenuePerEvent = $revenuePerEventStmt->fetchAll(PDO::FETCH_ASSOC);

$packagePopularityStmt = $pdo->query("SELECT
    CONCAT(e.title, ' - ', p.package_name) AS package_label,
    COUNT(r.id) AS total
FROM event_packages p
INNER JOIN events e ON e.id = p.event_id
LEFT JOIN event_registrations r ON r.package_id = p.id
GROUP BY p.id
ORDER BY total DESC, p.id DESC
LIMIT 12");
$packagePopularity = $packagePopularityStmt->fetchAll(PDO::FETCH_ASSOC);

$dailyTrendRawStmt = $pdo->query("SELECT DATE(created_at) AS day, COUNT(*) AS total
FROM event_registrations
WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
GROUP BY DATE(created_at)
ORDER BY day ASC");
$dailyTrendRaw = $dailyTrendRawStmt->fetchAll(PDO::FETCH_ASSOC);
$dailyTrendMap = [];
foreach ($dailyTrendRaw as $row) {
    $dailyTrendMap[(string)$row['day']] = (int)$row['total'];
}

$dailyTrendLabels = [];
$dailyTrendValues = [];
for ($i = 29; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime('-' . $i . ' day'));
    $dailyTrendLabels[] = date('d M', strtotime($day));
    $dailyTrendValues[] = $dailyTrendMap[$day] ?? 0;
}

$upcomingStmt = $pdo->prepare("SELECT
    e.id,
    e.title,
    e.event_date,
    e.event_type,
    e.location,
    e.registration_start,
    e.registration_end,
    e.status,
    COUNT(r.id) AS registrations,
    SUM(CASE WHEN r.payment_status = 'Paid' THEN 1 ELSE 0 END) AS paid_count
FROM events e
LEFT JOIN event_registrations r ON r.event_id = e.id
WHERE e.event_date >= ?
GROUP BY e.id
ORDER BY e.event_date ASC, e.id ASC
LIMIT 12");
$upcomingStmt->execute([$today]);
$upcomingEvents = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($upcomingEvents as &$eventRow) {
    $eventRow['event_date_display'] = vs_event_get_event_date_display(
        $pdo,
        (int)$eventRow['id'],
        (string)($eventRow['event_date'] ?? ''),
        (string)($eventRow['event_type'] ?? 'single_day')
    );
}
unset($eventRow);

$chartPayload = [
    'registrationsPerEvent' => [
        'labels' => array_map(static function ($row) { return (string)$row['title']; }, $registrationsPerEvent),
        'data' => array_map(static function ($row) { return (int)$row['total']; }, $registrationsPerEvent),
    ],
    'revenuePerEvent' => [
        'labels' => array_map(static function ($row) { return (string)$row['title']; }, $revenuePerEvent),
        'data' => array_map(static function ($row) { return (float)$row['revenue']; }, $revenuePerEvent),
    ],
    'packagePopularity' => [
        'labels' => array_map(static function ($row) { return (string)$row['package_label']; }, $packagePopularity),
        'data' => array_map(static function ($row) { return (int)$row['total']; }, $packagePopularity),
    ],
    'dailyTrend' => [
        'labels' => $dailyTrendLabels,
        'data' => $dailyTrendValues,
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f7f7fa; margin:0; }
        .admin-container { max-width:1450px; margin:0 auto; padding:24px 12px; }
        h1 { color:#800000; margin-bottom:14px; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
        .btn-main { display:inline-block; border:none; border-radius:8px; padding:9px 12px; font-weight:700; cursor:pointer; text-decoration:none; background:#800000; color:#fff; }
        .btn-alt { background:#0b7285; }
        .summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:10px; margin-bottom:16px; }
        .summary-card { background:#fff; border:1px solid #ecd3d3; border-radius:12px; box-shadow:0 2px 12px #e0bebe22; padding:12px; }
        .summary-title { color:#800000; font-weight:700; font-size:0.9em; }
        .summary-value { font-size:1.45em; font-weight:800; color:#333; margin-top:4px; }
        .analytics-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); gap:12px; margin-bottom:16px; }
        .chart-card { background:#fff; border:1px solid #ecd3d3; border-radius:12px; box-shadow:0 2px 12px #e0bebe22; padding:12px; min-height:290px; }
        .chart-card h3 { margin:0 0 10px; color:#800000; font-size:1em; }
        .chart-wrap { position:relative; height:230px; }
        .list-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px #e0bebe22; table-layout:auto; font-size:0.86em; }
        .list-table th, .list-table td { padding:8px 6px; border-bottom:1px solid #f3caca; text-align:left; }
        .list-table th { background:#f9eaea; color:#800000; font-size:0.9em; }
        .status { display:inline-block; border-radius:12px; padding:3px 9px; font-size:0.8em; font-weight:700; }
        .status.active { background:#e5ffe5; color:#1a8917; }
        .status.closed { background:#ffeaea; color:#b00020; }
        .small { color:#666; font-size:0.84em; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <h1>Events Dashboard</h1>

    <div class="actions">
        <a class="btn-main" href="all-events.php">All Events</a>
        <a class="btn-main" href="add-event.php">Add Event</a>
        <a class="btn-main btn-alt" href="registrations.php">Registrations</a>
        <a class="btn-main btn-alt" href="checkin.php">Check-In</a>
        <a class="btn-main btn-alt" href="waitlist.php">Waitlist</a>
        <a class="btn-main btn-alt" href="event-reports.php">Event Reports</a>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-title">Total Events</div>
            <div class="summary-value"><?php echo $totalEvents; ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-title">Active Events</div>
            <div class="summary-value"><?php echo $activeEvents; ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-title">Total Registrations</div>
            <div class="summary-value"><?php echo $totalRegistrations; ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-title">Paid Registrations</div>
            <div class="summary-value"><?php echo $paidRegistrations; ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-title">Pending Payments</div>
            <div class="summary-value"><?php echo $pendingPayments; ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-title">Upcoming Events</div>
            <div class="summary-value"><?php echo $upcomingEventsCount; ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-title">Total Revenue</div>
            <div class="summary-value">Rs <?php echo number_format($totalRevenue, 2); ?></div>
        </div>
    </div>

    <div class="analytics-grid">
        <div class="chart-card">
            <h3>Registrations Per Event</h3>
            <div class="chart-wrap"><canvas id="chartRegistrationsPerEvent"></canvas></div>
        </div>
        <div class="chart-card">
            <h3>Revenue Per Event</h3>
            <div class="chart-wrap"><canvas id="chartRevenuePerEvent"></canvas></div>
        </div>
        <div class="chart-card">
            <h3>Package Popularity</h3>
            <div class="chart-wrap"><canvas id="chartPackagePopularity"></canvas></div>
        </div>
        <div class="chart-card">
            <h3>Daily Registration Trend (30 Days)</h3>
            <div class="chart-wrap"><canvas id="chartDailyTrend"></canvas></div>
        </div>
    </div>

    <table class="list-table">
        <thead>
            <tr>
                <th>Upcoming Event</th>
                <th>Date</th>
                <th>Location</th>
                <th>Registration Window</th>
                <th>Status</th>
                <th>Registrations</th>
                <th>Paid</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($upcomingEvents)): ?>
            <tr><td colspan="8" style="text-align:center; padding:20px; color:#666;">No upcoming events found.</td></tr>
        <?php else: ?>
            <?php foreach ($upcomingEvents as $event): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars((string)$event['title']); ?></strong>
                        <div class="small">ID: <?php echo (int)$event['id']; ?></div>
                    </td>
                    <td><?php echo htmlspecialchars((string)($event['event_date_display'] ?? $event['event_date'])); ?></td>
                    <td><?php echo htmlspecialchars((string)$event['location']); ?></td>
                    <td>
                        <span class="small"><?php echo htmlspecialchars((string)$event['registration_start']); ?> to <?php echo htmlspecialchars((string)$event['registration_end']); ?></span>
                    </td>
                    <td>
                        <span class="status <?php echo strtolower((string)$event['status']); ?>"><?php echo htmlspecialchars((string)$event['status']); ?></span>
                    </td>
                    <td><?php echo (int)$event['registrations']; ?></td>
                    <td><?php echo (int)$event['paid_count']; ?></td>
                    <td>
                        <a class="btn-main" href="registrations.php?event_id=<?php echo (int)$event['id']; ?>">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const payload = <?php echo json_encode($chartPayload, JSON_UNESCAPED_UNICODE); ?>;

    function buildChart(canvasId, labels, values, label, color, isCurrency, type) {
        const el = document.getElementById(canvasId);
        if (!el) return;
        const chartType = type || 'bar';

        new Chart(el, {
            type: chartType,
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: values,
                    backgroundColor: color,
                    borderColor: color,
                    borderWidth: 1,
                    fill: chartType === 'line'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                if (isCurrency) {
                                    return 'Rs ' + Number(ctx.parsed.y || ctx.parsed).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                                return Number(ctx.parsed.y || ctx.parsed).toLocaleString('en-IN');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    buildChart(
        'chartRegistrationsPerEvent',
        payload.registrationsPerEvent.labels,
        payload.registrationsPerEvent.data,
        'Registrations',
        'rgba(128, 0, 0, 0.6)',
        false,
        'bar'
    );

    buildChart(
        'chartRevenuePerEvent',
        payload.revenuePerEvent.labels,
        payload.revenuePerEvent.data,
        'Revenue',
        'rgba(26, 137, 23, 0.6)',
        true,
        'bar'
    );

    buildChart(
        'chartPackagePopularity',
        payload.packagePopularity.labels,
        payload.packagePopularity.data,
        'Registrations',
        'rgba(11, 114, 133, 0.6)',
        false,
        'bar'
    );

    buildChart(
        'chartDailyTrend',
        payload.dailyTrend.labels,
        payload.dailyTrend.data,
        'Daily Registrations',
        'rgba(230, 119, 0, 0.6)',
        false,
        'line'
    );
})();
</script>
<script src="../includes/responsive-tables.js"></script>
</body>
</html>
