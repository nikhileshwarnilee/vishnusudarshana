<?php
// Safe cron recovery for Razorpay captures where user never returned to payment-success.php.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../helpers/payment_link_map.php';

use Razorpay\Api\Api;

function vs_reconcile_log_line(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

function vs_reconcile_env(string $key, string $default = ''): string
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

function vs_reconcile_ensure_logs_table(PDO $pdo): void
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

function vs_reconcile_log_event(PDO $pdo, string $eventType, string $orderId, string $paymentId, string $payload): void
{
    try {
        vs_reconcile_ensure_logs_table($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO razorpay_webhook_logs
                (event_type, razorpay_order_id, razorpay_payment_id, payload)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$eventType, $orderId !== '' ? $orderId : null, $paymentId !== '' ? $paymentId : null, $payload]);
    } catch (Throwable $e) {
        vs_reconcile_log_line('Webhook log insert failed: ' . $e->getMessage());
    }
}

function vs_reconcile_pending_columns(PDO $pdo): array
{
    static $columns = null;
    if ($columns !== null) {
        return $columns;
    }

    $columns = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM pending_payments');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[strtolower((string)$row['Field'])] = true;
    }

    return $columns;
}

function vs_reconcile_update_pending(PDO $pdo, string $orderId, string $paymentId = '', string $status = ''): void
{
    if ($orderId === '') {
        return;
    }

    $preUpdatePending = null;
    try {
        $lookupStmt = $pdo->prepare('SELECT payment_id, source, category FROM pending_payments WHERE razorpay_order_id = ? LIMIT 1');
        $lookupStmt->execute([$orderId]);
        $preUpdatePending = $lookupStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        vs_reconcile_log_line('Pending lookup failed for map sync: ' . $e->getMessage());
    }

    $columns = vs_reconcile_pending_columns($pdo);
    $set = [];
    $params = [];

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
    $pdo->prepare($sql)->execute($params);

    try {
        $originalPaymentId = '';
        $source = null;
        $category = null;
        if (is_array($preUpdatePending)) {
            $source = isset($preUpdatePending['source']) ? (string)$preUpdatePending['source'] : null;
            $category = isset($preUpdatePending['category']) ? (string)$preUpdatePending['category'] : null;
            if (!empty($preUpdatePending['payment_id']) && strpos((string)$preUpdatePending['payment_id'], 'ORD-') === 0) {
                $originalPaymentId = (string)$preUpdatePending['payment_id'];
            }
        }

        if ($originalPaymentId === '') {
            $mapRow = vs_paymap_find($pdo, (string)$orderId);
            if ($mapRow && !empty($mapRow['original_payment_id'])) {
                $originalPaymentId = (string)$mapRow['original_payment_id'];
            }
        }

        if ($originalPaymentId !== '') {
            vs_paymap_upsert($pdo, $originalPaymentId, (string)$orderId, $paymentId !== '' ? (string)$paymentId : null, $source, $category);
        } else if ($paymentId !== '') {
            vs_paymap_update_by_order($pdo, (string)$orderId, (string)$paymentId);
        }
    } catch (Throwable $e) {
        vs_reconcile_log_line('Payment-link map sync failed: ' . $e->getMessage());
    }
}

function vs_reconcile_json_array($value): array
{
    if (!is_string($value) || trim($value) === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function vs_reconcile_products_list(array $products, string $category): string
{
    $names = [];
    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }
        if (!empty($product['name'])) {
            $names[] = (string)$product['name'];
            continue;
        }
        if (!empty($product['product_name'])) {
            $names[] = (string)$product['product_name'];
            continue;
        }
    }

    if (!empty($names)) {
        return implode(', ', $names);
    }

    return ucwords(str_replace('-', ' ', $category));
}

/**
 * Additive notification path for Scenario C (cron recovery), keeps A-flow untouched.
 */
