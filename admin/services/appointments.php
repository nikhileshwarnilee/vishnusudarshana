<?php
/**
 * admin/services/appointments.php
 *
 * Appointment Management – Phase 3 (Pending-first, date-driven)
 * Data source: service_requests table
 * category_slug = 'appointment'
 */

session_start();

require_once __DIR__ . '/../../config/db.php';

/* ============================================================
    HANDLE ACCEPT ACTION (Simplified)
   ============================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'accept') {
        $appointmentIds = $_POST['appointment_ids'] ?? [];
        $assignedDate = $_POST['assigned_date'] ?? '';
        $timeFrom = $_POST['time_from'] ?? '';
        $timeTo = $_POST['time_to'] ?? '';

        // Validation: required fields, time ordering, date not in past
        if (!empty($appointmentIds) && $assignedDate && $timeFrom && $timeTo && $timeFrom < $timeTo && $assignedDate >= date('Y-m-d')) {
            $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
            $updatedAt = date('Y-m-d H:i:s');
            $sql = "
                UPDATE service_requests
                SET service_status = 'Accepted',
                    form_data = JSON_SET(
                        form_data,
                        '$.assigned_date', ?,
                        '$.assigned_from_time', ?,
                        '$.assigned_to_time', ?
                    ),
                    updated_at = NOW()
                WHERE id IN ($placeholders)
                  AND category_slug = 'appointment'
                  AND payment_status IN ('Paid', 'Free')
            ";
            $params = array_merge([$assignedDate, $timeFrom, $timeTo], $appointmentIds);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // WhatsApp: Appointment Scheduled (admin accepted with date/time)
            require_once __DIR__ . '/../../helpers/send_whatsapp.php';
            foreach ($appointmentIds as $id) {
                $st = $pdo->prepare("SELECT customer_name, mobile, tracking_id FROM service_requests WHERE id = ?");
                $st->execute([$id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row && $row['mobile']) {
                    try {
                        // Format appointment date for display
                        $dateObj = DateTime::createFromFormat('Y-m-d', $assignedDate);
                        $formattedDate = $dateObj ? $dateObj->format('d F Y') : $assignedDate;

                        // Format time to 12-hour with AM/PM
                        $fromObj = DateTime::createFromFormat('H:i', $timeFrom);
                        $toObj = DateTime::createFromFormat('H:i', $timeTo);
                        $formattedFrom = $fromObj ? $fromObj->format('g:i A') : $timeFrom;
                        $formattedTo = $toObj ? $toObj->format('g:i A') : $timeTo;
                        
                        sendWhatsAppNotification(
                            'admin_appointment_scheduled',
                            [
                                'mobile' => $row['mobile'],
                                'name' => $row['customer_name'],
                                'tracking_id' => $row['tracking_id'],
                                'appointment_date' => $formattedDate,
                                'from_time' => $formattedFrom,
                                'to_time' => $formattedTo
                            ]
                        );
                    } catch (Throwable $e) {
                        error_log('WhatsApp appointment scheduled failed: ' . $e->getMessage());
                    }
                }
            }

            header('Location: appointments.php?success=accepted');
            exit;
        }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'mark_completed') {
        $appointmentIds = $_POST['appointment_ids'] ?? [];
        if (!empty($appointmentIds)) {
            $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
            $sql = "
                UPDATE service_requests
                SET service_status = 'Completed',
                    updated_at = NOW()
                WHERE id IN ($placeholders)
                  AND category_slug = 'appointment'
                  AND payment_status IN ('Paid', 'Free')
            ";
            $params = $appointmentIds;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // WhatsApp: Appointment Completed (for each appointment)
            require_once __DIR__ . '/../../helpers/send_whatsapp.php';
            foreach ($appointmentIds as $id) {
                $st = $pdo->prepare("SELECT customer_name, mobile, tracking_id, form_data FROM service_requests WHERE id = ?");
                $st->execute([$id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row && $row['mobile']) {
                    try {
                        // Use today's date as completion date
                        $completionDate = date('d F Y');

                        sendWhatsAppNotification(
                            'appointment_completed_admin',
                            [
                                'mobile' => $row['mobile'],
                                'name' => $row['customer_name'],
                                'tracking_id' => $row['tracking_id'],
                                'appointment_date' => $completionDate
                            ]
                        );
                    } catch (Throwable $e) {
                        error_log('WhatsApp completed failed: ' . $e->getMessage());
                    }
                }
            }

            header('Location: appointments.php?success=completed');
            exit;
        }
    }
}

/* ============================================================
    DASHBOARD STATISTICS (Optional)
   ============================================================ */

