<?php
session_start();
require_once 'header.php';
require_once __DIR__ . '/config/db.php';

// Detect source: appointment or service
$source = $_GET['source'] ?? '';
$appointmentId = $_GET['appointment_id'] ?? null;

// Always set payment context in session and pass as GET param to payment gateway/callback
if ($source === 'appointment') {
    $_SESSION['pending_payment_source'] = 'appointment';
} else {
    $_SESSION['pending_payment_source'] = 'service';
}

if ($source === 'appointment') {
    // Appointment payment flow: prefer session data; fallback to existing record when id given
    $pending = $_SESSION['pending_payment'] ?? [];
    $appointment = null;
    if ($appointmentId) {
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
        $stmt->execute([$appointmentId]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Prefer product_id on appointment if column exists
    $selected_products = [];
    $total_amount = 0;
    $hasProductIdCol = false;
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'product_id'");
        $hasProductIdCol = (bool)$colCheck->fetch();
    } catch (Throwable $e) {}

    if ($hasProductIdCol && !empty($appointment['product_id'])) {
        $pid = (int)$appointment['product_id'];
        $stmtP = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
        $stmtP->execute([$pid]);
        $p = $stmtP->fetch(PDO::FETCH_ASSOC);
        if ($p) {
            $selected_products[] = [
                'id' => $p['id'],
                'name' => $p['product_name'],
                'desc' => $p['short_description'],
                'price' => $p['price'],
                'qty' => 1,
                'line_total' => $p['price']
            ];
            $total_amount = $p['price'];
        }
    }

    // Fallbacks: session-based selection or default appointment product
    if (empty($selected_products)) {
        $appointmentProducts = $_SESSION['appointment_products'] ?? null;
        if ($appointmentProducts && !empty($appointmentProducts['product_ids'])) {
            $productIds = $appointmentProducts['product_ids'];
            $quantities = $appointmentProducts['quantities'];
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $productStmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND is_active = 1");
            $productStmt->execute($productIds);
            $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($products as $product) {
                $pid = $product['id'];
                $qty = isset($quantities[$pid]) ? max(1, intval($quantities[$pid])) : 1;
                $line_total = $product['price'] * $qty;
                $selected_products[] = [
                    'id' => $pid,
                    'name' => $product['product_name'],
                    'desc' => $product['short_description'],
                    'price' => $product['price'],
                    'qty' => $qty,
                    'line_total' => $line_total
                ];
                $total_amount += $line_total;
            }
        }
    }

    if (empty($selected_products)) {
        $productStmt = $pdo->query("SELECT * FROM products WHERE category_slug = 'appointment' AND is_active = 1 ORDER BY price ASC LIMIT 1");
        $appointmentProduct = $productStmt->fetch(PDO::FETCH_ASSOC);
        if ($appointmentProduct) {
            $selected_products[] = [
                'id' => $appointmentProduct['id'],
                'name' => $appointmentProduct['product_name'],
                'desc' => $appointmentProduct['short_description'] ?? '',
                'price' => $appointmentProduct['price'],
                'qty' => 1,
                'line_total' => $appointmentProduct['price']
            ];
            $total_amount = $appointmentProduct['price'];
        } else {
            echo '<main class="main-content" style="background-color:#FFD700;"><h2>No appointment services available</h2>';
            echo '<p>Please contact support to complete your appointment booking.</p>';
            echo '<a href="services.php" class="review-back-link">&larr; Back to Services</a></main>';
            require_once 'footer.php';
            exit;
        }
    }
    
    if (!empty($pending) && ($pending['source'] ?? '') === 'appointment' && !$appointmentId) {
        // Build from session appointment_form
        $customer = $pending['customer_details'] ?? [];
        $form = $pending['appointment_form'] ?? [];
        $sel = $pending['products_selection'] ?? [];
        $selected_products = [];
        $total_amount = 0;
        $productIds = $sel['product_ids'] ?? [];
        $quantities = $sel['quantities'] ?? [];

        if (!empty($productIds)) {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $productStmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND is_active = 1");
            $productStmt->execute($productIds);
            $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($products as $product) {
                $pid = $product['id'];
                $qty = isset($quantities[$pid]) ? max(1, intval($quantities[$pid])) : 1;
                $line_total = $product['price'] * $qty;
                $selected_products[] = [
                    'id' => $pid,
                    'name' => $product['product_name'],
                    'desc' => $product['short_description'],
                    'price' => $product['price'],
                    'qty' => $qty,
                    'line_total' => $line_total
                ];
                $total_amount += $line_total;
            }
        }
        if (empty($selected_products)) {
            $productStmt = $pdo->query("SELECT * FROM products WHERE category_slug = 'appointment' AND is_active = 1 ORDER BY price ASC LIMIT 1");
            $appointmentProduct = $productStmt->fetch(PDO::FETCH_ASSOC);
            if ($appointmentProduct) {
                $selected_products[] = [
                    'id' => $appointmentProduct['id'],
                    'name' => $appointmentProduct['product_name'],
                    'desc' => $appointmentProduct['short_description'] ?? '',
                    'price' => $appointmentProduct['price'],
                    'qty' => 1,
                    'line_total' => $appointmentProduct['price']
                ];
                $total_amount = $appointmentProduct['price'];
            } else {
                echo '<main class="main-content" style="background-color:#FFD700;"><h2>No appointment services available</h2>';
                echo '<p>Please contact support to complete your appointment booking.</p>';
                echo '<a href="services.php" class="review-back-link">&larr; Back to Services</a></main>';
                require_once 'footer.php';
                exit;
            }
        }
        $_SESSION['pending_payment'] = [
            'source' => 'appointment',
            'customer_details' => $customer,
            'appointment_form' => $form,
            'products' => $selected_products,
            'total_amount' => $total_amount
        ];
    } else {
        // Existing record path (legacy)
        if (!$appointment) {
            echo '<main class="main-content" style="background-color:#FFD700;"><h2>Appointment not found</h2>';
            echo '<a href="services.php" class="review-back-link">&larr; Back to Services</a></main>';
            require_once 'footer.php';
            exit;
        }
        // Build from stored appointment with product_id preference
        $customer = [
            'full_name' => $appointment['customer_name'],
            'mobile'    => $appointment['mobile'],
            'email'     => $appointment['email'] ?? ''
        ];
        // selected_products and total_amount are prepared by existing logic below
    }
    // For UI after preparing session
    $pending = $_SESSION['pending_payment'] ?? [];
    $customer = $pending['customer_details'] ?? $customer ?? [];

    // When redirecting to payment gateway, append source=appointment to callback URL
    if (!empty($pending)) {
        if (isset($pending['payment_callback_url'])) {
            $cb = $pending['payment_callback_url'];
            if (strpos($cb, 'source=') === false) {
                $cb .= (strpos($cb, '?') === false ? '?' : '&') . 'source=appointment';
                $_SESSION['pending_payment']['payment_callback_url'] = $cb;
            }
        }
    }
} else {
    // Existing service payment flow
    $category = $_POST['category'] ?? '';
    $form_data = $_POST;
    $product_ids = $_POST['product_ids'] ?? [];
    $quantities = $_POST['qty'] ?? [];

    // Validate products and total
    if (!$category || empty($product_ids) || empty($quantities)) {
        echo '<main class="main-content" style="background-color:#FFD700;"><h2>Invalid payment request</h2>';
        echo '<p>Please select at least one service/product.</p>';
        echo '<a href="service-review.php?category=' . htmlspecialchars($category) . '" class="review-back-link">&larr; Back to Review</a></main>';
        require_once 'footer.php';
        exit;
    }

    // Fetch product details from DB
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($product_ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare summary and total
    $selected_products = [];
    $total_amount = 0;
    foreach ($products as $product) {
        $pid = $product['id'];
        $qty = isset($quantities[$pid]) ? max(1, intval($quantities[$pid])) : 1;
        $line_total = $product['price'] * $qty;
        $selected_products[] = [
            'id' => $pid,
            'name' => $product['product_name'],
            'desc' => $product['short_description'],
            'price' => $product['price'],
            'qty' => $qty,
            'line_total' => $line_total
        ];
        $total_amount += $line_total;
    }
    if ($total_amount <= 0) {
        echo '<main class="main-content" style="background-color:#FFD700;"><h2>Invalid total amount</h2>';
        echo '<p>Total amount must be greater than zero.</p>';
        echo '<a href="service-review.php?category=' . htmlspecialchars($category) . '" class="review-back-link">&larr; Back to Review</a></main>';
        require_once 'footer.php';
        exit;
    }

    // Store in session
    $_SESSION['pending_payment'] = [
        'source' => 'service',
        'category' => $category,
        'customer_details' => $form_data,
        'form_data' => $form_data,
        'products' => $selected_products,
        'total_amount' => $total_amount
    ];
    
    $customer = $form_data;
}

// UI
?>
<main class="main-content" style="background-color:#FFD700;">
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
                <span class="details-value"><?php echo htmlspecialchars($customer['mobile'] ?? ''); ?></span>
            </div>
            <div class="details-row">
                <span class="details-label">Email:</span>
                <span class="details-value"><?php echo htmlspecialchars($customer['email'] ?? ''); ?></span>
            </div>
        </div>
    </div>
    <?php if ($source === 'appointment'): ?>
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
    </div>
    <?php endif; ?>
    <button class="pay-btn" id="rzpPayBtn" style="margin-top:18px;">Proceed to Secure Payment</button>
    <?php if ($source === 'appointment'): ?>
    <a href="service-detail.php?service=appointment" class="review-back-link">&larr; Back to Appointment</a>
    <?php else: ?>
    <a href="service-review.php?category=<?php echo htmlspecialchars($category ?? ''); ?>" class="review-back-link">&larr; Back to Review</a>
    <?php endif; ?>
</main>

<?php
// ========== STORE PENDING PAYMENT DATA BEFORE RAZORPAY REDIRECT ==========
// This MUST execute before user clicks the payment button
// Data survives session loss, page refresh, and browser close

$pending = $_SESSION['pending_payment'] ?? [];
$customer = $pending['customer_details'] ?? [];
$total_amount = $pending['total_amount'] ?? 0;
$paymentSource = $pending['source'] ?? 'service';

// Generate unique order_id for database storage
// This will be mapped to actual Razorpay payment_id in payment-success.php
$orderId = 'ORD-' . time() . '-' . bin2hex(random_bytes(8));

// Store ALL pending payment data to database for session loss recovery
try {
    $insertStmt = $pdo->prepare("
        INSERT INTO pending_payments (
            payment_id, 
            source, 
            customer_details, 
            appointment_form, 
            form_data, 
            selected_products, 
            category, 
            total_amount
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insertStmt->execute([
        $orderId,
        $paymentSource,
        json_encode($pending['customer_details'] ?? []),
        json_encode($pending['appointment_form'] ?? []),
        json_encode($pending['form_data'] ?? []),
        json_encode($pending['products'] ?? []),
        $pending['category'] ?? '',
        $total_amount
    ]);
    // Success - data is persisted in database
} catch (Throwable $e) {
    error_log('Failed to store pending payment to database: ' . $e->getMessage());
    // Log error but continue - payment flow should not be blocked
}
?>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
<?php
$amount_in_paise = (int)round($total_amount * 100);
$name = isset($customer['full_name']) ? addslashes($customer['full_name']) : '';
$email = isset($customer['email']) ? addslashes($customer['email']) : '';
$mobile = isset($customer['mobile']) ? addslashes($customer['mobile']) : '';
$description = ($paymentSource === 'appointment') ? 'Appointment Booking Fee' : 'Service Payment';
?>

const options = {
    key: "rzp_test_a3iYwPnLkGMlDM",
    amount: <?php echo $amount_in_paise; ?>,
    currency: "INR",
    name: "Vishnusudarshana Dharmik Sanskar Kendra",
    description: "<?php echo $description; ?>",
    prefill: {
        name: "<?php echo $name; ?>",
        email: "<?php echo $email; ?>",
        contact: "<?php echo $mobile; ?>"
    },
    theme: {
        color: "#800000"
    },
    handler: function (response) {
        // Razorpay payment successful - update database and redirect
        var actualPaymentId = response.razorpay_payment_id;
        var orderId = "<?php echo $orderId; ?>";
        
        // Update database to link orderId with actual Razorpay payment_id
        fetch('payment-update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'order_id=' + encodeURIComponent(orderId) + '&payment_id=' + encodeURIComponent(actualPaymentId)
        }).then(function() {
            window.location.href = "payment-success.php?payment_id=" + encodeURIComponent(actualPaymentId);
        }).catch(function() {
            // Even if DB update fails, proceed - data is safe with orderId in database
            window.location.href = "payment-success.php?payment_id=" + encodeURIComponent(actualPaymentId);
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
<style>
/* ...reuse review page styles for consistency... */
.main-content { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 18px; box-shadow: 0 4px 24px #e0bebe33; padding: 18px 12px 28px 12px; }
.review-title { font-size: 1.18em; font-weight: bold; margin-bottom: 18px; text-align: center; }
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
</style>
