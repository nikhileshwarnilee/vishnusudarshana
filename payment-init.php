<?php
// payment-init.php -- Unified for both service and appointment payments (DB-based)
$pageTitle = 'Payment | Vishnusudarshana';
require_once 'header.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/payment_link_map.php';

// Load Razorpay keys
$razorpayKeyId = getenv('RAZORPAY_KEY_ID');
$razorpayKeySecret = getenv('RAZORPAY_KEY_SECRET');
if (!$razorpayKeyId || !$razorpayKeySecret) {
    if (file_exists(__DIR__ . '/.env')) {
        $envContent = file_get_contents(__DIR__ . '/.env');
        $lines = explode("\n", $envContent);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                if ($key === 'RAZORPAY_KEY_ID') $razorpayKeyId = $value;
                if ($key === 'RAZORPAY_KEY_SECRET') $razorpayKeySecret = $value;
            }
        }
    }
}
if (!$razorpayKeyId || !$razorpayKeySecret) {
    die('Razorpay API key/secret not set. Please set RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET in your .env file.');
}

$payment_id = $_GET['payment_id'] ?? null;
if (!$payment_id) {
    echo '<main class="main-content" style="background-color:var(--cream-bg);"><h2>Invalid payment request</h2>';
    echo '<p>Payment ID missing. Please start your booking again.</p>';
    echo '<a href="services.php" class="review-back-link">&larr; Back to Services</a></main>';
    require_once 'footer.php';
    exit;
}

/**
 * Output-safe redirect helper for this page.
 * header.php is loaded at the top, so HTTP header redirects can fail after output starts.
 */