// Total appointments
$stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM service_requests
        WHERE category_slug = 'appointment'
            AND payment_status IN ('Paid', 'Free')
");
$stmt->execute();
$totalAppointments = (int)$stmt->fetchColumn();

// Today's appointments
$stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM service_requests
        WHERE category_slug = 'appointment'
            AND payment_status IN ('Paid', 'Free')
            AND DATE(created_at) = CURDATE()
");
$stmt->execute();
$todayAppointments = (int)$stmt->fetchColumn();

// Pending (Received)
$stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM service_requests
        WHERE category_slug = 'appointment'
            AND payment_status IN ('Paid', 'Free')
            AND service_status = 'Received'
");
$stmt->execute();
$pendingAppointments = (int)$stmt->fetchColumn();

// Accepted
$stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM service_requests
        WHERE category_slug = 'appointment'
            AND payment_status IN ('Paid', 'Free')
            AND service_status = 'Accepted'
");
$stmt->execute();
$acceptedAppointments = (int)$stmt->fetchColumn();

// Completed
$stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM service_requests
        WHERE category_slug = 'appointment'
            AND payment_status IN ('Paid', 'Free')
            AND service_status = 'Completed'
");
$stmt->execute();
$completedAppointments = (int)$stmt->fetchColumn();

// Failed Appointments (from pending_payments, same logic as failed-appointments.php)
$stmt = $pdo->prepare("SELECT id, customer_details, form_data FROM pending_payments WHERE category = 'appointment'");
$stmt->execute();
$failedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$failedAppointmentsCount = 0;
foreach ($failedRows as $row) {
    // Optionally parse JSON fields if you want to filter further in the future
    $failedAppointmentsCount++;
}

/* ============================================================
    FETCH UNACCEPTED APPOINTMENT DATES (DATE(created_at))
   ============================================================ */

$pendingDates = [];

// Only show unaccepted appointments in the pending list
$whereUnaccepted = "
    category_slug = 'appointment'
    AND payment_status IN ('Paid', 'Free')
    AND service_status IN ('Received','Pending')
";

// Fetch all unaccepted appointments with their preferred dates
$stmt = $pdo->prepare("SELECT form_data, created_at FROM service_requests WHERE $whereUnaccepted");
$stmt->execute();
$dateRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$preferredDateCounts = [];
foreach ($dateRows as $r) {
    $formData = json_decode($r['form_data'], true);
    $preferred = $formData['preferred_date'] ?? null;
    if ($preferred) {
        if (!isset($preferredDateCounts[$preferred])) {
            $preferredDateCounts[$preferred] = 0;
        }
        $preferredDateCounts[$preferred]++;
    }
}
// Sort by preferred date ASC
uksort($preferredDateCounts, function($a, $b) {
    return strtotime($a) <=> strtotime($b);
});
$pendingDates = $preferredDateCounts;

/* ============================================================
    AUTO-SELECT OLDEST PENDING DATE
   ============================================================ */

if (!empty($pendingDates)) {
    if (isset($_GET['date']) && isset($pendingDates[$_GET['date']])) {
        $selectedDate = $_GET['date'];
    } else {
        $selectedDate = array_key_first($pendingDates);
    }
} else {
    $selectedDate = null;
}

/* ============================================================
    FETCH APPOINTMENTS FOR SELECTED DATE (Unaccepted criteria)
   ============================================================ */

$appointments = [];

if ($selectedDate !== null) {
    // Fetch all unaccepted appointments
    $sqlList = "
                SELECT id, tracking_id, customer_name, mobile, email, payment_status, service_status, form_data, selected_products, created_at
                FROM service_requests
                WHERE $whereUnaccepted
                ORDER BY created_at DESC
        ";
    $stmt = $pdo->prepare($sqlList);
    $stmt->execute();
    $allAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Filter by preferred_date
    $appointments = array_filter($allAppointments, function($row) use ($selectedDate) {
        $formData = json_decode($row['form_data'], true);
        return isset($formData['preferred_date']) && $formData['preferred_date'] === $selectedDate;
    });
}
// Group all unaccepted appointments by preferred date (for all dates, not just selected)
$appointmentsByDate = [];
$sqlList = "
    SELECT id, tracking_id, customer_name, mobile, email, payment_status, service_status, form_data, selected_products, created_at
    FROM service_requests
    WHERE $whereUnaccepted
    ORDER BY created_at DESC
