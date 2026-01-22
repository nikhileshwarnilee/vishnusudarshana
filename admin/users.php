<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/send_whatsapp.php';
$action = $_GET['action'] ?? '';
$message = '';

// AJAX: Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    // Debug: log POST data to a file for troubleshooting
    file_put_contents(__DIR__ . '/debug_post.log', print_r($_POST, true));
    if ($_POST['ajax'] === 'custom_msg') {
        $userId = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (!$userId || !$name || !$mobile || !$message) {
            echo json_encode(['success' => false, 'msg' => 'All fields are required.']);
            exit;
        }
        try {
            $result = sendWhatsAppMessage(
                $mobile,
                'APPOINTMENT_MESSAGE',
                [
                    'name' => $name,
                    'message' => $message
                ]
            );
            
            error_log('WhatsApp result: ' . json_encode($result));
            
            if (isset($result['success']) && $result['success'] === true) {
                echo json_encode(['success' => true, 'msg' => 'Message sent successfully']);
            } else {
                echo json_encode(['success' => false, 'msg' => $result['message'] ?? 'Failed to send']);
            }
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['ajax'] === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $password = $_POST['password'] ?? '';
        $permissions = $_POST['perm'] ?? [];
        if ($name && $email && $mobile && $password) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare('INSERT INTO users (name, email, mobile, password) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $email, $mobile, $password]);
                $user_id = $pdo->lastInsertId();
                // Save permissions
                if (!empty($permissions)) {
                    $permStmt = $pdo->prepare('INSERT INTO user_permissions (user_id, menu, submenu, action) VALUES (?, ?, ?, ?)');
                    foreach ($permissions as $menu => $subs) {
                        foreach ($subs as $submenu => $actions) {
                            foreach ($actions as $action => $val) {
                                if ($val) $permStmt->execute([$user_id, $menu, $submenu, $action]);
                            }
                        }
                    }
                }
                echo json_encode(['success' => true, 'msg' => 'User added successfully!']);
            } else {
                echo json_encode(['success' => false, 'msg' => 'Email already exists!']);
            }
        } else {
            echo json_encode(['success' => false, 'msg' => 'All fields are required!']);
        }
        exit;
    }
    if ($_POST['ajax'] === 'get_permissions' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $perms = $pdo->query("SELECT menu, submenu, action FROM user_permissions WHERE user_id = $id")->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($perms as $p) {
            $result[$p['menu']][$p['submenu']][$p['action']] = true;
        }
        echo json_encode(['success' => true, 'permissions' => $result]);
        exit;
    }
    if ($_POST['ajax'] === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $password = $_POST['password'] ?? '';
        $permissions = $_POST['perm'] ?? [];
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $id]);
        if ($stmt->fetchColumn() == 0) {
            if (!$mobile) {
                echo json_encode(['success' => false, 'msg' => 'Mobile number is required.']);
                exit;
            }
            if ($password) {
                $stmt = $pdo->prepare('UPDATE users SET name=?, email=?, mobile=?, password=? WHERE id=?');
                $stmt->execute([$name, $email, $mobile, $password, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name=?, email=?, mobile=? WHERE id=?');
                $stmt->execute([$name, $email, $mobile, $id]);
            }
            // Update permissions
            $pdo->prepare('DELETE FROM user_permissions WHERE user_id=?')->execute([$id]);
            if (!empty($permissions)) {
                $permStmt = $pdo->prepare('INSERT INTO user_permissions (user_id, menu, submenu, action) VALUES (?, ?, ?, ?)');
                foreach ($permissions as $menu => $subs) {
                    foreach ($subs as $submenu => $actions) {
                        foreach ($actions as $action => $val) {
                            if ($val) $permStmt->execute([$id, $menu, $submenu, $action]);
                        }
                    }
                }
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
        @import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
        html,body{font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif!important;}
        .admin-container { max-width: 1400px; margin: 0 auto; padding: 24px 12px; }
        /* Summary cards now in responsive-cards.css */
        .form-box { margin-bottom: 20px; padding: 18px; border: 1px solid #ccc; border-radius: 12px; background: #fff; max-width: none; width: 100%; box-sizing: border-box; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #333; }
        .form-group input { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 1em; box-sizing: border-box; }
        .form-group input:focus { border-color: #800000; outline: none; }
        .btn-main { padding: 8px 18px; background: #800000; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-main:hover { background: #600000; }
        @media (max-width: 1200px) {
            .users-table { overflow-x: auto; display: block; }
        }
        @media (max-width: 768px) {
            .admin-container { padding: 18px 10px; }
            h1 { font-size: 1.4em; margin-bottom: 14px; }
            .form-box { padding: 14px; }
            .form-group { margin-bottom: 12px; }
            .form-group label { font-size: 0.9em; margin-bottom: 4px; }
            .form-group input { padding: 8px 10px; font-size: 0.95em; }
            .btn-main { padding: 6px 14px; font-size: 0.9em; }
            .users-table { font-size: 0.85em; }
            .users-table th, .users-table td { padding: 8px 6px; }
            .action-btn { padding: 4px 8px; font-size: 0.85em; margin-right: 2px; }
        }
        @media (max-width: 600px) {
            .admin-container { padding: 14px 8px; }
            h1 { font-size: 1.2em; margin-bottom: 12px; }
            .form-box { padding: 12px; }
            .form-group { margin-bottom: 10px; }
            .form-group label { font-size: 0.85em; margin-bottom: 3px; }
            .form-group input { padding: 7px 8px; font-size: 0.9em; }
            .btn-main { padding: 6px 12px; font-size: 0.85em; width: auto; }
            .users-table { font-size: 0.8em; }
            .users-table th, .users-table td { padding: 6px 4px; }
            .action-btn { padding: 3px 6px; font-size: 0.75em; margin-right: 1px; margin-bottom: 2px; }
        }
        @media (max-width: 400px) {
            .admin-container { padding: 10px 6px; }
            h1 { font-size: 1.1em; }
            .form-box { padding: 10px; }
            .form-group input { font-size: 0.85em; }
            .users-table { font-size: 0.75em; }
            .users-table th, .users-table td { padding: 4px 3px; }
        }
        .users-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 12px #e0bebe22; border-radius: 12px; overflow: hidden; table-layout: auto; font-size: 0.85em; }
        .users-table th, .users-table td { padding: 8px 6px; border-bottom: 1px solid #f3caca; text-align: left; }
        .users-table th { background: #f9eaea; color: #800000; font-weight: 700; font-size: 0.9em; }
        .users-table td { font-size: 0.95em; }
        .users-table tbody tr:hover { background: #f3f7fa; }
        .action-btn { padding: 6px 14px; background: #007bff; color: #fff; border-radius: 6px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; margin-right: 6px; }
        .action-btn.edit { background: #ffc107; color: #333; }
        .action-btn.delete { background: #dc3545; color: #fff; }
        .action-btn.edit:hover { background: #e0a800; }
        .action-btn.delete:hover { background: #b52a37; }
        .no-data { text-align: center; color: #777; padding: 24px; }
        .permissions-card { margin-bottom: 24px; }
        .permissions-table th, .permissions-table td { border-bottom: 1px solid #f3caca; }
        .permissions-table th { background: #f9eaea; color: #800000; font-weight: 700; }
        .permissions-table tr:last-child td { border-bottom: none; }
        .perm-check { display: inline-flex; align-items: center; cursor: pointer; }
        .perm-check input[type="checkbox"] { accent-color: #800000; width: 18px; height: 18px; margin-right: 4px; }
        /* Mobile styles now in responsive-cards.css */
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
                <label>Mobile</label>
                <input type="text" name="mobile" id="userMobile" required>
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
                <th>Mobile</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
                <th>Msg</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr data-id="<?= $user['id'] ?>">
                <td><?= $user['id'] ?></td>
                <td><?= htmlspecialchars($user['name']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><?= htmlspecialchars($user['mobile'] ?? '') ?></td>
                <td><?= $user['status'] ? 'Active' : 'Inactive' ?></td>
                <td><?= $user['created_at'] ?></td>
                <td>
                    <button class="action-btn edit" data-id="<?= $user['id'] ?>">Edit</button>
                    <a class="action-btn" style="background:#17a2b8;color:#fff;" href="assign-permissions.php?user_id=<?= $user['id'] ?>">Assign Permissions</a>
                    <?php if ($user['id'] != 1): ?>
                        <button class="action-btn delete" data-id="<?= $user['id'] ?>">Delete</button>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="action-btn" style="background:#25D366;color:#fff;" onclick="openUserMsgModal(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['name'])) ?>', '<?= htmlspecialchars($user['mobile'] ?? '') ?>')">Send Msg</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Custom Message Modal -->
<div id="userMsgModalBg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:1000; align-items:center; justify-content:center;">
    <div id="userMsgModal" style="background:#fff; border-radius:12px; box-shadow:0 2px 16px #80000033; padding:28px 24px 18px 24px; min-width:340px; max-width:95vw; width:420px; text-align:left; position:relative;">
        <div style="font-size:1.12em;color:#007bff;font-weight:700;margin-bottom:10px;">Send Custom Message</div>
        <form id="userMsgForm" autocomplete="off">
            <input type="hidden" name="ajax" value="custom_msg">
            <input type="hidden" name="id" id="msgUserId">
            <input type="hidden" name="name" id="msgUserNameInput">
            <input type="hidden" name="mobile" id="msgUserMobileInput">
            <div style="margin-bottom:10px;color:#444;"><b>User:</b> <span id="msgUserName"></span></div>
            <div style="margin-bottom:10px;color:#444;"><b>Mobile:</b> <span id="msgUserMobile"></span></div>
            <div style="margin-bottom:10px;">
                <label for="msgUserText" style="display:block; margin-bottom:6px;"><b>Message:</b></label>
                <textarea name="message" id="msgUserText" style="width:100%;height:110px;padding:8px;border-radius:6px;border:1px solid #ccc;font-family:Arial,sans-serif;resize:vertical;" placeholder="Enter your custom message..." required></textarea>
            </div>
            <div style="margin-top:14px;text-align:center;">
                <button type="submit" style="background:#25D366;color:#fff;padding:8px 22px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Send Message</button>
                &nbsp;
                <button type="button" onclick="closeUserMsgModal()" style="background:#800000;color:#fff;padding:8px 22px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
            </div>
            <div id="userMsgStatus" style="margin-top:10px; color:#c00; display:none;"></div>
        </form>
    </div>
</div>
<script>
// AJAX add/edit
let editing = false;
let editId = null;
$(function() {
    $('#userForm').on('submit', function(e) {
        e.preventDefault();
        const ajaxType = editing ? 'edit' : 'add';
        let formData = $(this).serialize();
        formData += '&ajax=' + encodeURIComponent(ajaxType);
        $.post('users.php', formData, handleFormResponse, 'json');
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
        $('#userMobile').val(row.find('td:eq(3)').text());
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

    // Custom message modal
    window.openUserMsgModal = function(id, name, mobile) {
        if (!mobile) {
            alert('No mobile number saved for this user.');
            return;
        }
        $('#msgUserId').val(id);
        $('#msgUserName').text(name);
        $('#msgUserNameInput').val(name);
        $('#msgUserMobile').text(mobile);
        $('#msgUserMobileInput').val(mobile);
        $('#msgUserText').val('');
        $('#userMsgStatus').hide().text('');
        $('#userMsgModalBg').css('display','flex');
    };
    window.closeUserMsgModal = function() {
        $('#userMsgModalBg').hide();
    };

    $('#userMsgForm').on('submit', function(e) {
        e.preventDefault();
        const $status = $('#userMsgStatus');
        const $btn = $(this).find('button[type="submit"]');
        $status.hide();
        $btn.prop('disabled', true).text('Sending...');
        $.post('users.php', $(this).serialize(), function(resp) {
            if (resp.success) {
                $status.css('color','#28a745').text('Message sent!').show();
                setTimeout(() => {
                    closeUserMsgModal();
                    $btn.prop('disabled', false).text('Send Message');
                }, 800);
            } else {
                $status.css('color','#c00').text(resp.msg || 'Failed to send').show();
                $btn.prop('disabled', false).text('Send Message');
            }
        }, 'json').fail(function() {
            $status.css('color','#c00').text('Failed to send').show();
            $btn.prop('disabled', false).text('Send Message');
        });
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
        $('#userMobile').val('');
        $('#userPassword').val('');
        $('#pwdNote').text('');
        $('#cancelBtn').hide();
        editing = false;
    }
});
</script>
</body>
</html>
