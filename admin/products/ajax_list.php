<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: text/html; charset=UTF-8');

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$countStmt = $pdo->query("SELECT COUNT(*) FROM products");
$total_products = (int)$countStmt->fetchColumn();
$total_pages = max(1, ceil($total_products / $perPage));
$page = min($page, $total_pages);
$offset = ($page - 1) * $perPage;
$stmt = $pdo->prepare("SELECT * FROM products ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categoryNames = [
    'birth-child' => 'Birth & Child Services',
    'marriage-matching' => 'Marriage & Matching',
    'astrology-consultation' => 'Astrology Consultation',
    'muhurat-event' => 'Muhurat & Event Guidance',
    'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
];

if (!$products) {
    echo '<tr><td colspan="6" class="no-data">No products found.</td></tr>';
} else {
    foreach ($products as $product) {
        echo '<tr>';
        echo '<td>' . $product['id'] . '</td>';
        echo '<td>' . htmlspecialchars($product['product_name']) . '</td>';
        echo '<td>' . ($categoryNames[$product['category_slug']] ?? $product['category_slug']) . '</td>';
        echo '<td>₹' . number_format($product['price'], 2) . '</td>';
        echo '<td><span class="status-badge ' . ($product['is_active'] ? 'status-completed' : 'status-cancelled') . '">' . ($product['is_active'] ? 'Active' : 'Inactive') . '</span></td>';
        echo '<td>';
        echo '<a href="edit.php?id=' . $product['id'] . '" class="action-btn">Edit</a> ';
        echo '<a href="delete.php?id=' . $product['id'] . '" class="action-btn delete" onclick="return confirm(\'Delete this product?\');">Delete</a>';
        echo '</td>';
        echo '</tr>';
    }
}
echo '<script>window.ajaxPagination = { currentPage: ' . json_encode($page) . ', totalPages: ' . json_encode($total_pages) . ' };</script>';
// Output pagination metadata for JS
echo '<script>window.ajaxPagination = { currentPage: ' . json_encode($page) . ', totalPages: ' . json_encode($total_pages) . ' };</script>';
