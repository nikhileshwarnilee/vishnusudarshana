<?php
$pageTitle = 'Review & Select Services | Vishnusudarshana';
require_once 'header.php';
require_once __DIR__ . '/config/db.php';

// Step 2: Read input
$category = $_GET['category'] ?? '';
$form_data = $_POST ?? [];

if ($category === 'appointment') {
    // Validate session data
    if (!empty($form_data)) {
        $_SESSION['book_appointment'] = $form_data;
    } else if (!empty($_SESSION['book_appointment'])) {
        $form_data = $_SESSION['book_appointment'];
    }
    // Fetch products for 'appointment'
    $stmt = $pdo->prepare('SELECT * FROM products WHERE category_slug = ? AND is_active = 1 ORDER BY price ASC');
    $stmt->execute(['appointment']);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Backend validation
    $required = ['full_name', 'mobile', 'appointment_type', 'preferred_date']; // preferred_time is now optional
    $errors = [];
    foreach ($required as $field) {
        if (empty($form_data[$field]) || !is_string($form_data[$field]) || trim($form_data[$field]) === '') {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }
    // Validate appointment_type
    $valid_types = ['Online', 'Offline'];
    if (!in_array($form_data['appointment_type'] ?? '', $valid_types, true)) {
        $errors[] = 'Invalid appointment type.';
    }
    // Validate date (IST)
    $nowUtc = new DateTime('now', new DateTimeZone('UTC'));
    $nowUtc->setTimezone(new DateTimeZone('Asia/Kolkata'));
    $todayIST = $nowUtc->format('Y-m-d');
    $maxDate = date('Y-m-d', strtotime('+30 days', strtotime($todayIST)));
    $date = $form_data['preferred_date'] ?? '';
    if ($date < $todayIST || $date > $maxDate) {
        $errors[] = 'Invalid preferred date.';
    }
    // For appointment, bypass generic product selection validation
    if ($category === 'appointment') {
        $sessionData = !empty($_SESSION['book_appointment']) ? $_SESSION['book_appointment'] : $form_data;
        $requiredFields = ['full_name', 'mobile', 'preferred_date', 'appointment_type'];
        foreach ($requiredFields as $field) {
            if (empty($sessionData[$field]) || !is_string($sessionData[$field]) || trim($sessionData[$field]) === '') {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        if (empty($sessionData)) {
            $errors[] = 'Appointment form data missing.';
        }
        // Do NOT require product selection at this stage for appointment
    } else {
        // Validate product selection (on POST) for other categories
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $product_ids = $_POST['product_ids'] ?? [];
            if (empty($product_ids) || !is_array($product_ids)) {
                $errors[] = 'Please select at least one service.';
            }
        }
    }
    // Reject direct access without session data
    if (empty($form_data)) {
        $errors[] = 'Appointment details are required.';
    }
    if (!empty($errors)) {
        echo '<h2>Validation Error</h2>';
        echo '<ul style="color:#d00;">';
        foreach ($errors as $err) echo '<li>' . htmlspecialchars($err) . '</li>';
        echo '</ul>';
        echo '<a href="service-form.php?category=appointment">&larr; Back to Appointment Form</a>';
        exit;
    }
} else {
    if (!$category || empty($form_data)) {
        echo '<h2>Missing information</h2>';
        echo '<p>Category and form data are required.</p>';
        echo '<a href="services.php">&larr; Back to Services</a>';
        exit;
    }
    $stmt = $pdo->prepare('SELECT * FROM products WHERE category_slug = ? AND is_active = 1 ORDER BY price ASC');
    $stmt->execute([$category]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?><main class="main-content" style="background-color:var(--cream-bg);">
    <h1 class="review-title">Review &amp; Select Services</h1>
    <div class="review-card">
        <h2 class="section-title">Your Submitted Details</h2>
        <div class="details-list">
            <?php foreach ($form_data as $key => $val): ?>
                <?php if ($key === 'custom_country_code') continue; ?>
                <?php if ($key === 'mobile'): ?>
                    <div class="details-row">
                        <span class="details-label">Mobile:</span>
                        <span class="details-value">
                            <?php
                            $cc = $form_data['country_code'] ?? '+91';
                            if ($cc === 'other') {
                                $cc = $form_data['custom_country_code'] ?? '';
                            }
                            echo htmlspecialchars($cc . ' ' . $val);
                            ?>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="details-row">
                        <span class="details-label"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?>:</span>
                        <span class="details-value"><?php echo htmlspecialchars(is_array($val) ? implode(', ', $val) : $val); ?></span>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <h2 class="section-title">Select Services</h2>
    <?php if (!$products): ?>
        <div class="review-card">No services available currently.</div>
    <?php else: ?>
    <form id="productForm" method="post" action="payment-init.php">
        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
        <?php foreach ($form_data as $key => $val): ?>
            <?php if (is_array($val)): ?>
                <?php foreach ($val as $v): ?>
                    <input type="hidden" name="<?php echo htmlspecialchars($key); ?>[]" value="<?php echo htmlspecialchars($v); ?>">
                <?php endforeach; ?>
            <?php else: ?>
                <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($val); ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <ul class="product-list">
            <?php foreach ($products as $product): ?>
            <li class="product-item">
                <div class="product-info">
                    <div style="display:flex;align-items:center;gap:14px;">
                        <input type="checkbox" class="product-checkbox" name="product_ids[]" value="<?php echo $product['id']; ?>" data-price="<?php echo $product['price']; ?>" style="width:28px;height:28px;accent-color:#800000;cursor:pointer;">
                        <div>
                            <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="product-desc"><?php echo htmlspecialchars($product['short_description']); ?></div>
                        </div>
                    </div>
                    <div class="product-price">₹<?php echo number_format($product['price'], 2); ?></div>
                </div>
                <div class="qty-controls">
                    <button type="button" class="qty-btn" onclick="changeQty(this, -1)" disabled>−</button>
                    <input type="number" class="qty-input" name="qty[<?php echo $product['id']; ?>]" value="1" min="1" max="99" readonly>
                    <button type="button" class="qty-btn" onclick="changeQty(this, 1)" disabled>+</button>
                </div>
                <div class="line-total" id="line-total-<?php echo $product['id']; ?>">₹<?php echo number_format($product['price'], 2); ?></div>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="sticky-total">
            Total: <span id="totalPrice">₹0.00</span>
        </div>
        <button type="submit" class="pay-btn" id="payBtn" disabled>Make Payment</button>
    </form>
    <?php endif; ?>
    <a href="services.php" class="review-back-link">&larr; Back to Services</a>
</main>
<?php require_once 'footer.php'; ?>
<script>
function updateTotals() {
    let total = 0;
    let anyChecked = false;
    document.querySelectorAll('.product-item').forEach(function(row) {
        const cb = row.querySelector('input[type=checkbox][name="product_ids[]"]');
        const qtyInput = row.querySelector('.qty-input');
        const price = parseFloat(cb.getAttribute('data-price'));
        let qty = parseInt(qtyInput.value);
        if (!cb.checked) qty = 0;
        else anyChecked = true;
        const lineTotal = price * qty;
        row.querySelector('.line-total').textContent = '₹' + lineTotal.toFixed(2);
        total += lineTotal;
        qtyInput.disabled = !cb.checked;
        row.querySelectorAll('.qty-btn').forEach(btn => btn.disabled = !cb.checked);
    });
    document.getElementById('totalPrice').textContent = '₹' + total.toFixed(2);
    // Enable payBtn if any product is selected, even if total is 0
    document.getElementById('payBtn').disabled = !anyChecked;
}
function changeQty(btn, delta) {
    const row = btn.closest('.product-item');
    const qtyInput = row.querySelector('.qty-input');
    let qty = parseInt(qtyInput.value) + delta;
    if (qty < 1) qty = 1;
    if (qty > 99) qty = 99;
    qtyInput.value = qty;
    updateTotals();
}
document.querySelectorAll('input[type=checkbox][name="product_ids[]"]').forEach(cb => {
    cb.addEventListener('change', updateTotals);
});
document.querySelectorAll('.qty-btn').forEach(btn => {
    btn.addEventListener('click', function() { updateTotals(); });
});
window.onload = updateTotals;
const form = document.getElementById('productForm');
if (form) {
    form.addEventListener('submit', function(e) {
        const payBtn = document.getElementById('payBtn');
        if (payBtn.disabled) {
            e.preventDefault();
            alert('Please select at least one service to proceed.');
            return;
        }
        // If total is 0.00 and at least one product is selected, submit to payment-success.php directly
        const totalText = document.getElementById('totalPrice').textContent.replace(/[^\d.]/g, '');
        const total = parseFloat(totalText);
        const anyChecked = Array.from(document.querySelectorAll('input[type=checkbox][name="product_ids[]"]')).some(cb => cb.checked);
        if (anyChecked && total === 0) {
            e.preventDefault();
            // Change form action to payment-success.php and submit
            form.action = 'payment-success.php?free=1';
            form.submit();
        }
    });
}
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html,body{font-family:'Marcellus',serif!important;}
.main-content { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 18px; box-shadow: 0 4px 24px #e0bebe33; padding: 18px 12px 28px 12px; }
.review-title { font-size: 1.18em; font-weight: bold; margin-bottom: 18px; text-align: center; color: #800000; }
.review-card { background: #f9eaea; border-radius: 14px; box-shadow: 0 2px 8px #e0bebe33; padding: 16px; margin-bottom: 18px; }
.section-title { font-size: 1.05em; color: #800000; margin-bottom: 10px; font-weight: 600; }
.details-list { display: flex; flex-direction: column; gap: 8px; }
.details-row { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px dashed #e0bebe; padding-bottom: 4px; }
.details-label { color: #a03c3c; font-weight: 500; margin-right: 6px; }
.details-value { color: #333; max-width: 60%; word-break: break-word; }
.product-list { margin: 0; padding: 0; list-style: none; }
.product-item { display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f3caca; padding: 14px 0; }
.product-item:last-child { border-bottom: none; }
.product-info { flex: 1; }
.product-checkbox { width: 28px; height: 28px; accent-color: #800000; cursor: pointer; }
.product-name { font-weight: 600; color: #800000; font-size: 1.08em; }
.product-desc { font-size: 0.97em; color: #555; margin: 2px 0 2px 0; }
.product-price { color: #1a8917; font-weight: 600; font-size: 1.08em; margin-top: 6px; }
.qty-controls { display: flex; align-items: center; gap: 4px; }
.qty-btn {
    background: #f5faff;
    border: 1px solid #e0bebe;
    color: #800000;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    font-size: 1.18em;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    line-height: 1;
    box-sizing: border-box;
}
.qty-input { width: 32px; text-align: center; border: 1px solid #e0bebe; border-radius: 6px; padding: 2px 0; font-size: 1em; }
.line-total { font-size: 0.98em; color: #800000; font-weight: 600; min-width: 60px; text-align: right; }
.sticky-total { position: sticky; bottom: 0; background: #fff; padding: 14px 0 0 0; text-align: right; font-size: 1.13em; border-top: 1px solid #e0bebe; box-shadow: 0 -2px 8px #e0bebe22; z-index: 10; }
.pay-btn { width: 100%; background: #800000; color: #fff; border: none; border-radius: 8px; padding: 14px 0; font-size: 1.08em; font-weight: 600; margin-top: 10px; cursor: pointer; box-shadow: 0 2px 8px #80000022; transition: background 0.15s; }
.pay-btn:disabled { background: #ccc; color: #fff; cursor: not-allowed; }
.review-back-link { display:block;text-align:center;margin-top:18px;color:#1a8917;font-size:0.98em;text-decoration:none; }
@media (max-width: 700px) { .main-content { padding: 8px 2px 16px 2px; border-radius: 0; } }
</style>
</script>
</body>
</html>
