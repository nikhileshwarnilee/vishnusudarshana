<?php
require_once __DIR__ . '/../../config/db.php';

// Handle AJAX form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $category_name = trim($_POST['category_name'] ?? '');
    $category_slug = trim($_POST['category_slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $services_include = trim($_POST['services_include'] ?? '');
    $errors = [];

    if ($category_name === '') $errors[] = 'Category name is required.';
    if ($category_slug === '') $errors[] = 'Category slug is required.';
    // Slug uniqueness
    $stmt = $pdo->prepare("SELECT id FROM service_categories WHERE category_slug = ?");
    $stmt->execute([$category_slug]);
    if ($stmt->fetch()) {
        $errors[] = 'Slug must be unique.';
    }
    if (!$errors) {
        $stmt = $pdo->prepare("INSERT INTO service_categories (category_name, category_slug, description, services_include) VALUES (?, ?, ?, ?)");
        $stmt->execute([$category_name, $category_slug, $description, $services_include]);
        echo json_encode(['success' => true]);
        exit;
    } else {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
}

// Fetch categories for table
$stmt = $pdo->query("SELECT * FROM service_categories ORDER BY id DESC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Service Categories</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
    body { font-family: Arial, sans-serif; background: #f7f7fa; margin: 0; }
    .admin-container { max-width: 800px; margin: 0 auto; padding: 24px 12px; }
    h1 { color: #800000; margin-bottom: 18px; font-family: inherit; }
    .form-label { font-weight: 600; display:block; margin-bottom:6px; }
    .form-input, .form-textarea { width:100%; padding:10px 12px; border-radius:8px; border:1px solid #e0bebe; font-size:1.08em; margin-bottom:16px; font-family: inherit; }
    .form-input:focus, .form-textarea:focus { border-color: #800000; outline: none; }
    .form-btn { background:#800000; color:#fff; border:none; border-radius:8px; padding:12px 0; font-size:1.08em; font-weight:600; width:100%; cursor:pointer; margin-top:10px; transition: background 0.15s; }
    .form-btn:hover { background: #a00000; }
    .error-list { color:#c00; margin-bottom:18px; }
    .success-msg { color: #1a8917; margin-bottom: 18px; font-weight: 600; }
    .loading { color: #800000; font-weight: 600; margin-bottom: 12px; }
    .category-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 12px #e0bebe22; border-radius: 12px; overflow: hidden; margin-top: 32px; }
    .category-table th, .category-table td { padding: 12px 10px; border-bottom: 1px solid #f3caca; text-align: left; font-size: 1.04em; }
    .category-table th { background: #f9eaea; color: #800000; font-weight: 700; letter-spacing: 0.01em; }
    .category-table tr:last-child td { border-bottom: none; }
    .action-btn { background: #007bff; color: #fff; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-right: 6px; transition: background 0.15s; }
    .action-btn:hover { background: #0056b3; }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Service Categories</h1>
    <div id="form-messages"></div>
    <form id="addCategoryForm" method="post" autocomplete="off">
        <label class="form-label">Category Name:
            <input type="text" name="category_name" id="category_name" class="form-input" required>
        </label>
        <label class="form-label">Category Slug:
            <input type="text" name="category_slug" id="category_slug" class="form-input" required>
        </label>
        <label class="form-label">Description:
            <textarea name="description" class="form-textarea" rows="2"></textarea>
        </label>
        <label class="form-label">Services Include:
            <input type="text" name="services_include" class="form-input" placeholder="Comma separated services">
        </label>
        <button type="submit" class="form-btn">Add Category</button>
    </form>
    <table class="category-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Description</th>
                <th>Services Include</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $cat): ?>
            <tr>
                <td><?php echo $cat['id']; ?></td>
                <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                <td><?php echo htmlspecialchars($cat['category_slug']); ?></td>
                <td><?php echo htmlspecialchars($cat['description']); ?></td>
                <td><?php echo htmlspecialchars($cat['services_include']); ?></td>
                <td><a href="category-edit.php?id=<?php echo $cat['id']; ?>" class="action-btn">Edit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <script>
    // Instant slug generation
    $('#category_name').on('input', function() {
        var name = $(this).val();
        var slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        $('#category_slug').val(slug);
    });
    // AJAX form submission
    $('#addCategoryForm').on('submit', function(e) {
        e.preventDefault();
        $('#form-messages').html('<div class="loading">Adding category...</div>');
        $.ajax({
            url: window.location.pathname,
            type: 'POST',
            data: $(this).serialize() + '&ajax=1',
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    $('#form-messages').html('<div class="success-msg">Category added successfully!</div>');
                    $('#addCategoryForm')[0].reset();
                    setTimeout(function(){ location.reload(); }, 800);
                } else if (res.errors) {
                    var html = '<ul class="error-list">';
                    res.errors.forEach(function(e) { html += '<li>' + e + '</li>'; });
                    html += '</ul>';
                    $('#form-messages').html(html);
                }
            },
            error: function() {
                $('#form-messages').html('<ul class="error-list"><li>Server error. Please try again.</li></ul>');
            }
        });
    });
    </script>
</div>
</body>
</html>
