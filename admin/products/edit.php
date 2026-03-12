<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/product_variants.php';

vs_ensure_product_variant_schema($pdo);

$categoryOptions = [
    'appointment' => 'Appointment Booking',
    'birth-child' => 'Birth & Child Services',
    'marriage-matching' => 'Marriage & Matching',
    'astrology-consultation' => 'Astrology Consultation',
    'muhurat-event' => 'Muhurat & Event Guidance',
    'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
];

$id = $_GET['id'] ?? '';
if (!$id || !is_numeric($id)) {
    die('Invalid product ID.');
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) die('Product not found.');

$product_name = $product['product_name'];
$product_slug = $product['product_slug'];
$category_slug = $product['category_slug'];
$short_description = $product['short_description'];
$long_description = $product['long_description'] ?? '';
$price = $product['price'];
$variant_id = isset($product['variant_id']) ? (string)$product['variant_id'] : '';
$is_active = $product['is_active'];
$errors = [];
$variants = vs_get_product_variants($pdo, false);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $product_name = trim($_POST['product_name'] ?? '');
    $product_slug = trim($_POST['product_slug'] ?? '');
    $category_slug = $_POST['category_slug'] ?? '';
    $short_description = trim($_POST['short_description'] ?? '');
    $long_description = trim($_POST['long_description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $variant_id = trim((string)($_POST['variant_id'] ?? ''));
    $is_active = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;

    if ($product_name === '') $errors[] = 'Product name is required.';
    if ($product_slug === '') $errors[] = 'Product slug is required.';
    if ($category_slug === '' || !isset($categoryOptions[$category_slug])) $errors[] = 'Category is required.';
    if ($price === '' || !is_numeric($price)) $errors[] = 'Valid price is required.';

    $variantIdForSave = null;
    if ($variant_id !== '') {
        if (!ctype_digit($variant_id)) {
            $errors[] = 'Invalid variant selected.';
        } else {
            $variantIdForSave = (int)$variant_id;
            $variantCheck = $pdo->prepare("SELECT id FROM product_variants WHERE id = ? LIMIT 1");
            $variantCheck->execute([$variantIdForSave]);
            if (!$variantCheck->fetchColumn()) {
                $errors[] = 'Selected variant does not exist.';
            }
        }
    }

    // Slug uniqueness (exclude current product)
    $stmt = $pdo->prepare("SELECT id FROM products WHERE product_slug = ? AND id != ?");
    $stmt->execute([$product_slug, $id]);
    if ($stmt->fetch()) {
        $errors[] = 'Slug must be unique.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("UPDATE products SET category_slug=?, product_name=?, product_slug=?, short_description=?, long_description=?, price=?, variant_id=?, is_active=? WHERE id=?");
        $stmt->execute([$category_slug, $product_name, $product_slug, $short_description, $long_description, $price, $variantIdForSave, $is_active, $id]);
        echo json_encode(['success' => true]);
        exit;
    } else {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
    .admin-container { max-width: 700px; margin: 0 auto; padding: 24px 12px; }
    h1 { color: #800000; margin-bottom: 18px; font-family: inherit; }
    .form-label { font-weight: 600; display:block; margin-bottom:6px; }
    .form-input, .form-select, .form-textarea { width:100%; padding:10px 12px; border-radius:8px; border:1px solid #e0bebe; font-size:1.08em; margin-bottom:16px; font-family: inherit; }
    .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: #800000; outline: none; }
    .editor-wrapper { border:1px solid #e0bebe; border-radius:12px; overflow:hidden; margin-bottom:16px; }
    .editor-area { min-height:200px; }
    .form-btn { background:#800000; color:#fff; border:none; border-radius:8px; padding:12px 0; font-size:1.08em; font-weight:600; width:100%; cursor:pointer; margin-top:10px; transition: background 0.15s; }
    .form-btn:hover { background: #a00000; }
    .back-link { display:inline-block; margin-bottom:18px; color:#800000; text-decoration:none; font-weight:600; }
    .error-list { color:#c00; margin-bottom:18px; }
    .success-msg { color: #1a8917; margin-bottom: 18px; font-weight: 600; }
    .loading { color: #800000; font-weight: 600; margin-bottom: 12px; }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Edit Product</h1>
    <a href="index.php" class="back-link">&larr; Back to Product List</a>
    <div id="form-messages"></div>
    <form id="editProductForm" method="post" autocomplete="off">
        <label class="form-label">Product Name:
            <input type="text" name="product_name" id="product_name" class="form-input" value="<?php echo htmlspecialchars($product_name); ?>" required>
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
        </label>
        <label class="form-label" for="long_description_editor">Long Description (Read More Content):</label>
        <div class="editor-wrapper">
            <textarea id="long_description_editor" name="long_description" class="editor-area"><?php echo htmlspecialchars($long_description); ?></textarea>
        </div>
        <label class="form-label">Price:
            <input type="number" name="price" class="form-input" value="<?php echo htmlspecialchars($price); ?>" step="0.01" required>
        </label>
        <label class="form-label">Variant (Optional):
            <select name="variant_id" class="form-select">
                <option value="">-- No Variant --</option>
                <?php foreach ($variants as $variant): ?>
                    <option value="<?php echo (int)$variant['id']; ?>" <?php if ((string)$variant_id === (string)$variant['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($variant['variant_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="form-label">Status:
            <select name="is_active" class="form-select">
                <option value="1" <?php if ($is_active == 1) echo 'selected'; ?>>Active</option>
                <option value="0" <?php if ($is_active == 0) echo 'selected'; ?>>Inactive</option>
            </select>
        </label>
        <button type="submit" class="form-btn">Save Changes</button>
    </form>
    <script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.1/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '#long_description_editor',
            height: 280,
            menubar: 'edit view insert format tools table',
            plugins: 'advlist autolink lists link image media table code preview searchreplace visualblocks wordcount charmap emoticons autoresize anchor',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table blockquote | removeformat | preview code',
            toolbar_mode: 'sliding',
            branding: false,
            promotion: false,
            content_style: 'body { font-family:Segoe UI,Tahoma,Verdana,sans-serif; font-size:15px } img { max-width:100%; height:auto; }',
            object_resizing: 'img,table,iframe,video',
            image_advtab: true,
            image_caption: true,
            image_dimensions: true,
            media_dimensions: true,
            media_live_embeds: true,
            link_default_target: '_blank',
            extended_valid_elements: 'iframe[src|frameborder|style|scrolling|class|width|height|name|align|allow|allowfullscreen],video[*],source[*]',
            images_upload_handler: (blobInfo, progress) => new Promise((resolve, reject) => {
                const file = blobInfo.blob();
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'upload-editor-image.php', true);
                xhr.responseType = 'json';
                xhr.onerror = () => reject('Upload failed');
                xhr.onload = () => {
                    const response = xhr.response || {};
                    if (!response.url) {
                        reject(response.error || 'Upload failed');
                        return;
                    }
                    resolve(response.url);
                };
                const data = new FormData();
                data.append('upload', file);
                xhr.send(data);
            })
        });
    }

    // Instant slug generation
    $('#product_name').on('input', function() {
        var name = $(this).val();
        var slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        $('#product_slug').val(slug);
    });

    // AJAX form submission
    $('#editProductForm').on('submit', function(e) {
        e.preventDefault();
        if (window.tinymce && window.tinymce.get('long_description_editor')) {
            window.tinymce.triggerSave();
        }
        $('#form-messages').html('<div class="loading">Saving changes...</div>');
        $.ajax({
            url: window.location.pathname + '?id=<?php echo $id; ?>',
            type: 'POST',
            data: $(this).serialize() + '&ajax=1',
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    $('#form-messages').html('<div class="success-msg">Product updated successfully!</div>');
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

