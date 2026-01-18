<?php
/**
 * admin/services/accepted-appointments.php
 *
 * Simplified Accepted Appointments: Today + Future only
 * Data source: service_requests table
 */

require_once __DIR__ . '/../../config/db.php';

// Handle "Mark Completed" action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'complete') {
        $appointmentIds = $_POST['appointment_ids'] ?? [];
        if (!empty($appointmentIds)) {
            $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
            $sql = "
                UPDATE service_requests
                SET service_status = 'Completed',
                    updated_at = NOW()
                WHERE id IN ($placeholders)
                  AND category_slug = 'appointment'
                  AND payment_status = 'Paid'
                  AND service_status = 'Accepted'
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($appointmentIds);

            // WhatsApp: Appointment Completed (for each appointment)
            require_once __DIR__ . '/../../helpers/send_whatsapp.php';
            foreach ($appointmentIds as $id) {
                $st = $pdo->prepare("SELECT customer_name, mobile, tracking_id FROM service_requests WHERE id = ?");
                $st->execute([$id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    try {
                        sendWhatsAppMessage(
                            $row['mobile'],
                            'appointment_completed',
                            'en',
                            [
                                'name' => $row['customer_name'],
                                'tracking_id' => $row['tracking_id']
                            ]
                        );
                    } catch (Throwable $e) {
                        error_log('WhatsApp completed failed: ' . $e->getMessage());
                    }
                }
            }

            header('Location: accepted-appointments.php?success=completed');
            exit;
        }
    }
}

// Build date dropdown: only today/future assigned dates with Accepted status
$whereAcceptedFuture = "
    category_slug = 'appointment' AND payment_status = 'Paid' AND service_status = 'Accepted' AND
    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(form_data,'$.assigned_date')), '') <> '' AND
    JSON_UNQUOTE(JSON_EXTRACT(form_data,'$.assigned_date')) >= CURDATE()
";

$acceptedDates = [];
$stmt = $pdo->prepare("SELECT JSON_UNQUOTE(JSON_EXTRACT(form_data,'$.assigned_date')) AS ad, COUNT(*) AS c FROM service_requests WHERE $whereAcceptedFuture GROUP BY ad ORDER BY ad ASC");
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $acceptedDates[$row['ad']] = (int)$row['c'];
}

// Default selected date: earliest available (today or future)
$selectedDate = null;
if (!empty($acceptedDates)) {
    if (isset($_GET['date']) && isset($acceptedDates[$_GET['date']])) {
        $selectedDate = $_GET['date'];
    } else {
        $selectedDate = array_key_first($acceptedDates);
    }
}

// Fetch appointments for selected date
$appointments = [];
if ($selectedDate !== null) {
        $sql = "
                SELECT id, tracking_id, customer_name, mobile, form_data, created_at
                FROM service_requests
                WHERE $whereAcceptedFuture
                    AND JSON_UNQUOTE(JSON_EXTRACT(form_data,'$.assigned_date')) = ?
                ORDER BY created_at ASC
        ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$selectedDate]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Accepted Appointments</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
