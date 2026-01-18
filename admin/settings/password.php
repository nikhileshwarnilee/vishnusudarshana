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
        body { margin: 0; background: #f7f7fa; }
        .admin-container { max-width: 500px; margin: 40px auto; padding: 24px; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px #e0bebe22; box-sizing: border-box; }
        h1 { color: #800000; margin-top: 0; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #333; }
        .form-group input { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 1em; box-sizing: border-box; }
        .form-group input:focus { border-color: #800000; outline: none; }
        .btn-main { padding: 8px 18px; background: #800000; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; width: 100%; }
        .btn-main:hover { background: #600000; }
        .msg { margin-bottom: 16px; font-weight: 600; }
        @media (max-width: 768px) {
            .admin-container { margin: 30px auto; padding: 20px; }
            h1 { font-size: 1.4em; margin-bottom: 14px; }
            .form-group { margin-bottom: 14px; }
            .form-group label { margin-bottom: 4px; }
            .form-group input { padding: 8px 10px; font-size: 0.95em; }
            .btn-main { padding: 10px 0; font-size: 0.95em; }
        }
        @media (max-width: 600px) {
            .admin-container { margin: 20px 12px; padding: 16px 14px; border-radius: 10px; }
            h1 { font-size: 1.2em; margin-bottom: 12px; }
            .form-group { margin-bottom: 12px; }
            .form-group label { font-size: 0.9em; margin-bottom: 3px; }
            .form-group input { padding: 8px 8px; font-size: 0.9em; }
            .msg { font-size: 0.9em; }
        }
        @media (max-width: 400px) {
            .admin-container { margin: 15px 8px; padding: 12px 10px; }
            h1 { font-size: 1.1em; }
            .form-group input { font-size: 0.85em; }
        }
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
