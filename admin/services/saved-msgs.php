<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
$pageTitle = 'Saved Messages | Vishnusudarshana';
// DB connection
require_once __DIR__ . '/../../config/db.php';
$msgSaved = false;
$msgUpdated = false;
$msgDeleted = false;
$editMsg = '';
$editId = null;
// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    if ($delete_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM letterpad_titles WHERE id=? AND source='msgs'");
            $stmt->execute([$delete_id]);
            header('Location: saved-msgs.php?deleted=1');
            exit;
        } catch (PDOException $e) {
            echo '<div style=\"color:red;font-weight:600;margin-bottom:16px;\">DB ERROR: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
// Handle edit (update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id']) && isset($_POST['msg'])) {
    $edit_id = (int)$_POST['edit_id'];
    $msg = trim($_POST['msg']);
    if ($edit_id && $msg) {
        try {
            $stmt = $pdo->prepare("UPDATE letterpad_titles SET title=? WHERE id=? AND source='msgs'");
            $stmt->execute([$msg, $edit_id]);
            header('Location: saved-msgs.php?updated=1');
            exit;
        } catch (PDOException $e) {
            echo '<div style=\"color:red;font-weight:600;margin-bottom:16px;\">DB ERROR: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
// Handle add new
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['msg']) && empty($_POST['edit_id']) && !isset($_POST['delete_id'])) {
    $msg = trim($_POST['msg']);
    if ($msg) {
        try {
            $stmt = $pdo->prepare("INSERT INTO letterpad_titles (title, source, created_at) VALUES (?, 'msgs', NOW())");
            $stmt->execute([$msg]);
            header('Location: saved-msgs.php?saved=1');
            exit;
        } catch (PDOException $e) {
            echo '<div style=\"color:red;font-weight:600;margin-bottom:16px;\">DB ERROR: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
// If edit button clicked, load message for editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_edit_id'])) {
    $editId = (int)$_POST['start_edit_id'];
    if ($editId) {
        $stmt = $pdo->prepare("SELECT title FROM letterpad_titles WHERE id=? AND source='msgs'");
        $stmt->execute([$editId]);
        $editMsg = $stmt->fetchColumn();
    }
}
$msgs = [];
try {
    $msgs = $pdo->query("SELECT * FROM letterpad_titles WHERE source='msgs' ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    echo '<div style="color:red;font-weight:600;margin-bottom:16px;">DB ERROR: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script>
    // Force page to open in the same window (no _blank, no new tab)
    if (window.top !== window.self) {
        window.top.location = window.self.location;
    }
    document.addEventListener('DOMContentLoaded', function() {
        // Remove target="_blank" from all links if any
        var links = document.querySelectorAll('a[target="_blank"]');
        links.forEach(function(link) { link.removeAttribute('target'); });
    });
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 12px #e0bebe22; padding: 32px 24px; }
        h1 { color: #800000; margin-bottom: 18px; }
        .btn-main { padding: 8px 22px; background: #800000; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-main:hover { background: #600000; }
        #richMsg { width:100%; min-height:160px; max-height:400px; padding:14px; border-radius:8px; border:1.5px solid #ccc; font-size:1.15em; background:#fff; outline:none; overflow:auto; margin-bottom:14px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="container">
    <a href="booking-slots.php" style="display:inline-block;margin-bottom:18px;padding:8px 22px;background:#007bff;color:#fff;border-radius:8px;font-weight:600;text-decoration:none;">&larr; Back to Booking Slots</a>
    <h1>Saved Messages</h1>
    <?php if (isset($_GET['saved'])): ?>
        <div style="color:green;font-weight:600;margin-bottom:16px;">Message saved.</div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
        <div style="color:green;font-weight:600;margin-bottom:16px;">Message updated.</div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div style="color:green;font-weight:600;margin-bottom:16px;">Message deleted.</div>
    <?php endif; ?>
    <form method="post" onsubmit="return saveRichMsg();">
        <div style="margin-bottom:8px;">
            <button type="button" class="btn-main" style="padding:4px 10px;font-size:1em;" onclick="format('bold')"><b>B</b></button>
            <button type="button" class="btn-main" style="padding:4px 10px;font-size:1em;" onclick="format('italic')"><i>I</i></button>
            <button type="button" class="btn-main" style="padding:4px 10px;font-size:1em;" onclick="format('underline')"><u>U</u></button>
            <button type="button" class="btn-main" style="padding:4px 10px;font-size:1em;" onclick="format('insertUnorderedList')">• List</button>
            <button type="button" class="btn-main" style="padding:4px 10px;font-size:1em;" onclick="format('insertOrderedList')">1. List</button>
            <button type="button" class="btn-main" style="padding:4px 10px;font-size:1em;" onclick="insertLink()">🔗 Link</button>
            <button type="button" class="btn-main" style="padding:4px 10px;font-size:1em;background:#b30000;" onclick="clearEditor()">Clear</button>
        </div>
        <div id="richMsg" contenteditable="true"><?php if ($editMsg) echo $editMsg; ?></div>
        <input type="hidden" name="msg" id="msgInput">
        <input type="hidden" name="edit_id" id="editIdInput" value="<?php if ($editId) echo htmlspecialchars($editId); ?>">
        <button type="submit" class="btn-main"><?php echo $editMsg ? 'Update Message' : 'Save Message'; ?></button>
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

<div class="container" style="margin-top:24px;">
    <h2 style="color:#800000;">Saved Messages</h2>
    <?php if (empty($msgs)): ?>
        <div style="color:#777;">No saved messages found.</div>
    <?php else: ?>
        <ul style="padding-left:18px;">
        <?php foreach ($msgs as $row): ?>
            <li style="margin-bottom:12px;">
                <div style="background:#f7f7fa;padding:10px 14px;border-radius:8px;border:1px solid #eee;display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <div style="flex:1;min-width:0;word-break:break-word;"> <?= $row['title'] ?>
                        <div style="font-size:0.9em;color:#888;margin-top:4px;">Saved: <?= htmlspecialchars($row['created_at']) ?></div>
                    </div>
                    <form method="post" style="display:inline;margin:0;padding:0;">
                        <input type="hidden" name="start_edit_id" value="<?= $row['id'] ?>">
                        <button type="submit" class="btn-main" style="background:#007bff;padding:4px 12px;">Edit</button>
                    </form>
                    <form method="post" style="display:inline;margin:0;padding:0;" onsubmit="return confirm('Delete this message?');">
                        <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                        <button type="submit" class="btn-main" style="background:#b30000;padding:4px 12px;">Delete</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
</body>
</html>
