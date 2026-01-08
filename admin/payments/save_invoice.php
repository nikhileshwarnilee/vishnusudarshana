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
    $product_names = isset($_POST['product_name']) ? $_POST['product_name'] : [];
    $product_qtys = isset($_POST['product_qty']) ? $_POST['product_qty'] : [];
    $product_amounts = isset($_POST['product_amount']) ? $_POST['product_amount'] : [];

    if (!$customer_id || !$invoice_date || empty($product_names) || empty($product_qtys) || empty($product_amounts)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
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

    echo json_encode(['success' => true, 'invoice_id' => $invoice_id]);
} catch (Exception $e) {
    error_log('Invoice Save Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
