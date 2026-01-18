<?php
require_once __DIR__ . '/../config/db.php';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$user_id) { die('Invalid user.'); }

// Fetch user
$stmt = $pdo->prepare('SELECT * FROM users WHERE id=?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) { die('User not found.'); }

// Fetch current permissions
$perms = $pdo->prepare('SELECT menu, submenu, action FROM user_permissions WHERE user_id=?');
$perms->execute([$user_id]);
$permData = $perms->fetchAll(PDO::FETCH_ASSOC);
$existingPerms = [];
foreach ($permData as $p) {
    $existingPerms[$p['menu']][$p['submenu']][$p['action']] = true;
}

// Save permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $permissions = $_POST['perm'] ?? [];
    $pdo->prepare('DELETE FROM user_permissions WHERE user_id=?')->execute([$user_id]);
    if (!empty($permissions)) {
        $permStmt = $pdo->prepare('INSERT INTO user_permissions (user_id, menu, submenu, action) VALUES (?, ?, ?, ?)');
        foreach ($permissions as $menu => $subs) {
            foreach ($subs as $submenu => $actions) {
                foreach ($actions as $action => $val) {
                    if ($val) $permStmt->execute([$user_id, $menu, $submenu, $action]);
                }
            }
        }
    }
    header('Location: users.php');
    exit;
}

include __DIR__ . '/includes/top-menu.php';
include __DIR__ . '/includes/top-menu.php'; // for $menu

// Always reload $menu from top-menu.php for up-to-date menu/submenu list
$excludeMenus = include __DIR__ . '/permissions-exclude.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Permissions</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
        html,body{font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif!important;}
        .permissions-card { margin: 32px auto; max-width: 700px; background: #fffbe7; border-radius: 12px; box-shadow: 0 2px 8px #e0bebe22; padding: 24px; }
        .permissions-table th, .permissions-table td { border-bottom: 1px solid #f3caca; }
        .permissions-table th { background: #f9eaea; color: #800000; font-weight: 700; }
        .permissions-table tr:last-child td { border-bottom: none; }
        .perm-check { display: inline-flex; align-items: center; cursor: pointer; }
        .perm-check input[type="checkbox"] { accent-color: #800000; width: 18px; height: 18px; margin-right: 4px; }
    </style>
</head>
<body>
<div class="admin-container">
    <h1>Assign Permissions to <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)</h1>
    <form method="post" class="permissions-card">
        <table class="permissions-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th>Menu</th>
                    <th>Submenu</th>
                    <th>View</th>
                    <th>Edit</th>
                    <th>Delete</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($menu as $menuLabel => $menuItem): ?>
                <?php if (in_array($menuLabel, $excludeMenus)) continue; ?>
                <?php if (isset($menuItem['submenu'])): foreach ($menuItem['submenu'] as $subLabel => $subUrl): ?>
                    <tr>
                        <td><?= htmlspecialchars($menuLabel) ?></td>
                        <td><?= htmlspecialchars($subLabel) ?></td>
                        <td style="text-align:center;"><label class="perm-check"><input type="checkbox" name="perm[<?= $menuLabel ?>][<?= $subLabel ?>][view]" value="1"<?= isset($existingPerms[$menuLabel][$subLabel]['view']) ? ' checked' : '' ?>><span></span></label></td>
                        <td style="text-align:center;"><label class="perm-check"><input type="checkbox" name="perm[<?= $menuLabel ?>][<?= $subLabel ?>][edit]" value="1"<?= isset($existingPerms[$menuLabel][$subLabel]['edit']) ? ' checked' : '' ?>><span></span></label></td>
                        <td style="text-align:center;"><label class="perm-check"><input type="checkbox" name="perm[<?= $menuLabel ?>][<?= $subLabel ?>][delete]" value="1"<?= isset($existingPerms[$menuLabel][$subLabel]['delete']) ? ' checked' : '' ?>><span></span></label></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr>
                        <td><?= htmlspecialchars($menuLabel) ?></td>
                        <td>-</td>
                        <td style="text-align:center;"><label class="perm-check"><input type="checkbox" name="perm[<?= $menuLabel ?>][main][view]" value="1"<?= isset($existingPerms[$menuLabel]['main']['view']) ? ' checked' : '' ?>><span></span></label></td>
                        <td style="text-align:center;"><label class="perm-check"><input type="checkbox" name="perm[<?= $menuLabel ?>][main][edit]" value="1"<?= isset($existingPerms[$menuLabel]['main']['edit']) ? ' checked' : '' ?>><span></span></label></td>
                        <td style="text-align:center;"><label class="perm-check"><input type="checkbox" name="perm[<?= $menuLabel ?>][main][delete]" value="1"<?= isset($existingPerms[$menuLabel]['main']['delete']) ? ' checked' : '' ?>><span></span></label></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="text-align:center;margin-top:24px;">
            <button type="submit" class="btn-main">Save Permissions</button>
            <a href="users.php" class="btn-main" style="background:#6c757d;">Back</a>
        </div>
    </form>
</div>
</body>
</html>
