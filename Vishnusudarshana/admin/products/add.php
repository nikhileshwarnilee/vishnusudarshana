<?php
require_once __DIR__ . '/../../config/db.php';

$categoryOptions = [
    'appointment' => 'Appointment Booking',
    'birth-child' => 'Birth & Child Services',
    'marriage-matching' => 'Marriage & Matching',
    'astrology-consultation' => 'Astrology Consultation',
    'muhurat-event' => 'Muhurat & Event Guidance',
    'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
];

$product_name = $product_slug = $category_slug = $short_description = $price = '';
$is_active = 1;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = trim($_POST['product_name'] ?? '');
    $product_slug = trim($_POST['product_slug'] ?? '');
    $category_slug = $_POST['category_slug'] ?? '';
    $short_description = trim($_POST['short_description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $is_active = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;

    // Validation
    if ($product_name === '') $errors[] = 'Product name is required.';
    if ($product_slug === '') $errors[] = 'Product slug is required.';
    if ($category_slug === '' || !isset($categoryOptions[$category_slug])) $errors[] = 'Category is required.';
    if ($price === '' || !is_numeric($price)) $errors[] = 'Valid price is required.';

    // Slug uniqueness
    $stmt = $pdo->prepare("SELECT id FROM products WHERE product_slug = ?");
    $stmt->execute([$product_slug]);
    if ($stmt->fetch()) {
        $errors[] = 'Slug must be unique.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("INSERT INTO products (category_slug, product_name, product_slug, short_description, price, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$category_slug, $product_name, $product_slug, $short_description, $price, $is_active]);
        header('Location: index.php');
        exit;
    }
}

function generateSlug($str) {
    $slug = strtolower(trim($str));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Product</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
    body { font-family: Arial, sans-serif; background: #f7f7fa; margin: 0; }
    .admin-container { max-width: 700px; margin: 0 auto; padding: 24px 12px; }
    h1 { color: #800000; margin-bottom: 18px; font-family: inherit; }
    .form-label { font-weight: 600; display:block; margin-bottom:6px; }
    .form-input, .form-select, .form-textarea { width:100%; padding:10px 12px; border-radius:8px; border:1px solid #e0bebe; font-size:1.08em; margin-bottom:16px; font-family: inherit; }
    .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: #800000; outline: none; }
    .form-btn { background:#800000; color:#fff; border:none; border-radius:8px; padding:12px 0; font-size:1.08em; font-weight:600; width:100%; cursor:pointer; margin-top:10px; transition: background 0.15s; }
    .form-btn:hover { background: #a00000; }
    .back-link { display:inline-block; margin-bottom:18px; color:#800000; text-decoration:none; font-weight:600; }
    .error-list { color:#c00; margin-bottom:18px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Add Product</h1>
    <a href="index.php" class="back-link">&larr; Back to Product List</a>
    <?php if ($errors): ?>
        <ul class="error-list">
            <?php foreach ($errors as $e) echo "<li>$e</li>"; ?>
        </ul>
    <?php endif; ?>
    <form method="post">
        <label class="form-label">Product Name:
            <input type="text" name="product_name" id="product_name" class="form-input" value="<?php echo htmlspecialchars($product_name); ?>" oninput="updateSlug()" required>
        </label>
        <label class="form-label">Product Slug:
            <input type="text" name="product_slug" id="product_slug" class="form-input" value="<?php echo htmlspecialchars($product_slug); ?>" required>
        </label>
        <label class="form-label">Category:
            <select name="category_slug" class="form-select" required>
                <option value="">--Select--</option>
                <?php foreach ($categoryOptions as $val => $label): ?>
                    <option value="<?php echo $val; ?>" <?php if ($category_slug === $val) echo 'selected'; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="form-label">Short Description:
            <textarea name="short_description" class="form-textarea" rows="3"><?php echo htmlspecialchars($short_description); ?></textarea>
        </label><br><br>
        <label>Price: <input type="number" name="price" value="<?php echo htmlspecialchars($price); ?>" step="0.01" required></label><br><br>
        <label>Status:
            <select name="is_active">
                <option value="1" <?php if ($is_active == 1) echo 'selected'; ?>>Active</option>
                <option value="0" <?php if ($is_active == 0) echo 'selected'; ?>>Inactive</option>
            </select>
        </label><br><br>
        <button type="submit">Add Product</button>
    </form>
    <script>
    function updateSlug() {
        var name = document.getElementById('product_name').value;
        var slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        document.getElementById('product_slug').value = slug;
    }
    </script>
</body>
</html>
