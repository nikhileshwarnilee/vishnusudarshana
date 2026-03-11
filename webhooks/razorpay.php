<?php
// Razorpay webhook handler with safe recovery for abandoned browser redirects.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Read config from environment first, then fallback to .env file.
 */
function vs_rzp_env(string $key, string $default = ''): string
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
                    $env[trim($parts[0])] = trim($parts[1]);
                }
            }
        }
    }

    if (isset($env[$key]) && trim((string)$env[$key]) !== '') {
        return trim((string)$env[$key]);
    }

    return $default;
}

/**
 * Append invalid webhook attempts to a local log file for security auditing.
 */
function vs_rzp_log_invalid_attempt(string $reason, string $signature, string $payload): void
{
    try {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $logFile = $logDir . '/webhook_invalid.log';
        $line = sprintf(
            "[%s] ip=%s reason=%s signature=%s payload_sha256=%s\n",
            date('Y-m-d H:i:s'),
            (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            $reason,
            $signature !== '' ? $signature : 'empty',
            hash('sha256', $payload)
        );
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        // Ignore logging failures; webhook should still terminate safely.
    }
}

/**
 * Best-effort creation of webhook logs table.
 */
function vs_rzp_ensure_logs_table(PDO $pdo): void
{
    static $created = false;
    if ($created) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS razorpay_webhook_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(100) NOT NULL,
            razorpay_order_id VARCHAR(100) NULL,
            razorpay_payment_id VARCHAR(100) NULL,
            payload LONGTEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_rzp_order_id (razorpay_order_id),
            INDEX idx_rzp_payment_id (razorpay_payment_id),
            INDEX idx_rzp_event_type (event_type),
            INDEX idx_rzp_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $created = true;
}

/**
 * Log each webhook event payload for production debugging.
 */
function vs_rzp_log_event(PDO $pdo, string $eventType, string $orderId, string $paymentId, string $payload): void
{
    try {
        vs_rzp_ensure_logs_table($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO razorpay_webhook_logs
                (event_type, razorpay_order_id, razorpay_payment_id, payload)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$eventType, $orderId !== '' ? $orderId : null, $paymentId !== '' ? $paymentId : null, $payload]);
    } catch (Throwable $e) {
        error_log('Razorpay webhook log insert failed: ' . $e->getMessage());
    }
}

/**
 * Cache pending_payments columns so updates remain schema-safe.
 */
function vs_rzp_pending_columns(PDO $pdo): array
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
        error_log('Failed to inspect pending_payments columns: ' . $e->getMessage());
    }

    return $columns;
}

/**
 * Update pending_payments with captured/failed state without assuming extra columns.
 */
