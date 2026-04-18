<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';

if (!function_exists('vs_failed_appointments_service_request_columns')) {
    function vs_failed_appointments_service_request_columns(PDO $pdo): array
    {
        static $columns = null;
        if ($columns !== null) {
            return $columns;
        }

        $columns = [];
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM service_requests');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[strtolower((string)($row['Field'] ?? ''))] = true;
            }
        } catch (Throwable $e) {
            error_log('Unable to inspect service_requests columns on failed appointments page: ' . $e->getMessage());
        }

        return $columns;
    }
}

if (!function_exists('vs_failed_appointments_restore_failed_row')) {
    function vs_failed_appointments_restore_failed_row(PDO $pdo, int $appointmentId): bool
    {
        if ($appointmentId <= 0) {
            return false;
        }

        $columns = vs_failed_appointments_service_request_columns($pdo);
        $set = [
            "payment_status = 'Paid'",
            "service_status = 'Received'",
            'updated_at = NOW()',
        ];
        if (isset($columns['booking_status'])) {
            $set[] = "booking_status = 'confirmed'";
        }

        $sql = "
            UPDATE service_requests
            SET " . implode(', ', $set) . "
            WHERE id = ?
              AND category_slug = 'appointment'
              AND LOWER(TRIM(COALESCE(payment_status, ''))) = 'failed'
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$appointmentId]);
        if ($stmt->rowCount() <= 0) {
            return false;
        }

        try {
            $trackingStmt = $pdo->prepare('SELECT tracking_id FROM service_requests WHERE id = ? LIMIT 1');
            $trackingStmt->execute([$appointmentId]);
            $trackingId = (string)($trackingStmt->fetchColumn() ?: '');
            if ($trackingId !== '') {
                $updateTrackingStmt = $pdo->prepare('UPDATE tracking SET service_status = ? WHERE tracking_id = ?');
                $updateTrackingStmt->execute(['Received', $trackingId]);
            }
        } catch (Throwable $e) {
            error_log('Unable to sync tracking while restoring failed appointment id=' . $appointmentId . ': ' . $e->getMessage());
        }

        return true;
    }
}

$hasWebhookLogsTable = false;
try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'razorpay_webhook_logs'");
    $hasWebhookLogsTable = (bool)$tableCheck->fetchColumn();
} catch (Throwable $e) {
    $hasWebhookLogsTable = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM pending_payments WHERE id = ? LIMIT 1");
    $stmt->execute([$deleteId]);
    header('Location: failed-appointments.php?deleted=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_failed_id'])) {
    $restoreId = (int)$_POST['restore_failed_id'];
    $restored = vs_failed_appointments_restore_failed_row($pdo, $restoreId);
    header('Location: failed-appointments.php?restored=' . ($restored ? '1' : '0'));
    exit;
}

