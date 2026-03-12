<?php
date_default_timezone_set('Asia/Kolkata');
$pageTitle = 'Payment Success | Vishnusudarshana';
/**
 * payment-success.php
 * 
 * Handles successful Razorpay payments for:
 * 1) Appointment bookings → appointments table
 * 2) Service requests → service_requests table
 * 
 * ARCHITECTURE: Session-Optional
 * - Primary source of truth: pending_payments table in database
 * - Session ($_SESSION['pending_payment']) is optional
 * - If session lost: rebuilds context from database using payment_id
 * - All payment data persists in database before user is redirected to payment gateway
 * 
 * FLOW:
 * 1) Validate payment_id from GET parameter
 * 2) Load pending_payment from session OR database
 * 3) Determine source (appointment/service)
 * 4) Process accordingly and insert into respective table
 * 5) Render success UI with tracking ID
 */

/* ======================
   STEP 1: BOOTSTRAP
   ====================== */

// Session is already started in header.php — DO NOT start here
require_once __DIR__ . '/config/db.php';

/**
 * Find an existing finalized booking by payment/order identifiers.
 * Used to keep payment-success idempotent when users revisit links.
 */
function vs_ps_find_existing_request(PDO $pdo, string $paymentId = '', string $orderId = '', string $razorpayPaymentId = ''): ?array
{
    $paymentId = trim($paymentId);
    $orderId = trim($orderId);
    $razorpayPaymentId = trim($razorpayPaymentId);

    $where = [];
    $params = [];

    if ($paymentId !== '') {
        $where[] = 'payment_id = ?';
        $params[] = $paymentId;
        $where[] = 'razorpay_payment_id = ?';
        $params[] = $paymentId;
    }

    if ($razorpayPaymentId !== '' && $razorpayPaymentId !== $paymentId) {
        $where[] = 'payment_id = ?';
        $params[] = $razorpayPaymentId;
        $where[] = 'razorpay_payment_id = ?';
        $params[] = $razorpayPaymentId;
    }

    if ($orderId !== '') {
        $where[] = 'razorpay_order_id = ?';
        $params[] = $orderId;
    }

    if (empty($where)) {
        return null;
    }

    $sql = 'SELECT id, tracking_id, category_slug, created_at
            FROM service_requests
            WHERE ' . implode(' OR ', $where) . '
            ORDER BY id DESC
            LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/**
 * Clean stale pending rows once a booking is confirmed.
 */
function vs_ps_cleanup_pending(PDO $pdo, string $paymentId = '', string $orderId = ''): void
{
    $paymentId = trim($paymentId);
    $orderId = trim($orderId);

    $where = [];
    $params = [];
    if ($paymentId !== '') {
        $where[] = 'payment_id = ?';
        $params[] = $paymentId;
    }
    if ($orderId !== '') {
        $where[] = 'razorpay_order_id = ?';
        $params[] = $orderId;
    }
    if (empty($where)) {
        return;
    }

    $sql = 'DELETE FROM pending_payments WHERE ' . implode(' OR ', $where);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

/**
 * Reused success UI for already-processed payment revisits.
 */
function vs_ps_render_processed_page(array $existingRequest): void
{
    $trackingId = (string)($existingRequest['tracking_id'] ?? '');
    $categorySlug = (string)($existingRequest['category_slug'] ?? '');
    $categoryLabel = $categorySlug !== '' ? ucwords(str_replace('-', ' ', $categorySlug)) : 'Service';
    $createdAt = (string)($existingRequest['created_at'] ?? '');

    require_once 'header.php';
    ?>
    <style>
        .vs-processed-shell {
            max-width: 760px;
            margin: 22px auto;
            padding: 10px 12px 22px;
        }
        .vs-processed-card {
            background: linear-gradient(155deg, #fffef9 0%, #fff5f5 52%, #fff9ef 100%);
            border: 1px solid rgba(128, 0, 0, 0.16);
            border-radius: 20px;
            box-shadow: 0 18px 44px rgba(128, 0, 0, 0.12);
            padding: 26px 22px 22px;
        }
        .vs-processed-badge {
            display: inline-block;
            background: #f0fff1;
            color: #0b7d2a;
            border: 1px solid #9bd8aa;
            border-radius: 999px;
            font-size: 0.84rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            padding: 5px 12px;
            margin-bottom: 10px;
        }
        .vs-processed-title {
            margin: 0;
            font-size: 1.62rem;
            color: #6f0000;
            line-height: 1.25;
        }
        .vs-processed-subtitle {
            margin: 9px 0 0;
            color: #5f4a4a;
            font-size: 1.03rem;
            line-height: 1.6;
        }
        .vs-processed-meta {
            margin: 18px 0 0;
            background: #fff;
            border: 1px solid #f0d7d7;
            border-radius: 14px;
            padding: 8px 12px;
        }
        .vs-processed-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px dashed #ecd2d2;
        }
        .vs-processed-row:last-child {
            border-bottom: 0;
        }
        .vs-processed-label {
            color: #8f5555;
            font-weight: 700;
            font-size: 0.95rem;
        }
        .vs-processed-value {
            color: #2f2f2f;
            font-weight: 700;
            text-align: right;
            word-break: break-word;
        }
        .vs-processed-value.tracking {
            color: #7f0000;
            letter-spacing: 0.02em;
        }
        .vs-processed-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }
        .vs-processed-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 16px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.96rem;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease, color 0.15s ease;
        }
        .vs-processed-btn:hover {
            transform: translateY(-1px);
        }
        .vs-processed-btn-primary {
            background: #800000;
            color: #fff;
            box-shadow: 0 8px 18px rgba(128, 0, 0, 0.24);
        }
        .vs-processed-btn-primary:hover {
            background: #670000;
        }
        .vs-processed-btn-secondary {
            background: #fff;
            color: #7a2121;
            border: 1px solid #e7c6c6;
        }
        .vs-processed-btn-secondary:hover {
            background: #fff3f3;
        }
        @media (max-width: 640px) {
            .vs-processed-title {
                font-size: 1.35rem;
            }
            .vs-processed-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            .vs-processed-value {
                text-align: left;
            }
        }
    </style>
    <main class="main-content vs-processed-shell">
        <section class="vs-processed-card">
            <div class="vs-processed-badge">Payment Completed</div>
            <h1 class="vs-processed-title">Payment Already Processed</h1>
            <p class="vs-processed-subtitle">Your payment link was already completed earlier. Your booking is confirmed and no further action is required.</p>
            <div class="vs-processed-meta">
                <?php if ($trackingId !== ''): ?>
                    <div class="vs-processed-row">
                        <div class="vs-processed-label">Tracking ID</div>
                        <div class="vs-processed-value tracking"><?= htmlspecialchars($trackingId, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($createdAt !== ''): ?>
                    <div class="vs-processed-row">
                        <div class="vs-processed-label">Processed On</div>
                        <div class="vs-processed-value"><?= htmlspecialchars(date('d-M-Y h:i A', strtotime($createdAt)), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endif; ?>
                <div class="vs-processed-row">
                    <div class="vs-processed-label">Category</div>
                    <div class="vs-processed-value"><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
            <div class="vs-processed-actions">
                <?php if ($trackingId !== ''): ?>
                    <a href="track.php?id=<?= urlencode($trackingId) ?>" class="vs-processed-btn vs-processed-btn-primary">Track Your Request</a>
                <?php endif; ?>
                <a href="services.php" class="vs-processed-btn vs-processed-btn-secondary">&larr; Back to Services</a>
            </div>
        </section>
    </main>
    <?php
    require_once 'footer.php';
    exit;
}

// Always define $payment_id for paid flow
$payment_id = isset($_GET['payment_id']) ? trim($_GET['payment_id']) : '';
// Handle free (₹0.00) service requests (no payment_id)
if (isset($_GET['free']) && $_GET['free'] == 1) {
    // Insert directly into service_requests table
    require_once __DIR__ . '/config/db.php';
    session_start();
    $form_data = $_POST ?? [];
    unset($form_data['product_ids'], $form_data['qty'], $form_data['make_payment']);
    $category = $_POST['category'] ?? '';
    $customerName = $_POST['full_name'] ?? '';
    $mobile = $_POST['mobile'] ?? '';
    $email = $_POST['email'] ?? '';
    $city = $_POST['city'] ?? '';
    $products = $_POST['product_ids'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $totalAmount = 0;
    // Compose selected_products array
    $selected_products = [];
    if (is_array($products)) {
        foreach ($products as $pid) {
            $selected_products[] = [
                'id' => $pid,
                'qty' => $qtys[$pid] ?? 1
            ];
        }
    }
    // Generate tracking ID
    $tracking_id = 'VDSK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $createdAt = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO service_requests (tracking_id, category_slug, customer_name, mobile, email, city, form_data, selected_products, total_amount, payment_id, payment_status, service_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '', 'Free', 'Received', ?)");
    $stmt->execute([
        $tracking_id,
        $category,
        $customerName ?: 'N/A',
        $mobile ?: 'N/A',
        $email ?: '',
        $city ?: '',
        json_encode($form_data),
        json_encode($selected_products),
        $totalAmount,
        $createdAt
    ]);
    // Show success page
    require_once 'header.php';
    ?>
    <main class="main-content" style="background-color:var(--cream-bg);">
        <h1 class="review-title">Request Submitted</h1>
        <div class="review-card">
            <h2 class="section-title">Thank You!</h2>
            <p class="success-text">
                Your free service request has been submitted successfully.<br>
                Our team is processing your request.<br><br>
                We will contact you shortly with details.<br>
                <b>Tracking ID:</b> <?php echo htmlspecialchars($tracking_id); ?>
            </p>
            <a href="services.php" class="pay-btn">Back to Services</a>
        </div>
    </main>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
        html,body{font-family:'Marcellus',serif!important;}
        .main-content { max-width:480px;margin:0 auto;padding:18px; }
        .review-title { text-align:center;font-size:1.2em;margin-bottom:16px; }
        .review-card { background:#f9eaea;border-radius:14px;padding:16px;text-align:center; }
        .section-title { color:#800000;font-weight:600;margin-bottom:10px; }
        .success-text { color:#333;margin-bottom:18px; }
        .pay-btn { display:inline-block;background:#800000;color:#fff;padding:12px 28px;
                   border-radius:8px;text-decoration:none;font-weight:600; }
    </style>
    <?php
    require_once 'footer.php';
    exit;
}

// LOAD FROM DATABASE FIRST (source of truth for all payment types)
if ($payment_id !== '') {
    $stmt = $pdo->prepare("SELECT * FROM pending_payments WHERE payment_id = ?");
    $stmt->execute([$payment_id]);
    $dbRecord = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $dbRecord = false;
}

// Capture identifiers early for idempotent checks.
$incoming_razorpay_payment_id = isset($_GET['razorpay_payment_id']) ? trim((string)$_GET['razorpay_payment_id']) : '';
$razorpay_order_id = isset($dbRecord['razorpay_order_id']) ? (string)$dbRecord['razorpay_order_id'] : '';

// If this payment/order is already finalized, show existing booking instead of inserting again.
try {
    $existingRequest = vs_ps_find_existing_request($pdo, (string)$payment_id, (string)$razorpay_order_id, (string)$incoming_razorpay_payment_id);
    if ($existingRequest) {
        try {
            vs_ps_cleanup_pending($pdo, (string)$payment_id, (string)$razorpay_order_id);
        } catch (Throwable $cleanupError) {
            error_log('payment-success pending cleanup skipped: ' . $cleanupError->getMessage());
        }
        vs_ps_render_processed_page($existingRequest);
    }
} catch (Throwable $idempotencyError) {
    error_log('payment-success idempotency precheck failed: ' . $idempotencyError->getMessage());
}

// Reconstruct pending payment data from database
$pending = [];
if ($dbRecord) {
    $pending = [
        'source' => $dbRecord['source'],
        'customer_details' => json_decode($dbRecord['customer_details'], true) ?? [],
        'appointment_form' => json_decode($dbRecord['appointment_form'], true) ?? [],
        'form_data' => json_decode($dbRecord['form_data'], true) ?? [],
        'products' => json_decode($dbRecord['selected_products'], true) ?? [],
        'category' => $dbRecord['category'],
        'category_slug' => $dbRecord['category'],
        'total_amount' => $dbRecord['total_amount']
    ];
    // Store in session for consistency with rest of codebase
    $_SESSION['pending_payment'] = $pending;
} else if ($payment_id !== '') {
    error_log('No pending payment found in database for payment_id: ' . $payment_id);
}

// If context is missing after trying session and database, show friendly message
if ($payment_id !== '' && empty($pending)) {
    error_log('Payment successful but context missing: no pending data found for payment_id=' . $payment_id);
    require_once 'header.php';
    ?>
    <main class="main-content" style="background-color:var(--cream-bg);">
        <h1 class="review-title">Payment Received</h1>
        <div class="review-card">
            <h2 class="section-title">Thank You!</h2>
            <p class="success-text">
                Your payment has been received successfully.<br>
                Our team is processing your request.<br>
                <br>
                We will contact you shortly with details.
            </p>
            <a href="services.php" class="pay-btn">Back to Services</a>
        </div>
    </main>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
        html,body{font-family:'Marcellus',serif!important;}
        .main-content { max-width:480px;margin:0 auto;padding:18px; }
        .review-title { text-align:center;font-size:1.2em;margin-bottom:16px; }
        .review-card { background:#f9eaea;border-radius:14px;padding:16px;text-align:center; }
        .section-title { color:#800000;font-weight:600;margin-bottom:10px; }
        .success-text { color:#333;margin-bottom:18px; }
        .pay-btn { display:inline-block;background:#800000;color:#fff;padding:12px 28px;
                   border-radius:8px;text-decoration:none;font-weight:600; }
    </style>
    <?php
    require_once 'footer.php';
    exit;
}

// Extract Razorpay order ID from pending_payments if available
$razorpay_order_id = $dbRecord['razorpay_order_id'] ?? $razorpay_order_id ?? null;

/* ======================
   UNIFIED SERVICE FLOW
   All services (including appointments) follow this single path
   ====================== */

// Create service tables if not exist
$pdo->exec("CREATE TABLE IF NOT EXISTS service_requests (
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
    payment_status VARCHAR(20),
    service_status VARCHAR(50),
    created_at DATETIME NOT NULL
);");

// Use pending data from database as source of truth (session not required)
$category = $pending['category_slug'] ?? $pending['category'] ?? '';

// Log if category is empty but continue processing
// Payment is recorded in pending_payments table, so recovery is still possible
if ($category === '') {
    error_log('Service payment: no category found. payment_id=' . $payment_id . '. Will create default tracking record.');
    $category = 'unknown-service';
}

// Generate tracking ID
$tracking_id = 'VDSK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

// Extract all data from pending (database is source of truth)
// For appointments, form_data or appointment_form may contain the details
$formData = $pending['form_data'] ?? [];
if (empty($formData) && !empty($pending['appointment_form'])) {
    $formData = $pending['appointment_form'];
}

$customerName = $pending['customer_details']['full_name'] ?? $formData['full_name'] ?? '';
$mobile       = $pending['customer_details']['mobile'] ?? $formData['mobile'] ?? '';
$email        = $pending['customer_details']['email'] ?? $formData['email'] ?? '';
$city         = $pending['customer_details']['city'] ?? $formData['city'] ?? '';
$products     = $pending['products'] ?? [];
$totalAmount  = $pending['total_amount'] ?? 0;
$alreadyProcessedInInsert = false;
$razorpay_payment_id = $incoming_razorpay_payment_id;

// Insert service request (log errors but continue - data is still in pending_payments table)
try {
    $createdAt = date('Y-m-d H:i:s');
    $razorpay_payment_id = isset($_GET['razorpay_payment_id']) ? $_GET['razorpay_payment_id'] : (isset($dbRecord['razorpay_payment_id']) ? $dbRecord['razorpay_payment_id'] : '');

    $existingDuringInsert = vs_ps_find_existing_request($pdo, (string)$payment_id, (string)$razorpay_order_id, (string)$razorpay_payment_id);
    if ($existingDuringInsert) {
        $tracking_id = (string)($existingDuringInsert['tracking_id'] ?? $tracking_id);
        $alreadyProcessedInInsert = true;
    } else {
        $stmt = $pdo->prepare("INSERT INTO service_requests (
        tracking_id, category_slug, customer_name, mobile, email, city,
        form_data, selected_products, total_amount, payment_id, razorpay_order_id, razorpay_payment_id, payment_status, service_status, created_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Paid', 'Received', ?
    )");
        $stmt->execute([
        $tracking_id,
        $category,
        $customerName ?: 'N/A',
        $mobile ?: 'N/A',
        $email ?: '',
        $city ?: '',
        json_encode($formData),
        json_encode($products),
        $totalAmount,
        $payment_id,
        $razorpay_order_id,
        $razorpay_payment_id,
        $createdAt
        ]);

        // Format products list for WhatsApp message
        $productsList = '';
        if (!empty($products) && is_array($products)) {
            $productNames = [];
            foreach ($products as $product) {
                // Prefer already-available names to avoid extra queries
                if (!empty($product['name'])) {
                    $productNames[] = $product['name'];
                    continue;
                }
                if (!empty($product['product_name'])) {
                    $productNames[] = $product['product_name'];
                    continue;
                }
                // Fallback: fetch by ID if present
                $productId = $product['id'] ?? $product['product_id'] ?? null;
                if ($productId) {
                    try {
                        $prodStmt = $pdo->prepare('SELECT product_name FROM products WHERE id = ? LIMIT 1');
                        $prodStmt->execute([$productId]);
                        $prodName = $prodStmt->fetchColumn();
                        if ($prodName) {
                            $productNames[] = $prodName;
                        } else {
                            error_log('Product name not found for ID: ' . $productId);
                            $productNames[] = 'Service Item';
                        }
                    } catch (Exception $e) {
                        error_log('Product lookup failed for ID ' . $productId . ': ' . $e->getMessage());
                        $productNames[] = 'Service Item';
                    }
                }
            }
            // Join with comma and space
            $productsList = implode(', ', $productNames);
        }
        if (empty($productsList)) {
            $productsList = ucwords(str_replace('-', ' ', $category));
        }

        // WhatsApp: Appointment Booked + Payment Successful (only for appointments)
        if ($category === 'appointment') {
            require_once __DIR__ . '/helpers/send_whatsapp.php';
            try {
                // Send automatic WhatsApp notification to customer
                $whatsappResult = sendWhatsAppNotification('appointment_booked_payment_success', [
                    'mobile' => $mobile,
                    'name' => $customerName ?: 'Customer',
                    'category' => 'Appointment',
                    'products_list' => $productsList,
                    'tracking_url' => $tracking_id  // Ensure tracking_id is passed
                ]);
                    // Send WhatsApp alert to admin
                    // Use ADMIN_WHATSAPP from config/admin_config.php
                    require_once __DIR__ . '/config/admin_config.php';
                    $adminMobile = defined('ADMIN_WHATSAPP') ? ADMIN_WHATSAPP : (defined('WHATSAPP_BUSINESS_PHONE') ? WHATSAPP_BUSINESS_PHONE : '918975224444');
                    sendWhatsAppNotification('admin_services_alert', [
                        'admin_mobile' => $adminMobile,
                        'customer_name' => $customerName ?: 'Customer',
                        'customer_mobile' => $mobile,
                        'category' => 'Appointment',
                        'products_list' => $productsList,
                        'tracking_id' => $tracking_id
                    ]);

                if (!$whatsappResult['success']) {
                    error_log('WhatsApp notification failed for appointment ' . $tracking_id . ': ' . $whatsappResult['message']);
                } else {
                    error_log('WhatsApp notification sent for appointment ' . $tracking_id);
                }
            } catch (Exception $e) {
                error_log('WhatsApp error for appointment ' . $tracking_id . ': ' . $e->getMessage());
            }
        } else if ($category !== 'appointment') {
            // For other services (Birth & Child, Marriage, Astrology, Muhurat, Pooja, Vastu)
            require_once __DIR__ . '/helpers/send_whatsapp.php';
            try {
                $serviceCategoryDisplay = ucwords(str_replace('-', ' ', $category));
                $whatsappResult = sendWhatsAppNotification('service_received', [
                    'mobile' => $mobile,
                    'name' => $customerName ?: 'Customer',
                    'category' => $serviceCategoryDisplay,
                    'products_list' => $productsList,
                    'tracking_url' => $tracking_id  // Ensure tracking_id is passed
                ]);
                    // Send WhatsApp alert to admin
                    require_once __DIR__ . '/config/admin_config.php';
                    $adminMobile = defined('ADMIN_WHATSAPP') ? ADMIN_WHATSAPP : (defined('WHATSAPP_BUSINESS_PHONE') ? WHATSAPP_BUSINESS_PHONE : '918975224444');
                    sendWhatsAppNotification('admin_services_alert', [
                        'admin_mobile' => $adminMobile,
                        'customer_name' => $customerName ?: 'Customer',
                        'customer_mobile' => $mobile,
                        'category' => $serviceCategoryDisplay,
                        'products_list' => $productsList,
                        'tracking_id' => $tracking_id
                    ]);

                if (!$whatsappResult['success']) {
                    error_log('WhatsApp notification failed for service ' . $tracking_id . ': ' . $whatsappResult['message']);
                } else {
                    error_log('WhatsApp notification sent for service ' . $tracking_id);
                }
            } catch (Exception $e) {
                error_log('WhatsApp error for service ' . $tracking_id . ': ' . $e->getMessage());
            }
        }
    }
    
    // Old code reference (commented out - replaced with new event-based system above)
    /*
    try {
        // Old implementation - no longer used
        sendWhatsAppMessage(
            $mobile,
            'appointment_confirmation',
            'en',
            [
                'name' => $customerName,
                'tracking_code' => $tracking_id
            ]
        );
    } catch (Throwable $e) {
        error_log('WhatsApp booking failed: ' . $e->getMessage());
    }
    */
} catch (Throwable $e) {
    error_log('Service request insert failed: ' . $e->getMessage() . ' (payment_id=' . $payment_id . ', tracking_id=' . $tracking_id . ')');
    try {
        $existingAfterError = vs_ps_find_existing_request($pdo, (string)$payment_id, (string)$razorpay_order_id, (string)$razorpay_payment_id);
        if ($existingAfterError) {
            $tracking_id = (string)($existingAfterError['tracking_id'] ?? $tracking_id);
            $alreadyProcessedInInsert = true;
        } else {
            echo '<main class="main-content" style="background-color:var(--cream-bg);"><h2>Payment Received</h2>';
            echo '<p>There was an error saving your request. Please contact support with your payment ID: '.htmlspecialchars($payment_id).'</p>';
            echo '<a href="services.php" class="pay-btn">Back to Services</a></main>';
            require_once 'footer.php';
            exit;
        }
    } catch (Throwable $fallbackError) {
        error_log('Service request fallback lookup failed: ' . $fallbackError->getMessage());
        echo '<main class="main-content" style="background-color:var(--cream-bg);"><h2>Payment Received</h2>';
        echo '<p>There was an error saving your request. Please contact support with your payment ID: '.htmlspecialchars($payment_id).'</p>';
        echo '<a href="services.php" class="pay-btn">Back to Services</a></main>';
        require_once 'footer.php';
        exit;
    }
}

$successStatusMessage = $alreadyProcessedInInsert
    ? 'This payment was already finalized earlier. Your booking remains confirmed.'
    : 'Our team will contact you shortly.<br>Keep your tracking ID for reference.';
$categoryDisplay = ucwords(str_replace('-', ' ', (string)$category));
$amountDisplay = number_format((float)$totalAmount, 2);
$customerDisplay = trim((string)$customerName) !== '' ? (string)$customerName : 'Customer';
$mobileDisplay = trim((string)$mobile) !== '' ? (string)$mobile : 'Not Available';
$paymentDisplay = trim((string)$razorpay_payment_id) !== '' ? (string)$razorpay_payment_id : (string)$payment_id;

/* ======================
   RENDER SERVICE UI
   ====================== */
require_once 'header.php';
?>

<main class="main-content success-shell">
    <section class="success-card">
        <div class="success-badge">&#9989; Payment Successful</div>
        <h1 class="success-title">Booking Confirmed</h1>
        <p id="payment-status-msg" class="success-subtitle">
            <?= $successStatusMessage ?>
        </p>

        <div class="success-tracking-wrap">
            <div class="success-tracking-label">&#128269; Tracking ID</div>
            <div class="success-tracking-id"><?= htmlspecialchars($tracking_id) ?></div>
        </div>

        <div class="success-details">
            <div class="success-row">
                <span>&#127991; Category</span>
                <strong><?= htmlspecialchars($categoryDisplay) ?></strong>
            </div>
            <div class="success-row">
                <span>&#128179; Payment ID</span>
                <strong><?= htmlspecialchars($paymentDisplay) ?></strong>
            </div>
            <div class="success-row">
                <span>&#8377; Amount Paid</span>
                <strong>&#8377;<?= htmlspecialchars($amountDisplay) ?></strong>
            </div>
            <div class="success-row">
                <span>&#128100; Customer</span>
                <strong><?= htmlspecialchars($customerDisplay) ?></strong>
            </div>
            <div class="success-row">
                <span>&#128222; Mobile</span>
                <strong><?= htmlspecialchars($mobileDisplay) ?></strong>
            </div>
        </div>

        <div class="success-actions">
            <a href="track.php?id=<?= urlencode($tracking_id) ?>" class="success-btn success-btn-primary">&#128269; Track Your Request</a>
            <a href="services.php" class="success-btn success-btn-secondary">&larr; Back to Services</a>
        </div>
    </section>
</main>
<script>
// Poll backend every 3 seconds for payment status
const paymentId = <?= json_encode($payment_id) ?>;
const statusMsg = document.getElementById('payment-status-msg');
let pollInterval = null;

function checkPaymentStatus() {
    fetch('ajax/check_payment_status.php?payment_id=' + encodeURIComponent(paymentId))
        .then(res => res.json())
        .then(data => {
            const statusNormalized = String((data && data.payment_status) ? data.payment_status : '').toLowerCase();
            if (data.success && (statusNormalized === 'paid' || statusNormalized === 'free')) {
                statusMsg.textContent = 'Payment verified. Your booking will be confirmed soon.';
                if (pollInterval) clearInterval(pollInterval);
            } else if (data.success && data.payment_status) {
                // Still pending, keep polling
            } else {
                statusMsg.textContent = 'Payment received. Verifying appointment...';
            }
        })
        .catch(() => {
            statusMsg.textContent = 'Payment received. Verifying appointment...';
        });
}

pollInterval = setInterval(checkPaymentStatus, 3000);
window.addEventListener('DOMContentLoaded', checkPaymentStatus);
</script>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
    html,body{font-family:'Marcellus',serif!important;}
    .success-shell { max-width: 760px; margin: 0 auto; padding: 20px 12px 26px; }
    .success-card {
        background: linear-gradient(150deg, #fffef9 0%, #fff5f6 56%, #fff9ef 100%);
        border: 1px solid rgba(128, 0, 0, 0.16);
        border-radius: 20px;
        box-shadow: 0 18px 44px rgba(128, 0, 0, 0.12);
        padding: 24px 20px 22px;
    }
    .success-badge {
        display: inline-block;
        background: #f0fff1;
        color: #0b7d2a;
        border: 1px solid #9bd8aa;
        border-radius: 999px;
        font-size: 0.86rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        padding: 5px 12px;
        margin-bottom: 10px;
    }
    .success-title {
        margin: 0;
        color: #6f0000;
        font-size: 1.68rem;
        line-height: 1.2;
    }
    .success-subtitle {
        margin: 10px 0 0;
        color: #5f4a4a;
        font-size: 1.02rem;
        line-height: 1.55;
    }
    .success-tracking-wrap {
        margin-top: 18px;
        padding: 12px 14px;
        background: #fff;
        border: 1px solid #f0d7d7;
        border-radius: 14px;
        text-align: center;
    }
    .success-tracking-label {
        color: #8f5555;
        font-weight: 700;
        font-size: 0.95rem;
        margin-bottom: 6px;
    }
    .success-tracking-id {
        color: #7f0000;
        font-weight: 800;
        letter-spacing: 0.04em;
        font-size: 1.2rem;
        word-break: break-word;
    }
    .success-details {
        margin-top: 14px;
        background: #fff;
        border: 1px solid #f0d7d7;
        border-radius: 14px;
        padding: 8px 12px;
    }
    .success-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px dashed #ecd2d2;
        color: #5e4545;
    }
    .success-row:last-child { border-bottom: 0; }
    .success-row span { font-weight: 700; }
    .success-row strong {
        color: #2f2f2f;
        text-align: right;
        word-break: break-word;
    }
    .success-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 18px;
    }
    .success-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        padding: 0 16px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 0.96rem;
        font-weight: 700;
        transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }
    .success-btn:hover { transform: translateY(-1px); }
    .success-btn-primary {
        background: #800000;
        color: #fff;
        box-shadow: 0 8px 18px rgba(128, 0, 0, 0.24);
    }
    .success-btn-primary:hover { background: #670000; }
    .success-btn-secondary {
        background: #fff;
        color: #7a2121;
        border: 1px solid #e7c6c6;
    }
    .success-btn-secondary:hover { background: #fff3f3; }
    @media (max-width: 640px) {
        .success-title { font-size: 1.4rem; }
        .success-row { flex-direction: column; gap: 4px; }
        .success-row strong { text-align: left; }
    }
</style>
<?php
require_once 'footer.php';

// Clear service-related session data ONLY AFTER UI rendering
unset($_SESSION['pending_payment']);
unset($_SESSION['book_appointment']);
unset($_SESSION['appointment_products']);

// DELETE from pending_payments after successful insertion/confirmation
try {
    vs_ps_cleanup_pending($pdo, (string)$payment_id, (string)$razorpay_order_id);
} catch (Throwable $e) {
    error_log('Failed to delete pending_payments record for payment_id=' . $payment_id . '. Error: ' . $e->getMessage());
}
