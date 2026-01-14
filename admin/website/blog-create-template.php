<?php
// blog-create-template.php: Shared template for create/edit blog
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($editId) && $editId > 0 ? 'Edit Blog' : 'Create Blog' ?> - Admin</title>
    <!-- Copy all styles from blog-create.php here -->
    <?php /* ...existing CSS from blog-create.php... */ ?>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <div class="page-header">
        <h1><?= isset($editId) && $editId > 0 ? 'Edit Blog' : 'Create Blog' ?></h1>
        <a class="back-link" href="blogs-management.php">← Back to Blogs Management</a>
    </div>
    <div class="content-card">
        <?php if (!empty($errors)): ?>
            <div class="pill-note" style="background:#fef2f2; color:#800000; border-color:#f5c6cb;">
                <?= implode('<br>', $errors); ?>
            </div>
        <?php elseif (isset($success) && $success): ?>
            <div class="pill-note" style="background:#d1e7dd; color:#0f5132; border-color:#badbcc;">
                Blog updated successfully! Redirecting...
            </div>
        <?php endif; ?>
        <!-- Copy the form and JS from blog-create.php here, using PHP variables for values -->
        <?php /* ...form and JS from blog-create.php... */ ?>
    </div>
</div>
</body>
</html>
