<?php

function vs_service_request_columns(PDO $pdo, bool $refresh = false): array
{
    static $columns = null;

    if ($refresh) {
        $columns = null;
    }

    if ($columns !== null) {
        return $columns;
    }

    $columns = [];

    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM service_requests');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[strtolower((string)$row['Field'])] = true;
        }
    } catch (Throwable $e) {
        $columns = [];
    }

    return $columns;
}

function vs_service_request_ensure_schema(PDO $pdo): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS service_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tracking_id VARCHAR(30) UNIQUE,
            category_slug VARCHAR(50),
            customer_name VARCHAR(255),
            mobile VARCHAR(20),
            email VARCHAR(255),
            city VARCHAR(255),
            form_data JSON,
            selected_products JSON,
            total_amount DECIMAL(10,2),
            payment_id VARCHAR(100),
            razorpay_order_id VARCHAR(100) NULL,
            razorpay_payment_id VARCHAR(100) NULL,
            payment_status VARCHAR(20),
            booking_status VARCHAR(20) NULL,
            service_status VARCHAR(50),
            customer_notification_sent_at DATETIME NULL,
            admin_notification_sent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_tracking_id (tracking_id),
            INDEX idx_payment_id (payment_id),
            INDEX idx_razorpay_order_id (razorpay_order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $columns = vs_service_request_columns($pdo, true);
    $alterStatements = [];

    if (!isset($columns['razorpay_order_id'])) {
        $alterStatements[] = "ALTER TABLE service_requests ADD COLUMN razorpay_order_id VARCHAR(100) NULL";
    }
    if (!isset($columns['razorpay_payment_id'])) {
        $alterStatements[] = "ALTER TABLE service_requests ADD COLUMN razorpay_payment_id VARCHAR(100) NULL";
    }
    if (!isset($columns['booking_status'])) {
        $alterStatements[] = "ALTER TABLE service_requests ADD COLUMN booking_status VARCHAR(20) NULL";
    }
    if (!isset($columns['customer_notification_sent_at'])) {
        $alterStatements[] = "ALTER TABLE service_requests ADD COLUMN customer_notification_sent_at DATETIME NULL";
    }
    if (!isset($columns['admin_notification_sent_at'])) {
        $alterStatements[] = "ALTER TABLE service_requests ADD COLUMN admin_notification_sent_at DATETIME NULL";
    }

    foreach ($alterStatements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            error_log('service_requests schema sync failed: ' . $e->getMessage());
        }
    }

    vs_service_request_columns($pdo, true);
    $ensured = true;
}

function vs_service_request_insert(PDO $pdo, array $payload): void
{
    vs_service_request_ensure_schema($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO service_requests (
            tracking_id, category_slug, customer_name, mobile, email, city,
            form_data, selected_products, total_amount, payment_id, razorpay_order_id,
            razorpay_payment_id, payment_status, booking_status, service_status, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        (string)($payload['tracking_id'] ?? ''),
        (string)($payload['category_slug'] ?? ''),
        (string)($payload['customer_name'] ?? 'N/A'),
        (string)($payload['mobile'] ?? 'N/A'),
        (string)($payload['email'] ?? ''),
        (string)($payload['city'] ?? ''),
        json_encode($payload['form_data'] ?? [], JSON_UNESCAPED_UNICODE),
        json_encode($payload['selected_products'] ?? [], JSON_UNESCAPED_UNICODE),
        isset($payload['total_amount']) ? (float)$payload['total_amount'] : 0.0,
        (string)($payload['payment_id'] ?? ''),
        ($payload['razorpay_order_id'] ?? '') !== '' ? (string)$payload['razorpay_order_id'] : null,
        ($payload['razorpay_payment_id'] ?? '') !== '' ? (string)$payload['razorpay_payment_id'] : null,
        (string)($payload['payment_status'] ?? ''),
        ($payload['booking_status'] ?? '') !== '' ? (string)$payload['booking_status'] : null,
        (string)($payload['service_status'] ?? ''),
        (string)($payload['created_at'] ?? date('Y-m-d H:i:s')),
    ]);
}

function vs_service_request_products_list(array $products, string $fallbackCategory = ''): string
{
    $names = [];

    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }

        $baseName = '';
        if (!empty($product['name'])) {
            $baseName = (string)$product['name'];
        } elseif (!empty($product['product_name'])) {
            $baseName = (string)$product['product_name'];
        }

        $variantValueName = trim((string)($product['variant_value_name'] ?? ''));
        if ($baseName !== '' && $variantValueName !== '') {
            $names[] = $baseName . ' (' . $variantValueName . ')';
        } elseif ($baseName !== '') {
            $names[] = $baseName;
        } elseif ($variantValueName !== '') {
            $names[] = $variantValueName;
        }
    }

    if (!empty($names)) {
        return implode(', ', $names);
    }

    $fallbackCategory = trim($fallbackCategory);
    if ($fallbackCategory !== '') {
        return ucwords(str_replace('-', ' ', $fallbackCategory));
    }

    return 'Service';
}

function vs_service_request_lock_path(string $trackingId): string
{
    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', trim($trackingId));
    $safeName = $safeName !== '' ? $safeName : 'default';
    $lockDir = dirname(__DIR__) . '/logs/service-request-notify-locks';

    if (!is_dir($lockDir)) {
        @mkdir($lockDir, 0775, true);
    }

    return $lockDir . '/' . $safeName . '.lock';
}

function vs_service_request_with_lock(string $trackingId, callable $callback)
{
    $lockPath = vs_service_request_lock_path($trackingId);
    $handle = @fopen($lockPath, 'c+');

    if ($handle === false) {
        return $callback();
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            return $callback();
        }

        return $callback();
    } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

