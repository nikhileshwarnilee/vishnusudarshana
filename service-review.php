<?php
// --- Handle Make Payment POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_payment'])) {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/helpers/payment_link_map.php';
    require_once __DIR__ . '/helpers/product_variants.php';
    vs_ensure_product_variant_schema($pdo);
    $category = $_POST['category'] ?? '';
    $customer_details = $_POST;
    unset($customer_details['product_ids'], $customer_details['qty'], $customer_details['variant_value'], $customer_details['make_payment']);
    // Store only customer-entered fields in form_data. Product/payment control fields are internal.
    $form_data = $customer_details;
    $product_ids = $_POST['product_ids'] ?? [];
    $quantities = $_POST['qty'] ?? [];
    $variantSelections = $_POST['variant_value'] ?? [];
    $selected_products = [];
    $total_amount = 0;
    if (is_array($product_ids)) {
        $product_ids = vs_variant_normalize_id_list($product_ids);
    } else {
        $product_ids = [];
    }
    if (!empty($product_ids)) {
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT p.*, pv.variant_name
            FROM products p
            LEFT JOIN product_variants pv ON pv.id = p.variant_id
            WHERE p.id IN ($placeholders)
        ");
        $stmt->execute($product_ids);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $requestedValueIds = [];
        foreach ($products as $product) {
            $pid = (int)$product['id'];
            $rawValueId = $variantSelections[$pid] ?? '';
            if ($rawValueId !== '' && ctype_digit((string)$rawValueId)) {
                $requestedValueIds[] = (int)$rawValueId;
            }
        }
        $valueLookup = vs_get_product_variant_value_lookup($pdo, $requestedValueIds, true);

        foreach ($products as $product) {
            $pid = (int)$product['id'];
            $qty = isset($quantities[$pid]) ? max(1, intval($quantities[$pid])) : 1;
            $line_total = $product['price'] * $qty;
            $variantId = !empty($product['variant_id']) ? (int)$product['variant_id'] : null;
            $variantName = !empty($product['variant_name']) ? (string)$product['variant_name'] : '';

            $selectedVariantValueId = null;
            $selectedVariantValueName = '';
            if ($variantId !== null) {
                $rawValueId = $variantSelections[$pid] ?? '';
                if ($rawValueId !== '' && ctype_digit((string)$rawValueId)) {
                    $candidateValueId = (int)$rawValueId;
                    if (!empty($valueLookup[$candidateValueId]) && (int)$valueLookup[$candidateValueId]['variant_id'] === $variantId) {
                        $selectedVariantValueId = $candidateValueId;
                        $selectedVariantValueName = (string)$valueLookup[$candidateValueId]['value_name'];
                    }
                }
            }

            $selected_products[] = [
                'id' => $pid,
                'name' => $product['product_name'],
                'desc' => $product['short_description'],
                'price' => $product['price'],
                'qty' => $qty,
                'line_total' => $line_total,
                'variant_id' => $variantId,
                'variant_name' => $variantName !== '' ? $variantName : null,
                'variant_value_id' => $selectedVariantValueId,
                'variant_value_name' => $selectedVariantValueName !== '' ? $selectedVariantValueName : null,
            ];
            $total_amount += $line_total;
        }
    }
    $payment_id = 'ORD-' . date('YmdHis') . '-' . bin2hex(random_bytes(8));
    $insertStmt = $pdo->prepare("
        INSERT INTO pending_payments (
            payment_id, source, customer_details, form_data, selected_products, category, total_amount
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $insertStmt->execute([
        $payment_id,
        'service',
        json_encode($customer_details),
        json_encode($form_data),
        json_encode($selected_products),
        $category,
        $total_amount
    ]);
    try {
        // Preserve original ORD-* link for later recovery UX.
        vs_paymap_upsert($pdo, (string)$payment_id, null, null, 'service', (string)$category);
    } catch (Throwable $e) {
        error_log('Payment link map upsert failed (service-review): ' . $e->getMessage());
    }
    header('Location: payment-init.php?payment_id=' . urlencode($payment_id));
    exit;
}

$pageTitle = 'Review & Select Services | Vishnusudarshana';
require_once 'header.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/product_variants.php';

vs_ensure_product_variant_schema($pdo);

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
    $stmt = $pdo->prepare('SELECT * FROM products WHERE category_slug = ? AND is_active = 1 ORDER BY display_order ASC, price ASC');
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
    $stmt = $pdo->prepare('SELECT * FROM products WHERE category_slug = ? AND is_active = 1 ORDER BY display_order ASC, price ASC');
    $stmt->execute([$category]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$variantLookup = [];
$variantValuesByVariant = [];
if (!empty($products) && is_array($products)) {
    $variantIds = [];
    foreach ($products as $productRow) {
        if (!empty($productRow['variant_id'])) {
            $variantIds[] = (int)$productRow['variant_id'];
        }
    }
    $variantIds = vs_variant_normalize_id_list($variantIds);
    if (!empty($variantIds)) {
        $variantLookup = vs_get_product_variant_lookup($pdo, $variantIds, true);
        $variantValuesByVariant = vs_get_product_variant_values_grouped($pdo, $variantIds, true);
    }
}

?>
<?php
$reviewFieldLabels = [
    'boy_name' => 'Men Name',
    'girl_name' => 'Women Name',
    'boy_dob' => 'Men Date Of Birth',
    'boy_tob' => 'Men Time Of Birth',
    'boy_pob' => 'Men Place Of Birth',
    'girl_dob' => 'Women Date Of Birth',
    'girl_tob' => 'Women Time Of Birth',
    'girl_pob' => 'Women Place Of Birth',
];
?>
<main class="main-content" style="background-color:var(--cream-bg);">
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
                        <span class="details-label"><?php echo htmlspecialchars($reviewFieldLabels[$key] ?? ucwords(str_replace('_', ' ', $key))); ?>:</span>
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
    <form id="productForm" method="post" action="">
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
            <?php
                $productVariantId = !empty($product['variant_id']) ? (int)$product['variant_id'] : 0;
                $variantRow = $productVariantId > 0 && isset($variantLookup[$productVariantId]) ? $variantLookup[$productVariantId] : null;
                $variantLabel = $variantRow ? (string)$variantRow['variant_name'] : '';
                $variantValues = $productVariantId > 0 && isset($variantValuesByVariant[$productVariantId]) ? $variantValuesByVariant[$productVariantId] : [];
                $longDescriptionRaw = (string)($product['long_description'] ?? '');
                $hasLongDescription = trim(strip_tags(html_entity_decode($longDescriptionRaw, ENT_QUOTES, 'UTF-8'))) !== '';
            ?>
            <li class="product-item">
                <div class="product-info">
                    <div style="display:flex;align-items:center;gap:14px;">
                        <input type="checkbox" class="product-checkbox" name="product_ids[]" value="<?php echo $product['id']; ?>" data-price="<?php echo $product['price']; ?>"
                        <?php if (!empty($product['is_mandatory'])): ?> checked style="width:28px;height:28px;accent-color:#800000;cursor:not-allowed;pointer-events:none;"<?php else: ?>style="width:28px;height:28px;accent-color:#800000;cursor:pointer;"<?php endif; ?> >
                        <div>
                            <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="product-desc"><?php echo htmlspecialchars($product['short_description']); ?></div>
                            <?php if ($hasLongDescription): ?>
                                <button
                                    type="button"
                                    class="read-more-btn"
                                    data-product-title="<?php echo htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-long-desc-id="product_long_desc_<?php echo (int)$product['id']; ?>"
                                    onclick="openProductDetails(this)"
                                >
                                    Read More
                                </button>
                                <script type="application/json" id="product_long_desc_<?php echo (int)$product['id']; ?>" class="product-long-desc-json">
<?php
echo json_encode(
    ['html' => $longDescriptionRaw],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
?>
                                </script>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="product-price">₹<?php echo number_format($product['price'], 2); ?></div>
                    <?php if (!empty($variantValues)): ?>
                        <div class="product-variant-wrap">
                            <label class="variant-label" for="variant_value_<?php echo (int)$product['id']; ?>">
                                <?php echo htmlspecialchars($variantLabel !== '' ? $variantLabel : 'Variant'); ?>:
                            </label>
                            <select
                                id="variant_value_<?php echo (int)$product['id']; ?>"
                                class="variant-select"
                                name="variant_value[<?php echo (int)$product['id']; ?>]"
                            >
                                <option value="">Select <?php echo htmlspecialchars($variantLabel !== '' ? $variantLabel : 'Option'); ?> (Optional)</option>
                                <?php foreach ($variantValues as $variantValue): ?>
                                    <option value="<?php echo (int)$variantValue['id']; ?>">
                                        <?php echo htmlspecialchars($variantValue['value_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="qty-controls">
                    <button type="button" class="qty-btn" onclick="changeQty(this, -1)">−</button>
                    <input type="number" class="qty-input" name="qty[<?php echo $product['id']; ?>]" value="1" min="1" max="99" readonly>
                    <button type="button" class="qty-btn" onclick="changeQty(this, 1)">+</button>
                </div>
                <div class="line-total" id="line-total-<?php echo $product['id']; ?>">₹<?php echo number_format($product['price'], 2); ?></div>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="sticky-total">
            Total: <span id="totalPrice">₹0.00</span>
        </div>
        <button type="submit" class="pay-btn" id="payBtn" disabled>Make Payment</button>
        <input type="hidden" name="make_payment" value="1">
    </form>
    <?php endif; ?>
    <a href="services.php" class="review-back-link">&larr; Back to Services</a>
<div id="productInfoModal" class="product-info-modal" aria-hidden="true">
    <div class="product-info-overlay" onclick="closeProductDetails()"></div>
    <div class="product-info-dialog" role="dialog" aria-modal="true" aria-labelledby="productInfoTitle">
        <button type="button" class="product-info-close" onclick="closeProductDetails()" aria-label="Close details">&times;</button>
        <div class="product-info-head">
            <div class="product-info-kicker">Service Details</div>
            <h3 id="productInfoTitle" class="product-info-title"></h3>
        </div>
        <div id="productInfoBody" class="product-info-body"></div>
    </div>
</div>
<script>
function sanitizeLongDescriptionHtml(html) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html || '', 'text/html');
    doc.querySelectorAll('script').forEach(function(el) { el.remove(); });
    doc.querySelectorAll('*').forEach(function(el) {
        Array.from(el.attributes).forEach(function(attr) {
            if (/^on/i.test(attr.name)) {
                el.removeAttribute(attr.name);
            }
        });
    });
    return doc.body.innerHTML;
}

function openProductDetails(button) {
    const modal = document.getElementById('productInfoModal');
    const titleEl = document.getElementById('productInfoTitle');
    const bodyEl = document.getElementById('productInfoBody');
    if (!modal || !titleEl || !bodyEl || !button) return;

    const title = button.getAttribute('data-product-title') || 'Service Details';
    const longDescId = button.getAttribute('data-long-desc-id') || '';
    let html = '';
    if (longDescId) {
        const descJsonNode = document.getElementById(longDescId);
        if (descJsonNode) {
            try {
                const parsed = JSON.parse(descJsonNode.textContent || '{}');
                html = typeof parsed.html === 'string' ? parsed.html : '';
            } catch (e) {
                html = '';
            }
        }
    }
    const safeHtml = sanitizeLongDescriptionHtml(html);

    titleEl.textContent = title;
    bodyEl.innerHTML = safeHtml || '<p>Details are not available at the moment.</p>';
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
}

function closeProductDetails() {
    const modal = document.getElementById('productInfoModal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeProductDetails();
    }
});

function updateTotals() {
    let total = 0;
    let anyChecked = false;
    document.querySelectorAll('.product-item').forEach(function(row) {
        const cb = row.querySelector('input[type=checkbox][name="product_ids[]"]');
        const qtyInput = row.querySelector('.qty-input');
        const variantSelect = row.querySelector('.variant-select');
        const price = parseFloat(cb.getAttribute('data-price'));
        let qty = parseInt(qtyInput.value);
        if (!cb.checked) qty = 0;
        else anyChecked = true;
        const lineTotal = price * qty;
        row.querySelector('.line-total').textContent = '₹' + lineTotal.toFixed(2);
        total += lineTotal;
        qtyInput.disabled = !cb.checked;
        row.querySelectorAll('.qty-btn').forEach(btn => btn.disabled = !cb.checked);
        if (variantSelect) {
            variantSelect.disabled = !cb.checked;
        }
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
    // Ensure mandatory products are always selected in form submission
    if (cb.disabled && cb.checked) {
        cb.setAttribute('checked', 'checked');
    }
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
        // Ensure all mandatory products are included in submission
        document.querySelectorAll('input[type=checkbox][name="product_ids[]"]').forEach(cb => {
            if (cb.disabled && cb.checked) {
                cb.setAttribute('checked', 'checked');
            }
        });
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
.read-more-btn {
    margin-top: 8px;
    border: none;
    background: linear-gradient(135deg, #fff3e7 0%, #ffe4d4 100%);
    color: #7c2200;
    font-weight: 700;
    font-size: 0.88em;
    padding: 6px 11px;
    border-radius: 999px;
    box-shadow: 0 2px 8px rgba(124, 34, 0, 0.14);
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.read-more-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 5px 14px rgba(124, 34, 0, 0.2);
}
.product-price { color: #1a8917; font-weight: 600; font-size: 1.08em; margin-top: 6px; }
.product-variant-wrap { margin-top: 8px; }
.variant-label { display: block; color: #6a2f2f; font-size: 0.9em; margin-bottom: 4px; font-weight: 600; }
.variant-select {
    width: 100%;
    border: 1px solid #e0bebe;
    border-radius: 6px;
    padding: 6px 8px;
    font-size: 0.92em;
    background: #fff;
    color: #333;
}
.variant-select:disabled { background: #f3f3f3; color: #999; }
.line-total { font-size: 0.98em; color: #800000; font-weight: 600; min-width: 60px; text-align: right; }
.sticky-total { position: sticky; bottom: 0; background: #fff; padding: 14px 0 0 0; text-align: right; font-size: 1.13em; border-top: 1px solid #e0bebe; box-shadow: 0 -2px 8px #e0bebe22; z-index: 10; }
.pay-btn { width: 100%; background: #800000; color: #fff; border: none; border-radius: 8px; padding: 14px 0; font-size: 1.08em; font-weight: 600; margin-top: 10px; cursor: pointer; box-shadow: 0 2px 8px #80000022; transition: background 0.15s; }
.pay-btn:disabled { background: #ccc; color: #fff; cursor: not-allowed; }
.review-back-link { display:block;text-align:center;margin-top:18px;color:#1a8917;font-size:0.98em;text-decoration:none; }
.modal-open { overflow: hidden; }
.product-info-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
}
.product-info-modal.open { display: block; }
.product-info-overlay {
    position: absolute;
    inset: 0;
    background: rgba(33, 20, 12, 0.56);
    backdrop-filter: blur(2px);
}
.product-info-dialog {
    position: relative;
    width: min(92vw, 560px);
    max-height: 82vh;
    overflow: auto;
    margin: 9vh auto 0;
    background: linear-gradient(165deg, #fffef8 0%, #fff5ee 48%, #fff8f3 100%);
    border: 1px solid #f2d3c4;
    border-radius: 20px;
    box-shadow: 0 22px 56px rgba(73, 31, 12, 0.24);
    animation: productInfoPop 0.2s ease-out;
}
@keyframes productInfoPop {
    from { opacity: 0; transform: translateY(14px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.product-info-close {
    position: absolute;
    top: 10px;
    right: 12px;
    border: none;
    background: transparent;
    font-size: 1.9em;
    color: #8f4931;
    line-height: 1;
    cursor: pointer;
}
.product-info-head {
    padding: 20px 22px 10px 22px;
    border-bottom: 1px solid #f3ddd1;
}
.product-info-kicker {
    font-size: 0.78em;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #b04f26;
    font-weight: 700;
}
.product-info-title {
    margin: 7px 0 0;
    color: #6f1400;
    font-size: 1.2em;
}
.product-info-body {
    padding: 16px 22px 22px;
    color: #3b3b3b;
    font-size: 0.98em;
    line-height: 1.7;
}
.product-info-body h1,
.product-info-body h2,
.product-info-body h3,
.product-info-body h4,
.product-info-body h5,
.product-info-body h6 { color: #6f1400; margin: 10px 0 6px; }
.product-info-body p { margin: 8px 0; }
.product-info-body ul,
.product-info-body ol { padding-left: 20px; }
.product-info-body img { max-width: 100%; height: auto; border-radius: 10px; }
@media (max-width: 700px) { .main-content { padding: 8px 2px 16px 2px; border-radius: 0; } }
</style>
</script>
</body>
</html>
