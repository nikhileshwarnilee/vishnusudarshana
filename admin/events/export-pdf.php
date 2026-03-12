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
$paymentStatus = trim((string)($_GET['payment_status'] ?? ''));

$where = [];
$params = [];
if ($eventId > 0) {
    $where[] = 'r.event_id = ?';
    $params[] = $eventId;
}
if ($paymentStatus !== '') {
    $where[] = 'r.payment_status = ?';
    $params[] = $paymentStatus;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT
    r.id,
    r.event_id,
    r.event_date_id,
    r.booking_reference,
    r.name,
    r.phone,
    r.persons,
    r.payment_status,
    r.created_at,
    e.title AS event_title,
    e.event_type,
    COALESCE(d.event_date, e.event_date) AS selected_event_date,
    p.package_name
FROM event_registrations r
INNER JOIN events e ON e.id = r.event_id
LEFT JOIN event_dates d ON d.id = r.event_date_id
INNER JOIN event_packages p ON p.id = r.package_id
$whereSql
ORDER BY e.event_date ASC, r.id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$row) {
    $ref = trim((string)($row['booking_reference'] ?? ''));
    if ($ref === '') {
        $ref = vs_event_assign_booking_reference($pdo, (int)$row['id']);
        $row['booking_reference'] = $ref;
    }
    $row['event_date_display'] = vs_event_get_registration_date_display(
        $pdo,
        $row,
        (string)($row['selected_event_date'] ?? '')
    );
}
unset($row);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export PDF - Event Registrations</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f7f7fa; margin:0; }
        .admin-container { max-width:1200px; margin:0 auto; padding:24px 12px; }
        h1 { color:#800000; margin-bottom:14px; }
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 12px #e0bebe22; padding:14px; margin-bottom:16px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:10px; align-items:end; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        label { color:#800000; font-weight:700; font-size:0.9em; }
        select { width:100%; box-sizing:border-box; padding:9px 10px; border:1px solid #e0bebe; border-radius:8px; font-size:0.94em; }
        .btn-main { display:inline-block; border:none; border-radius:8px; padding:9px 12px; font-weight:700; cursor:pointer; text-decoration:none; background:#800000; color:#fff; }
        .btn-alt { background:#0b7285; }
        .print-wrapper { background:#fff; border:1px solid #ecd3d3; border-radius:12px; padding:14px; }
        .meta { display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; margin-bottom:10px; color:#555; font-size:0.9em; }
        table { width:100%; border-collapse:collapse; font-size:0.86em; }
        th, td { border:1px solid #e7c9c9; padding:7px 6px; text-align:left; }
        th { background:#f9eaea; color:#800000; }
        @media print {
            .no-print, .admin-top-menu { display:none !important; }
            body { background:#fff; }
            .admin-container { max-width:none; margin:0; padding:0; }
            .card { box-shadow:none; border:none; padding:0; margin:0 0 8px 0; }
            .print-wrapper { border:none; padding:0; }
            table { font-size:11px; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <h1 class="no-print">Export PDF - Registrations</h1>

    <div class="card no-print">
        <form method="get" class="grid">
            <div class="form-group">
                <label>Event</label>
                <select name="event_id">
                    <option value="0">All Events</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo (int)$event['id']; ?>" <?php echo ($eventId === (int)$event['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$event['title'] . ' (' . (string)($event['event_date_display'] ?? $event['event_date']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Payment Status</label>
                <select name="payment_status">
                    <option value="">All</option>
                    <?php foreach (['Unpaid', 'Partial Paid', 'Pending Verification', 'Paid', 'Failed', 'Cancelled'] as $st): ?>
                        <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($paymentStatus === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn-main">Apply</button>
            </div>
            <div class="form-group">
                <button type="button" class="btn-main btn-alt" onclick="window.print()">Print / Save as PDF</button>
            </div>
        </form>
    </div>

    <div class="print-wrapper">
        <div class="meta">
            <div><strong>Generated:</strong> <?php echo date('Y-m-d H:i:s'); ?></div>
            <div><strong>Total Rows:</strong> <?php echo count($rows); ?></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Booking Reference</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Event</th>
                    <th>Package</th>
                    <th>Persons</th>
                    <th>Payment Status</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="9" style="text-align:center; padding:18px; color:#666;">No registrations found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $idx => $row): ?>
                    <tr>
                        <td><?php echo $idx + 1; ?></td>
                        <td><?php echo htmlspecialchars((string)$row['booking_reference']); ?></td>
                        <td><?php echo htmlspecialchars((string)$row['name']); ?></td>
                        <td><?php echo htmlspecialchars(vs_format_mobile_for_display((string)$row['phone'])); ?></td>
                        <td>
                            <?php echo htmlspecialchars((string)$row['event_title']); ?><br>
                            <span style="font-size:11px;color:#666;"><?php echo htmlspecialchars((string)($row['event_date_display'] ?? $row['selected_event_date'])); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars((string)$row['package_name']); ?></td>
                        <td><?php echo (int)$row['persons']; ?></td>
                        <td><?php echo htmlspecialchars((string)$row['payment_status']); ?></td>
                        <td><?php echo htmlspecialchars((string)$row['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
