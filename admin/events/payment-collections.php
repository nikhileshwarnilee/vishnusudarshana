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

$normalizeDate = static function (string $rawDate): string {
    $rawDate = trim($rawDate);
    return ($rawDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) ? $rawDate : '';
};
$formatMoney = static function ($amount): string {
    return number_format((float)$amount, 2, '.', ',');
};
$roleLabel = static function (?string $role): string {
    $role = strtolower(trim((string)$role));
    if ($role === 'admin') {
        return 'Admin';
    }
    if ($role === 'staff') {
        return 'Staff';
    }
    if ($role === 'website') {
        return 'Website';
    }
    if ($role === 'system') {
        return 'System';
    }
    return 'Unknown / Legacy';
};

$events = $pdo->query("SELECT id, title, event_date, event_type, status FROM events ORDER BY status = 'Active' DESC, event_date DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
$eventsById = [];
foreach ($events as &$eventRow) {
    $eventRow['event_date_display'] = vs_event_get_event_date_display($pdo, (int)$eventRow['id'], (string)($eventRow['event_date'] ?? ''), (string)($eventRow['event_type'] ?? 'single_day'));
    $eventsById[(int)$eventRow['id']] = $eventRow;
}
unset($eventRow);

$todayDate = date('Y-m-d');
$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$fromDate = $normalizeDate((string)($_GET['from_date'] ?? date('Y-m-01')));
$toDate = $normalizeDate((string)($_GET['to_date'] ?? $todayDate));
$collectorRole = strtolower(trim((string)($_GET['collector_role'] ?? 'all')));
if (!in_array($collectorRole, ['all', 'admin', 'staff'], true)) {
    $collectorRole = 'all';
}
if ($fromDate !== '' && $toDate !== '' && strcmp($fromDate, $toDate) > 0) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}
if ($eventId > 0 && !isset($eventsById[$eventId])) {
    $eventId = 0;
}

$baseFromSql = " FROM event_payment_activity epa
    INNER JOIN event_registrations r ON r.id = epa.registration_id
    INNER JOIN events e ON e.id = r.event_id
    LEFT JOIN event_dates d ON d.id = r.event_date_id
    INNER JOIN event_packages p ON p.id = r.package_id ";
$buildWhere = static function (bool $includeRange = true, ?string $fixedDate = null) use ($eventId, $fromDate, $toDate, $collectorRole): array {
    $where = ['epa.amount > 0'];
    $params = [];
    if ($eventId > 0) {
        $where[] = 'r.event_id = ?';
        $params[] = $eventId;
    }
    if ($collectorRole !== 'all') {
        $where[] = "COALESCE(NULLIF(TRIM(epa.collected_by_role), ''), 'unknown') = ?";
        $params[] = $collectorRole;
    }
    if ($fixedDate !== null && $fixedDate !== '') {
        $where[] = 'DATE(epa.collected_at) = ?';
        $params[] = $fixedDate;
    } elseif ($includeRange) {
        if ($fromDate !== '') {
            $where[] = 'DATE(epa.collected_at) >= ?';
            $params[] = $fromDate;
        }
        if ($toDate !== '') {
            $where[] = 'DATE(epa.collected_at) <= ?';
            $params[] = $toDate;
        }
    }
    return [$where, $params];
};
[$rangeWhere, $rangeParams] = $buildWhere(true);
[$todayWhere, $todayParams] = $buildWhere(false, $todayDate);
$rangeWhereSql = ' WHERE ' . implode(' AND ', $rangeWhere);
$todayWhereSql = ' WHERE ' . implode(' AND ', $todayWhere);