.admin-container { max-width: 1100px; margin: 0 auto; padding: 24px 12px; }
h1 { color: #800000; margin-bottom: 18px; }
.filter-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 18px; }
#dateSelect { padding: 8px 12px; border-radius: 6px; border: 1px solid #ddd; min-width: 260px; }
.action-bar { display: none; background: #e9f7ef; border: 2px solid #28a745; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; align-items: center; gap: 12px; }
.action-bar.show { display: flex; }
.action-btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; font-size: 0.95em; }
.btn-complete { background: #28a745; color: #fff; }
.service-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 12px #e0bebe22; border-radius: 12px; overflow: hidden; }
.service-table th, .service-table td { padding: 12px 10px; border-bottom: 1px solid #f3caca; text-align: left; }
.service-table th { background: #f9eaea; color: #800000; }
.no-data { text-align: center; color: #777; padding: 24px; }
.status-badge { padding: 4px 12px; border-radius: 8px; font-weight: 600; font-size: 0.9em; display: inline-block; min-width: 80px; text-align: center; }
.status-accepted { background: #e5f0ff; color: #0056b3; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Accepted Appointments</h1>

    <?php if (empty($acceptedDates)): ?>
        <div class="no-data" style="font-size:1.1em;color:#800000;font-weight:600;">No accepted appointments for today or future.</div>
    <?php else: ?>
        <div class="filter-bar">
            <label for="dateSelect">Select Date</label>
            <select id="dateSelect" onchange="window.location.href='?date=' + this.value;">
                <?php foreach ($acceptedDates as $date => $count): $dobj = DateTime::createFromFormat('Y-m-d', $date); $disp = $dobj ? $dobj->format('d-M-Y') : $date; $selected = ($date === $selectedDate) ? 'selected' : ''; ?>
                    <option value="<?= htmlspecialchars($date) ?>" <?= $selected ?>><?= htmlspecialchars($disp) ?> — <?= (int)$count ?> Accepted</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="action-bar" id="actionBar">
            <span id="selectedCount">0</span> selected
            <button class="action-btn btn-complete" onclick="submitComplete()">Mark Completed</button>
        </div>

        <form id="completeForm" method="POST">
            <input type="hidden" name="action" value="complete">
            <table class="service-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Tracking ID</th>
                        <th>Customer Name</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Preferred Date</th>
                        <th>Scheduled Time</th>
                        <th>Payment Status</th>
                        <th>Service Status</th>
                        <th>Created Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($appointments)): ?>
                    <tr><td colspan="10" class="no-data">No appointment bookings found.</td></tr>
                <?php else: ?>
                    <?php foreach ($appointments as $a):
                        $fd = json_decode($a['form_data'], true) ?? [];
                        $preferredDate = $fd['preferred_date'] ?? '';
                        $preferredDisplay = $preferredDate ? (DateTime::createFromFormat('Y-m-d', $preferredDate)?->format('d-M-Y') ?: $preferredDate) : '—';
                        $createdDisplay = '';
                        if (!empty($a['created_at'])) {
                            $co = new DateTime($a['created_at']);
                            $createdDisplay = $co->format('d-M-Y');
                        }
                        $fromTime = $fd['assigned_from_time'] ?? ($fd['time_from'] ?? '');
                        $toTime = $fd['assigned_to_time'] ?? ($fd['time_to'] ?? '');
                    ?>
                        <tr>
                            <td><input type="checkbox" class="rowCheckbox" value="<?= (int)$a['id'] ?>"></td>
                            <td><?= htmlspecialchars($a['tracking_id']) ?></td>
                            <td><?= htmlspecialchars($a['customer_name']) ?></td>
                            <td><?= htmlspecialchars($a['mobile']) ?></td>
                            <td><?= htmlspecialchars($fd['email'] ?? '') ?></td>
                            <td style="font-weight:600;color:#800000;">
                                <?= htmlspecialchars($preferredDisplay) ?>
                            </td>
                            <td style="font-weight:600; color:#0056b3;">
                                <?php
                                if ($fromTime && $toTime) {
                                    $fromFmt = date('h:i A', strtotime($fromTime));
                                    $toFmt = date('h:i A', strtotime($toTime));
                                    echo htmlspecialchars($fromFmt . ' – ' . $toFmt);
                                } else {
                                    echo 'Time not set';
                                }
                                ?>
                            </td>
                            <td><span class="status-badge payment-paid">Paid</span></td>
                            <td><span class="status-badge status-accepted">Accepted</span></td>
                            <td><?= htmlspecialchars($createdDisplay) ?></td>
                            <td>
                                <a href="view.php?id=<?= (int)$a['id'] ?>" class="view-btn" style="padding:6px 14px;background:#007bff;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </form>
    <?php endif; ?>
</div>
<script>
let selectedIds = [];
function updateBar() {
    selectedIds = Array.from(document.querySelectorAll('.rowCheckbox:checked')).map(cb => cb.value);
    document.getElementById('selectedCount').textContent = selectedIds.length;
    document.getElementById('actionBar').classList.toggle('show', selectedIds.length > 0);
}

document.querySelectorAll('.rowCheckbox').forEach(cb => cb.addEventListener('change', updateBar));
const selectAll = document.getElementById('selectAll');
if (selectAll) {
    selectAll.addEventListener('change', () => {
        document.querySelectorAll('.rowCheckbox').forEach(cb => cb.checked = selectAll.checked);
        updateBar();
    });
}

function submitComplete() {
    if (selectedIds.length === 0) return;
    if (!confirm(`Mark ${selectedIds.length} appointment(s) as Completed?`)) return;
    const form = document.getElementById('completeForm');
    // remove existing hidden inputs
    Array.from(form.querySelectorAll('input[name="appointment_ids[]"]')).forEach(el => el.remove());
    selectedIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'appointment_ids[]';
        input.value = id;
        form.appendChild(input);
    });
    form.submit();
}
</script>
</body>
</html>
