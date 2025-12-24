<?php
// admin/settings/password.php
require_once __DIR__ . '/../../config/db.php';
session_start();

// Dummy user id for demo (replace with session user id in real use)
$userId = $_SESSION['user_id'] ?? 1;

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (!$current || !$new || !$confirm) {
        $message = 'All fields are required!';
    } elseif ($new !== $confirm) {
        $message = 'New passwords do not match!';
    } else {
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id=?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || $current !== $row['password']) {
            $message = 'Current password is incorrect!';
        } else {
            // Store new password as plain text (insecure)
            $stmt = $pdo->prepare('UPDATE users SET password=? WHERE id=?');
            $stmt->execute([$new, $userId]);
            $message = 'Password changed successfully!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .admin-container { max-width: 500px; margin: 40px auto; padding: 24px; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px #e0bebe22; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #333; }
        .form-group input { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 1em; }
        .btn-main { padding: 8px 18px; background: #800000; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-main:hover { background: #600000; }
        .msg { margin-bottom: 16px; font-weight: 600; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Change Password</h1>
    <?php if ($message): ?><div class="msg" style="color:<?= strpos($message,'success')!==false?'green':'red' ?>;"> <?= htmlspecialchars($message) ?> </div><?php endif; ?>
    <form method="post" autocomplete="off">
        <div class="form-group">
            <label>Current Password</label>
            <input type="password" name="current_password" required>
        </div>
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" required>
        </div>
        <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn-main">Change Password</button>
    </form>
</div>
</body>
</html>
