<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
/**
 * admin/services/appointments.php
 *
 * Appointment Management - Phase 3 (Pending-first, date-driven)
 * Data source: service_requests table
 * category_slug = 'appointment'
 */

if (session_status() === PHP_SESSION_NONE) {

    session_start();

}
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/system_settings.php';

if (!function_exists('vs_appointments_is_valid_time')) {
    function vs_appointments_is_valid_time(string $value): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }
}

if (!function_exists('vs_appointments_normalize_time_setting')) {
    function vs_appointments_normalize_time_setting(?string $value, string $fallback): string
    {
        $value = trim((string) $value);

        return vs_appointments_is_valid_time($value) ? $value : $fallback;
    }
}

if (!function_exists('vs_appointments_format_time_label')) {
    function vs_appointments_format_time_label(string $value): string
    {
        $timeObject = DateTime::createFromFormat('H:i', $value);

        return $timeObject ? $timeObject->format('g:i A') : $value;
    }
}

$appointmentDefaultTimeFromKey = 'appointment_default_time_from';
$appointmentDefaultTimeToKey = 'appointment_default_time_to';
$appointmentSameDayCutoffKey = 'appointment_same_day_booking_cutoff_time';
$appointmentDefaultTimeFrom = '09:00';
$appointmentDefaultTimeTo = '21:00';
$appointmentSameDayCutoffTime = '11:30';
$appointmentSettingsSaved = isset($_GET['settings']) && $_GET['settings'] === 'saved';
$appointmentSettingsErrorMessage = '';

try {
    vs_settings_ensure_table($pdo);
    $appointmentDefaultTimeFrom = vs_appointments_normalize_time_setting(
        vs_settings_get_value($pdo, $appointmentDefaultTimeFromKey),
        $appointmentDefaultTimeFrom
    );
    $appointmentDefaultTimeTo = vs_appointments_normalize_time_setting(
        vs_settings_get_value($pdo, $appointmentDefaultTimeToKey),
        $appointmentDefaultTimeTo
    );
    $appointmentSameDayCutoffTime = vs_appointments_normalize_time_setting(
        vs_settings_get_value($pdo, $appointmentSameDayCutoffKey),
        $appointmentSameDayCutoffTime
    );
} catch (Throwable $e) {
    error_log('Unable to load appointment schedule defaults: ' . $e->getMessage());
}