$pendingStmt = $pdo->prepare("
    SELECT p.*
    FROM pending_payments p
    LEFT JOIN service_requests s ON s.razorpay_order_id = p.razorpay_order_id
    WHERE p.category = 'appointment'
      AND s.id IS NULL
    ORDER BY p.created_at DESC
");
$pendingStmt->execute();
$pendingRows = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

$failedStmt = $pdo->prepare("
    SELECT id, tracking_id, customer_name, mobile, email, category_slug, payment_status, booking_status, service_status, form_data, selected_products, created_at, razorpay_order_id
    FROM service_requests
    WHERE category_slug = 'appointment'
      AND LOWER(TRIM(COALESCE(payment_status, ''))) = 'failed'
    ORDER BY created_at DESC, id DESC
");
$failedStmt->execute();
$failedAppointmentRows = $failedStmt->fetchAll(PDO::FETCH_ASSOC);

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
        $sql = "
            SELECT l.razorpay_order_id, l.event_type
            FROM razorpay_webhook_logs l
            INNER JOIN (
                SELECT razorpay_order_id, MAX(id) AS max_id
                FROM razorpay_webhook_logs
                WHERE razorpay_order_id IN ($placeholders)
                GROUP BY razorpay_order_id
            ) x ON x.max_id = l.id
        ";
        $evStmt = $pdo->prepare($sql);
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
    if (empty($formData)) {
        $formData = json_decode($row['appointment_form'] ?? '', true) ?? [];
    }

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
        'source_label' => 'Pending Payment Row',
        'id' => (int)($row['id'] ?? 0),
        'view_id' => null,
        'tracking_id' => (string)($row['payment_id'] ?? ''),
        'customer_name' => (string)($customerDetails['full_name'] ?? ($formData['full_name'] ?? '')),
        'mobile' => (string)($customerDetails['mobile'] ?? ($formData['mobile'] ?? '')),
        'mobile_context' => !empty($customerDetails) ? $customerDetails : $formData,
        'preferred_date' => (string)($customerDetails['preferred_date'] ?? ($formData['preferred_date'] ?? '')),
        'created_at' => (string)($row['created_at'] ?? ''),
        'selected_products' => (string)($row['selected_products'] ?? ''),
        'notes' => (string)($customerDetails['notes'] ?? ($formData['notes'] ?? ($formData['message'] ?? ($formData['remark'] ?? '')))),
        'payment_badge_class' => $paymentBadgeClass,
        'payment_badge_text' => $paymentBadgeText,
        'service_badge_class' => 'status-default',
        'service_badge_text' => 'Unaccepted',
        'action_kind' => 'delete',
    ];
}

