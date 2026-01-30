<?php

// DEBUG: Show POST data and DB errors
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<div style="background:#ffe0e0;color:#b30000;padding:10px 16px;margin-bottom:10px;border-radius:8px;">';
    echo '<b>DEBUG POST:</b> <pre>' . htmlspecialchars(print_r($_POST, true)) . '</pre>';
    if (isset($pdo)) {
        $err = $pdo->errorInfo();
        if ($err && $err[0] !== '00000') {
            echo '<b>PDO ERROR:</b> <pre>' . htmlspecialchars(print_r($err, true)) . '</pre>';
        }
    }
    echo '</div>';
}

// admin/services/saved-msgs.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../../config/db.php';
$pageTitle = 'Saved Messages | Vishnusudarshana';
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../../assets/css/style.css">
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
.admin-container { max-width: 900px; margin: 0 auto; padding: 24px 12px; }
h1 { color: #800000; margin-bottom: 18px; }
.saved-msgs-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 12px #e0bebe22; border-radius: 12px; font-size: 0.95em; }
.saved-msgs-table th, .saved-msgs-table td { padding: 10px 8px; border-bottom: 1px solid #f3caca; text-align: left; }
.saved-msgs-table th { background: #f9eaea; color: #800000; font-size: 1em; font-weight: 600; }
.saved-msgs-table td { font-size: 1em; }
.no-data { text-align: center; color: #777; padding: 24px; }
.btn-main { padding: 7px 18px; background: #800000; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
.btn-main:hover { background: #600000; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Saved Messages</h1>
    <div style="background:#fffbe7;padding:18px 20px;border-radius:12px;max-width:600px;margin-bottom:28px;box-shadow:0 2px 8px #e0bebe22;">
        <form method="post" onsubmit="return saveRichMsg();">
            <label style="font-weight:600;color:#800000;">Add New Message</label><br>
            <div style="margin-bottom:8px;">
                <button type="button" class="btn-main" style="padding:4px 10px;font-size:1em;" onclick="format('bold')"><b>B</b></button>
                <button type="button" class="btn-main" style="padding:4px 10px;font-size:1em;" onclick="format('italic')"><i>I</i></button>
                <button type="button" class="btn-main" style="padding:4px 10px;font-size:1em;" onclick="format('underline')"><u>U</u></button>
                <button type="button" class="btn-main" style="padding:4px 10px;font-size:1em;" onclick="format('insertUnorderedList')">• List</button>
                <button type="button" class="btn-main" style="padding:4px 10px;font-size:1em;" onclick="format('insertOrderedList')">1. List</button>
                <button type="button" class="btn-main" style="padding:4px 10px;font-size:1em;" onclick="insertLink()">🔗 Link</button>
                <button type="button" class="btn-main" style="padding:4px 10px;font-size:1em;background:#b30000;" onclick="clearEditor()">Clear</button>
            </div>
            <div id="richMsg" contenteditable="true" style="width:100%;min-height:180px;max-height:400px;padding:14px 14px;border-radius:8px;border:1.5px solid #ccc;font-size:1.15em;margin-bottom:14px;background:#fff;outline:none;overflow:auto;"></div>
            <input type="hidden" name="msg" id="msgInput">
            <button type="submit" class="btn-main">Save Message</button>
        </form>
        <script>
        function format(cmd) {
            document.execCommand(cmd, false, null);
        }
        function insertLink() {
            var url = prompt('Enter the URL:', 'https://');
            if (url) document.execCommand('createLink', false, url);
        }
        function clearEditor() {
            document.getElementById('richMsg').innerHTML = '';
        }
        function saveRichMsg() {
            var html = document.getElementById('richMsg').innerHTML.trim();
            if (!html || html === '<br>') {
                alert('Please enter a message.');
                return false;
            }
            document.getElementById('msgInput').value = html;
            return true;
        }
        </script>
    </div>
    <?php
    // Ensure 'source' column exists in letterpad_titles
    $pdo->exec("ALTER TABLE letterpad_titles ADD COLUMN IF NOT EXISTS source VARCHAR(50) DEFAULT NULL");

    // Handle add/edit/delete
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Add new message
        if (isset($_POST['msg']) && !isset($_POST['edit_id'])) {
            $msg = trim($_POST['msg']);
            if ($msg) {
                $stmt = $pdo->prepare("INSERT INTO letterpad_titles (title, source, created_at) VALUES (?, 'msgs', NOW())");
                $stmt->execute([$msg]);
                echo '<div style=\"color:green;font-weight:600;margin-bottom:16px;\">Message saved.</div>';
            }
        }
        // Edit message
        if (isset($_POST['edit_id']) && isset($_POST['msg'])) {
            $edit_id = (int)$_POST['edit_id'];
            $msg = trim($_POST['msg']);
            if ($edit_id && $msg) {
                $stmt = $pdo->prepare("UPDATE letterpad_titles SET title=? WHERE id=? AND source='msgs'");
                $stmt->execute([$msg, $edit_id]);
                echo '<div style=\"color:green;font-weight:600;margin-bottom:16px;\">Message updated.</div>';
            }
        }
        // Delete message
        if (isset($_POST['delete_id'])) {
            $delete_id = (int)$_POST['delete_id'];
            if ($delete_id) {
                $stmt = $pdo->prepare("DELETE FROM letterpad_titles WHERE id=? AND source='msgs'");
                $stmt->execute([$delete_id]);
                echo '<div style=\"color:green;font-weight:600;margin-bottom:16px;\">Message deleted.</div>';
            }
        }
    }
    // Fetch all saved messages from letterpad_titles where source='msgs'
    $msgs = $pdo->query("SELECT * FROM letterpad_titles WHERE source='msgs' ORDER BY created_at DESC")->fetchAll();
    ?>
    <table class="saved-msgs-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Message</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($msgs) === 0): ?>
            <tr><td colspan="4" class="no-data">No saved messages found.</td></tr>
        <?php else: ?>
            <?php foreach ($msgs as $i => $row): ?>
                <tr id="row-<?= $row['id'] ?>">
                    <td><?= $i+1 ?></td>
                    <td id="msg-content-<?= $row['id'] ?>"><?php echo $row['title']; ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                    <td>
                        <button class="btn-main" type="button" onclick="editMsg(<?= $row['id'] ?>)">Edit</button>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this message?');">
                            <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                            <button class="btn-main" type="submit" style="background:#b30000;">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    <script>
    function editMsg(id) {
        var content = document.getElementById('msg-content-' + id).innerHTML;
        document.getElementById('richMsg').innerHTML = content;
        document.getElementById('msgInput').value = content;
        // Add or update a hidden input for edit_id
        var form = document.querySelector('form[onsubmit]');
        var editInput = document.getElementById('editIdInput');
        if (!editInput) {
            editInput = document.createElement('input');
            editInput.type = 'hidden';
            editInput.name = 'edit_id';
            editInput.id = 'editIdInput';
            form.appendChild(editInput);
        }
        editInput.value = id;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    </script>
</div>
</body>
</html>
