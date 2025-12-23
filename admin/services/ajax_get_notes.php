<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$serviceRequestId = $_GET['service_request_id'] ?? '';

if (!$serviceRequestId || !is_numeric($serviceRequestId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid service request ID']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT note_text, created_at FROM admin_notes WHERE service_request_id = ? ORDER BY created_at DESC');
    $stmt->execute([$serviceRequestId]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'notes' => $notes]);
} catch (Exception $e) {
    error_log('Failed to fetch admin notes: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch notes']);
}