function vs_reconcile_send_recovery_notifications(array $pendingRow, string $trackingId): void
{
    try {
        require_once __DIR__ . '/../helpers/send_whatsapp.php';
        require_once __DIR__ . '/../config/admin_config.php';

        $customerDetails = vs_reconcile_json_array($pendingRow['customer_details'] ?? '');
        $formData = vs_reconcile_json_array($pendingRow['form_data'] ?? '');
        if (empty($formData)) {
            $formData = vs_reconcile_json_array($pendingRow['appointment_form'] ?? '');
        }
        $products = vs_reconcile_json_array($pendingRow['selected_products'] ?? '');

        $category = trim((string)($pendingRow['category'] ?? ''));
        if ($category === '') {
            $category = 'unknown-service';
        }

        $customerName = (string)($customerDetails['full_name'] ?? ($formData['full_name'] ?? 'Customer'));
        $mobile = (string)($customerDetails['mobile'] ?? ($formData['mobile'] ?? ''));
        if ($mobile === '') {
            vs_reconcile_log_line('Recovery WhatsApp skipped: missing mobile for tracking_id=' . $trackingId);
            return;
        }

        $productsList = vs_reconcile_products_list($products, $category);
        $adminMobile = defined('ADMIN_WHATSAPP')
            ? ADMIN_WHATSAPP
            : (defined('WHATSAPP_BUSINESS_PHONE') ? WHATSAPP_BUSINESS_PHONE : '918975224444');

        if ($category === 'appointment') {
            $customerResult = sendWhatsAppNotification('appointment_booked_payment_success', [
                'mobile' => $mobile,
                'name' => $customerName,
                'category' => 'Appointment',
                'products_list' => $productsList,
                'tracking_url' => $trackingId
            ]);
            sendWhatsAppNotification('admin_services_alert', [
                'admin_mobile' => $adminMobile,
                'customer_name' => $customerName,
                'customer_mobile' => $mobile,
                'category' => 'Appointment',
                'products_list' => $productsList,
                'tracking_id' => $trackingId
            ]);
            if (!$customerResult['success']) {
                vs_reconcile_log_line('Recovery WhatsApp failed (appointment) tracking_id=' . $trackingId . ': ' . ($customerResult['message'] ?? 'unknown'));
            }
        } else {
            $serviceCategoryDisplay = ucwords(str_replace('-', ' ', $category));
            $customerResult = sendWhatsAppNotification('service_received', [
                'mobile' => $mobile,
                'name' => $customerName,
                'category' => $serviceCategoryDisplay,
                'products_list' => $productsList,
                'tracking_url' => $trackingId
            ]);
            sendWhatsAppNotification('admin_services_alert', [
                'admin_mobile' => $adminMobile,
                'customer_name' => $customerName,
                'customer_mobile' => $mobile,
                'category' => $serviceCategoryDisplay,
                'products_list' => $productsList,
                'tracking_id' => $trackingId
            ]);
            if (!$customerResult['success']) {
                vs_reconcile_log_line('Recovery WhatsApp failed (service) tracking_id=' . $trackingId . ': ' . ($customerResult['message'] ?? 'unknown'));
            }
        }
    } catch (Throwable $e) {
        vs_reconcile_log_line('Recovery notification error tracking_id=' . $trackingId . ': ' . $e->getMessage());
    }
}

function vs_reconcile_generate_tracking_id(PDO $pdo): string
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

