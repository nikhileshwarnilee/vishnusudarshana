<?php
require_once __DIR__ . '/../config/db.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] == 1) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Staff Dashboard</h1>
    <!-- Staff dashboard content will go here -->
</div>
</body>
</html>
