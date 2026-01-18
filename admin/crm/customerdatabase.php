<?php
// admin/customers/crm/index.php
require_once __DIR__ . '/../includes/top-menu.php';
require_once __DIR__ . '/../../config/db.php';

// Pagination and search
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$perPage = 10;
$params = [];
$where = '';
if ($search !== '') {
    $where = "WHERE name LIKE :q OR mobile LIKE :q OR address_city LIKE :q";
    $params['q'] = "%$search%";
}

// Build unified query using UNION ALL
$sql = "
    SELECT name, mobile, address as address_city FROM cif_clients
    UNION ALL
    SELECT name, mobile, address as address_city FROM customers
    UNION ALL
    SELECT customer_name as name, mobile, city as address_city FROM service_requests
";
if ($search !== '') {
    $sql = "
        SELECT * FROM (
            SELECT name, mobile, address as address_city FROM cif_clients
            UNION ALL
            SELECT name, mobile, address as address_city FROM customers
            UNION ALL
            SELECT customer_name as name, mobile, city as address_city FROM service_requests
        ) AS all_customers
        $where
    ";
}

// Get total count
$countSql = "SELECT COUNT(*) FROM (" . str_replace('SELECT * FROM', 'SELECT 1 FROM', $sql) . ") AS total";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total_customers = (int)$countStmt->fetchColumn();
$total_pages = max(1, ceil($total_customers / $perPage));
$offset = ($page - 1) * $perPage;

// Add LIMIT for pagination
$sql .= " LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Database</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
        .admin-container { max-width: 1100px; margin: 0 auto; padding: 24px 12px; }
        h1 { color: #800000; margin-bottom: 18px; }
        .filter-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 18px; }
        .filter-bar input { min-width: 220px; padding: 7px 12px; border-radius: 6px; font-size: 1em; border: 1px solid #ddd; }
        .filter-bar .btn-main { padding: 8px 16px; background: #800000; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .filter-bar .btn-main:hover { background: #600000; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 8px #e0bebe22; border-radius: 12px; overflow: hidden; }
        table th, table td { padding: 12px 10px; text-align: left; }
        table thead { background: #f9eaea; color: #800000; }
        table tbody tr:hover { background: #f1f1f1; }
        .page-link { display: inline-block; padding: 8px 14px; margin: 0 2px; border-radius: 6px; background: #f9eaea; color: #800000; font-weight: 600; text-decoration: none; }
        .page-link:hover { background: #800000; color: #fff; }
        .page-link.active { background: #800000; color: #fff; }
        .summary-cards { display: flex; gap: 18px; margin-bottom: 24px; flex-wrap: wrap; }
        .summary-card { flex: 1 1 180px; background: #fffbe7; border-radius: 14px; padding: 16px; text-align: center; box-shadow: 0 2px 8px #e0bebe22; }
        .summary-count { font-size: 2.2em; font-weight: 700; color: #800000; }
        .summary-label { font-size: 1em; color: #444; }
    </style>
</head>
<body>
<?php /* ...existing code for top menu... */ ?>
<div class="admin-container">
    <h1>Customer Database</h1>
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-count"><?= $total_customers ?></div>
            <div class="summary-label">Total Customers</div>
        </div>
    </div>
    <form class="filter-bar" method="get" action="">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search customers...">
        <button type="submit" class="btn-main">Search</button>
    </form>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Mobile</th>
                <th>Address/City</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($customers)): ?>
            <tr><td colspan="3" style="text-align:center;color:#888;">No customers found.</td></tr>
        <?php else: foreach ($customers as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td><?= htmlspecialchars($c['mobile']) ?></td>
                <td><?= htmlspecialchars($c['address_city']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <div style="margin-top:18px;text-align:center;">
        <?php
        // Improved pagination logic
        $maxPagesToShow = 5;
        $startPage = max(1, $page - 2);
        $endPage = min($total_pages, $page + 2);
        if ($endPage - $startPage < $maxPagesToShow - 1) {
            if ($startPage === 1) {
                $endPage = min($total_pages, $startPage + $maxPagesToShow - 1);
            } else {
                $startPage = max(1, $endPage - $maxPagesToShow + 1);
            }
        }
        if ($startPage > 1) {
            echo '<a class="page-link" href="?search=' . urlencode($search) . '&page=1">1</a>';
            if ($startPage > 2) echo '<span class="page-link" style="background:none;color:#888;cursor:default;">...</span>';
        }
        for ($i = $startPage; $i <= $endPage; $i++) {
            echo '<a class="page-link' . ($i === $page ? ' active' : '') . '" href="?search=' . urlencode($search) . '&page=' . $i . '">' . $i . '</a>';
        }
        if ($endPage < $total_pages) {
            if ($endPage < $total_pages - 1) echo '<span class="page-link" style="background:none;color:#888;cursor:default;">...</span>';
            echo '<a class="page-link" href="?search=' . urlencode($search) . '&page=' . $total_pages . '">' . $total_pages . '</a>';
        }
        ?>
    </div>
</div>
</body>
</html>
