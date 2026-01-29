<?php
// Endpoint: /ajax/manual_verify_razorpay.php
// Manually verify Razorpay payment and update appointment if captured

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/razorpay/Razorpay.php';

use Razorpay\Api\Api;

header('Content-Type: application/json');

$razorpay_order_id = isset($_POST['razorpay_order_id']) ? trim($_POST['razorpay_order_id']) : '';
$response = [
    'success' => false,
    'message' => '',
    'payment_id' => null,
    'payment_status' => null
];

if ($razorpay_order_id === '') {
    $response['message'] = 'Missing razorpay_order_id';
    echo json_encode($response);
    exit;
}

// Set your Razorpay API credentials
$key_id = 'rzp_test_a3iYwPnLkGMlDM';
$key_secret = 'YOUR_KEY_SECRET'; // TODO: Set your actual Razorpay key secret

try {
    $api = new Api($key_id, $key_secret);
    // Fetch all payments for this order
    $payments = $api->order->fetch($razorpay_order_id)->payments();
    $capturedPayment = null;
    foreach ($payments['items'] as $payment) {
        if ($payment['status'] === 'captured') {
            $capturedPayment = $payment;
            break;
        }
    }
    if ($capturedPayment) {
        $razorpay_payment_id = $capturedPayment['id'];
        // Update appointment if not already paid
        $stmt = $pdo->prepare("SELECT payment_status FROM service_requests WHERE razorpay_order_id = ? LIMIT 1");
        $stmt->execute([$razorpay_order_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['payment_status'] !== 'paid') {
            $updateStmt = $pdo->prepare("UPDATE service_requests SET payment_status = ?, booking_status = ?, razorpay_payment_id = ? WHERE razorpay_order_id = ?");
            $updateStmt->execute(['paid', 'confirmed', $razorpay_payment_id, $razorpay_order_id]);
        }
        $response['success'] = true;
        $response['message'] = 'Payment captured and appointment updated.';
        $response['payment_id'] = $razorpay_payment_id;
        $response['payment_status'] = 'paid';
    } else {
        $response['message'] = 'No captured payment found for this order.';
    }
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
