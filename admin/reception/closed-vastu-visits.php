<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/vastu-visits-common.php';

vs_reception_vastu_bootstrap($pdo);

$redirectBase = 'closed-vastu-visits.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'reopen_visit') {
        $visitId = (int)($_POST['visit_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE vastu_visits SET status = 'open', closed_at = NULL WHERE id = ? LIMIT 1");
        $stmt->execute([$visitId]);
        header('Location: ' . $redirectBase . '?notice=' . rawurlencode('Vastu visit moved back to open list.') . '&type=success');
        exit;
    }

    if ($action === 'delete_visit') {
        $visitId = (int)($_POST['visit_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM vastu_visits WHERE id = ? LIMIT 1");
        $stmt->execute([$visitId]);
        header('Location: ' . $redirectBase . '?notice=' . rawurlencode('Vastu visit deleted successfully.') . '&type=success');
        exit;
    }
}

$groupedVisits = vs_reception_vastu_fetch_grouped_visits($pdo, 'closed');
$notice = trim((string)($_GET['notice'] ?? ''));
$noticeType = trim((string)($_GET['type'] ?? 'success'));

require_once __DIR__ . '/../includes/top-menu.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Closed Vastu Visits</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
        .admin-container { max-width: 1440px; margin: 0 auto; padding: 24px 12px; }
        .page-head { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:18px; }
        h1 { color:#800000; margin:0; }
        .head-actions { display:flex; gap:10px; flex-wrap:wrap; }
        .btn-main { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 16px; background:#800000; color:#fff; border:none; border-radius:8px; text-decoration:none; font-weight:600; cursor:pointer; }
        .btn-main:hover { background:#600000; color:#fff; }
        .btn-alt { background:#6c757d; }
        .btn-alt:hover { background:#545b62; }
        .notice { padding:12px 14px; border-radius:10px; font-weight:600; margin-bottom:16px; }
        .notice.success { background:#eaf8ea; color:#176117; }
        .notice.danger { background:#ffeaea; color:#a40000; }
        .area-card { background:#fff; border-radius:16px; box-shadow:0 2px 14px #e0bebe22; margin-bottom:20px; overflow:hidden; }
        .area-head { display:flex; justify-content:space-between; align-items:center; gap:12px; background:#f1f8f1; color:#176117; padding:14px 16px; font-weight:700; }
        .area-count { font-size:0.9em; background:#fff; color:#176117; border-radius:999px; padding:4px 10px; }
        table { width:100%; border-collapse:collapse; background:#fff; table-layout:auto; font-size:0.9em; }
        table th, table td { padding:10px 8px; text-align:left; border-bottom:1px solid #e7f0e7; vertical-align:top; }
        table thead { background:#f8fcf8; color:#176117; }
        .details-cell { min-width:240px; white-space:pre-wrap; }
        .address-cell { min-width:220px; white-space:pre-wrap; }
        .status-badge { display:inline-block; padding:5px 10px; border-radius:999px; background:#e5ffe5; color:#176117; font-weight:700; font-size:0.85em; text-transform:capitalize; }
        .action-row { display:flex; gap:8px; flex-wrap:wrap; }
        .btn-inline { display:inline-flex; align-items:center; justify-content:center; padding:7px 12px; border:none; border-radius:7px; color:#fff; font-weight:600; text-decoration:none; cursor:pointer; }
        .btn-edit { background:#0d6efd; }
        .btn-reopen { background:#176117; }
        .btn-delete { background:#b00020; }
        .stack { display:flex; flex-direction:column; gap:4px; }
        .small { font-size:0.84em; color:#666; }
        .empty-state { padding:18px; text-align:center; color:#777; background:#fff; border-radius:16px; box-shadow:0 2px 14px #e0bebe22; }
        @media (max-width: 768px) {
            .head-actions, .btn-main { width:100%; }
            .action-row { flex-direction:column; }
            .btn-inline { width:100%; }
        }
    </style>
</head>
<body>
<div class="admin-container">
    <div class="page-head">
        <h1>Closed Vastu Visits</h1>
        <div class="head-actions">
            <a class="btn-main btn-alt" href="vastu-visits.php">Open Vastu Visits</a>
            <a class="btn-main" href="vastu-visit-areas.php">Manage Areas</a>
        </div>
    </div>

    <?php if ($notice !== ''): ?>
        <div class="notice <?php echo htmlspecialchars(in_array($noticeType, ['success', 'danger'], true) ? $noticeType : 'success'); ?>">
            <?php echo htmlspecialchars($notice); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($groupedVisits)): ?>
        <div class="empty-state">No closed vastu visits found.</div>
    <?php else: ?>
        <?php foreach ($groupedVisits as $areaName => $rows): ?>
            <div class="area-card">
                <div class="area-head">
                    <span><?php echo htmlspecialchars($areaName); ?></span>
                    <span class="area-count"><?php echo count($rows); ?> visits</span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Vastu Type</th>
                            <th>Details</th>
                            <th>Address</th>
                            <th>Closed</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $addressParts = array_filter([
                            trim((string)($row['address_line1'] ?? '')),
                            trim((string)($row['address_line2'] ?? '')),
                            trim((string)($row['landmark'] ?? '')),
                        ], static function ($value) {
                            return $value !== '';
                        });
                        $closedAt = trim((string)($row['closed_at'] ?? $row['updated_at'] ?? $row['created_at'] ?? ''));
                        ?>
                        <tr>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td><?php echo htmlspecialchars((string)($row['customer_name'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars(function_exists('vs_format_mobile_for_display') ? vs_format_mobile_for_display((string)($row['contact_number'] ?? '')) : (string)($row['contact_number'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($row['vastu_type'] ?? '')); ?></td>
                            <td class="details-cell"><?php echo nl2br(htmlspecialchars((string)($row['details'] ?? ''))); ?></td>
                            <td class="address-cell"><?php echo $addressParts ? nl2br(htmlspecialchars(implode("\n", $addressParts))) : '<span class="small">-</span>'; ?></td>
                            <td>
                                <div class="stack">
                                    <span><?php echo htmlspecialchars(date('d M Y', strtotime($closedAt))); ?></span>
                                    <span class="small"><?php echo htmlspecialchars(date('h:i A', strtotime($closedAt))); ?></span>
                                </div>
                            </td>
                            <td><span class="status-badge"><?php echo htmlspecialchars((string)($row['status'] ?? 'closed')); ?></span></td>
                            <td>
                                <div class="action-row">
                                    <a class="btn-inline btn-edit" href="vastu-visits.php?edit=<?php echo (int)$row['id']; ?>&return=closed-vastu-visits.php">Edit</a>
                                    <form method="post" onsubmit="return confirm('Move this vastu visit back to open list?');">
                                        <input type="hidden" name="action" value="reopen_visit">
                                        <input type="hidden" name="visit_id" value="<?php echo (int)$row['id']; ?>">
                                        <button type="submit" class="btn-inline btn-reopen">Reopen</button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Delete this vastu visit permanently?');">
                                        <input type="hidden" name="action" value="delete_visit">
                                        <input type="hidden" name="visit_id" value="<?php echo (int)$row['id']; ?>">
                                        <button type="submit" class="btn-inline btn-delete">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<script src="../includes/responsive-tables.js"></script>
</body>
</html>
