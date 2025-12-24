<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/send_whatsapp.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$serviceRequestId = $_POST['service_request_id'] ?? '';
$noteText = trim($_POST['note_text'] ?? '');

if (!$serviceRequestId || !is_numeric($serviceRequestId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid service request ID']);
    exit;
}

if ($noteText === '') {
    echo json_encode(['success' => false, 'message' => 'Note text cannot be empty']);
    exit;
}

// Fetch customer details
$stmt = $pdo->prepare('SELECT customer_name, mobile, tracking_id, category_slug FROM service_requests WHERE id = ?');
$stmt->execute([$serviceRequestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$request) {
    echo json_encode(['success' => false, 'message' => 'Service request not found']);
    exit;
}

$customerName = $request['customer_name'];
$mobile = $request['mobile'];
$trackingId = $request['tracking_id'];
$categorySlug = $request['category_slug'];

// Prepare WhatsApp message
$categoryTitles = [
    'birth-child' => 'Birth & Child Services',
    'marriage-matching' => 'Marriage & Matching',
    'astrology-consultation' => 'Astrology Consultation',
    'muhurat-event' => 'Muhurat & Event Guidance',
    'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
];
$category = $categoryTitles[$categorySlug] ?? ucfirst(str_replace('-', ' ', $categorySlug));
$trackingLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
    "://" . $_SERVER['HTTP_HOST'] . dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))) . "/track.php?tracking_id=" . urlencode($trackingId);

// Compose message (customize as needed)
$templateName = 'admin_note_notification'; // You must have this template in WhatsApp Cloud API
$language = 'en';
$variables = [
    'name' => $customerName,
    'tracking_id' => $trackingId,
    'category' => $category,
    'tracking_link' => $trackingLink,
    'note' => $noteText
];

$success = sendWhatsAppMessage($mobile, $templateName, $language, $variables);

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Notification sent successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send WhatsApp notification.']);
}
