<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/event_module.php';

vs_event_ensure_tables($pdo);

$registrationId = isset($_GET['registration_id']) ? (int)$_GET['registration_id'] : 0;
$bookingReference = trim((string)($_GET['ref'] ?? ''));

if ($registrationId <= 0 || $bookingReference === '') {
    http_response_code(404);
    exit;
}

$stmt = $pdo->prepare("SELECT id, booking_reference, payment_status, qr_code_path
    FROM event_registrations
    WHERE id = ?
    LIMIT 1");
$stmt->execute([$registrationId]);
$registration = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registration) {
    http_response_code(404);
    exit;
}

$dbReference = trim((string)($registration['booking_reference'] ?? ''));
if ($dbReference === '' || !hash_equals($dbReference, $bookingReference)) {
    http_response_code(403);
    exit;
}

if (strtolower((string)$registration['payment_status']) !== 'paid') {
    http_response_code(403);
    exit;
}

$qrPath = trim((string)($registration['qr_code_path'] ?? ''));
if ($qrPath === '') {
    $qrPath = vs_event_ensure_registration_qr($pdo, $registrationId);
}

if ($qrPath === '') {
    http_response_code(404);
    exit;
}

$baseDir = realpath(__DIR__ . '/uploads/events/qrcodes');
$fullPath = realpath(__DIR__ . '/' . ltrim($qrPath, '/'));
if ($baseDir === false || $fullPath === false || strpos($fullPath, $baseDir) !== 0 || !is_file($fullPath)) {
    http_response_code(403);
    exit;
}

header('Content-Type: image/png');
header('Content-Length: ' . (string)filesize($fullPath));
header('Cache-Control: private, max-age=300');
readfile($fullPath);
exit;
