<?php
// Razorpay webhook handler

/*
Razorpay webhook JSON structure for payment.captured event:

{
  "event": "payment.captured",
  "payload": {
    "payment": {
      "entity": {
        "id": "pay_xxxxxxxx",           // Unique payment ID
        "order_id": "order_xxxxxxxx",   // Associated order ID
        "status": "captured",           // Payment status
        // ... other payment fields ...
      }
    }
  }
  // ... other fields ...
}

// To access these values in PHP:
// $data['event']
// $data['payload']['payment']['entity']['id']
// $data['payload']['payment']['entity']['order_id']
// $data['payload']['payment']['entity']['status']
*/

require_once __DIR__ . '/../config/db.php';

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

$secret = 'YOUR_WEBHOOK_SECRET_HERE';

// Verify signature
$expectedSignature = hash_hmac('sha256', $payload, $secret);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(400);
    exit('Invalid signature');
}

$data = json_decode($payload, true);

if (!$data || !isset($data['event'])) {
    http_response_code(400);
    exit('Invalid payload');
}

// Safely extract Razorpay payment details from webhook payload
// Assumes $data contains decoded webhook JSON

$razorpayPaymentId = isset($data['payload']['payment']['entity']['id'])
    ? $data['payload']['payment']['entity']['id']
    : null; // Payment ID or null if missing

$razorpayOrderId = isset($data['payload']['payment']['entity']['order_id'])
    ? $data['payload']['payment']['entity']['order_id']
    : null; // Order ID or null if missing

$paymentStatus = isset($data['payload']['payment']['entity']['status'])
    ? $data['payload']['payment']['entity']['status']
    : null; // Payment status or null if missing

// Log received event type
$eventType = isset($data['event']) ? $data['event'] : 'unknown';
error_log('Razorpay webhook received event: ' . $eventType);

// Handle events
switch ($data['event']) {

    case 'payment.captured':
        try {
            // Safely extract payment details
            $razorpayPaymentId = isset($data['payload']['payment']['entity']['id'])
                ? $data['payload']['payment']['entity']['id']
                : null;
            $razorpayOrderId = isset($data['payload']['payment']['entity']['order_id'])
                ? $data['payload']['payment']['entity']['order_id']
                : null;
            $paymentStatus = isset($data['payload']['payment']['entity']['status'])
                ? $data['payload']['payment']['entity']['status']
                : null;

            error_log('Razorpay webhook order_id: ' . ($razorpayOrderId ?: 'not set'));

            if ($razorpayOrderId && $razorpayPaymentId) {
                // Find appointment/service request by razorpay_order_id
                $stmt = $pdo->prepare("SELECT payment_status, booking_status FROM service_requests WHERE razorpay_order_id = ? LIMIT 1");
                $stmt->execute([$razorpayOrderId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                // Only update if not already paid/confirmed
                if ($row && strtolower($row['payment_status']) !== 'paid' && strtolower($row['booking_status']) !== 'confirmed') {
                    $updateStmt = $pdo->prepare(
                        "UPDATE service_requests SET payment_status = ?, booking_status = ?, razorpay_payment_id = ? WHERE razorpay_order_id = ?"
                    );
                    $updateStmt->execute([
                        'paid',
                        'confirmed',
                        $razorpayPaymentId,
                        $razorpayOrderId
                    ]);
                    error_log('Razorpay webhook: payment confirmation success for order_id ' . $razorpayOrderId);
                } else if ($row && strtolower($row['payment_status']) === 'paid' && strtolower($row['booking_status']) === 'confirmed') {
                    error_log('Razorpay webhook: already paid and confirmed for order_id ' . $razorpayOrderId);
                } else if ($row && strtolower($row['payment_status']) === 'paid') {
                    error_log('Razorpay webhook: already paid for order_id ' . $razorpayOrderId);
                } else if ($row && strtolower($row['booking_status']) === 'confirmed') {
                    error_log('Razorpay webhook: already confirmed for order_id ' . $razorpayOrderId);
                } else {
                    error_log('Razorpay webhook: appointment/service request not found for order_id ' . $razorpayOrderId);
                }
            } else {
                error_log('Razorpay webhook: missing order_id or payment_id');
            }
        } catch (Exception $e) {
            error_log('Razorpay webhook payment.captured error: ' . $e->getMessage());
            http_response_code(500);
            exit;
        }
        break;

    case 'payment.failed':
        try {
            $razorpayOrderId = isset($data['payload']['payment']['entity']['order_id'])
                ? $data['payload']['payment']['entity']['order_id']
                : null;
            error_log('Razorpay webhook order_id: ' . ($razorpayOrderId ?: 'not set'));
            if ($razorpayOrderId) {
                $stmt = $pdo->prepare("SELECT payment_status FROM service_requests WHERE razorpay_order_id = ? LIMIT 1");
                $stmt->execute([$razorpayOrderId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $updateStmt = $pdo->prepare(
                        "UPDATE service_requests SET payment_status = ?, booking_status = ? WHERE razorpay_order_id = ?"
                    );
                    $updateStmt->execute([
                        'failed',
                        'cancelled',
                        $razorpayOrderId
                    ]);
                    error_log('Razorpay webhook: payment failed/cancelled for order_id ' . $razorpayOrderId);
                } else {
                    error_log('Razorpay webhook: appointment/service request not found for order_id ' . $razorpayOrderId);
                }
            } else {
                error_log('Razorpay webhook: missing order_id');
            }
        } catch (Exception $e) {
            error_log('Razorpay webhook payment.failed error: ' . $e->getMessage());
            http_response_code(500);
            exit;
        }
        break;
}

http_response_code(200);
echo 'OK';

// Example usage:
// if ($razorpayPaymentId && $razorpayOrderId && $paymentStatus) {
//     // All values present, proceed with business logic
// }

// Update appointment record after successful Razorpay payment
if ($razorpayOrderId && $razorpayPaymentId) {
    // 1. Find appointment by razorpay_order_id
    $stmt = $pdo->prepare("SELECT * FROM service_requests WHERE razorpay_order_id = ? LIMIT 1");
    $stmt->execute([$razorpayOrderId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($appointment) {
        // 2. If found, update payment_status, booking_status, and razorpay_payment_id
        $updateStmt = $pdo->prepare(
            "UPDATE service_requests SET payment_status = ?, booking_status = ?, razorpay_payment_id = ? WHERE razorpay_order_id = ?"
        );
        $updateStmt->execute([
            'paid',
            'confirmed',
            $razorpayPaymentId,
            $razorpayOrderId
        ]);
        // Optionally, you can log or handle post-update actions here
    }
    // else: appointment not found, handle as needed
}
