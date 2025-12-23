<?php
// AJAX endpoint for dashboard recent activity (admin/index.php)
require_once __DIR__ . '/../config/db.php';

header('Content-Type: text/html; charset=UTF-8');

$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(tracking_id LIKE ? OR customer_name LIKE ? OR service_status LIKE ? OR category_slug LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM service_requests $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

    $sql = "SELECT id, tracking_id, customer_name, mobile, selected_products, category_slug, service_status, payment_status, created_at FROM service_requests $whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) === 0) {
    echo '<tr><td colspan="9" class="no-data">No recent activity found.</td></tr>';
} else {
    $catMap = [
        'birth-child' => 'Birth & Child Services',
        'marriage-matching' => 'Marriage & Matching',
        'astrology-consultation' => 'Astrology Consultation',
        'muhurat-event' => 'Muhurat & Event Guidance',
        'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
        'appointment' => 'Appointment',
    ];
    foreach ($rows as $row) {
        echo '<tr>';
        // Date
        echo '<td>' . date('d M Y', strtotime($row['created_at'])) . '</td>';
        // Type
        $catSlug = $row['category_slug'];
        $type = ($catSlug === 'appointment') ? 'Appointment' : 'Service';
        echo '<td>' . htmlspecialchars($type) . '</td>';
        // Customer
        echo '<td>' . htmlspecialchars($row['customer_name']) . '</td>';
        // Tracking ID
        echo '<td>' . htmlspecialchars($row['tracking_id']) . '</td>';
        // Mobile
        echo '<td>' . htmlspecialchars($row['mobile']) . '</td>';
        // Product (parse JSON)
        $products = '-';
        $decoded = json_decode($row['selected_products'], true);
        if (is_array($decoded) && count($decoded)) {
            $names = [];
            foreach ($decoded as $prod) {
                if (isset($prod['name'])) {
                    $names[] = htmlspecialchars($prod['name']);
                }
            }
            if ($names) {
                $products = implode(', ', $names);
            }
        }
        echo '<td>' . $products . '</td>';
        // Category (map slug)
        echo '<td>' . (isset($catMap[$catSlug]) ? htmlspecialchars($catMap[$catSlug]) : htmlspecialchars($catSlug)) . '</td>';
        // Status
        echo '<td><span class="status-badge status-' . strtolower(str_replace(' ', '-', $row['service_status'])) . '">' . htmlspecialchars($row['service_status']) . '</span></td>';
        // Action
        echo '<td><a class="view-btn" href="../admin/services/view.php?id=' . $row['id'] . '">View</a></td>';
        echo '</tr>';
    }
}

// Pagination object for JS
$pagination = [
    'totalPages' => $totalPages,
    'currentPage' => $page
];
echo '<script>window.dashboardPagination = ' . json_encode($pagination) . ';</script>';