/* ============================================================
    HANDLE ACCEPT ACTION (Simplified)
   ============================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string) ($_POST['action'] ?? '');

    if ($postAction === 'save_schedule_defaults') {
        $submittedDefaultTimeFrom = trim((string) ($_POST['default_time_from'] ?? ''));
        $submittedDefaultTimeTo = trim((string) ($_POST['default_time_to'] ?? ''));
        $submittedSameDayCutoffTime = trim((string) ($_POST['same_day_booking_cutoff_time'] ?? ''));

        $appointmentDefaultTimeFrom = vs_appointments_normalize_time_setting(
            $submittedDefaultTimeFrom,
            $appointmentDefaultTimeFrom
        );
        $appointmentDefaultTimeTo = vs_appointments_normalize_time_setting(
            $submittedDefaultTimeTo,
            $appointmentDefaultTimeTo
        );
        $appointmentSameDayCutoffTime = vs_appointments_normalize_time_setting(
            $submittedSameDayCutoffTime,
            $appointmentSameDayCutoffTime
        );

        if (
            !vs_appointments_is_valid_time($submittedDefaultTimeFrom) ||
            !vs_appointments_is_valid_time($submittedDefaultTimeTo) ||
            !vs_appointments_is_valid_time($submittedSameDayCutoffTime) ||
            $submittedDefaultTimeFrom >= $submittedDefaultTimeTo
        ) {
            $appointmentSettingsErrorMessage = 'Please choose a valid default schedule and cutoff time.';
        } else {
            try {
                vs_settings_ensure_table($pdo);
                vs_settings_upsert($pdo, $appointmentDefaultTimeFromKey, $submittedDefaultTimeFrom);
                vs_settings_upsert($pdo, $appointmentDefaultTimeToKey, $submittedDefaultTimeTo);
                vs_settings_upsert($pdo, $appointmentSameDayCutoffKey, $submittedSameDayCutoffTime);

                header('Location: appointments.php?settings=saved');
                exit;
            } catch (Throwable $e) {
                error_log('Unable to save appointment schedule defaults: ' . $e->getMessage());
                $appointmentSettingsErrorMessage = 'Unable to save default schedule settings right now.';
            }
        }
    } elseif ($postAction === 'accept') {
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
    } elseif ($postAction === 'mark_completed') {
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

$appointmentDefaultTimeFromLabel = vs_appointments_format_time_label($appointmentDefaultTimeFrom);
$appointmentDefaultTimeToLabel = vs_appointments_format_time_label($appointmentDefaultTimeTo);
$appointmentSameDayCutoffLabel = vs_appointments_format_time_label($appointmentSameDayCutoffTime);
$reopenScheduleSettingsModal = $appointmentSettingsErrorMessage !== '';

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

// Failed appointments = unresolved pending payment rows + appointment rows explicitly marked failed.
$stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM pending_payments p
        LEFT JOIN service_requests s ON s.razorpay_order_id = p.razorpay_order_id
        WHERE p.category = 'appointment'
          AND s.id IS NULL
");
$stmt->execute();
$failedPendingAppointmentsCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM service_requests
        WHERE category_slug = 'appointment'
          AND LOWER(TRIM(COALESCE(payment_status, ''))) = 'failed'
");
$stmt->execute();
$failedServiceAppointmentsCount = (int)$stmt->fetchColumn();

$failedAppointmentsCount = $failedPendingAppointmentsCount + $failedServiceAppointmentsCount;

$stmt = $pdo->prepare("
        SELECT id, tracking_id, customer_name, mobile, email, payment_status, service_status, form_data, created_at
        FROM service_requests
        WHERE category_slug = 'appointment'
          AND LOWER(TRIM(COALESCE(payment_status, ''))) = 'failed'
        ORDER BY created_at DESC, id DESC
        LIMIT 5
");
$stmt->execute();
$recentFailedAppointmentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
.page-title {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.page-title h1 {
    margin-bottom: 6px;
}
.page-title-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.current-default-note {
    color: #6b4a4a;
    font-size: 0.95em;
    font-weight: 600;
}
.icon-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: 1px solid #d8c0c0;
    background: #fff;
    color: #800000;
    cursor: pointer;
    box-shadow: 0 2px 12px rgba(128, 0, 0, 0.08);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.icon-action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(128, 0, 0, 0.16);
}
.notice-banner {
    margin-bottom: 14px;
    padding: 12px 14px;
    border-radius: 10px;
    font-weight: 600;
}
.notice-success {
    background: #e8f8ec;
    border: 1px solid #b9e0c1;
    color: #1f6f2c;
}
.notice-error {
    background: #fff1f1;
    border: 1px solid #e9b7b7;
    color: #9f2525;
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
.payment-failed { background: #ffeaea; color: #c00; }
.status-cancelled { background: #ffeaea; color: #c00; }
.status-default { background: #f2f2f2; color: #666; }
.summary-card-link {
    padding: 0;
    overflow: hidden;
}
.summary-card-anchor {
    display: block;
    padding: 14px 18px;
    color: inherit;
    text-decoration: none;
}
.summary-card-alert {
    background: #fff2f2;
    border: 1px solid #f0c7c7;
}
.summary-card-hint {
    margin-top: 6px;
    color: #8f5555;
    font-size: 0.84em;
    line-height: 1.4;
}
.failed-preview {
    background: #fff;
    border: 1px solid #f0d7d7;
    border-radius: 14px;
    box-shadow: 0 2px 12px #e0bebe22;
    margin: 22px 0 28px;
    padding: 18px;
}
.failed-preview-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}
.failed-preview-title {
    margin: 0;
    color: #800000;
    font-size: 1.15em;
}
.failed-preview-subtitle {
    margin: 6px 0 0;
    color: #6b4a4a;
    line-height: 1.5;
}
.failed-preview-empty {
    margin: 0;
    color: #6b4a4a;
    line-height: 1.6;
}
.view-btn {
    background: #800000;
    color: #fff;
    padding: 8px 14px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.92em;
    transition: background 0.2s ease;
}
.view-btn:hover {
    background: #a00000;
}
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
.settings-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
}
.settings-help-text {
    margin: 0 0 16px;
    color: #6b4a4a;
    line-height: 1.5;
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
/* PHASE 4 - Dropdown Styling */
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
<div class="page-title">
    <div>
        <h1>Appointment Management</h1>
        <div class="current-default-note">
            Accept popup default time: <?= htmlspecialchars($appointmentDefaultTimeFromLabel) ?> to <?= htmlspecialchars($appointmentDefaultTimeToLabel) ?> | Same-day public booking cutoff: <?= htmlspecialchars($appointmentSameDayCutoffLabel) ?> IST
        </div>
    </div>
    <div class="page-title-actions">
        <button id="refreshRollbackBtn" title="Run Auto Rollback" style="background:none;border:none;cursor:pointer;padding:0;display:inline-flex;align-items:center;gap:6px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24"><path fill="#007bff" d="M12 6V3l-5 5 5 5V8c3.31 0 6 2.69 6 6 0 1.3-.42 2.5-1.13 3.47l1.46 1.46A7.938 7.938 0 0020 14c0-4.42-3.58-8-8-8zm-6.87 2.53L3.67 7.07A7.938 7.938 0 004 10c0 4.42 3.58 8 8 8v3l5-5-5-5v3c-3.31 0-6-2.69-6-6 0-1.3.42-2.5 1.13-3.47z"/></svg>
            <span style="font-size:0.9em;color:#007bff;vertical-align:middle;">Refresh</span>
        </button>
        <button
            type="button"
            class="icon-action-btn"
            onclick="openScheduleSettingsModal()"
            title="Default schedule settings"
            aria-label="Open default schedule settings"
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="3.2" stroke="currentColor" stroke-width="1.8"/>
                <path d="M12 2.75v2.1M12 19.15v2.1M21.25 12h-2.1M4.85 12H2.75M18.54 5.46l-1.48 1.48M6.94 17.06l-1.48 1.48M18.54 18.54l-1.48-1.48M6.94 6.94L5.46 5.46" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
        </button>
    </div>