";
$stmt = $pdo->prepare($sqlList);
$stmt->execute();
$allAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($allAppointments as $row) {
    $formData = json_decode($row['form_data'], true);
    $preferred = $formData['preferred_date'] ?? null;
    if ($preferred) {
        $appointmentsByDate[$preferred][] = $row;
    }
}
ksort($appointmentsByDate);
// Ensure $appointmentsByDate is always defined
if (!isset($appointmentsByDate) || !is_array($appointmentsByDate)) {
    $appointmentsByDate = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Appointment Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f7f7fa;
    margin: 0;
}
.admin-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px 12px;
}
h1 {
    color: #800000;
    margin-bottom: 18px;
}
/* Summary cards now in responsive-cards.css */
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
    align-items: center;
}
.filter-bar label {
    font-weight: 600;
}
.filter-bar input[type="date"] {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 1em;
}
.service-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    box-shadow: 0 2px 12px #e0bebe22;
    border-radius: 12px;
    table-layout: auto;
    font-size: 0.85em;
}
.service-table th,
.service-table td {
    padding: 8px 6px;
    border-bottom: 1px solid #f3caca;
    text-align: left;
    white-space: nowrap;
}
.service-table th {
    background: #f9eaea;
    color: #800000;
    font-size: 0.9em;
    font-weight: 600;
}
.service-table td {
    font-size: 0.95em;
}
.service-table tbody tr:hover {
    background: #f3f7fa;
}
.service-table th:nth-child(1), .service-table td:nth-child(1) { width: 5%; }
.service-table th:nth-child(2), .service-table td:nth-child(2) { width: 10%; }
.service-table th:nth-child(3), .service-table td:nth-child(3) { width: 12%; }
.service-table th:nth-child(4), .service-table td:nth-child(4) { width: 14%; }
.service-table th:nth-child(5), .service-table td:nth-child(5) { width: 11%; }
.service-table th:nth-child(6), .service-table td:nth-child(6) { width: 13%; }
.service-table th:nth-child(7), .service-table td:nth-child(7) { width: 12%; }
.service-table th:nth-child(8), .service-table td:nth-child(8) { width: 11%; }
.service-table th:nth-child(9), .service-table td:nth-child(9) { width: 12%; }
.status-badge {
    padding: 4px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9em;
    display: inline-block;
    min-width: 80px;
    text-align: center;
}
.status-received { background: #e5f0ff; color: #0056b3; }
.payment-paid { background: #e5ffe5; color: #1a8917; }
.badge-overdue {
    background: #ff4444;
    color: #fff;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.85em;
    font-weight: 600;
    margin-left: 6px;
}
.no-data {
    text-align: center;
    color: #777;
    padding: 24px;
}
/* Action Bar */
.action-bar {
    display: none;
    background: #fff3cd;
    border: 2px solid #ffc107;
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
    align-items: center;
    gap: 12px;
}
.action-bar.show {
    display: flex;
}
.action-bar-label {
    font-weight: 600;
    color: #856404;
    margin-right: auto;
}
.action-btn {
    padding: 8px 16px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.95em;
}
.btn-accept {
    background: #28a745;
    color: #fff;
}
.btn-reschedule {
    background: #ffc107;
    color: #333;
}
.btn-accept:hover {
    background: #218838;
}
.btn-reschedule:hover {
    background: #e0a800;
}
/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}
.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-content {
    background: #fff;
    border-radius: 12px;
    padding: 24px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.modal-header h2 {
    color: #800000;
    margin: 0;
}
.modal-close {
    background: none;
    border: none;
    font-size: 1.5em;
    cursor: pointer;
    color: #999;
}
.form-group {
    margin-bottom: 16px;
}
.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    color: #333;
}
.form-group input,
.form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 1em;
}
.form-group textarea {
    resize: vertical;
    min-height: 80px;
}
.modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}
.btn-cancel {
    padding: 8px 16px;
    background: #6c757d;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}
.btn-submit {
    padding: 8px 16px;
    background: #800000;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}
.btn-cancel:hover {
    background: #5a6268;
}
.btn-submit:hover {
    background: #600000;
}
/* PHASE 4 – Dropdown Styling */
#appointmentDateSelect {
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #ddd;
    font-size: 1em;
    background: #fff;
    color: #333;
    cursor: pointer;
    min-width: 250px;
}
#appointmentDateSelect:focus {
    outline: none;
    border-color: #800000;
    box-shadow: 0 0 4px #800000;
}
#appointmentDateSelect option[data-date] {
    padding: 8px;
}
/* Mobile styles now in responsive-cards.css */
#appointmentDateSelect { 
    width: 100%;
}
</style>
</head>

