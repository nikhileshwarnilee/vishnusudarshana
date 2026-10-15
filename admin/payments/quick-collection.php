<?php
/**
 * Fast, phone-friendly collection entry screen.
 * This page deliberately does not use the admin session so it can be opened
 * from a trusted bookmarked WebView after a pooja visit.
 */
require_once __DIR__ . '/../../config/db.php';

function vs_quick_collection_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function vs_quick_collection_history(PDO $pdo, int $customerId): array
{
    $customerStmt = $pdo->prepare('SELECT id, name, mobile, address FROM customers WHERE id = ? LIMIT 1');
    $customerStmt->execute([$customerId]);
    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
    if (!$customer) {
        return ['error' => 'Customer not found.'];
    }

    $summaryStmt = $pdo->prepare('SELECT COALESCE(SUM(total_amount), 0) AS total_bill, COALESCE(SUM(paid_amount), 0) AS total_paid FROM invoices WHERE customer_id = ?');
    $summaryStmt->execute([$customerId]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_bill' => 0, 'total_paid' => 0];
    $totalBill = (float)$summary['total_bill'];
    $totalPaid = (float)$summary['total_paid'];

    $invoiceStmt = $pdo->prepare("
        SELECT i.id, i.invoice_date, i.total_amount, i.paid_amount, i.notes,
               GROUP_CONCAT(ii.product_name ORDER BY ii.id SEPARATOR ', ') AS services
        FROM invoices i
        LEFT JOIN invoice_items ii ON ii.invoice_id = i.id
        WHERE i.customer_id = ?
        GROUP BY i.id, i.invoice_date, i.total_amount, i.paid_amount, i.notes
        ORDER BY i.invoice_date DESC, i.id DESC
        LIMIT 30
    ");
    $invoiceStmt->execute([$customerId]);
    $invoices = $invoiceStmt->fetchAll(PDO::FETCH_ASSOC);

    $paymentStmt = $pdo->prepare('SELECT paid_date, paid_amount, method, transaction_details, note FROM payments WHERE customer_id = ? ORDER BY paid_date DESC, id DESC LIMIT 30');
    $paymentStmt->execute([$customerId]);
    $payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'customer' => $customer,
        'summary' => [
            'total_bill' => $totalBill,
            'total_paid' => $totalPaid,
            'remaining' => max(0, $totalBill - $totalPaid),
        ],
        'invoices' => $invoices,
        'payments' => $payments,
    ];
}

$action = trim((string)($_REQUEST['action'] ?? ''));
if ($action !== '') {
    try {
        if ($action === 'customers') {
            $query = trim((string)($_GET['q'] ?? ''));
            if ($query === '') {
                vs_quick_collection_json(['customers' => []]);
            }

            $stmt = $pdo->prepare("
                SELECT c.id, c.name, c.mobile, c.address,
                       COALESCE((SELECT SUM(i.total_amount) FROM invoices i WHERE i.customer_id = c.id), 0)
                       - COALESCE((SELECT SUM(p.paid_amount) FROM payments p WHERE p.customer_id = c.id), 0) AS remaining
                FROM customers c
                WHERE c.name LIKE ? OR c.mobile LIKE ? OR c.address LIKE ?
                ORDER BY c.name ASC, c.id DESC
                LIMIT 12
            ");
            $like = '%' . $query . '%';
            $stmt->execute([$like, $like, $like]);
            vs_quick_collection_json(['customers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        if ($action === 'products') {
            $query = trim((string)($_GET['q'] ?? ''));
            $stmt = $pdo->prepare("
                SELECT MIN(service_name) AS service_name
                FROM (
                    SELECT TRIM(product_name) AS service_name
                    FROM invoice_items
                    WHERE product_name IS NOT NULL AND TRIM(product_name) <> ''
                    UNION ALL
                    SELECT TRIM(title) AS service_name
                    FROM letterpad_titles
                    WHERE source = 'product' AND title IS NOT NULL AND TRIM(title) <> ''
                ) AS saved_services
                WHERE service_name LIKE ?
                GROUP BY LOWER(service_name)
                ORDER BY service_name ASC
                LIMIT 20
            ");
            $stmt->execute(['%' . $query . '%']);
            vs_quick_collection_json(['services' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
        }

        if ($action === 'history') {
            $customerId = (int)($_GET['customer_id'] ?? 0);
            if ($customerId <= 0) {
                vs_quick_collection_json(['error' => 'Select a valid customer first.'], 422);
            }
            vs_quick_collection_json(vs_quick_collection_history($pdo, $customerId));
        }

        if ($action === 'save') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                vs_quick_collection_json(['success' => false, 'error' => 'Invalid request method.'], 405);
            }

            $customerId = (int)($_POST['customer_id'] ?? 0);
            $newCustomerName = trim((string)($_POST['new_customer_name'] ?? ''));
            $newCustomerMobile = trim((string)($_POST['new_customer_mobile'] ?? ''));
            $newCustomerAddress = trim((string)($_POST['new_customer_address'] ?? ''));
            $serviceName = trim((string)($_POST['service_name'] ?? ''));
            $invoiceDate = trim((string)($_POST['invoice_date'] ?? ''));
            $totalAmount = (float)($_POST['total_amount'] ?? 0);
            $paidAmount = (float)($_POST['paid_amount'] ?? 0);
            $payMethod = trim((string)($_POST['pay_method'] ?? 'Cash'));
            $transactionDetails = trim((string)($_POST['transaction_details'] ?? ''));
            $note = trim((string)($_POST['note'] ?? ''));

            $date = DateTime::createFromFormat('Y-m-d', $invoiceDate);
            if (!$date || $date->format('Y-m-d') !== $invoiceDate) {
                vs_quick_collection_json(['success' => false, 'error' => 'Choose a valid date.'], 422);
            }
            if ($serviceName === '') {
                vs_quick_collection_json(['success' => false, 'error' => 'Enter the product or service name.'], 422);
            }
            if ($totalAmount <= 0) {
                vs_quick_collection_json(['success' => false, 'error' => 'Bill total must be more than zero.'], 422);
            }
            if ($paidAmount < 0 || $paidAmount > $totalAmount) {
                vs_quick_collection_json(['success' => false, 'error' => 'Paid amount must be between zero and the bill total.'], 422);
            }
            if (!in_array($payMethod, ['Cash', 'UPI', 'Bank', 'Other'], true)) {
                $payMethod = 'Other';
            }

            $pdo->beginTransaction();
            try {
                if ($customerId > 0) {
                    $customerStmt = $pdo->prepare('SELECT id FROM customers WHERE id = ? LIMIT 1');
                    $customerStmt->execute([$customerId]);
                    if (!$customerStmt->fetchColumn()) {
                        throw new RuntimeException('The selected customer no longer exists.');
                    }
                } else {
                    if ($newCustomerName === '') {
                        throw new RuntimeException('Select an existing customer or enter a new customer name.');
                    }
                    if ($newCustomerMobile !== '') {
                        $duplicateStmt = $pdo->prepare('SELECT id FROM customers WHERE mobile = ? LIMIT 1');
                        $duplicateStmt->execute([$newCustomerMobile]);
                        if ($duplicateStmt->fetchColumn()) {
                            throw new RuntimeException('This mobile number already belongs to a customer. Please search and select that customer.');
                        }
                    }
                    $customerStmt = $pdo->prepare('INSERT INTO customers (name, mobile, address, created_at) VALUES (?, ?, ?, ?)');
                    $customerStmt->execute([$newCustomerName, $newCustomerMobile, $newCustomerAddress, date('Y-m-d H:i:s')]);
                    $customerId = (int)$pdo->lastInsertId();
                }

                $invoiceStmt = $pdo->prepare('INSERT INTO invoices (customer_id, invoice_date, notes, total_qty, total_amount, created_at) VALUES (?, ?, ?, 1, ?, ?)');
                $invoiceStmt->execute([$customerId, $invoiceDate, $note, $totalAmount, date('Y-m-d H:i:s')]);
                $invoiceId = (int)$pdo->lastInsertId();

                $itemStmt = $pdo->prepare('INSERT INTO invoice_items (invoice_id, product_name, qty, amount) VALUES (?, ?, 1, ?)');
                $itemStmt->execute([$invoiceId, $serviceName, $totalAmount]);

                if ($paidAmount > 0) {
                    $updateInvoice = $pdo->prepare('UPDATE invoices SET paid_amount = ? WHERE id = ?');
                    $updateInvoice->execute([$paidAmount, $invoiceId]);
                    $paymentStmt = $pdo->prepare('INSERT INTO payments (customer_id, invoice_id, paid_date, paid_amount, note, method, transaction_details) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $paymentStmt->execute([$customerId, $invoiceId, $invoiceDate, $paidAmount, $note, $payMethod, $transactionDetails]);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            vs_quick_collection_json([
                'success' => true,
                'customer_id' => $customerId,
                'invoice_id' => $invoiceId,
                'message' => 'Saved. Remaining for this bill: ₹' . number_format($totalAmount - $paidAmount, 2),
            ]);
        }

        vs_quick_collection_json(['error' => 'Unknown action.'], 404);
    } catch (Throwable $e) {
        error_log('Quick collection error: ' . $e->getMessage());
        vs_quick_collection_json(['success' => false, 'error' => 'Unable to save right now. Please try again.'], 500);
    }
}

$today = date('Y-m-d');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Quick Pooja Collection</title>
    <style>
        :root { --maroon:#800000; --maroon-dark:#5f0000; --green:#16803c; --ink:#242126; --muted:#736d72; --line:#eadde0; --soft:#fff7f7; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; color:var(--ink); background:linear-gradient(145deg,#fff8f5 0%,#f4f4f7 52%,#fffafa 100%); font-family:Arial,Helvetica,sans-serif; }
        .shell { width:min(720px,100%); margin:0 auto; padding:calc(18px + env(safe-area-inset-top)) 14px calc(92px + env(safe-area-inset-bottom)); }
        .top { padding:5px 5px 18px; }
        h1 { margin:0; color:var(--maroon); font-size:1.5rem; line-height:1.2; }
        .sub { margin:6px 0 0; color:var(--muted); font-size:.92rem; }
        .card { margin:0 0 14px; padding:16px; border:1px solid var(--line); border-radius:16px; background:rgba(255,255,255,.96); box-shadow:0 7px 22px rgba(80,30,35,.07); }
        .card-title { margin:0 0 12px; color:var(--maroon); font-size:1rem; }
        label { display:block; margin:0 0 6px; color:#4d3a3e; font-size:.88rem; font-weight:700; }
        input, select, textarea, button { font:inherit; }
        input, select, textarea { width:100%; min-height:46px; padding:11px 12px; border:1px solid #d9c9cd; border-radius:10px; color:var(--ink); background:#fff; font-size:16px; }
        textarea { min-height:70px; resize:vertical; }
        input:focus, select:focus, textarea:focus { border-color:var(--maroon); outline:3px solid #8000001c; }
        .field { margin-bottom:13px; }
        .field:last-child { margin-bottom:0; }
        .hint { margin:5px 0 0; color:var(--muted); font-size:.78rem; }
        .customer-results { overflow:hidden; margin-top:7px; border:1px solid var(--line); border-radius:10px; background:#fff; }
        .customer-results:empty { display:none; }
        .customer-result { width:100%; padding:11px 12px; border:0; border-bottom:1px solid #f0e5e7; background:#fff; text-align:left; cursor:pointer; }
        .customer-result:last-child { border-bottom:0; }
        .customer-result:active { background:var(--soft); }
        .customer-result strong, .customer-result small { display:block; }
        .customer-result small { margin-top:3px; color:var(--muted); line-height:1.35; }
        .selected { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-top:10px; padding:11px 12px; border-radius:10px; background:#f5fbf6; border:1px solid #cfe7d5; }
        .selected strong, .selected span { display:block; }
        .selected span { margin-top:3px; color:#59705e; font-size:.83rem; }
        .link-button { padding:2px 0; border:0; background:transparent; color:var(--maroon); font-size:.86rem; font-weight:700; text-decoration:underline; cursor:pointer; }
        .new-customer { margin-top:10px; padding-top:13px; border-top:1px dashed #decdd1; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .amounts { display:grid; grid-template-columns:1fr 1fr 1fr; gap:9px; }
        .remaining-box { display:flex; min-height:46px; flex-direction:column; justify-content:center; padding:8px 10px; border:1px solid #efc6c6; border-radius:10px; background:#fff5f5; }
        .remaining-box span { color:#8e2222; font-size:.76rem; font-weight:700; }
        .remaining-box strong { margin-top:2px; color:var(--maroon); font-size:1rem; }
        .save-wrap { position:sticky; bottom:0; z-index:3; padding:10px 0 calc(4px + env(safe-area-inset-bottom)); background:linear-gradient(180deg,transparent 0%,#f7f4f7d9 24%,#f7f4f7 100%); }
        .save { width:100%; min-height:52px; border:0; border-radius:12px; color:#fff; background:linear-gradient(135deg,var(--maroon),#a30f1a); font-weight:800; font-size:1rem; box-shadow:0 5px 13px #80000042; cursor:pointer; }
        .save:disabled { cursor:wait; opacity:.68; }
        .message { margin:0 0 12px; padding:11px 12px; border-radius:10px; font-size:.9rem; font-weight:700; }
        .message[hidden] { display:none; }
        .message.ok { color:#166534; border:1px solid #b9dfc2; background:#f0fbf2; }
        .message.error { color:#a31616; border:1px solid #efc1c1; background:#fff1f1; }
        .history { margin-top:18px; }
        .summary-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:13px; }
        .summary { padding:10px 8px; border-radius:10px; background:#faf6f6; text-align:center; }
        .summary span { display:block; color:var(--muted); font-size:.71rem; font-weight:700; }
        .summary strong { display:block; margin-top:4px; color:var(--maroon); font-size:.96rem; }
        .history-title { margin:15px 0 7px; color:#59383d; font-size:.92rem; }
        .table-wrap { overflow-x:auto; border:1px solid #eee2e4; border-radius:10px; }
        table { width:100%; min-width:560px; border-collapse:collapse; background:#fff; font-size:.82rem; }
        th, td { padding:9px 8px; border-bottom:1px solid #f2e8e9; text-align:left; vertical-align:top; }
        th { color:var(--maroon); background:#fff8f8; font-size:.76rem; }
        tr:last-child td { border-bottom:0; }
        .right { text-align:right; }
        .empty { padding:13px; color:var(--muted); font-size:.86rem; text-align:center; }
        [hidden] { display:none !important; }
        @media (max-width:430px) { .shell { padding-left:10px; padding-right:10px; } .card { padding:14px; border-radius:14px; } .amounts { gap:7px; } .amounts input { padding-left:8px; padding-right:8px; } .remaining-box { padding-left:8px; } }
    </style>
</head>
<body>
<main class="shell">
    <header class="top">
        <h1>Quick Pooja Collection</h1>
        <p class="sub">Create a bill, record the amount received, and keep the balance due.</p>
    </header>

    <div id="message" class="message" role="status" aria-live="polite" hidden></div>

    <form id="collectionForm" autocomplete="off">
        <section class="card">
            <h2 class="card-title">Customer</h2>
            <div class="field">
                <label for="customerSearch">Find existing customer</label>
                <input id="customerSearch" type="search" placeholder="Name, mobile number or address" autocomplete="off">
                <div id="customerResults" class="customer-results" role="listbox"></div>
            </div>
            <div id="selectedCustomer" class="selected" hidden>
                <div><strong id="selectedCustomerName"></strong><span id="selectedCustomerInfo"></span></div>
                <button class="link-button" type="button" id="changeCustomer">Change</button>
            </div>
            <input type="hidden" id="customerId" name="customer_id">
            <button class="link-button" type="button" id="showNewCustomer">+ Add new customer</button>
            <div class="new-customer" id="newCustomerFields" hidden>
                <div class="field"><label for="newCustomerName">Customer name *</label><input id="newCustomerName" name="new_customer_name" type="text" placeholder="Enter name"></div>
                <div class="grid">
                    <div class="field"><label for="newCustomerMobile">Mobile number</label><input id="newCustomerMobile" name="new_customer_mobile" type="tel" inputmode="numeric" placeholder="Can be added later"></div>
                    <div class="field"><label for="newCustomerAddress">Address</label><input id="newCustomerAddress" name="new_customer_address" type="text" placeholder="Can be added later"></div>
                </div>
            </div>
        </section>

        <section class="card">
            <h2 class="card-title">Bill details</h2>
            <div class="field">
                <label for="serviceName">Product / service *</label>
                <input id="serviceName" name="service_name" list="serviceSuggestions" type="text" placeholder="Start typing a product or service" required>
                <datalist id="serviceSuggestions"></datalist>
                <p class="hint">Previously used product and service names appear while you type.</p>
            </div>
            <div class="grid">
                <div class="field"><label for="invoiceDate">Date</label><input id="invoiceDate" name="invoice_date" type="date" value="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="field"><label for="payMethod">Payment method</label><select id="payMethod" name="pay_method"><option>Cash</option><option>UPI</option><option>Bank</option><option>Other</option></select></div>
            </div>
            <div class="amounts">
                <div class="field"><label for="totalAmount">Bill total *</label><input id="totalAmount" name="total_amount" type="number" inputmode="decimal" min="0.01" step="0.01" placeholder="0" required></div>
                <div class="field"><label for="paidAmount">Paid now</label><input id="paidAmount" name="paid_amount" type="number" inputmode="decimal" min="0" step="0.01" value="0" placeholder="0"></div>
                <div class="field"><label>Remaining</label><div class="remaining-box"><span>Balance due</span><strong id="remainingAmount">₹0.00</strong></div></div>
            </div>
            <div class="grid">
                <div class="field"><label for="transactionDetails">UPI / reference</label><input id="transactionDetails" name="transaction_details" type="text" placeholder="Optional"></div>
                <div class="field"><label for="note">Note</label><input id="note" name="note" type="text" placeholder="Optional"></div>
            </div>
        </section>

        <div class="save-wrap"><button id="saveButton" class="save" type="submit">Save Bill & Payment</button></div>
    </form>

    <section id="history" class="card history" hidden>
        <h2 class="card-title">Customer history</h2>
        <div class="summary-grid">
            <div class="summary"><span>Total billed</span><strong id="historyBilled">₹0.00</strong></div>
            <div class="summary"><span>Total paid</span><strong id="historyPaid">₹0.00</strong></div>
            <div class="summary"><span>Balance due</span><strong id="historyDue">₹0.00</strong></div>
        </div>
        <h3 class="history-title">Bills</h3>
        <div class="table-wrap"><table><thead><tr><th>Date</th><th>Service</th><th class="right">Bill</th><th class="right">Paid</th><th class="right">Due</th></tr></thead><tbody id="invoiceHistory"></tbody></table></div>
        <h3 class="history-title">Payments</h3>
        <div class="table-wrap"><table><thead><tr><th>Date</th><th>Method</th><th>Reference / note</th><th class="right">Paid</th></tr></thead><tbody id="paymentHistory"></tbody></table></div>
    </section>
</main>
<script>
(function () {
    var endpoint = window.location.pathname;
    var form = document.getElementById('collectionForm');
    var customerSearch = document.getElementById('customerSearch');
    var customerResults = document.getElementById('customerResults');
    var customerId = document.getElementById('customerId');
    var newCustomerFields = document.getElementById('newCustomerFields');
    var serviceName = document.getElementById('serviceName');
    var serviceSuggestions = document.getElementById('serviceSuggestions');
    var totalAmount = document.getElementById('totalAmount');
    var paidAmount = document.getElementById('paidAmount');
    var message = document.getElementById('message');
    var customerTimer;
    var serviceTimer;

    function money(value) { return '₹' + Number(value || 0).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2}); }
    function escapeHtml(value) { var div = document.createElement('div'); div.textContent = value || ''; return div.innerHTML; }
    function setMessage(text, type) { message.textContent = text; message.className = 'message ' + type; message.hidden = !text; }
    function updateRemaining() {
        var total = Number(totalAmount.value || 0);
        var paid = Number(paidAmount.value || 0);
        document.getElementById('remainingAmount').textContent = money(Math.max(0, total - paid));
    }
    function clearCustomerSelection() {
        customerId.value = '';
        document.getElementById('selectedCustomer').hidden = true;
        document.getElementById('history').hidden = true;
    }
    function selectCustomer(customer) {
        customerId.value = customer.id;
        customerSearch.value = '';
        customerResults.innerHTML = '';
        newCustomerFields.hidden = true;
        document.getElementById('newCustomerName').value = '';
        document.getElementById('newCustomerMobile').value = '';
        document.getElementById('newCustomerAddress').value = '';
        document.getElementById('selectedCustomerName').textContent = customer.name;
        document.getElementById('selectedCustomerInfo').textContent = [customer.mobile, customer.address].filter(Boolean).join(' • ') || 'No mobile or address saved';
        document.getElementById('selectedCustomer').hidden = false;
        loadHistory(customer.id);
    }
    function renderCustomerResults(customers, query) {
        if (!customers.length) {
            customerResults.innerHTML = '<div class="empty">No customer found. <button type="button" class="link-button" id="addSearchedCustomer">Add as new customer</button></div>';
            document.getElementById('addSearchedCustomer').addEventListener('click', function () {
                newCustomerFields.hidden = false;
                document.getElementById('newCustomerName').value = query;
                document.getElementById('newCustomerName').focus();
                customerResults.innerHTML = '';
            });
            return;
        }
        customerResults.innerHTML = customers.map(function (customer) {
            var info = [customer.mobile, customer.address].filter(Boolean).join(' • ');
            var due = Number(customer.remaining || 0) > 0 ? 'Balance: ' + money(customer.remaining) : 'No balance due';
            return '<button type="button" class="customer-result" data-customer="' + escapeHtml(JSON.stringify(customer)) + '"><strong>' + escapeHtml(customer.name) + '</strong><small>' + escapeHtml(info) + '</small><small>' + due + '</small></button>';
        }).join('');
        customerResults.querySelectorAll('[data-customer]').forEach(function (button) {
            button.addEventListener('click', function () { selectCustomer(JSON.parse(button.getAttribute('data-customer'))); });
        });
    }
    function loadHistory(id) {
        fetch(endpoint + '?action=history&customer_id=' + encodeURIComponent(id))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.error) { return; }
                document.getElementById('historyBilled').textContent = money(data.summary.total_bill);
                document.getElementById('historyPaid').textContent = money(data.summary.total_paid);
                document.getElementById('historyDue').textContent = money(data.summary.remaining);
                document.getElementById('invoiceHistory').innerHTML = data.invoices.length ? data.invoices.map(function (invoice) {
                    var due = Math.max(0, Number(invoice.total_amount) - Number(invoice.paid_amount));
                    return '<tr><td>' + escapeHtml(invoice.invoice_date) + '</td><td>' + escapeHtml(invoice.services || invoice.notes || '-') + '</td><td class="right">' + money(invoice.total_amount) + '</td><td class="right">' + money(invoice.paid_amount) + '</td><td class="right">' + money(due) + '</td></tr>';
                }).join('') : '<tr><td colspan="5" class="empty">No bills yet.</td></tr>';
                document.getElementById('paymentHistory').innerHTML = data.payments.length ? data.payments.map(function (payment) {
                    return '<tr><td>' + escapeHtml(payment.paid_date) + '</td><td>' + escapeHtml(payment.method || '-') + '</td><td>' + escapeHtml(payment.transaction_details || payment.note || '-') + '</td><td class="right">' + money(payment.paid_amount) + '</td></tr>';
                }).join('') : '<tr><td colspan="4" class="empty">No payments yet.</td></tr>';
                document.getElementById('history').hidden = false;
            });
    }

    customerSearch.addEventListener('input', function () {
        var query = customerSearch.value.trim();
        clearCustomerSelection();
        clearTimeout(customerTimer);
        if (!query) { customerResults.innerHTML = ''; return; }
        customerTimer = setTimeout(function () {
            fetch(endpoint + '?action=customers&q=' + encodeURIComponent(query))
                .then(function (response) { return response.json(); })
                .then(function (data) { renderCustomerResults(data.customers || [], query); })
                .catch(function () { customerResults.innerHTML = '<div class="empty">Customer search is unavailable.</div>'; });
        }, 180);
    });
    document.getElementById('showNewCustomer').addEventListener('click', function () {
        clearCustomerSelection();
        newCustomerFields.hidden = !newCustomerFields.hidden;
        if (!newCustomerFields.hidden) { document.getElementById('newCustomerName').focus(); }
    });
    document.getElementById('changeCustomer').addEventListener('click', function () { clearCustomerSelection(); customerSearch.focus(); });
    serviceName.addEventListener('input', function () {
        var query = serviceName.value.trim();
        clearTimeout(serviceTimer);
        serviceTimer = setTimeout(function () {
            fetch(endpoint + '?action=products&q=' + encodeURIComponent(query))
                .then(function (response) { return response.json(); })
                .then(function (data) { serviceSuggestions.innerHTML = (data.services || []).map(function (name) { return '<option value="' + escapeHtml(name) + '"></option>'; }).join(''); });
        }, 150);
    });
    totalAmount.addEventListener('input', updateRemaining);
    paidAmount.addEventListener('input', updateRemaining);

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var total = Number(totalAmount.value || 0);
        var paid = Number(paidAmount.value || 0);
        if (paid > total) { setMessage('Paid amount cannot be more than bill total.', 'error'); return; }
        if (!customerId.value && !document.getElementById('newCustomerName').value.trim()) { setMessage('Select a customer or add a new customer name.', 'error'); return; }
        var button = document.getElementById('saveButton');
        var data = new FormData(form);
        data.append('action', 'save');
        button.disabled = true;
        setMessage('', '');
        fetch(endpoint, {method:'POST', body:data})
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (!result.success) { throw new Error(result.error || 'Could not save the bill.'); }
                setMessage(result.message, 'ok');
                selectCustomer({id: result.customer_id, name: document.getElementById('selectedCustomerName').textContent || document.getElementById('newCustomerName').value, mobile:'', address:''});
                serviceName.value = '';
                totalAmount.value = '';
                paidAmount.value = '0';
                document.getElementById('transactionDetails').value = '';
                document.getElementById('note').value = '';
                updateRemaining();
                serviceName.focus();
            })
            .catch(function (error) { setMessage(error.message || 'Could not save the bill.', 'error'); })
            .finally(function () { button.disabled = false; });
    });
}());
</script>
</body>
</html>