</div>
<div id="rollbackMsg" style="margin-bottom:12px;font-weight:600;color:#007bff;"></div>
<?php if ($appointmentSettingsSaved): ?>
    <div class="notice-banner notice-success">
        Default appointment schedule settings saved. New accept popups will open with <?= htmlspecialchars($appointmentDefaultTimeFromLabel) ?> to <?= htmlspecialchars($appointmentDefaultTimeToLabel) ?>, and same-day public booking will stay open until <?= htmlspecialchars($appointmentSameDayCutoffLabel) ?> IST.
    </div>
<?php endif; ?>
<?php if ($appointmentSettingsErrorMessage !== ''): ?>
    <div class="notice-banner notice-error">
        <?= htmlspecialchars($appointmentSettingsErrorMessage) ?>
    </div>
<?php endif; ?>
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
        <div class="summary-label">Today's Appointments</div>
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
    <div class="summary-card summary-card-link<?= $failedAppointmentsCount > 0 ? ' summary-card-alert' : '' ?>">
        <a href="failed-appointments.php" class="summary-card-anchor">
            <div class="summary-count"><?= $failedAppointmentsCount ?></div>
            <div class="summary-label">Failed</div>
            <div class="summary-card-hint">
                <?= $failedServiceAppointmentsCount ?> failed booking row<?= $failedServiceAppointmentsCount === 1 ? '' : 's' ?>, <?= $failedPendingAppointmentsCount ?> unresolved payment row<?= $failedPendingAppointmentsCount === 1 ? '' : 's' ?>
            </div>
        </a>
    </div>
