<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/db.php';

$hasWebhookLogsTable = false;
try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'razorpay_webhook_logs'");
    $hasWebhookLogsTable = (bool)$tableCheck->fetchColumn();
} catch (Throwable $e) {
    $hasWebhookLogsTable = false;
}

// 1) Pending non-appointment online payments that never finalized into service_requests
$pendingStmt = $pdo->prepare("
    SELECT p.*
    FROM pending_payments p
    LEFT JOIN service_requests s ON s.razorpay_order_id = p.razorpay_order_id
    WHERE p.category != 'appointment'
      AND s.id IS NULL
    ORDER BY p.created_at DESC
");
$pendingStmt->execute();
$pendingRows = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

// 2) Existing service_requests explicitly marked as failed
$failedStmt = $pdo->prepare("
    SELECT id, tracking_id, customer_name, mobile, category_slug, payment_status, service_status, form_data, selected_products, created_at, razorpay_order_id
    FROM service_requests
    WHERE category_slug != 'appointment'
      AND LOWER(TRIM(COALESCE(payment_status, ''))) = 'failed'
    ORDER BY created_at DESC
");
$failedStmt->execute();
$failedServiceRequests = $failedStmt->fetchAll(PDO::FETCH_ASSOC);

$latestWebhookEventsByOrder = [];
if ($hasWebhookLogsTable && !empty($pendingRows)) {
    $orderIds = [];
    foreach ($pendingRows as $row) {
        $orderId = trim((string)($row['razorpay_order_id'] ?? ''));
        if ($orderId !== '') {
            $orderIds[$orderId] = true;
        }
    }
    if (!empty($orderIds)) {
        $orderIds = array_keys($orderIds);
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $evSql = "
            SELECT l.razorpay_order_id, l.event_type
            FROM razorpay_webhook_logs l
            INNER JOIN (
                SELECT razorpay_order_id, MAX(id) AS max_id
                FROM razorpay_webhook_logs
                WHERE razorpay_order_id IN ($placeholders)
                GROUP BY razorpay_order_id
            ) x ON x.max_id = l.id
        ";
        $evStmt = $pdo->prepare($evSql);
        $evStmt->execute($orderIds);
        while ($ev = $evStmt->fetch(PDO::FETCH_ASSOC)) {
            $oid = trim((string)($ev['razorpay_order_id'] ?? ''));
            if ($oid !== '') {
                $latestWebhookEventsByOrder[$oid] = strtolower(trim((string)($ev['event_type'] ?? '')));
            }
        }
    }
}

$records = [];
$pendingCount = 0;
$failedCount = 0;

foreach ($pendingRows as $row) {
    $customerDetails = json_decode($row['customer_details'] ?? '', true) ?? [];
    $formData = json_decode($row['form_data'] ?? '', true) ?? [];
    $orderId = trim((string)($row['razorpay_order_id'] ?? ''));
    $paymentBadgeClass = 'payment-pending';
    $paymentBadgeText = 'Pending Confirmation';
    if ($orderId !== '' && isset($latestWebhookEventsByOrder[$orderId]) && $latestWebhookEventsByOrder[$orderId] === 'payment.failed') {
        $paymentBadgeClass = 'payment-failed';
        $paymentBadgeText = 'Failed';
        $failedCount++;
    } else {
        $pendingCount++;
    }

    $records[] = [
        'source' => 'pending',
        'id' => (int)($row['id'] ?? 0),
        'tracking_id' => (string)($row['payment_id'] ?? ''),
        'customer_name' => (string)($customerDetails['full_name'] ?? ($formData['full_name'] ?? '')),
        'mobile' => (string)($customerDetails['mobile'] ?? ($formData['mobile'] ?? '')),
        'mobile_context' => !empty($customerDetails) ? $customerDetails : $formData,
        'category_slug' => (string)($row['category'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
        'selected_products' => (string)($row['selected_products'] ?? ''),
        'notes' => (string)($customerDetails['notes'] ?? ($formData['notes'] ?? ($formData['message'] ?? ($formData['remark'] ?? '')))),
        'payment_badge_class' => $paymentBadgeClass,
        'payment_badge_text' => $paymentBadgeText,
        'service_badge_class' => 'status-default',
        'service_badge_text' => 'Unaccepted',
        'view_id' => null,
    ];
}

foreach ($failedServiceRequests as $row) {
    $formData = json_decode($row['form_data'] ?? '', true) ?? [];
    $serviceStatusRaw = trim((string)($row['service_status'] ?? ''));
    $serviceBadgeClass = $serviceStatusRaw !== '' ? 'status-' . strtolower(str_replace(' ', '-', $serviceStatusRaw)) : 'status-default';
    $serviceBadgeText = $serviceStatusRaw !== '' ? $serviceStatusRaw : '-';
    $failedCount++;

    $records[] = [
        'source' => 'service_request_failed',
        'id' => (int)($row['id'] ?? 0),
        'tracking_id' => (string)($row['tracking_id'] ?? ''),
        'customer_name' => (string)($row['customer_name'] ?? ''),
        'mobile' => (string)($row['mobile'] ?? ''),
        'mobile_context' => $formData,
        'category_slug' => (string)($row['category_slug'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
        'selected_products' => (string)($row['selected_products'] ?? ''),
        'notes' => (string)($formData['notes'] ?? ($formData['message'] ?? ($formData['remark'] ?? ''))),
        'payment_badge_class' => 'payment-failed',
        'payment_badge_text' => 'Failed',
        'service_badge_class' => $serviceBadgeClass,
        'service_badge_text' => $serviceBadgeText,
        'view_id' => (int)($row['id'] ?? 0),
    ];
}

usort($records, static function(array $a, array $b): int {
    $at = strtotime((string)($a['created_at'] ?? ''));
    $bt = strtotime((string)($b['created_at'] ?? ''));
    if ($at === $bt) {
        return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
    }
    return $bt <=> $at;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Pending/Failed Service Payments</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f7f7fa;
    margin: 0;
}
.admin-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px 12px;
}
h1 {
    color: #800000;
    margin-bottom: 18px;
}
.service-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    box-shadow: 0 2px 12px #e0bebe22;
    border-radius: 12px;
    table-layout: auto;
    font-size: 0.85em;
}
.service-table th,
.service-table td {
    padding: 8px 6px;
    border-bottom: 1px solid #f3caca;
    text-align: left;
    white-space: nowrap;
}
.service-table th {
    background: #f9eaea;
    color: #800000;
    font-size: 0.9em;
    font-weight: 600;
}
.service-table td {
    font-size: 0.95em;
}
.service-table tbody tr:hover {
    background: #f3f7fa;
}
.status-badge {
    padding: 4px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9em;
    display: inline-block;
    min-width: 80px;
    text-align: center;
}
.payment-pending { background: #fff8e5; color: #8a5b00; }
.payment-failed { background: #ffeaea; color: #c00; }
.status-received { background: #e5f0ff; color: #0056b3; }
.status-in-progress { background: #fffbe5; color: #b36b00; }
.status-completed { background: #e5ffe5; color: #1a8917; }
.status-cancelled { background: #ffeaea; color: #c00; }
.status-default { background: #f2f2f2; color: #666; }
.no-data {
    text-align: center;
    color: #777;
    padding: 24px;
}
.summary-cards {
    display: flex;
    gap: 16px;
    margin-bottom: 18px;
    flex-wrap: wrap;
}
.summary-card {
    background: #fffbe7;
    border-radius: 10px;
    padding: 14px 18px;
    box-shadow: 0 2px 8px #e0bebe22;
    min-width: 180px;
}
.summary-count {
    font-size: 1.8em;
    font-weight: 700;
    color: #800000;
}
.summary-label {
    color: #444;
    margin-top: 2px;
}
.view-btn {
    background: #800000;
    color: #fff;
    padding: 6px 12px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9em;
    transition: background 0.2s;
}
.view-btn:hover {
    background: #a00000;
}
</style>
</head>

<body>
<div style="max-width:600px;margin:18px auto 0 auto;text-align:center;">
    <input type="text" id="globalSearch" placeholder="Search pending/failed service payments..." style="width:100%;padding:10px 14px;font-size:1.1em;border-radius:8px;border:1px solid #ccc;">
</div>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">

<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-count"><?= count($records) ?></div>
        <div class="summary-label">Pending/Failed Service Payments</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?= $pendingCount ?></div>
        <div class="summary-label">Pending Confirmation</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?= $failedCount ?></div>
        <div class="summary-label">Failed</div>
    </div>
</div>

<?php if (empty($records)): ?>
    <div class="no-data" style="font-size:1.2em;color:#800000;font-weight:600;">
        No pending/failed service payment requests found.
    </div>
<?php else: ?>
    <table class="service-table">
        <thead>
            <tr>
                <th>Customer Name</th>
                <th>Mobile</th>
                <th>Category</th>
                <th>Created Date</th>
                <th>Products</th>
                <th>ID</th>
                <th>Tracking ID</th>
                <th>Payment Status</th>
                <th>Service Status</th>
                <th>Notes</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($records as $row): ?>
                <?php
                $selectedProducts = json_decode($row['selected_products'] ?? '', true) ?? [];
                $products = '-';
                if (is_array($selectedProducts) && count($selectedProducts)) {
                    $productDetails = [];
                    foreach ($selectedProducts as $prod) {
                        if (!is_array($prod)) {
                            continue;
                        }
                        $qty = isset($prod['qty']) ? (int)$prod['qty'] : 1;
                        $name = '';
                        if (!empty($prod['name'])) {
                            $name = (string)$prod['name'];
                        } elseif (!empty($prod['product_name'])) {
                            $name = (string)$prod['product_name'];
                        } elseif (!empty($prod['id'])) {
                            static $productNameCache = [];
                            $pid = (int)$prod['id'];
                            if (!isset($productNameCache[$pid])) {
                                $pstmt = $pdo->prepare('SELECT product_name FROM products WHERE id = ? LIMIT 1');
                                $pstmt->execute([$pid]);
                                $prodName = $pstmt->fetchColumn();
                                $productNameCache[$pid] = $prodName ?: ('Product#' . $pid);
                            }
                            $name = (string)$productNameCache[$pid];
                        }
                        if ($name !== '') {
                            $productDetails[] = htmlspecialchars($name) . ' x' . max(1, $qty);
                        }
                    }
                    if ($productDetails) {
                        $products = implode(', ', $productDetails);
                    }
                }

                $catMap = [
                    'birth-child' => 'Birth & Child Services',
                    'marriage-matching' => 'Marriage & Matching',
                    'astrology-consultation' => 'Astrology Consultation',
                    'muhurat-event' => 'Muhurat & Event Guidance',
                    'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
                ];
                $catSlug = (string)($row['category_slug'] ?? '');
                $categoryDisplay = $catMap[$catSlug] ?? ucwords(str_replace('-', ' ', $catSlug));

                $createdDisplay = '';
                if (!empty($row['created_at'])) {
                    $co = new DateTime($row['created_at']);
                    $createdDisplay = $co->format('d-M-Y h:i A');
                }
                $notes = (string)($row['notes'] ?? '');
                ?>
                <tr>
                    <td><?= htmlspecialchars((string)$row['customer_name']) ?></td>
                    <td><?= htmlspecialchars(vs_format_mobile_from_form_data((string)($row['mobile'] ?? ''), $row['mobile_context'] ?? null)) ?></td>
                    <td><?= htmlspecialchars($categoryDisplay) ?></td>
                    <td><?= htmlspecialchars($createdDisplay) ?></td>
                    <td><?= $products ?></td>
                    <td><?= (int)$row['id'] ?></td>
                    <td><?= htmlspecialchars((string)$row['tracking_id']) ?></td>
                    <td><span class="status-badge <?= htmlspecialchars((string)$row['payment_badge_class']) ?>"><?= htmlspecialchars((string)$row['payment_badge_text']) ?></span></td>
                    <td><span class="status-badge <?= htmlspecialchars((string)$row['service_badge_class']) ?>"><?= htmlspecialchars((string)$row['service_badge_text']) ?></span></td>
                    <td><?= htmlspecialchars($notes) ?></td>
                    <td>
                        <?php if (!empty($row['view_id'])): ?>
                            <a class="view-btn" href="view.php?id=<?= (int)$row['view_id'] ?>">View</a>
                        <?php else: ?>
                            <span style="color:#777;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('globalSearch');
    if (!searchInput) return;
    searchInput.addEventListener('input', function() {
        const val = this.value.trim().toLowerCase();
        document.querySelectorAll('.service-table tbody tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = val === '' || text.includes(val) ? '' : 'none';
        });
    });
});
</script>

</body>
</html>
