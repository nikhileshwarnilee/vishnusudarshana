<?php
// Handle delete action before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_visitor']) && isset($_POST['visitor_id'])) {
    require_once __DIR__ . '/../../config/db.php';
    $delId = (int)$_POST['visitor_id'];
    $pdo->prepare('DELETE FROM visitor_tickets WHERE id=?')->execute([$delId]);
    header('Location: visitors-log.php?deleted=1');
    exit;
}

require_once __DIR__ . '/../includes/top-menu.php';
require_once __DIR__ . '/../../config/db.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo '<div class="admin-container"><h2>Invalid Visitor ID</h2></div>';
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM visitor_tickets WHERE id = ?");
$stmt->execute([$id]);
$visitor = $stmt->fetch();
if (!$visitor) {
    echo '<div class="admin-container"><h2>Visitor Not Found</h2></div>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Visitor #<?= htmlspecialchars($visitor['id']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
        .admin-container { max-width: 700px; margin: 0 auto; padding: 28px 12px; }
        h1 { font-size: 1.3em; margin-bottom: 18px; color: #800000; }
        .details-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 12px #e0bebe22; border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
        .details-table th, .details-table td { padding: 12px 10px; border-bottom: 1px solid #f3caca; text-align: left; font-size: 1em; }
        .details-table th { background: #f9eaea; color: #800000; font-weight: 700; width: 180px; }
        .details-table tr:last-child td { border-bottom: none; }
        .status-badge { padding: 2px 12px; border-radius: 8px; font-weight: 600; font-size: 0.98em; background: #f7e7e7; color: #800000; display: inline-block; }
        .status-badge.status-open { background: #e5f0ff; color: #0056b3; }
        .status-badge.status-closed { background: #e5ffe5; color: #1a8917; }
        /* .status-badge.status-cancelled { background: #ffe5e5; color: #a00; } */
        .form-bar { margin-bottom: 18px; }
        .form-bar label { font-weight: 600; margin-right: 8px; }
        .form-bar select { padding: 6px 12px; border-radius: 6px; border: 1px solid #ccc; font-size: 1em; }
        .form-bar button { background: #800000; color: #fff; border: none; border-radius: 8px; padding: 8px 18px; font-size: 0.98em; font-weight: 600; text-align: center; text-decoration: none; box-shadow: 0 2px 8px #80000022; transition: background 0.15s; display: inline-block; cursor: pointer; margin-left: 10px; }
        .form-bar button:active { background: #5a0000; }
        .notes-section { background: #fffbe7; border-radius: 12px; box-shadow: 0 2px 8px #e0bebe22; padding: 18px; margin-bottom: 24px; }
        .note-item { background:#fff;border-left:3px solid #800000;padding:10px;margin-bottom:10px;border-radius:4px; }
        .note-meta { color: #999; font-size: 0.85em; font-style: italic; }
        .add-note-form textarea { width: 100%; min-height: 60px; border-radius: 6px; border: 1px solid #ccc; padding: 8px; font-size: 1em; }
        .add-note-form button { margin-top: 8px; background: #800000; color: #fff; border: none; border-radius: 6px; padding: 8px 18px; font-weight: 600; cursor: pointer; }
        .add-note-form button:hover { background: #600000; }
        @media (max-width: 700px) {
            .admin-container { padding: 12px 2px; }
            .details-table th, .details-table td { padding: 8px 4px; font-size: 0.97em; }
        }
    </style>
</head>
<body>
<div class="admin-container">
    <h1>Visitor Details</h1>
    <table class="details-table">
        <tr><th>ID</th><td><?= htmlspecialchars($visitor['id']) ?></td></tr>
        <tr><th>Name</th><td><?= htmlspecialchars($visitor['visitor_name']) ?></td></tr>
        <tr><th>Contact</th><td><?= htmlspecialchars($visitor['contact_number']) ?></td></tr>
        <tr><th>Address</th><td><?= htmlspecialchars($visitor['address']) ?></td></tr>
        <tr><th>Purpose</th><td><?= htmlspecialchars($visitor['purpose']) ?></td></tr>
        <tr><th>Visit Type</th><td><?= $visitor['visit_type'] === 'call' ? 'Call' : 'In Office' ?></td></tr>
        <tr><th>Priority</th><td><?= $visitor['priority'] === 'urgent' ? '<span style="color:#a00;font-weight:600;">Urgent</span>' : 'Normal' ?></td></tr>
        <tr><th>In Time</th><td><?= htmlspecialchars($visitor['in_time']) ?></td></tr>
        <tr><th>Out Time</th><td><?= htmlspecialchars($visitor['out_time']) ?></td></tr>
        <tr><th>Status</th><td id="status-cell">
            <?php
            $status = htmlspecialchars($visitor['status']);
            $badgeClass = 'status-badge status-' . $status;
            echo '<span class="' . $badgeClass . '">' . ucfirst($status) . '</span>';
            ?>
        </td></tr>
    </table>
    <form class="form-bar" id="status-form" onsubmit="return false;" style="margin-bottom:24px;">
        <label for="visitor_status">Update Status:</label>
        <select name="visitor_status" id="visitor_status">
            <option value="open" <?= $visitor['status']==='open'?'selected':''; ?>>Open</option>
            <option value="closed" <?= $visitor['status']==='closed'?'selected':''; ?>>Closed</option>
        </select>
        <button type="submit">Update</button>
        <a href="visitors-log.php" style="color:#800000;text-decoration:underline;font-size:0.98em;margin-left:18px;">&larr; Back to Log</a>
        <button type="button" id="deleteBtn" style="background:#dc3545;margin-left:18px;">Delete Visitor</button>
    </form>
    <h2 style="font-size:1.1em;color:#800000;margin:18px 0 8px 0;">Internal Admin Notes</h2>
    <div id="notes_container"></div>
    <div style="background:#fef9f9;border:1px solid #f3caca;border-radius:8px;padding:12px;margin-bottom:18px;">
        <textarea id="note_text" placeholder="Add internal note (admin only)" style="width:100%;padding:10px;border:1px solid #f3caca;border-radius:6px;font-size:0.98em;font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;min-height:80px;box-sizing:border-box;"></textarea>
        <button onclick="saveNote()" style="background:#800000;color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:0.98em;font-weight:600;cursor:pointer;margin-top:8px;">Save Note</button>
        <span id="note_status" style="margin-left:10px;font-size:0.95em;"></span>
    </div>
</div>
</script>
</script>
<script>
function refreshNotes() {
    fetch('ajax_get_notes.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ref_id=<?= (int)$visitor['id'] ?>'
    })
    .then(r => r.json())
    .then(data => {
        var notesContainer = document.getElementById('notes_container');
        if (data.success && data.notes.length > 0) {
            var html = '<div style="background:#fef9f9;border:1px solid #f3caca;border-radius:8px;padding:12px;margin-bottom:18px;">';
            data.notes.forEach(note => {
                let date = new Date(note.created_at);
                let formattedDate = date.toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'}) + ', ' + date.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit', hour12: true});
                html += '<div style="background:#fff;border-left:3px solid #800000;padding:10px;margin-bottom:10px;border-radius:4px;">';
                html += '<div style="color:#333;font-size:0.98em;margin-bottom:6px;">' + note.note + '</div>';
                html += '<div style="color:#999;font-size:0.85em;font-style:italic;">' + formattedDate + '</div>';
                html += '</div>';
            });
            html += '</div>';
            notesContainer.innerHTML = html;
        } else {
            notesContainer.innerHTML = '<div style="color:#888;font-size:0.98em;margin-bottom:18px;background:#fef9f9;border:1px solid #f3caca;border-radius:8px;padding:12px;">No internal notes yet.</div>';
        }
    });
}
function saveNote() {
    var noteText = document.getElementById('note_text').value.trim();
    var statusEl = document.getElementById('note_status');
    if (noteText === '') {
        statusEl.style.color = '#c00';
        statusEl.textContent = 'Note cannot be empty';
        return;
    }
    statusEl.style.color = '#888';
    statusEl.textContent = 'Saving...';
    var fd = new FormData();
    fd.append('ref_id', '<?= (int)$visitor['id'] ?>');
    fd.append('note', noteText);
    fetch('ajax_add_note.php', {
        method: 'POST',
        body: fd
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusEl.style.color = '#1a8917';
            statusEl.textContent = 'Note saved successfully!';
            document.getElementById('note_text').value = '';
            refreshNotes();
            setTimeout(() => { statusEl.textContent = ''; }, 3000);
        } else {
            statusEl.style.color = '#c00';
            statusEl.textContent = 'Failed to save note';
        }
    })
    .catch(error => {
        statusEl.style.color = '#c00';
        statusEl.textContent = 'Error saving note';
        console.error('Error:', error);
    });
}
refreshNotes();

// Status update dropdown
document.getElementById('status-form').onsubmit = function(e) {
    e.preventDefault();
    var sel = document.getElementById('visitor_status');
    var newStatus = sel.value;
    fetch('ajax_visitors_log.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=update_status&id=<?= (int)$visitor['id'] ?>&status=' + encodeURIComponent(newStatus)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            var badgeClass = 'status-badge status-' + newStatus;
            document.getElementById('status-cell').innerHTML = '<span class="' + badgeClass + '">' + newStatus.charAt(0).toUpperCase() + newStatus.slice(1) + '</span>';
        } else {
            alert('Error updating status');
        }
    });
};

document.getElementById('deleteBtn').onclick = function() {
    if (confirm('Are you sure you want to delete this visitor? This action cannot be undone.')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        var input1 = document.createElement('input');
        input1.type = 'hidden';
        input1.name = 'delete_visitor';
        input1.value = '1';
        form.appendChild(input1);
        var input2 = document.createElement('input');
        input2.type = 'hidden';
        input2.name = 'visitor_id';
        input2.value = '<?= (int)$visitor['id'] ?>';
        form.appendChild(input2);
        document.body.appendChild(form);
        form.submit();
    }
};
</script>
</script>
</script>