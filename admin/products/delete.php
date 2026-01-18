<?php
require_once '../../config/db.php';

$id = $_GET['id'] ?? '';
if (!$id || !is_numeric($id)) {
    die('Invalid product ID.');
}

// Confirm deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: index.php');
        exit;
    } else {
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete Product</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
    .admin-container { max-width: 500px; margin: 0 auto; padding: 24px 12px; }
    h1 { color: #800000; margin-bottom: 18px; font-family: inherit; }
    .form-btn { background:#800000; color:#fff; border:none; border-radius:8px; padding:12px 0; font-size:1.08em; font-weight:600; width:48%; cursor:pointer; margin:0 2% 0 0; transition: background 0.15s; }
    .form-btn:hover { background: #a00000; }
    .back-link { display:inline-block; margin-top:18px; color:#800000; text-decoration:none; font-weight:600; }
    .danger { background:#c00; }
    .danger:hover { background:#a00000; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Delete Product</h1>
    <form method="post" style="margin-bottom:18px;">
        <p style="font-size:1.08em;">Are you sure you want to delete this product permanently?</p>
        <button type="submit" name="confirm" value="yes" class="form-btn danger">Yes, Delete</button>
        <button type="submit" name="confirm" value="no" class="form-btn">Cancel</button>
    </form>
    <a href="index.php" class="back-link">&larr; Back to Product List</a>
</div>
</body>
</html>