function vs_rzp_update_pending(PDO $pdo, string $orderId, string $paymentId = '', string $status = ''): void
{
    if ($orderId === '') {
        return;
    }

    $columns = vs_rzp_pending_columns($pdo);
    $set = [];
    $params = [];

    // Keep compatibility with current flow where payment_id gets replaced with Razorpay payment id.
    if ($paymentId !== '' && isset($columns['payment_id'])) {
        $set[] = 'payment_id = ?';
        $params[] = $paymentId;
    }

    if ($paymentId !== '' && isset($columns['razorpay_payment_id'])) {
        $set[] = 'razorpay_payment_id = ?';
        $params[] = $paymentId;
    }

    if ($status !== '' && isset($columns['status'])) {
        $set[] = 'status = ?';
        $params[] = $status;
    }

    if (empty($set)) {
        return;
    }

    $params[] = $orderId;
    $sql = 'UPDATE pending_payments SET ' . implode(', ', $set) . ' WHERE razorpay_order_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function vs_rzp_json_array($value): array
{
    if (!is_string($value) || trim($value) === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Match payment-success.php tracking id pattern and uniqueness.
 */
function vs_rzp_generate_tracking_id(PDO $pdo): string
{
    $checkStmt = $pdo->prepare('SELECT id FROM service_requests WHERE tracking_id = ? LIMIT 1');

    for ($i = 0; $i < 10; $i++) {
        $trackingId = 'VDSK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $checkStmt->execute([$trackingId]);
        if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
            return $trackingId;
        }
    }

    return 'VDSK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * Insert booking row using the same insert structure used in payment-success.php.
 */
function vs_rzp_insert_service_request_from_pending(PDO $pdo, array $pendingRow, string $orderId, string $paymentId): string
{
    $customerDetails = vs_rzp_json_array($pendingRow['customer_details'] ?? '');
    $formData = vs_rzp_json_array($pendingRow['form_data'] ?? '');
    if (empty($formData)) {
        $formData = vs_rzp_json_array($pendingRow['appointment_form'] ?? '');
    }
    $products = vs_rzp_json_array($pendingRow['selected_products'] ?? '');

    $category = trim((string)($pendingRow['category'] ?? ''));
    if ($category === '') {
        $category = 'unknown-service';
        error_log('Razorpay recovery: missing category for order_id=' . $orderId);
    }

    $customerName = (string)($customerDetails['full_name'] ?? ($formData['full_name'] ?? ''));
    $mobile = (string)($customerDetails['mobile'] ?? ($formData['mobile'] ?? ''));
    $email = (string)($customerDetails['email'] ?? ($formData['email'] ?? ''));
    $city = (string)($customerDetails['city'] ?? ($formData['city'] ?? ''));
    $totalAmount = isset($pendingRow['total_amount']) ? (float)$pendingRow['total_amount'] : 0.0;

    $trackingId = vs_rzp_generate_tracking_id($pdo);
    $createdAt = date('Y-m-d H:i:s');

    $insertStmt = $pdo->prepare("INSERT INTO service_requests (
        tracking_id, category_slug, customer_name, mobile, email, city,
        form_data, selected_products, total_amount, payment_id, razorpay_order_id, razorpay_payment_id, payment_status, service_status, created_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Paid', 'Received', ?
    )");

    $insertStmt->execute([
        $trackingId,
        $category,
        $customerName !== '' ? $customerName : 'N/A',
        $mobile !== '' ? $mobile : 'N/A',
        $email,
        $city,
        json_encode($formData),
        json_encode($products),
        $totalAmount,
        $paymentId,
        $orderId,
        $paymentId,
        $createdAt
    ]);

    return $trackingId;
}

/**
 * Recovery path: if user never returned to payment-success.php, finalize from pending_payments.
 */
function vs_rzp_recover_from_pending(PDO $pdo, string $orderId, string $paymentId): string
{
    if ($orderId === '' || $paymentId === '') {
        return 'missing-identifiers';
    }

    try {
        $pdo->beginTransaction();

        $existingStmt = $pdo->prepare('SELECT id FROM service_requests WHERE razorpay_order_id = ? LIMIT 1');
        $existingStmt->execute([$orderId]);
        if ($existingStmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->commit();
            return 'already-exists-order';
        }

        $pendingStmt = $pdo->prepare('SELECT * FROM pending_payments WHERE razorpay_order_id = ? LIMIT 1 FOR UPDATE');
        $pendingStmt->execute([$orderId]);
        $pendingRow = $pendingStmt->fetch(PDO::FETCH_ASSOC);

        if (!$pendingRow) {
            $pdo->commit();
            return 'pending-not-found';
        }

        // Re-check after row lock to avoid duplicate inserts during retries.
        $existingStmt->execute([$orderId]);
        if ($existingStmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->commit();
            return 'already-exists-order-after-lock';
        }

        $existingPaymentStmt = $pdo->prepare('SELECT id FROM service_requests WHERE payment_id = ? LIMIT 1');
        $existingPaymentStmt->execute([$paymentId]);
        if ($existingPaymentStmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->commit();
            return 'already-exists-payment';
        }

        $trackingId = vs_rzp_insert_service_request_from_pending($pdo, $pendingRow, $orderId, $paymentId);
        vs_rzp_update_pending($pdo, $orderId, $paymentId, 'confirmed');

        $pdo->commit();
        error_log('Razorpay webhook recovery: service_request inserted for order_id=' . $orderId . ' tracking_id=' . $trackingId);
        return 'inserted';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

// Security check 1: webhook secret must come from configuration only (no dummy fallback).
$webhook_secret = vs_rzp_env('RAZORPAY_WEBHOOK_SECRET', '');
if ($webhook_secret === '') {
    $webhook_secret = vs_rzp_env('RAZORPAY_WEBHOOK_SECRET_KEY', '');
}
if ($webhook_secret === '') {
    vs_rzp_log_invalid_attempt('webhook_secret_not_configured', $signature, $payload);
    error_log('Razorpay webhook rejected: webhook secret is not configured.');
    http_response_code(500);
    exit('Webhook secret not configured');
}

// Security check 2: compute HMAC using the configured webhook secret.
$expected_signature = hash_hmac('sha256', $payload, $webhook_secret);

// Security check 3: constant-time signature compare; reject immediately on mismatch.
if ($signature === '' || !hash_equals($expected_signature, $signature)) {
    vs_rzp_log_invalid_attempt('invalid_signature', $signature, $payload);
    error_log('Razorpay webhook rejected: invalid signature.');
    http_response_code(400);
    exit('Invalid webhook signature');
}

$data = json_decode($payload, true);

if (!is_array($data) || !isset($data['event'])) {
    http_response_code(400);
    exit('Invalid payload');
}

$paymentEntity = $data['payload']['payment']['entity'] ?? [];
$razorpayPaymentId = isset($paymentEntity['id']) ? (string)$paymentEntity['id'] : '';
$razorpayOrderId = isset($paymentEntity['order_id']) ? (string)$paymentEntity['order_id'] : '';
$paymentStatus = isset($paymentEntity['status']) ? (string)$paymentEntity['status'] : '';
$amount = isset($paymentEntity['amount']) ? (int)$paymentEntity['amount'] : 0;
$currency = isset($paymentEntity['currency']) ? (string)$paymentEntity['currency'] : '';

// Log received event type
$eventType = isset($data['event']) ? (string)$data['event'] : 'unknown';
vs_rzp_log_event($pdo, $eventType, $razorpayOrderId, $razorpayPaymentId, $payload);
error_log(
    'Razorpay webhook received event: ' . $eventType
    . ' order_id=' . ($razorpayOrderId !== '' ? $razorpayOrderId : 'not-set')
    . ' payment_id=' . ($razorpayPaymentId !== '' ? $razorpayPaymentId : 'not-set')
    . ' amount=' . $amount
    . ' currency=' . $currency
);

// Handle events
switch ($data['event']) {

    case 'payment.captured':
        try {
            error_log('Razorpay webhook order_id: ' . ($razorpayOrderId !== '' ? $razorpayOrderId : 'not set'));

            if ($razorpayOrderId !== '' && $razorpayPaymentId !== '') {
                // Find appointment/service request by razorpay_order_id
                $stmt = $pdo->prepare("SELECT payment_status, booking_status FROM service_requests WHERE razorpay_order_id = ? LIMIT 1");
                $stmt->execute([$razorpayOrderId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                // Only update if not already paid/confirmed
                if ($row && strtolower((string)$row['payment_status']) !== 'paid' && strtolower((string)$row['booking_status']) !== 'confirmed') {
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
                } else if ($row && strtolower((string)$row['payment_status']) === 'paid' && strtolower((string)$row['booking_status']) === 'confirmed') {
                    error_log('Razorpay webhook: already paid and confirmed for order_id ' . $razorpayOrderId);
                } else if ($row && strtolower((string)$row['payment_status']) === 'paid') {
                    error_log('Razorpay webhook: already paid for order_id ' . $razorpayOrderId);
                } else if ($row && strtolower((string)$row['booking_status']) === 'confirmed') {
                    error_log('Razorpay webhook: already confirmed for order_id ' . $razorpayOrderId);
                } else {
                    $recoveryStatus = vs_rzp_recover_from_pending($pdo, $razorpayOrderId, $razorpayPaymentId);
                    error_log('Razorpay webhook recovery result for order_id ' . $razorpayOrderId . ': ' . $recoveryStatus);
                }

                // Best effort status sync in pending_payments (if columns exist).
                vs_rzp_update_pending($pdo, $razorpayOrderId, $razorpayPaymentId, 'confirmed');
            } else {
                error_log('Razorpay webhook: missing order_id or payment_id');
            }
        } catch (Throwable $e) {
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

                // Best effort failed status sync in pending_payments (if status column exists).
                vs_rzp_update_pending($pdo, (string)$razorpayOrderId, '', 'failed');
            } else {
                error_log('Razorpay webhook: missing order_id');
            }
        } catch (Throwable $e) {
            error_log('Razorpay webhook payment.failed error: ' . $e->getMessage());
            http_response_code(500);
            exit;
        }
        break;
}

http_response_code(200);
echo 'OK';