function vs_service_request_mark_notification_sent(PDO $pdo, string $trackingId, string $column): void
{
    $allowedColumns = [
        'customer_notification_sent_at',
        'admin_notification_sent_at',
    ];

    if (!in_array($column, $allowedColumns, true)) {
        throw new InvalidArgumentException('Invalid notification column');
    }

    $stmt = $pdo->prepare("UPDATE service_requests SET {$column} = ? WHERE tracking_id = ? LIMIT 1");
    $stmt->execute([date('Y-m-d H:i:s'), $trackingId]);
}

function vs_service_request_send_initial_notifications(PDO $pdo, array $context): array
{
    vs_service_request_ensure_schema($pdo);

    $trackingId = trim((string)($context['tracking_id'] ?? ''));
    if ($trackingId === '') {
        return [
            'success' => false,
            'message' => 'Missing tracking_id',
        ];
    }

    return vs_service_request_with_lock($trackingId, function () use ($pdo, $context, $trackingId): array {
        $stmt = $pdo->prepare('
            SELECT customer_notification_sent_at, admin_notification_sent_at
            FROM service_requests
            WHERE tracking_id = ?
            LIMIT 1
        ');
        $stmt->execute([$trackingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [
                'success' => false,
                'message' => 'Service request not found',
            ];
        }

        require_once __DIR__ . '/send_whatsapp.php';
        require_once __DIR__ . '/../config/admin_config.php';

        $formData = isset($context['form_data']) && is_array($context['form_data'])
            ? $context['form_data']
            : [];
        $selectedProducts = isset($context['selected_products']) && is_array($context['selected_products'])
            ? $context['selected_products']
            : [];

        $categorySlug = strtolower(trim((string)($context['category'] ?? '')));
        $categoryDisplay = trim((string)($context['category_display'] ?? ''));
        if ($categoryDisplay === '') {
            $categoryDisplay = $categorySlug === 'appointment'
                ? 'Appointment'
                : ucwords(str_replace('-', ' ', $categorySlug !== '' ? $categorySlug : 'service'));
        }

        $customerName = trim((string)($context['customer_name'] ?? $context['name'] ?? 'Customer'));
        $mobile = trim((string)($context['mobile'] ?? ''));
        $countryCode = trim((string)($context['country_code'] ?? ($formData['country_code'] ?? '')));
        $customCountryCode = trim((string)($context['custom_country_code'] ?? ($formData['custom_country_code'] ?? '')));
        $productsList = trim((string)($context['products_list'] ?? ''));
        if ($productsList === '') {
            $productsList = vs_service_request_products_list($selectedProducts, $categorySlug);
        }

        $results = [
            'success' => true,
            'customer' => [
                'success' => !empty($row['customer_notification_sent_at']),
                'message' => !empty($row['customer_notification_sent_at']) ? 'Already sent' : 'Not sent',
            ],
            'admin' => [
                'success' => !empty($row['admin_notification_sent_at']),
                'message' => !empty($row['admin_notification_sent_at']) ? 'Already sent' : 'Not sent',
            ],
        ];

        if (empty($row['customer_notification_sent_at']) && $mobile !== '') {
            try {
                if ($categorySlug === 'appointment') {
                    $customerResult = sendWhatsAppNotification('appointment_booked_payment_success', [
                        'mobile' => $mobile,
                        'country_code' => $countryCode,
                        'custom_country_code' => $customCountryCode,
                        'name' => $customerName !== '' ? $customerName : 'Customer',
                        'category' => 'Appointment',
                        'products_list' => $productsList,
                        'tracking_url' => $trackingId,
                    ]);
                } else {
                    $customerResult = sendWhatsAppNotification('service_received', [
                        'mobile' => $mobile,
                        'country_code' => $countryCode,
                        'custom_country_code' => $customCountryCode,
                        'name' => $customerName !== '' ? $customerName : 'Customer',
                        'category' => $categoryDisplay,
                        'products_list' => $productsList,
                        'tracking_url' => $trackingId,
                    ]);
                }

                $results['customer'] = $customerResult;
                if (!empty($customerResult['success'])) {
                    vs_service_request_mark_notification_sent($pdo, $trackingId, 'customer_notification_sent_at');
                }
            } catch (Throwable $e) {
                $results['customer'] = [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
                error_log('Service request customer WhatsApp failed for ' . $trackingId . ': ' . $e->getMessage());
            }
        }

        if (empty($row['admin_notification_sent_at'])) {
            try {
                $adminMobile = defined('ADMIN_WHATSAPP')
                    ? ADMIN_WHATSAPP
                    : (defined('WHATSAPP_BUSINESS_PHONE') ? WHATSAPP_BUSINESS_PHONE : '918975224444');

                $adminResult = sendWhatsAppNotification('admin_services_alert', [
                    'admin_mobile' => $adminMobile,
                    'customer_name' => $customerName !== '' ? $customerName : 'Customer',
                    'customer_mobile' => $mobile,
                    'category' => $categoryDisplay,
                    'products_list' => $productsList,
                    'tracking_id' => $trackingId,
                ]);

                $results['admin'] = $adminResult;
                if (!empty($adminResult['success'])) {
                    vs_service_request_mark_notification_sent($pdo, $trackingId, 'admin_notification_sent_at');
                }
            } catch (Throwable $e) {
                $results['admin'] = [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
                error_log('Service request admin WhatsApp failed for ' . $trackingId . ': ' . $e->getMessage());
            }
        }

        return $results;
    });
}
