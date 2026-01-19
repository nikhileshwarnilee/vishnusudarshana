<?php
// Temporary debug script to check products array structure
require_once __DIR__ . '/config/db.php';

echo "=== CHECKING SERVICE_REQUESTS TABLE ===\n";
$stmt = $pdo->prepare("
    SELECT tracking_id, selected_products 
    FROM service_requests 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "Latest Tracking ID: " . $result['tracking_id'] . "\n\n";
    echo "Products JSON: " . $result['selected_products'] . "\n\n";
    
    $products = json_decode($result['selected_products'], true);
    echo "Products Array:\n";
    print_r($products);
    echo "\n\n";
} else {
    echo "No service requests found\n";

echo "\n=== CHECKING PENDING_PAYMENTS TABLE ===\n";
$stmt2 = $pdo->prepare("
    SELECT payment_id, selected_products, source, category 
    FROM pending_payments 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt2->execute();
$result2 = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($result2) {
    echo "Payment ID: " . $result2['payment_id'] . "\n";
    echo "Source: " . $result2['source'] . "\n";
    echo "Category: " . $result2['category'] . "\n";
    echo "Products JSON: " . $result2['selected_products'] . "\n\n";
    
    $products2 = json_decode($result2['selected_products'], true);
    echo "Products Array:\n";
    print_r($products2);
    
    // Check if products exist in database
    if (!empty($products2) && is_array($products2)) {
        echo "\nChecking products in database:\n";
        foreach ($products2 as $product) {
            $id = $product['id'] ?? $product['product_id'] ?? null;
            if ($id) {
                $prodStmt = $pdo->prepare('SELECT id, name FROM products WHERE id = ?');
                $prodStmt->execute([$id]);
                $prod = $prodStmt->fetch(PDO::FETCH_ASSOC);
                if ($prod) {
                    echo "  ID {$id}: {$prod['name']}\n";
                } else {
                    echo "  ID {$id}: NOT FOUND IN DATABASE\n";
                }
            }
        }
    }
} else {
    echo "No pending payments found\n";
}
}