<body>
<div style="max-width:600px;margin:18px auto 0 auto;text-align:center;">
    <input type="text" id="globalSearch" placeholder="Search appointments..." style="width:100%;padding:10px 14px;font-size:1.1em;border-radius:8px;border:1px solid #ccc;">
</div>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
<h1 style="display:flex;align-items:center;gap:12px;">Appointment Management
    <button id="refreshRollbackBtn" title="Run Auto Rollback" style="background:none;border:none;cursor:pointer;padding:0;margin-left:8px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24"><path fill="#007bff" d="M12 6V3l-5 5 5 5V8c3.31 0 6 2.69 6 6 0 1.3-.42 2.5-1.13 3.47l1.46 1.46A7.938 7.938 0 0020 14c0-4.42-3.58-8-8-8zm-6.87 2.53L3.67 7.07A7.938 7.938 0 004 10c0 4.42 3.58 8 8 8v3l5-5-5-5v3c-3.31 0-6-2.69-6-6 0-1.3.42-2.5 1.13-3.47z"/></svg>
        <span style="font-size:0.9em;color:#007bff;vertical-align:middle;">Refresh</span>
    </button>
</h1>
<div id="rollbackMsg" style="margin-bottom:12px;font-weight:600;color:#007bff;"></div>
</script>
<script>
document.getElementById('refreshRollbackBtn').onclick = function() {
    var btn = this;
    btn.disabled = true;
    btn.style.opacity = 0.6;
    document.getElementById('rollbackMsg').textContent = 'Running auto-rollback...';
    fetch('auto_rollback_appointments.php')
        .then(r => r.text())
        .then(msg => {
            document.getElementById('rollbackMsg').textContent = msg || 'Auto-rollback completed.';
            btn.disabled = false;
            btn.style.opacity = 1;
            setTimeout(function() {
                window.location.reload();
            }, 2200);
        })
        .catch(() => {
            document.getElementById('rollbackMsg').textContent = 'Error running auto-rollback.';
            btn.disabled = false;
            btn.style.opacity = 1;
        });
};
</script>

<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-count"><?= $todayAppointments ?></div>
        <div class="summary-label">Today’s Appointments</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?= $pendingAppointments ?></div>
        <div class="summary-label">Pending</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?= $acceptedAppointments ?></div>
        <div class="summary-label">Accepted</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?= $completedAppointments ?></div>
        <div class="summary-label">Completed</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?= $totalAppointments ?></div>
        <div class="summary-label">Total Appointments</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?= $failedAppointmentsCount ?></div>
        <div class="summary-label">Failed</div>
    </div>
</div>

<?php if (empty($pendingDates)): ?>
    <div class="no-data" style="font-size:1.2em;color:#800000;font-weight:600;">
        No pending appointments.
    </div>
