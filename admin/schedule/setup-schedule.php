<?php
/**
 * admin/schedule/setup-schedule.php
 *
 * Setup Schedule Entry Page
 * Uses same form fields, DB table, and business logic as manage-schedule.php
 */

require_once __DIR__ . '/../../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Handle delete BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    $pdo->prepare('DELETE FROM admin_schedule WHERE id = ?')->execute([$deleteId]);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
require_once __DIR__ . '/../includes/top-menu.php';
$currentUserId = $_SESSION['user_id'] ?? 1;
$isAdmin = ($currentUserId == 1);
$users = [];
if ($isAdmin) {
    $users = $pdo->query('SELECT id, name FROM users ORDER BY name ASC')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE id = ?');
    $stmt->execute([$currentUserId]);
    $users = $stmt->fetchAll();
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only handle add/edit if not delete
    if (!isset($_POST['delete_id'])) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $status = $_POST['status'] ?? 'tentative';
        $assigned_user_ids = isset($_POST['assigned_user_id']) ? (array)$_POST['assigned_user_id'] : [$currentUserId];
        $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
        // Handle fix schedule
        if (isset($_POST['fix_id'])) {
            $fix_id = (int)$_POST['fix_id'];
            $row = $pdo->query("SELECT multi_assigned_user_id FROM admin_schedule WHERE id = " . $fix_id)->fetch(PDO::FETCH_ASSOC);
            $multi_ids = json_decode($row['multi_assigned_user_id'] ?? '[]', true);
            if (is_array($multi_ids) && count($multi_ids) === 1) {
                $pdo->prepare("UPDATE admin_schedule SET source_page = NULL WHERE id = ?")->execute([$fix_id]);
                // WhatsApp logic: send to the single user
                require_once __DIR__ . '/../../helpers/send_whatsapp.php';
                $userId = intval($multi_ids[0]);
                $row2 = $pdo->prepare("SELECT u.name, u.mobile, a.title, a.description, a.schedule_date, a.end_date, a.start_time, a.end_time, a.status FROM admin_schedule a JOIN users u ON a.assigned_user_id = u.id WHERE a.id = ?");
                $row2->execute([$fix_id]);
                $ud = $row2->fetch(PDO::FETCH_ASSOC);
                if ($ud && !empty($ud['mobile'])) {
                    sendWhatsAppMessage(
                        $ud['mobile'],
                        'Schedule Manager Marathi',
                        [
                            'name' => $ud['name'],
                            'date_range' => ($ud['schedule_date'] === $ud['end_date'] ? $ud['schedule_date'] : ($ud['schedule_date'] . ' to ' . $ud['end_date'])),
                            'title' => $ud['title'],
                            'time_range' => $ud['start_time'] . ' - ' . $ud['end_time'],
                            'status' => ucfirst($ud['status']),
                            'description' => $ud['description']
                        ]
                    );
                }
                $msg = '<div style="color:green;font-weight:600;margin-bottom:16px;">Schedule fixed: now marked as normal schedule. WhatsApp message sent again.</div>';
            } else {
                echo "<script>alert('Cannot fix: More than one user assigned. Please ensure only one user is selected before fixing.');window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
                exit;
            }
        }
        if ($title && $start_date && $start_time && $end_time && count($assigned_user_ids) > 0) {
            $multi_assigned_user_id = json_encode(array_map('intval', $assigned_user_ids));
            $assigned_user_id = $assigned_user_ids[0]; // For compatibility, use first as main
            if ($edit_id) {
                $stmt = $pdo->prepare("UPDATE admin_schedule SET title=?, description=?, schedule_date=?, end_date=?, start_time=?, end_time=?, status=?, assigned_user_id=?, multi_assigned_user_id=?, source_page=? WHERE id=?");
                $stmt->execute([$title, $description, $start_date, $end_date, $start_time, $end_time, $status, $assigned_user_id, $multi_assigned_user_id, 'setup', $edit_id]);
                $msg = '<div style=\"color:green;font-weight:600;margin-bottom:16px;\">Schedule entry updated successfully.</div>';
            } else {
                $stmt = $pdo->prepare("INSERT INTO admin_schedule (title, description, schedule_date, end_date, start_time, end_time, status, assigned_user_id, multi_assigned_user_id, created_by, created_at, source_page) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
                $stmt->execute([$title, $description, $start_date, $end_date, $start_time, $end_time, $status, $assigned_user_id, $multi_assigned_user_id, $currentUserId, 'setup']);
                $msg = '<div style=\"color:green;font-weight:600;margin-bottom:16px;\">Schedule entry added and WhatsApp message sent.</div>';
            }
            // WhatsApp logic: send to all selected users
            require_once __DIR__ . '/../../helpers/send_whatsapp.php';
            $userIds = array_map('intval', $assigned_user_ids);
            if (count($userIds) > 0) {
                $in = str_repeat('?,', count($userIds) - 1) . '?';
                $userStmt = $pdo->prepare("SELECT name, mobile FROM users WHERE id IN ($in)");
                $userStmt->execute($userIds);
                $usersData = $userStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($usersData as $ud) {
                    if (!empty($ud['mobile'])) {
                        sendWhatsAppMessage(
                            $ud['mobile'],
                            'Schedule Manager Marathi',
                            [
                                'name' => $ud['name'],
                                'date_range' => ($start_date === $end_date ? $start_date : ($start_date . ' to ' . $end_date)),
                                'title' => $title,
                                'time_range' => $start_time . ' - ' . $end_time,
                                'status' => ucfirst($status),
                                'description' => $description
                            ]
                        );
                    }
                }
            }
        } else {
            $msg = '<div style=\"color:#b30000;font-weight:600;margin-bottom:16px;\">All fields except description/end date are required.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
.admin-container { max-width: 1400px; margin: 0 auto; padding: 24px 12px; }
h1 { color: #800000; margin-bottom: 18px; }
.form-section { background: #fffbe7; border-radius: 14px; padding: 18px 20px; box-shadow: 0 2px 8px #e0bebe22; margin-bottom: 24px; }
.form-horizontal { display: flex; flex-wrap: wrap; gap: 18px 24px; align-items: flex-end; }
.form-group { display: flex; flex-direction: column; min-width: 180px; flex: 1 1 220px; margin-bottom: 0; }
label { font-weight: 600; color: #800000; margin-bottom: 4px; }
input, select, textarea { width: 100%; padding: 8px 10px; border: 1px solid #e0bebe; border-radius: 8px; font-size: 1em; background: #f9eaea; }
button { background: #800000; color: #fff; border: none; border-radius: 8px; padding: 10px 24px; font-weight: 600; font-size: 1.05em; cursor: pointer; margin-left: 12px; }
button:active { background: #5a0000; }
@media (max-width: 900px) {
    .form-horizontal { flex-direction: column; gap: 0; }
    .form-group { min-width: 100%; margin-bottom: 12px; }
    button { margin-left: 0; margin-top: 12px; }
}
</style>
</head>
<body>
<div class="admin-container">
    <h1>Setup Schedule</h1>
    <?= $msg ?>
    <form method="post" class="form-section form-horizontal">
        <input type="hidden" id="edit_id" name="edit_id" value="">
        <div class="form-group">
            <label for="title">Title <span style="color:#ef4444">*</span></label>
            <input type="text" id="title" name="title" required placeholder="Enter event title">
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" placeholder="Add details (optional)"></textarea>
        </div>
        <div class="form-group">
            <label for="start_date">Start Date <span style="color:#ef4444">*</span></label>
            <input type="date" id="start_date" name="start_date" required>
        </div>
        <div class="form-group">
            <label for="end_date">End Date</label>
            <input type="date" id="end_date" name="end_date">
        </div>
        <div class="form-group">
            <label for="start_time">Start Time <span style="color:#ef4444">*</span></label>
            <input type="time" id="start_time" name="start_time" required>
        </div>
        <div class="form-group">
            <label for="end_time">End Time <span style="color:#ef4444">*</span></label>
            <input type="time" id="end_time" name="end_time" required>
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="tentative">Tentative</option>
                <option value="confirmed">Confirmed</option>
                <option value="blocked">Blocked</option>
            </select>
        </div>
        <div class="form-group">
            <label>Assigned User(s) <span style="color:#ef4444">*</span></label>
            <div id="user-checkbox-list" style="display:flex; flex-wrap:wrap; gap:10px 24px;">
                <?php foreach ($users as $user): ?>
                <label style="display:flex; align-items:center; gap:6px; font-weight:500;">
                    <input type="checkbox" name="assigned_user_id[]" value="<?= $user['id'] ?>" <?= ($user['id'] == $currentUserId ? 'checked' : '') ?> style="accent-color:#800000; width:18px; height:18px;"> <?= htmlspecialchars($user['name']) ?>
                </label>
                <?php endforeach; ?>
            </div>
            <small style="color:#800000;">Check one or more users to assign this schedule.</small>
        </div>
        <button type="submit">Add Schedule Entry</button>
    </form>

<?php
// Fetch all schedule entries for display (admin: all, user: only their own)
$query = $isAdmin
    ? "SELECT a.*, u.name as assigned_user FROM admin_schedule a JOIN users u ON a.assigned_user_id = u.id WHERE a.source_page = 'setup' ORDER BY a.schedule_date DESC, a.start_time DESC"
    : "SELECT a.*, u.name as assigned_user FROM admin_schedule a JOIN users u ON a.assigned_user_id = u.id WHERE a.assigned_user_id = ? AND a.source_page = 'setup' ORDER BY a.schedule_date DESC, a.start_time DESC";
$stmt = $pdo->prepare($query);
if ($isAdmin) {
    $stmt->execute();
} else {
    $stmt->execute([$currentUserId]);
}
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<div style="margin-top:36px; width:100%;">
    <h2 style="color:#800000;">All Schedule Entries</h2>
    <div style="overflow-x:auto;">
    <table class="service-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>User</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Status</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($entries) === 0): ?>
            <tr><td colspan="9" class="no-data">No schedule entries found.</td></tr>
        <?php else: ?>
            <?php foreach ($entries as $row): ?>
            <tr data-id="<?= $row['id'] ?>"
                data-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>"
                data-description="<?= htmlspecialchars($row['description'], ENT_QUOTES) ?>"
                data-schedule_date="<?= htmlspecialchars($row['schedule_date']) ?>"
                data-end_date="<?= htmlspecialchars($row['end_date']) ?>"
                data-start_time="<?= htmlspecialchars($row['start_time']) ?>"
                data-end_time="<?= htmlspecialchars($row['end_time']) ?>"
                data-status="<?= htmlspecialchars($row['status']) ?>"
                data-assigned_user_id="<?= htmlspecialchars($row['assigned_user_id']) ?>"
                data-multi_assigned_user_id='<?= htmlspecialchars($row['multi_assigned_user_id']) ?>'
            >
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td>
                <?php 
                $multi_ids = json_decode($row['multi_assigned_user_id'], true) ?: [];
                $user_names = array();
                foreach ($users as $u) {
                    if (in_array($u['id'], $multi_ids)) $user_names[] = htmlspecialchars($u['name']);
                }
                echo implode(', ', $user_names);
                ?>
                </td>
                <td><?= htmlspecialchars($row['schedule_date']) ?></td>
                <td><?= htmlspecialchars($row['end_date']) ?></td>
                <td><?= htmlspecialchars($row['start_time']) ?></td>
                <td><?= htmlspecialchars($row['end_time']) ?></td>
                <td><span class="status-badge status-<?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars(ucfirst($row['status'])) ?></span></td>
                <td style="max-width:320px; overflow-x:auto; white-space:pre-line;\"><?= nl2br(htmlspecialchars($row['description'])) ?></td>
                <td>
                    <button class="edit-btn" style="background:#f9eaea;color:#800000;border:1px solid #e0bebe;padding:4px 12px;border-radius:6px;font-weight:600;cursor:pointer;">Edit</button>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this entry?');">
                        <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                        <button type="submit" class="delete-btn" style="background:#ffeaea;color:#b30000;border:1px solid #e0bebe;padding:4px 12px;border-radius:6px;font-weight:600;cursor:pointer;">Delete</button>
                    </form>
                    <form method="post" style="display:inline;" class="fix-schedule-form">
                        <input type="hidden" name="fix_id" value="<?= $row['id'] ?>">
                        <button type="submit" class="fix-btn" style="background:#e6ffe6;color:#1a8917;border:1px solid #b3e6b3;padding:4px 12px;border-radius:6px;font-weight:600;cursor:pointer;">Fix Schedule</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    <?php
    // Handle delete
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
        $deleteId = (int)$_POST['delete_id'];
        $pdo->prepare('DELETE FROM admin_schedule WHERE id = ?')->execute([$deleteId]);
        // Simple redirect to avoid resubmission
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    ?>
    </div>
    <script>
    // Edit button logic: fill form with row data
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.edit-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var tr = btn.closest('tr');
                document.getElementById('edit_id').value = tr.dataset.id;
                document.getElementById('title').value = tr.dataset.title;
                document.getElementById('description').value = tr.dataset.description;
                document.getElementById('start_date').value = tr.dataset.schedule_date;
                document.getElementById('end_date').value = tr.dataset.end_date;
                document.getElementById('start_time').value = tr.dataset.start_time;
                document.getElementById('end_time').value = tr.dataset.end_time;
                document.getElementById('status').value = tr.dataset.status;
                // Set user checkboxes
                var multiIds = [];
                try { multiIds = JSON.parse(tr.dataset.multi_assigned_user_id || '[]'); } catch(e) {}
                document.querySelectorAll('input[name="assigned_user_id[]"]:checked').forEach(function(cb){ cb.checked = false; });
                multiIds.forEach(function(id) {
                    var cb = document.querySelector('input[name="assigned_user_id[]"][value="'+id+'"]').checked = true;
                });
                // Optionally, scroll to form
                window.scrollTo({ top: document.querySelector('.form-section').offsetTop - 20, behavior: 'smooth' });
            });
        });
        // Add confirmation for Fix Schedule
        document.querySelectorAll('.fix-schedule-form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to fix this schedule? This will finalize the assignment for the single user.')) {
                    e.preventDefault();
                }
            });
        });
    });
    </script>
</div>

</body>
</html>