</div>

<?php if ($failedAppointmentsCount > 0): ?>
    <section class="failed-preview">
        <div class="failed-preview-header">
            <div>
                <h2 class="failed-preview-title">Failed Appointment Visibility</h2>
                <p class="failed-preview-subtitle">
                    This queue now tracks both unresolved online payment rows and appointment bookings already created in <code>service_requests</code> but marked failed later.
                </p>
            </div>
            <a href="failed-appointments.php" class="view-btn">Open Failed Queue</a>
        </div>
        <?php if (empty($recentFailedAppointmentRows)): ?>
            <p class="failed-preview-empty">
                No appointment booking rows are currently marked failed. The failed queue still includes <?= $failedPendingAppointmentsCount ?> unresolved online payment row<?= $failedPendingAppointmentsCount === 1 ? '' : 's' ?>.
            </p>
        <?php else: ?>
            <table class="service-table">
                <thead>
                    <tr>
                        <th>View</th>
                        <th>Tracking ID</th>
                        <th>Customer Name</th>
                        <th>Mobile</th>
                        <th>Preferred Date</th>
                        <th>Payment Status</th>
                        <th>Service Status</th>
                        <th>Created Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentFailedAppointmentRows as $failedAppointment): ?>
                        <?php
                            $failedFormData = json_decode($failedAppointment['form_data'] ?? '', true) ?? [];
                            $preferredDateRaw = (string)($failedFormData['preferred_date'] ?? '');
                            if ($preferredDateRaw !== '') {
                                $preferredDateObj = DateTime::createFromFormat('Y-m-d', $preferredDateRaw);
                                $preferredDateDisplay = $preferredDateObj ? $preferredDateObj->format('d-M-Y') : $preferredDateRaw;
                            } else {
                                $preferredDateDisplay = '--';
                            }
                            $createdAtDisplay = '';
                            if (!empty($failedAppointment['created_at'])) {
                                $createdAtObject = new DateTime((string)$failedAppointment['created_at']);
                                $createdAtDisplay = $createdAtObject->format('d-M-Y h:i A');
                            }
                            $serviceStatusRaw = trim((string)($failedAppointment['service_status'] ?? ''));
                            $serviceStatusClass = $serviceStatusRaw !== '' ? 'status-' . strtolower(str_replace(' ', '-', $serviceStatusRaw)) : 'status-default';
                            $serviceStatusText = $serviceStatusRaw !== '' ? $serviceStatusRaw : '-';
                        ?>
                        <tr>
                            <td>
                                <a href="view.php?id=<?= (int)$failedAppointment['id'] ?>" class="view-btn">View</a>
                            </td>
                            <td><?= htmlspecialchars((string)($failedAppointment['tracking_id'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string)($failedAppointment['customer_name'] ?? '')) ?></td>
                            <td><?= htmlspecialchars(vs_format_mobile_from_form_data((string)($failedAppointment['mobile'] ?? ''), $failedFormData)) ?></td>
                            <td><?= htmlspecialchars($preferredDateDisplay) ?></td>
                            <td><span class="status-badge payment-failed">Failed</span></td>
                            <td><span class="status-badge <?= htmlspecialchars($serviceStatusClass) ?>"><?= htmlspecialchars($serviceStatusText) ?></span></td>
                            <td><?= htmlspecialchars($createdAtDisplay) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
<?php endif; ?>

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
            <?= $formattedDate ?> - <?= count($appointments) ?> Appointment<?= count($appointments) !== 1 ? 's' : '' ?>
        </h2>
        <div class="action-bar" id="actionBar-<?= htmlspecialchars($date) ?>">
            <span class="action-bar-label"><span id="selectedCount-<?= htmlspecialchars($date) ?>">0</span> appointment(s) selected</span>
            <button class="action-btn btn-accept" onclick="openAcceptModal('<?= htmlspecialchars($date) ?>')">Accept Selected</button>
            <button class="action-btn btn-completed" onclick="submitMarkCompleted('<?= htmlspecialchars($date) ?>')" style="background:#007bff;color:#fff;">Mark Completed</button>
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
                        if ($preferredDate) {
                            $preferredDateObj = DateTime::createFromFormat('Y-m-d', $preferredDate);
                            $preferredDisplay = $preferredDateObj ? $preferredDateObj->format('d-M-Y') : $preferredDate;
                        } else {
                            $preferredDisplay = '--';
                        }
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
                                            $label .= ' (&#8377;' . number_format((float)$price, 2) . ')';
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
                        <td>
                            <a href="../cif/index.php?prefill_name=<?= rawurlencode($a['customer_name']) ?>" style="color:#0056b3;text-decoration:underline;">
                                <?= htmlspecialchars($a['customer_name']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars(vs_format_mobile_from_form_data($a['mobile'] ?? '', $a['form_data'] ?? null)) ?></td>
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

<div class="modal" id="scheduleSettingsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Default Schedule Settings</h2>
            <button class="modal-close" type="button" onclick="closeScheduleSettingsModal()">&times;</button>
        </div>
        <form method="POST" onsubmit="return validateScheduleSettingsForm()">
            <input type="hidden" name="action" value="save_schedule_defaults">
            <p class="settings-help-text">
                These settings control the default Accept Appointment popup time range and the same-day cutoff used on the public appointment booking form.
            </p>
            <div class="settings-form-grid">
                <div class="form-group">
                    <label for="defaultTimeFrom">Default Time From *</label>
                    <input type="time" id="defaultTimeFrom" name="default_time_from" value="<?= htmlspecialchars($appointmentDefaultTimeFrom) ?>" required>
                </div>
                <div class="form-group">
                    <label for="defaultTimeTo">Default Time To *</label>
                    <input type="time" id="defaultTimeTo" name="default_time_to" value="<?= htmlspecialchars($appointmentDefaultTimeTo) ?>" required>
                </div>
                <div class="form-group">
                    <label for="sameDayBookingCutoffTime">Same-Day Booking Cutoff Time *</label>
                    <input type="time" id="sameDayBookingCutoffTime" name="same_day_booking_cutoff_time" value="<?= htmlspecialchars($appointmentSameDayCutoffTime) ?>" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeScheduleSettingsModal()">Cancel</button>
                <button type="submit" class="btn-submit">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<!-- PHASE 3 - ACCEPT APPOINTMENT MODAL -->
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
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="date" id="assignedDate" name="assigned_date" required min="<?= date('Y-m-d') ?>">
                        <button type="button" id="setTodayBtn" style="padding:6px 10px;font-size:0.95em;border-radius:6px;border:1px solid #ccc;background:#f3f3f3;cursor:pointer;">Today</button>
                    </div>
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
const appointmentDefaultTimeFrom = <?= json_encode($appointmentDefaultTimeFrom, JSON_UNESCAPED_SLASHES) ?>;
const appointmentDefaultTimeTo = <?= json_encode($appointmentDefaultTimeTo, JSON_UNESCAPED_SLASHES) ?>;
const reopenScheduleSettingsModal = <?= $reopenScheduleSettingsModal ? 'true' : 'false' ?>;

let selectedAppointmentsByDate = {};
let activeAcceptModalDate = null;

// Global search filter for all tables
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('globalSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const val = this.value.trim().toLowerCase();
            document.querySelectorAll('.service-table tbody tr').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = val === '' || text.includes(val) ? '' : 'none';
            });
        });
    }

    if (reopenScheduleSettingsModal) {
        openScheduleSettingsModal();
    }

    const setTodayBtn = document.getElementById('setTodayBtn');
    if (setTodayBtn) {
        setTodayBtn.onclick = function() {
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            document.getElementById('assignedDate').value = `${yyyy}-${mm}-${dd}`;
        };
    }

    setTimeout(function() {
        document.querySelectorAll('.selectAll').forEach(selectAll => {
            const date = selectAll.getAttribute('data-date');
            selectAll.addEventListener('change', function() {
                document.querySelectorAll('.rowCheckbox[data-date="' + date + '"]').forEach(cb => {
                    cb.checked = selectAll.checked;
                });
                updateActionBar(date);
            });
        });

        document.querySelectorAll('.rowCheckbox').forEach(checkbox => {
            const date = checkbox.getAttribute('data-date');
            checkbox.addEventListener('change', function() {
                updateActionBar(date);
            });
        });
    }, 100);
});

