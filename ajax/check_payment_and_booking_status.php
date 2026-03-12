<?php
// Lightweight backend status gate for payment-init button flow.
// Purpose: prevent duplicate payment attempts when users reopen old payment links.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../helpers/payment_link_map.php';

use Razorpay\Api\Api;

header('Content-Type: application/json');

function vs_status_json(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function vs_status_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value !== false && trim((string)$value) !== '') {
        return trim((string)$value);
    }

    static $env = null;
    if ($env === null) {
        $env = [];
        $envPath = dirname(__DIR__) . '/.env';
        if (is_file($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $line = trim((string)$line);
                    if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                        continue;
                    }
                    $parts = explode('=', $line, 2);
                    $env[trim((string)$parts[0])] = trim((string)$parts[1]);
                }
            }
        }
    }

    if (isset($env[$key]) && trim((string)$env[$key]) !== '') {
        return trim((string)$env[$key]);
    }

    return $default;
}

function vs_status_add_unique(array &$bucket, string $value): void
{
    $value = trim($value);
    if ($value === '') {
        return;
    }
    if (!in_array($value, $bucket, true)) {
        $bucket[] = $value;
    }
}

function vs_status_pending_columns(PDO $pdo): array
{
    static $columns = null;
    if ($columns !== null) {
        return $columns;
    }

    $columns = [];
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM pending_payments');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[strtolower((string)$row['Field'])] = true;
        }
    } catch (Throwable $e) {
        error_log('status-gate pending column lookup failed: ' . $e->getMessage());
    }

    return $columns;
}

function vs_status_sync_pending_payment(PDO $pdo, array $pendingRow, string $orderId, string $paymentId): void
{
    if ($orderId === '' || $paymentId === '') {
        return;
    }

    $columns = vs_status_pending_columns($pdo);
    $set = [];
    $params = [];

    if (isset($columns['payment_id'])) {
        $set[] = 'payment_id = ?';
        $params[] = $paymentId;
    }
    if (isset($columns['razorpay_payment_id'])) {
        $set[] = 'razorpay_payment_id = ?';
        $params[] = $paymentId;
    }

    if (!empty($set)) {
        $params[] = $orderId;
        $sql = 'UPDATE pending_payments SET ' . implode(', ', $set) . ' WHERE razorpay_order_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    try {
        $source = isset($pendingRow['source']) ? (string)$pendingRow['source'] : null;
        $category = isset($pendingRow['category']) ? (string)$pendingRow['category'] : null;
        $originalPaymentId = isset($pendingRow['payment_id']) ? (string)$pendingRow['payment_id'] : '';
        if ($originalPaymentId !== '' && strpos($originalPaymentId, 'ORD-') === 0) {
            vs_paymap_upsert($pdo, $originalPaymentId, $orderId, $paymentId, $source, $category);
        } else {
            vs_paymap_update_by_order($pdo, $orderId, $paymentId);
        }
    } catch (Throwable $e) {
        error_log('status-gate payment map sync failed: ' . $e->getMessage());
    }
}

function vs_status_item_value($item, string $key): string
{
    if (is_array($item) && isset($item[$key])) {
        return trim((string)$item[$key]);
    }
    if (is_object($item) && isset($item->$key)) {
        return trim((string)$item->$key);
    }
    if ($item instanceof ArrayAccess && isset($item[$key])) {
        return trim((string)$item[$key]);
    }
    return '';
}

$paymentToken = trim((string)($_GET['payment_id'] ?? ''));
$orderHint = trim((string)($_GET['order_id'] ?? ''));

if ($paymentToken === '' && $orderHint === '') {
    vs_status_json([
        'success' => false,
        'state' => 'invalid',
        'message' => 'Missing payment reference.'
    ]);
}

