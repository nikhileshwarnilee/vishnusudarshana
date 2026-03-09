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

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminder_event_id'])) {
    $reminderEventId = (int)$_POST['send_reminder_event_id'];
    $force = isset($_POST['force_send']) && (string)$_POST['force_send'] === '1';

    if ($reminderEventId <= 0) {
        $error = 'Please select a valid event to send reminders.';
    } else {
        $result = vs_event_send_event_reminders($pdo, $reminderEventId, $force);
        if (!empty($result['event_found'])) {
            $message = 'Reminder process completed. Sent: ' . (int)$result['sent'] . ', Skipped: ' . (int)$result['skipped'] . '.';
        } else {
            $error = 'Event not found for reminder.';
        }
    }
}

$events = $pdo->query("SELECT id, title, event_date, event_type FROM events ORDER BY event_date DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($events as &$eventRow) {
    $eventRow['event_date_display'] = vs_event_get_event_date_display(
        $pdo,
        (int)$eventRow['id'],
        (string)($eventRow['event_date'] ?? ''),
        (string)($eventRow['event_type'] ?? 'single_day')
    );
}
unset($eventRow);

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$fromDate = trim((string)($_GET['from_date'] ?? ''));
$toDate = trim((string)($_GET['to_date'] ?? ''));

$where = [];
$params = [];
if ($eventId > 0) {
    $where[] = 'r.event_id = ?';
    $params[] = $eventId;
}
if ($fromDate !== '') {
    $where[] = 'DATE(r.created_at) >= ?';
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $where[] = 'DATE(r.created_at) <= ?';
    $params[] = $toDate;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$summarySql = "SELECT
    COUNT(*) AS total_registrations,
    SUM(CASE WHEN r.payment_status = 'Paid' THEN 1 ELSE 0 END) AS paid_registrations,
    SUM(CASE WHEN r.payment_status = 'Pending Verification' THEN 1 ELSE 0 END) AS pending_verification,
    SUM(CASE WHEN r.payment_status = 'Failed' THEN 1 ELSE 0 END) AS failed_registrations,
    COALESCE(SUM(CASE WHEN r.payment_status = 'Paid' THEN ep.amount ELSE 0 END), 0) AS total_revenue
FROM event_registrations r
LEFT JOIN event_payments ep ON ep.registration_id = r.id
$whereSql";
$summaryStmt = $pdo->prepare($summarySql);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$reportSql = "SELECT
    e.id AS event_id,
    e.title AS event_title,
    e.event_type,
    e.event_date,
    p.id AS package_id,
    p.package_name,
    p.price,
    p.seat_limit,
    COUNT(r.id) AS registrations,
    SUM(CASE WHEN r.payment_status = 'Paid' THEN 1 ELSE 0 END) AS paid_count,
    SUM(CASE WHEN r.payment_status = 'Pending Verification' THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN r.payment_status = 'Failed' THEN 1 ELSE 0 END) AS failed_count,
    COALESCE(SUM(CASE WHEN r.payment_status = 'Paid' THEN ep.amount ELSE 0 END), 0) AS revenue
FROM events e
INNER JOIN event_packages p ON p.event_id = e.id
LEFT JOIN event_registrations r ON r.package_id = p.id
LEFT JOIN event_payments ep ON ep.registration_id = r.id
";

$reportWhere = [];
$reportParams = [];
if ($eventId > 0) {
    $reportWhere[] = 'e.id = ?';
    $reportParams[] = $eventId;
}
if ($fromDate !== '') {
    $reportWhere[] = '(r.id IS NULL OR DATE(r.created_at) >= ?)';
    $reportParams[] = $fromDate;
}
if ($toDate !== '') {
    $reportWhere[] = '(r.id IS NULL OR DATE(r.created_at) <= ?)';
    $reportParams[] = $toDate;
}
if (!empty($reportWhere)) {
    $reportSql .= ' WHERE ' . implode(' AND ', $reportWhere);
}
$reportSql .= ' GROUP BY e.id, p.id ORDER BY e.event_date DESC, p.id DESC';

$reportStmt = $pdo->prepare($reportSql);
$reportStmt->execute($reportParams);
$reportRows = $reportStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($reportRows as &$row) {
    $row['event_date_display'] = vs_event_get_event_date_display(
        $pdo,
        (int)$row['event_id'],
        (string)($row['event_date'] ?? ''),
        (string)($row['event_type'] ?? 'single_day')
    );
}
unset($row);

$exportQuery = $_GET;
$exportUrl = 'export-registrations.php';
if (!empty($exportQuery)) {
    $exportUrl .= '?' . http_build_query($exportQuery);
}
$pdfUrl = 'export-pdf.php';
if (!empty($exportQuery)) {
    $pdfUrl .= '?' . http_build_query($exportQuery);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Reports</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f7f7fa; margin:0; }
        .admin-container { max-width:1400px; margin:0 auto; padding:24px 12px; }
        h1 { color:#800000; margin-bottom:14px; }
        .notice { margin:10px 0; padding:10px 12px; border-radius:8px; font-weight:600; }
        .notice.ok { background:#e7f7ed; color:#1a8917; }
        .notice.err { background:#ffeaea; color:#b00020; }
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 12px #e0bebe22; padding:14px; margin-bottom:16px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:10px; align-items:end; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        label { color:#800000; font-weight:700; font-size:0.9em; }
        select, input[type="date"] { width:100%; box-sizing:border-box; padding:9px 10px; border:1px solid #e0bebe; border-radius:8px; font-size:0.94em; }
        .btn-main { display:inline-block; border:none; border-radius:8px; padding:9px 12px; font-weight:700; cursor:pointer; text-decoration:none; background:#800000; color:#fff; }
        .btn-alt { background:#0b7285; }
        .btn-warning { background:#e67700; }
        .summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:10px; }
        .summary-card { background:#fffbe7; border:1px solid #f1d6d6; border-radius:10px; padding:12px; }
        .summary-title { color:#800000; font-weight:700; font-size:0.92em; }
        .summary-value { font-size:1.5em; font-weight:800; color:#222; margin-top:4px; }
        .list-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px #e0bebe22; table-layout:auto; font-size:0.86em; }
        .list-table th, .list-table td { padding:8px 6px; border-bottom:1px solid #f3caca; text-align:left; vertical-align:top; }
        .list-table th { background:#f9eaea; color:#800000; font-size:0.9em; }
        .small { color:#666; font-size:0.84em; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <h1>Event Reports</h1>

    <?php if ($message !== ''): ?><div class="notice ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card">
        <form method="get" class="grid">
            <div class="form-group">
                <label>Event</label>
                <select name="event_id">
                    <option value="">All Events</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo (int)$event['id']; ?>" <?php echo ($eventId === (int)$event['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($event['title'] . ' (' . ($event['event_date_display'] ?? $event['event_date']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>From Date</label>
                <input type="date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
            </div>
            <div class="form-group">
                <label>To Date</label>
                <input type="date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
            </div>
            <div class="form-group"><button class="btn-main" type="submit">Apply</button></div>
            <div class="form-group"><a class="btn-main btn-alt" href="<?php echo htmlspecialchars($exportUrl); ?>">Export To Excel</a></div>
            <div class="form-group"><a class="btn-main btn-alt" href="<?php echo htmlspecialchars($pdfUrl); ?>" target="_blank">Export PDF</a></div>
        </form>
    </div>

    <div class="card">
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-title">Total Registrations</div>
                <div class="summary-value"><?php echo (int)($summary['total_registrations'] ?? 0); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-title">Paid Registrations</div>
                <div class="summary-value"><?php echo (int)($summary['paid_registrations'] ?? 0); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-title">Pending Verification</div>
                <div class="summary-value"><?php echo (int)($summary['pending_verification'] ?? 0); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-title">Failed Registrations</div>
                <div class="summary-value"><?php echo (int)($summary['failed_registrations'] ?? 0); ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-title">Revenue</div>
                <div class="summary-value">Rs <?php echo number_format((float)($summary['total_revenue'] ?? 0), 2); ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top:0; color:#800000;">Event Reminder</h3>
        <p class="small">Sends WhatsApp reminders to paid and approved registrations of the selected event.</p>
        <form method="post" class="grid">
            <div class="form-group">
                <label>Event</label>
                <select name="send_reminder_event_id" required>
                    <option value="">-- Select Event --</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo (int)$event['id']; ?>" <?php echo ($eventId === (int)$event['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($event['title'] . ' (' . ($event['event_date_display'] ?? $event['event_date']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="justify-content:center;">
                <label><input type="checkbox" name="force_send" value="1"> Force resend (ignore already-sent markers)</label>
            </div>
            <div class="form-group">
                <button type="submit" class="btn-main btn-warning">Send Event Reminder</button>
            </div>
        </form>
    </div>

    <table class="list-table">
        <thead>
            <tr>
                <th>Event</th>
                <th>Package</th>
                <th>Price</th>
                <th>Seat Limit</th>
                <th>Registrations</th>
                <th>Paid</th>
                <th>Pending</th>
                <th>Failed</th>
                <th>Revenue</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($reportRows)): ?>
            <tr><td colspan="9" style="text-align:center; padding:20px; color:#666;">No report rows found for selected filters.</td></tr>
        <?php else: ?>
            <?php foreach ($reportRows as $row): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($row['event_title']); ?></strong><br>
                        <span class="small"><?php echo htmlspecialchars((string)($row['event_date_display'] ?? $row['event_date'])); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($row['package_name']); ?></td>
                    <td>Rs <?php echo number_format((float)$row['price'], 2); ?></td>
                    <td><?php echo ($row['seat_limit'] !== null && $row['seat_limit'] !== '') ? (int)$row['seat_limit'] : 'Unlimited'; ?></td>
                    <td><?php echo (int)$row['registrations']; ?></td>
                    <td><?php echo (int)$row['paid_count']; ?></td>
                    <td><?php echo (int)$row['pending_count']; ?></td>
                    <td><?php echo (int)$row['failed_count']; ?></td>
                    <td>Rs <?php echo number_format((float)$row['revenue'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="../includes/responsive-tables.js"></script>
</body>
</html>
