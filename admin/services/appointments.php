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
            $sql = "
                UPDATE service_requests
                SET service_status = 'Accepted',
                    form_data = JSON_SET(
                        form_data,
                        '$.assigned_date', ?,
                        '$.assigned_from_time', ?,
                        '$.assigned_to_time', ?
                    ),
                    updated_at = :updated_at
                // Use PHP datetime for updated_at
                $updatedAt = date('Y-m-d H:i:s');
                WHERE id IN ($placeholders)
                  AND category_slug = 'appointment'
                  AND payment_status = 'Paid'
            ";
            $stmt = $pdo->prepare($sql);
            $params = array_merge([$assignedDate, $timeFrom, $timeTo], $appointmentIds);
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

/* ============================================================
    FETCH UNACCEPTED APPOINTMENT DATES (DATE(created_at))
   ============================================================ */

$pendingDates = [];

$whereUnaccepted = "
    category_slug = 'appointment'
    AND payment_status IN ('Paid', 'Free')
    AND (
        service_status IN ('Received','Pending')
        OR (
            service_status = 'Accepted'
            AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(form_data,'$.assigned_date')), '') <> ''
            AND JSON_UNQUOTE(JSON_EXTRACT(form_data,'$.assigned_date')) < CURDATE()
        )
    )
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
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
<h1>Appointment Management</h1>

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
</div>

<?php if (empty($pendingDates)): ?>
    <div class="no-data" style="font-size:1.2em;color:#800000;font-weight:600;">
        No pending appointments.
    </div>
<?php else: ?>


<?php
// PHASE 3 – Determine default selection (today or oldest pending date)
$defaultDate = null;
$todayDate = date('Y-m-d');
if (isset($pendingDates[$todayDate])) {
    $defaultDate = $todayDate;
} elseif (!empty($pendingDates)) {
    $defaultDate = array_key_first($pendingDates);
}
?>

<?php if (!empty($pendingDates)): ?>
<div class="filter-bar" style="align-items: center;">
    <label for="appointmentDateSelect">Select Appointment Date</label>
    <select id="appointmentDateSelect" name="date" onchange="window.location.href='?date=' + this.value;">
        <?php foreach ($pendingDates as $date => $count): ?>
            <?php
                $dateObj = DateTime::createFromFormat('Y-m-d', $date);
                $formattedDate = $dateObj->format('d-M-Y');
                $isToday = ($date === $todayDate) ? 'today' : '';
                $selected = ($date === $selectedDate) ? 'selected' : '';
            ?>
            <option value="<?= htmlspecialchars($date) ?>" <?= $selected ?> data-date="<?= $date ?>" class="<?= $isToday ?>">
                <?= htmlspecialchars($formattedDate) ?> – <?= (int)$count ?> Appointment<?= $count !== 1 ? 's' : '' ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div style="margin-bottom:18px;font-weight:600;color:#800000;">
    Total appointments on this date: <?= $pendingDates[$selectedDate] ?? 0 ?>
</div>

<!-- PHASE 2 – ACTION BAR -->
<div class="action-bar" id="actionBar">
    <span class="action-bar-label"><span id="selectedCount">0</span> appointment(s) selected</span>
    <button class="action-btn btn-accept" onclick="openAcceptModal()">Accept Selected</button>
</div>

<?php else: ?>
<div class="no-data" style="font-size:1.2em;color:#800000;font-weight:600;">
    No pending appointments available
</div>
<?php endif; ?>

<table class="service-table">
    <thead>
        <tr>
            <th><input type="checkbox" id="selectAll"></th>
            <th>View</th>
            <th>Tracking ID</th>
            <th>Products</th>
            <th>Customer Name</th>
            <th>Mobile</th>
            <th>Preferred Date</th>
            <th>Payment Status</th>
            <th>Service Status</th>
            <th>Created Date</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($appointments)): ?>
            <tr>
                <td colspan="10" class="no-data">No appointment bookings found.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($appointments as $a): ?>
                <?php
                    $formData = json_decode($a['form_data'], true) ?? [];
                    $preferredDate = $formData['preferred_date'] ?? '';
                    $preferredDisplay = $preferredDate ? (DateTime::createFromFormat('Y-m-d', $preferredDate)?->format('d-M-Y') ?: $preferredDate) : '—';
                    $createdDisplay = '';
                    if (!empty($a['created_at'])) {
                        $co = new DateTime($a['created_at']);
                        $createdDisplay = $co->format('d-M-Y');
                    }
                ?>
                <tr>
                    <td>
                        <input type="checkbox" class="rowCheckbox" value="<?= (int)$a['id'] ?>" data-date="<?= htmlspecialchars($selectedDate) ?>">
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
                                // If name missing, fetch from products table using id
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
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

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
// Track selected appointments
let selectedAppointments = [];

// Update action bar visibility and selection counter
function updateActionBar() {
    const checkboxes = document.querySelectorAll('.rowCheckbox:checked');
    selectedAppointments = Array.from(checkboxes).map(cb => ({
        id: cb.value,
        date: cb.getAttribute('data-date')
    }));
    
    const count = selectedAppointments.length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('actionBar').classList.toggle('show', count > 0);
}

// Accept Appointment Functions (Simplified)
function openAcceptModal() {
    if (selectedAppointments.length === 0) return;
    
    // Set appointment IDs
    const ids = selectedAppointments.map(a => a.id);
    document.getElementById('acceptAppointmentIds').value = ids.join(',');
    
    // Default to today
    document.getElementById('assignedDate').value = '<?= date('Y-m-d') ?>';
    
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
    
    if (!confirm(`Accept ${selectedAppointments.length} appointment(s)?`)) {
        return false;
    }
    
    // Convert single hidden input to multiple
    const form = event.target;
    form.querySelector('#acceptAppointmentIds').remove();
    selectedAppointments.forEach(a => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'appointment_ids[]';
        input.value = a.id;
        form.appendChild(input);
    });
    
    return true;
}

// Reschedule flow removed

// Select/Deselect all checkboxes
const selectAll = document.getElementById('selectAll');
if (selectAll) {
    selectAll.addEventListener('change', function () {
        document.querySelectorAll('.rowCheckbox').forEach(cb => {
            cb.checked = selectAll.checked;
        });
        updateActionBar();
    });
}

// Listen to individual checkbox changes
document.querySelectorAll('.rowCheckbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateActionBar);
});
</script>

</body>
</html>
