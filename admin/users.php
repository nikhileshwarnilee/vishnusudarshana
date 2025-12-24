

<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/../config/db.php';
$action = $_GET['action'] ?? '';
$message = '';

// AJAX: Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    if ($_POST['ajax'] === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($name && $email && $password) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() == 0) {
                // Store password as plain text (insecure)
                $stmt = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
                $stmt->execute([$name, $email, $password]);
                echo json_encode(['success' => true, 'msg' => 'User added successfully!']);
            } else {
                echo json_encode(['success' => false, 'msg' => 'Email already exists!']);
            }
        } else {
            echo json_encode(['success' => false, 'msg' => 'All fields are required!']);
        }
        exit;
    }
    if ($_POST['ajax'] === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $id]);
        if ($stmt->fetchColumn() == 0) {
            if ($password) {
                // Store password as plain text (insecure)
                $stmt = $pdo->prepare('UPDATE users SET name=?, email=?, password=? WHERE id=?');
                $stmt->execute([$name, $email, $password, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name=?, email=? WHERE id=?');
                $stmt->execute([$name, $email, $id]);
            }
            echo json_encode(['success' => true, 'msg' => 'User updated!']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Email already exists!']);
        }
        exit;
    }
    if ($_POST['ajax'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        echo json_encode(['success' => true, 'msg' => 'User deleted.']);
        exit;
    }
}

// List users
$users = $pdo->query('SELECT * FROM users ORDER BY id DESC')->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Users</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-container { max-width: 1100px; margin: 0 auto; padding: 24px 12px; }
        .summary-cards { display: flex; gap: 18px; margin-bottom: 24px; flex-wrap: wrap; }
        .summary-card { flex: 1 1 180px; background: #fffbe7; border-radius: 14px; padding: 16px; text-align: center; box-shadow: 0 2px 8px #e0bebe22; }
        .summary-count { font-size: 2.2em; font-weight: 700; color: #800000; }
        .summary-label { font-size: 1em; color: #444; }
        .form-box { margin-bottom: 20px; padding: 18px; border: 1px solid #ccc; border-radius: 12px; background: #fff; max-width: 500px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #333; }
        .form-group input { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 1em; }
        .btn-main { padding: 8px 18px; background: #800000; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-main:hover { background: #600000; }
        .users-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 12px #e0bebe22; border-radius: 12px; overflow: hidden; }
        .users-table th, .users-table td { padding: 12px 10px; border-bottom: 1px solid #f3caca; text-align: left; }
        .users-table th { background: #f9eaea; color: #800000; }
        .users-table tbody tr:hover { background: #f3f7fa; }
        .action-btn { padding: 6px 14px; background: #007bff; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; margin-right: 6px; }
        .action-btn.edit { background: #ffc107; color: #333; }
        .action-btn.delete { background: #dc3545; color: #fff; }
        .action-btn.edit:hover { background: #e0a800; }
        .action-btn.delete:hover { background: #b52a37; }
        .no-data { text-align: center; color: #777; padding: 24px; }
        @media (max-width: 700px) { .summary-cards { flex-direction: column; } }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Admin Users</h1>
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-count"><?= count($users) ?></div>
            <div class="summary-label">Total Users</div>
        </div>
    </div>

    <div class="form-box">
        <h2 id="formTitle">Add User</h2>
        <form id="userForm" autocomplete="off">
            <input type="hidden" name="id" id="userId">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" id="userName" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="userEmail" required>
            </div>
            <div class="form-group">
                <label>Password <span id="pwdNote" style="font-weight:400;font-size:0.95em;"></span></label>
                <input type="password" name="password" id="userPassword">
            </div>
            <button type="submit" class="btn-main" id="saveBtn">Save</button>
            <button type="button" class="btn-main" id="cancelBtn" style="display:none;background:#6c757d;">Cancel</button>
        </form>
        <div id="formMsg" style="margin-top:10px;font-weight:600;"></div>
    </div>

    <table class="users-table" id="usersTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr data-id="<?= $user['id'] ?>">
                <td><?= $user['id'] ?></td>
                <td><?= htmlspecialchars($user['name']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><?= $user['status'] ? 'Active' : 'Inactive' ?></td>
                <td><?= $user['created_at'] ?></td>
                <td>
                    <button class="action-btn edit" data-id="<?= $user['id'] ?>">Edit</button>
                    <?php if ($user['id'] != 1): ?>
                        <button class="action-btn delete" data-id="<?= $user['id'] ?>">Delete</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
// AJAX add/edit
let editing = false;
let editId = null;
$(function() {
    $('#userForm').on('submit', function(e) {
        e.preventDefault();
        const id = $('#userId').val();
        const name = $('#userName').val();
        const email = $('#userEmail').val();
        const password = $('#userPassword').val();
        const ajaxType = editing ? 'edit' : 'add';
        if (editing && !password) {
            // Don't send password if not changing
            $.post('users.php', { ajax: ajaxType, id, name, email }, handleFormResponse, 'json');
        } else {
            $.post('users.php', { ajax: ajaxType, id, name, email, password }, handleFormResponse, 'json');
        }
    });
    $('#cancelBtn').on('click', function() {
        resetForm();
    });
    $('#usersTable').on('click', '.edit', function() {
        const row = $(this).closest('tr');
        editId = row.data('id');
        $('#formTitle').text('Edit User');
        $('#userId').val(editId);
        $('#userName').val(row.find('td:eq(1)').text());
        $('#userEmail').val(row.find('td:eq(2)').text());
        $('#userPassword').val('');
        $('#pwdNote').text('(Leave blank to keep current password)');
        $('#cancelBtn').show();
        editing = true;
    });
    $('#usersTable').on('click', '.delete', function() {
        if (!confirm('Delete this user?')) return;
        const id = $(this).data('id');
        $.post('users.php', { ajax: 'delete', id }, function(resp) {
            if (resp.success) {
                $('tr[data-id="'+id+'"]').remove();
                $('#formMsg').css('color','green').text(resp.msg);
                resetForm();
            } else {
                $('#formMsg').css('color','red').text(resp.msg);
            }
        }, 'json');
    });
    function handleFormResponse(resp) {
        if (resp.success) {
            location.reload();
        } else {
            $('#formMsg').css('color','red').text(resp.msg);
        }
    }
    function resetForm() {
        $('#formTitle').text('Add User');
        $('#userId').val('');
        $('#userName').val('');
        $('#userEmail').val('');
        $('#userPassword').val('');
        $('#pwdNote').text('');
        $('#cancelBtn').hide();
        editing = false;
    }
});
</script>
</body>
</html>
