<?php
require __DIR__ . '/config/db.php';
$pdo->beginTransaction();
try {
    $productStmt = $pdo->prepare("\n+        SELECT MIN(service_name) AS service_name\n+        FROM (\n+            SELECT TRIM(product_name) AS service_name\n+            FROM invoice_items\n+            WHERE product_name IS NOT NULL AND TRIM(product_name) <> ''\n+            UNION ALL\n+            SELECT TRIM(title) AS service_name\n+            FROM letterpad_titles\n+            WHERE source = 'product' AND title IS NOT NULL AND TRIM(title) <> ''\n+        ) AS saved_services\n+        WHERE service_name LIKE ?\n+        GROUP BY LOWER(service_name)\n+        ORDER BY service_name ASC\n+        LIMIT 20\n+    ");
    $productStmt->execute(['%%']);
    echo 'Products: ' . count($productStmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";
    $customerStmt = $pdo->prepare('INSERT INTO customers (name, mobile, address, created_at) VALUES (?, ?, ?, ?)');
    $customerStmt->execute(['__rollback-only validation__', '', '', date('Y-m-d H:i:s')]);
    $customerId = (int)$pdo->lastInsertId();
    $invoiceStmt = $pdo->prepare('INSERT INTO invoices (customer_id, invoice_date, notes, total_qty, total_amount, created_at) VALUES (?, ?, ?, 1, ?, ?)');
    $invoiceStmt->execute([$customerId, date('Y-m-d'), '__rollback-only validation__', 1.00, date('Y-m-d H:i:s')]);
    $invoiceId = (int)$pdo->lastInsertId();
    $itemStmt = $pdo->prepare('INSERT INTO invoice_items (invoice_id, product_name, qty, amount) VALUES (?, ?, 1, ?)');
    $itemStmt->execute([$invoiceId, '__rollback-only validation__', 1.00]);
    $pdo->prepare('UPDATE invoices SET paid_amount = ? WHERE id = ?')->execute([1.00, $invoiceId]);
    $paymentStmt = $pdo->prepare('INSERT INTO payments (customer_id, invoice_id, paid_date, paid_amount, note, method, transaction_details) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $paymentStmt->execute([$customerId, $invoiceId, date('Y-m-d'), 1.00, '__rollback-only validation__', 'Cash', '']);
    $pdo->rollBack();
    echo "Quick collection billing transaction validated and rolled back.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    throw $e;
}
