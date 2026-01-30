<?php
/**
 * admin/services/booking-slots.php
 *
 * Booking Slots Management
 * Data source: service_requests table (for reference)
 */

require_once __DIR__ . '/../../config/db.php';
include __DIR__ . '/../includes/top-menu.php';

$pageTitle = 'Booking Slots | Vishnusudarshana';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
.admin-container { max-width: 1400px; margin: 0 auto; padding: 24px 12px; }
h1 { color: #800000; margin-bottom: 18px; }
.summary-cards { display: flex; gap: 18px; margin-bottom: 24px; flex-wrap: wrap; }
.summary-card { flex: 1 1 180px; background: #fffbe7; border-radius: 14px; padding: 16px; text-align: center; box-shadow: 0 2px 8px #e0bebe22; }
.summary-count { font-size: 2.2em; font-weight: 700; color: #800000; }
.summary-label { font-size: 1em; color: #444; }
.service-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 12px #e0bebe22; border-radius: 12px; table-layout: auto; font-size: 0.85em; }
.service-table th, .service-table td { padding: 8px 6px; border-bottom: 1px solid #f3caca; text-align: left; white-space: nowrap; }
.service-table th { background: #f9eaea; color: #800000; font-size: 0.9em; font-weight: 600; }
.service-table td { font-size: 0.95em; }
.no-data { text-align: center; color: #777; padding: 24px; }
</style>
</head>
<body>
<div class="admin-container">
    <h1>Booking Slots</h1>
    <a href="saved-msgs.php" target="_blank" style="background:#007bff;color:#fff;padding:8px 22px;border:none;border-radius:8px;font-weight:600;margin-bottom:18px;float:right;display:inline-block;text-decoration:none;">Saved Msgs</a>
    <form method="post" style="background:#fffbe7;padding:18px 20px;border-radius:12px;max-width:500px;margin-bottom:28px;box-shadow:0 2px 8px #e0bebe22;">
        <h2 style="color:#800000;font-size:1.2em;margin-bottom:12px;">Block Appointment Date</h2>
        <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:center;">
            <div style="flex:1;min-width:140px;">
                <label>Block Date</label><br>
                <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required style="padding:6px 10px;width:100%;">
            </div>
            <div style="flex:2;min-width:180px;">
                <label>Message (optional)</label><br>
                <input type="text" name="msg" maxlength="255" placeholder="Reason or message for this block" style="padding:6px 10px;width:100%;">
            </div>
            <div style="align-self:flex-end;">
                <button type="submit" style="background:#800000;color:#fff;padding:8px 22px;border:none;border-radius:8px;font-weight:600;">Block Date</button>
            </div>
        </div>
    </form>

    <?php
    // Handle form submission
    // Handle block date form
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_date']) && !isset($_POST['delete_id']) && !isset($_POST['edit_id'])) {
        $start_date = $_POST['start_date'];
        $msg = isset($_POST['msg']) ? trim($_POST['msg']) : null;
        if ($start_date) {
            // Check for duplicate
            $check = $pdo->prepare("SELECT COUNT(*) FROM blocked_appointment_slots WHERE start_date = ?");
            $check->execute([$start_date]);
            if ($check->fetchColumn() > 0) {
                echo '<div style=\"color:#b30000;font-weight:600;margin-bottom:16px;\">This date is already blocked.</div>';
            } else {
                $stmt = $pdo->prepare("INSERT INTO blocked_appointment_slots (start_date, end_date, start_time, end_time, msg) VALUES (?, ?, '00:00', '23:59', ?)");
                $stmt->execute([$start_date, $start_date, $msg]);
                echo '<div style=\"color:green;font-weight:600;margin-bottom:16px;\">Date blocked successfully.</div>';
            }
        }
    }

    // Handle edit
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
        $edit_id = (int)$_POST['edit_id'];
        $edit_date = $_POST['edit_start_date'] ?? '';
        $edit_msg = isset($_POST['edit_msg']) ? trim($_POST['edit_msg']) : null;
        if ($edit_id && $edit_date) {
            // Check for duplicate date (ignore current row)
            $check = $pdo->prepare("SELECT COUNT(*) FROM blocked_appointment_slots WHERE start_date = ? AND id != ?");
            $check->execute([$edit_date, $edit_id]);
            if ($check->fetchColumn() > 0) {
                echo '<div style=\"color:#b30000;font-weight:600;margin-bottom:16px;\">This date is already blocked.</div>';
            } else {
                $stmt = $pdo->prepare("UPDATE blocked_appointment_slots SET start_date = ?, end_date = ?, msg = ? WHERE id = ?");
                $stmt->execute([$edit_date, $edit_date, $edit_msg, $edit_id]);
                echo '<div style=\"color:green;font-weight:600;margin-bottom:16px;\">Blocked date updated.</div>';
            }
        }
    }

    // Handle delete
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
        $delete_id = (int)$_POST['delete_id'];
        if ($delete_id) {
            $stmt = $pdo->prepare("DELETE FROM blocked_appointment_slots WHERE id = ?");
            $stmt->execute([$delete_id]);
            echo '<div style="color:#b30000;font-weight:600;margin-bottom:16px;">Blocked date deleted.</div>';
        }
    }

    // Fetch all blocked slots
    $blocked = $pdo->query("SELECT * FROM blocked_appointment_slots ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <table class="service-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Blocked Date</th>
                <th>Message</th>
                <th>Created At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $editRowId = isset($_POST['edit_row_start']) ? (int)$_POST['edit_row_start'] : 0;
            if (empty($blocked)): ?>
                <tr><td colspan="5" class="no-data">No blocked dates found.</td></tr>
            <?php else: foreach ($blocked as $row): ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <?php if (isset($_POST['edit_row_start']) && $editRowId === (int)$row['id']): ?>
                        <form method="post">
                        <td><input type="date" name="edit_start_date" value="<?= htmlspecialchars($row['start_date']) ?>" required style="padding:4px 8px;width:120px;"></td>
                        <td><input type="text" name="edit_msg" value="<?= htmlspecialchars($row['msg'] ?? '') ?>" maxlength="255" style="padding:4px 8px;width:220px;"></td>
                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                        <td>
                            <input type="hidden" name="edit_id" value="<?= (int)$row['id'] ?>">
                            <button type="submit" style="background:#007bff;color:#fff;padding:4px 14px;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Save</button>
                            <a href="booking-slots.php" style="margin-left:8px;color:#800000;font-weight:600;">Cancel</a>
                        </td>
                        </form>
                    <?php else: ?>
                        <td><?= htmlspecialchars($row['start_date']) ?></td>
                        <td><?= htmlspecialchars($row['msg'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="edit_row_start" value="<?= (int)$row['id'] ?>">
                                <button type="submit" style="background:#007bff;color:#fff;padding:4px 14px;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Edit</button>
                            </form>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this blocked date?');">
                                <input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" style="background:#b30000;color:#fff;padding:4px 14px;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Delete</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