document.addEventListener('click', function(event) {
    if (event.target.id === 'acceptModal') {
        closeAcceptModal();
    }
    if (event.target.id === 'scheduleSettingsModal') {
        closeScheduleSettingsModal();
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeAcceptModal();
        closeScheduleSettingsModal();
    }
});

function openScheduleSettingsModal() {
    document.getElementById('scheduleSettingsModal').classList.add('show');
}

function closeScheduleSettingsModal() {
    document.getElementById('scheduleSettingsModal').classList.remove('show');
}

function validateScheduleSettingsForm() {
    const defaultTimeFrom = document.getElementById('defaultTimeFrom').value;
    const defaultTimeTo = document.getElementById('defaultTimeTo').value;
    const sameDayBookingCutoffTime = document.getElementById('sameDayBookingCutoffTime').value;

    if (!defaultTimeFrom || !defaultTimeTo || !sameDayBookingCutoffTime || defaultTimeFrom >= defaultTimeTo) {
        alert('Please choose a valid default schedule and cutoff time.');
        return false;
    }

    return true;
}

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
    activeAcceptModalDate = date;
    // Set appointment IDs
    const ids = selectedAppointmentsByDate[date].map(a => a.id);
    document.getElementById('acceptAppointmentIds').value = ids.join(',');
    // Set assigned date to preferred date
    document.getElementById('assignedDate').value = date;
    // Set configured default time range
    document.getElementById('timeFrom').value = appointmentDefaultTimeFrom;
    document.getElementById('timeTo').value = appointmentDefaultTimeTo;
    document.getElementById('acceptModal').classList.add('show');
}