if (!function_exists('vs_safe_redirect')) {
    function vs_safe_redirect($url)
    {
        $safeUrl = htmlspecialchars((string)$url, ENT_QUOTES, 'UTF-8');
        $jsUrl = json_encode((string)$url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        echo '<script>window.location.href=' . $jsUrl . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . $safeUrl . '"></noscript>';
        echo '<p>If you are not redirected automatically, <a href="' . $safeUrl . '">continue here</a>.</p>';
    }
}

if (!function_exists('vs_render_processed_payment_page')) {
    function vs_render_processed_payment_page(array $serviceRow): void
    {
        $trackingId = (string)($serviceRow['tracking_id'] ?? '');
        $createdAt = (string)($serviceRow['created_at'] ?? '');
        $categorySlug = (string)($serviceRow['category_slug'] ?? '');
        $categoryLabel = $categorySlug !== '' ? ucwords(str_replace('-', ' ', $categorySlug)) : 'Service';
        $resolvedPaymentId = trim((string)($serviceRow['razorpay_payment_id'] ?? ''));
        if ($resolvedPaymentId === '') {
            $resolvedPaymentId = trim((string)($serviceRow['payment_id'] ?? ''));
        }

        if ($resolvedPaymentId !== '') {
            $successUrl = 'payment-success.php?payment_id=' . urlencode($resolvedPaymentId);
            echo '<main class="main-content" style="background-color:var(--cream-bg);"><h2>Redirecting...</h2>';
            echo '<p>Your payment is already confirmed. Taking you to the success page.</p>';
            vs_safe_redirect($successUrl);
            echo '</main>';
            require_once 'footer.php';
            exit;
        }

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
}

$stmt = $pdo->prepare("SELECT * FROM pending_payments WHERE payment_id = ? LIMIT 1");
$stmt->execute([$payment_id]);
$pending = $stmt->fetch(PDO::FETCH_ASSOC);

if ($pending) {
    try {
        $pendingOrderId = trim((string)($pending['razorpay_order_id'] ?? ''));
        $pendingPaymentId = trim((string)($pending['payment_id'] ?? ''));
        $clauses = [];
        $params = [];

        if ($pendingOrderId !== '') {
            $clauses[] = 'razorpay_order_id = ?';
            $params[] = $pendingOrderId;
        }

        $tokens = [];
        if ((string)$payment_id !== '') {
            $tokens[] = (string)$payment_id;
        }
        if ($pendingPaymentId !== '' && $pendingPaymentId !== (string)$payment_id) {
            $tokens[] = $pendingPaymentId;
        }

        foreach ($tokens as $token) {
            $clauses[] = '(payment_id = ? OR razorpay_payment_id = ?)';
            $params[] = $token;
            $params[] = $token;
        }

        if (!empty($clauses)) {
            $sql = "
                SELECT id, tracking_id, category_slug, payment_id, razorpay_order_id, created_at
                FROM service_requests
                WHERE " . implode(' OR ', $clauses) . "
                ORDER BY id DESC
                LIMIT 1
            ";
            $existingStmt = $pdo->prepare($sql);
            $existingStmt->execute($params);
            $existingRow = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if ($existingRow) {
                vs_render_processed_payment_page($existingRow);
            }
        }
    } catch (Throwable $e) {
        error_log('payment-init existing booking precheck failed for payment_id=' . (string)$payment_id . ': ' . $e->getMessage());
    }
}

if (!$pending) {
    $resolvedService = null;
    $resolvedOrderId = null;
    $payMapRow = null;

    try {
        // Prefer local map resolution (works even when ORD -> pay mapping already changed in DB).
        $payMapRow = vs_paymap_find($pdo, (string)$payment_id);
        if ($payMapRow) {
            $mappedOrderId = trim((string)($payMapRow['razorpay_order_id'] ?? ''));
            $mappedPayId = trim((string)($payMapRow['razorpay_payment_id'] ?? ''));

            if ($mappedOrderId !== '') {
                $resolvedOrderId = $mappedOrderId;

                // If pending row still exists under updated pay_* id, redirect to valid payment-init URL.
                $pendingOrderStmt = $pdo->prepare("SELECT payment_id FROM pending_payments WHERE razorpay_order_id = ? LIMIT 1");
                $pendingOrderStmt->execute([$mappedOrderId]);
                $pendingByOrder = $pendingOrderStmt->fetch(PDO::FETCH_ASSOC);
                if ($pendingByOrder && !empty($pendingByOrder['payment_id']) && $pendingByOrder['payment_id'] !== $payment_id) {
                    $redirectUrl = 'payment-init.php?payment_id=' . urlencode((string)$pendingByOrder['payment_id']);
                    echo '<main class="main-content" style="background-color:var(--cream-bg);"><h2>Redirecting...</h2>';
                    echo '<p>We found your latest payment link and are taking you there.</p>';
                    vs_safe_redirect($redirectUrl);
                    echo '</main>';
                    require_once 'footer.php';
                    exit;
                }

                $serviceOrderStmt = $pdo->prepare("
                    SELECT id, tracking_id, category_slug, payment_id, razorpay_order_id, created_at
                    FROM service_requests
                    WHERE razorpay_order_id = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $serviceOrderStmt->execute([$mappedOrderId]);
                $resolvedService = $serviceOrderStmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$resolvedService && $mappedPayId !== '' && $mappedPayId !== $payment_id) {
                $serviceMappedPayStmt = $pdo->prepare("
                    SELECT id, tracking_id, category_slug, payment_id, razorpay_order_id, created_at
                    FROM service_requests
                    WHERE payment_id = ? OR razorpay_payment_id = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $serviceMappedPayStmt->execute([$mappedPayId, $mappedPayId]);
                $resolvedService = $serviceMappedPayStmt->fetch(PDO::FETCH_ASSOC);

                if (!$resolvedService) {
                    $pendingMappedPayStmt = $pdo->prepare("SELECT payment_id FROM pending_payments WHERE payment_id = ? LIMIT 1");
                    $pendingMappedPayStmt->execute([$mappedPayId]);
                    $pendingByMappedPay = $pendingMappedPayStmt->fetch(PDO::FETCH_ASSOC);
                    if ($pendingByMappedPay) {
                        $redirectUrl = 'payment-init.php?payment_id=' . urlencode($mappedPayId);
                        echo '<main class="main-content" style="background-color:var(--cream-bg);"><h2>Redirecting...</h2>';
                        echo '<p>We found your active payment link and are taking you there.</p>';
                        vs_safe_redirect($redirectUrl);
                        echo '</main>';
                        require_once 'footer.php';
                        exit;
                    }
                }
            }
        }

        // First: direct lookup by payment id (covers active pay_* links).
        if (!$resolvedService) {
            $serviceStmt = $pdo->prepare("
                SELECT id, tracking_id, category_slug, payment_id, razorpay_order_id, created_at
                FROM service_requests
                WHERE payment_id = ? OR razorpay_payment_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $serviceStmt->execute([$payment_id, $payment_id]);
            $resolvedService = $serviceStmt->fetch(PDO::FETCH_ASSOC);
        }

        // Second: if old ORD link was replaced by pay_ id, resolve using Razorpay order receipt.
        if (!$resolvedService && $resolvedOrderId === null && strpos((string)$payment_id, 'ORD-') === 0) {
            require_once __DIR__ . '/vendor/autoload.php';
            $rzApi = new \Razorpay\Api\Api($razorpayKeyId, $razorpayKeySecret);
            $orders = $rzApi->order->all([
                'receipt' => (string)$payment_id,
                'count' => 1
            ]);
            $orderItems = (is_array($orders) && isset($orders['items']) && is_array($orders['items'])) ? $orders['items'] : [];
            if (!empty($orderItems) && !empty($orderItems[0]['id'])) {
                $resolvedOrderId = (string)$orderItems[0]['id'];
                try {
                    vs_paymap_upsert($pdo, (string)$payment_id, (string)$resolvedOrderId, null, null, null);
                } catch (Throwable $mapE) {
                    error_log('payment-init paymap upsert failed (receipt fallback): ' . $mapE->getMessage());
                }

                // If pending row still exists under updated pay_* id, redirect to valid payment-init URL.
                $pendingOrderStmt = $pdo->prepare("SELECT payment_id FROM pending_payments WHERE razorpay_order_id = ? LIMIT 1");
                $pendingOrderStmt->execute([$resolvedOrderId]);
                $pendingByOrder = $pendingOrderStmt->fetch(PDO::FETCH_ASSOC);
                if ($pendingByOrder && !empty($pendingByOrder['payment_id']) && $pendingByOrder['payment_id'] !== $payment_id) {
                    $redirectUrl = 'payment-init.php?payment_id=' . urlencode((string)$pendingByOrder['payment_id']);
                    echo '<main class="main-content" style="background-color:var(--cream-bg);"><h2>Redirecting...</h2>';
                    echo '<p>We found your latest payment link and are taking you there.</p>';
                    vs_safe_redirect($redirectUrl);
                    echo '</main>';
                    require_once 'footer.php';
                    exit;
                }

                // If booking is already finalized, show a clear processed message with tracking link.
                $serviceOrderStmt = $pdo->prepare("
                    SELECT id, tracking_id, category_slug, payment_id, razorpay_order_id, created_at
                    FROM service_requests
                    WHERE razorpay_order_id = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $serviceOrderStmt->execute([$resolvedOrderId]);
                $resolvedService = $serviceOrderStmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    } catch (Throwable $e) {
        error_log('payment-init fallback lookup failed for payment_id=' . (string)$payment_id . ': ' . $e->getMessage());
    }

    if ($resolvedService) {
        vs_render_processed_payment_page($resolvedService);
    }

    echo '<main class="main-content" style="background-color:var(--cream-bg);"><h2>Payment not found</h2>';
    echo '<p>Could not find payment details for this link. If payment was already completed, it may have been processed.</p>';
    if ($resolvedOrderId) {
        echo '<p><small>Order reference: ' . htmlspecialchars($resolvedOrderId, ENT_QUOTES, 'UTF-8') . '</small></p>';
    }
    echo '<a href="services.php" class="review-back-link">&larr; Back to Services</a></main>';
    require_once 'footer.php';
    exit;
}

if (strpos((string)$payment_id, 'ORD-') === 0) {
    try {
        vs_paymap_upsert(
            $pdo,
            (string)$payment_id,
            isset($pending['razorpay_order_id']) ? (string)$pending['razorpay_order_id'] : null,
            null,
            isset($pending['source']) ? (string)$pending['source'] : null,
            isset($pending['category']) ? (string)$pending['category'] : null
        );
    } catch (Throwable $e) {
        error_log('payment-init paymap upsert failed (pending hit): ' . $e->getMessage());
    }
}

$customer = json_decode($pending['customer_details'] ?? '{}', true);
$selected_products = json_decode($pending['selected_products'] ?? '[]', true);
if (!is_array($selected_products)) $selected_products = [];
$total_amount = $pending['total_amount'] ?? 0;
$paymentSource = $pending['source'] ?? 'service';
$category = $pending['category'] ?? '';
$razorpay_order_id = $pending['razorpay_order_id'] ?? null;

// Razorpay order creation (if not already created)
require_once __DIR__ . '/vendor/autoload.php';
use Razorpay\Api\Api;
$api = new Api($razorpayKeyId, $razorpayKeySecret);

if (!$razorpay_order_id) {
    $amount_in_paise = (int)round($total_amount * 100);
    if ($total_amount < 1) {
        echo '<main class="main-content" style="background-color:var(--cream-bg);"><h2>Invalid total amount</h2>';
        echo '<p>Total amount must be at least ₹1.00 to proceed with payment.</p>';
        echo '<a href="services.php" class="review-back-link">&larr; Back to Services</a></main>';
        require_once 'footer.php';
        exit;
    }
    $orderData = [
        'receipt'         => $payment_id,
        'amount'          => $amount_in_paise,
        'currency'        => 'INR',
        'payment_capture' => 1
    ];
    $razorpayOrder = $api->order->create($orderData);
    $razorpay_order_id = $razorpayOrder['id'];
    // Update DB with razorpay_order_id
    $updateStmt = $pdo->prepare("UPDATE pending_payments SET razorpay_order_id = ? WHERE payment_id = ?");
    $updateStmt->execute([$razorpay_order_id, $payment_id]);
    if (strpos((string)$payment_id, 'ORD-') === 0) {
        try {
            vs_paymap_upsert(
                $pdo,
                (string)$payment_id,
                (string)$razorpay_order_id,
                null,
                isset($pending['source']) ? (string)$pending['source'] : null,
                isset($pending['category']) ? (string)$pending['category'] : null
            );
        } catch (Throwable $e) {
            error_log('payment-init paymap upsert failed (order create): ' . $e->getMessage());
        }
    }
}

$orderId = $razorpay_order_id;

// UI
?>
<style>@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');html,body{font-family:'Marcellus',serif!important;}</style>
<style>
/* ...reuse review page styles for consistency... */
.main-content { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 18px; box-shadow: 0 4px 24px #e0bebe33; padding: 18px 12px 28px 12px; }
.review-title { font-size: 1.18em; font-weight: bold; margin-bottom: 18px; text-align: center; color: #800000; }
.review-card { background: #f9eaea; border-radius: 14px; box-shadow: 0 2px 8px #e0bebe33; padding: 16px; margin-bottom: 18px; }
.section-title { font-size: 1.05em; color: #800000; margin-bottom: 10px; font-weight: 600; }
.details-list { display: flex; flex-direction: column; gap: 8px; }
.details-row { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px dashed #e0bebe; padding-bottom: 4px; }
.details-label { color: #a03c3c; font-weight: 500; margin-right: 6px; }
.details-value { color: #333; max-width: 60%; word-break: break-word; }
.product-list { margin: 0; padding: 0; list-style: none; }
.product-item { display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f3caca; padding: 10px 0; }
.product-item:last-child { border-bottom: none; }
.product-info { flex: 1; }
.product-name { font-weight: 600; color: #800000; font-size: 1em; }
.product-desc { font-size: 0.95em; color: #555; margin: 2px 0 2px 0; }
.qty-controls { display: flex; align-items: center; gap: 4px; }
.line-total { font-size: 0.98em; color: #800000; font-weight: 600; min-width: 60px; text-align: right; }
.sticky-total { position: sticky; bottom: 0; background: #fff; padding: 14px 0 0 0; text-align: right; font-size: 1.13em; border-top: 1px solid #e0bebe; box-shadow: 0 -2px 8px #e0bebe22; z-index: 10; }
.pay-btn { width: 100%; background: #800000; color: #fff; border: none; border-radius: 8px; padding: 14px 0; font-size: 1.08em; font-weight: 600; margin-top: 10px; cursor: pointer; box-shadow: 0 2px 8px #80000022; transition: background 0.15s; }
.pay-btn:disabled { background: #ccc; color: #fff; cursor: not-allowed; }
.pay-btn.loading { background: #6a0000; color: #fff; }
.pay-status-hint { margin-top: 10px; min-height: 18px; font-size: 0.92em; text-align: center; color: #7a2121; }
.pay-status-hint.info { color: #7a2121; }
.pay-status-hint.success { color: #1a8917; }
.pay-status-hint.warn { color: #8a5b00; }
.pay-btn-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    margin-right: 8px;
    border: 2px solid rgba(255, 255, 255, 0.55);
    border-top-color: #fff;
    border-radius: 50%;
    vertical-align: -2px;
    animation: payBtnSpin 0.8s linear infinite;
}
@keyframes payBtnSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.review-back-link { display:block;text-align:center;margin-top:18px;color:#1a8917;font-size:0.98em;text-decoration:none; }
@media (max-width: 700px) { .main-content { padding: 8px 2px 16px 2px; border-radius: 0; } }

/* Payment Processing Loader Overlay */
.payment-loader-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.98);
    z-index: 9999;
    justify-content: center;
    align-items: center;
    flex-direction: column;
}
.payment-loader-overlay.active {
    display: flex;
}
.loader-content {
    text-align: center;
    padding: 20px;
}
.loader-spinner {
    width: 60px;
    height: 60px;
    margin: 0 auto 20px;
    border: 4px solid #f3caca;
    border-top: 4px solid #800000;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.loader-checkmark {
    display: none;
    width: 60px;
    height: 60px;
    margin: 0 auto 20px;
    border-radius: 50%;
    background: #1a8917;
    position: relative;
}
.loader-checkmark::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 36px;
    font-weight: bold;
}
.loader-text {
    font-size: 1.2em;
    color: #800000;
    font-weight: 600;
    margin-bottom: 10px;
}
.loader-subtext {
    font-size: 0.95em;
    color: #666;
}
</style>
<main class="main-content" style="background-color:var(--cream-bg);">
    <h1 class="review-title">Payment Summary</h1>
    <div class="review-card">
        <h2 class="section-title">Customer Details</h2>
        <div class="details-list">
            <div class="details-row">
                <span class="details-label">Name:</span>
                <span class="details-value"><?php echo htmlspecialchars($customer['full_name'] ?? ''); ?></span>
            </div>
            <div class="details-row">
                <span class="details-label">Mobile:</span>
                <span class="details-value">
                <?php
                    $cc = $customer['country_code'] ?? '+91';
                    if ($cc === 'other') {
                        $cc = $customer['custom_country_code'] ?? '';
                    }
                    echo htmlspecialchars(trim($cc . ' ' . ($customer['mobile'] ?? '')));
                ?>
                </span>
            </div>
            <div class="details-row">
                <span class="details-label">Email:</span>
                <span class="details-value"><?php echo htmlspecialchars($customer['email'] ?? ''); ?></span>
            </div>
        </div>
    </div>
    <?php if ($paymentSource === 'appointment'): ?>
    <div class="review-card">
        <h2 class="section-title">Appointment Details</h2>
        <div class="details-list">
            <?php 
            $appointmentForm = $pending['appointment_form'] ?? [];
            $appointmentType = $appointmentForm['appointment_type'] ?? 'online';
            $preferredDate = $appointmentForm['preferred_date'] ?? '';
            $preferredTime = $appointmentForm['preferred_time'] ?? '';
            ?>
            <div class="details-row">
                <span class="details-label">Appointment Type:</span>
                <span class="details-value"><?php echo htmlspecialchars(ucfirst($appointmentType)); ?></span>
            </div>
            <div class="details-row">
                <span class="details-label">Preferred Date:</span>
                <span class="details-value"><?php echo htmlspecialchars($preferredDate); ?></span>
            </div>
            <div class="details-row">
                <span class="details-label">Preferred Time:</span>
                <span class="details-value"><?php echo htmlspecialchars($preferredTime); ?></span>
            </div>
        </div>
    </div>
    <div class="review-card">
        <h2 class="section-title">Selected Services</h2>
        <?php if (!empty($selected_products)): ?>
        <ul class="product-list">
            <?php foreach ($selected_products as $item): ?>
            <li class="product-item">
                <div class="product-info">
                    <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                    <?php if (!empty($item['desc'])): ?>
                    <div class="product-desc"><?php echo htmlspecialchars($item['desc']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($item['variant_value_name'])): ?>
                    <div class="product-desc">
                        <?php
                            $variantName = !empty($item['variant_name']) ? $item['variant_name'] : 'Variant';
                            echo htmlspecialchars($variantName . ': ' . $item['variant_value_name']);
                        ?>
                    </div>
                    <?php endif; ?>
                    <div class="product-price">₹<?php echo number_format($item['price'], 2); ?> × <?php echo $item['qty']; ?> = ₹<?php echo number_format($item['line_total'], 2); ?></div>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <div class="details-list">
            <div class="details-row">
                <span class="details-label">Appointment Service:</span>
                <span class="details-value">₹<?php echo number_format($total_amount, 2); ?></span>
            </div>
        </div>
        <?php endif; ?>
        <div class="sticky-total">
            Total: <span id="totalPrice">₹<?php echo number_format($total_amount, 2); ?></span>
        </div>
    </div>
    <?php else: ?>
    <div class="review-card">
        <h2 class="section-title">Selected Services</h2>
        <?php if (!empty($selected_products)): ?>
        <ul class="product-list">
            <?php foreach ($selected_products as $prod): ?>
            <li class="product-item">
                <div class="product-info">
                    <div class="product-name"><?php echo htmlspecialchars($prod['name']); ?></div>
                    <div class="product-desc"><?php echo htmlspecialchars($prod['desc']); ?></div>
                    <?php if (!empty($prod['variant_value_name'])): ?>
                    <div class="product-desc">
                        <?php
                            $variantName = !empty($prod['variant_name']) ? $prod['variant_name'] : 'Variant';
                            echo htmlspecialchars($variantName . ': ' . $prod['variant_value_name']);
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="qty-controls">
                    <span class="details-label">Qty:</span>
                    <span class="details-value"><?php echo $prod['qty']; ?></span>
                </div>
                <div class="line-total">₹<?php echo number_format($prod['line_total'], 2); ?></div>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="sticky-total">
            Total: <span id="totalPrice">₹<?php echo number_format($total_amount, 2); ?></span>
        </div>
        <?php else: ?>
        <div class="details-list">
            <div class="details-row">
                <span class="details-label">No services found for this payment.</span>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <button class="pay-btn" id="rzpPayBtn" style="margin-top:18px;">Proceed to Secure Payment</button>
    <div id="paymentStatusHint" class="pay-status-hint" aria-live="polite"></div>
    <?php if ($paymentSource === 'appointment'): ?>
    <a href="service-detail.php?service=appointment" class="review-back-link">&larr; Back to Appointment</a>
    <?php else: ?>
    <a href="service-review.php?category=<?php echo htmlspecialchars($category ?? ''); ?>" class="review-back-link">&larr; Back to Review</a>
    <?php endif; ?>
</main>

<!-- Payment Processing Loader Overlay -->
<div class="payment-loader-overlay" id="paymentLoader">
    <div class="loader-content">
        <div class="loader-spinner" id="loaderSpinner"></div>
        <div class="loader-checkmark" id="loaderCheckmark"></div>
        <div class="loader-text" id="loaderText">Processing Payment...</div>
        <div class="loader-subtext" id="loaderSubtext">Please wait while we confirm your payment</div>
    </div>
</div>

<?php
$amount_in_paise = (int)round($total_amount * 100);
$name = isset($customer['full_name']) ? addslashes($customer['full_name']) : '';
$email = isset($customer['email']) ? addslashes($customer['email']) : '';
$cc = isset($customer['country_code']) ? $customer['country_code'] : '+91';
if ($cc === 'other') {
    $cc = isset($customer['custom_country_code']) ? $customer['custom_country_code'] : '';
}
$mobile = isset($customer['mobile']) ? addslashes(trim($cc . $customer['mobile'])) : '';
$description = ($paymentSource === 'appointment') ? 'Appointment Booking Fee' : 'Service Payment';
?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
var paymentId = "<?php echo addslashes((string)$payment_id); ?>";
var orderId = "<?php echo addslashes((string)$orderId); ?>";
var paymentFailedUrl = "payment-failed.php?payment_id=<?php echo urlencode((string)$payment_id); ?>";
var statusCheckUrl = "ajax/check_payment_and_booking_status.php";
var payBtn = document.getElementById('rzpPayBtn');
var paymentStatusHint = document.getElementById('paymentStatusHint');
var payBtnLabel = payBtn ? payBtn.textContent : 'Proceed to Secure Payment';
var payBtnMode = 'pay';
var attemptStorageKey = 'vds_payment_attempted_' + paymentId;
var attemptExpiryMs = 6 * 60 * 60 * 1000;

function setHint(message, type) {
    if (!paymentStatusHint) {
        return;
    }
    paymentStatusHint.className = 'pay-status-hint' + (type ? (' ' + type) : '');
    paymentStatusHint.textContent = message || '';
}

function getAttemptState() {
    try {
        var raw = localStorage.getItem(attemptStorageKey);
        if (!raw) {
            return false;
        }
        var parsed = JSON.parse(raw);
        if (!parsed || parsed.attempted !== true) {
            return false;
        }
        var ts = Number(parsed.ts || 0);
        if (!ts || (Date.now() - ts) > attemptExpiryMs) {
            localStorage.removeItem(attemptStorageKey);
            return false;
        }
        return true;
    } catch (e) {
        return false;
    }
}

function setAttemptState(attempted) {
    try {
        if (attempted) {
            localStorage.setItem(attemptStorageKey, JSON.stringify({ attempted: true, ts: Date.now() }));
        } else {
            localStorage.removeItem(attemptStorageKey);
        }
    } catch (e) {
        // Ignore storage errors.
    }
}

function setButtonMode(mode) {
    payBtnMode = (mode === 'check') ? 'check' : 'pay';
    payBtnLabel = (payBtnMode === 'check') ? 'Check Payment Status' : 'Proceed to Secure Payment';
    if (payBtn && !payBtn.classList.contains('loading')) {
        payBtn.textContent = payBtnLabel;
    }
}

function setPayButtonLoading(isLoading, loadingText) {
    if (!payBtn) {
        return;
    }
    if (isLoading) {
        payBtn.disabled = true;
        payBtn.classList.add('loading');
        payBtn.innerHTML = '<span class="pay-btn-spinner"></span>' + (loadingText || 'Opening Secure Gateway...');
    } else {
        payBtn.disabled = false;
        payBtn.classList.remove('loading');
        payBtn.textContent = payBtnLabel;
    }
}

function buildStatusCheckUrl() {
    return statusCheckUrl
        + '?payment_id=' + encodeURIComponent(paymentId)
        + '&order_id=' + encodeURIComponent(orderId);
}

function routeTo(url, fallbackUrl) {
    var target = (url && String(url).trim() !== '') ? String(url) : fallbackUrl;
    window.location.href = target;
}

function openRazorpayCheckout() {
    setAttemptState(true);
    setButtonMode('check');
    setPayButtonLoading(true, 'Opening Secure Gateway...');
    try {
        var rzp = new Razorpay(options);
        rzp.open();
    } catch (error) {
        setPayButtonLoading(false);
        routeTo(paymentFailedUrl, paymentFailedUrl);
    }
}

function checkPaymentStatusAndRoute(allowCheckoutOnUnpaid) {
    setPayButtonLoading(true, 'Checking Status...');
    setHint('Checking latest payment status...', 'info');

    fetch(buildStatusCheckUrl(), { credentials: 'same-origin' })
        .then(function(res) {
            return res.json();
        })
        .then(function(data) {
            if (!data || !data.success) {
                if (allowCheckoutOnUnpaid) {
                    setHint('Could not verify status. Opening secure gateway...', 'warn');
                    openRazorpayCheckout();
                } else {
                    setPayButtonLoading(false);
                    setHint('Could not verify status. Please try again.', 'warn');
                }
                return;
            }

            var state = String(data.state || '').toLowerCase();
            if (state === 'success') {
                setAttemptState(false);
                setHint('Payment confirmed. Redirecting...', 'success');
                routeTo(data.redirect_url, 'payment-success.php?payment_id=' + encodeURIComponent(paymentId));
                return;
            }

            if (state === 'failed') {
                setAttemptState(false);
                setHint('Payment failed. Redirecting to retry page...', 'warn');
                routeTo(data.redirect_url, paymentFailedUrl);
                return;
            }

            if (state === 'unpaid') {
                if (allowCheckoutOnUnpaid) {
                    setHint('No completed payment found. Opening secure gateway...', 'info');
                    openRazorpayCheckout();
                } else {
                    setAttemptState(false);
                    setHint('No completed payment found. Redirecting to retry page...', 'warn');
                    routeTo(data.redirect_url, paymentFailedUrl);
                }
                return;
            }

            if (state === 'pending' || state === 'processing') {
                setAttemptState(true);
                setButtonMode('check');
                setPayButtonLoading(false);
                setHint(data.message || 'Payment is processing. Please click "Check Payment Status" again in a few seconds.', 'warn');
                return;
            }

            if (allowCheckoutOnUnpaid) {
                setHint('Status unclear. Opening secure gateway...', 'warn');
                openRazorpayCheckout();
            } else {
                setPayButtonLoading(false);
                setHint('Status unclear. Please try again in a moment.', 'warn');
            }
        })
        .catch(function() {
            if (allowCheckoutOnUnpaid) {
                setHint('Network issue while checking status. Opening secure gateway...', 'warn');
                openRazorpayCheckout();
            } else {
                setPayButtonLoading(false);
                setHint('Network issue while checking status. Please retry.', 'warn');
            }
        });
}

const options = {
    key: "<?php echo htmlspecialchars($razorpayKeyId); ?>",
    amount: <?php echo $amount_in_paise; ?>,
    currency: "INR",
    name: "Vishnusudarshana Dharmik Sanskar Kendra",
    description: "<?php echo $description; ?>",
    order_id: "<?php echo $razorpay_order_id; ?>",
    prefill: {
        name: "<?php echo $name; ?>",
        email: "<?php echo $email; ?>",
        contact: "<?php echo $mobile; ?>"
    },
    theme: {
        color: "#800000"
    },
    handler: function (response) {
        // Show payment processing loader
        var loader = document.getElementById('paymentLoader');
        var spinner = document.getElementById('loaderSpinner');
        var checkmark = document.getElementById('loaderCheckmark');
        var loaderText = document.getElementById('loaderText');
        var loaderSubtext = document.getElementById('loaderSubtext');
        
        loader.classList.add('active');
        loaderText.textContent = 'Payment Successful!';
        loaderSubtext.textContent = 'Verifying payment details...';
        
        // Razorpay payment successful - update database and redirect
        var actualPaymentId = response.razorpay_payment_id;
        var orderId = "<?php echo $orderId; ?>";
        
        // Update database to link orderId with actual Razorpay payment_id
        fetch('payment-update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'order_id=' + encodeURIComponent(orderId) + '&payment_id=' + encodeURIComponent(actualPaymentId)
        }).then(function() {
            // Show success checkmark briefly before redirect
            spinner.style.display = 'none';
            checkmark.style.display = 'block';
            loaderText.textContent = 'Payment Verified!';
            loaderSubtext.textContent = 'Redirecting to confirmation page...';
            setAttemptState(false);
            
            setTimeout(function() {
                window.location.href = "payment-success.php?payment_id=" + encodeURIComponent(actualPaymentId);
            }, 800);
        }).catch(function() {
            // Even if DB update fails, proceed - data is safe with orderId in database
            loaderSubtext.textContent = 'Completing transaction...';
            setAttemptState(false);
            setTimeout(function() {
                window.location.href = "payment-success.php?payment_id=" + encodeURIComponent(actualPaymentId);
            }, 500);
        });
    },
    modal: {
        ondismiss: function() {
            setPayButtonLoading(false);
            routeTo(paymentFailedUrl, paymentFailedUrl);
        }
    }
};

if (getAttemptState()) {
    setButtonMode('check');
    setHint('Checking your last payment status...', 'info');
    setTimeout(function() {
        checkPaymentStatusAndRoute(false);
    }, 300);
} else {
    setButtonMode('pay');
}

if (payBtn) {
    payBtn.onclick = function(e) {
        e.preventDefault();
        if (payBtnMode === 'check') {
            checkPaymentStatusAndRoute(false);
        } else {
            checkPaymentStatusAndRoute(true);
        }
    };
}
</script>
<?php require_once 'footer.php'; ?>
