<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: text/html; charset=UTF-8');

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : '';
$perPage = 10;

// Build query based on filter
$whereClause = '';
if ($categoryFilter) {
    $whereClause = ' WHERE category_slug = ' . $pdo->quote($categoryFilter);
}

$countStmt = $pdo->query("SELECT COUNT(*) FROM products" . $whereClause);
$total_products = (int)$countStmt->fetchColumn();
$total_pages = max(1, ceil($total_products / $perPage));
$page = min($page, $total_pages);
$offset = ($page - 1) * $perPage;

$query = "SELECT * FROM products" . $whereClause . " ORDER BY display_order ASC, id DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categoryNames = [
    'birth-child' => 'Birth & Child Services',
    'marriage-matching' => 'Marriage & Matching',
    'astrology-consultation' => 'Astrology Consultation',
    'muhurat-event' => 'Muhurat & Event Guidance',
    'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
    'appointment' => 'Appointment',
];

// If filtering by category, show all products in that category grouped
if ($categoryFilter) {
    // Get all products for the selected category (no pagination limit)
    $allStmt = $pdo->prepare("SELECT * FROM products WHERE category_slug = ? ORDER BY display_order ASC, id DESC");
    $allStmt->execute([$categoryFilter]);
    $allProducts = $allStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($allProducts) {
        $categoryName = $categoryNames[$categoryFilter] ?? ucfirst(str_replace('-', ' ', $categoryFilter));
        echo '<div class="category-section">';
        echo '<div class="category-heading">' . htmlspecialchars($categoryName) . ' <span class="product-count">(' . count($allProducts) . ' products)</span></div>';
        echo '<table class="service-table">';
        echo '<thead><tr>';
        echo '<th>ID</th>';
        echo '<th>Product Name</th>';
        echo '<th>Price</th>';
        echo '<th>Status</th>';
        echo '<th>Is Mandatory</th>';
        echo '<th>Sequence</th>';
        echo '<th>Actions</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($allProducts as $product) {
            echo '<tr>';
            echo '<td>' . $product['id'] . '</td>';
            echo '<td>' . htmlspecialchars($product['product_name']) . '</td>';
            echo '<td>₹' . number_format($product['price'], 2) . '</td>';
            echo '<td><span class="status-badge ' . ($product['is_active'] ? 'status-completed' : 'status-cancelled') . '">' . ($product['is_active'] ? 'Active' : 'Inactive') . '</span></td>';
            echo '<td>';
            echo '<select class="mandatory-select" data-id="' . $product['id'] . '" style="padding:4px 10px;border-radius:6px;">';
            echo '<option value="0"' . (empty($product['is_mandatory']) ? ' selected' : '') . '>No</option>';
            echo '<option value="1"' . (!empty($product['is_mandatory']) ? ' selected' : '') . '>Yes</option>';
            echo '</select>';
            echo '</td>';
            echo '<td>';
            echo '<input type="number" class="seq-input" data-id="' . $product['id'] . '" data-category="' . htmlspecialchars($categoryFilter) . '" value="' . ($product['display_order'] ?? 0) . '" style="width:70px;padding:4px;border:1px solid #ddd;border-radius:4px;" placeholder="Order">';
            echo '</td>';
            echo '<td>';
            echo '<a href="edit.php?id=' . $product['id'] . '" class="action-btn">Edit</a> ';
            echo '<a href="#" class="action-btn delete" data-id="' . $product['id'] . '">Delete</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    } else {
        echo '<div class="no-products">No products found in this category.</div>';
    }
    echo '<script>window.ajaxPagination = { currentPage: 1, totalPages: 1 };</script>';
} else {
    // Show all categories with their products
    $allCategoriesStmt = $pdo->query("SELECT DISTINCT category_slug FROM products ORDER BY category_slug ASC");
    $categories = $allCategoriesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!$categories) {
        echo '<div class="no-products">No products found.</div>';
    } else {
        foreach ($categories as $cat) {
            $catStmt = $pdo->prepare("SELECT * FROM products WHERE category_slug = ? ORDER BY display_order ASC, id DESC");
            $catStmt->execute([$cat]);
            $catProducts = $catStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($catProducts) {
                $categoryName = $categoryNames[$cat] ?? ucfirst(str_replace('-', ' ', $cat));
                echo '<div class="category-section">';
                echo '<div class="category-heading">' . htmlspecialchars($categoryName) . ' <span class="product-count">(' . count($catProducts) . ' products)</span></div>';
                echo '<table class="service-table">';
                echo '<thead><tr>';
                echo '<th>ID</th>';
                echo '<th>Product Name</th>';
                echo '<th>Price</th>';
                echo '<th>Status</th>';
                echo '<th>Is Mandatory</th>';
                echo '<th>Sequence</th>';
                echo '<th>Actions</th>';
                echo '</tr></thead>';
                echo '<tbody>';
                
                foreach ($catProducts as $product) {
                    echo '<tr>';
                    echo '<td>' . $product['id'] . '</td>';
                    echo '<td>' . htmlspecialchars($product['product_name']) . '</td>';
                    echo '<td>₹' . number_format($product['price'], 2) . '</td>';
                    echo '<td><span class="status-badge ' . ($product['is_active'] ? 'status-completed' : 'status-cancelled') . '">' . ($product['is_active'] ? 'Active' : 'Inactive') . '</span></td>';
                    echo '<td>';
                    echo '<select class="mandatory-select" data-id="' . $product['id'] . '" style="padding:4px 10px;border-radius:6px;">';
                    echo '<option value="0"' . (empty($product['is_mandatory']) ? ' selected' : '') . '>No</option>';
                    echo '<option value="1"' . (!empty($product['is_mandatory']) ? ' selected' : '') . '>Yes</option>';
                    echo '</select>';
                    echo '</td>';
                    echo '<td>';
                    echo '<input type="number" class="seq-input" data-id="' . $product['id'] . '" data-category="' . htmlspecialchars($cat) . '" value="' . ($product['display_order'] ?? 0) . '" style="width:70px;padding:4px;border:1px solid #ddd;border-radius:4px;" placeholder="Order">';
                    echo '</td>';
                    echo '<td>';
                    echo '<a href="edit.php?id=' . $product['id'] . '" class="action-btn">Edit</a> ';
                    echo '<a href="#" class="action-btn delete" data-id="' . $product['id'] . '">Delete</a>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
                echo '</div>';
            }
        }
    }
    echo '<script>window.ajaxPagination = { currentPage: ' . json_encode($page) . ', totalPages: ' . json_encode($total_pages) . ' };</script>';
}

