<?php
// admin/cif/category.php
// CIF Category page with add form and table
require_once __DIR__ . '/../../config/db.php';


$msg = '';
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_cat = null;

// Handle add or update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['category_name'], $_POST['category_color'])) {
    $name = trim($_POST['category_name']);
    $color = trim($_POST['category_color']);
    $id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    if ($name !== '' && $color !== '') {
        if ($id > 0) {
            // Update
            $stmt = $pdo->prepare('UPDATE cif_categories SET name=?, color=? WHERE id=?');
            $stmt->execute([$name, $color, $id]);
            $msg = 'Category updated!';
            $edit_id = 0;
            header('Location: category.php');
            exit;
        } else {
            // Insert
            $stmt = $pdo->prepare('INSERT INTO cif_categories (name, color) VALUES (?, ?)');
            $stmt->execute([$name, $color]);
            $msg = 'Category added!';
        }
    } else {
        $msg = 'Please enter a name and select a color.';
    }
}

// If editing, fetch the category
if ($edit_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM cif_categories WHERE id = ?');
    $stmt->execute([$edit_id]);
    $edit_cat = $stmt->fetch();
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare('DELETE FROM cif_categories WHERE id = ?')->execute([$id]);
    header('Location: category.php');
    exit;
}

// Fetch categories
$categories = $pdo->query('SELECT * FROM cif_categories ORDER BY id DESC')->fetchAll();
$total_categories = count($categories);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CIF Category</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
.admin-container { max-width: 1100px; margin: 0 auto; padding: 24px 12px; }
h1 { color: #800000; margin-bottom: 18px; }
.summary-cards { display: flex; gap: 18px; margin-bottom: 24px; flex-wrap: wrap; }
.summary-card { flex: 1 1 180px; background: #fffbe7; border-radius: 14px; padding: 16px; text-align: center; box-shadow: 0 2px 8px #e0bebe22; }
.summary-count { font-size: 2.2em; font-weight: 700; color: #800000; }
.summary-label { font-size: 1em; color: #444; }
@media (max-width: 700px) { .summary-cards { flex-direction: column; } }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>CIF Category</h1>
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-count"><?= $total_categories ?></div>
            <div class="summary-label">Categories</div>
        </div>
    </div>
    <div style="background:#fff;padding:24px;border-radius:12px;box-shadow:0 2px 8px #e0bebe22;">

        <h2 style="color:#800000;"> <?= $edit_cat ? 'Edit Category' : 'Add Category' ?> </h2>
        <?php if ($msg): ?>
            <div style="color: var(--maroon); font-weight: 600; margin-bottom: 12px;"> <?= htmlspecialchars($msg) ?> </div>
        <?php endif; ?>
        <form method="post" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;margin-bottom:24px;">
            <input type="text" name="category_name" placeholder="Category Name" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= $edit_cat ? htmlspecialchars($edit_cat['name']) : '' ?>">
            <input type="color" name="category_color" value="<?= $edit_cat ? htmlspecialchars($edit_cat['color']) : '#800000' ?>" required style="width:48px;height:38px;border:none;">
            <?php if ($edit_cat): ?>
                <input type="hidden" name="edit_id" value="<?= (int)$edit_cat['id'] ?>">
            <?php endif; ?>
            <button type="submit" style="padding:10px 22px;background:var(--maroon);color:#fff;border:none;border-radius:6px;font-weight:600;">
                <?= $edit_cat ? 'Update Category' : 'Add Category' ?>
            </button>
            <?php if ($edit_cat): ?>
                <a href="category.php" style="padding:10px 22px;background:#6c757d;color:#fff;border:none;border-radius:6px;font-weight:600;text-decoration:none;">Cancel</a>
            <?php endif; ?>
        </form>

        <h2 style="color:var(--maroon);">Categories List</h2>
        <table style="width:100%;border-collapse:collapse;background:#fff;box-shadow:0 2px 8px rgba(139,21,56,0.08);border-radius:12px;overflow:hidden;">
            <thead>
                <tr style="background:#f9eaea;color:var(--maroon);">
                    <th style="padding:12px 10px;">#</th>
                    <th style="padding:12px 10px;">Name</th>
                    <th style="padding:12px 10px;">Color</th>
                    <th style="padding:12px 10px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="4" style="text-align:center;color:#777;padding:24px;">No categories found.</td></tr>
                <?php else: foreach ($categories as $cat): ?>
                    <tr id="cat-row-<?= (int)$cat['id'] ?>">
                        <td style="padding:10px;"> <?= (int)$cat['id'] ?> </td>
                        <td style="padding:10px;">
                            <span class="cat-view-<?= (int)$cat['id'] ?>"> <?= htmlspecialchars($cat['name']) ?> </span>
                            <form method="post" class="cat-edit-<?= (int)$cat['id'] ?>" style="display:none;">
                                <input type="text" name="category_name" value="<?= htmlspecialchars($cat['name']) ?>" required style="padding:6px 10px;border-radius:6px;border:1px solid #ccc;font-size:0.95em;">
                            </form>
                        </td>
                        <td style="padding:10px;">
                            <span class="cat-view-<?= (int)$cat['id'] ?>">
                                <span style="display:inline-block;width:28px;height:28px;border-radius:50%;background:<?= htmlspecialchars($cat['color']) ?>;border:1px solid #ccc;"></span>
                                <span style="margin-left:8px;"> <?= htmlspecialchars($cat['color']) ?> </span>
                            </span>
                            <span class="cat-edit-<?= (int)$cat['id'] ?>" style="display:none;">
                                <input type="color" name="category_color" value="<?= htmlspecialchars($cat['color']) ?>" required style="width:48px;height:38px;border:none;" form="cat-form-<?= (int)$cat['id'] ?>">
                            </span>
                        </td>
                        <td style="padding:10px;">
                            <span class="cat-view-<?= (int)$cat['id'] ?>">
                                <button type="button" onclick="toggleEditCat(<?= (int)$cat['id'] ?>)" style="padding:6px 14px;background:#007bff;color:#fff;border:none;border-radius:6px;font-weight:600;margin-right:8px;cursor:pointer;">Edit</button>
                                <a href="category.php?delete=<?= (int)$cat['id'] ?>" onclick="return confirm('Delete this category?');" style="padding:6px 14px;background:#dc3545;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">Delete</a>
                            </span>
                            <form id="cat-form-<?= (int)$cat['id'] ?>" method="post" class="cat-edit-<?= (int)$cat['id'] ?>" style="display:none;">
                                <input type="hidden" name="edit_id" value="<?= (int)$cat['id'] ?>">
                                <button type="submit" style="padding:6px 14px;background:#28a745;color:#fff;border:none;border-radius:6px;font-weight:600;margin-right:8px;">Save</button>
                                <button type="button" onclick="toggleEditCat(<?= (int)$cat['id'] ?>)" style="padding:6px 14px;background:#6c757d;color:#fff;border:none;border-radius:6px;font-weight:600;">Cancel</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function toggleEditCat(id) {
    const viewEls = document.querySelectorAll('.cat-view-' + id);
    const editEls = document.querySelectorAll('.cat-edit-' + id);
    viewEls.forEach(el => el.style.display = el.style.display === 'none' ? '' : 'none');
    editEls.forEach(el => el.style.display = el.style.display === 'none' ? '' : 'none');
}
</script>
<script src="/assets/js/language.js"></script>
</body>
</html>
