<?php
// save_invoice.php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

try {
    // Validate required fields
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    $invoice_date = isset($_POST['invoice_date']) ? $_POST['invoice_date'] : '';
    $note = isset($_POST['invoice_note']) ? trim($_POST['invoice_note']) : '';
    $total_qty = isset($_POST['total_qty']) ? intval($_POST['total_qty']) : 0;
    $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0.00;
    // Support both product_name[] and product_name as array (FormData sends as product_name[])
    $product_names = [];
    $product_qtys = [];
    $product_amounts = [];
    if (isset($_POST['product_name'])) {
        $product_names = is_array($_POST['product_name']) ? $_POST['product_name'] : [$_POST['product_name']];
    } elseif (isset($_POST['product_name[]'])) {
        $product_names = is_array($_POST['product_name[]']) ? $_POST['product_name[]'] : [$_POST['product_name[]']];
    }
    if (isset($_POST['product_qty'])) {
        $product_qtys = is_array($_POST['product_qty']) ? $_POST['product_qty'] : [$_POST['product_qty']];
    } elseif (isset($_POST['product_qty[]'])) {
        $product_qtys = is_array($_POST['product_qty[]']) ? $_POST['product_qty[]'] : [$_POST['product_qty[]']];
    }
    if (isset($_POST['product_amount'])) {
        $product_amounts = is_array($_POST['product_amount']) ? $_POST['product_amount'] : [$_POST['product_amount']];
    } elseif (isset($_POST['product_amount[]'])) {
        $product_amounts = is_array($_POST['product_amount[]']) ? $_POST['product_amount[]'] : [$_POST['product_amount[]']];
    }


    // Validate at least one product/service with valid name and amount > 0
    $valid_product = false;
    for ($i = 0; $i < count($product_names); $i++) {
        $name = trim($product_names[$i]);
        $amt = isset($product_amounts[$i]) ? floatval($product_amounts[$i]) : 0.00;
        if ($name !== '' && $amt > 0) {
            $valid_product = true;
            break;
        }
    }
    if (!$customer_id || !$invoice_date || !$valid_product) {
        echo json_encode(['success' => false, 'error' => 'Add at least one product/service with a valid amount.']);
        exit;
    }

    // Insert invoice
    $stmt = $pdo->prepare("INSERT INTO invoices (customer_id, invoice_date, notes, total_qty, total_amount, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$customer_id, $invoice_date, $note, $total_qty, $total_amount]);
    $invoice_id = $pdo->lastInsertId();

    // Insert invoice items
    $itemStmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, product_name, qty, amount) VALUES (?, ?, ?, ?)");
    for ($i = 0; $i < count($product_names); $i++) {
        $name = trim($product_names[$i]);
        $qty = isset($product_qtys[$i]) ? intval($product_qtys[$i]) : 1;
        $amt = isset($product_amounts[$i]) ? floatval($product_amounts[$i]) : 0.00;
        if ($name !== '') {
            $itemStmt->execute([$invoice_id, $name, $qty, $amt]);
        }
    }

    // Collect payment logic removed. Now invoice and payment are handled separately.

    // DEBUG: Return all POST data for troubleshooting
    if (isset($_GET['debug']) || isset($_POST['debug'])) {
        echo json_encode(['post'=>$_POST]);
        exit;
    }

    echo json_encode(['success' => true, 'invoice_id' => $invoice_id]);
} catch (Exception $e) {
    error_log('Invoice Save Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
