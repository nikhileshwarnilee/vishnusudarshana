<?php
require_once __DIR__ . '/../../config/db.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Check if we have a valid database connection
if (!$pdo) {
    die('Database connection error');
}

// Default categories/services from services.php
$default_categories = [
    [
        'category_name' => 'Appointment',
        'category_slug' => 'appointment',
        'description' => 'Schedule an online or offline appointment; final slot confirmed by our team.',
        'services_include' => 'Booking',
        'logo' => '',
    ],
    [
        'category_name' => 'Birth & Child Services',
        'category_slug' => 'birth-child',
        'description' => 'Janma Patrika, name suggestions, baby horoscope and child guidance.',
        'services_include' => 'Paid Service',
        'logo' => '',
    ],
    [
        'category_name' => 'Marriage & Matching',
        'category_slug' => 'marriage-matching',
        'description' => 'Kundali Milan, marriage prediction and compatibility analysis.',
        'services_include' => 'Paid Service',
        'logo' => '',
    ],
    [
        'category_name' => 'Astrology Consultation',
        'category_slug' => 'astrology-consultation',
        'description' => 'Career, marriage, health, finance and personal guidance.',
        'services_include' => 'Consultation',
        'logo' => '',
    ],
    [
        'category_name' => 'Muhurat & Event Guidance',
        'category_slug' => 'muhurat-event',
        'description' => 'Marriage, griha pravesh, vehicle purchase and business start muhurat.',
        'services_include' => 'Guidance',
        'logo' => '',
    ],
    [
        'category_name' => 'Pooja, Ritual & Vastu Enquiry',
        'category_slug' => 'pooja-vastu-enquiry',
        'description' => 'Pooja, shanti, dosh nivaran, yagya and vastu consultation.',
        'services_include' => 'Enquiry (No Payment)',
        'logo' => '',
    ],
];

// Insert default categories if not present
foreach ($default_categories as $cat) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM service_categories WHERE category_slug = ?");
        $stmt->execute([$cat['category_slug']]);
        if (!$stmt->fetch()) {
            $ins = $pdo->prepare("INSERT INTO service_categories (category_name, category_slug, description, services_include, logo) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$cat['category_name'], $cat['category_slug'], $cat['description'], $cat['services_include'], $cat['logo']]);
        }
    } catch (Exception $e) {
        error_log("Error inserting default category: " . $e->getMessage());
    }
}

// Ensure sequence column exists in DB
try {
    $pdo->exec("ALTER TABLE service_categories ADD COLUMN IF NOT EXISTS sequence INT DEFAULT 0");
} catch (Exception $e) {
    // Column may already exist
}

// Ensure all categories have unique, ordered sequence values
try {
    $allCats = $pdo->query("SELECT id FROM service_categories ORDER BY sequence ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allCats as $i => $cat) {
        $pdo->prepare("UPDATE service_categories SET sequence=? WHERE id=?")->execute([$i+1, $cat['id']]);
    }
} catch (Exception $e) {
    error_log("Error updating sequences: " . $e->getMessage());
}

// Handle AJAX reorder (robust)
if (isset($_POST['reorder']) && isset($_POST['id']) && isset($_POST['direction'])) {
    $id = (int)$_POST['id'];
    $direction = $_POST['direction'] === 'up' ? 'up' : 'down';
    $stmt = $pdo->prepare("SELECT id, sequence FROM service_categories WHERE id = ?");
    $stmt->execute([$id]);
    $cat = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cat) {
        $neighborStmt = $pdo->prepare(
            $direction === 'up'
                ? "SELECT id, sequence FROM service_categories WHERE sequence < ? ORDER BY sequence DESC LIMIT 1"
                : "SELECT id, sequence FROM service_categories WHERE sequence > ? ORDER BY sequence ASC LIMIT 1"
        );
        $neighborStmt->execute([$cat['sequence']]);
        $neighbor = $neighborStmt->fetch(PDO::FETCH_ASSOC);
        if ($neighbor) {
            // Swap sequence values
            $pdo->prepare("UPDATE service_categories SET sequence=? WHERE id=?")->execute([$neighbor['sequence'], $cat['id']]);
            $pdo->prepare("UPDATE service_categories SET sequence=? WHERE id=?")->execute([$cat['sequence'], $neighbor['id']]);
            echo json_encode(['success'=>true]);
            exit;
        }
    }
    echo json_encode(['success'=>false]);
    exit;
}

// Handle AJAX form submission (add or update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $category_name = trim($_POST['category_name'] ?? '');
    $category_slug = trim($_POST['category_slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $services_include = trim($_POST['services_include'] ?? '');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $errors = [];
    $logo_filename = '';

    if ($category_name === '') $errors[] = 'Category name is required.';
    if ($category_slug === '') $errors[] = 'Category slug is required.';
    // Slug uniqueness
    $stmt = $pdo->prepare("SELECT id FROM service_categories WHERE category_slug = ?" . ($id ? " AND id != ?" : ""));
    $stmt->execute($id ? [$category_slug, $id] : [$category_slug]);
    if ($stmt->fetch()) {
        $errors[] = 'Slug must be unique.';
    }
    // Handle logo upload
    if (isset($_FILES['category_logo']) && $_FILES['category_logo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['category_logo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $errors[] = 'Logo must be an image file (jpg, png, gif, webp).';
        } else {
            $logo_filename = 'cat_' . uniqid() . '.' . $ext;
            $dest = __DIR__ . '/../../assets/images/logo/' . $logo_filename;
            move_uploaded_file($_FILES['category_logo']['tmp_name'], $dest);
        }
    }
    if (!$errors) {
        if ($id) {
            // Update
            $sql = "UPDATE service_categories SET category_name=?, category_slug=?, description=?, services_include=?";
            $params = [$category_name, $category_slug, $description, $services_include];
            if ($logo_filename) {
                $sql .= ", logo=?";
                $params[] = $logo_filename;
            }
            $sql .= " WHERE id=?";
            $params[] = $id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO service_categories (category_name, category_slug, description, services_include, logo) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$category_name, $category_slug, $description, $services_include, $logo_filename]);
        }
        echo json_encode(['success' => true]);
        exit;
    } else {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
}

