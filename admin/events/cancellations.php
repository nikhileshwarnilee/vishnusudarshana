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
$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : (int)($_POST['event_id'] ?? 0);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'], $_POST['refund_action'])) {
    $cancelId = (int)$_POST['cancel_id'];
    $action = trim((string)$_POST['refund_action']);
    if ($cancelId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        $error = 'Invalid action.';
    } else {
        $status = $action === 'approve' ? 'processed' : 'rejected';
        $stmt = $pdo->prepare("UPDATE event_cancellations SET refund_status = ? WHERE id = ?");
        $stmt->execute([$status, $cancelId]);
        if ($stmt->rowCount() > 0) {
            $message = $action === 'approve' ? 'Refund marked as processed.' : 'Refund request rejected.';
        } else {
            $error = 'Cancellation record not found.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['request_action'])) {
    $requestId = (int)$_POST['request_id'];
    $requestAction = strtolower(trim((string)$_POST['request_action']));
    $decisionNote = trim((string)($_POST['decision_note'] ?? ''));
    if ($requestId <= 0 || !in_array($requestAction, ['approve', 'reject'], true)) {
        $error = 'Invalid cancellation request action.';
    } else {
        try {
            $reviewResult = vs_event_review_cancellation_request(
                $pdo,
                $requestId,
                $requestAction,
                (int)($_SESSION['user_id'] ?? 0),
                trim((string)($_SESSION['user_name'] ?? '')),
                $decisionNote
            );
            $message = ((string)($reviewResult['request_status'] ?? '') === 'approved')
                ? 'Cancellation request approved. Booking cancelled and refund marked processed.'
                : 'Cancellation request rejected.';
        } catch (Throwable $e) {
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to process cancellation request.';
        }
    }
}

$where = '';
$params = [];
if ($eventId > 0) {
    $where = 'WHERE r.event_id = ?';
    $params[] = $eventId;
}

$requestStmt = $pdo->prepare("SELECT
        cr.*,
        r.id AS registration_id,
        r.event_id,
        r.event_date_id,
        r.booking_reference,
        r.name,
        r.phone,
        r.persons,
        r.payment_status,
        r.verification_status,
        e.title AS event_title,
        e.event_type,
        COALESCE(d.event_date, e.event_date) AS event_date,
        p.package_name,
        COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS package_price_total,
        COALESCE(ep.amount_paid, ep.amount, 0) AS paid_amount
    FROM event_cancellation_requests cr
    INNER JOIN event_registrations r ON r.id = cr.registration_id
    INNER JOIN events e ON e.id = r.event_id
    LEFT JOIN event_dates d ON d.id = r.event_date_id
    INNER JOIN event_packages p ON p.id = r.package_id
    LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
    LEFT JOIN event_payments ep ON ep.registration_id = r.id
    $where
      " . ($where !== '' ? " AND " : " WHERE ") . "cr.request_status = 'pending'
      AND cr.request_source = 'online'
    ORDER BY cr.requested_at DESC, cr.id DESC");
$requestStmt->execute($params);
$pendingRequests = $requestStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($pendingRequests as &$pendingRow) {
    $pendingRow['event_date_display'] = vs_event_get_registration_date_display(
        $pdo,
        $pendingRow,
        (string)($pendingRow['event_date'] ?? '')
    );
}
unset($pendingRow);

$stmt = $pdo->prepare("SELECT
        c.*,
        r.id AS registration_id,
        r.event_id,
        r.event_date_id,
        r.booking_reference,
        r.name,
        r.phone,
        r.persons,
        r.payment_status,
        r.verification_status,
        e.title AS event_title,
        e.event_type,
        COALESCE(d.event_date, e.event_date) AS event_date,
        p.package_name,
        COALESCE(ep.amount_paid, ep.amount, 0) AS paid_amount
    FROM event_cancellations c
    INNER JOIN event_registrations r ON r.id = c.registration_id
    INNER JOIN events e ON e.id = r.event_id
    LEFT JOIN event_dates d ON d.id = r.event_date_id
    INNER JOIN event_packages p ON p.id = r.package_id
    LEFT JOIN event_payments ep ON ep.registration_id = r.id
    $where
    ORDER BY c.cancelled_at DESC, c.id DESC");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as &$row) {
    $row['event_date_display'] = vs_event_get_registration_date_display(
        $pdo,
        $row,
        (string)($row['event_date'] ?? '')
    );
}
unset($row);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Cancellations</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f7f7fa;margin:0}
        .admin-container{max-width:1300px;margin:0 auto;padding:24px 12px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 12px #e0bebe22;padding:14px;margin-bottom:16px}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;align-items:end}
        .form-group{display:flex;flex-direction:column;gap:6px}
        label{color:#800000;font-weight:700;font-size:.9em}
        select{width:100%;box-sizing:border-box;padding:9px 10px;border:1px solid #e0bebe;border-radius:8px}
        .btn-main{display:inline-block;border:none;border-radius:8px;padding:9px 12px;font-weight:700;cursor:pointer;text-decoration:none;background:#800000;color:#fff}
        .notice{margin:10px 0;padding:10px 12px;border-radius:8px;font-weight:600}
        .ok{background:#e7f7ed;color:#1a8917}.err{background:#ffeaea;color:#b00020}
        .tag{display:inline-block;padding:4px 9px;border-radius:12px;font-size:.82em;font-weight:700}
        .pending{background:#fff4db;color:#b36b00}.processed{background:#e5ffe5;color:#1a8917}.rejected{background:#ffeaea;color:#b00020}
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1 style="color:#800000;">Cancellations & Refunds</h1>
    <?php if ($message !== ''): ?><div class="notice ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card">
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
            <div class="form-group"><button type="submit" class="btn-main">Apply Filter</button></div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin:0 0 10px;color:#800000;">Pending Online Cancellation Requests</h2>
        <table class="list-table">
            <thead>
                <tr>
                    <th>Request ID</th><th>Booking Ref</th><th>Event</th><th>Package</th><th>Name / Phone</th><th>Type</th><th>Requested Persons</th><th>Current Persons</th><th>Paid</th><th>Reason</th><th>Requested At</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($pendingRequests)): ?>
                <tr><td colspan="12" style="text-align:center;padding:18px;color:#666;">No pending cancellation requests.</td></tr>
            <?php else: ?>
                <?php foreach ($pendingRequests as $requestRow): ?>
                    <tr>
                        <td><?php echo (int)($requestRow['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string)($requestRow['booking_reference'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string)($requestRow['event_title'] ?? '')); ?><br><span style="font-size:11px;color:#666;"><?php echo htmlspecialchars((string)($requestRow['event_date_display'] ?? $requestRow['event_date'] ?? '')); ?></span></td>
                        <td><?php echo htmlspecialchars((string)($requestRow['package_name'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string)($requestRow['name'] ?? '')); ?><br><span style="font-size:11px;color:#666;"><?php echo htmlspecialchars((string)($requestRow['phone'] ?? '')); ?></span></td>
                        <td><?php echo htmlspecialchars(ucfirst((string)($requestRow['request_type'] ?? 'full'))); ?></td>
                        <td><?php echo (int)($requestRow['requested_persons'] ?? 0); ?></td>
                        <td><?php echo (int)($requestRow['persons'] ?? 0); ?></td>
                        <td>Rs <?php echo number_format((float)($requestRow['paid_amount'] ?? 0), 2); ?></td>
                        <td><?php echo nl2br(htmlspecialchars((string)($requestRow['cancel_reason'] ?? '-'))); ?></td>
                        <td><?php echo htmlspecialchars((string)($requestRow['requested_at'] ?? '-')); ?></td>
                        <td>
                            <form method="post" style="display:flex;gap:6px;flex-wrap:wrap;align-items:flex-start;">
                                <input type="hidden" name="event_id" value="<?php echo (int)$eventId; ?>">
                                <input type="hidden" name="request_id" value="<?php echo (int)($requestRow['id'] ?? 0); ?>">
                                <input type="text" name="decision_note" placeholder="Decision note (optional)" style="min-width:180px;">
                                <button type="submit" name="request_action" value="approve" class="btn-main" style="background:#1a8917;" onclick="return confirm('Approve request? Booking will be cancelled and refund marked processed.');">Approve</button>
                                <button type="submit" name="request_action" value="reject" class="btn-main" style="background:#dc3545;" onclick="return confirm('Reject this cancellation request?');">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2 style="margin:0 0 10px;color:#800000;">Processed Cancellations & Refunds</h2>
        <table class="list-table">
            <thead>
                <tr>
                    <th>ID</th><th>Booking Ref</th><th>Event</th><th>Package</th><th>Name / Phone</th><th>Type</th><th>Cancelled Persons</th><th>Current Persons</th><th>Paid</th><th>Refund Amount</th><th>Reason</th><th>Status</th><th>Cancelled At</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="14" style="text-align:center;padding:18px;color:#666;">No cancellations found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <?php $statusClass = (string)$row['refund_status']; if (!in_array($statusClass, ['pending','processed','rejected'], true)) { $statusClass = 'pending'; } ?>
                    <?php $displayRefundAmount = vs_event_resolve_refund_amount($row); ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><?php echo htmlspecialchars((string)$row['booking_reference']); ?></td>
                        <td><?php echo htmlspecialchars((string)$row['event_title']); ?><br><span style="font-size:11px;color:#666;"><?php echo htmlspecialchars((string)($row['event_date_display'] ?? $row['event_date'])); ?></span></td>
                        <td><?php echo htmlspecialchars((string)$row['package_name']); ?></td>
                        <td><?php echo htmlspecialchars((string)$row['name']); ?><br><span style="font-size:11px;color:#666;"><?php echo htmlspecialchars((string)$row['phone']); ?></span></td>
                        <td><?php echo htmlspecialchars(ucfirst((string)($row['cancellation_type'] ?? 'full'))); ?></td>
                        <td><?php echo (int)($row['cancelled_persons'] ?? 0); ?></td>
                        <td><?php echo (int)$row['persons']; ?></td>
                        <td>Rs <?php echo number_format((float)$row['paid_amount'], 2); ?></td>
                        <td>Rs <?php echo number_format($displayRefundAmount, 2); ?></td>
                        <td><?php echo nl2br(htmlspecialchars((string)$row['cancel_reason'])); ?></td>
                        <td><span class="tag <?php echo $statusClass; ?>"><?php echo htmlspecialchars((string)$row['refund_status']); ?></span></td>
                        <td><?php echo htmlspecialchars((string)$row['cancelled_at']); ?></td>
                        <td>
                            <?php if ((string)$row['refund_status'] === 'pending'): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="event_id" value="<?php echo (int)$eventId; ?>">
                                    <input type="hidden" name="cancel_id" value="<?php echo (int)$row['id']; ?>">
                                    <button type="submit" name="refund_action" value="approve" class="btn-main" style="background:#1a8917;" onclick="return confirm('Mark refund as processed?');">Approve</button>
                                    <button type="submit" name="refund_action" value="reject" class="btn-main" style="background:#dc3545;" onclick="return confirm('Reject this refund?');">Reject</button>
                                </form>
                            <?php else: ?>
                                <span style="font-size:12px;color:#666;">Completed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="../includes/responsive-tables.js"></script>
</body>
</html>
