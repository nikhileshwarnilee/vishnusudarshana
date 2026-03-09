<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/event_module.php';
if (is_file(__DIR__ . '/config/whatsapp_config.php')) {
    require_once __DIR__ . '/config/whatsapp_config.php';
}
if (is_file(__DIR__ . '/config/admin_config.php')) {
    require_once __DIR__ . '/config/admin_config.php';
}

$registrationId = isset($_GET['registration_id']) ? (int)$_GET['registration_id'] : (int)($_POST['registration_id'] ?? 0);
if ($registrationId <= 0) {
    header('Location: events.php');
    exit;
}
$autoPrint = isset($_GET['auto_print']) && (string)$_GET['auto_print'] === '1';

vs_event_ensure_tables($pdo);
$cancelError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
    $cancelReason = trim((string)($_POST['cancel_reason'] ?? ''));
    if ($cancelReason === '') {
        $cancelReason = 'Cancellation requested by customer';
    }
    $cancelPersons = (int)($_POST['cancel_persons'] ?? 0);
    try {
        $cancelResult = vs_event_submit_cancellation_request($pdo, $registrationId, $cancelPersons, $cancelReason, 'online');
        $statusToken = ((string)($cancelResult['request_type'] ?? 'full') === 'partial') ? 'partial-cancel-requested' : 'cancel-requested';
        header('Location: event-booking-confirmation.php?registration_id=' . $registrationId . '&status=' . urlencode($statusToken));
        exit;
    } catch (Throwable $e) {
        $cancelError = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to submit cancellation request.';
    }
}

