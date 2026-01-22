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
                $st = $pdo->prepare("SELECT customer_name, mobile, tracking_id, form_data FROM service_requests WHERE id = ?");
                $st->execute([$id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row && $row['mobile']) {
                    try {
                        $fd = json_decode($row['form_data'], true) ?? [];
                        $assignedDate = $fd['assigned_date'] ?? '';
                        $formattedDate = $assignedDate;
                        if ($assignedDate) {
                            $dobj = DateTime::createFromFormat('Y-m-d', $assignedDate);
                            if ($dobj) {
                                $formattedDate = $dobj->format('d F Y');
                            }
                        }

                        sendWhatsAppNotification(
                            'appointment_completed_admin',
                            [
                                'mobile' => $row['mobile'],
                                'name' => $row['customer_name'],
                                'tracking_id' => $row['tracking_id'],
                                'appointment_date' => $formattedDate
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
    
    // Handle "Cancel" action
    elseif (isset($_POST['action']) && $_POST['action'] === 'cancel') {
        $appointmentIds = $_POST['appointment_ids'] ?? [];
        if (!empty($appointmentIds)) {
            $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
            $sql = "
                UPDATE service_requests
                SET service_status = 'Received',
                    updated_at = NOW()
                WHERE id IN ($placeholders)
                  AND category_slug = 'appointment'
                  AND payment_status = 'Paid'
                  AND service_status = 'Accepted'
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($appointmentIds);

            // WhatsApp: Appointment Cancelled (for each appointment)
            require_once __DIR__ . '/../../helpers/send_whatsapp.php';
            foreach ($appointmentIds as $id) {
                $st = $pdo->prepare("SELECT customer_name, mobile, tracking_id FROM service_requests WHERE id = ?");
                $st->execute([$id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row && $row['mobile']) {
                    try {
                        sendWhatsAppNotification(
                            'appointment_cancelled_admin',
                            [
                                'mobile' => $row['mobile'],
                                'name' => $row['customer_name'],
                                'tracking_id' => $row['tracking_id']
                            ]
                        );
                    } catch (Throwable $e) {
                        error_log('WhatsApp cancel failed: ' . $e->getMessage());
                    }
                }
            }

            header('Location: accepted-appointments.php?success=cancelled');
            exit;
        }
    }
    
    // Handle "Send Custom Message" action
    elseif (isset($_POST['action']) && $_POST['action'] === 'send_message') {
        $appointmentIds = $_POST['appointment_ids'] ?? [];
        $customMessage = $_POST['custom_message'] ?? '';
        
        if (!empty($appointmentIds) && !empty($customMessage)) {
            require_once __DIR__ . '/../../helpers/send_whatsapp.php';
            $successCount = 0;
            
            foreach ($appointmentIds as $id) {
                $st = $pdo->prepare("SELECT customer_name, mobile FROM service_requests WHERE id = ?");
                $st->execute([$id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row && $row['mobile']) {
                    try {
                        $result = sendWhatsAppNotification(
                            'admin_custom_message',
                            [
                                'mobile' => $row['mobile'],
                                'name' => $row['customer_name'] ?? 'Customer',
                                'message' => $customMessage
                            ]
                        );
                        
                        if ($result['success']) {
                            $successCount++;
                            error_log("Custom message sent to {$row['mobile']}: " . substr($customMessage, 0, 50));
                        } else {
                            error_log("Custom message failed to {$row['mobile']}: " . $result['message']);
                        }
                    } catch (Throwable $e) {
                        error_log('Custom message failed: ' . $e->getMessage());
                    }
                }
            }
            
            header('Location: accepted-appointments.php?success=message_sent&count=' . $successCount);
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
.admin-container { max-width: 1400px; margin: 0 auto; padding: 24px 12px; }
h1 { color: #800000; margin-bottom: 18px; }
.filter-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 18px; }
#dateSelect { padding: 8px 12px; border-radius: 6px; border: 1px solid #ddd; min-width: 260px; }
.action-bar { display: none; background: #e9f7ef; border: 2px solid #28a745; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; align-items: center; gap: 12px; }
.action-bar.show { display: flex; }
.action-btn { padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; font-size: 0.85em; }
.btn-complete { background: #28a745; color: #fff; }
.btn-cancel { background: #dc3545; color: #fff; margin-left: 8px; }
.btn-message { background: #007bff; color: #fff; margin-left: 8px; }
.service-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 12px #e0bebe22; border-radius: 12px; table-layout: auto; font-size: 0.85em; }
.service-table th, .service-table td { padding: 8px 6px; border-bottom: 1px solid #f3caca; text-align: left; white-space: nowrap; }
.service-table th { background: #f9eaea; color: #800000; font-size: 0.9em; font-weight: 600; }
.service-table td { font-size: 0.95em; }
.service-table th:nth-child(1), .service-table td:nth-child(1) { width: 5%; }
.service-table th:nth-child(2), .service-table td:nth-child(2) { width: 9%; }
.service-table th:nth-child(3), .service-table td:nth-child(3) { width: 11%; }
.service-table th:nth-child(4), .service-table td:nth-child(4) { width: 13%; }
.service-table th:nth-child(5), .service-table td:nth-child(5) { width: 10%; }
.service-table th:nth-child(6), .service-table td:nth-child(6) { width: 12%; }
.service-table th:nth-child(7), .service-table td:nth-child(7) { width: 12%; }
.service-table th:nth-child(8), .service-table td:nth-child(8) { width: 11%; }
.service-table th:nth-child(9), .service-table td:nth-child(9) { width: 11%; }
.service-table th:nth-child(10), .service-table td:nth-child(10) { width: 11%; }
.no-data { text-align: center; color: #777; padding: 24px; }
.status-badge { padding: 4px 12px; border-radius: 8px; font-weight: 600; font-size: 0.9em; display: inline-block; min-width: 80px; text-align: center; }
.status-accepted { background: #e5f0ff; color: #0056b3; }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
.modal.show { display: flex; align-items: center; justify-content: center; }
.modal-content { background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); width: 90%; max-width: 500px; }
.modal-header { font-size: 1.3em; font-weight: 600; color: #800000; margin-bottom: 16px; }
.modal-body { margin-bottom: 16px; }
.modal-body textarea { width: 100%; height: 120px; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-family: Arial, sans-serif; resize: vertical; }
.modal-footer { display: flex; gap: 10px; justify-content: flex-end; }
.btn-send { background: #007bff; color: #fff; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
.btn-close { background: #ccc; color: #333; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
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
            <button class="action-btn btn-cancel" onclick="submitCancel()">Cancel Appointments</button>
            <button class="action-btn btn-message" onclick="openMessageModal()">Send Message</button>
        </div>

        <form id="completeForm" method="POST">
            <input type="hidden" name="action" value="complete">
            <table class="service-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>View</th>
                        <th>Tracking ID</th>
                        <th>Customer Name</th>
                        <th>Mobile</th>
                        <th>Preferred Date</th>
                        <th>Scheduled Time</th>
                        <th>Payment Status</th>
                        <th>Service Status</th>
                        <th>Created Date</th>
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
                            <td>
                                <a href="view.php?id=<?= (int)$a['id'] ?>" class="view-btn" style="padding:6px 14px;background:#007bff;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;display:inline-block;">View</a>
                            </td>
                            <td><?= htmlspecialchars($a['tracking_id']) ?></td>
                            <td><?= htmlspecialchars($a['customer_name']) ?></td>
                            <td><?= htmlspecialchars($a['mobile']) ?></td>
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
    form.action = '?action=complete';
    form.submit();
}

function submitCancel() {
    if (selectedIds.length === 0) return;
    if (!confirm(`Cancel ${selectedIds.length} appointment(s)? Customers will be notified.`)) return;
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
    form.action = location.href;
    document.querySelector('input[name="action"]').value = 'cancel';
    form.submit();
}

function openMessageModal() {
    if (selectedIds.length === 0) {
        alert('Please select at least one appointment');
        return;
    }
    document.getElementById('messageModal').classList.add('show');
}

function closeMessageModal() {
    document.getElementById('messageModal').classList.remove('show');
    document.getElementById('customMessage').value = '';
}

function submitMessage() {
    const message = document.getElementById('customMessage').value.trim();
    if (!message) {
        alert('Please write a message');
        return;
    }
    if (!confirm(`Send message to ${selectedIds.length} customer(s)?`)) return;
    
    const form = document.getElementById('completeForm');
    Array.from(form.querySelectorAll('input[name="appointment_ids[]"]')).forEach(el => el.remove());
    selectedIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'appointment_ids[]';
        input.value = id;
        form.appendChild(input);
    });
    
    const msgInput = document.createElement('input');
    msgInput.type = 'hidden';
    msgInput.name = 'custom_message';
    msgInput.value = message;
    form.appendChild(msgInput);
    
    form.action = location.href;
    document.querySelector('input[name="action"]').value = 'send_message';
    form.submit();
    closeMessageModal();
}

document.getElementById('messageModal')?.addEventListener('click', (e) => {
    if (e.target.id === 'messageModal') closeMessageModal();
});
</script>

<!-- Custom Message Modal -->
<div id="messageModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">Send Custom Message</div>
        <div class="modal-body">
            <textarea id="customMessage" placeholder="Type your message here..."></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn-close" onclick="closeMessageModal()">Cancel</button>
            <button class="btn-send" onclick="submitMessage()">Send Message</button>
        </div>
    </div>
</div>

</body>
</html>