$summaryStmt = $pdo->prepare("SELECT
        COUNT(*) AS total_entries,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(epa.status, '')) IN ('approved', 'paid') THEN epa.amount ELSE 0 END), 0) AS approved_amount,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(epa.status, '')) IN ('pending', 'pending verification') THEN epa.amount ELSE 0 END), 0) AS pending_amount,
        COALESCE(SUM(CASE WHEN COALESCE(epa.collected_by_role, '') = 'admin' AND LOWER(COALESCE(epa.status, '')) IN ('approved', 'paid') THEN epa.amount ELSE 0 END), 0) AS admin_amount,
        COALESCE(SUM(CASE WHEN COALESCE(epa.collected_by_role, '') = 'staff' AND LOWER(COALESCE(epa.status, '')) IN ('approved', 'paid') THEN epa.amount ELSE 0 END), 0) AS staff_amount,
        COALESCE(SUM(CASE WHEN COALESCE(epa.collected_by_role, '') NOT IN ('admin', 'staff') AND LOWER(COALESCE(epa.status, '')) IN ('approved', 'paid') THEN epa.amount ELSE 0 END), 0) AS other_amount,
        SUM(CASE WHEN epa.legacy_migrated = 1 OR COALESCE(NULLIF(TRIM(epa.collected_by_role), ''), 'unknown') = 'unknown' THEN 1 ELSE 0 END) AS legacy_entries
    $baseFromSql
    $rangeWhereSql");
$summaryStmt->execute($rangeParams);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$todaySummaryStmt = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(epa.status, '')) IN ('approved', 'paid') THEN epa.amount ELSE 0 END), 0) AS approved_amount,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(epa.status, '')) IN ('pending', 'pending verification') THEN epa.amount ELSE 0 END), 0) AS pending_amount
    $baseFromSql
    $todayWhereSql");
$todaySummaryStmt->execute($todayParams);
$todaySummary = $todaySummaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$dateWiseStmt = $pdo->prepare("SELECT
        DATE(epa.collected_at) AS collection_date,
        COUNT(*) AS entries_count,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(epa.status, '')) IN ('approved', 'paid') THEN epa.amount ELSE 0 END), 0) AS approved_amount,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(epa.status, '')) IN ('pending', 'pending verification') THEN epa.amount ELSE 0 END), 0) AS pending_amount,
        COALESCE(SUM(CASE WHEN COALESCE(epa.collected_by_role, '') = 'admin' AND LOWER(COALESCE(epa.status, '')) IN ('approved', 'paid') THEN epa.amount ELSE 0 END), 0) AS admin_amount,
        COALESCE(SUM(CASE WHEN COALESCE(epa.collected_by_role, '') = 'staff' AND LOWER(COALESCE(epa.status, '')) IN ('approved', 'paid') THEN epa.amount ELSE 0 END), 0) AS staff_amount
    $baseFromSql
    $rangeWhereSql
    GROUP BY DATE(epa.collected_at)
    ORDER BY collection_date DESC");
$dateWiseStmt->execute($rangeParams);
$dateWiseRows = $dateWiseStmt->fetchAll(PDO::FETCH_ASSOC);

$staffWiseStmt = $pdo->prepare("SELECT
        COALESCE(NULLIF(TRIM(epa.collected_by_user_name), ''), CONCAT('Staff #', COALESCE(epa.collected_by_user_id, 0))) AS collector_name,
        COUNT(*) AS entries_count,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(epa.status, '')) IN ('approved', 'paid') THEN epa.amount ELSE 0 END), 0) AS approved_amount,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(epa.status, '')) IN ('pending', 'pending verification') THEN epa.amount ELSE 0 END), 0) AS pending_amount
    $baseFromSql
    $rangeWhereSql
      AND COALESCE(epa.collected_by_role, '') = 'staff'
    GROUP BY collector_name
    ORDER BY approved_amount DESC, pending_amount DESC, collector_name ASC");
$staffWiseStmt->execute($rangeParams);
$staffWiseRows = $staffWiseStmt->fetchAll(PDO::FETCH_ASSOC);

