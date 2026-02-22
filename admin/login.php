<?php
// admin/login.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/favicon.php';
session_start();

// If already logged in, redirect to admin dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_id'] == 1) {
        header('Location: index.php');
    } else {
        header('Location: staff-dashboard.php');
    }
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && $user['password'] === $password) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        if ($user['id'] == 1) {
            header('Location: index.php');
        } else {
            header('Location: staff-dashboard.php');
        }
        exit;
    } else {
        $message = 'Invalid email or password!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    <?php echo vs_favicon_tags(); ?>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
        html,body{font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif!important;}
        body { background: #f7f7fa; }
        .login-container { max-width: 400px; margin: 60px auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px #e0bebe22; padding: 32px 28px; }
        h1 { color: #800000; text-align: center; margin-bottom: 24px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #333; }
        .form-group input { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 1em; }
        .btn-main { padding: 8px 18px; background: #800000; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; width: 100%; }
        .btn-main:hover { background: #600000; }
        .msg { margin-bottom: 16px; font-weight: 600; text-align: center; }
        @media (max-width: 600px) {
            body { padding: 16px 0; }
            .login-container { margin: 28px 12px; padding: 24px 18px; }
            h1 { font-size: 1.4em; }
        }
    </style>
</head>
<body>
<div class="login-container">
    <div style="text-align:center;margin-bottom:18px;">
        <img src="../assets/images/logo/logomain.png" alt="Logo" style="height:54px;max-width:90%;">
    </div>
    <h1>Admin Login</h1>
    <?php if ($message): ?><div class="msg" style="color:red;"> <?= htmlspecialchars($message) ?> </div><?php endif; ?>
    <form method="post" autocomplete="off">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn-main">Login</button>
    </form>
</div>
</body>
</html>
