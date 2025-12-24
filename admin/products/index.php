<?php
require_once __DIR__ . '/../../config/db.php';

$categoryNames = [
    'birth-child' => 'Birth & Child Services',
    'marriage-matching' => 'Marriage & Matching',
    'astrology-consultation' => 'Astrology Consultation',
    'muhurat-event' => 'Muhurat & Event Guidance',
    'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
];

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$countStmt = $pdo->query("SELECT COUNT(*) FROM products");
$total_products = (int)$countStmt->fetchColumn();
$total_pages = max(1, ceil($total_products / $perPage));
$offset = ($page - 1) * $perPage;
$stmt = $pdo->prepare("SELECT * FROM products ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Product Management</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
    body { font-family: Arial, sans-serif; background: #f7f7fa; margin: 0; }
    .admin-container { max-width: 1100px; margin: 0 auto; padding: 24px 12px; }
    h1 { color: #800000; margin-bottom: 18px; font-family: inherit; }
    .add-btn { display:inline-block; background:#800000; color:#fff; padding:8px 18px; border-radius:8px; text-decoration:none; font-weight:600; margin-bottom:18px; transition: background 0.15s; }
    .add-btn:hover { background: #a00000; }
    .service-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 12px #e0bebe22; border-radius: 12px; overflow: hidden; font-family: inherit; }
    .service-table th, .service-table td { padding: 12px 10px; border-bottom: 1px solid #f3caca; text-align: left; font-size: 1.04em; }
    .service-table th { background: #f9eaea; color: #800000; font-weight: 700; letter-spacing: 0.01em; }
    .service-table tr:last-child td { border-bottom: none; }
    .action-btn { background: #007bff; color: #fff; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-right: 6px; transition: background 0.15s; }
    .action-btn.delete { background: #c00; }
    .action-btn:hover { background: #0056b3; }
    .action-btn.delete:hover { background: #a00000; }
    .status-badge { padding: 4px 12px; border-radius: 8px; font-weight: 600; font-size: 0.98em; display: inline-block; min-width: 80px; text-align: center; }
    .status-completed { background: #e5ffe5; color: #1a8917; }
    .status-cancelled { background: #ffeaea; color: #c00; }
    .pagination { margin: 18px 0; text-align: center; }
    .pagination a { display:inline-block;padding:8px 14px;margin:0 2px;border-radius:6px;background:#f9eaea;color:#800000;font-weight:600;text-decoration:none; }
    .pagination a.active { background:#800000;color:#fff; }
    .pagination span { padding:8px 6px; }
    @media (max-width: 700px) {
        .admin-container { padding: 12px 2px; }
        .service-table th, .service-table td { padding: 10px 6px; font-size: 0.97em; }
        .service-table { min-width: 600px; }
    }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Product Management</h1>
    <a href="add.php" class="add-btn">+ Add Product</a>
    <div style="overflow-x:auto;">
    <div id="productsAjaxResult">
    <table class="service-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Product Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $product): ?>
            <tr>
                <td><?php echo $product['id']; ?></td>
                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                <td><?php echo $categoryNames[$product['category_slug']] ?? $product['category_slug']; ?></td>
                <td>₹<?php echo number_format($product['price'], 2); ?></td>
                <td>
                    <span class="status-badge <?php echo $product['is_active'] ? 'status-completed' : 'status-cancelled'; ?>">
                        <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </td>
                <td>
                    <a href="edit.php?id=<?php echo $product['id']; ?>" class="action-btn">Edit</a>
                    <a href="delete.php?id=<?php echo $product['id']; ?>" class="action-btn delete" onclick="return confirm('Delete this product?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="pagination">
        <?php
        $maxPagesToShow = 7;
        $startPage = max(1, $page - 2);
        $endPage = min($total_pages, $page + 2);
        if ($startPage > 1) {
            echo '<a href="#" class="page-link" data-page="1">1</a>';
            if ($startPage > 2) echo '<span>...</span>';
        }
        for ($i = $startPage; $i <= $endPage; $i++) {
            echo '<a href="#" class="page-link' . ($i == $page ? ' active' : '') . '" data-page="' . $i . '">' . $i . '</a>';
        }
        if ($endPage < $total_pages) {
            if ($endPage < $total_pages - 1) echo '<span>...</span>';
            echo '<a href="#" class="page-link" data-page="' . $total_pages . '">' . $total_pages . '</a>';
        }
        ?>
    </div>
    </div>
    </div>
</div>
<script>
function loadProductsAjax(page) {
    $.get(window.location.pathname, { page: page, ajax: 1 }, function(res) {
        var html = $(res).find('#productsAjaxResult').html();
        $('#productsAjaxResult').html(html);
    });
}
$(document).on('click', '.page-link', function(e) {
    e.preventDefault();
    var page = $(this).data('page');
    loadProductsAjax(page);
});
</script>
</body>
</html>