foreach ($failedAppointmentRows as $row) {
    $formData = json_decode($row['form_data'] ?? '', true) ?? [];
    $serviceStatusRaw = trim((string)($row['service_status'] ?? ''));
    $serviceBadgeClass = $serviceStatusRaw !== '' ? 'status-' . strtolower(str_replace(' ', '-', $serviceStatusRaw)) : 'status-default';
    $serviceBadgeText = $serviceStatusRaw !== '' ? $serviceStatusRaw : '-';
    $failedCount++;

    $records[] = [
        'source' => 'service_request_failed',
        'source_label' => 'Failed Booking Row',
        'id' => (int)($row['id'] ?? 0),
        'view_id' => (int)($row['id'] ?? 0),
        'tracking_id' => (string)($row['tracking_id'] ?? ''),
        'customer_name' => (string)($row['customer_name'] ?? ''),
        'mobile' => (string)($row['mobile'] ?? ''),
        'mobile_context' => $formData,
        'preferred_date' => (string)($formData['preferred_date'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
        'selected_products' => (string)($row['selected_products'] ?? ''),
        'notes' => (string)($formData['notes'] ?? ($formData['message'] ?? ($formData['remark'] ?? ''))),
        'payment_badge_class' => 'payment-failed',
        'payment_badge_text' => 'Failed',
        'service_badge_class' => $serviceBadgeClass,
        'service_badge_text' => $serviceBadgeText,
        'action_kind' => 'view',
    ];
}

usort($records, static function (array $a, array $b): int {
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
<title>Pending/Failed Appointments</title>
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
    margin-bottom: 10px;
}
.page-subtitle {
    color: #6b4a4a;
    margin: 0 0 18px;
    line-height: 1.5;
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
.view-btn,
.delete-btn,
.restore-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 14px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9em;
    border: none;
    cursor: pointer;
}
.view-btn {
    background: #800000;
    color: #fff;
}
.view-btn:hover {
    background: #a00000;
}
.delete-btn {
    background: #dc3545;
    color: #fff;
}
.delete-btn:hover {
    background: #bb2d3b;
}
.restore-btn {
    background: #fff3cd;
    color: #7a4a00;
    border: 1px solid #efcf86;
}
.restore-btn:hover {
    background: #ffe49a;
}
.source-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 999px;
    background: #f3e8e8;
    color: #7a2d2d;
    font-weight: 600;
    font-size: 0.85em;
}
</style>
</head>

<body>
<div style="max-width:600px;margin:18px auto 0 auto;text-align:center;">
    <input type="text" id="globalSearch" placeholder="Search pending/failed appointments..." style="width:100%;padding:10px 14px;font-size:1.1em;border-radius:8px;border:1px solid #ccc;">
</div>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <h1>Pending/Failed Appointments</h1>
    <p class="page-subtitle">
        This queue shows both unresolved online appointment payments and appointment booking rows already created in <code>service_requests</code> but later marked as failed.
    </p>
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>
        <div style="margin:0 0 16px;padding:12px 14px;border-radius:10px;background:#e8f8ec;border:1px solid #b9e0c1;color:#1f6f2c;font-weight:600;">
            Unresolved appointment payment row deleted.
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['restored']) && $_GET['restored'] === '1'): ?>
        <div style="margin:0 0 16px;padding:12px 14px;border-radius:10px;background:#e8f8ec;border:1px solid #b9e0c1;color:#1f6f2c;font-weight:600;">
            Failed appointment moved back into Pending Appointments.
        </div>
    <?php elseif (isset($_GET['restored']) && $_GET['restored'] === '0'): ?>
        <div style="margin:0 0 16px;padding:12px 14px;border-radius:10px;background:#fff1f1;border:1px solid #e9b7b7;color:#9f2525;font-weight:600;">
            Unable to move that failed appointment back to pending. It may already have been updated.
        </div>
    <?php endif; ?>

    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-count"><?= count($records) ?></div>
            <div class="summary-label">Pending/Failed Appointments</div>
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
            No pending or failed appointments found.
        </div>
    <?php else: ?>
        <table class="service-table">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Mobile</th>
                    <th>Preferred Date</th>
                    <th>Created Date</th>
                    <th>Products</th>
                    <th>ID</th>
                    <th>Tracking ID</th>
                    <th>Payment Status</th>
                    <th>Service Status</th>
                    <th>Source</th>
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

                        $preferredDateRaw = trim((string)($row['preferred_date'] ?? ''));
                        if ($preferredDateRaw !== '') {
                            $preferredDateObj = DateTime::createFromFormat('Y-m-d', $preferredDateRaw);
                            $preferredDisplay = $preferredDateObj ? $preferredDateObj->format('d-M-Y') : $preferredDateRaw;
                        } else {
                            $preferredDisplay = '--';
                        }

                        $createdDisplay = '';
                        if (!empty($row['created_at'])) {
                            $createdAtObj = new DateTime((string)$row['created_at']);
                            $createdDisplay = $createdAtObj->format('d-M-Y h:i A');
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$row['customer_name']) ?></td>
                        <td><?= htmlspecialchars(vs_format_mobile_from_form_data((string)($row['mobile'] ?? ''), $row['mobile_context'] ?? null)) ?></td>
                        <td style="font-weight:600;color:#800000;"><?= htmlspecialchars($preferredDisplay) ?></td>
                        <td><?= htmlspecialchars($createdDisplay) ?></td>
                        <td><?= $products ?></td>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= htmlspecialchars((string)$row['tracking_id']) ?></td>
                        <td><span class="status-badge <?= htmlspecialchars((string)$row['payment_badge_class']) ?>"><?= htmlspecialchars((string)$row['payment_badge_text']) ?></span></td>
                        <td><span class="status-badge <?= htmlspecialchars((string)$row['service_badge_class']) ?>"><?= htmlspecialchars((string)$row['service_badge_text']) ?></span></td>
                        <td><span class="source-badge"><?= htmlspecialchars((string)$row['source_label']) ?></span></td>
                        <td><?= htmlspecialchars((string)($row['notes'] ?? '')) ?></td>
                        <td>
                            <?php if (($row['action_kind'] ?? '') === 'delete'): ?>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this unresolved appointment payment row?');" style="display:inline;">
                                    <input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>">
                                    <button type="submit" class="delete-btn">Delete</button>
                                </form>
                            <?php elseif (!empty($row['view_id'])): ?>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <a href="view.php?id=<?= (int)$row['view_id'] ?>" class="view-btn">View</a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Move this failed appointment back into Pending Appointments? Use this only when payment actually succeeded and the failed state is incorrect.');">
                                        <input type="hidden" name="restore_failed_id" value="<?= (int)$row['view_id'] ?>">
                                        <button type="submit" class="restore-btn">Move To Pending</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span style="color:#888;">-</span>
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