$todayCollectorsStmt = $pdo->prepare("SELECT
        COALESCE(NULLIF(TRIM(epa.collected_by_user_name), ''), CASE
            WHEN COALESCE(epa.collected_by_role, '') = 'admin' THEN 'Admin'
            WHEN COALESCE(epa.collected_by_role, '') = 'staff' THEN CONCAT('Staff #', COALESCE(epa.collected_by_user_id, 0))
            WHEN COALESCE(epa.collected_by_role, '') = 'website' THEN 'Website'
            WHEN COALESCE(epa.collected_by_role, '') = 'system' THEN 'System'
            ELSE 'Unknown / Legacy'
        END) AS collector_name,
        COALESCE(epa.collected_by_role, 'unknown') AS collected_by_role,
        COUNT(*) AS entries_count,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(epa.status, '')) IN ('approved', 'paid') THEN epa.amount ELSE 0 END), 0) AS approved_amount,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(epa.status, '')) IN ('pending', 'pending verification') THEN epa.amount ELSE 0 END), 0) AS pending_amount
    $baseFromSql
    $todayWhereSql
    GROUP BY collector_name, collected_by_role
    ORDER BY approved_amount DESC, pending_amount DESC, collector_name ASC");
$todayCollectorsStmt->execute($todayParams);
$todayCollectorRows = $todayCollectorsStmt->fetchAll(PDO::FETCH_ASSOC);

$paymentMethodStmt = $pdo->prepare("SELECT
        COALESCE(NULLIF(TRIM(epa.payment_method), ''), 'Unknown') AS payment_method,
        COUNT(*) AS entries_count,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(epa.status, '')) IN ('approved', 'paid') THEN epa.amount ELSE 0 END), 0) AS approved_amount,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(epa.status, '')) IN ('pending', 'pending verification') THEN epa.amount ELSE 0 END), 0) AS pending_amount,
        COALESCE(SUM(CASE WHEN COALESCE(epa.collected_by_role, '') = 'admin' AND LOWER(COALESCE(epa.status, '')) IN ('approved', 'paid') THEN epa.amount ELSE 0 END), 0) AS admin_amount,
        COALESCE(SUM(CASE WHEN COALESCE(epa.collected_by_role, '') = 'staff' AND LOWER(COALESCE(epa.status, '')) IN ('approved', 'paid') THEN epa.amount ELSE 0 END), 0) AS staff_amount
    $baseFromSql
    $rangeWhereSql
    GROUP BY payment_method
    ORDER BY approved_amount DESC, pending_amount DESC, payment_method ASC");
$paymentMethodStmt->execute($rangeParams);
$paymentMethodRows = $paymentMethodStmt->fetchAll(PDO::FETCH_ASSOC);

