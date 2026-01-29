<?php
// Endpoint: /ajax/get_booking_status.php
// Returns payment_status and booking_status for a given appointment (by id or razorpay_order_id)

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? trim($_GET['id']) : '';
$razorpay_order_id = isset($_GET['razorpay_order_id']) ? trim($_GET['razorpay_order_id']) : '';

$response = [
    'success' => false,
    'payment_status' => null,
    'booking_status' => null,
    'error' => null
];

try {
    if ($razorpay_order_id !== '') {
        $stmt = $pdo->prepare('SELECT payment_status, booking_status FROM service_requests WHERE razorpay_order_id = ? LIMIT 1');
        $stmt->execute([$razorpay_order_id]);
    } elseif ($id !== '') {
        $stmt = $pdo->prepare('SELECT payment_status, booking_status FROM service_requests WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
    } else {
        $response['error'] = 'Missing id or razorpay_order_id';
        echo json_encode($response);
        exit;
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $response['success'] = true;
        $response['payment_status'] = $row['payment_status'];
        $response['booking_status'] = $row['booking_status'];
    } else {
        $response['error'] = 'Not found';
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
