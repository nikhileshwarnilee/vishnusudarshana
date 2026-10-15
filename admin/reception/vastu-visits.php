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
            'details' => trim((string)($_POST['details'] ?? '')),
        ];

        if ($form['customer_name'] === '') {
            $errors[] = 'Customer name is required.';
        }
        if ($form['contact_number'] === '') {
            $errors[] = 'Customer number is required.';
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
                        address_line2 = NULL,
                        landmark = NULL,
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
                    $visitId,
                ]);

                header('Location: ' . $returnPage . '?notice=' . rawurlencode('Vastu visit updated successfully.') . '&type=success');
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO vastu_visits
                    (customer_name, contact_number, details, vastu_type, area_id, address_line1, status)
                VALUES
                    (?, ?, ?, ?, ?, ?, 'open')
            ");
            $stmt->execute([
                $form['customer_name'],
                $form['contact_number'],
                $form['details'],
                $form['vastu_type'],
                $areaId,
                $form['address_line1'],
            ]);

            header('Location: ' . $redirectBase . '?notice=' . rawurlencode('Vastu visit added successfully.') . '&type=success');
            exit;
        }

        if ($existingVisit) {
            $editVisit = array_merge($existingVisit, $form, ['area_id' => $areaId]);
        }
    } elseif ($action === 'add_note') {
        $visitId = (int)($_POST['visit_id'] ?? 0);
        $noteText = trim((string)($_POST['note_text'] ?? ''));
        $target = vs_reception_vastu_allowed_return_page($_POST['return_page'] ?? $redirectBase);
        $visit = vs_reception_vastu_find_visit($pdo, $visitId);

        if (!$visit) {
            header('Location: ' . $target . '?notice=' . rawurlencode('Vastu visit not found.') . '&type=danger');
            exit;
        }

        if ($noteText === '') {
            header('Location: ' . $target . '?notice=' . rawurlencode('Note cannot be empty.') . '&type=danger');
            exit;
        }

        if (vs_reception_vastu_add_note($pdo, $visitId, $noteText)) {
            header('Location: ' . $target . '?notice=' . rawurlencode('Note saved successfully.') . '&type=success');
            exit;
        }

        header('Location: ' . $target . '?notice=' . rawurlencode('Unable to save note right now.') . '&type=danger');
        exit;
    } elseif ($action === 'edit_note') {
        $visitId = (int)($_POST['visit_id'] ?? 0);
        $noteId = (int)($_POST['note_id'] ?? 0);
        $noteText = trim((string)($_POST['note_text'] ?? ''));
        $target = vs_reception_vastu_allowed_return_page($_POST['return_page'] ?? $redirectBase);
        $visit = vs_reception_vastu_find_visit($pdo, $visitId);
        $note = vs_reception_vastu_find_note($pdo, $noteId, $visitId);

        if (!$visit || !$note) {
            header('Location: ' . $target . '?notice=' . rawurlencode('Note not found.') . '&type=danger');
            exit;
        }

        if ($noteText === '') {
            header('Location: ' . $target . '?notice=' . rawurlencode('Note cannot be empty.') . '&type=danger');
            exit;
        }

        if (vs_reception_vastu_update_note($pdo, $noteId, $visitId, $noteText)) {
            header('Location: ' . $target . '?notice=' . rawurlencode('Note updated successfully.') . '&type=success');
            exit;
        }

        header('Location: ' . $target . '?notice=' . rawurlencode('No note changes were saved.') . '&type=warn');
        exit;
    } elseif ($action === 'delete_note') {
        $visitId = (int)($_POST['visit_id'] ?? 0);
        $noteId = (int)($_POST['note_id'] ?? 0);
        $target = vs_reception_vastu_allowed_return_page($_POST['return_page'] ?? $redirectBase);
        $visit = vs_reception_vastu_find_visit($pdo, $visitId);
        $note = vs_reception_vastu_find_note($pdo, $noteId, $visitId);

        if (!$visit || !$note) {
            header('Location: ' . $target . '?notice=' . rawurlencode('Note not found.') . '&type=danger');
            exit;
        }

        if (vs_reception_vastu_delete_note($pdo, $noteId, $visitId)) {
            header('Location: ' . $target . '?notice=' . rawurlencode('Note deleted successfully.') . '&type=success');
            exit;
        }

        header('Location: ' . $target . '?notice=' . rawurlencode('Unable to delete note right now.') . '&type=danger');
        exit;
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
        .area-search { margin-bottom:10px; }
        .area-options { display:flex; flex-wrap:wrap; gap:10px; }
        .area-option { display:flex; align-items:center; gap:10px; padding:12px 14px; border:1px solid #e0bebe; border-radius:10px; background:#fff8f8; cursor:pointer; font-weight:600; color:#5f2222; }
        .area-option[hidden] { display:none !important; }
        .area-option input { width:auto; margin:0; accent-color:#800000; }
        .area-search-empty { margin:0; color:#777; font-size:0.9em; }
        .form-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:14px; }
        .section-title { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:10px; }
        .visit-search { margin:0 0 20px; }
        .visit-search label { display:block; margin-bottom:6px; }
        .visit-search-help { display:block; margin-top:6px; color:#666; font-size:0.86em; }
        .area-card { background:#fff; border-radius:16px; box-shadow:0 2px 14px #e0bebe22; margin-bottom:20px; overflow:hidden; }
        .area-head { display:flex; justify-content:space-between; align-items:center; gap:12px; background:#f9eaea; color:#800000; padding:14px 16px; font-weight:700; }
        .area-head-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .area-count { font-size:0.9em; background:#fff; color:#800000; border-radius:999px; padding:4px 10px; }
        table { width:100%; border-collapse:collapse; background:#fff; table-layout:auto; font-size:0.9em; }
        table th, table td { padding:10px 8px; text-align:left; border-bottom:1px solid #f3caca; vertical-align:top; }
        table thead { background:#fff7f7; color:#800000; }
        .stack { display:flex; flex-direction:column; gap:4px; }
        .small { font-size:0.84em; color:#666; }
        .details-cell { min-width:240px; white-space:pre-wrap; }
        .address-cell { min-width:220px; white-space:pre-wrap; }
        .notes-cell { min-width:280px; }
        .note-list { display:flex; flex-direction:column; gap:8px; }
        .note-item { border-left:3px solid #800000; background:#fff8f8; border-radius:8px; padding:8px 10px; }
        .note-text { color:#333; white-space:pre-wrap; }
        .note-meta { font-size:0.82em; color:#7a6161; }
        .note-tools { margin-top:6px; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
        .note-actions { display:flex; align-items:center; gap:8px; }
        .note-delete-form { margin:0; }
        .note-icon-btn { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border:none; border-radius:50%; cursor:pointer; }
        .note-icon-btn svg { width:16px; height:16px; }
        .note-icon-btn.edit { background:#fff; color:#0d6efd; border:1px solid #cfe1ff; }
        .note-icon-btn.delete { background:#fff; color:#b00020; border:1px solid #f1c0ca; }
        .note-edit-form { margin-top:8px; display:flex; flex-direction:column; gap:8px; }
        .note-edit-form[hidden] { display:none !important; }
        .note-edit-form textarea { min-height:90px; }
        .note-edit-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .note-item.is-editing .note-text,
        .note-item.is-editing .note-tools { display:none; }
        .note-item.is-editing .note-edit-form { margin-top:0; }
        .note-entry-row td { background:#fffaf9; }
        .note-form { display:flex; flex-direction:column; gap:10px; }
        .status-badge { display:inline-block; padding:5px 10px; border-radius:999px; background:#e5f0ff; color:#0056b3; font-weight:700; font-size:0.85em; text-transform:capitalize; }
        .action-row { display:flex; gap:8px; flex-wrap:wrap; }
        .btn-inline { display:inline-flex; align-items:center; justify-content:center; padding:7px 12px; border:none; border-radius:7px; color:#fff; font-weight:600; text-decoration:none; cursor:pointer; }
        .btn-edit { background:#0d6efd; }
        .btn-note { background:#8f4b00; }
        .btn-print { background:#5b2c83; }
        .btn-close { background:#1a8917; }
        .btn-delete { background:#b00020; }
        .empty-state { padding:18px; text-align:center; color:#777; background:#fff; border-radius:16px; box-shadow:0 2px 14px #e0bebe22; }
        .error-list { margin:0 0 16px; padding-left:18px; color:#a40000; }
        @media (max-width: 768px) {
            .page-head { align-items:flex-start; }
            .head-actions { width:100%; }
            .btn-main { width:100%; }
            .icon-btn { width:100%; height:auto; padding:10px 16px; }
            .area-options { flex-direction:column; }
            .action-row { flex-direction:column; }
            .btn-inline { width:100%; }
        }
        @media print {
            body { background:#fff; }
            .admin-top-menu, .page-head, .card, .notice, .empty-state { display:none !important; }
            .admin-container { max-width:none; padding:0; }
            .area-card { display:none !important; box-shadow:none; border-radius:0; margin:0; }
            .area-card.print-target { display:block !important; }
            .area-card.print-target .note-entry-row,
            .area-card.print-target .actions-col,
            .area-card.print-target .note-tools,
            .area-card.print-target .note-edit-form,
            .area-card.print-target .area-head-actions,
            .area-card.print-target .area-count,
            .area-card.print-target [data-print-area] { display:none !important; }
            .area-card.print-target .area-head { background:#fff !important; color:#000 !important; padding:0 0 12px; border-bottom:2px solid #ccc; }
            .area-card.print-target table { font-size:12px; }
            .area-card.print-target table th,
            .area-card.print-target table td { border-color:#ddd; }
            .note-item { background:#fff !important; }
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
                    <label for="vastu_type">Vastu Type</label>
                    <input type="text" id="vastu_type" name="vastu_type" value="<?php echo htmlspecialchars($form['vastu_type']); ?>" maxlength="150">
                </div>
                <div class="form-group full">
                    <label>Area *</label>
                    <input class="area-search" type="search" id="area_search" placeholder="Search area to select" aria-label="Search area options" autocomplete="off">
                    <div class="area-options">
                        <?php foreach ($areas as $index => $area): ?>
                            <label class="area-option" data-area-option="<?php echo htmlspecialchars((string)$area['area_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input
                                    type="radio"
                                    name="area_id"
                                    value="<?php echo (int)$area['id']; ?>"
                                    <?php echo ((string)$form['area_id'] === (string)$area['id']) ? 'checked' : ''; ?>
                                    <?php echo ($index === 0 && !empty($areas)) ? 'required' : ''; ?>
                                    <?php echo empty($areas) ? 'disabled' : ''; ?>
                                >
                                <span><?php echo htmlspecialchars((string)$area['area_name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="area-search-empty" id="area_search_empty" hidden>No matching area found.</p>
                </div>
                <div class="form-group full">
                    <label for="address_line1">Address Line 1</label>
                    <input type="text" id="address_line1" name="address_line1" value="<?php echo htmlspecialchars($form['address_line1']); ?>" maxlength="255">
                </div>
                <div class="form-group full">
                    <label for="details">Details</label>
                    <textarea id="details" name="details"><?php echo htmlspecialchars($form['details']); ?></textarea>
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
        <div class="card visit-search">
            <label for="visit_search">Search Open Vastu Visits</label>
            <input type="search" id="visit_search" placeholder="Search by area, name, mobile number, address, vastu type, details or notes" autocomplete="off" aria-describedby="visit_search_help">
            <span class="visit-search-help" id="visit_search_help">Results update as you type.</span>
        </div>
        <?php $areaCardIndex = 0; ?>
        <?php foreach ($groupedVisits as $areaName => $rows): ?>
            <?php $areaCardIndex++; $areaPrintId = 'open-area-card-' . $areaCardIndex; ?>
            <div class="area-card" data-print-card="<?php echo htmlspecialchars($areaPrintId); ?>">
                <div class="area-head">
                    <span><?php echo htmlspecialchars($areaName); ?></span>
                    <div class="area-head-actions">
                        <span class="area-count" data-area-count><?php echo count($rows); ?> visits</span>
                        <button type="button" class="btn-inline btn-print" data-print-area="<?php echo htmlspecialchars($areaPrintId); ?>">Print List</button>
                    </div>
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
                            <th>Notes</th>
                            <th>Created</th>
                            <th>Status</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $visitId = (int)($row['id'] ?? 0);
                        $addressLine1 = trim((string)($row['address_line1'] ?? ''));
                        $notes = isset($row['notes']) && is_array($row['notes']) ? $row['notes'] : [];
                        $visitSearchParts = [
                            (string)$visitId,
                            (string)$areaName,
                            (string)($row['customer_name'] ?? ''),
                            (string)($row['contact_number'] ?? ''),
                            (string)($row['vastu_type'] ?? ''),
                            (string)($row['details'] ?? ''),
                            $addressLine1,
                            (string)($row['address_line2'] ?? ''),
                            (string)($row['landmark'] ?? ''),
                            (string)($row['status'] ?? ''),
                            (string)($row['created_at'] ?? ''),
                            (string)($row['updated_at'] ?? ''),
                        ];
                        foreach ($notes as $note) {
                            $visitSearchParts[] = (string)($note['note_text'] ?? '');
                        }
                        $visitSearchText = implode(' ', $visitSearchParts);
                        ?>
                        <tr class="visit-row" data-visit-search="<?php echo htmlspecialchars($visitSearchText, ENT_QUOTES, 'UTF-8'); ?>">
                            <td><?php echo $visitId; ?></td>
                            <td><?php echo htmlspecialchars((string)($row['customer_name'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars(function_exists('vs_format_mobile_for_display') ? vs_format_mobile_for_display((string)($row['contact_number'] ?? '')) : (string)($row['contact_number'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($row['vastu_type'] ?? '')); ?></td>
                            <td class="details-cell"><?php echo trim((string)($row['details'] ?? '')) !== '' ? nl2br(htmlspecialchars((string)($row['details'] ?? ''))) : '<span class="small">-</span>'; ?></td>
                            <td class="address-cell"><?php echo $addressLine1 !== '' ? nl2br(htmlspecialchars($addressLine1)) : '<span class="small">-</span>'; ?></td>
                            <td class="notes-cell">
                                <?php if (!empty($notes)): ?>
                                    <div class="note-list">
                                        <?php foreach ($notes as $note): ?>
                                            <?php
                                            $noteId = (int)($note['id'] ?? 0);
                                            $noteCreatedAt = trim((string)($note['created_at'] ?? ''));
                                            $noteEditKey = 'open-note-' . $noteId;
                                            ?>
                                            <div class="note-item">
                                                <div class="note-text"><?php echo nl2br(htmlspecialchars((string)($note['note_text'] ?? ''))); ?></div>
                                                <div class="note-tools">
                                                    <div class="stack" style="gap:2px;">
                                                        <?php if ($noteCreatedAt !== ''): ?>
                                                            <div class="note-meta"><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($noteCreatedAt))); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="note-actions">
                                                        <button type="button" class="note-icon-btn edit" data-note-edit-toggle="<?php echo htmlspecialchars($noteEditKey); ?>" title="Edit note" aria-label="Edit note">
                                                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                <path d="M4 15.5V20h4.5L19 9.5 14.5 5 4 15.5Z" fill="currentColor"></path>
                                                                <path d="M13.5 6l4.5 4.5" stroke="#fff" stroke-width="1.4" stroke-linecap="round"></path>
                                                            </svg>
                                                        </button>
                                                        <form method="post" class="note-delete-form" onsubmit="return confirm('Delete this note permanently?');">
                                                            <input type="hidden" name="action" value="delete_note">
                                                            <input type="hidden" name="visit_id" value="<?php echo $visitId; ?>">
                                                            <input type="hidden" name="note_id" value="<?php echo $noteId; ?>">
                                                            <input type="hidden" name="return_page" value="vastu-visits.php">
                                                            <button type="submit" class="note-icon-btn delete" title="Delete note" aria-label="Delete note">
                                                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                    <path d="M6 7h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                                                                    <path d="M9 7V5h6v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
                                                                    <path d="M8 7l1 12h6l1-12" fill="currentColor"></path>
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                                <form method="post" class="note-edit-form" data-note-edit-form="<?php echo htmlspecialchars($noteEditKey); ?>" hidden>
                                                    <input type="hidden" name="action" value="edit_note">
                                                    <input type="hidden" name="visit_id" value="<?php echo $visitId; ?>">
                                                    <input type="hidden" name="note_id" value="<?php echo $noteId; ?>">
                                                    <input type="hidden" name="return_page" value="vastu-visits.php">
                                                    <textarea name="note_text" required><?php echo htmlspecialchars((string)($note['note_text'] ?? '')); ?></textarea>
                                                    <div class="note-edit-actions">
                                                        <button type="submit" class="btn-main">Save Changes</button>
                                                        <button type="button" class="btn-main btn-alt" data-note-edit-cancel="<?php echo htmlspecialchars($noteEditKey); ?>">Cancel</button>
                                                    </div>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="small">No notes</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="stack">
                                    <span><?php echo htmlspecialchars(date('d M Y', strtotime((string)$row['created_at']))); ?></span>
                                    <span class="small"><?php echo htmlspecialchars(date('h:i A', strtotime((string)$row['created_at']))); ?></span>
                                </div>
                            </td>
                            <td><span class="status-badge"><?php echo htmlspecialchars((string)($row['status'] ?? 'open')); ?></span></td>
                            <td class="actions-col">
                                <div class="action-row">
                                    <a class="btn-inline btn-edit" href="vastu-visits.php?edit=<?php echo $visitId; ?>">Edit</a>
                                    <button type="button" class="btn-inline btn-note" data-note-toggle="<?php echo $visitId; ?>">Add Note</button>
                                    <form method="post" onsubmit="return confirm('Move this vastu visit to closed list?');">
                                        <input type="hidden" name="action" value="close_visit">
                                        <input type="hidden" name="visit_id" value="<?php echo $visitId; ?>">
                                        <button type="submit" class="btn-inline btn-close">Close</button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Delete this vastu visit permanently?');">
                                        <input type="hidden" name="action" value="delete_visit">
                                        <input type="hidden" name="visit_id" value="<?php echo $visitId; ?>">
                                        <input type="hidden" name="return_page" value="vastu-visits.php">
                                        <button type="submit" class="btn-inline btn-delete">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr class="note-entry-row" data-note-row="<?php echo $visitId; ?>" hidden>
                            <td colspan="10">
                                <form method="post" class="note-form">
                                    <input type="hidden" name="action" value="add_note">
                                    <input type="hidden" name="visit_id" value="<?php echo $visitId; ?>">
                                    <input type="hidden" name="return_page" value="vastu-visits.php">
                                    <label for="note_text_<?php echo $visitId; ?>">Add Note For <?php echo htmlspecialchars((string)($row['customer_name'] ?? ('Visit #' . $visitId))); ?></label>
                                    <textarea id="note_text_<?php echo $visitId; ?>" name="note_text" required placeholder="Enter note"></textarea>
                                    <div class="form-actions">
                                        <button type="submit" class="btn-main">Save Note</button>
                                        <button type="button" class="btn-main btn-alt" data-note-cancel="<?php echo $visitId; ?>">Cancel</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
        <div class="empty-state" id="visit_search_empty" hidden>No visits match your search.</div>
    <?php endif; ?>
</div>
<script src="../includes/responsive-tables.js"></script>
<script>
    (function () {
        function searchKey(value) {
            return String(value || '').toLocaleLowerCase().replace(/[^\p{L}\p{N}]+/gu, '');
        }

        function filterAreaOptions() {
            var searchInput = document.getElementById('area_search');
            var emptyMessage = document.getElementById('area_search_empty');
            if (!searchInput) {
                return;
            }

            var query = searchKey(searchInput.value);
            var visibleCount = 0;
            document.querySelectorAll('[data-area-option]').forEach(function (option) {
                var matches = searchKey(option.getAttribute('data-area-option')).indexOf(query) !== -1;
                option.hidden = !matches;
                if (matches) {
                    visibleCount++;
                }
            });

            if (emptyMessage) {
                emptyMessage.hidden = visibleCount !== 0;
            }
        }

        function filterVisits() {
            var searchInput = document.getElementById('visit_search');
            var emptyMessage = document.getElementById('visit_search_empty');
            if (!searchInput) {
                return;
            }

            var query = searchKey(searchInput.value);
            var visibleVisits = 0;
            document.querySelectorAll('.area-card[data-print-card]').forEach(function (card) {
                var visibleInArea = 0;
                card.querySelectorAll('tr.visit-row[data-visit-search]').forEach(function (row) {
                    var matches = searchKey(row.getAttribute('data-visit-search')).indexOf(query) !== -1;
                    row.hidden = !matches;
                    if (!matches) {
                        var noteRow = row.nextElementSibling;
                        if (noteRow && noteRow.hasAttribute('data-note-row')) {
                            noteRow.hidden = true;
                        }
                    }
                    if (matches) {
                        visibleInArea++;
                        visibleVisits++;
                    }
                });

                card.hidden = visibleInArea === 0;
                var count = card.querySelector('[data-area-count]');
                if (count) {
                    count.textContent = visibleInArea + (visibleInArea === 1 ? ' visit' : ' visits');
                }
            });

            if (emptyMessage) {
                emptyMessage.hidden = visibleVisits !== 0;
            }
        }

        function clearPrintTargets() {
            document.querySelectorAll('.area-card.print-target').forEach(function (card) {
                card.classList.remove('print-target');
            });
        }

        function closeNoteEditForm(key) {
            var form = document.querySelector('[data-note-edit-form="' + key + '"]');
            if (form) {
                form.hidden = true;
                var noteItem = form.closest('.note-item');
                if (noteItem) {
                    noteItem.classList.remove('is-editing');
                }
            }
        }

        function closeAllNoteEditForms() {
            document.querySelectorAll('[data-note-edit-form]').forEach(function (form) {
                form.hidden = true;
                var noteItem = form.closest('.note-item');
                if (noteItem) {
                    noteItem.classList.remove('is-editing');
                }
            });
        }

        function closeNoteRow(visitId) {
            var row = document.querySelector('[data-note-row="' + visitId + '"]');
            var button = document.querySelector('[data-note-toggle="' + visitId + '"]');
            if (row) {
                row.hidden = true;
            }
            if (button) {
                button.textContent = 'Add Note';
            }
        }

        document.addEventListener('click', function (event) {
            var printButton = event.target.closest('[data-print-area]');
            if (printButton) {
                var printId = printButton.getAttribute('data-print-area');
                var card = document.querySelector('[data-print-card="' + printId + '"]');
                if (!card) {
                    return;
                }

                document.querySelectorAll('[data-note-row]').forEach(function (noteRow) {
                    closeNoteRow(noteRow.getAttribute('data-note-row'));
                });
                clearPrintTargets();
                card.classList.add('print-target');
                window.print();
                return;
            }

            var noteEditToggle = event.target.closest('[data-note-edit-toggle]');
            if (noteEditToggle) {
                var key = noteEditToggle.getAttribute('data-note-edit-toggle');
                var form = document.querySelector('[data-note-edit-form="' + key + '"]');
                if (!form) {
                    return;
                }

                var shouldOpen = form.hidden;
                closeAllNoteEditForms();
                if (shouldOpen) {
                    form.hidden = false;
                    var noteItem = form.closest('.note-item');
                    if (noteItem) {
                        noteItem.classList.add('is-editing');
                    }
                    var textarea = form.querySelector('textarea');
                    if (textarea) {
                        textarea.focus();
                        textarea.setSelectionRange(textarea.value.length, textarea.value.length);
                    }
                }
                return;
            }

            var noteEditCancel = event.target.closest('[data-note-edit-cancel]');
            if (noteEditCancel) {
                closeNoteEditForm(noteEditCancel.getAttribute('data-note-edit-cancel'));
                return;
            }

            var toggleButton = event.target.closest('[data-note-toggle]');
            if (toggleButton) {
                var visitId = toggleButton.getAttribute('data-note-toggle');
                var row = document.querySelector('[data-note-row="' + visitId + '"]');
                if (!row) {
                    return;
                }

                var shouldOpen = row.hidden;
                document.querySelectorAll('[data-note-row]').forEach(function (noteRow) {
                    closeNoteRow(noteRow.getAttribute('data-note-row'));
                });

                if (shouldOpen) {
                    row.hidden = false;
                    toggleButton.textContent = 'Hide Note Form';
                    var textarea = row.querySelector('textarea');
                    if (textarea) {
                        textarea.focus();
                    }
                }
                return;
            }

            var cancelButton = event.target.closest('[data-note-cancel]');
            if (cancelButton) {
                closeNoteRow(cancelButton.getAttribute('data-note-cancel'));
            }
        });

        var areaSearch = document.getElementById('area_search');
        if (areaSearch) {
            areaSearch.addEventListener('input', filterAreaOptions);
        }

        var visitSearch = document.getElementById('visit_search');
        if (visitSearch) {
            visitSearch.addEventListener('input', filterVisits);
        }

        window.addEventListener('afterprint', clearPrintTargets);
    }());
</script>
</body>
</html>
