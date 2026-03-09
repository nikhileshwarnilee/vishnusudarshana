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
require_once __DIR__ . '/../../helpers/send_whatsapp.php';

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

$selectedEventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$audience = trim((string)($_POST['audience'] ?? 'all'));
$messageText = trim((string)($_POST['message'] ?? ''));

if (!in_array($audience, ['all', 'paid', 'pending'], true)) {
    $audience = 'all';
}

$info = '';
$error = '';
$previewRecipients = [];
$sentCount = 0;
$failedCount = 0;

$where = [];
$params = [];
if ($selectedEventId > 0) {
    $where[] = 'r.event_id = ?';
    $params[] = $selectedEventId;
}
if ($audience === 'paid') {
    $where[] = "r.payment_status = 'Paid'";
} elseif ($audience === 'pending') {
    $where[] = "r.payment_status IN ('Unpaid', 'Pending Verification')";
}
$whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$recipientSql = "SELECT
    r.phone,
    MAX(r.name) AS name,
    COUNT(*) AS registrations,
    MAX(r.id) AS max_id
FROM event_registrations r
$whereSql
GROUP BY r.phone
HAVING r.phone IS NOT NULL AND r.phone != ''
ORDER BY max_id DESC";
$recipientStmt = $pdo->prepare($recipientSql);
$recipientStmt->execute($params);
$previewRecipients = $recipientStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_broadcast'])) {
    if ($messageText === '') {
        $error = 'Message is required.';
    } elseif (empty($previewRecipients)) {
        $error = 'No recipients found for selected filters.';
    } else {
        foreach ($previewRecipients as $recipient) {
            $phone = trim((string)$recipient['phone']);
            if ($phone === '') {
                $failedCount++;
                continue;
            }

            $result = sendWhatsAppMessage(
                $phone,
                'APPOINTMENT_MESSAGE',
                [
                    'name' => (string)($recipient['name'] ?? 'Devotee'),
                    'message' => $messageText,
                ]
            );

            if (!empty($result['success'])) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        $info = 'Broadcast completed. Sent: ' . $sentCount . ', Failed: ' . $failedCount . '.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Broadcast</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f7f7fa; margin:0; }
        .admin-container { max-width:1200px; margin:0 auto; padding:24px 12px; }
        h1 { color:#800000; margin-bottom:14px; }
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 12px #e0bebe22; padding:14px; margin-bottom:16px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:10px; align-items:end; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        label { color:#800000; font-weight:700; font-size:0.9em; }
        select, textarea { width:100%; box-sizing:border-box; padding:9px 10px; border:1px solid #e0bebe; border-radius:8px; font-size:0.94em; }
        textarea { min-height:110px; resize:vertical; }
        .btn-main { display:inline-block; border:none; border-radius:8px; padding:9px 12px; font-weight:700; cursor:pointer; text-decoration:none; background:#800000; color:#fff; }
        .notice { margin:10px 0; padding:10px 12px; border-radius:8px; font-weight:600; }
        .notice.ok { background:#e7f7ed; color:#1a8917; }
        .notice.err { background:#ffeaea; color:#b00020; }
        .list-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px #e0bebe22; table-layout:auto; font-size:0.86em; }
        .list-table th, .list-table td { padding:8px 6px; border-bottom:1px solid #f3caca; text-align:left; }
        .list-table th { background:#f9eaea; color:#800000; font-size:0.9em; }
        .small { color:#666; font-size:0.85em; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <h1>WhatsApp Broadcast</h1>

    <?php if ($info !== ''): ?><div class="notice ok"><?php echo htmlspecialchars($info); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card">
        <form method="post" autocomplete="off">
            <div class="grid">
                <div class="form-group">
                    <label>Event (optional)</label>
                    <select name="event_id">
                        <option value="0">All Events</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo (int)$event['id']; ?>" <?php echo ($selectedEventId === (int)$event['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string)$event['title'] . ' (' . (string)($event['event_date_display'] ?? $event['event_date']) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Audience</label>
                    <select name="audience" required>
                        <option value="all" <?php echo ($audience === 'all') ? 'selected' : ''; ?>>All Registered Users</option>
                        <option value="paid" <?php echo ($audience === 'paid') ? 'selected' : ''; ?>>Only Paid Users</option>
                        <option value="pending" <?php echo ($audience === 'pending') ? 'selected' : ''; ?>>Pending Payment Users</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-top:10px;">
                <label>Broadcast Message</label>
                <textarea name="message" required placeholder="Type message to send via WhatsApp"><?php echo htmlspecialchars($messageText); ?></textarea>
            </div>

            <div class="small">Recipients matched: <strong><?php echo count($previewRecipients); ?></strong></div>

            <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                <button type="submit" class="btn-main" name="preview_only" value="1">Refresh Preview</button>
                <button type="submit" class="btn-main" name="send_broadcast" value="1" onclick="return confirm('Send this WhatsApp broadcast to selected recipients?');">Send Broadcast</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-top:0; color:#800000;">Recipient Preview</h3>
        <table class="list-table">
            <thead>
                <tr>
                    <th>Phone</th>
                    <th>Name</th>
                    <th>Registrations</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($previewRecipients)): ?>
                <tr><td colspan="3" style="text-align:center; padding:16px; color:#666;">No recipients for selected filters.</td></tr>
            <?php else: ?>
                <?php foreach ($previewRecipients as $recipient): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)$recipient['phone']); ?></td>
                        <td><?php echo htmlspecialchars((string)$recipient['name']); ?></td>
                        <td><?php echo (int)$recipient['registrations']; ?></td>
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