function vs_reconcile_insert_service_request(PDO $pdo, array $pendingRow, string $orderId, string $paymentId): string
{
    $customerDetails = vs_reconcile_json_array($pendingRow['customer_details'] ?? '');
    $formData = vs_reconcile_json_array($pendingRow['form_data'] ?? '');
    if (empty($formData)) {
        $formData = vs_reconcile_json_array($pendingRow['appointment_form'] ?? '');
    }
    $products = vs_reconcile_json_array($pendingRow['selected_products'] ?? '');

    $category = trim((string)($pendingRow['category'] ?? ''));
    if ($category === '') {
        $category = 'unknown-service';
    }

    $customerName = (string)($customerDetails['full_name'] ?? ($formData['full_name'] ?? ''));
    $mobile = (string)($customerDetails['mobile'] ?? ($formData['mobile'] ?? ''));
    $email = (string)($customerDetails['email'] ?? ($formData['email'] ?? ''));
    $city = (string)($customerDetails['city'] ?? ($formData['city'] ?? ''));
    $totalAmount = isset($pendingRow['total_amount']) ? (float)$pendingRow['total_amount'] : 0.0;
    $trackingId = vs_reconcile_generate_tracking_id($pdo);
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

function vs_reconcile_finalize_from_pending(PDO $pdo, array $pendingRow, string $orderId, string $paymentId): string
{
    if ($orderId === '' || $paymentId === '') {
        return 'missing-identifiers';
    }

    $pendingId = isset($pendingRow['id']) ? (int)$pendingRow['id'] : 0;
    if ($pendingId <= 0) {
        return 'invalid-pending-id';
    }

    try {
        $pdo->beginTransaction();

        $existingOrderStmt = $pdo->prepare('SELECT id FROM service_requests WHERE razorpay_order_id = ? LIMIT 1');
        $existingOrderStmt->execute([$orderId]);
        if ($existingOrderStmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->commit();
            return 'already-exists-order';
        }

        $lockStmt = $pdo->prepare('SELECT * FROM pending_payments WHERE id = ? LIMIT 1 FOR UPDATE');
        $lockStmt->execute([$pendingId]);
        $lockedPending = $lockStmt->fetch(PDO::FETCH_ASSOC);
        if (!$lockedPending) {
            $pdo->commit();
            return 'pending-not-found';
        }

        // Recheck after lock to stay idempotent under concurrent cron/webhook runs.
        $existingOrderStmt->execute([$orderId]);
        if ($existingOrderStmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->commit();
            return 'already-exists-order-after-lock';
        }

        $existingPaymentStmt = $pdo->prepare('SELECT id FROM service_requests WHERE payment_id = ? LIMIT 1');
        $existingPaymentStmt->execute([$paymentId]);
        if ($existingPaymentStmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->commit();
            return 'already-exists-payment';
        }

        $trackingId = vs_reconcile_insert_service_request($pdo, $lockedPending, $orderId, $paymentId);
        vs_reconcile_update_pending($pdo, $orderId, $paymentId, 'confirmed');

        $pdo->commit();
        vs_reconcile_send_recovery_notifications($lockedPending, $trackingId);
        return 'inserted:' . $trackingId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

$keyId = vs_reconcile_env('RAZORPAY_KEY_ID', '');
$keySecret = vs_reconcile_env('RAZORPAY_KEY_SECRET', '');

if ($keyId === '' || $keySecret === '') {
    vs_reconcile_log_line('Missing RAZORPAY_KEY_ID or RAZORPAY_KEY_SECRET. Exiting.');
    exit(1);
}

$limit = 200;
if (isset($argv[1]) && ctype_digit((string)$argv[1])) {
    $limit = max(1, min(1000, (int)$argv[1]));
}

$pendingColumns = vs_reconcile_pending_columns($pdo);
$statusFilter = '';
if (isset($pendingColumns['status'])) {
    $statusFilter = " AND (p.status IS NULL OR LOWER(TRIM(p.status)) IN ('', 'pending', 'created', 'initiated'))";
}

$sql = "
    SELECT p.*
    FROM pending_payments p
    LEFT JOIN service_requests s ON s.razorpay_order_id = p.razorpay_order_id
    WHERE COALESCE(p.razorpay_order_id, '') <> ''
      AND s.id IS NULL
      $statusFilter
    ORDER BY p.created_at ASC
    LIMIT $limit
";

$pendingRows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
if (empty($pendingRows)) {
    vs_reconcile_log_line('No unresolved pending payments found.');
    exit(0);
}

vs_reconcile_log_line('Found ' . count($pendingRows) . ' unresolved pending payment rows.');

$api = new Api($keyId, $keySecret);
$stats = [
    'checked' => 0,
    'captured' => 0,
    'inserted' => 0,
    'already_exists' => 0,
    'failed' => 0,
    'errors' => 0,
];

foreach ($pendingRows as $row) {
    $stats['checked']++;
    $orderId = trim((string)($row['razorpay_order_id'] ?? ''));
    if ($orderId === '') {
        continue;
    }

    try {
        $payments = $api->order->fetch($orderId)->payments();
        $items = isset($payments['items']) && is_array($payments['items']) ? $payments['items'] : [];
        $capturedPayment = null;
        $failedSeen = false;

        foreach ($items as $payment) {
            $status = strtolower((string)($payment['status'] ?? ''));
            if ($status === 'captured') {
                $capturedPayment = $payment;
                break;
            }
            if ($status === 'failed') {
                $failedSeen = true;
            }
        }

        if ($capturedPayment !== null) {
            $stats['captured']++;
            $paymentId = (string)($capturedPayment['id'] ?? '');
            if ($paymentId === '') {
                continue;
            }

            $result = vs_reconcile_finalize_from_pending($pdo, $row, $orderId, $paymentId);
            if (strpos($result, 'inserted:') === 0) {
                $stats['inserted']++;
                vs_reconcile_log_line('Inserted booking for order_id=' . $orderId . ' (' . $result . ')');
            } else if (strpos($result, 'already-exists') === 0) {
                $stats['already_exists']++;
                vs_reconcile_log_line('Skipped existing booking for order_id=' . $orderId . ' (' . $result . ')');
            } else {
                vs_reconcile_log_line('Captured but not inserted for order_id=' . $orderId . ' (' . $result . ')');
            }

            vs_reconcile_log_event(
                $pdo,
                'reconcile.payment.captured',
                $orderId,
                $paymentId,
                json_encode(['result' => $result, 'payment' => $capturedPayment])
            );

            continue;
        }

        if ($failedSeen) {
            $stats['failed']++;
            vs_reconcile_update_pending($pdo, $orderId, '', 'failed');
            vs_reconcile_log_event(
                $pdo,
                'reconcile.payment.failed',
                $orderId,
                '',
                json_encode(['reason' => 'failed payment seen via order->payments API'])
            );
            vs_reconcile_log_line('Marked pending as failed for order_id=' . $orderId);
        }
    } catch (Throwable $e) {
        $stats['errors']++;
        vs_reconcile_log_line('Error for order_id=' . $orderId . ': ' . $e->getMessage());
        vs_reconcile_log_event(
            $pdo,
            'reconcile.error',
            $orderId,
            '',
            json_encode(['error' => $e->getMessage()])
        );
    }
}

vs_reconcile_log_line(
    'Done. checked=' . $stats['checked']
    . ' captured=' . $stats['captured']
    . ' inserted=' . $stats['inserted']
    . ' already_exists=' . $stats['already_exists']
    . ' failed=' . $stats['failed']
    . ' errors=' . $stats['errors']
);
