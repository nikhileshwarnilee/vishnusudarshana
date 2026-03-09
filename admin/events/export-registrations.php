<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/event_module.php';

vs_event_ensure_tables($pdo);

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$paymentStatus = trim((string)($_GET['payment_status'] ?? ''));
$verificationStatus = trim((string)($_GET['verification_status'] ?? ''));
$fromDate = trim((string)($_GET['from_date'] ?? ''));
$toDate = trim((string)($_GET['to_date'] ?? ''));

$where = [];
$params = [];
if ($eventId > 0) {
    $where[] = 'r.event_id = ?';
    $params[] = $eventId;
}
if ($paymentStatus !== '') {
    $where[] = 'r.payment_status = ?';
    $params[] = $paymentStatus;
}
if ($verificationStatus !== '') {
    $where[] = 'r.verification_status = ?';
    $params[] = $verificationStatus;
}
if ($fromDate !== '') {
    $where[] = 'DATE(r.created_at) >= ?';
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $where[] = 'DATE(r.created_at) <= ?';
    $params[] = $toDate;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT
    r.id,
    r.event_id,
    r.event_date_id,
    r.booking_reference,
    e.title AS event_title,
    e.event_type,
    COALESCE(d.event_date, e.event_date) AS selected_event_date,
    p.package_name,
    r.name,
    r.phone,
    r.persons,
    r.payment_status,
    r.verification_status,
    ep.payment_method,
    ep.transaction_id,
    ep.status AS payment_record_status,
    ep.amount AS payment_amount,
    r.created_at
FROM event_registrations r
INNER JOIN events e ON e.id = r.event_id
LEFT JOIN event_dates d ON d.id = r.event_date_id
INNER JOIN event_packages p ON p.id = r.package_id
LEFT JOIN event_payments ep ON ep.registration_id = r.id
$whereSql
ORDER BY r.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fieldStmt = $pdo->prepare('SELECT field_name, value FROM event_registration_data WHERE registration_id = ? ORDER BY id ASC');

$filename = 'event_registrations_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

$headers = [
    'Registration ID',
    'Booking Reference',
    'Event',
    'Event Date',
    'Package',
    'Name',
    'Phone',
    'Persons',
    'Payment Status',
    'Verification Status',
    'Payment Method',
    'Transaction ID',
    'Payment Record Status',
    'Payment Amount',
    'Created At',
    'Dynamic Form Data',
];
fputcsv($out, $headers, "\t");

foreach ($rows as $row) {
    $bookingRef = trim((string)($row['booking_reference'] ?? ''));
    if ($bookingRef === '') {
        $bookingRef = vs_event_assign_booking_reference($pdo, (int)$row['id']);
    }
    $eventDateDisplay = vs_event_get_registration_date_display(
        $pdo,
        $row,
        (string)($row['selected_event_date'] ?? '')
    );

    $fieldStmt->execute([(int)$row['id']]);
    $fields = $fieldStmt->fetchAll(PDO::FETCH_ASSOC);
    $fieldParts = [];
    foreach ($fields as $field) {
        $name = (string)$field['field_name'];
        if (strpos($name, '__event_reminder_') === 0) {
            continue;
        }
        $fieldParts[] = $name . ': ' . str_replace(["\r", "\n"], ' ', (string)$field['value']);
    }

    $line = [
        (int)$row['id'],
        $bookingRef,
        (string)$row['event_title'],
        $eventDateDisplay,
        (string)$row['package_name'],
        (string)$row['name'],
        (string)$row['phone'],
        (int)$row['persons'],
        (string)$row['payment_status'],
        (string)$row['verification_status'],
        (string)$row['payment_method'],
        (string)$row['transaction_id'],
        (string)$row['payment_record_status'],
        (string)$row['payment_amount'],
        (string)$row['created_at'],
        implode(' | ', $fieldParts),
    ];

    fputcsv($out, $line, "\t");
}

fclose($out);
exit;
