<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/vastu-visits-common.php';

vs_reception_vastu_bootstrap($pdo);

$redirectBase = 'closed-vastu-visits.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'add_note') {
        $visitId = (int)($_POST['visit_id'] ?? 0);
        $noteText = trim((string)($_POST['note_text'] ?? ''));
        $visit = vs_reception_vastu_find_visit($pdo, $visitId);

        if (!$visit) {
            header('Location: ' . $redirectBase . '?notice=' . rawurlencode('Vastu visit not found.') . '&type=danger');
            exit;
        }

        if ($noteText === '') {
            header('Location: ' . $redirectBase . '?notice=' . rawurlencode('Note cannot be empty.') . '&type=danger');
            exit;
        }

        if (vs_reception_vastu_add_note($pdo, $visitId, $noteText)) {
            header('Location: ' . $redirectBase . '?notice=' . rawurlencode('Note saved successfully.') . '&type=success');
            exit;
        }

        header('Location: ' . $redirectBase . '?notice=' . rawurlencode('Unable to save note right now.') . '&type=danger');
        exit;
    }

    if ($action === 'edit_note') {
        $visitId = (int)($_POST['visit_id'] ?? 0);
        $noteId = (int)($_POST['note_id'] ?? 0);
        $noteText = trim((string)($_POST['note_text'] ?? ''));
        $visit = vs_reception_vastu_find_visit($pdo, $visitId);
        $note = vs_reception_vastu_find_note($pdo, $noteId, $visitId);

        if (!$visit || !$note) {
            header('Location: ' . $redirectBase . '?notice=' . rawurlencode('Note not found.') . '&type=danger');
            exit;
        }

        if ($noteText === '') {
            header('Location: ' . $redirectBase . '?notice=' . rawurlencode('Note cannot be empty.') . '&type=danger');
            exit;
        }

        if (vs_reception_vastu_update_note($pdo, $noteId, $visitId, $noteText)) {
            header('Location: ' . $redirectBase . '?notice=' . rawurlencode('Note updated successfully.') . '&type=success');
            exit;
        }

        header('Location: ' . $redirectBase . '?notice=' . rawurlencode('No note changes were saved.') . '&type=success');
        exit;
    }

    if ($action === 'delete_note') {
        $visitId = (int)($_POST['visit_id'] ?? 0);
        $noteId = (int)($_POST['note_id'] ?? 0);
        $visit = vs_reception_vastu_find_visit($pdo, $visitId);
        $note = vs_reception_vastu_find_note($pdo, $noteId, $visitId);

        if (!$visit || !$note) {
            header('Location: ' . $redirectBase . '?notice=' . rawurlencode('Note not found.') . '&type=danger');
            exit;
        }

        if (vs_reception_vastu_delete_note($pdo, $noteId, $visitId)) {
            header('Location: ' . $redirectBase . '?notice=' . rawurlencode('Note deleted successfully.') . '&type=success');
            exit;
        }

        header('Location: ' . $redirectBase . '?notice=' . rawurlencode('Unable to delete note right now.') . '&type=danger');
        exit;
    }

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
        .area-head-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .area-count { font-size:0.9em; background:#fff; color:#176117; border-radius:999px; padding:4px 10px; }
        textarea { width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #d7e8d7; border-radius:8px; font-size:0.95em; background:#fff; min-height:110px; resize:vertical; }
        .form-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:4px; }
        table { width:100%; border-collapse:collapse; background:#fff; table-layout:auto; font-size:0.9em; }
        table th, table td { padding:10px 8px; text-align:left; border-bottom:1px solid #e7f0e7; vertical-align:top; }
        table thead { background:#f8fcf8; color:#176117; }
        .details-cell { min-width:240px; white-space:pre-wrap; }
        .address-cell { min-width:220px; white-space:pre-wrap; }
        .notes-cell { min-width:280px; }
        .note-list { display:flex; flex-direction:column; gap:8px; }
        .note-item { border-left:3px solid #176117; background:#f6fcf6; border-radius:8px; padding:8px 10px; }
        .note-text { color:#333; white-space:pre-wrap; }
        .note-meta { font-size:0.82em; color:#607860; }
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
        .note-entry-row td { background:#fbfefb; }
        .note-form { display:flex; flex-direction:column; gap:10px; }
        .status-badge { display:inline-block; padding:5px 10px; border-radius:999px; background:#e5ffe5; color:#176117; font-weight:700; font-size:0.85em; text-transform:capitalize; }
        .action-row { display:flex; gap:8px; flex-wrap:wrap; }
        .btn-inline { display:inline-flex; align-items:center; justify-content:center; padding:7px 12px; border:none; border-radius:7px; color:#fff; font-weight:600; text-decoration:none; cursor:pointer; }
        .btn-edit { background:#0d6efd; }
        .btn-note { background:#7a5b00; }
        .btn-print { background:#315f9b; }
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
        @media print {
            body { background:#fff; }
            .admin-top-menu, .page-head, .notice, .empty-state { display:none !important; }
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
        <?php $areaCardIndex = 0; ?>
        <?php foreach ($groupedVisits as $areaName => $rows): ?>
            <?php $areaCardIndex++; $areaPrintId = 'closed-area-card-' . $areaCardIndex; ?>
            <div class="area-card" data-print-card="<?php echo htmlspecialchars($areaPrintId); ?>">
                <div class="area-head">
                    <span><?php echo htmlspecialchars($areaName); ?></span>
                    <div class="area-head-actions">
                        <span class="area-count"><?php echo count($rows); ?> visits</span>
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
                            <th>Closed</th>
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
                        $closedAt = trim((string)($row['closed_at'] ?? $row['updated_at'] ?? $row['created_at'] ?? ''));
                        ?>
                        <tr>
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
                                            $noteEditKey = 'closed-note-' . $noteId;
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
                                    <span><?php echo htmlspecialchars(date('d M Y', strtotime($closedAt))); ?></span>
                                    <span class="small"><?php echo htmlspecialchars(date('h:i A', strtotime($closedAt))); ?></span>
                                </div>
                            </td>
                            <td><span class="status-badge"><?php echo htmlspecialchars((string)($row['status'] ?? 'closed')); ?></span></td>
                            <td class="actions-col">
                                <div class="action-row">
                                    <a class="btn-inline btn-edit" href="vastu-visits.php?edit=<?php echo $visitId; ?>&return=closed-vastu-visits.php">Edit</a>
                                    <button type="button" class="btn-inline btn-note" data-note-toggle="<?php echo $visitId; ?>">Add Note</button>
                                    <form method="post" onsubmit="return confirm('Move this vastu visit back to open list?');">
                                        <input type="hidden" name="action" value="reopen_visit">
                                        <input type="hidden" name="visit_id" value="<?php echo $visitId; ?>">
                                        <button type="submit" class="btn-inline btn-reopen">Reopen</button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Delete this vastu visit permanently?');">
                                        <input type="hidden" name="action" value="delete_visit">
                                        <input type="hidden" name="visit_id" value="<?php echo $visitId; ?>">
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
    <?php endif; ?>
</div>
<script src="../includes/responsive-tables.js"></script>
<script>
    (function () {
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

        window.addEventListener('afterprint', clearPrintTargets);
    }());
</script>
</body>
</html>