try {
    $candidatePaymentTokens = [];
    $candidateOrderIds = [];

    vs_status_add_unique($candidatePaymentTokens, $paymentToken);
    vs_status_add_unique($candidateOrderIds, $orderHint);

    $mapRow = null;
    if ($paymentToken !== '') {
        $mapRow = vs_paymap_find($pdo, $paymentToken);
    }
    if (!$mapRow && $orderHint !== '') {
        $mapRow = vs_paymap_find($pdo, $orderHint);
    }

    if ($mapRow) {
        vs_status_add_unique($candidatePaymentTokens, (string)($mapRow['original_payment_id'] ?? ''));
        vs_status_add_unique($candidatePaymentTokens, (string)($mapRow['razorpay_payment_id'] ?? ''));
        vs_status_add_unique($candidateOrderIds, (string)($mapRow['razorpay_order_id'] ?? ''));
    }

    $pendingRow = null;
    foreach ($candidateOrderIds as $orderId) {
        $stmt = $pdo->prepare('SELECT * FROM pending_payments WHERE razorpay_order_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $pendingRow = $row;
            break;
        }
    }

    if (!$pendingRow) {
        foreach ($candidatePaymentTokens as $token) {
            $stmt = $pdo->prepare('SELECT * FROM pending_payments WHERE payment_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $pendingRow = $row;
                break;
            }
        }
    }

    if ($pendingRow) {
        vs_status_add_unique($candidatePaymentTokens, (string)($pendingRow['payment_id'] ?? ''));
        vs_status_add_unique($candidatePaymentTokens, (string)($pendingRow['razorpay_payment_id'] ?? ''));
        vs_status_add_unique($candidateOrderIds, (string)($pendingRow['razorpay_order_id'] ?? ''));
    }

    $where = [];
    $params = [];
    foreach ($candidateOrderIds as $orderId) {
        $where[] = 'razorpay_order_id = ?';
        $params[] = $orderId;
    }
    foreach ($candidatePaymentTokens as $token) {
        $where[] = '(payment_id = ? OR razorpay_payment_id = ?)';
        $params[] = $token;
        $params[] = $token;
    }

    $existingBooking = null;
    if (!empty($where)) {
        $sql = '
            SELECT id, tracking_id, payment_status, service_status, payment_id, razorpay_payment_id, razorpay_order_id
            FROM service_requests
            WHERE ' . implode(' OR ', $where) . '
            ORDER BY id DESC
            LIMIT 1
        ';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $existingBooking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if ($existingBooking) {
        $paymentStatus = strtolower(trim((string)($existingBooking['payment_status'] ?? '')));
        $canonicalPaymentId = trim((string)($existingBooking['razorpay_payment_id'] ?? ''));
        if ($canonicalPaymentId === '') {
            $canonicalPaymentId = trim((string)($existingBooking['payment_id'] ?? ''));
        }
        if ($canonicalPaymentId === '') {
            $canonicalPaymentId = $paymentToken !== '' ? $paymentToken : 'unknown';
        }

        if ($paymentStatus === 'failed') {
            vs_status_json([
                'success' => true,
                'state' => 'failed',
                'message' => 'Payment marked as failed.',
                'redirect_url' => 'payment-failed.php?payment_id=' . urlencode($canonicalPaymentId)
            ]);
        }

        vs_status_json([
            'success' => true,
            'state' => 'success',
            'message' => 'Booking already confirmed.',
            'tracking_id' => (string)($existingBooking['tracking_id'] ?? ''),
            'redirect_url' => 'payment-success.php?payment_id=' . urlencode($canonicalPaymentId)
        ]);
    }

    $resolvedOrderId = '';
    if (!empty($candidateOrderIds)) {
        $resolvedOrderId = (string)$candidateOrderIds[0];
    }

    if ($resolvedOrderId === '') {
        vs_status_json([
            'success' => true,
            'state' => 'unpaid',
            'message' => 'No payment status available yet.',
            'redirect_url' => 'payment-failed.php?payment_id=' . urlencode($paymentToken)
        ]);
    }

    $keyId = vs_status_env('RAZORPAY_KEY_ID');
    $keySecret = vs_status_env('RAZORPAY_KEY_SECRET');
    if ($keyId === '' || $keySecret === '') {
        vs_status_json([
            'success' => false,
            'state' => 'config_error',
            'message' => 'Razorpay credentials are missing.'
        ]);
    }

    $api = new Api($keyId, $keySecret);
    $order = $api->order->fetch($resolvedOrderId);
    $paymentsCollection = $order->payments();
    $items = [];

    if (is_array($paymentsCollection) && isset($paymentsCollection['items']) && is_array($paymentsCollection['items'])) {
        $items = $paymentsCollection['items'];
    } elseif (is_object($paymentsCollection) && isset($paymentsCollection->items) && is_array($paymentsCollection->items)) {
        $items = $paymentsCollection->items;
    }

    $capturedPaymentId = '';
    $failedPaymentId = '';
    $hasAttempt = false;

    foreach ($items as $item) {
        $status = strtolower(vs_status_item_value($item, 'status'));
        $payId = vs_status_item_value($item, 'id');
        if ($status === '') {
            continue;
        }

        $hasAttempt = true;
        if ($status === 'captured' && $payId !== '') {
            $capturedPaymentId = $payId;
            break;
        }
        if ($status === 'failed' && $payId !== '' && $failedPaymentId === '') {
            $failedPaymentId = $payId;
        }
    }

    if ($capturedPaymentId !== '') {
        if ($pendingRow) {
            vs_status_sync_pending_payment($pdo, $pendingRow, $resolvedOrderId, $capturedPaymentId);
        }

        vs_status_json([
            'success' => true,
            'state' => 'success',
            'message' => 'Payment captured. Redirecting to confirmation.',
            'redirect_url' => 'payment-success.php?payment_id=' . urlencode($capturedPaymentId),
            'payment_id' => $capturedPaymentId
        ]);
    }

    if ($failedPaymentId !== '') {
        vs_status_json([
            'success' => true,
            'state' => 'failed',
            'message' => 'Payment failed.',
            'redirect_url' => 'payment-failed.php?payment_id=' . urlencode($failedPaymentId),
            'payment_id' => $failedPaymentId
        ]);
    }

    if ($hasAttempt) {
        vs_status_json([
            'success' => true,
            'state' => 'pending',
            'message' => 'Payment attempt found. Waiting for final confirmation.'
        ]);
    }

    vs_status_json([
        'success' => true,
        'state' => 'unpaid',
        'message' => 'No successful payment found yet.',
        'redirect_url' => 'payment-failed.php?payment_id=' . urlencode($paymentToken !== '' ? $paymentToken : $resolvedOrderId)
    ]);
} catch (Throwable $e) {
    error_log('status-gate check failed: ' . $e->getMessage());
    vs_status_json([
        'success' => false,
        'state' => 'error',
        'message' => 'Unable to verify payment status right now.'
    ]);
}

