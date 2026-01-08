<?php
require_once __DIR__ . '/../../config/db.php';
// Fetch categories for dropdown
$catStmt = $pdo->query("SELECT category_slug, category_name FROM service_categories ORDER BY sequence ASC, id ASC");
$categoryOptions = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Field definitions (copy from service-form.php)
$commonFields = [
    ['label' => 'Full Name', 'name' => 'full_name', 'type' => 'text', 'required' => true],
    ['label' => 'Mobile Number', 'name' => 'mobile', 'type' => 'tel', 'required' => true],
    ['label' => 'Email', 'name' => 'email', 'type' => 'email', 'required' => false],
    ['label' => 'City / Location', 'name' => 'city', 'type' => 'text', 'required' => true],
];
$categoryFields = [
    'appointment' => [
        ['label' => 'Preferred Date', 'name' => 'preferred_date', 'type' => 'date', 'required' => true],
        ['label' => 'Preferred Time Slot', 'name' => 'preferred_time', 'type' => 'text', 'required' => true],
        ['label' => 'Consultation Type', 'name' => 'consultation_type', 'type' => 'select', 'options' => ['Online', 'In-person'], 'required' => true],
        ['label' => 'Topic', 'name' => 'topic', 'type' => 'select', 'options' => ['Astrology', 'Vastu', 'Rituals', 'General Guidance', 'Other'], 'required' => true],
    ],
    'birth-child' => [
        ['label' => 'Child Name', 'name' => 'child_name', 'type' => 'text', 'required' => false],
        ['label' => 'Date of Birth', 'name' => 'dob', 'type' => 'date', 'required' => true],
        ['label' => 'Time of Birth', 'name' => 'tob', 'type' => 'time', 'required' => true],
        ['label' => 'Place of Birth', 'name' => 'pob', 'type' => 'text', 'required' => true],
        ['label' => 'Gender', 'name' => 'gender', 'type' => 'select', 'options' => ['Male', 'Female', 'Other'], 'required' => true],
    ],
    'marriage-matching' => [
        ['label' => 'Boy Date of Birth', 'name' => 'boy_dob', 'type' => 'date', 'required' => true],
        ['label' => 'Boy Time of Birth', 'name' => 'boy_tob', 'type' => 'time', 'required' => true],
        ['label' => 'Boy Place of Birth', 'name' => 'boy_pob', 'type' => 'text', 'required' => true],
        ['label' => 'Girl Date of Birth', 'name' => 'girl_dob', 'type' => 'date', 'required' => true],
        ['label' => 'Girl Time of Birth', 'name' => 'girl_tob', 'type' => 'time', 'required' => true],
        ['label' => 'Girl Place of Birth', 'name' => 'girl_pob', 'type' => 'text', 'required' => true],
    ],
    'astrology-consultation' => [
        ['label' => 'Date of Birth', 'name' => 'dob', 'type' => 'date', 'required' => true],
        ['label' => 'Time of Birth', 'name' => 'tob', 'type' => 'time', 'required' => true],
        ['label' => 'Place of Birth', 'name' => 'pob', 'type' => 'text', 'required' => true],
    ],
    'muhurat-event' => [
        ['label' => 'Event Type', 'name' => 'event_type', 'type' => 'select', 'options' => ['Marriage', 'Griha Pravesh', 'Vehicle Purchase', 'Business Start', 'Other'], 'required' => true],
        ['label' => 'Preferred Date or Month', 'name' => 'preferred_date', 'type' => 'text', 'required' => true],
        ['label' => 'City', 'name' => 'event_city', 'type' => 'text', 'required' => true],
    ],
    'pooja-vastu-enquiry' => [
        ['label' => 'Service Topic', 'name' => 'service_topic', 'type' => 'select', 'options' => ['Pooja & Ritual', 'Shanti & Dosh Nivaran', 'Yagya & Havan', 'Vastu Consultation', 'Other'], 'required' => true],
        ['label' => 'Problem Description', 'name' => 'problem_desc', 'type' => 'textarea', 'required' => true],
        ['label' => 'City', 'name' => 'enquiry_city', 'type' => 'text', 'required' => true],
    ],
];
foreach ($categoryFields as $catKey => &$fields) {
    $fields[] = [
        'label' => 'Additional Questions / Details',
        'name' => 'questions',
        'type' => 'textarea',
        'required' => false,
    ];
}
unset($fields);

