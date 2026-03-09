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

$isValidDate = static function (string $value): bool {
    return $value !== '' && (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
};

$eventId = (int)($_GET['event_id'] ?? 0);
$paymentStatus = trim((string)($_GET['payment_status'] ?? ''));
$verificationStatus = trim((string)($_GET['verification_status'] ?? ''));
$checkinUserId = (int)($_GET['checkin_user_id'] ?? 0);
$checkinDateFrom = trim((string)($_GET['checkin_date_from'] ?? ''));
$checkinDateTo = trim((string)($_GET['checkin_date_to'] ?? ''));
$bookingReference = trim((string)($_GET['booking_reference'] ?? ''));

if (!$isValidDate($checkinDateFrom)) {
    $checkinDateFrom = '';
}
if (!$isValidDate($checkinDateTo)) {
    $checkinDateTo = '';
}
if ($checkinDateFrom !== '' && $checkinDateTo !== '' && strcmp($checkinDateFrom, $checkinDateTo) > 0) {
    [$checkinDateFrom, $checkinDateTo] = [$checkinDateTo, $checkinDateFrom];
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

$checkinUsers = [];
try {
    $checkinUsers = $pdo->query("SELECT DISTINCT
            COALESCE(r.checkin_by_user_id, 0) AS user_id,
            COALESCE(NULLIF(TRIM(r.checkin_by_user_name), ''), u.name, CONCAT('User #', COALESCE(r.checkin_by_user_id, 0))) AS user_name
        FROM event_registrations r
        LEFT JOIN users u ON u.id = r.checkin_by_user_id
        WHERE r.checkin_status = 1
          AND (
              COALESCE(r.checkin_by_user_id, 0) > 0
              OR COALESCE(NULLIF(TRIM(r.checkin_by_user_name), ''), '') <> ''
          )
        ORDER BY user_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $checkinUsers = [];
}

$where = ['r.checkin_status = 1'];
$params = [];
if ($eventId > 0) {
    $where[] = 'r.event_id = ?';
    $params[] = $eventId;
}
if ($paymentStatus !== '') {
    $where[] = 'r.payment_status = ?';
    $params[] = $paymentStatus;
}
if ($verificationStatus !== '') {
    $where[] = 'r.verification_status = ?';
    $params[] = $verificationStatus;
}
if ($checkinUserId > 0) {
    $where[] = 'r.checkin_by_user_id = ?';
    $params[] = $checkinUserId;
}
if ($checkinDateFrom !== '') {
    $where[] = 'DATE(r.checkin_time) >= ?';
    $params[] = $checkinDateFrom;
}
if ($checkinDateTo !== '') {
    $where[] = 'DATE(r.checkin_time) <= ?';
    $params[] = $checkinDateTo;
}
if ($bookingReference !== '') {
    $where[] = 'r.booking_reference LIKE ?';
    $params[] = '%' . $bookingReference . '%';
}

$listStmt = $pdo->prepare("SELECT
        r.id,
        r.event_id,
        r.event_date_id,
        r.package_id,
        r.booking_reference,
        r.name,
        r.phone,
        r.persons,
        r.payment_status,
        r.verification_status,
        r.checkin_time,
        r.checkin_by_user_id,
        r.checkin_by_user_name,
        e.title AS event_title,
        e.event_type,
        COALESCE(d.event_date, e.event_date) AS selected_event_date,
        p.package_name,
        COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS package_price_total,
        COALESCE(ep.amount_paid, 0) AS amount_paid,
        COALESCE(ep.remaining_amount, 0) AS remaining_amount,
        COALESCE(ep.payment_method, '') AS payment_method,
        COALESCE(ep.status, '') AS payment_record_status
    FROM event_registrations r
    INNER JOIN events e ON e.id = r.event_id
    LEFT JOIN event_dates d ON d.id = r.event_date_id
    INNER JOIN event_packages p ON p.id = r.package_id
    LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
    LEFT JOIN event_payments ep ON ep.registration_id = r.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY r.checkin_time DESC, r.id DESC");
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$summary = ['count' => 0, 'persons' => 0, 'pending_count' => 0, 'pending_amount' => 0, 'paid_amount' => 0];
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

    $persons = max((int)($row['persons'] ?? 1), 1);
    $total = round((float)($row['package_price_total'] ?? 0) * $persons, 2);
    $paid = round(max((float)($row['amount_paid'] ?? 0), 0), 2);
    if (strtolower(trim((string)($row['payment_status'] ?? ''))) === 'paid' && $paid <= 0) {
        $paid = $total;
    }
    $remaining = round((float)($row['remaining_amount'] ?? 0), 2);
    if ($remaining <= 0 && strtolower(trim((string)($row['payment_status'] ?? ''))) !== 'cancelled') {
        $remaining = round(max($total - $paid, 0), 2);
    }

    $row['amount_total'] = $total;
    $row['amount_paid'] = $paid;
    $row['amount_due'] = $remaining;

    $summary['count']++;
    $summary['persons'] += $persons;
    $summary['paid_amount'] += $paid;
    if ($remaining > 0 && !in_array(strtolower((string)$row['payment_status']), ['paid', 'cancelled'], true)) {
        $summary['pending_count']++;
        $summary['pending_amount'] += $remaining;
    }
}
unset($row);

$queryParams = $_GET;
$currentUrl = 'checkin-history.php';
if (!empty($queryParams)) {
    $currentUrl .= '?' . http_build_query($queryParams);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-In History</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f7f7fa; margin:0; }
        .admin-container { max-width:1500px; margin:0 auto; padding:24px 12px; }
        h1 { color:#800000; margin-bottom:14px; }
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 12px #e0bebe22; padding:14px; margin-bottom:16px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:10px; align-items:end; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        label { color:#800000; font-weight:700; font-size:0.9em; }
        input, select { width:100%; box-sizing:border-box; padding:9px 10px; border:1px solid #e0bebe; border-radius:8px; font-size:0.94em; }
        .btn { display:inline-block; border:none; border-radius:8px; padding:8px 11px; font-weight:700; cursor:pointer; text-decoration:none; background:#800000; color:#fff; }
        .btn-alt { background:#0b7285; }
        .summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:10px; margin-bottom:14px; }
        .summary-card { border:1px solid #efd3d3; border-radius:10px; padding:10px 11px; background:#fffaf9; }
        .summary-label { margin:0; font-size:0.78rem; text-transform:uppercase; letter-spacing:.04em; color:#7a5151; font-weight:700; }
        .summary-value { margin:4px 0 0; color:#800000; font-size:1.15rem; font-weight:800; }
        .list-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; table-layout:auto; font-size:0.86em; }
        .list-table th, .list-table td { padding:8px 6px; border-bottom:1px solid #f3caca; text-align:left; vertical-align:top; }
        .list-table th { background:#f9eaea; color:#800000; font-size:0.9em; }
        .small { color:#666; font-size:0.84em; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <h1>Check-In History</h1>

    <div class="card">
        <form method="get" class="grid">
            <div class="form-group"><label>Event</label><select name="event_id"><option value="">All</option><?php foreach ($events as $event): ?><option value="<?php echo (int)$event['id']; ?>" <?php echo ($eventId === (int)$event['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$event['title'] . ' (' . ($event['event_date_display'] ?? $event['event_date']) . ')'); ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Payment Status</label><select name="payment_status"><option value="">All</option><?php foreach (['Unpaid', 'Partial Paid', 'Paid', 'Pending Verification', 'Failed', 'Cancelled'] as $st): ?><option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($paymentStatus === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Verification</label><select name="verification_status"><option value="">All</option><?php foreach (['Pending', 'Approved', 'Auto Verified', 'Rejected'] as $st): ?><option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($verificationStatus === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Checked-In By</label><select name="checkin_user_id"><option value="">All</option><?php foreach ($checkinUsers as $checkinUser): ?><?php $tmpUserId = (int)($checkinUser['user_id'] ?? 0); if ($tmpUserId <= 0) { continue; } ?><option value="<?php echo $tmpUserId; ?>" <?php echo ($checkinUserId === $tmpUserId) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($checkinUser['user_name'] ?? ('User #' . $tmpUserId))); ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Check-In From</label><input type="date" name="checkin_date_from" value="<?php echo htmlspecialchars($checkinDateFrom); ?>"></div>
            <div class="form-group"><label>Check-In To</label><input type="date" name="checkin_date_to" value="<?php echo htmlspecialchars($checkinDateTo); ?>"></div>
            <div class="form-group"><label>Booking Ref</label><input type="text" name="booking_reference" value="<?php echo htmlspecialchars($bookingReference); ?>" placeholder="Search booking ref"></div>
            <div class="form-group"><button type="submit" class="btn">Apply</button></div>
            <div class="form-group"><a class="btn btn-alt" href="checkin-history.php">Reset</a></div>
        </form>
    </div>

    <div class="summary-grid">
        <div class="summary-card"><p class="summary-label">Total Check-Ins</p><p class="summary-value"><?php echo number_format((float)$summary['count'], 0, '.', ''); ?></p></div>
        <div class="summary-card"><p class="summary-label">Total Persons</p><p class="summary-value"><?php echo number_format((float)$summary['persons'], 0, '.', ''); ?></p></div>
        <div class="summary-card"><p class="summary-label">Pending Payments</p><p class="summary-value"><?php echo number_format((float)$summary['pending_count'], 0, '.', ''); ?></p></div>
        <div class="summary-card"><p class="summary-label">Pending Amount</p><p class="summary-value">Rs <?php echo number_format((float)$summary['pending_amount'], 0, '.', ''); ?></p></div>
    </div>

    <div class="card">
        <div style="overflow:auto;">
            <table class="list-table">
                <thead>
                <tr>
                    <th>ID</th><th>Booking Ref</th><th>Event</th><th>Package</th><th>Name / Phone</th><th>Persons</th><th>Check-In By</th><th>Check-In Time</th><th>Payment</th><th>Verification</th><th>Method</th><th>Total</th><th>Paid</th><th>Due</th><th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="15" style="text-align:center; padding:20px; color:#666;">No checked-in registrations found for selected filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $checkinBy = trim((string)($row['checkin_by_user_name'] ?? ''));
                        if ($checkinBy === '' && (int)($row['checkin_by_user_id'] ?? 0) > 0) {
                            $checkinBy = 'User #' . (int)$row['checkin_by_user_id'];
                        }
                        $pendingCollectUrl = 'pending-payments.php?tab=checkin&registration_id=' . (int)$row['id'] . '&return=' . urlencode($currentUrl);
                        ?>
                        <tr>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars((string)$row['booking_reference']); ?></strong></td>
                            <td><strong><?php echo htmlspecialchars((string)$row['event_title']); ?></strong><br><span class="small"><?php echo htmlspecialchars((string)$row['event_date_display']); ?></span></td>
                            <td><?php echo htmlspecialchars((string)$row['package_name']); ?></td>
                            <td><strong><?php echo htmlspecialchars((string)$row['name']); ?></strong><br><span class="small"><?php echo htmlspecialchars((string)$row['phone']); ?></span></td>
                            <td><?php echo (int)$row['persons']; ?></td>
                            <td><?php echo $checkinBy !== '' ? htmlspecialchars($checkinBy) : '-'; ?></td>
                            <td><?php echo !empty($row['checkin_time']) ? htmlspecialchars((string)$row['checkin_time']) : '-'; ?></td>
                            <td><?php echo htmlspecialchars((string)$row['payment_status']); ?></td>
                            <td><?php echo htmlspecialchars((string)$row['verification_status']); ?></td>
                            <td><?php echo !empty($row['payment_method']) ? htmlspecialchars((string)$row['payment_method']) : '-'; ?></td>
                            <td>Rs <?php echo number_format((float)$row['amount_total'], 0, '.', ''); ?></td>
                            <td>Rs <?php echo number_format((float)$row['amount_paid'], 0, '.', ''); ?></td>
                            <td>Rs <?php echo number_format((float)$row['amount_due'], 0, '.', ''); ?></td>
                            <td>
                                <a class="btn btn-alt" href="registration-view.php?id=<?php echo (int)$row['id']; ?>&return=<?php echo urlencode($currentUrl); ?>">View</a>
                                <?php if ((float)$row['amount_due'] > 0 && !in_array(strtolower((string)$row['payment_status']), ['paid', 'cancelled'], true)): ?>
                                    <a class="btn" href="<?php echo htmlspecialchars($pendingCollectUrl); ?>">Collect</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="../includes/responsive-tables.js"></script>
</body>
</html>