<?php else: ?>
    <?php foreach ($appointmentsByDate as $date => $appointments): ?>
        <?php
            $dateObj = DateTime::createFromFormat('Y-m-d', $date);
            $formattedDate = $dateObj ? $dateObj->format('d-M-Y') : htmlspecialchars($date);
        ?>
        <h2 style="margin-top:32px;color:#800000;font-size:1.3em;">
            <?= $formattedDate ?> – <?= count($appointments) ?> Appointment<?= count($appointments) !== 1 ? 's' : '' ?>
        </h2>
        <div class="action-bar" id="actionBar-<?= htmlspecialchars($date) ?>">
            <span class="action-bar-label"><span id="selectedCount-<?= htmlspecialchars($date) ?>">0</span> appointment(s) selected</span>
            <button class="action-btn btn-accept" onclick="openAcceptModal('<?= htmlspecialchars($date) ?>')">Accept Selected</button>
            <button class="action-btn btn-completed" onclick="submitMarkCompleted('<?= htmlspecialchars($date) ?>')" style="background:#007bff;color:#fff;">Mark Completed</button>
        <script>
        function submitMarkCompleted(date) {
            if (!selectedAppointmentsByDate[date] || selectedAppointmentsByDate[date].length === 0) return;
            const ids = selectedAppointmentsByDate[date].map(a => a.id);
            if (!confirm(`Mark ${ids.length} appointment(s) as completed?`)) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            form.innerHTML = `<input type="hidden" name="action" value="mark_completed">`;
            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'appointment_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
        }
        </script>
        </div>
        <table class="service-table">
            <thead>
                <tr>
                    <th><input type="checkbox" class="selectAll" data-date="<?= htmlspecialchars($date) ?>"></th>
                    <th>View</th>
                    <th>Tracking ID</th>
                    <th>Products</th>
                    <th>Customer Name</th>
                    <th>Mobile</th>
                    <th>Preferred Date</th>
                    <th>Payment Status</th>
                    <th>Service Status</th>
                    <th>Created Date</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $a): ?>
                    <?php
                        $formData = json_decode($a['form_data'], true) ?? [];
                        $notes = isset($formData['notes']) ? htmlspecialchars($formData['notes']) : '';
                        $preferredDate = $formData['preferred_date'] ?? '';
                        $preferredDisplay = $preferredDate ? (DateTime::createFromFormat('Y-m-d', $preferredDate)?->format('d-M-Y') ?: $preferredDate) : '—';
                        $createdDisplay = '';
                        if (!empty($a['created_at'])) {
                            $co = new DateTime($a['created_at']);
                            $createdDisplay = $co->format('d-M-Y h:i A');
                        }
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="rowCheckbox" value="<?= (int)$a['id'] ?>" data-date="<?= htmlspecialchars($date) ?>">
                        </td>
                        <td>
                            <a href="view.php?id=<?= (int)$a['id'] ?>" class="view-btn" style="padding:6px 14px;background:#007bff;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">View</a>
                        </td>
                        <td><?= htmlspecialchars($a['tracking_id']) ?></td>
                        <td>
                            <?php
                            $products = '-';
                            $decoded = json_decode($a['selected_products'], true);
                            if (is_array($decoded) && count($decoded)) {
                                $productDetails = [];
                                foreach ($decoded as $prod) {
                                    $qty = isset($prod['qty']) ? (int)$prod['qty'] : 1;
                                    $name = isset($prod['name']) ? htmlspecialchars($prod['name']) : '';
                                    $price = isset($prod['price']) ? $prod['price'] : '';
                                    if ($name === '' && isset($prod['id'])) {
                                        $pid = (int)$prod['id'];
                                        $stmtP = $pdo->prepare('SELECT product_name FROM products WHERE id = ? LIMIT 1');
                                        $stmtP->execute([$pid]);
                                        $rowP = $stmtP->fetch(PDO::FETCH_ASSOC);
                                        if ($rowP && isset($rowP['product_name'])) {
                                            $name = htmlspecialchars($rowP['product_name']);
                                        }
                                    }
                                    if ($name !== '') {
                                        $label = $name . ' x' . $qty;
                                        if ($price !== '') {
                                            $label .= ' (₹' . number_format((float)$price, 2) . ')';
                                        }
                                        $productDetails[] = $label;
                                    }
                                }
                                if ($productDetails) {
                                    $products = implode(', ', $productDetails);
                                }
                            }
                            echo $products;
                            ?>
                        </td>
                        <td><?= htmlspecialchars($a['customer_name']) ?></td>
                        <td><?= htmlspecialchars($a['mobile']) ?></td>
                        <td style="font-weight:600;color:#800000;">
                            <?= htmlspecialchars($preferredDisplay) ?>
                        </td>
                        <td><span class="status-badge payment-paid">Paid</span></td>
                        <td><span class="status-badge status-received">Unaccepted</span></td>
                        <td><?= htmlspecialchars($createdDisplay) ?></td>
                        <td><?= $notes ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
<?php endif; ?>

</div>