// When inserting default categories, set sequence
foreach ($default_categories as $i => $cat) {
    $stmt = $pdo->prepare("SELECT id FROM service_categories WHERE category_slug = ?");
    $stmt->execute([$cat['category_slug']]);
    if (!$stmt->fetch()) {
        $ins = $pdo->prepare("INSERT INTO service_categories (category_name, category_slug, description, services_include, logo, sequence) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->execute([$cat['category_name'], $cat['category_slug'], $cat['description'], $cat['services_include'], $cat['logo'], $i+1]);
    }
}

// Fetch categories for table (ordered by sequence)
$stmt = $pdo->query("SELECT * FROM service_categories ORDER BY sequence ASC, id ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Service Categories</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
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
    .cat-logo { width: 48px; height: 48px; object-fit: contain; border-radius: 8px; background: #f3f3f3; border: 1px solid #eee; }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Service Categories</h1>
    <div id="form-messages"></div>
    <form id="addCategoryForm" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="id" id="category_id">
        <label class="form-label">Category Name:
            <input type="text" name="category_name" id="category_name" class="form-input" required>
        </label>
        <label class="form-label">Category Slug:
            <input type="text" name="category_slug" id="category_slug" class="form-input" required>
        </label>
        <label class="form-label">Description:
            <textarea name="description" class="form-textarea" rows="2" id="category_desc"></textarea>
        </label>
        <label class="form-label">Services Include:
            <input type="text" name="services_include" class="form-input" id="category_services" placeholder="Comma separated services">
        </label>
        <label class="form-label">Category Logo:
            <input type="file" name="category_logo" accept="image/*" class="form-input" id="category_logo">
            <span id="currentLogo"></span>
        </label>
        <button type="submit" class="form-btn" id="formBtn">Add Category</button>
        <button type="button" class="form-btn" id="cancelEdit" style="display:none;background:#aaa;">Cancel</button>
    </form>
    <table class="category-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Logo</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Description</th>
                <th>Services Include</th>
                <th>Order</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $idx => $cat): ?>
            <tr>
                <td><?php echo $cat['id']; ?></td>
                <td>
                    <?php if (!empty($cat['logo'])): ?>
                        <img src="/assets/images/logo/<?php echo htmlspecialchars($cat['logo']); ?>" class="cat-logo" alt="Logo">
                    <?php else: ?>
                        <span style="color:#aaa;">—</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                <td><?php echo htmlspecialchars($cat['category_slug']); ?></td>
                <td><?php echo htmlspecialchars($cat['description']); ?></td>
                <td><?php echo htmlspecialchars($cat['services_include']); ?></td>
                <td>
                    <button class="order-btn" data-id="<?php echo $cat['id']; ?>" data-dir="up" <?php if ($idx==0) echo 'disabled'; ?>>&#8593;</button>
                    <button class="order-btn" data-id="<?php echo $cat['id']; ?>" data-dir="down" <?php if ($idx==count($categories)-1) echo 'disabled'; ?>>&#8595;</button>
                </td>
                <td><a href="#" class="action-btn edit-btn">Edit</a></td>
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
        var formData = new FormData(this);
        formData.append('ajax', '1');
        $('#form-messages').html('<div class="loading">Saving category...</div>');
        $.ajax({
            url: window.location.pathname,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    $('#form-messages').html('<div class="success-msg">Category saved!</div>');
                    $('#addCategoryForm')[0].reset();
                    $('#formBtn').text('Add Category');
                    $('#cancelEdit').hide();
                    $('#currentLogo').html('');
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
    // Edit button handler
    $('.edit-btn, .action-btn').on('click', function(e) {
        e.preventDefault();
        var row = $(this).closest('tr');
        $('#category_id').val(row.find('td:eq(0)').text().trim());
        $('#category_name').val(row.find('td:eq(2)').text().trim());
        $('#category_slug').val(row.find('td:eq(3)').text().trim());
        $('#category_desc').val(row.find('td:eq(4)').text().trim());
        $('#category_services').val(row.find('td:eq(5)').text().trim());
        var logo = row.find('img.cat-logo').attr('src');
        if (logo) {
            $('#currentLogo').html('<img src="'+logo+'" class="cat-logo" style="margin-top:8px;">');
        } else {
            $('#currentLogo').html('');
        }
        $('#formBtn').text('Update Category');
        $('#cancelEdit').show();
        $('html,body').animate({scrollTop:$('.admin-container').offset().top}, 300);
    });
    // Cancel edit
    $('#cancelEdit').on('click', function() {
        $('#addCategoryForm')[0].reset();
        $('#category_id').val('');
        $('#formBtn').text('Add Category');
        $('#cancelEdit').hide();
        $('#currentLogo').html('');
    });
    // Order up/down
    $(document).on('click', '.order-btn', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var dir = $(this).data('dir');
        $.post(window.location.pathname, {reorder:1, id:id, direction:dir}, function(res) {
            if (res.success) location.reload();
        }, 'json');
    });
    </script>
</div>
</body>
</html>
