<?php
/**
 * payment-update.php
 * Maps order_id (temporary) to actual Razorpay payment_id
 * Allows payment-success.php to find payment data even if payment_id changes
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/payment_link_map.php';

$order_id = $_POST['order_id'] ?? '';
$payment_id = $_POST['payment_id'] ?? '';

if ($order_id && $payment_id) {
    try {
        $lookupStmt = $pdo->prepare("SELECT payment_id, source, category FROM pending_payments WHERE razorpay_order_id = ? LIMIT 1");
        $lookupStmt->execute([$order_id]);
        $pendingRow = $lookupStmt->fetch(PDO::FETCH_ASSOC);

        $originalPaymentId = '';
        if ($pendingRow && !empty($pendingRow['payment_id']) && strpos((string)$pendingRow['payment_id'], 'ORD-') === 0) {
            $originalPaymentId = (string)$pendingRow['payment_id'];
        } else {
            $mapRow = vs_paymap_find($pdo, (string)$order_id);
            if ($mapRow && !empty($mapRow['original_payment_id'])) {
                $originalPaymentId = (string)$mapRow['original_payment_id'];
            }
        }

        if ($originalPaymentId !== '') {
            vs_paymap_upsert(
                $pdo,
                $originalPaymentId,
                (string)$order_id,
                (string)$payment_id,
                isset($pendingRow['source']) ? (string)$pendingRow['source'] : null,
                isset($pendingRow['category']) ? (string)$pendingRow['category'] : null
            );
        } else {
            vs_paymap_update_by_order($pdo, (string)$order_id, (string)$payment_id);
        }

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