$recentStmt = $pdo->prepare("SELECT
        epa.id,
        epa.payment_method,
        epa.payment_type,
        epa.amount,
        epa.transaction_id,
        epa.status,
        epa.collected_at,
        epa.collected_by_user_name,
        epa.collected_by_user_id,
        epa.collected_by_role,
        epa.reviewed_by_user_name,
        epa.reviewed_by_role,
        epa.reviewed_at,
        epa.legacy_migrated,
        r.id AS registration_id,
        r.booking_reference,
        r.name AS customer_name,
        r.phone,
        e.title AS event_title,
        e.event_type,
        COALESCE(d.event_date, e.event_date) AS selected_event_date,
        p.package_name
    $baseFromSql
    $rangeWhereSql
    ORDER BY epa.collected_at DESC, epa.id DESC
    LIMIT 200");
$recentStmt->execute($rangeParams);
$recentRows = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($recentRows as &$recentRow) {
    $recentRow['collector_name'] = trim((string)($recentRow['collected_by_user_name'] ?? ''));
    if ($recentRow['collector_name'] === '') {
        $recentRow['collector_name'] = $roleLabel((string)($recentRow['collected_by_role'] ?? ''));
    }
    $recentRow['event_date_display'] = vs_event_get_registration_date_display($pdo, $recentRow, (string)($recentRow['selected_event_date'] ?? ''));
}
unset($recentRow);

$pageTitle = 'Event Payment Collections';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f7f7fa;margin:0}.admin-shell{max-width:1380px;margin:0 auto;padding:24px 12px 40px}.page-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap;margin-bottom:16px}.page-head h1{margin:0;color:#800000}.page-head p{margin:4px 0 0;color:#666}.surface{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(128,0,0,.08);padding:16px;margin-bottom:16px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.form-group{display:flex;flex-direction:column;gap:6px}label{color:#800000;font-weight:700;font-size:.92rem}input,select{width:100%;box-sizing:border-box;border:1px solid #e0bebe;border-radius:8px;padding:10px 11px;font-size:.94rem;background:#fff}.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;border:none;border-radius:9px;padding:10px 14px;font-weight:700;cursor:pointer;text-decoration:none}.btn-primary{background:#800000;color:#fff}.btn-light{background:#f4f5f7;color:#333;border:1px solid #d8dde3}.summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-top:10px}.summary-card{border:1px solid #ead7d2;border-radius:12px;padding:12px;background:#fff}.summary-card span{display:block;color:#6b6f7b;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;font-weight:700}.summary-card strong{display:block;margin-top:6px;color:#2a303b;font-size:1.08rem}.summary-card small{display:block;margin-top:6px;color:#667085}.ok{background:#eef9f1;border-color:#cee7d5}.warn{background:#fff8e8;border-color:#f2ddad}.note{background:#f4fbff;border-color:#cfe2eb}.section-title{margin:0 0 12px;color:#800000;font-size:1.04rem}.notice{margin:0 0 12px;padding:11px 12px;border-radius:10px;background:#fff8e8;color:#8a5a00;border:1px solid #f2ddad}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:10px 9px;border-bottom:1px solid #f0e6e3;text-align:left;font-size:.92rem;vertical-align:top}th{color:#6e3d3d;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em}.stack{display:flex;flex-direction:column;gap:3px}.small{color:#666;font-size:.83rem}.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}.empty{padding:20px 10px;text-align:center;color:#777}.pill{display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;font-size:.74rem;font-weight:700}.pill.ok{background:#e7f7ed;color:#1a7f37}.pill.warn{background:#fff3d6;color:#8a5a00}.pill.err{background:#ffeaea;color:#b00020}.pill.note{background:#edf6ff;color:#0b7285}@media (max-width:760px){.admin-shell{padding:16px 8px 28px}}
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-shell">
    <div class="page-head">
        <div>
            <h1>Payment Collections</h1>
            <p>Track event payment collections by date, collector, and payment method from the admin panel.</p>
        </div>
        <div class="actions" style="margin-top:0;">
            <a class="btn btn-light" href="event-reports.php">Event Reports</a>
        </div>
    </div>
    <div class="surface">
        <form method="get" class="grid" autocomplete="off">
            <div class="form-group">
                <label>Event</label>
                <select name="event_id">
                    <option value="0">All Events</option>
                    <?php foreach ($events as $eventRow): ?>
                        <option value="<?php echo (int)$eventRow['id']; ?>" <?php echo ($eventId === (int)$eventRow['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$eventRow['title'] . ' - ' . (string)($eventRow['event_date_display'] ?? '')); ?>
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
            <div class="form-group">
                <label>Collector Type</label>
                <select name="collector_role">
                    <option value="all" <?php echo $collectorRole === 'all' ? 'selected' : ''; ?>>All Collectors</option>
                    <option value="admin" <?php echo $collectorRole === 'admin' ? 'selected' : ''; ?>>Admin Only</option>
                    <option value="staff" <?php echo $collectorRole === 'staff' ? 'selected' : ''; ?>>Staff Only</option>
                </select>
            </div>
            <div class="form-group" style="justify-content:flex-end;">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Apply Filters</button>
            </div>
        </form>
        <div class="summary-grid">
            <div class="summary-card ok"><span>Approved Collection</span><strong>Rs <?php echo $formatMoney((float)($summary['approved_amount'] ?? 0)); ?></strong><small><?php echo (int)($summary['total_entries'] ?? 0); ?> tracked entries in selected range</small></div>
            <div class="summary-card warn"><span>Pending Verification</span><strong>Rs <?php echo $formatMoney((float)($summary['pending_amount'] ?? 0)); ?></strong><small>Awaiting admin review</small></div>
            <div class="summary-card note"><span>Admin Collected</span><strong>Rs <?php echo $formatMoney((float)($summary['admin_amount'] ?? 0)); ?></strong><small>Approved amount collected by admin</small></div>
            <div class="summary-card note"><span>Staff Collected</span><strong>Rs <?php echo $formatMoney((float)($summary['staff_amount'] ?? 0)); ?></strong><small>Approved amount collected by staff</small></div>
            <div class="summary-card"><span>Other / Legacy</span><strong>Rs <?php echo $formatMoney((float)($summary['other_amount'] ?? 0)); ?></strong><small><?php echo (int)($summary['legacy_entries'] ?? 0); ?> entries without collector audit</small></div>
            <div class="summary-card"><span>Today Approved</span><strong>Rs <?php echo $formatMoney((float)($todaySummary['approved_amount'] ?? 0)); ?></strong><small>Today pending: Rs <?php echo $formatMoney((float)($todaySummary['pending_amount'] ?? 0)); ?></small></div>
        </div>
    </div>

    <?php if (!empty($summary['legacy_entries'])): ?>
        <div class="notice">Some older payment rows were backfilled from the existing payment snapshot, so they may appear under <strong>Unknown / Legacy</strong> when collector tracking was not available earlier.</div>
    <?php endif; ?>

    <div class="surface">
        <h2 class="section-title">Today's Collections</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Collector</th><th>Role</th><th>Approved</th><th>Pending</th><th>Entries</th></tr></thead>
                <tbody>
                <?php if (empty($todayCollectorRows)): ?>
                    <tr><td colspan="5" class="empty">No collections recorded today for the selected filters.</td></tr>
                <?php else: foreach ($todayCollectorRows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)$row['collector_name']); ?></td>
                        <td><?php echo htmlspecialchars($roleLabel((string)($row['collected_by_role'] ?? ''))); ?></td>
                        <td>Rs <?php echo $formatMoney((float)($row['approved_amount'] ?? 0)); ?></td>
                        <td>Rs <?php echo $formatMoney((float)($row['pending_amount'] ?? 0)); ?></td>
                        <td><?php echo (int)($row['entries_count'] ?? 0); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="surface">
        <h2 class="section-title">Staff-Wise Collections</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Staff</th><th>Approved</th><th>Pending</th><th>Entries</th></tr></thead>
                <tbody>
                <?php if (empty($staffWiseRows)): ?>
                    <tr><td colspan="4" class="empty">No staff collections found for this date range.</td></tr>
                <?php else: foreach ($staffWiseRows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)$row['collector_name']); ?></td>
                        <td>Rs <?php echo $formatMoney((float)($row['approved_amount'] ?? 0)); ?></td>
                        <td>Rs <?php echo $formatMoney((float)($row['pending_amount'] ?? 0)); ?></td>
                        <td><?php echo (int)($row['entries_count'] ?? 0); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid">
        <div class="surface">
            <h2 class="section-title">Date-Wise Collections</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Date</th><th>Approved</th><th>Pending</th><th>Admin</th><th>Staff</th><th>Entries</th></tr></thead>
                    <tbody>
                    <?php if (empty($dateWiseRows)): ?>
                        <tr><td colspan="6" class="empty">No collection activity found for the selected range.</td></tr>
                    <?php else: foreach ($dateWiseRows as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)($row['collection_date'] ?? '')); ?></td>
                            <td>Rs <?php echo $formatMoney((float)($row['approved_amount'] ?? 0)); ?></td>
                            <td>Rs <?php echo $formatMoney((float)($row['pending_amount'] ?? 0)); ?></td>
                            <td>Rs <?php echo $formatMoney((float)($row['admin_amount'] ?? 0)); ?></td>
                            <td>Rs <?php echo $formatMoney((float)($row['staff_amount'] ?? 0)); ?></td>
                            <td><?php echo (int)($row['entries_count'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="surface">
            <h2 class="section-title">By Payment Method</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Method</th><th>Approved</th><th>Pending</th><th>Admin</th><th>Staff</th><th>Entries</th></tr></thead>
                    <tbody>
                    <?php if (empty($paymentMethodRows)): ?>
                        <tr><td colspan="6" class="empty">No payment method data found.</td></tr>
                    <?php else: foreach ($paymentMethodRows as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)$row['payment_method']); ?></td>
                            <td>Rs <?php echo $formatMoney((float)($row['approved_amount'] ?? 0)); ?></td>
                            <td>Rs <?php echo $formatMoney((float)($row['pending_amount'] ?? 0)); ?></td>
                            <td>Rs <?php echo $formatMoney((float)($row['admin_amount'] ?? 0)); ?></td>
                            <td>Rs <?php echo $formatMoney((float)($row['staff_amount'] ?? 0)); ?></td>
                            <td><?php echo (int)($row['entries_count'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="surface">
        <h2 class="section-title">Recent Collection Activity</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Date & Time</th><th>Event</th><th>Customer</th><th>Amount</th><th>Method</th><th>Collected By</th><th>Status</th><th>Reference</th></tr></thead>
                <tbody>
                <?php if (empty($recentRows)): ?>
                    <tr><td colspan="8" class="empty">No payment activity found for the selected filters.</td></tr>
                <?php else: foreach ($recentRows as $row): ?>
                    <?php
                    $statusLower = strtolower(trim((string)($row['status'] ?? '')));
                    $statusClass = 'note';
                    if (in_array($statusLower, ['approved', 'paid'], true)) {
                        $statusClass = 'ok';
                    } elseif (in_array($statusLower, ['pending', 'pending verification'], true)) {
                        $statusClass = 'warn';
                    } elseif ($statusLower === 'rejected') {
                        $statusClass = 'err';
                    }
                    ?>
                    <tr>
                        <td><div class="stack"><span><?php echo htmlspecialchars((string)($row['collected_at'] ?? '')); ?></span><?php if (!empty($row['reviewed_at'])): ?><span class="small">Reviewed: <?php echo htmlspecialchars((string)$row['reviewed_at']); ?></span><?php endif; ?></div></td>
                        <td><div class="stack"><span><?php echo htmlspecialchars((string)($row['event_title'] ?? '')); ?></span><span class="small"><?php echo htmlspecialchars((string)($row['package_name'] ?? '')); ?></span><span class="small"><?php echo htmlspecialchars((string)($row['event_date_display'] ?? '')); ?></span></div></td>
                        <td><div class="stack"><span><?php echo htmlspecialchars((string)($row['customer_name'] ?? '')); ?></span><span class="small"><?php echo htmlspecialchars((string)($row['phone'] ?? '')); ?></span><a class="small" href="registration-view.php?id=<?php echo (int)($row['registration_id'] ?? 0); ?>&return=<?php echo urlencode('payment-collections.php?' . http_build_query($_GET)); ?>">View Registration</a></div></td>
                        <td>Rs <?php echo $formatMoney((float)($row['amount'] ?? 0)); ?></td>
                        <td><div class="stack"><span><?php echo htmlspecialchars((string)($row['payment_method'] ?? '')); ?></span><span class="small"><?php echo htmlspecialchars((string)($row['payment_type'] ?? '-')); ?></span></div></td>
                        <td><div class="stack"><span><?php echo htmlspecialchars((string)($row['collector_name'] ?? '')); ?></span><span class="small"><?php echo htmlspecialchars($roleLabel((string)($row['collected_by_role'] ?? ''))); ?></span><?php if (!empty($row['reviewed_by_user_name'])): ?><span class="small">Reviewed by <?php echo htmlspecialchars((string)$row['reviewed_by_user_name']); ?></span><?php endif; ?></div></td>
                        <td><span class="pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars((string)($row['status'] ?? '')); ?></span><?php if (!empty($row['legacy_migrated'])): ?><div class="small">Legacy</div><?php endif; ?></td>
                        <td><div class="stack"><span><?php echo htmlspecialchars((string)($row['transaction_id'] ?? '-')); ?></span><span class="small"><?php echo htmlspecialchars((string)($row['booking_reference'] ?? '')); ?></span></div></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
