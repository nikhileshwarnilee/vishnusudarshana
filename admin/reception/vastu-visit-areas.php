<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/vastu-visits-common.php';

vs_reception_vastu_bootstrap($pdo);

$defaultArea = ['area_name' => ''];
$areaForm = $defaultArea;
$editArea = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'save_area') {
        $areaId = (int)($_POST['area_id'] ?? 0);
        $existingArea = $areaId > 0 ? vs_reception_vastu_find_area($pdo, $areaId) : null;
        $areaName = trim((string)($_POST['area_name'] ?? ''));
        $areaForm['area_name'] = $areaName;

        if ($areaName === '') {
            $errors[] = 'Area name is required.';
        } else {
            $dupStmt = $pdo->prepare("SELECT id FROM vastu_visit_areas WHERE LOWER(area_name) = LOWER(?) AND id <> ? LIMIT 1");
            $dupStmt->execute([$areaName, $areaId]);
            if ($dupStmt->fetchColumn()) {
                $errors[] = 'This area already exists.';
            }
        }

        if ($areaId > 0 && !$existingArea) {
            $errors[] = 'The selected area could not be found.';
        }

        if (empty($errors)) {
            if ($areaId > 0) {
                $stmt = $pdo->prepare("UPDATE vastu_visit_areas SET area_name = ? WHERE id = ? LIMIT 1");
                $stmt->execute([$areaName, $areaId]);
                header('Location: vastu-visit-areas.php?notice=' . rawurlencode('Area updated successfully.') . '&type=success');
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO vastu_visit_areas (area_name) VALUES (?)");
            $stmt->execute([$areaName]);
            header('Location: vastu-visit-areas.php?notice=' . rawurlencode('Area added successfully.') . '&type=success');
            exit;
        }

        if ($existingArea) {
            $editArea = $existingArea;
        }
    } elseif ($action === 'delete_area') {
        $areaId = (int)($_POST['area_id'] ?? 0);
        $usageStmt = $pdo->prepare("SELECT COUNT(*) FROM vastu_visits WHERE area_id = ?");
        $usageStmt->execute([$areaId]);
        $usageCount = (int)$usageStmt->fetchColumn();

        if ($usageCount > 0) {
            header('Location: vastu-visit-areas.php?notice=' . rawurlencode('This area is already used in vastu visits, so it cannot be deleted.') . '&type=danger');
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM vastu_visit_areas WHERE id = ? LIMIT 1");
        $stmt->execute([$areaId]);
        header('Location: vastu-visit-areas.php?notice=' . rawurlencode('Area deleted successfully.') . '&type=success');
        exit;
    }
}

if ($editArea === null && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if ($editId > 0) {
        $editArea = vs_reception_vastu_find_area($pdo, $editId);
        if ($editArea) {
            $areaForm['area_name'] = (string)($editArea['area_name'] ?? '');
        } else {
            $errors[] = 'The selected area could not be found.';
        }
    }
}

$areas = vs_reception_vastu_all_areas($pdo);
$notice = trim((string)($_GET['notice'] ?? ''));
$noticeType = trim((string)($_GET['type'] ?? 'success'));

require_once __DIR__ . '/../includes/top-menu.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vastu Visit Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f7f7fa; margin:0; }
        .admin-container { max-width:1100px; margin:0 auto; padding:24px 12px; }
        .page-head { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:18px; }
        h1 { color:#800000; margin:0; }
        .head-actions { display:flex; gap:10px; flex-wrap:wrap; }
        .btn-main { display:inline-flex; align-items:center; justify-content:center; padding:10px 16px; background:#800000; color:#fff; border:none; border-radius:8px; text-decoration:none; font-weight:600; cursor:pointer; }
        .btn-main:hover { background:#600000; color:#fff; }
        .btn-alt { background:#6c757d; }
        .btn-alt:hover { background:#545b62; }
        .notice { padding:12px 14px; border-radius:10px; font-weight:600; margin-bottom:16px; }
        .notice.success { background:#eaf8ea; color:#176117; }
        .notice.danger { background:#ffeaea; color:#a40000; }
        .card { background:#fff; border-radius:16px; box-shadow:0 2px 14px #e0bebe22; padding:18px; margin-bottom:20px; }
        .form-row { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
        .form-group { display:flex; flex-direction:column; gap:6px; flex:1 1 320px; }
        label { color:#800000; font-weight:700; }
        input { width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #e0bebe; border-radius:8px; font-size:0.95em; }
        table { width:100%; border-collapse:collapse; background:#fff; }
        table th, table td { padding:12px 10px; text-align:left; border-bottom:1px solid #f3caca; }
        table thead { background:#f9eaea; color:#800000; }
        .action-row { display:flex; gap:8px; flex-wrap:wrap; }
        .btn-inline { display:inline-flex; align-items:center; justify-content:center; padding:7px 12px; border:none; border-radius:7px; color:#fff; font-weight:600; text-decoration:none; cursor:pointer; }
        .btn-edit { background:#0d6efd; }
        .btn-delete { background:#b00020; }
        .small { color:#666; font-size:0.84em; }
        .error-list { margin:0; padding-left:18px; color:#a40000; }
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
        <h1>Vastu Visit Settings</h1>
        <div class="head-actions">
            <a class="btn-main btn-alt" href="vastu-visits.php">Back To Vastu Visits</a>
            <a class="btn-main btn-alt" href="closed-vastu-visits.php">Closed Vastu Visits</a>
        </div>
    </div>

    <?php if ($notice !== ''): ?>
        <div class="notice <?php echo htmlspecialchars(in_array($noticeType, ['success', 'danger'], true) ? $noticeType : 'success'); ?>">
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

    <div class="card">
        <h2 style="margin:0 0 12px;color:#800000;"><?php echo $editArea ? 'Edit Area' : 'Add Area'; ?></h2>
        <form method="post" autocomplete="off">
            <input type="hidden" name="action" value="save_area">
            <input type="hidden" name="area_id" value="<?php echo (int)($editArea['id'] ?? 0); ?>">
            <div class="form-row">
                <div class="form-group">
                    <label for="area_name">Area Name *</label>
                    <input type="text" id="area_name" name="area_name" value="<?php echo htmlspecialchars($areaForm['area_name']); ?>" required maxlength="150">
                </div>
                <button type="submit" class="btn-main"><?php echo $editArea ? 'Update Area' : 'Add Area'; ?></button>
                <?php if ($editArea): ?>
                    <a class="btn-main btn-alt" href="vastu-visit-areas.php">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin:0 0 12px;color:#800000;">Area List</h2>
        <?php if (empty($areas)): ?>
            <div class="small">No areas added yet.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Area Name</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($areas as $area): ?>
                    <tr>
                        <td><?php echo (int)$area['id']; ?></td>
                        <td><?php echo htmlspecialchars((string)$area['area_name']); ?></td>
                        <td><?php echo htmlspecialchars(date('d M Y h:i A', strtotime((string)$area['created_at']))); ?></td>
                        <td>
                            <div class="action-row">
                                <a class="btn-inline btn-edit" href="vastu-visit-areas.php?edit=<?php echo (int)$area['id']; ?>">Edit</a>
                                <form method="post" onsubmit="return confirm('Delete this area?');">
                                    <input type="hidden" name="action" value="delete_area">
                                    <input type="hidden" name="area_id" value="<?php echo (int)$area['id']; ?>">
                                    <button type="submit" class="btn-inline btn-delete">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
