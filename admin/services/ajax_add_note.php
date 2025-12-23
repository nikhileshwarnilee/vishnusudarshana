<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$serviceRequestId = $_POST['service_request_id'] ?? '';
$noteText = trim($_POST['note_text'] ?? '');

// Validate
if (!$serviceRequestId || !is_numeric($serviceRequestId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid service request ID']);
    exit;
}

if ($noteText === '') {
    echo json_encode(['success' => false, 'message' => 'Note text cannot be empty']);
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO admin_notes (service_request_id, note_text, created_at) VALUES (?, ?, NOW())');
    $stmt->execute([$serviceRequestId, $noteText]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Failed to save admin note: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to save note']);
}
