<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: text/html; charset=UTF-8');

/* ===============================
   READ INPUTS
=============================== */
$search   = trim($_GET['search'] ?? '');
$status   = $_GET['status'] ?? 'All';
$category = $_GET['category'] ?? 'All';
$page     = max(1, (int)($_GET['page'] ?? 1));

/* ===============================
    PAGINATION
=============================== */
$perPage = 10;

/* ===============================
   FILTER LOGIC
=============================== */
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
    // VISIBILITY CONTROL: Exclude appointment bookings from generic service list
    // Appointments are managed separately in appointmentmanagement.php
    // This ensures index.php shows only non-appointment services
    $where[] = "category_slug != 'appointment'";
}

if ($search !== '') {
    $where[] = '(tracking_id LIKE ? OR mobile LIKE ? OR customer_name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ===============================
    COUNT
=============================== */
$countSql = "SELECT COUNT(*) FROM service_requests $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));
$page = min($page, $totalPages);
$offset  = ($page - 1) * $perPage;

/* ===============================
    QUERY
=============================== */
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

/* ===============================
   CATEGORY MAP
=============================== */
$catMap = [
    'birth-child' => 'Birth & Child Services',
    'marriage-matching' => 'Marriage & Matching',
    'astrology-consultation' => 'Astrology Consultation',
    'muhurat-event' => 'Muhurat & Event Guidance',
    'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
];

/* ===============================
   OUTPUT
=============================== */
if (!$requests) {
    echo '<tr><td colspan="10" class="no-data">No service requests found.</td></tr>';
}

foreach ($requests as $row) {

    // Products
    $products = '-';
    if (!empty($row['selected_products'])) {
        $decoded = json_decode($row['selected_products'], true);
        if (is_array($decoded)) {
            $names = [];
            foreach ($decoded as $prod) {
                if (!empty($prod['name'])) {
                    $names[] = $prod['name'];
                }
            }
            if ($names) {
                $products = implode(', ', $names);
            }
        }
    }

    // Category
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
    echo '<td><span class="status-badge ' . $payClass . '">' . htmlspecialchars($row['payment_status']) . '</span></td>';

    $statusClass = 'status-' . strtolower(str_replace(' ', '-', $row['service_status']));
    echo '<td><span class="status-badge ' . $statusClass . '">' . htmlspecialchars($row['service_status']) . '</span></td>';

    echo '<td>' . date('d-m-Y', strtotime($row['created_at'])) . '</td>';
    echo '<td><a class="view-btn" href="view.php?id=' . (int)$row['id'] . '">View</a></td>';
    echo '</tr>';
}

// Append pagination metadata for client-side rendering
echo '<script>window.ajaxPagination = { currentPage: ' . json_encode($page) . ', totalPages: ' . json_encode($totalPages) . ' };</script>';
