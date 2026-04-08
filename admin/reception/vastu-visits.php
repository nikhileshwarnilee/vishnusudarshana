<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/vastu-visits-common.php';

vs_reception_vastu_bootstrap($pdo);

$redirectBase = 'vastu-visits.php';
$defaultForm = [
    'customer_name' => '',
    'contact_number' => '',
    'vastu_type' => '',
    'area_id' => '',
    'address_line1' => '',
    'address_line2' => '',
    'landmark' => '',
    'details' => '',
];
$form = $defaultForm;
$errors = [];
$editVisit = null;
$returnPage = vs_reception_vastu_allowed_return_page($_POST['return_page'] ?? $_GET['return'] ?? $redirectBase);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'save_visit') {
        $visitId = (int)($_POST['visit_id'] ?? 0);
        $existingVisit = $visitId > 0 ? vs_reception_vastu_find_visit($pdo, $visitId) : null;
        $status = $existingVisit ? (string)($existingVisit['status'] ?? 'open') : 'open';
        $returnPage = vs_reception_vastu_allowed_return_page($_POST['return_page'] ?? $returnPage);

        $form = [
            'customer_name' => trim((string)($_POST['customer_name'] ?? '')),
            'contact_number' => trim((string)($_POST['contact_number'] ?? '')),
            'vastu_type' => trim((string)($_POST['vastu_type'] ?? '')),
            'area_id' => trim((string)($_POST['area_id'] ?? '')),
            'address_line1' => trim((string)($_POST['address_line1'] ?? '')),
            'address_line2' => trim((string)($_POST['address_line2'] ?? '')),
            'landmark' => trim((string)($_POST['landmark'] ?? '')),
            'details' => trim((string)($_POST['details'] ?? '')),
        ];

        if ($form['customer_name'] === '') {
            $errors[] = 'Customer name is required.';
        }
        if ($form['contact_number'] === '') {
            $errors[] = 'Customer number is required.';
        }
        if ($form['vastu_type'] === '') {
            $errors[] = 'Vastu type is required.';
        }
        if ($form['details'] === '') {
            $errors[] = 'Details are required.';
        }

        $areaId = (int)$form['area_id'];
        if ($areaId <= 0 || !vs_reception_vastu_area_exists($pdo, $areaId)) {
            $errors[] = 'Please select a valid area.';
        }

        if ($visitId > 0 && !$existingVisit) {
            $errors[] = 'The selected vastu visit could not be found.';
        }

        if (empty($errors)) {
            if ($visitId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE vastu_visits
                    SET customer_name = ?,
                        contact_number = ?,
                        details = ?,
                        vastu_type = ?,
                        area_id = ?,
                        address_line1 = ?,
                        address_line2 = ?,
                        landmark = ?,
                        closed_at = CASE WHEN status = 'closed' THEN COALESCE(closed_at, NOW()) ELSE NULL END
                    WHERE id = ?
                    LIMIT 1
                ");
                $stmt->execute([
                    $form['customer_name'],
                    $form['contact_number'],
                    $form['details'],
                    $form['vastu_type'],
                    $areaId,
                    $form['address_line1'],
                    $form['address_line2'],
                    $form['landmark'],
                    $visitId,
                ]);

                header('Location: ' . $returnPage . '?notice=' . rawurlencode('Vastu visit updated successfully.') . '&type=success');
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO vastu_visits
                    (customer_name, contact_number, details, vastu_type, area_id, address_line1, address_line2, landmark, status)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, 'open')
            ");
            $stmt->execute([
                $form['customer_name'],
                $form['contact_number'],
                $form['details'],
                $form['vastu_type'],
                $areaId,
                $form['address_line1'],
                $form['address_line2'],
                $form['landmark'],
            ]);

            header('Location: ' . $redirectBase . '?notice=' . rawurlencode('Vastu visit added successfully.') . '&type=success');
            exit;
        }

        if ($existingVisit) {
            $editVisit = array_merge($existingVisit, $form, ['area_id' => $areaId]);
        }
    } elseif ($action === 'close_visit') {
        $visitId = (int)($_POST['visit_id'] ?? 0);
        $visit = vs_reception_vastu_find_visit($pdo, $visitId);
        if ($visit) {
            $stmt = $pdo->prepare("UPDATE vastu_visits SET status = 'closed', closed_at = NOW() WHERE id = ? LIMIT 1");
            $stmt->execute([$visitId]);
            header('Location: ' . $redirectBase . '?notice=' . rawurlencode('Vastu visit moved to closed list.') . '&type=success');
            exit;
        }
        header('Location: ' . $redirectBase . '?notice=' . rawurlencode('Vastu visit not found.') . '&type=danger');
        exit;
    } elseif ($action === 'delete_visit') {
        $visitId = (int)($_POST['visit_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM vastu_visits WHERE id = ? LIMIT 1");
        $stmt->execute([$visitId]);
        $target = vs_reception_vastu_allowed_return_page($_POST['return_page'] ?? $redirectBase);
        header('Location: ' . $target . '?notice=' . rawurlencode('Vastu visit deleted successfully.') . '&type=success');
        exit;
    }
}

if ($editVisit === null && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if ($editId > 0) {
        $editVisit = vs_reception_vastu_find_visit($pdo, $editId);
        if ($editVisit) {
            $form = [
                'customer_name' => (string)($editVisit['customer_name'] ?? ''),
                'contact_number' => (string)($editVisit['contact_number'] ?? ''),
                'vastu_type' => (string)($editVisit['vastu_type'] ?? ''),
                'area_id' => (string)($editVisit['area_id'] ?? ''),
                'address_line1' => (string)($editVisit['address_line1'] ?? ''),
                'address_line2' => (string)($editVisit['address_line2'] ?? ''),
                'landmark' => (string)($editVisit['landmark'] ?? ''),
                'details' => (string)($editVisit['details'] ?? ''),
            ];
        } else {
            $errors[] = 'The selected vastu visit could not be found.';
        }
    }
}

$areas = vs_reception_vastu_all_areas($pdo);
$groupedVisits = vs_reception_vastu_fetch_grouped_visits($pdo, 'open');
$notice = trim((string)($_GET['notice'] ?? ''));
$noticeType = trim((string)($_GET['type'] ?? 'success'));

require_once __DIR__ . '/../includes/top-menu.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vastu Visits</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
        .admin-container { max-width: 1440px; margin: 0 auto; padding: 24px 12px; }
        .page-head { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:18px; }
        h1 { color: #800000; margin: 0; }
        .head-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
        .btn-main { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 16px; background:#800000; color:#fff; border:none; border-radius:8px; text-decoration:none; font-weight:600; cursor:pointer; }
        .btn-main:hover { background:#600000; color:#fff; }
        .btn-alt { background:#6c757d; }
        .btn-alt:hover { background:#545b62; }
        .icon-btn { width:42px; height:42px; padding:0; }
        .notice { padding:12px 14px; border-radius:10px; font-weight:600; margin-bottom:16px; }
        .notice.success { background:#eaf8ea; color:#176117; }
        .notice.danger { background:#ffeaea; color:#a40000; }
        .notice.warn { background:#fff7e3; color:#8a5a00; }
        .card { background:#fff; border-radius:16px; box-shadow:0 2px 14px #e0bebe22; padding:18px; margin-bottom:20px; }
        .form-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        .form-group.full { grid-column:1 / -1; }
        label { color:#800000; font-weight:700; font-size:0.95em; }
        input, select, textarea { width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #e0bebe; border-radius:8px; font-size:0.95em; background:#fff; }
        textarea { min-height:110px; resize:vertical; }
        .form-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:14px; }
        .section-title { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:10px; }
        .area-card { background:#fff; border-radius:16px; box-shadow:0 2px 14px #e0bebe22; margin-bottom:20px; overflow:hidden; }
        .area-head { display:flex; justify-content:space-between; align-items:center; gap:12px; background:#f9eaea; color:#800000; padding:14px 16px; font-weight:700; }
        .area-count { font-size:0.9em; background:#fff; color:#800000; border-radius:999px; padding:4px 10px; }
        table { width:100%; border-collapse:collapse; background:#fff; table-layout:auto; font-size:0.9em; }
        table th, table td { padding:10px 8px; text-align:left; border-bottom:1px solid #f3caca; vertical-align:top; }
        table thead { background:#fff7f7; color:#800000; }
        .stack { display:flex; flex-direction:column; gap:4px; }
        .small { font-size:0.84em; color:#666; }
        .details-cell { min-width:240px; white-space:pre-wrap; }
        .address-cell { min-width:220px; white-space:pre-wrap; }
        .status-badge { display:inline-block; padding:5px 10px; border-radius:999px; background:#e5f0ff; color:#0056b3; font-weight:700; font-size:0.85em; text-transform:capitalize; }
        .action-row { display:flex; gap:8px; flex-wrap:wrap; }
        .btn-inline { display:inline-flex; align-items:center; justify-content:center; padding:7px 12px; border:none; border-radius:7px; color:#fff; font-weight:600; text-decoration:none; cursor:pointer; }
        .btn-edit { background:#0d6efd; }
        .btn-close { background:#1a8917; }
        .btn-delete { background:#b00020; }
        .empty-state { padding:18px; text-align:center; color:#777; background:#fff; border-radius:16px; box-shadow:0 2px 14px #e0bebe22; }
        .error-list { margin:0 0 16px; padding-left:18px; color:#a40000; }
        @media (max-width: 768px) {
            .page-head { align-items:flex-start; }
            .head-actions { width:100%; }
            .btn-main { width:100%; }
            .icon-btn { width:100%; height:auto; padding:10px 16px; }
            .action-row { flex-direction:column; }
            .btn-inline { width:100%; }
        }
    </style>
</head>
<body>
<div class="admin-container">
    <div class="page-head">
        <h1>Vastu Visits</h1>
        <div class="head-actions">
            <a class="btn-main btn-alt" href="closed-vastu-visits.php">Closed Vastu Visits</a>
            <a class="btn-main icon-btn" href="vastu-visit-areas.php" title="Manage Areas" aria-label="Manage Areas">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58z" fill="currentColor"/>
                    <circle cx="12" cy="12" r="3.1" fill="#fff"/>
                </svg>
            </a>
        </div>
    </div>

    <?php if ($notice !== ''): ?>
        <div class="notice <?php echo htmlspecialchars(in_array($noticeType, ['success', 'danger', 'warn'], true) ? $noticeType : 'success'); ?>">
            <?php echo htmlspecialchars($notice); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="notice danger">
            <ul class="error-list">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (empty($areas)): ?>
        <div class="notice warn">
            No area has been added yet. Please create at least one area from <a href="vastu-visit-areas.php" style="color:inherit;text-decoration:underline;">Vastu Visit Settings</a>.
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="section-title">
            <h2 style="margin:0;color:#800000;"><?php echo $editVisit ? 'Edit Vastu Visit' : 'Add Vastu Visit'; ?></h2>
            <?php if ($editVisit && (string)($editVisit['status'] ?? '') === 'closed'): ?>
                <span class="small">You are editing a closed vastu visit.</span>
            <?php endif; ?>
        </div>
        <form method="post" autocomplete="off">
            <input type="hidden" name="action" value="save_visit">
            <input type="hidden" name="visit_id" value="<?php echo (int)($editVisit['id'] ?? 0); ?>">
            <input type="hidden" name="return_page" value="<?php echo htmlspecialchars($returnPage); ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label for="customer_name">Customer Name *</label>
                    <input type="text" id="customer_name" name="customer_name" value="<?php echo htmlspecialchars($form['customer_name']); ?>" required maxlength="255">
                </div>
                <div class="form-group">
                    <label for="contact_number">Customer Number *</label>
                    <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($form['contact_number']); ?>" required maxlength="30">
                </div>
                <div class="form-group">
                    <label for="vastu_type">Vastu Type *</label>
                    <input type="text" id="vastu_type" name="vastu_type" value="<?php echo htmlspecialchars($form['vastu_type']); ?>" required maxlength="150">
                </div>
                <div class="form-group">
                    <label for="area_id">Area *</label>
                    <select id="area_id" name="area_id" required <?php echo empty($areas) ? 'disabled' : ''; ?>>
                        <option value="">Select Area</option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?php echo (int)$area['id']; ?>" <?php echo ((string)$form['area_id'] === (string)$area['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string)$area['area_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="address_line1">Address Line 1</label>
                    <input type="text" id="address_line1" name="address_line1" value="<?php echo htmlspecialchars($form['address_line1']); ?>" maxlength="255">
                </div>
                <div class="form-group">
                    <label for="address_line2">Address Line 2</label>
                    <input type="text" id="address_line2" name="address_line2" value="<?php echo htmlspecialchars($form['address_line2']); ?>" maxlength="255">
                </div>
                <div class="form-group full">
                    <label for="landmark">Landmark</label>
                    <input type="text" id="landmark" name="landmark" value="<?php echo htmlspecialchars($form['landmark']); ?>" maxlength="255">
                </div>
                <div class="form-group full">
                    <label for="details">Details *</label>
                    <textarea id="details" name="details" required><?php echo htmlspecialchars($form['details']); ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-main" <?php echo empty($areas) ? 'disabled' : ''; ?>>
                    <?php echo $editVisit ? 'Update Vastu Visit' : 'Add Vastu Visit'; ?>
                </button>
                <?php if ($editVisit): ?>
                    <a class="btn-main btn-alt" href="<?php echo htmlspecialchars($returnPage); ?>">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if (empty($groupedVisits)): ?>
        <div class="empty-state">No open vastu visits found.</div>
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
                            <th>Created</th>
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
                                    <span><?php echo htmlspecialchars(date('d M Y', strtotime((string)$row['created_at']))); ?></span>
                                    <span class="small"><?php echo htmlspecialchars(date('h:i A', strtotime((string)$row['created_at']))); ?></span>
                                </div>
                            </td>
                            <td><span class="status-badge"><?php echo htmlspecialchars((string)($row['status'] ?? 'open')); ?></span></td>
                            <td>
                                <div class="action-row">
                                    <a class="btn-inline btn-edit" href="vastu-visits.php?edit=<?php echo (int)$row['id']; ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Move this vastu visit to closed list?');">
                                        <input type="hidden" name="action" value="close_visit">
                                        <input type="hidden" name="visit_id" value="<?php echo (int)$row['id']; ?>">
                                        <button type="submit" class="btn-inline btn-close">Close</button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Delete this vastu visit permanently?');">
                                        <input type="hidden" name="action" value="delete_visit">
                                        <input type="hidden" name="visit_id" value="<?php echo (int)$row['id']; ?>">
                                        <input type="hidden" name="return_page" value="vastu-visits.php">
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
