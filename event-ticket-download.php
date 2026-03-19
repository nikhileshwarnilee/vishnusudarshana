<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/event_module.php';

vs_event_ensure_tables($pdo);

$registrationId = isset($_GET['registration_id']) ? (int)$_GET['registration_id'] : 0;
$bookingReference = trim((string)($_GET['ref'] ?? ''));
$mode = strtolower(trim((string)($_GET['mode'] ?? 'download')));
$isMediaRequest = ($mode === 'media');

if ($registrationId <= 0 || $bookingReference === '') {
    http_response_code(404);
    exit;
}

$stmt = $pdo->prepare("SELECT id, booking_reference, payment_status
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

if (strtolower((string)($registration['payment_status'] ?? '')) !== 'paid') {
    http_response_code(403);
    exit;
}

$ticketPath = vs_event_ensure_registration_ticket_pdf($pdo, $registrationId, [
    'booking_reference' => $dbReference,
]);
if ($ticketPath === '') {
    http_response_code(404);
    exit;
}

$baseDir = realpath(__DIR__ . '/uploads/events/tickets');
$fullPath = realpath(__DIR__ . '/' . ltrim($ticketPath, '/'));
if ($baseDir === false || $fullPath === false || strpos($fullPath, $baseDir) !== 0 || !is_file($fullPath)) {
    http_response_code(403);
    exit;
}

$safeReference = preg_replace('/[^A-Za-z0-9_-]/', '-', $dbReference);
if (!is_string($safeReference) || $safeReference === '') {
    $safeReference = 'event-ticket';
}

header('Content-Type: application/pdf');
header('Content-Length: ' . (string)filesize($fullPath));
header('Content-Disposition: ' . ($isMediaRequest ? 'inline' : 'attachment') . '; filename="' . $safeReference . '.pdf"');
header('Cache-Control: ' . ($isMediaRequest ? 'public' : 'private') . ', max-age=300');
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: bytes');
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD') {
    exit;
}
readfile($fullPath);
exit;
