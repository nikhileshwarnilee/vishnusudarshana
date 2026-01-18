<?php
/**
 * admin/services/completed-appointments.php
 *
 * Completed Appointments: Read-only listing
 * Data source: service_requests table
 */

require_once __DIR__ . '/../../config/db.php';

// --- SUMMARY STATS ---
// Completed Today
$stmt = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE category_slug = 'appointment' AND payment_status = 'Paid' AND service_status = 'Completed' AND DATE(updated_at) = CURDATE()");
$stmt->execute();
$completedToday = (int)$stmt->fetchColumn();
// Total Completed
$stmt = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE category_slug = 'appointment' AND payment_status = 'Paid' AND service_status = 'Completed'");
$stmt->execute();
$totalCompleted = (int)$stmt->fetchColumn();

// --- FETCH ALL COMPLETED APPOINTMENTS ---
$appointments = [];
$sql = "
    SELECT id, tracking_id, customer_name, mobile, email, payment_status, service_status, form_data, created_at
    FROM service_requests
    WHERE category_slug = 'appointment' AND payment_status = 'Paid' AND service_status = 'Completed'
    ORDER BY created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Completed Appointments</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
.admin-container { max-width: 1100px; margin: 0 auto; padding: 24px 12px; }
h1 { color: #800000; margin-bottom: 18px; }
.summary-cards { display: flex; gap: 18px; margin-bottom: 24px; flex-wrap: wrap; }
.summary-card { flex: 1 1 180px; background: #fffbe7; border-radius: 14px; padding: 16px; text-align: center; box-shadow: 0 2px 8px #e0bebe22; }
.summary-count { font-size: 2.2em; font-weight: 700; color: #800000; }
.summary-label { font-size: 1em; color: #444; }
.filter-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 18px; }
#dateSelect { padding: 8px 12px; border-radius: 6px; border: 1px solid #ddd; min-width: 260px; }
.service-table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 12px #e0bebe22; border-radius: 12px; overflow: hidden; }
.service-table th, .service-table td { padding: 12px 10px; border-bottom: 1px solid #f3caca; text-align: left; }
.service-table th { background: #f9eaea; color: #800000; }
.no-data { text-align: center; color: #777; padding: 24px; }
.status-badge { padding: 4px 12px; border-radius: 8px; font-weight: 600; font-size: 0.9em; display: inline-block; min-width: 80px; text-align: center; }
.status-completed { background: #e5ffe5; color: #1a8917; }
.payment-paid { background: #e5ffe5; color: #1a8917; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Completed Appointments</h1>
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-count"><?= $completedToday ?></div>
            <div class="summary-label">Completed Today</div>
        </div>
        <div class="summary-card">
            <div class="summary-count"><?= $totalCompleted ?></div>
            <div class="summary-label">Total Completed</div>
        </div>
    </div>
    <?php if (empty($appointments)): ?>
        <div class="no-data" style="font-size:1.1em;color:#800000;font-weight:600;">No completed appointments found.</div>
    <?php else: ?>
        <table class="service-table">
            <thead>
                <tr>
                    <th>Tracking ID</th>
                    <th>Customer Name</th>
                    <th>Mobile</th>
                    <th>Email</th>
                    <th>Preferred Date</th>
                    <th>Scheduled Time</th>
                    <th>Payment Status</th>
                    <th>Service Status</th>
                    <th>Created Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($appointments as $a):
                $formData = json_decode($a['form_data'], true) ?? [];
                $preferredDate = $formData['preferred_date'] ?? '';
                $preferredDisplay = $preferredDate ? (DateTime::createFromFormat('Y-m-d', $preferredDate)?->format('d-M-Y') ?: $preferredDate) : '—';
                $fromTime = $formData['assigned_from_time'] ?? ($formData['time_from'] ?? '');
                $toTime = $formData['assigned_to_time'] ?? ($formData['time_to'] ?? '');
                $createdDisplay = '';
                if (!empty($a['created_at'])) {
                    $co = new DateTime($a['created_at']);
                    $createdDisplay = $co->format('d-M-Y');
                }
            ?>
                <tr>
                    <td><?= htmlspecialchars($a['tracking_id']) ?></td>
                    <td><?= htmlspecialchars($a['customer_name']) ?></td>
                    <td><?= htmlspecialchars($a['mobile']) ?></td>
                    <td><?= htmlspecialchars($a['email']) ?></td>
                    <td style="font-weight:600;color:#800000;">
                        <?= htmlspecialchars($preferredDisplay) ?>
                    </td>
                    <td style="font-weight:600; color:#0056b3;">
                        <?php
                        if ($fromTime && $toTime) {
                            $fromFmt = date('h:i A', strtotime($fromTime));
                            $toFmt = date('h:i A', strtotime($toTime));
                            echo htmlspecialchars($fromFmt . ' – ' . $toFmt);
                        } else {
                            echo 'Time not set';
                        }
                        ?>
                    </td>
                    <td><span class="status-badge payment-paid">Paid</span></td>
                    <td><span class="status-badge status-completed">Completed</span></td>
                    <td><?= htmlspecialchars($createdDisplay) ?></td>
                    <td>
                        <a href="view.php?id=<?= (int)$a['id'] ?>" class="view-btn" style="padding:6px 14px;background:#007bff;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