// Handle form submission
$successMsg = '';
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['category_slug'])) {
    $category = $_POST['category_slug'];
    $formData = $_POST;
    $fields = array_merge($commonFields, $categoryFields[$category] ?? []);
    $data = [];
    foreach ($fields as $f) {
        $data[$f['name']] = trim($formData[$f['name']] ?? '');
    }
    $selected_products = isset($_POST['selected_products']) ? $_POST['selected_products'] : '[]';
    $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
    // Generate unique tracking ID (e.g., SR20251224XXXX)
    $tracking_id = 'SR' . date('Ymd') . strtoupper(substr(uniqid(), -4));
    // Insert into service_requests
    $stmt = $pdo->prepare("INSERT INTO service_requests (category_slug, customer_name, mobile, email, form_data, payment_status, selected_products, total_amount, tracking_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $category,
        $data['full_name'] ?? '',
        $data['mobile'] ?? '',
        $data['email'] ?? '',
        json_encode($data),
        'Unpaid',
        $selected_products,
        $total_amount,
        $tracking_id
    ]);
    $successMsg = 'Service request added successfully! Tracking ID: ' . $tracking_id;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Offline Service Request</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
    body { font-family: Arial, sans-serif; background: #f7f7fa; margin: 0; }
    .admin-container { max-width: 700px; margin: 0 auto; padding: 24px 12px; }
    h1 { color: #800000; margin-bottom: 18px; font-family: inherit; }
    .form-label { font-weight: 600; display:block; margin-bottom:6px; }
    .form-input, .form-select, .form-textarea { width:100%; padding:10px 12px; border-radius:8px; border:1px solid #e0bebe; font-size:1.08em; margin-bottom:16px; font-family: inherit; }
    .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: #800000; outline: none; }
    .form-btn { background:#800000; color:#fff; border:none; border-radius:8px; padding:12px 0; font-size:1.08em; font-weight:600; width:100%; cursor:pointer; margin-top:10px; transition: background 0.15s; }
    .form-btn:hover { background: #a00000; }
    .back-link { display:inline-block; margin-bottom:18px; color:#800000; text-decoration:none; font-weight:600; }
    .error-list { color:#c00; margin-bottom:18px; }
    .success-msg { color: #1a8917; margin-bottom: 18px; font-weight: 600; }
    .loading { color: #800000; font-weight: 600; margin-bottom: 12px; }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Offline Service Request</h1>
    <?php if ($successMsg): ?><div class="success-msg"><?= $successMsg ?></div><?php endif; ?>
    <form id="offlineServiceForm" method="post" autocomplete="off">
        <label class="form-label">Select Category:
            <select name="category_slug" id="categorySelect" class="form-select" required>
                <option value="">--Select--</option>
                <?php foreach ($categoryOptions as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['category_slug']) ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div id="dynamicFields"></div>
        <div id="productSection" style="display:none;"></div>
        <div id="totalSection" style="display:none;margin-bottom:12px;font-weight:600;color:#800000;">Total: <span id="totalAmount">₹0.00</span></div>
        <input type="hidden" name="selected_products" id="selectedProductsInput">
        <input type="hidden" name="total_amount" id="totalAmountInput">
        <div id="paymentFields" style="display:none;">
            <button type="submit" class="form-btn">Submit</button>
        </div>
    </form>
    <script>
    const categoryFields = <?= json_encode($categoryFields) ?>;
    const commonFields = <?= json_encode($commonFields) ?>;
    function renderProducts(products) {
        if (!products.length) return '<div style="color:#c00;">No products/services for this category.</div>';
        let html = '<div style="margin-bottom:10px;font-weight:600;">Select Services/Products:</div>';
        html += '<ul style="list-style:none;padding:0;">';
        products.forEach(function(p) {
            html += '<li class="product-item" style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">';
            html += '<input type="checkbox" class="product-checkbox" data-id="'+p.id+'" data-price="'+p.price+'" style="width:22px;height:22px;">';
            html += '<div style="flex:1;">';
            html += '<span style="font-weight:600;color:#800000;">'+p.product_name+'</span>';
            if (p.short_description) html += '<div style="font-size:0.97em;color:#555;">'+p.short_description+'</div>';
            html += '</div>';
            html += '<div style="min-width:80px;color:#1a8917;font-weight:600;">₹'+parseFloat(p.price).toFixed(2)+'</div>';
            html += '<input type="number" class="qty-input" data-id="'+p.id+'" value="1" min="1" max="99" style="width:40px;" disabled>';
            html += '<div class="line-total" data-id="'+p.id+'" style="min-width:60px;text-align:right;">₹0.00</div>';
            html += '</li>';
        });
        html += '</ul>';
        return html;
    }

    function updateTotals() {
        let total = 0;
        let selected = [];
        $(".product-item").each(function() {
            const cb = $(this).find('.product-checkbox');
            const qtyInput = $(this).find('.qty-input');
            const price = parseFloat(cb.data('price'));
            let qty = parseInt(qtyInput.val());
            if (!cb.prop('checked')) qty = 0;
            const lineTotal = price * qty;
            $(this).find('.line-total').text('₹'+lineTotal.toFixed(2));
            if (cb.prop('checked')) {
                selected.push({id: cb.data('id'), qty: qty, price: price});
                total += lineTotal;
                qtyInput.prop('disabled', false);
            } else {
                qtyInput.prop('disabled', true);
            }
        });
        $('#totalAmount').text('₹'+total.toFixed(2));
        $('#totalAmountInput').val(total.toFixed(2));
        $('#selectedProductsInput').val(JSON.stringify(selected));
    }

    $('#categorySelect').on('change', function() {
        var cat = $(this).val();
        var html = '';
        if (cat && categoryFields[cat]) {
            // Common fields
            commonFields.forEach(function(f) {
                html += renderField(f);
            });
            // Category-specific fields
            categoryFields[cat].forEach(function(f) {
                html += renderField(f);
            });
            $('#dynamicFields').html(html);
            // Fetch products for this category
            $.get('ajax_get_products.php', {category: cat}, function(res) {
                if (res.success) {
                    $('#productSection').html(renderProducts(res.products)).show();
                    $('#totalSection').show();
                    updateTotals();
                    // Bind events
                    $('.product-checkbox').on('change', updateTotals);
                    $('.qty-input').on('input', function() {
                        let v = parseInt($(this).val());
                        if (isNaN(v) || v < 1) v = 1;
                        if (v > 99) v = 99;
                        $(this).val(v);
                        updateTotals();
                    });
                } else {
                    $('#productSection').html('<div style="color:#c00;">No products/services for this category.</div>').show();
                    $('#totalSection').hide();
                }
            });
            $('#paymentFields').show();
        } else {
            $('#dynamicFields').html('');
            $('#productSection').hide();
            $('#totalSection').hide();
            $('#paymentFields').hide();
        }
    });
    function renderField(f) {
        var req = f.required ? 'required' : '';
        var html = '<label class="form-label">' + f.label + (f.required ? ' <span style="color:#c00">*</span>' : '') + ':';
        if (f.type === 'select') {
            html += '<select name="'+f.name+'" class="form-select" '+req+'><option value="">--Select--</option>';
            f.options.forEach(function(opt) {
                html += '<option value="'+opt+'">'+opt+'</option>';
            });
            html += '</select>';
        } else if (f.type === 'textarea') {
            html += '<textarea name="'+f.name+'" class="form-textarea" rows="2" '+req+'></textarea>';
        } else {
            html += '<input type="'+f.type+'" name="'+f.name+'" class="form-input" '+req+'>';
        }
        html += '</label>';
        return html;
    }
    </script>
</div>
</body>
</html>
