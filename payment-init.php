<?php
// payment-init.php -- Unified for both service and appointment payments (DB-based)
$pageTitle = 'Payment | Vishnusudarshana';
require_once 'header.php';
require_once __DIR__ . '/config/db.php';

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

$stmt = $pdo->prepare("SELECT * FROM pending_payments WHERE payment_id = ? LIMIT 1");
$stmt->execute([$payment_id]);
$pending = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pending) {
    echo '<main class="main-content" style="background-color:var(--cream-bg);"><h2>Payment not found</h2>';
    echo '<p>Could not find payment details. Please try again.</p>';
    echo '<a href="services.php" class="review-back-link">&larr; Back to Services</a></main>';
    require_once 'footer.php';
    exit;
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
            
            setTimeout(function() {
                window.location.href = "payment-success.php?payment_id=" + encodeURIComponent(actualPaymentId);
            }, 800);
        }).catch(function() {
            // Even if DB update fails, proceed - data is safe with orderId in database
            loaderSubtext.textContent = 'Completing transaction...';
            setTimeout(function() {
                window.location.href = "payment-success.php?payment_id=" + encodeURIComponent(actualPaymentId);
            }, 500);
        });
    },
    modal: {
        ondismiss: function() {
            window.location.href = "payment-failed.php";
        }
    }
};
document.getElementById('rzpPayBtn').onclick = function(e){
    e.preventDefault();
    var rzp = new Razorpay(options);
    rzp.open();
};
</script>
<?php require_once 'footer.php'; ?>
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

<!-- Only one payment summary and card UI should be rendered. Duplicate block removed. -->

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
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
            
            setTimeout(function() {
                window.location.href = "payment-success.php?payment_id=" + encodeURIComponent(actualPaymentId);
            }, 800);
        }).catch(function() {
            // Even if DB update fails, proceed - data is safe with orderId in database
            loaderSubtext.textContent = 'Completing transaction...';
            setTimeout(function() {
                window.location.href = "payment-success.php?payment_id=" + encodeURIComponent(actualPaymentId);
            }, 500);
        });
    },
    modal: {
        ondismiss: function() {
            window.location.href = "payment-failed.php";
        }
    }
};
document.getElementById('rzpPayBtn').onclick = function(e){
    e.preventDefault();
    var rzp = new Razorpay(options);
    rzp.open();
};
</script>
<?php require_once 'footer.php'; ?>
