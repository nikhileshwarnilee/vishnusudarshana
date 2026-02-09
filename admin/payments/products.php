<?php
// admin/payments/products.php
session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: ../login.php');
	exit;
}
require_once __DIR__ . '/../../config/db.php';

// Handle add, edit, delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $id = intval($_POST['id'] ?? 0);
    if ($action === 'add' && $title !== '') {
        $stmt = $pdo->prepare("INSERT INTO letterpad_titles (title, source) VALUES (?, 'product')");
        $stmt->execute([$title]);
        exit('success');
    }
    if ($action === 'edit' && $id > 0 && $title !== '') {
        $stmt = $pdo->prepare("UPDATE letterpad_titles SET title = ? WHERE id = ? AND source = 'product'");
        $stmt->execute([$title, $id]);
        exit('success');
    }
    if ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare("DELETE FROM letterpad_titles WHERE id = ? AND source = 'product'");
        $stmt->execute([$id]);
        exit('success');
    }
    exit('error');
}

// Fetch all products
$stmt = $pdo->prepare("SELECT id, title FROM letterpad_titles WHERE source = 'product' ORDER BY id DESC");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Products</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; padding: 24px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 12px #e0bebe22; padding: 24px; }
        h2 { color: #800000; margin-bottom: 18px; }
        .add-row { display: flex; gap: 10px; margin-bottom: 18px; }
        .add-row input { flex: 1; padding: 8px 12px; border-radius: 6px; border: 1px solid #ccc; font-size: 1em; }
        .add-row button { padding: 8px 18px; border-radius: 6px; border: none; background: #28a745; color: #fff; font-weight: 600; cursor: pointer; }
        .add-row button:hover { background: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px 6px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f9eaea; color: #800000; }
        .action-btn { padding: 4px 10px; border-radius: 5px; border: none; font-size: 0.95em; cursor: pointer; margin-right: 6px; }
        .edit-btn { background: #ffc107; color: #333; }
        .delete-btn { background: #c00; color: #fff; }
    </style>
</head>
<body>
<div class="container">
    <h2>Products</h2>
    <div class="add-row">
        <input type="text" id="productTitle" placeholder="Add product...">
        <button onclick="addProduct()">Save</button>
    </div>
    <table>
        <thead>
            <tr><th>Product</th><th>Actions</th></tr>
        </thead>
        <tbody id="productsTable">
            <?php foreach ($products as $p): ?>
            <tr data-id="<?= $p['id'] ?>">
                <td class="title-cell"><?= htmlspecialchars($p['title']) ?></td>
                <td>
                    <button class="action-btn edit-btn" onclick="editProduct(<?= $p['id'] ?>, this)">Edit</button>
                    <button class="action-btn delete-btn" onclick="deleteProduct(<?= $p['id'] ?>, this)">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
function addProduct() {
    var title = document.getElementById('productTitle').value.trim();
    if (!title) return alert('Enter product name');
    var btn = event.target;
    btn.disabled = true;
    fetch('', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'action=add&title=' + encodeURIComponent(title) })
        .then(r => r.text())
        .then(res => {
            btn.disabled = false;
            if (res === 'success') location.reload();
            else alert('Failed to add');
        });
}
function editProduct(id, btn) {
    var row = btn.closest('tr');
    var cell = row.querySelector('.title-cell');
    var old = cell.textContent;
    var input = document.createElement('input');
    input.type = 'text'; input.value = old; input.style.width = '80%';
    cell.innerHTML = '';
    cell.appendChild(input);
    input.focus();
    btn.textContent = 'Save';
    btn.onclick = function() {
        var val = input.value.trim();
        if (!val) return alert('Enter product name');
        btn.disabled = true;
        fetch('', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'action=edit&id=' + id + '&title=' + encodeURIComponent(val) })
            .then(r => r.text())
            .then(res => {
                btn.disabled = false;
                if (res === 'success') location.reload();
                else alert('Failed to update');
            });
    };
}
function deleteProduct(id, btn) {
    if (!confirm('Delete this product?')) return;
    btn.disabled = true;
    fetch('', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'action=delete&id=' + id })
        .then(r => r.text())
        .then(res => {
            btn.disabled = false;
            if (res === 'success') location.reload();
            else alert('Failed to delete');
        });
}
</script>
</body>
</html>