function closeAcceptModal() {
    activeAcceptModalDate = null;
    document.getElementById('acceptModal').classList.remove('show');
}

function validateAcceptForm() {
    const timeFrom = document.getElementById('timeFrom').value;
    const timeTo = document.getElementById('timeTo').value;
    if (timeFrom >= timeTo) {
        alert('Time From must be before Time To');
        return false;
    }

    const selectedDate = activeAcceptModalDate;
    if (!selectedDate || !selectedAppointmentsByDate[selectedDate] || selectedAppointmentsByDate[selectedDate].length === 0) {
        return false;
    }

    const checked = document.querySelectorAll('.rowCheckbox[data-date="' + selectedDate + '"]:checked').length;
    if (!confirm(`Accept ${checked} appointment(s)?`)) {
        return false;
    }

    const form = document.querySelector('#acceptModal form');
    form.querySelectorAll('input[name="appointment_ids[]"]').forEach(input => input.remove());
    selectedAppointmentsByDate[selectedDate].forEach(a => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'appointment_ids[]';
        input.value = a.id;
        form.appendChild(input);
    });

    return true;
}

function submitMarkCompleted(date) {
    if (!selectedAppointmentsByDate[date] || selectedAppointmentsByDate[date].length === 0) return;

    const ids = selectedAppointmentsByDate[date].map(a => a.id);
    if (!confirm(`Mark ${ids.length} appointment(s) as completed?`)) {
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    form.innerHTML = '<input type="hidden" name="action" value="mark_completed">';
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



</body>
</html>




