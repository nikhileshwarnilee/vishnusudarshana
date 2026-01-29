<?php
// Endpoint: /ajax/check_payment_status.php
// Returns payment_status for a given payment_id (from service_requests table)

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$payment_id = isset($_GET['payment_id']) ? trim($_GET['payment_id']) : '';
$response = [
    'success' => false,
    'payment_status' => null,
    'error' => null
];

if ($payment_id === '') {
    $response['error'] = 'Missing payment_id';
    echo json_encode($response);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT payment_status FROM service_requests WHERE payment_id = ? LIMIT 1');
    $stmt->execute([$payment_id]);
    $status = $stmt->fetchColumn();
    if ($status !== false) {
        $response['success'] = true;
        $response['payment_status'] = $status;
    } else {
        $response['error'] = 'Not found';
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