<!-- PHASE 3 – ACCEPT APPOINTMENT MODAL -->
<div class="modal" id="acceptModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Accept Appointment</h2>
            <button class="modal-close" onclick="closeAcceptModal()">&times;</button>
        </div>
        <form method="POST" onsubmit="return validateAcceptForm()">
            <input type="hidden" name="action" value="accept">
            <input type="hidden" name="appointment_ids[]" id="acceptAppointmentIds">
            
            <div class="form-group">
                <label for="assignedDate">Assigned Date *</label>
                <input type="date" id="assignedDate" name="assigned_date" required min="<?= date('Y-m-d') ?>">
            </div>
            
            <div class="form-group">
                <label for="timeFrom">Time From *</label>
                <input type="time" id="timeFrom" name="time_from" required>
            </div>
            
            <div class="form-group">
                <label for="timeTo">Time To *</label>
                <input type="time" id="timeTo" name="time_to" required>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeAcceptModal()">Cancel</button>
                <button type="submit" class="btn-submit">Accept Appointment</button>
            </div>
        </form>
    </div>
</div>

<!-- Reschedule removed in simplified flow -->

<script>
// Global search filter for all tables
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('globalSearch');
    if (!searchInput) return;
    searchInput.addEventListener('input', function() {
        const val = this.value.trim().toLowerCase();
        document.querySelectorAll('.service-table tbody tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = val === '' || text.includes(val) ? '' : 'none';
        });
    });
});
// Track selected appointments per date
let selectedAppointmentsByDate = {};

function updateActionBar(date) {
    const checkboxes = document.querySelectorAll('.rowCheckbox[data-date="' + date + '"]:checked');
    selectedAppointmentsByDate[date] = Array.from(checkboxes).map(cb => ({
        id: cb.value,
        date: cb.getAttribute('data-date')
    }));
    const count = selectedAppointmentsByDate[date].length;
    document.getElementById('selectedCount-' + date).textContent = count;
    document.getElementById('actionBar-' + date).classList.toggle('show', count > 0);
}

function openAcceptModal(date) {
    if (!selectedAppointmentsByDate[date] || selectedAppointmentsByDate[date].length === 0) return;
    // Set appointment IDs
    const ids = selectedAppointmentsByDate[date].map(a => a.id);
    document.getElementById('acceptAppointmentIds').value = ids.join(',');
    // Set assigned date to preferred date
    document.getElementById('assignedDate').value = date;
    // Set default time from 09:00 and to 21:00
    document.getElementById('timeFrom').value = '09:00';
    document.getElementById('timeTo').value = '21:00';
    document.getElementById('acceptModal').classList.add('show');
}

function closeAcceptModal() {
    document.getElementById('acceptModal').classList.remove('show');
}

function validateAcceptForm() {
    const timeFrom = document.getElementById('timeFrom').value;
    const timeTo = document.getElementById('timeTo').value;
    if (timeFrom >= timeTo) {
        alert('Time From must be before Time To');
        return false;
    }
    // Find which date's action bar is open
    let selectedDate = null;
    let count = 0;
    for (const date in selectedAppointmentsByDate) {
        if (selectedAppointmentsByDate[date] && selectedAppointmentsByDate[date].length > 0) {
            selectedDate = date;
            count = selectedAppointmentsByDate[date].length;
            break;
        }
    }
    if (!selectedDate) return false;
    // Use the actual checked checkboxes count for confirmation
    const checked = document.querySelectorAll('.rowCheckbox[data-date="' + selectedDate + '"]:checked').length;
    if (!confirm(`Accept ${checked} appointment(s)?`)) {
        return false;
    }
    // Convert single hidden input to multiple
    const form = event.target;
    form.querySelector('#acceptAppointmentIds').remove();
    selectedAppointmentsByDate[selectedDate].forEach(a => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'appointment_ids[]';
        input.value = a.id;
        form.appendChild(input);
    });
    return true;
}

// Select/Deselect all checkboxes per table
setTimeout(function() {
    document.querySelectorAll('.selectAll').forEach(selectAll => {
        const date = selectAll.getAttribute('data-date');
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.rowCheckbox[data-date="' + date + '"]').forEach(cb => {
                cb.checked = selectAll.checked;
            });
            updateActionBar(date);
        });
    });
    // Listen to individual checkbox changes per table
    document.querySelectorAll('.rowCheckbox').forEach(checkbox => {
        const date = checkbox.getAttribute('data-date');
        checkbox.addEventListener('change', function() {
            updateActionBar(date);
        });
    });
}, 100);
</script>

</body>
</html>