$stmt = $pdo->prepare("SELECT
    r.*,
    r.booking_reference,
    e.title AS event_title,
    e.slug AS event_slug,
    COALESCE(d.event_date, e.event_date) AS selected_event_date,
    e.location,
    p.package_name,
    p.cancellation_allowed,
    p.refund_allowed,
    COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS package_price_total,
    ep.payment_method,
    ep.transaction_id,
    ep.screenshot,
    ep.remarks,
    ep.status AS payment_record_status,
    ep.amount AS payment_amount,
    ep.amount_paid,
    ep.remaining_amount,
    ep.payment_type
FROM event_registrations r
INNER JOIN events e ON e.id = r.event_id
LEFT JOIN event_dates d ON d.id = r.event_date_id
INNER JOIN event_packages p ON p.id = r.package_id
LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
LEFT JOIN event_payments ep ON ep.registration_id = r.id
WHERE r.id = ?
LIMIT 1");
$stmt->execute([$registrationId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    header('Location: events.php');
    exit;
}

$bookingReference = trim((string)($row['booking_reference'] ?? ''));
if ($bookingReference === '') {
    $bookingReference = vs_event_assign_booking_reference($pdo, $registrationId);
    if ($bookingReference !== '') {
        $row['booking_reference'] = $bookingReference;
    }
}

$cancelInfoStmt = $pdo->prepare("SELECT * FROM event_cancellations WHERE registration_id = ? ORDER BY id DESC LIMIT 1");
$cancelInfoStmt->execute([$registrationId]);
$cancelInfo = $cancelInfoStmt->fetch(PDO::FETCH_ASSOC);
$cancelRequestInfoStmt = $pdo->prepare("SELECT * FROM event_cancellation_requests WHERE registration_id = ? ORDER BY id DESC LIMIT 1");
$cancelRequestInfoStmt->execute([$registrationId]);
$cancelRequestInfo = $cancelRequestInfoStmt->fetch(PDO::FETCH_ASSOC);
$registrationDataStmt = $pdo->prepare("SELECT field_name, value FROM event_registration_data WHERE registration_id = ? ORDER BY id ASC");
$registrationDataStmt->execute([$registrationId]);
$registrationDataRows = $registrationDataStmt->fetchAll(PDO::FETCH_ASSOC);
$registrationDataRows = array_values(array_filter($registrationDataRows, static function (array $item): bool {
    $name = (string)($item['field_name'] ?? '');
    return strpos($name, '__event_reminder_') !== 0;
}));
$displayEventDate = vs_event_get_registration_date_display($pdo, $row, (string)($row['selected_event_date'] ?? ''));

$totalAmount = (float)$row['package_price_total'] * max((int)$row['persons'], 1);
$amountPaid = (float)($row['amount_paid'] ?? 0);
if (strtolower((string)$row['payment_status']) === 'paid' && $amountPaid <= 0) {
    $amountPaid = (float)($row['payment_amount'] ?? $totalAmount);
}
$remainingAmount = (float)($row['remaining_amount'] ?? 0);
if ($remainingAmount <= 0 && strtolower((string)$row['payment_status']) !== 'cancelled') {
    $remainingAmount = round(max($totalAmount - $amountPaid, 0), 2);
}

$paymentStatus = strtolower(trim((string)($row['payment_status'] ?? '')));
$verificationStatus = strtolower(trim((string)($row['verification_status'] ?? '')));
$paymentMethod = strtolower(trim((string)($row['payment_method'] ?? '')));
$paymentRecordStatus = strtolower(trim((string)($row['payment_record_status'] ?? '')));

$isCancelled = ($paymentStatus === 'cancelled');
$isPaid = ($paymentStatus === 'paid');
$isPartialPaid = ($paymentStatus === 'partial paid');
$isManualOrCash = in_array($paymentMethod, ['manual upi', 'cash'], true);
$isPendingManualApproval = $isManualOrCash && (
    in_array($paymentStatus, ['pending', 'pending verification'], true) ||
    in_array($verificationStatus, ['pending', 'pending verification'], true) ||
    in_array($paymentRecordStatus, ['pending', 'pending verification'], true)
);
$isRejectedByAdmin = in_array($verificationStatus, ['rejected'], true) || in_array($paymentRecordStatus, ['rejected'], true);

$showMakePaymentButton = (!$isPaid && !$isCancelled && !$isPartialPaid);
if ($isPendingManualApproval && !$isRejectedByAdmin) {
    $showMakePaymentButton = false;
}
if ($isManualOrCash && $isRejectedByAdmin && !$isPaid && !$isCancelled && !$isPartialPaid) {
    $showMakePaymentButton = true;
}
$showRemainingPaymentButton = ($isPartialPaid && $remainingAmount > 0 && !$isCancelled);

$businessWhatsappRaw = defined('WHATSAPP_BUSINESS_PHONE') ? trim((string)WHATSAPP_BUSINESS_PHONE) : '';
$adminWhatsappRaw = defined('ADMIN_WHATSAPP') ? trim((string)ADMIN_WHATSAPP) : '';
$contactNumberRaw = $businessWhatsappRaw !== '' ? $businessWhatsappRaw : $adminWhatsappRaw;
$contactNumberRaw = preg_replace('/[^0-9]/', '', (string)$contactNumberRaw);
if (strlen($contactNumberRaw) === 10) {
    $contactNumberRaw = '91' . $contactNumberRaw;
}
$contactNumberDisplay = $contactNumberRaw !== '' ? ('+' . $contactNumberRaw) : 'Not Available';
$logoPath = 'assets/images/logo/logomain.png';
$printGeneratedAt = date('d M Y, h:i A');

$pageTitle = 'Booking Confirmation';
require_once 'header.php';

$status = strtolower((string)($_GET['status'] ?? ''));
if ($status === '') {
    $status = strtolower((string)$row['payment_status']);
}

$messageTitle = 'Registration Received';
$messageBody = 'Your registration has been received.';
$statusClass = 'pending';
$qrTicketUrl = '';
$cancelRequestStatus = strtolower(trim((string)($cancelRequestInfo['request_status'] ?? '')));
$hasPendingCancelRequest = (!$isCancelled && $cancelRequestStatus === 'pending');
if ($hasPendingCancelRequest) {
    $showMakePaymentButton = false;
    $showRemainingPaymentButton = false;
}

if (in_array($status, ['paid', 'success', 'successful'], true) || strtolower((string)$row['payment_status']) === 'paid') {
    $messageTitle = 'Payment Successful';
    $messageBody = 'Your event booking is confirmed. Please keep this booking reference for entry and support.';
    $statusClass = 'success';
    $qrPath = vs_event_ensure_registration_qr($pdo, $registrationId);
    if ($qrPath !== '') {
        $row['qr_code_path'] = $qrPath;
    }
    $qrTicketUrl = 'event-qr-ticket.php?registration_id=' . (int)$registrationId . '&ref=' . urlencode((string)$row['booking_reference']);
} elseif (in_array($status, ['partial', 'partial paid'], true) || strtolower((string)$row['payment_status']) === 'partial paid') {
    $messageTitle = 'Advance Payment Successful';
    $messageBody = 'Advance payment was received. Please complete the remaining amount before event date.';
    $statusClass = 'pending';
} elseif (
    !$isCancelled && (
    in_array($status, ['cancel-requested', 'partial-cancel-requested'], true) ||
    $hasPendingCancelRequest
    )
) {
    $messageTitle = 'Cancellation Request Pending Approval';
    $messageBody = 'Your cancellation request is submitted. Admin approval is pending.';
    $statusClass = 'pending';
} elseif (in_array($status, ['partial-cancelled'], true)) {
    $messageTitle = 'Booking Updated';
    $messageBody = 'Booking was partially cancelled successfully.';
    $statusClass = 'pending';
} elseif (in_array($status, ['cancelled'], true) || strtolower((string)$row['payment_status']) === 'cancelled') {
    $messageTitle = 'Booking Cancelled';
    if (strtolower((string)($cancelInfo['refund_status'] ?? '')) === 'processed') {
        $messageBody = 'Cancellation approved and refund marked as processed.';
    } elseif (strtolower((string)($cancelInfo['refund_status'] ?? '')) === 'pending') {
        $messageBody = 'Cancellation approved. Refund is pending processing.';
    } else {
        $messageBody = 'Cancellation approved.';
    }
    $statusClass = 'success';
} elseif (in_array($status, ['pending', 'pending verification'], true) || strtolower((string)$row['payment_status']) === 'pending verification') {
    $messageTitle = 'Payment Pending Verification';
    $messageBody = 'Your manual payment details were submitted. Admin will verify and confirm shortly.';
    $statusClass = 'pending';
} elseif (in_array($status, ['failed', 'rejected'], true) || strtolower((string)$row['payment_status']) === 'failed') {
    $messageTitle = 'Payment Failed';
    $messageBody = 'Your payment is not confirmed. You can retry payment from the event payment page.';
    $statusClass = 'failed';
}

$isCancellationAllowed = ((int)($row['cancellation_allowed'] ?? 1) === 1);
$canCancel = (
    $isCancellationAllowed &&
    strtolower((string)$row['payment_status']) !== 'cancelled' &&
    (int)($row['checkin_status'] ?? 0) !== 1 &&
    !$hasPendingCancelRequest
);
?>
<main class="event-confirm-main" style="background-color:var(--cream-bg);">
    <section class="event-confirm-wrap">
        <div class="card status-<?php echo $statusClass; ?>">
            <h1><?php echo htmlspecialchars($messageTitle); ?></h1>
            <p><?php echo htmlspecialchars($messageBody); ?></p>
            <?php if (!empty($cancelError)): ?><p style="margin-top:8px;color:#b00020;font-weight:700;"><?php echo htmlspecialchars((string)$cancelError); ?></p><?php endif; ?>
        </div>

        <div class="card">
            <h2>Booking Details</h2>
            <div class="details-grid">
                <div><strong>Registration ID:</strong> <?php echo (int)$row['id']; ?></div>
                <div><strong>Booking Reference:</strong> <?php echo htmlspecialchars((string)($row['booking_reference'] ?? '')); ?></div>
                <div><strong>Name:</strong> <?php echo htmlspecialchars((string)$row['name']); ?></div>
                <div><strong>Phone:</strong> <?php echo htmlspecialchars((string)$row['phone']); ?></div>
                <div><strong>Persons:</strong> <?php echo (int)$row['persons']; ?></div>
                <div><strong>Event:</strong> <?php echo htmlspecialchars((string)$row['event_title']); ?></div>
                <div><strong>Package:</strong> <?php echo htmlspecialchars((string)$row['package_name']); ?></div>
                <div><strong>Event Date:</strong> <?php echo htmlspecialchars($displayEventDate); ?></div>
                <div><strong>Location:</strong> <?php echo htmlspecialchars((string)$row['location']); ?></div>
                <div><strong>Payment Status:</strong> <?php echo htmlspecialchars((string)$row['payment_status']); ?></div>
                <div><strong>Verification:</strong> <?php echo htmlspecialchars((string)$row['verification_status']); ?></div>
                <div><strong>Payment Method:</strong> <?php echo htmlspecialchars((string)$row['payment_method']); ?></div>
                <div><strong>Transaction ID:</strong> <?php echo htmlspecialchars((string)$row['transaction_id']); ?></div>
                <div><strong>Total Amount:</strong> Rs. <?php echo number_format($totalAmount, 0, '.', ''); ?></div>
                <div><strong>Paid Amount:</strong> Rs. <?php echo number_format($amountPaid, 0, '.', ''); ?></div>
                <div><strong>Remaining Amount:</strong> Rs. <?php echo number_format($remainingAmount, 0, '.', ''); ?></div>
                <div><strong>Booked At:</strong> <?php echo htmlspecialchars((string)$row['created_at']); ?></div>
                <?php if ($qrTicketUrl !== ''): ?>
                    <div style="grid-column:1/-1;">
                        <strong>Entry QR Ticket:</strong><br>
                        <a href="<?php echo htmlspecialchars($qrTicketUrl); ?>" target="_blank">
                            <img src="<?php echo htmlspecialchars($qrTicketUrl); ?>" alt="Entry QR Ticket" style="width:180px;height:180px;border:1px solid #ecd3d3;border-radius:10px;margin-top:6px;object-fit:contain;background:#fff;">
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($cancelInfo): ?>
                <div style="margin-top:12px;padding:10px;border:1px solid #f1d6d6;border-radius:8px;background:#fffaf8;">
                    <strong>Cancellation Record</strong><br>
                    Type: <?php echo htmlspecialchars((string)($cancelInfo['cancellation_type'] ?? 'full')); ?><br>
                    Cancelled Persons: <?php echo (int)($cancelInfo['cancelled_persons'] ?? 0); ?><br>
                    Reason: <?php echo nl2br(htmlspecialchars((string)$cancelInfo['cancel_reason'])); ?><br>
                    Refund Amount: Rs. <?php echo number_format((float)$cancelInfo['refund_amount'], 0, '.', ''); ?><br>
                    Refund Status: <?php echo htmlspecialchars((string)$cancelInfo['refund_status']); ?>
                </div>
            <?php endif; ?>

            <?php if ($cancelRequestInfo && strtolower((string)($cancelRequestInfo['request_status'] ?? '')) !== 'approved'): ?>
                <div style="margin-top:12px;padding:10px;border:1px solid #f1d6d6;border-radius:8px;background:#fffaf8;">
                    <strong>Cancellation Request</strong><br>
                    Type: <?php echo htmlspecialchars((string)($cancelRequestInfo['request_type'] ?? 'full')); ?><br>
                    Requested Persons: <?php echo (int)($cancelRequestInfo['requested_persons'] ?? 0); ?><br>
                    Reason: <?php echo nl2br(htmlspecialchars((string)($cancelRequestInfo['cancel_reason'] ?? '-'))); ?><br>
                    Status: <?php echo htmlspecialchars((string)($cancelRequestInfo['request_status'] ?? 'pending')); ?><br>
                    Requested At: <?php echo htmlspecialchars((string)($cancelRequestInfo['requested_at'] ?? '-')); ?><br>
                    <?php if (!empty($cancelRequestInfo['decided_at'])): ?>Decided At: <?php echo htmlspecialchars((string)$cancelRequestInfo['decided_at']); ?><br><?php endif; ?>
                    <?php if (!empty($cancelRequestInfo['decision_note'])): ?>Decision Note: <?php echo nl2br(htmlspecialchars((string)$cancelRequestInfo['decision_note'])); ?><?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="btn-row">
                <button type="button" class="btn-main btn-print" onclick="printBookingSheet();">Print Booking</button>
                <?php if ($showMakePaymentButton): ?>
                    <a class="btn-main" href="event-payment.php?registration_id=<?php echo (int)$row['id']; ?>">Make Payment</a>
                <?php endif; ?>
                <?php if ($showRemainingPaymentButton): ?>
                    <a class="btn-main btn-alt" href="event-remaining-payment.php?booking_reference=<?php echo urlencode((string)$row['booking_reference']); ?>&phone=<?php echo urlencode((string)$row['phone']); ?>">Make Remaining Payment</a>
                <?php endif; ?>
                <?php if ($canCancel): ?>
                    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <input type="hidden" name="registration_id" value="<?php echo (int)$registrationId; ?>">
                        <input type="hidden" name="action" value="cancel_booking">
                        <input type="hidden" name="cancel_persons" value="<?php echo (int)$row['persons']; ?>">
                        <input type="text" name="cancel_reason" placeholder="Cancellation reason" style="min-width:220px;">
                        <button type="submit" class="btn-main" style="background:#dc3545;" onclick="return confirm('Submit cancellation request for this booking?');">Request Cancellation</button>
                    </form>
                <?php elseif ($hasPendingCancelRequest): ?>
                    <span style="font-size:13px;color:#8a6000;font-weight:700;">Cancellation request is pending admin approval.</span>
                <?php elseif (!$isCancellationAllowed): ?>
                    <span style="font-size:13px;color:#b00020;font-weight:700;">Cancellation is not allowed for this package.</span>
                <?php endif; ?>
                <a class="btn-main btn-alt" href="event-detail.php?slug=<?php echo urlencode((string)$row['event_slug']); ?>">Back To Event</a>
                <a class="btn-main btn-alt" href="events.php">All Events</a>
            </div>
        </div>
    </section>
</main>
<section class="booking-print-sheet" id="bookingPrintSheet">
    <div class="print-shell">
        <div class="print-header">
            <div class="print-brand">
                <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Vishnusudarshana Logo">
            </div>
            <div class="print-top-right">
                <div class="print-head-info">
                    <div>Event Booking Confirmation</div>
                    <div>Booking Ref: <strong><?php echo htmlspecialchars((string)($row['booking_reference'] ?? '')); ?></strong></div>
                </div>
                <div class="print-contact">
                    <div><strong>Contact:</strong> <?php echo htmlspecialchars($contactNumberDisplay); ?></div>
                    <div><strong>Printed:</strong> <?php echo htmlspecialchars($printGeneratedAt); ?></div>
                </div>
            </div>
        </div>

        <div class="print-status-row status-<?php echo $statusClass; ?>">
            <strong><?php echo htmlspecialchars($messageTitle); ?></strong>
            <span><?php echo htmlspecialchars($messageBody); ?></span>
        </div>

        <?php if ($qrTicketUrl !== ''): ?>
            <div class="print-section print-qr-section">
                <h3>Entry QR Ticket</h3>
                <div class="print-qr-wrap">
                    <img class="print-qr-page" src="<?php echo htmlspecialchars($qrTicketUrl); ?>" alt="Entry QR Ticket">
                </div>
            </div>
        <?php endif; ?>

        <div class="print-section">
            <h3>Booking, Event & Payment Summary</h3>
            <table class="print-table">
                <tr><th>Registration ID</th><td><?php echo (int)$row['id']; ?></td><th>Name</th><td><?php echo htmlspecialchars((string)$row['name']); ?></td></tr>
                <tr><th>Phone</th><td><?php echo htmlspecialchars((string)$row['phone']); ?></td><th>Persons / Qty</th><td><?php echo (int)$row['persons']; ?></td></tr>
                <tr><th>Event</th><td><?php echo htmlspecialchars((string)$row['event_title']); ?></td><th>Package</th><td><?php echo htmlspecialchars((string)$row['package_name']); ?></td></tr>
                <tr><th>Event Date</th><td><?php echo htmlspecialchars((string)$displayEventDate); ?></td><th>Location</th><td><?php echo htmlspecialchars((string)$row['location']); ?></td></tr>
                <tr><th>Payment Status</th><td><?php echo htmlspecialchars((string)$row['payment_status']); ?></td><th>Verification</th><td><?php echo htmlspecialchars((string)$row['verification_status']); ?></td></tr>
                <tr><th>Payment Method</th><td><?php echo htmlspecialchars((string)$row['payment_method']); ?></td><th>Payment Type</th><td><?php echo htmlspecialchars((string)($row['payment_type'] ?? '')); ?></td></tr>
                <tr><th>Transaction ID</th><td><?php echo htmlspecialchars((string)$row['transaction_id']); ?></td><th>Payment Record</th><td><?php echo htmlspecialchars((string)($row['payment_record_status'] ?? '')); ?></td></tr>
                <tr><th>Total Amount</th><td>Rs. <?php echo number_format($totalAmount, 0, '.', ''); ?></td><th>Paid Amount</th><td>Rs. <?php echo number_format($amountPaid, 0, '.', ''); ?></td></tr>
                <tr><th>Remaining Amount</th><td>Rs. <?php echo number_format($remainingAmount, 0, '.', ''); ?></td><th>Booked At</th><td><?php echo htmlspecialchars((string)$row['created_at']); ?></td></tr>
                <tr><th>Payment Remark</th><td colspan="3"><?php echo nl2br(htmlspecialchars((string)($row['remarks'] ?? ''))); ?></td></tr>
            </table>
        </div>

        <?php if (!empty($registrationDataRows)): ?>
            <div class="print-section">
                <h3>Registration Form Data</h3>
                <table class="print-table">
                    <?php foreach ($registrationDataRows as $dataRow): ?>
                        <tr>
                            <th><?php echo htmlspecialchars((string)$dataRow['field_name']); ?></th>
                            <td colspan="3">
                                <?php if (is_string($dataRow['value']) && preg_match('/^uploads\//', (string)$dataRow['value'])): ?>
                                    <a href="<?php echo htmlspecialchars(ltrim((string)$dataRow['value'], '/')); ?>" target="_blank">View Uploaded File</a>
                                <?php else: ?>
                                    <?php echo nl2br(htmlspecialchars((string)$dataRow['value'])); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($cancelInfo): ?>
            <div class="print-section">
                <h3>Cancellation / Refund</h3>
                <table class="print-table">
                    <tr><th>Type</th><td><?php echo htmlspecialchars((string)($cancelInfo['cancellation_type'] ?? 'full')); ?></td><th>Cancelled Persons</th><td><?php echo (int)($cancelInfo['cancelled_persons'] ?? 0); ?></td></tr>
                    <tr><th>Refund Amount</th><td>Rs. <?php echo number_format((float)$cancelInfo['refund_amount'], 0, '.', ''); ?></td><th>Refund Status</th><td><?php echo htmlspecialchars((string)$cancelInfo['refund_status']); ?></td></tr>
                    <tr><th>Reason</th><td colspan="3"><?php echo nl2br(htmlspecialchars((string)$cancelInfo['cancel_reason'])); ?></td></tr>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($cancelRequestInfo && strtolower((string)($cancelRequestInfo['request_status'] ?? '')) !== 'approved'): ?>
            <div class="print-section">
                <h3>Cancellation Request</h3>
                <table class="print-table">
                    <tr><th>Type</th><td><?php echo htmlspecialchars((string)($cancelRequestInfo['request_type'] ?? 'full')); ?></td><th>Requested Persons</th><td><?php echo (int)($cancelRequestInfo['requested_persons'] ?? 0); ?></td></tr>
                    <tr><th>Status</th><td><?php echo htmlspecialchars((string)($cancelRequestInfo['request_status'] ?? 'pending')); ?></td><th>Requested At</th><td><?php echo htmlspecialchars((string)($cancelRequestInfo['requested_at'] ?? '-')); ?></td></tr>
                    <tr><th>Reason</th><td colspan="3"><?php echo nl2br(htmlspecialchars((string)($cancelRequestInfo['cancel_reason'] ?? '-'))); ?></td></tr>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($row['screenshot'])): ?>
            <div class="print-proof-wrap">
                <strong>Payment Proof:</strong>
                <img class="print-proof-img" src="<?php echo htmlspecialchars(ltrim((string)$row['screenshot'], '/')); ?>" alt="Payment Proof">
            </div>
        <?php endif; ?>

        <div class="print-footer">
            This is a system-generated event booking acknowledgment.
        </div>
    </div>
</section>
<style>
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html,body{font-family:'Marcellus',serif!important}
.event-confirm-main{min-height:100vh;padding:1.6rem 0 5rem}
.event-confirm-wrap{max-width:920px;margin:0 auto;padding:0 14px}
.card{background:#fff;border:1px solid #ecd3d3;border-radius:14px;box-shadow:0 4px 14px rgba(128,0,0,.08);padding:14px;margin-bottom:14px}
.status-success{background:#e9ffef;border-color:#b6e5c2}
.status-pending{background:#fff8e6;border-color:#f1dca8}
.status-failed{background:#ffeaea;border-color:#f0b7b7}
h1{margin:0 0 6px;color:#800000}
h2{margin:0 0 10px;color:#800000}
.details-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:8px;font-size:.94rem;color:#444}
.btn-row{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap}
.btn-main{display:inline-block;border:none;border-radius:8px;background:#800000;color:#fff;font-weight:700;padding:9px 12px;cursor:pointer;text-decoration:none}
.btn-alt{background:#0b7285}
.btn-print{background:#14532d}

.booking-print-sheet{display:none}
.print-shell{background:#fff;color:#1f1f1f;border:1px solid #ddd;border-radius:12px;padding:12px 14px}
.print-header{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;border-bottom:2px solid #800000;padding-bottom:8px;margin-bottom:8px}
.print-brand{display:flex;align-items:flex-start;min-width:0;flex:1}
.print-brand img{width:260px;max-width:100%;height:auto;max-height:72px;object-fit:contain;border:none;border-radius:0;background:transparent;padding:0}
.print-top-right{display:flex;flex-direction:column;align-items:flex-end;gap:4px}
.print-head-info{font-size:.86rem;color:#800000;line-height:1.3;text-align:right}
.print-head-info > div{font-size:.86rem;color:#800000;font-weight:500}
.print-head-info strong{font-weight:700}
.print-contact{font-size:.78rem;color:#333;line-height:1.35;text-align:right}
.print-status-row{display:flex;flex-direction:column;gap:2px;border:1px solid #e8d7aa;border-radius:8px;padding:6px 8px;background:#fff8e6;margin-bottom:8px;font-size:.84rem}
.print-section{border:1px solid #eadede;border-radius:8px;padding:6px 7px;background:#fff;margin-bottom:7px;break-inside:avoid}
.print-section h3{margin:0 0 5px;color:#800000;font-size:.86rem}
.print-qr-section .print-qr-wrap{display:flex;justify-content:center;align-items:center}
.print-qr-page{display:block;width:126px;height:126px;object-fit:contain;border:1px solid #ddd;border-radius:8px;background:#fff;padding:3px}
.print-table{width:100%;border-collapse:collapse;font-size:.74rem}
.print-table th,.print-table td{border:1px solid #f2e2e2;padding:3px 4px;vertical-align:top;text-align:left;line-height:1.25}
.print-table th{background:#fdf4f4;color:#7a3030;width:18%}
.print-proof-wrap{margin:4px 0 0;display:flex;align-items:center;gap:7px;font-size:.78rem}
.print-proof-img{display:block;width:120px;max-height:74px;object-fit:contain;border:1px solid #eee;border-radius:6px;background:#fff;padding:2px}
.print-footer{margin-top:8px;border-top:1px dashed #b8b8b8;padding-top:5px;font-size:.72rem;color:#555;text-align:center}

@media print{
    @page{size:A4 portrait;margin:6mm}
    html,body{background:#fff!important;margin:0!important;padding:0!important}
    body > *{display:none!important}
    #bookingPrintSheet{display:block!important;margin:0!important;padding:0!important}
    #bookingPrintSheet *{visibility:visible!important}
    #bookingPrintSheet{position:static!important;left:auto!important;top:auto!important;width:auto!important}
    .print-shell{border:none;box-shadow:none;border-radius:0;padding:0;max-height:282mm;overflow:hidden}
    .print-header{margin-bottom:6px;padding-bottom:6px}
    .print-brand img{width:260px;max-width:100%;height:auto;max-height:72px}
    .print-qr-page{width:116px;height:116px}
    .print-table{font-size:.72rem}
    .print-table th,.print-table td{padding:2px 3px}
    .print-proof-img{width:112px;max-height:68px}
}
</style>
<script>
function printBookingSheet() {
    var sourceWrap = document.getElementById('bookingPrintSheet');
    if (!sourceWrap) {
        window.print();
        return;
    }
    var printable = sourceWrap.querySelector('.print-shell');
    if (!printable) {
        window.print();
        return;
    }

    var frameId = 'event-booking-print-frame';
    var oldFrame = document.getElementById(frameId);
    if (oldFrame && oldFrame.parentNode) {
        oldFrame.parentNode.removeChild(oldFrame);
    }

    var iframe = document.createElement('iframe');
    iframe.id = frameId;
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.style.opacity = '0';
    iframe.setAttribute('aria-hidden', 'true');
    document.body.appendChild(iframe);

    var doc = iframe.contentWindow.document;
    var printCss = ''
        + '@page{size:A4 portrait;margin:6mm;}'
        + 'html,body{margin:0;padding:0;background:#fff;color:#1f1f1f;font-family:Marcellus,serif;}'
        + '.print-shell{background:#fff;color:#1f1f1f;padding:0;}'
        + '.print-header{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;border-bottom:2px solid #800000;padding-bottom:8px;margin-bottom:8px;}'
        + '.print-brand{display:flex;align-items:flex-start;min-width:0;flex:1;}'
        + '.print-brand img{width:260px;max-width:100%;height:auto;max-height:72px;object-fit:contain;border:none;border-radius:0;background:transparent;padding:0;}'
        + '.print-top-right{display:flex;flex-direction:column;align-items:flex-end;gap:4px;}'
        + '.print-head-info{font-size:.86rem;color:#800000;line-height:1.3;text-align:right;}'
        + '.print-head-info > div{font-size:.86rem;color:#800000;font-weight:500;}'
        + '.print-head-info strong{font-weight:700;}'
        + '.print-contact{font-size:.78rem;color:#333;line-height:1.35;text-align:right;}'
        + '.print-status-row{display:flex;flex-direction:column;gap:2px;border:1px solid #e8d7aa;border-radius:8px;padding:6px 8px;background:#fff8e6;margin-bottom:8px;font-size:.84rem;}'
        + '.print-section{border:1px solid #eadede;border-radius:8px;padding:6px 7px;background:#fff;margin-bottom:7px;break-inside:avoid;}'
        + '.print-section h3{margin:0 0 5px;color:#800000;font-size:.86rem;}'
        + '.print-qr-section .print-qr-wrap{display:flex;justify-content:center;align-items:center;}'
        + '.print-qr-page{display:block;width:116px;height:116px;object-fit:contain;border:1px solid #ddd;border-radius:8px;background:#fff;padding:3px;}'
        + '.print-table{width:100%;border-collapse:collapse;font-size:.72rem;}'
        + '.print-table th,.print-table td{border:1px solid #f2e2e2;padding:2px 3px;vertical-align:top;text-align:left;line-height:1.25;}'
        + '.print-table th{background:#fdf4f4;color:#7a3030;width:18%;}'
        + '.print-proof-wrap{margin:4px 0 0;display:flex;align-items:center;gap:7px;font-size:.78rem;}'
        + '.print-proof-img{display:block;width:112px;max-height:68px;object-fit:contain;border:1px solid #eee;border-radius:6px;background:#fff;padding:2px;}'
        + '.print-footer{margin-top:8px;border-top:1px dashed #b8b8b8;padding-top:5px;font-size:.72rem;color:#555;text-align:center;}';

    doc.open();
    doc.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Event Booking Print</title><style>' + printCss + '</style></head><body>' + printable.outerHTML + '</body></html>');
    doc.close();

    setTimeout(function() {
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch (e) {}
        setTimeout(function() {
            if (iframe && iframe.parentNode) {
                iframe.parentNode.removeChild(iframe);
            }
        }, 1200);
    }, 200);
}

<?php if ($autoPrint): ?>
window.addEventListener('load', function() {
    setTimeout(function() {
        printBookingSheet();
    }, 250);
});
<?php endif; ?>
</script>
<?php require_once 'footer.php'; ?>
