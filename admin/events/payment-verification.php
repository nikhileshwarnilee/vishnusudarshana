<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');

date_default_timezone_set('Asia/Kolkata');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/event_module.php';

vs_event_ensure_tables($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_action'], $_POST['payment_id'])) {
    $action = trim((string)$_POST['verify_action']);
    $paymentId = (int)$_POST['payment_id'];

    if (!in_array($action, ['approve', 'reject'], true)) {
        $error = 'Invalid action.';
    } elseif ($paymentId <= 0) {
        $error = 'Invalid payment selected.';
    } else {
        $stmt = $pdo->prepare("SELECT
            ep.id,
            ep.amount,
            ep.payment_type,
            ep.amount_paid,
            ep.remaining_amount,
            ep.payment_method,
            ep.upi_id_used,
            ep.upi_qr_used,
            ep.status,
            ep.registration_id,
            r.event_id,
            r.event_date_id,
            r.booking_reference,
            r.package_upi_id_snapshot,
            r.package_upi_qr_snapshot,
            r.qr_code_path,
            r.persons,
            r.name,
            r.phone,
            e.title AS event_title,
            e.event_type,
            COALESCE(d.event_date, e.event_date) AS selected_event_date,
            p.package_name,
            COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS package_price_total
        FROM event_payments ep
        INNER JOIN event_registrations r ON r.id = ep.registration_id
        INNER JOIN events e ON e.id = r.event_id
        LEFT JOIN event_dates d ON d.id = r.event_date_id
        INNER JOIN event_packages p ON p.id = r.package_id
        LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
        WHERE ep.id = ?
        LIMIT 1");
        $stmt->execute([$paymentId]);
        $paymentRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$paymentRow) {
            $error = 'Payment record not found.';
        } else {
            $paymentBookingReference = trim((string)($paymentRow['booking_reference'] ?? ''));
            if ($paymentBookingReference === '') {
                $paymentBookingReference = vs_event_assign_booking_reference($pdo, (int)$paymentRow['registration_id']);
                $paymentRow['booking_reference'] = $paymentBookingReference;
            }

            try {
                $pdo->beginTransaction();

                if ($action === 'approve') {
                    $totalAmount = (float)$paymentRow['package_price_total'] * max((int)$paymentRow['persons'], 1);
                    $alreadyPaid = (float)($paymentRow['amount_paid'] ?? 0);
                    $currentAmount = (float)($paymentRow['amount'] ?? 0);
                    $newPaid = round($alreadyPaid + $currentAmount, 2);
                    if ($newPaid > $totalAmount) {
                        $newPaid = $totalAmount;
                    }
                    $remainingAfter = round(max($totalAmount - $newPaid, 0), 2);
                    $newPaymentStatus = $remainingAfter > 0 ? 'Partial Paid' : 'Paid';
                    $newPaymentType = $remainingAfter > 0 ? (string)($paymentRow['payment_type'] ?? 'advance') : 'full';

                    $pdo->prepare("UPDATE event_payments
                        SET status = 'Approved', payment_type = ?, amount_paid = ?, remaining_amount = ?
                        WHERE id = ?")
                        ->execute([$newPaymentType, $newPaid, $remainingAfter, $paymentId]);
                    $pdo->prepare("UPDATE event_registrations SET payment_status = ?, verification_status = 'Approved' WHERE id = ?")
                        ->execute([$newPaymentStatus, (int)$paymentRow['registration_id']]);

                    if (vs_event_is_whatsapp_enabled($pdo, (int)$paymentRow['event_id'])) {
                        if ($remainingAfter <= 0) {
                            $qrCodePath = vs_event_ensure_registration_qr($pdo, (int)$paymentRow['registration_id']);
                            $eventDateLabel = vs_event_get_registration_date_display($pdo, $paymentRow, (string)($paymentRow['selected_event_date'] ?? ''));
                            vs_event_send_whatsapp_notice('ticket_delivery', (string)$paymentRow['phone'], [
                                'name' => (string)$paymentRow['name'],
                                'event_name' => (string)$paymentRow['event_title'],
                                'package_name' => (string)$paymentRow['package_name'],
                                'event_date' => $eventDateLabel,
                                'amount' => (string)$totalAmount,
                                'booking_reference' => (string)($paymentRow['booking_reference'] ?? ''),
                                'registration_id' => (int)$paymentRow['registration_id'],
                                'event_id' => (int)$paymentRow['event_id'],
                                'qr_code_path' => $qrCodePath,
                            ]);
                        } else {
                            $eventDateLabel = vs_event_get_registration_date_display($pdo, $paymentRow, (string)($paymentRow['selected_event_date'] ?? ''));
                            vs_event_send_whatsapp_notice('payment_approved', (string)$paymentRow['phone'], [
                                'name' => (string)$paymentRow['name'],
                                'event_name' => (string)$paymentRow['event_title'],
                                'package_name' => (string)$paymentRow['package_name'],
                                'event_date' => $eventDateLabel,
                                'amount' => (string)$currentAmount,
                                'booking_reference' => (string)($paymentRow['booking_reference'] ?? ''),
                                'registration_id' => (int)$paymentRow['registration_id'],
                                'event_id' => (int)$paymentRow['event_id'],
                            ]);
                        }
                    }

                    $message = 'Payment approved successfully.';
                } else {
                    $pdo->prepare("UPDATE event_payments SET status = 'Rejected' WHERE id = ?")->execute([$paymentId]);
                    $fallbackStatus = ((float)($paymentRow['amount_paid'] ?? 0) > 0) ? 'Partial Paid' : 'Failed';
                    $pdo->prepare("UPDATE event_registrations SET payment_status = ?, verification_status = 'Rejected' WHERE id = ?")
                        ->execute([$fallbackStatus, (int)$paymentRow['registration_id']]);

                    $message = 'Payment rejected successfully.';
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Unable to update payment verification.';
                error_log('Event payment verification failed: ' . $e->getMessage());
            }
        }
    }
}

$pendingSql = "SELECT
    ep.*,
    r.event_id,
    r.event_date_id,
    r.booking_reference,
    r.package_upi_id_snapshot,
    r.package_upi_qr_snapshot,
    r.name,
    r.phone,
    r.persons,
    e.title AS event_title,
    e.event_type,
    COALESCE(d.event_date, e.event_date) AS selected_event_date,
    p.package_name
FROM event_payments ep
INNER JOIN event_registrations r ON r.id = ep.registration_id
INNER JOIN events e ON e.id = r.event_id
LEFT JOIN event_dates d ON d.id = r.event_date_id
INNER JOIN event_packages p ON p.id = r.package_id
WHERE ep.payment_method IN ('Manual UPI', 'Cash')
  AND ep.status IN ('Pending', 'Pending Verification')
ORDER BY ep.id DESC";
$pendingPayments = $pdo->query($pendingSql)->fetchAll(PDO::FETCH_ASSOC);
foreach ($pendingPayments as &$pendingRow) {
    $ref = trim((string)($pendingRow['booking_reference'] ?? ''));
    if ($ref === '') {
        $ref = vs_event_assign_booking_reference($pdo, (int)$pendingRow['registration_id']);
        $pendingRow['booking_reference'] = $ref;
    }
    $pendingRow['event_date_display'] = vs_event_get_registration_date_display(
        $pdo,
        $pendingRow,
        (string)($pendingRow['selected_event_date'] ?? '')
    );
}
unset($pendingRow);

$recentSql = "SELECT
    ep.id,
    ep.amount,
    ep.payment_type,
    ep.amount_paid,
    ep.remaining_amount,
    ep.payment_method,
    ep.upi_id_used,
    ep.upi_qr_used,
    ep.transaction_id,
    ep.screenshot,
    ep.remarks,
    ep.status,
    ep.updated_at,
    r.event_id,
    r.event_date_id,
    r.booking_reference,
    r.package_upi_id_snapshot,
    r.package_upi_qr_snapshot,
    ep.registration_id,
    r.name,
    e.title AS event_title,
    e.event_type,
    COALESCE(d.event_date, e.event_date) AS selected_event_date,
    p.package_name
FROM event_payments ep
INNER JOIN event_registrations r ON r.id = ep.registration_id
INNER JOIN events e ON e.id = r.event_id
LEFT JOIN event_dates d ON d.id = r.event_date_id
INNER JOIN event_packages p ON p.id = r.package_id
WHERE ep.payment_method IN ('Manual UPI', 'Cash')
  AND ep.status IN ('Approved', 'Rejected')
ORDER BY ep.updated_at DESC
LIMIT 25";
$recentPayments = $pdo->query($recentSql)->fetchAll(PDO::FETCH_ASSOC);
foreach ($recentPayments as &$recentRow) {
    $ref = trim((string)($recentRow['booking_reference'] ?? ''));
    if ($ref === '') {
        $ref = vs_event_assign_booking_reference($pdo, (int)$recentRow['registration_id']);
        $recentRow['booking_reference'] = $ref;
    }
    $recentRow['event_date_display'] = vs_event_get_registration_date_display(
        $pdo,
        $recentRow,
        (string)($recentRow['selected_event_date'] ?? '')
    );
}
unset($recentRow);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f7f7fa; margin:0; }
        .admin-container { max-width:1400px; margin:0 auto; padding:24px 12px; }
        h1 { color:#800000; margin-bottom:14px; }
        .notice { margin:10px 0; padding:10px 12px; border-radius:8px; font-weight:600; }
        .notice.ok { background:#e7f7ed; color:#1a8917; }
        .notice.err { background:#ffeaea; color:#b00020; }
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 12px #e0bebe22; padding:14px; margin-bottom:16px; }
        .list-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px #e0bebe22; table-layout:auto; font-size:0.86em; }
        .list-table th, .list-table td { padding:8px 6px; border-bottom:1px solid #f3caca; text-align:left; vertical-align:top; }
        .list-table th { background:#f9eaea; color:#800000; font-size:0.9em; }
        .screenshot { width:70px; height:70px; object-fit:cover; border-radius:8px; border:1px solid #eee; }
        .btn { border:none; border-radius:6px; padding:6px 10px; font-weight:700; cursor:pointer; font-size:0.82em; }
        .approve { background:#1a8917; color:#fff; }
        .reject { background:#dc3545; color:#fff; }
        .tag { display:inline-block; border-radius:12px; padding:3px 9px; font-size:0.8em; font-weight:700; }
        .tag-approved { background:#e5ffe5; color:#1a8917; }
        .tag-rejected { background:#ffeaea; color:#b00020; }
        .tag-pending { background:#fff4db; color:#b36b00; }
        .small { color:#666; font-size:0.84em; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <h1>Payment Verification (UPI / Cash)</h1>

    <?php if ($message !== ''): ?><div class="notice ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card">
        <h3 style="margin-top:0; color:#800000;">Pending Verification</h3>
        <table class="list-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Event</th>
                    <th>Package</th>
                    <th>Registrant</th>
                    <th>Booking Ref</th>
                    <th>Type</th>
                    <th>Method</th>
                    <th>UPI Account</th>
                    <th>Amount</th>
                    <th>Paid So Far</th>
                    <th>Remaining</th>
                    <th>Transaction ID</th>
                    <th>Remark</th>
                    <th>Screenshot</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($pendingPayments)): ?>
                <tr><td colspan="16" style="text-align:center; padding:20px; color:#666;">No pending manual payments.</td></tr>
            <?php else: ?>
                <?php foreach ($pendingPayments as $row): ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['event_title']); ?></strong><br>
                            <span class="small"><?php echo htmlspecialchars((string)($row['event_date_display'] ?? $row['selected_event_date'])); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($row['package_name']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['name']); ?></strong><br>
                            <span class="small"><?php echo htmlspecialchars(vs_format_mobile_for_display((string)$row['phone'])); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars((string)($row['booking_reference'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst((string)($row['payment_type'] ?? 'full'))); ?></td>
                        <td><?php echo htmlspecialchars((string)$row['payment_method']); ?></td>
                        <?php
                        $pendingUpi = trim((string)($row['upi_id_used'] ?? ''));
                        if ($pendingUpi === '') {
                            $pendingUpi = trim((string)($row['package_upi_id_snapshot'] ?? ''));
                        }
                        ?>
                        <td><?php echo $pendingUpi !== '' ? htmlspecialchars($pendingUpi) : '-'; ?></td>
                        <td>Rs <?php echo number_format((float)$row['amount'], 2); ?></td>
                        <td>Rs <?php echo number_format((float)($row['amount_paid'] ?? 0), 2); ?></td>
                        <td>Rs <?php echo number_format((float)($row['remaining_amount'] ?? 0), 2); ?></td>
                        <td><?php echo htmlspecialchars((string)$row['transaction_id']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars((string)($row['remarks'] ?? ''))); ?></td>
                        <td>
                            <?php if (!empty($row['screenshot'])): ?>
                                <a href="../../<?php echo htmlspecialchars(ltrim((string)$row['screenshot'], '/')); ?>" target="_blank">
                                    <img class="screenshot" src="../../<?php echo htmlspecialchars(ltrim((string)$row['screenshot'], '/')); ?>" alt="UPI Screenshot">
                                </a>
                            <?php else: ?>
                                <span class="small">Not uploaded</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="tag tag-pending"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="payment_id" value="<?php echo (int)$row['id']; ?>">
                                <button type="submit" name="verify_action" value="approve" class="btn approve" onclick="return confirm('Approve this payment?');">Approve</button>
                                <button type="submit" name="verify_action" value="reject" class="btn reject" onclick="return confirm('Reject this payment?');">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top:0; color:#800000;">Recent Decisions</h3>
        <table class="list-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Event</th>
                    <th>Package</th>
                    <th>Registrant</th>
                    <th>Booking Ref</th>
                    <th>Type</th>
                    <th>Method</th>
                    <th>UPI Account</th>
                    <th>Amount</th>
                    <th>Paid Total</th>
                    <th>Remaining</th>
                    <th>Transaction ID</th>
                    <th>Remark</th>
                    <th>Screenshot</th>
                    <th>Status</th>
                    <th>Updated At</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($recentPayments)): ?>
                <tr><td colspan="16" style="text-align:center; padding:18px; color:#666;">No approved/rejected manual payments yet.</td></tr>
            <?php else: ?>
                <?php foreach ($recentPayments as $row): ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['event_title']); ?><br><span class="small"><?php echo htmlspecialchars((string)($row['event_date_display'] ?? $row['selected_event_date'])); ?></span></td>
                        <td><?php echo htmlspecialchars($row['package_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars((string)($row['booking_reference'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst((string)($row['payment_type'] ?? 'full'))); ?></td>
                        <td><?php echo htmlspecialchars((string)$row['payment_method']); ?></td>
                        <?php
                        $recentUpi = trim((string)($row['upi_id_used'] ?? ''));
                        if ($recentUpi === '') {
                            $recentUpi = trim((string)($row['package_upi_id_snapshot'] ?? ''));
                        }
                        ?>
                        <td><?php echo $recentUpi !== '' ? htmlspecialchars($recentUpi) : '-'; ?></td>
                        <td>Rs <?php echo number_format((float)$row['amount'], 2); ?></td>
                        <td>Rs <?php echo number_format((float)($row['amount_paid'] ?? 0), 2); ?></td>
                        <td>Rs <?php echo number_format((float)($row['remaining_amount'] ?? 0), 2); ?></td>
                        <td><?php echo htmlspecialchars((string)$row['transaction_id']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars((string)($row['remarks'] ?? ''))); ?></td>
                        <td>
                            <?php if (!empty($row['screenshot'])): ?>
                                <a href="../../<?php echo htmlspecialchars(ltrim((string)$row['screenshot'], '/')); ?>" target="_blank">
                                    <img class="screenshot" src="../../<?php echo htmlspecialchars(ltrim((string)$row['screenshot'], '/')); ?>" alt="Payment Proof">
                                </a>
                            <?php else: ?>
                                <span class="small">Not uploaded</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="tag <?php echo ((string)$row['status'] === 'Approved') ? 'tag-approved' : 'tag-rejected'; ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars((string)$row['updated_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="../includes/responsive-tables.js"></script>
</body>
</html>
