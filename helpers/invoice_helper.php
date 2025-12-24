<?php
// Function to generate invoice number (INV-YYYY-0001, yearly reset)
function generateInvoiceNumber(PDO $pdo) {
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE YEAR(invoice_date) = ?");
    $stmt->execute([$year]);
    $count = $stmt->fetchColumn();
    $next = $count + 1;
    return sprintf('INV-%s-%04d', $year, $next);
}
