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

if (isset($_GET['saved']) && (string)$_GET['saved'] === '1') {
    $message = 'Event saved successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event_id'])) {
    $eventId = (int)$_POST['delete_event_id'];
    if ($eventId > 0) {
        try {
            $imgStmt = $pdo->prepare('SELECT image FROM events WHERE id = ? LIMIT 1');
            $imgStmt->execute([$eventId]);
            $existingImage = (string)$imgStmt->fetchColumn();

            $deleteStmt = $pdo->prepare('DELETE FROM events WHERE id = ? LIMIT 1');
            $deleteStmt->execute([$eventId]);

            if ($deleteStmt->rowCount() > 0) {
                if ($existingImage !== '') {
                    $imgPath = __DIR__ . '/../../' . ltrim($existingImage, '/');
                    if (is_file($imgPath)) {
                        @unlink($imgPath);
                    }
                }
                $message = 'Event deleted successfully.';
            } else {
                $error = 'Event not found.';
            }
        } catch (Throwable $e) {
            $error = 'Failed to delete event.';
            error_log('Event delete failed: ' . $e->getMessage());
        }
    }
}

$listSql = "
    SELECT
        e.*,
        COALESCE(pkg.package_count, 0) AS package_count,
        COALESCE(reg.registration_count, 0) AS registration_count,
        COALESCE(reg.registered_persons, 0) AS registered_persons,
        COALESCE(reg.paid_count, 0) AS paid_count
    FROM events e
    LEFT JOIN (
        SELECT event_id, COUNT(*) AS package_count
        FROM event_packages
        GROUP BY event_id
    ) pkg ON pkg.event_id = e.id
    LEFT JOIN (
        SELECT
            event_id,
            SUM(CASE
                WHEN COALESCE(payment_status, '') <> 'Cancelled'
                 AND COALESCE(verification_status, '') <> 'Cancelled'
                THEN 1 ELSE 0
            END) AS registration_count,
            SUM(CASE
                WHEN COALESCE(payment_status, '') <> 'Cancelled'
                 AND COALESCE(verification_status, '') <> 'Cancelled'
                THEN COALESCE(persons, 0) ELSE 0
            END) AS registered_persons,
            SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END) AS paid_count
        FROM event_registrations
        GROUP BY event_id
    ) reg ON reg.event_id = e.id
    ORDER BY e.event_date DESC, e.id DESC
";
$events = $pdo->query($listSql)->fetchAll(PDO::FETCH_ASSOC);
foreach ($events as &$eventRow) {
    $eventRow['event_date_display'] = vs_event_get_event_date_display(
        $pdo,
        (int)$eventRow['id'],
        (string)($eventRow['event_date'] ?? ''),
        (string)($eventRow['event_type'] ?? 'single_day')
    );
}
unset($eventRow);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Events</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f7f7fa; margin:0; }
        .admin-container { max-width:1400px; margin:0 auto; padding:24px 12px; }
        h1 { color:#800000; margin-bottom:14px; }
        .actions { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
        .btn-main { display:inline-block; padding:9px 14px; border-radius:8px; background:#800000; color:#fff; text-decoration:none; font-weight:600; border:none; cursor:pointer; }
        .btn-main.alt { background:#0b7285; }
        .status-badge { display:inline-block; padding:5px 10px; border-radius:14px; font-size:0.84em; font-weight:700; }
        .status-active { background:#e5ffe5; color:#1a8917; }
        .status-closed { background:#ffeaea; color:#b00020; }
        .notice { margin:10px 0; padding:10px 12px; border-radius:8px; font-weight:600; }
        .notice.ok { background:#e7f7ed; color:#1a8917; }
        .notice.err { background:#ffeaea; color:#b00020; }
        .list-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px #e0bebe22; table-layout:auto; font-size:0.86em; }
        .list-table th, .list-table td { padding:9px 7px; border-bottom:1px solid #f3caca; text-align:left; vertical-align:top; }
        .list-table th { background:#f9eaea; color:#800000; font-weight:700; font-size:0.9em; }
        .list-table tbody tr:hover { background:#f3f7fa; }
        .small { color:#666; font-size:0.88em; }
        .event-thumb { width:64px; height:64px; object-fit:cover; border-radius:8px; border:1px solid #eee; background:#fafafa; }
        .row-actions { display:flex; flex-wrap:wrap; gap:6px; }
        .action-btn { display:inline-block; padding:6px 10px; border-radius:6px; text-decoration:none; font-weight:600; font-size:0.82em; border:none; cursor:pointer; }
        .action-edit { background:#ffc107; color:#333; }
        .action-packages { background:#17a2b8; color:#fff; }
        .action-registrations { background:#1a8917; color:#fff; }
        .action-reports { background:#6f42c1; color:#fff; }
        .action-delete { background:#dc3545; color:#fff; }
        .empty { text-align:center; padding:24px; color:#666; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <h1>All Events</h1>

    <div class="actions">
        <a class="btn-main" href="add-event.php">+ Add Event</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="notice ok"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="notice err"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <table class="list-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Event</th>
                <th>Date & Location</th>
                <th>Registration Window</th>
                <th>Status</th>
                <th>Packages</th>
                <th>Registrations</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($events)): ?>
            <tr><td class="empty" colspan="9">No events found.</td></tr>
        <?php else: ?>
            <?php foreach ($events as $event): ?>
                <tr>
                    <td><?php echo (int)$event['id']; ?></td>
                    <td>
                        <?php if (!empty($event['image'])): ?>
                            <img class="event-thumb" src="../../<?php echo htmlspecialchars(ltrim($event['image'], '/')); ?>" alt="Event Image">
                        <?php else: ?>
                            <span class="small">No image</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($event['title']); ?></strong><br>
                        <span class="small">Slug: <?php echo htmlspecialchars($event['slug']); ?></span>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars((string)($event['event_date_display'] ?? $event['event_date'])); ?></strong><br>
                        <span class="small"><?php echo htmlspecialchars($event['location']); ?></span>
                    </td>
                    <td>
                        <span class="small">From: <?php echo htmlspecialchars($event['registration_start']); ?></span><br>
                        <span class="small">To: <?php echo htmlspecialchars($event['registration_end']); ?></span>
                    </td>
                    <td>
                        <span class="status-badge <?php echo ($event['status'] === 'Active') ? 'status-active' : 'status-closed'; ?>">
                            <?php echo htmlspecialchars($event['status']); ?>
                        </span>
                    </td>
                    <td><?php echo (int)$event['package_count']; ?></td>
                    <td>
                        <strong><?php echo (int)$event['registration_count']; ?></strong>
                        <span class="small">(Paid: <?php echo (int)$event['paid_count']; ?>)</span><br>
                        <span class="small">Persons: <?php echo (int)$event['registered_persons']; ?></span>
                    </td>
                    <td>
                        <div class="row-actions">
                            <a class="action-btn action-edit" href="add-event.php?id=<?php echo (int)$event['id']; ?>">Edit</a>
                            <a class="action-btn action-packages" href="event-packages.php?event_id=<?php echo (int)$event['id']; ?>">Packages</a>
                            <a class="action-btn action-registrations" href="registrations.php?event_id=<?php echo (int)$event['id']; ?>">Registrations</a>
                            <a class="action-btn action-reports" href="event-reports.php?event_id=<?php echo (int)$event['id']; ?>">Reports</a>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this event? This will remove packages, registrations and payments for this event.');">
                                <input type="hidden" name="delete_event_id" value="<?php echo (int)$event['id']; ?>">
                                <button type="submit" class="action-btn action-delete">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="../includes/responsive-tables.js"></script>
</body>
</html>
