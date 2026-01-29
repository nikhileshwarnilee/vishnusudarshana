<?php
/**
 * payment-update.php
 * Maps order_id (temporary) to actual Razorpay payment_id
 * Allows payment-success.php to find payment data even if payment_id changes
 */

require_once __DIR__ . '/config/db.php';

$order_id = $_POST['order_id'] ?? '';
$payment_id = $_POST['payment_id'] ?? '';

if ($order_id && $payment_id) {
    try {
        // Update pending_payments record: link order_id to actual Razorpay payment_id
        $stmt = $pdo->prepare("
            UPDATE pending_payments 
            SET payment_id = ? 
            WHERE razorpay_order_id = ?
        ");
        $stmt->execute([$payment_id, $order_id]);
    } catch (Throwable $e) {
        error_log('Failed to update pending payment: ' . $e->getMessage());
    }
}

http_response_code(200);
exit;
?>
<style>@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');html,body{font-family:'Marcellus',serif!important;}</style>
