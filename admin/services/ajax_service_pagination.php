<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: text/html; charset=UTF-8');

// Read inputs
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$search   = trim($_GET['search'] ?? '');
$status   = $_GET['status'] ?? 'All';
$category = $_GET['category'] ?? 'All';
$requestType = $_GET['request_type'] ?? 'all';

$where  = [];
$params = [];

if ($status !== 'All') {
    $where[]  = 'service_status = ?';
    $params[] = $status;
}
if ($category !== 'All') {
    $where[]  = 'category_slug = ?';
    $params[] = $category;
} else {
    $where[] = "category_slug != 'appointment'";
}
if ($search !== '') {
    $where[] = '(tracking_id LIKE ? OR mobile LIKE ? OR customer_name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($requestType === 'online') {
    $where[] = "(tracking_id LIKE 'VDSK%')";
} elseif ($requestType === 'offline') {
    $where[] = "(tracking_id LIKE 'SR%')";
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$countSql = "SELECT COUNT(*) FROM service_requests $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));
$page = min($page, $totalPages);
$offset  = ($page - 1) * $perPage;

$sql = "
    SELECT id, tracking_id, customer_name, mobile,
           category_slug, total_amount,
           payment_status, service_status,
           created_at, selected_products
    FROM service_requests
    $whereSql
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$catMap = [
    'birth-child' => 'Birth & Child Services',
    'marriage-matching' => 'Marriage & Matching',
    'astrology-consultation' => 'Astrology Consultation',
    'muhurat-event' => 'Muhurat & Event Guidance',
    'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
];

if (!$requests) {
    echo '<tr><td colspan="10" class="no-data">No service requests found.</td></tr>';
    echo '<script>window.ajaxPagination = { currentPage: ' . json_encode($page) . ', totalPages: ' . json_encode($totalPages) . ' };</script>';
    exit;
}

foreach ($requests as $row) {
    // Products
    $products = '-';
    if (!empty($row['selected_products'])) {
        $decoded = json_decode($row['selected_products'], true);
        if (is_array($decoded)) {
            $names = [];
            foreach ($decoded as $prod) {
                if (!empty($prod['id'])) {
                    static $productNameCache = [];
                    $pid = (int)$prod['id'];
                    if (!isset($productNameCache[$pid])) {
                        $pstmt = $pdo->prepare('SELECT product_name FROM products WHERE id = ?');
                        $pstmt->execute([$pid]);
                        $prow = $pstmt->fetch();
                        $productNameCache[$pid] = $prow ? $prow['product_name'] : 'Product#'.$pid;
                    }
                    $names[] = htmlspecialchars($productNameCache[$pid]);
                }
            }
            if ($names) {
                $products = implode(', ', $names);
            }
        }
    }
    $catSlug = $row['category_slug'];
    $categoryText = $catMap[$catSlug] ?? $catSlug;
    echo '<tr>';
    echo '<td>' . htmlspecialchars($row['tracking_id']) . '</td>';
    echo '<td>' . htmlspecialchars($row['customer_name']) . '</td>';
    echo '<td>' . htmlspecialchars($row['mobile']) . '</td>';
    echo '<td>' . htmlspecialchars($products) . '</td>';
    echo '<td>' . htmlspecialchars($categoryText) . '</td>';
    echo '<td>₹' . number_format($row['total_amount'], 2) . '</td>';
    $payClass = 'payment-' . strtolower(str_replace(' ', '-', $row['payment_status']));
    $isOffline = !empty($row['selected_products']);
    $trackingId = $row['tracking_id'];
    if (strpos($trackingId, 'SR') === 0) {
        echo '<td><button class="btn-pay" data-id="'.(int)$row['id'].'" style="background:#c00;color:#fff;padding:6px 14px;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Unpaid</button></td>';
    } elseif (strpos($trackingId, 'VDSK') === 0) {
        echo '<td><span class="status-badge payment-paid" style="background:#e5ffe5;color:#1a8917;">Paid</span></td>';
    } else {
        echo '<td><span class="status-badge" style="background:#eee;color:#888;">-</span></td>';
    }
    $statusClass = 'status-' . strtolower(str_replace(' ', '-', $row['service_status']));
    echo '<td><span class="status-badge ' . $statusClass . '">' . htmlspecialchars($row['service_status']) . '</span></td>';
    echo '<td>' . date('d-m-Y', strtotime($row['created_at'])) . '</td>';
    echo '<td><a class="view-btn" href="view.php?id=' . (int)$row['id'] . '">View</a></td>';
    echo '</tr>';
}
// Pagination info for JS
// window.ajaxPagination = { currentPage: ..., totalPages: ... }
echo '<script>window.ajaxPagination = { currentPage: ' . json_encode($page) . ', totalPages: ' . json_encode($totalPages) . ' };</script>';
