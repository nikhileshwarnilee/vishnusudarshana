<?php
require_once __DIR__ . '/../../config/db.php';

$categoryNames = [
    'birth-child' => 'Birth & Child Services',
    'marriage-matching' => 'Marriage & Matching',
    'astrology-consultation' => 'Astrology Consultation',
    'muhurat-event' => 'Muhurat & Event Guidance',
    'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
];

$stmt = $pdo->prepare("SELECT * FROM products ORDER BY id DESC");
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
    @media (max-width: 700px) {
        .admin-container { padding: 12px 2px; }
        .service-table th, .service-table td { padding: 10px 6px; font-size: 0.97em; }
        .service-table { min-width: 600px; }
    }
    </style>
    <script>
    // Optional: Add row highlight on hover for better UX
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.service-table tbody tr').forEach(function(row) {
            row.addEventListener('mouseenter', function() { row.style.background = '#f3f7fa'; });
            row.addEventListener('mouseleave', function() { row.style.background = ''; });
        });
    });
    </script>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Product Management</h1>
    <a href="add.php" class="add-btn">+ Add Product</a>
    <div style="overflow-x:auto;">
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
    </div>
</div>
</body>
</html>
