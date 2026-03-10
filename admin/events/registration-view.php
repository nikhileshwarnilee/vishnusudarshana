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

$isMainAdminActor = function_exists('vs_admin_is_super_admin')
    ? vs_admin_is_super_admin()
    : ((int)($_SESSION['user_id'] ?? 0) === 1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/event_module.php';

vs_event_ensure_tables($pdo);

$registrationId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['registration_id'] ?? 0);
$rawReturnUrl = trim((string)($_GET['return'] ?? $_POST['return'] ?? 'registrations.php'));
$returnUrl = 'registrations.php';
if ($rawReturnUrl !== '' && strpos($rawReturnUrl, 'registrations.php') === 0 && strpos($rawReturnUrl, '..') === false && !preg_match('/^[a-z]+:\/\//i', $rawReturnUrl)) {
    $returnUrl = $rawReturnUrl;
}

if ($registrationId <= 0) {
    header('Location: ' . $returnUrl);
    exit;
}

$message = trim((string)($_GET['msg'] ?? ''));
$messageType = trim((string)($_GET['msg_type'] ?? ''));

$redirectSelf = static function (int $id, string $return, string $msg, string $msgType): void {
    $params = [
        'id' => $id,
        'return' => $return,
        'msg' => $msg,
        'msg_type' => $msgType,
    ];
    header('Location: registration-view.php?' . http_build_query($params));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_cancel_request'], $_POST['request_id'], $_POST['request_action'])) {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $requestAction = strtolower(trim((string)($_POST['request_action'] ?? '')));
    $decisionNote = trim((string)($_POST['decision_note'] ?? ''));
    $decidedByUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $decidedByUserName = trim((string)($_SESSION['user_name'] ?? ''));
    try {
        $result = vs_event_review_cancellation_request(
            $pdo,
            $requestId,
            $requestAction,
            $decidedByUserId,
            $decidedByUserName,
            $decisionNote
        );
        $okMessage = ((string)($result['request_status'] ?? '') === 'approved')
            ? 'Cancellation request approved. Booking cancelled and refund marked processed.'
            : 'Cancellation request rejected.';
        $redirectSelf($registrationId, $returnUrl, $okMessage, 'ok');
    } catch (Throwable $e) {
        $errMessage = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to review cancellation request.';
        $redirectSelf($registrationId, $returnUrl, $errMessage, 'err');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_cancel_booking'])) {
    $cancelPersons = (int)($_POST['cancel_persons'] ?? 0);
    $cancelReason = trim((string)($_POST['cancel_reason'] ?? ''));

    try {
        $result = vs_event_cancel_registration(
            $pdo,
            $registrationId,
            $cancelPersons,
            $cancelReason,
            true,
            'processed'
        );
        $okMessage = ((string)($result['cancellation_type'] ?? 'full') === 'partial')
            ? 'Booking partially cancelled by admin and refund marked processed.'
            : 'Booking cancelled by admin and refund marked processed.';
        $redirectSelf($registrationId, $returnUrl, $okMessage, 'ok');
    } catch (Throwable $e) {
        $errMessage = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to cancel booking.';
        $redirectSelf($registrationId, $returnUrl, $errMessage, 'err');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_remaining_payment'])) {
    try {
        $pdo->beginTransaction();

        $lockStmt = $pdo->prepare("SELECT
                r.id,
                r.event_id,
                r.event_date_id,
                r.package_id,
                r.persons,
                r.name,
                r.phone,
                r.payment_status,
                r.verification_status,
                r.booking_reference,
                r.package_upi_id_snapshot,
                r.package_upi_qr_snapshot,
                e.title AS event_title,
                e.event_type,
                COALESCE(d.event_date, e.event_date) AS selected_event_date,
                p.package_name,
                COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS package_price_total,
                COALESCE(ep.amount_paid, 0) AS amount_paid,
                COALESCE(ep.remaining_amount, 0) AS remaining_amount
            FROM event_registrations r
            INNER JOIN events e ON e.id = r.event_id
            LEFT JOIN event_dates d ON d.id = r.event_date_id
            INNER JOIN event_packages p ON p.id = r.package_id
            LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
            LEFT JOIN event_payments ep ON ep.registration_id = r.id
            WHERE r.id = ?
            LIMIT 1
            FOR UPDATE");
        $lockStmt->execute([$registrationId]);
        $locked = $lockStmt->fetch(PDO::FETCH_ASSOC);
        if (!$locked) {
            throw new RuntimeException('Registration not found.');
        }

        if (strtolower((string)($locked['payment_status'] ?? '')) !== 'partial paid') {
            throw new RuntimeException('Remaining payment collection is only available for partial paid bookings.');
        }

        $totalAmount = round((float)($locked['package_price_total'] ?? 0) * max((int)($locked['persons'] ?? 1), 1), 2);
        $alreadyPaid = round(max((float)($locked['amount_paid'] ?? 0), 0), 2);
        $remainingDue = round((float)($locked['remaining_amount'] ?? 0), 2);
        if ($remainingDue <= 0) {
            $remainingDue = round(max($totalAmount - $alreadyPaid, 0), 2);
        }
        if ($remainingDue <= 0) {
            throw new RuntimeException('No remaining amount found for this booking.');
        }

        $methodInput = strtolower(trim((string)($_POST['remaining_payment_method'] ?? '')));
        if (!in_array($methodInput, ['upi', 'cash'], true)) {
            throw new RuntimeException('Please choose payment method (UPI or Cash).');
        }
        $paymentMethod = $methodInput === 'upi' ? 'Manual UPI' : 'Cash';

        $transactionId = trim((string)($_POST['remaining_transaction_id'] ?? ''));
        if ($methodInput === 'upi' && $transactionId === '') {
            throw new RuntimeException('Transaction ID is required for UPI collection.');
        }
        if ($methodInput === 'cash' && $transactionId === '') {
            $transactionId = 'CASH-REG-' . date('YmdHis');
        }

        $remark = trim((string)($_POST['remaining_payment_remark'] ?? ''));
        if ($remark === '') {
            throw new RuntimeException('Remark is required for remaining payment.');
        }

        if (!isset($_FILES['remaining_payment_proof']) || (int)($_FILES['remaining_payment_proof']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Payment proof image is required.');
        }
        $proofPath = vs_event_store_upload($_FILES['remaining_payment_proof'], 'payments', ['jpg', 'jpeg', 'png', 'webp']);
        if ($proofPath === null) {
            throw new RuntimeException('Invalid payment proof upload. Allowed formats: jpg, jpeg, png, webp.');
        }

        $upiIdUsed = null;
        $upiQrUsed = null;
        if ($methodInput === 'upi') {
            $tmpUpiId = trim((string)($locked['package_upi_id_snapshot'] ?? ''));
            $tmpUpiQr = trim((string)($locked['package_upi_qr_snapshot'] ?? ''));
            $upiIdUsed = $tmpUpiId !== '' ? $tmpUpiId : null;
            $upiQrUsed = $tmpUpiQr !== '' ? $tmpUpiQr : null;
        }

        if ($isMainAdminActor) {
            $newPaid = round($alreadyPaid + $remainingDue, 2);
            if ($newPaid > $totalAmount) {
                $newPaid = $totalAmount;
            }
            $remainingAfter = round(max($totalAmount - $newPaid, 0), 2);
            $newPaymentStatus = $remainingAfter > 0 ? 'Partial Paid' : 'Paid';
            $newPaymentType = $remainingAfter > 0 ? 'remaining' : 'full';

            $upsert = $pdo->prepare("INSERT INTO event_payments (registration_id, amount, payment_type, amount_paid, remaining_amount, payment_method, upi_id_used, upi_qr_used, transaction_id, screenshot, remarks, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Approved')
                ON DUPLICATE KEY UPDATE
                    amount = VALUES(amount),
                    payment_type = VALUES(payment_type),
                    amount_paid = VALUES(amount_paid),
                    remaining_amount = VALUES(remaining_amount),
                    payment_method = VALUES(payment_method),
                    upi_id_used = VALUES(upi_id_used),
                    upi_qr_used = VALUES(upi_qr_used),
                    transaction_id = VALUES(transaction_id),
                    screenshot = VALUES(screenshot),
                    remarks = VALUES(remarks),
                    status = 'Approved'");
            $upsert->execute([
                $registrationId,
                $remainingDue,
                $newPaymentType,
                $newPaid,
                $remainingAfter,
                $paymentMethod,
                $upiIdUsed,
                $upiQrUsed,
                $transactionId,
                $proofPath,
                $remark,
            ]);

            $pdo->prepare("UPDATE event_registrations
                SET payment_status = ?,
                    verification_status = 'Auto Verified'
                WHERE id = ?")
                ->execute([$newPaymentStatus, $registrationId]);

            $bookingReference = trim((string)($locked['booking_reference'] ?? ''));
            if ($bookingReference === '') {
                $bookingReference = vs_event_assign_booking_reference($pdo, $registrationId);
            }
            if (vs_event_is_whatsapp_enabled($pdo, (int)$locked['event_id'])) {
                $eventDateLabel = vs_event_get_registration_date_display($pdo, $locked, (string)($locked['selected_event_date'] ?? ''));
                if ($remainingAfter <= 0) {
                    $qrCodePath = vs_event_ensure_registration_qr($pdo, $registrationId);
                    vs_event_send_whatsapp_notice('ticket_delivery', (string)$locked['phone'], [
                        'name' => (string)$locked['name'],
                        'event_name' => (string)$locked['event_title'],
                        'package_name' => (string)$locked['package_name'],
                        'event_date' => $eventDateLabel,
                        'amount' => (string)$totalAmount,
                        'booking_reference' => $bookingReference,
                        'registration_id' => $registrationId,
                        'event_id' => (int)$locked['event_id'],
                        'qr_code_path' => $qrCodePath,
                    ]);
                } else {
                    vs_event_send_whatsapp_notice('payment_approved', (string)$locked['phone'], [
                        'name' => (string)$locked['name'],
                        'event_name' => (string)$locked['event_title'],
                        'package_name' => (string)$locked['package_name'],
                        'event_date' => $eventDateLabel,
                        'amount' => (string)$remainingDue,
                        'booking_reference' => $bookingReference,
                        'registration_id' => $registrationId,
                        'event_id' => (int)$locked['event_id'],
                    ]);
                }
            }

            $pdo->commit();
            $redirectSelf($registrationId, $returnUrl, 'Remaining payment collected and auto-verified (main admin).', 'ok');
        }

        $upsert = $pdo->prepare("INSERT INTO event_payments (registration_id, amount, payment_type, amount_paid, remaining_amount, payment_method, upi_id_used, upi_qr_used, transaction_id, screenshot, remarks, status)
            VALUES (?, ?, 'remaining', ?, ?, ?, ?, ?, ?, ?, ?, 'Pending Verification')
            ON DUPLICATE KEY UPDATE
                amount = VALUES(amount),
                payment_type = VALUES(payment_type),
                amount_paid = VALUES(amount_paid),
                remaining_amount = VALUES(remaining_amount),
                payment_method = VALUES(payment_method),
                upi_id_used = VALUES(upi_id_used),
                upi_qr_used = VALUES(upi_qr_used),
                transaction_id = VALUES(transaction_id),
                screenshot = VALUES(screenshot),
                remarks = VALUES(remarks),
                status = 'Pending Verification'");
        $upsert->execute([
            $registrationId,
            $remainingDue,
            $alreadyPaid,
            $remainingDue,
            $paymentMethod,
            $upiIdUsed,
            $upiQrUsed,
            $transactionId,
            $proofPath,
            $remark,
        ]);

        $pdo->prepare("UPDATE event_registrations
            SET payment_status = 'Pending Verification',
                verification_status = 'Pending'
            WHERE id = ?")
            ->execute([$registrationId]);

        $pdo->commit();
        $redirectSelf($registrationId, $returnUrl, 'Remaining payment collected. Awaiting payment verification.', 'ok');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errMessage = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to collect remaining payment.';
        $redirectSelf($registrationId, $returnUrl, $errMessage, 'err');
    }
}

$detailStmt = $pdo->prepare("SELECT
    r.*,
    r.booking_reference,
    e.title AS event_title,
    e.event_type,
    COALESCE(d.event_date, e.event_date) AS selected_event_date,
    e.location,
    e.slug AS event_slug,
    p.package_name,
    p.cancellation_allowed,
    p.refund_allowed,
    COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS package_price_total,
    r.package_upi_id_snapshot,
    r.package_upi_qr_snapshot,
    ep.payment_method,
    ep.upi_id_used,
    ep.upi_qr_used,
    ep.transaction_id,
    ep.screenshot,
    ep.status AS payment_record_status,
    ep.payment_type,
    ep.amount AS payment_amount,
    ep.amount_paid,
    ep.remaining_amount,
    ep.remarks
FROM event_registrations r
INNER JOIN events e ON e.id = r.event_id
LEFT JOIN event_dates d ON d.id = r.event_date_id
INNER JOIN event_packages p ON p.id = r.package_id
LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
LEFT JOIN event_payments ep ON ep.registration_id = r.id
WHERE r.id = ?
LIMIT 1");
$detailStmt->execute([$registrationId]);
$registration = $detailStmt->fetch(PDO::FETCH_ASSOC);

if (!$registration) {
    header('Location: ' . $returnUrl);
    exit;
}

$ref = trim((string)($registration['booking_reference'] ?? ''));
if ($ref === '') {
    $registration['booking_reference'] = vs_event_assign_booking_reference($pdo, (int)$registration['id']);
}
$registration['event_date_display'] = vs_event_get_registration_date_display(
    $pdo,
    $registration,
    (string)($registration['selected_event_date'] ?? '')
);

$fieldStmt = $pdo->prepare('SELECT field_name, value FROM event_registration_data WHERE registration_id = ? ORDER BY id ASC');
$fieldStmt->execute([$registrationId]);
$registrationFields = $fieldStmt->fetchAll(PDO::FETCH_ASSOC);

$cancelHistoryStmt = $pdo->prepare("SELECT *
    FROM event_cancellations
    WHERE registration_id = ?
    ORDER BY id DESC");
$cancelHistoryStmt->execute([$registrationId]);
$cancelHistory = $cancelHistoryStmt->fetchAll(PDO::FETCH_ASSOC);

$cancelRequestHistoryStmt = $pdo->prepare("SELECT *
    FROM event_cancellation_requests
    WHERE registration_id = ?
    ORDER BY id DESC");
$cancelRequestHistoryStmt->execute([$registrationId]);
$cancelRequestHistory = $cancelRequestHistoryStmt->fetchAll(PDO::FETCH_ASSOC);
$pendingCancelRequest = null;
foreach ($cancelRequestHistory as $requestRow) {
    if (strtolower(trim((string)($requestRow['request_status'] ?? ''))) === 'pending') {
        $pendingCancelRequest = $requestRow;
        break;
    }
}

$paymentStatusLower = strtolower(trim((string)($registration['payment_status'] ?? '')));
$verificationStatusLower = strtolower(trim((string)($registration['verification_status'] ?? '')));
$viewCanCancel = (
    $paymentStatusLower !== 'cancelled' &&
    (int)($registration['checkin_status'] ?? 0) !== 1 &&
    $pendingCancelRequest === null
);
$viewMaxCancelablePersons = max((int)($registration['persons'] ?? 1), 1);

$packagePriceTotal = (float)($registration['package_price_total'] ?? 0);
$totalAmount = round($packagePriceTotal * max((int)$registration['persons'], 1), 2);
$paidSoFar = round(max((float)($registration['amount_paid'] ?? 0), 0), 2);
if ($paymentStatusLower === 'paid' && $paidSoFar <= 0) {
    $paidSoFar = $totalAmount;
}
$remainingAmount = round((float)($registration['remaining_amount'] ?? 0), 2);
if ($remainingAmount <= 0 && $paymentStatusLower !== 'cancelled') {
    $remainingAmount = round(max($totalAmount - $paidSoFar, 0), 2);
}

$isCancelledBooking = ($paymentStatusLower === 'cancelled' || $verificationStatusLower === 'cancelled');
$paymentMethodLower = strtolower(trim((string)($registration['payment_method'] ?? '')));
$paymentRecordStatusLower = strtolower(trim((string)($registration['payment_record_status'] ?? '')));
$submittedManualAmount = round(max((float)($registration['payment_amount'] ?? 0), 0), 2);
$isManualOrCash = in_array($paymentMethodLower, ['manual upi', 'cash'], true);
$isPendingManualVerification = $isManualOrCash && (
    in_array($paymentStatusLower, ['pending', 'pending verification'], true) ||
    in_array($verificationStatusLower, ['pending', 'pending verification'], true) ||
    in_array($paymentRecordStatusLower, ['pending', 'pending verification'], true)
);
$isRejectedManualVerification = $isManualOrCash && (
    in_array($verificationStatusLower, ['rejected'], true) ||
    in_array($paymentRecordStatusLower, ['rejected'], true) ||
    in_array($paymentStatusLower, ['failed', 'rejected'], true)
);
$isApprovedPayment = (
    in_array($verificationStatusLower, ['approved', 'auto verified'], true) ||
    in_array($paymentRecordStatusLower, ['approved', 'paid', 'success', 'successful'], true) ||
    in_array($paymentStatusLower, ['paid', 'partial paid'], true)
);

$verifiedPaidAmount = round(max($paidSoFar, 0), 2);
if ($verifiedPaidAmount > $totalAmount && $totalAmount > 0) {
    $verifiedPaidAmount = $totalAmount;
}
$pendingSubmittedAmount = (!$isCancelledBooking && $isPendingManualVerification && !$isRejectedManualVerification && $submittedManualAmount > 0)
    ? $submittedManualAmount
    : 0.0;
$rejectedSubmittedAmount = (!$isCancelledBooking && $isRejectedManualVerification && $submittedManualAmount > 0)
    ? $submittedManualAmount
    : 0.0;

$displayPaidSoFar = $verifiedPaidAmount;
$displayRemainingAmount = $remainingAmount;
if (!$isCancelledBooking && $pendingSubmittedAmount > 0) {
    $displayPaidSoFar = round(min($totalAmount, $verifiedPaidAmount + $pendingSubmittedAmount), 2);
    $displayRemainingAmount = round(max($totalAmount - $displayPaidSoFar, 0), 2);
} elseif (!$isCancelledBooking && $isRejectedManualVerification) {
    $displayPaidSoFar = $verifiedPaidAmount;
    $displayRemainingAmount = round(max($totalAmount - $displayPaidSoFar, 0), 2);
}

$paymentVisualState = 'neutral';
$paymentVisualIcon = '&#9432;';
$paymentVisualTitle = 'Awaiting Payment Verification';
$paymentVisualMessage = 'Payment is not yet verified for this booking.';
if ($isCancelledBooking) {
    $paymentVisualState = 'neutral';
    $paymentVisualIcon = '&#9888;';
    $paymentVisualTitle = 'Booking Cancelled';
    $paymentVisualMessage = 'Payment status is locked because this booking is cancelled.';
} elseif ($isPendingManualVerification && !$isRejectedManualVerification) {
    $paymentVisualState = 'pending';
    $paymentVisualIcon = '&#9203;';
    $paymentVisualTitle = 'Under Verification';
    $paymentVisualMessage = 'Manual payment proof is submitted and waiting for admin approval.';
} elseif ($isRejectedManualVerification) {
    $paymentVisualState = 'rejected';
    $paymentVisualIcon = '&#10006;';
    $paymentVisualTitle = 'Verification Rejected';
    $paymentVisualMessage = 'Previous submitted payment was rejected. Customer needs to pay again.';
} elseif ($isApprovedPayment) {
    $paymentVisualState = 'approved';
    $paymentVisualIcon = '&#10004;';
    $paymentVisualTitle = 'Payment Verified';
    $paymentVisualMessage = 'Amount is verified and counted in confirmed paid value.';
}

$paymentDisplayNote = '';
if ($pendingSubmittedAmount > 0) {
    $paymentDisplayNote = 'Displayed paid amount includes pending verification submission.';
} elseif ($isRejectedManualVerification && $displayPaidSoFar <= 0) {
    $paymentDisplayNote = 'Previous payment verification was rejected. Paid amount is Rs 0.';
}
$paymentVisualStatClass = in_array($paymentVisualState, ['pending', 'approved', 'rejected'], true)
    ? ('stat-' . $paymentVisualState)
    : '';

$canCollectRemaining = ($paymentStatusLower === 'partial paid' && $remainingAmount > 0);

$detailUpiAccount = trim((string)($registration['upi_id_used'] ?? ''));
if ($detailUpiAccount === '') {
    $detailUpiAccount = trim((string)($registration['package_upi_id_snapshot'] ?? ''));
}
$detailUpiQr = trim((string)($registration['upi_qr_used'] ?? ''));
if ($detailUpiQr === '') {
    $detailUpiQr = trim((string)($registration['package_upi_qr_snapshot'] ?? ''));
}

$visibleRegistrationFields = [];
foreach ($registrationFields as $fieldRow) {
    $fieldName = (string)($fieldRow['field_name'] ?? '');
    if ($fieldName === '' || strpos($fieldName, '__event_reminder_') === 0) {
        continue;
    }
    $visibleRegistrationFields[] = $fieldRow;
}

$paymentStatusText = trim((string)($registration['payment_status'] ?? ''));
$verificationStatusText = trim((string)($registration['verification_status'] ?? ''));
$paymentRecordStatusText = trim((string)($registration['payment_record_status'] ?? ''));
$paymentMethodText = trim((string)($registration['payment_method'] ?? ''));
$paymentTypeText = trim((string)($registration['payment_type'] ?? 'full'));
$paymentTypeText = $paymentTypeText !== '' ? ucfirst($paymentTypeText) : '-';
$checkinStatusText = ((int)($registration['checkin_status'] ?? 0) === 1) ? 'Checked In' : 'Not Checked In';
$checkinTimeText = trim((string)($registration['checkin_time'] ?? ''));
$createdAtText = trim((string)($registration['created_at'] ?? ''));
$eventDateDisplay = (string)($registration['event_date_display'] ?? $registration['selected_event_date'] ?? '-');

$resolveStatusClass = static function (string $statusValue, string $context): string {
    $status = strtolower(trim($statusValue));
    if ($context === 'payment') {
        if ($status === 'paid') {
            return 'pill-success';
        }
        if (in_array($status, ['unpaid', 'pending verification', 'partial paid', 'pending'], true)) {
            return 'pill-warning';
        }
        if (in_array($status, ['failed', 'cancelled', 'rejected'], true)) {
            return 'pill-danger';
        }
        return 'pill-neutral';
    }
    if ($context === 'verification') {
        if (in_array($status, ['approved', 'auto verified'], true)) {
            return 'pill-success';
        }
        if ($status === 'pending') {
            return 'pill-warning';
        }
        if ($status === 'rejected') {
            return 'pill-danger';
        }
        return 'pill-neutral';
    }
    if ($context === 'checkin') {
        return $status === 'checked in' ? 'pill-success' : 'pill-neutral';
    }
    if ($status === 'approved' || $status === 'success') {
        return 'pill-success';
    }
    if ($status === 'pending verification' || $status === 'pending') {
        return 'pill-warning';
    }
    if ($status === 'rejected' || $status === 'failed') {
        return 'pill-danger';
    }
    return 'pill-neutral';
};

$paymentStatusClass = $resolveStatusClass($paymentStatusText, 'payment');
$verificationStatusClass = $resolveStatusClass($verificationStatusText, 'verification');
$paymentRecordStatusClass = $resolveStatusClass($paymentRecordStatusText, 'record');
$checkinStatusClass = $resolveStatusClass($checkinStatusText, 'checkin');

$pageTitle = 'Registration View';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration View</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        :root {
            --maroon: #7a0f12;
            --maroon-dark: #5f0c0f;
            --line: #ecd7d8;
            --ink: #21242d;
            --muted: #697488;
            --surface: #ffffff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--ink);
            background: #f4f6fb;
        }
        .admin-container { max-width: 1480px; margin: 0 auto; padding: 24px 14px 34px; }
        .surface {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
            box-shadow: 0 8px 20px rgba(79, 39, 39, 0.08);
        }
        .page-head {
            padding: 16px 18px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }
        .page-head h1 { margin: 4px 0 6px; color: var(--maroon); font-size: 1.45rem; line-height: 1.2; }
        .eyebrow { margin: 0; color: #8f7d7d; font-size: 0.78rem; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 700; }
        .subline { margin: 0; color: var(--muted); font-size: 0.92rem; }
        .event-line { margin: 8px 0 0; color: #4f596d; font-size: 0.93rem; }
        .head-right { text-align: right; min-width: 280px; }
        .ref-label { color: #7a7d8a; font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; }
        .ref-value { color: var(--maroon-dark); font-size: 1.15rem; font-weight: 800; margin-top: 3px; }
        .chip-row { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; margin-top: 8px; }
        .pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 0.74rem;
            font-weight: 700;
            border: 1px solid transparent;
            white-space: nowrap;
        }
        .pill-success { background: #e8f8ee; border-color: #c4ebd3; color: #14703f; }
        .pill-warning { background: #fff6e5; border-color: #f6dfaf; color: #8f5d00; }
        .pill-danger { background: #ffecee; border-color: #f8c2ca; color: #a12536; }
        .pill-neutral { background: #f2f4f8; border-color: #dbe1ea; color: #4c5564; }
        .action-bar { padding: 12px; display: flex; gap: 9px; flex-wrap: wrap; margin-bottom: 12px; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 9px 14px;
            border-radius: 10px;
            border: 1px solid transparent;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.15s ease all;
        }
        .btn-primary { background: var(--maroon); color: #fff; border-color: var(--maroon); }
        .btn-primary:hover { background: var(--maroon-dark); border-color: var(--maroon-dark); }
        .btn-secondary { background: #0f6f95; color: #fff; border-color: #0f6f95; }
        .btn-secondary:hover { background: #0a5673; border-color: #0a5673; }
        .btn-success { background: #1a7f3d; color: #fff; border-color: #1a7f3d; }
        .btn-success:hover { background: #156734; border-color: #156734; }
        .btn-danger { background: #c63642; color: #fff; border-color: #c63642; }
        .btn-danger:hover { background: #a72b36; border-color: #a72b36; }
        .btn-outline { background: #fff; color: var(--maroon); border-color: #ddc6c8; }
        .btn-outline:hover { background: #fdf5f5; }
        .notice { margin-bottom: 12px; border-radius: 12px; padding: 10px 12px; font-size: 0.9rem; font-weight: 700; border: 1px solid transparent; }
        .notice.ok { background: #e7f7ed; border-color: #c6e8d4; color: #1a6e3f; }
        .notice.err { background: #ffecee; border-color: #f8c2ca; color: #9f1f2e; }
        .payment-spotlight {
            padding: 12px 14px;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 10px;
            margin-bottom: 12px;
            border: 1px solid #ddd;
            border-radius: 14px;
        }
        .payment-spotlight-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1;
        }
        .payment-spotlight-copy { display: flex; flex-direction: column; gap: 3px; }
        .payment-spotlight-copy strong { color: #3a2020; font-size: 0.98rem; }
        .payment-spotlight-copy span { color: #4c586c; font-size: 0.86rem; line-height: 1.35; }
        .payment-spotlight-amounts {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 8px;
            margin-top: 2px;
        }
        .payment-spotlight-amount {
            background: #fff;
            border: 1px solid #e4d8d9;
            border-radius: 10px;
            padding: 8px 9px;
        }
        .payment-spotlight-amount span {
            display: block;
            font-size: 0.74rem;
            color: #6b6f7b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 700;
        }
        .payment-spotlight-amount strong {
            display: block;
            margin-top: 3px;
            font-size: 1.05rem;
            color: #2a303b;
            line-height: 1.2;
        }
        .payment-spotlight-pending { background: #fff8e8; border-color: #f2ddad; }
        .payment-spotlight-pending .payment-spotlight-icon { background: #f7d98d; color: #6d4b00; }
        .payment-spotlight-approved { background: #eef9f1; border-color: #cee7d5; }
        .payment-spotlight-approved .payment-spotlight-icon { background: #bde4c8; color: #14653a; }
        .payment-spotlight-rejected { background: #ffeff1; border-color: #f1c2cb; }
        .payment-spotlight-rejected .payment-spotlight-icon { background: #f3b3be; color: #992333; }
        .payment-spotlight-neutral { background: #f4f6fb; border-color: #d8dfeb; }
        .payment-spotlight-neutral .payment-spotlight-icon { background: #dbe1eb; color: #485566; }
        .payment-spotlight-note {
            grid-column: 1 / -1;
            margin: 0;
            font-size: 0.82rem;
            line-height: 1.32;
        }
        .payment-spotlight-pending .payment-spotlight-note { color: #8a5f00; }
        .payment-spotlight-rejected .payment-spotlight-note { color: #9f1f2e; }
        .payment-spotlight-approved .payment-spotlight-note { color: #156734; }
        .payment-spotlight-neutral .payment-spotlight-note { color: #485566; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(175px,1fr)); gap: 10px; margin-bottom: 12px; }
        .stat-card { padding: 12px; }
        .stat-label { margin: 0; color: #6f7584; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.07em; font-weight: 700; }
        .stat-value { margin: 6px 0 0; font-size: 1.22rem; font-weight: 800; color: #2a303b; line-height: 1.15; }
        .stat-card.stat-pending { background: #fff8e8; border-color: #f2ddad; }
        .stat-card.stat-pending .stat-value { color: #8a5f00; }
        .stat-card.stat-approved { background: #eef9f1; border-color: #cee7d5; }
        .stat-card.stat-approved .stat-value { color: #156734; }
        .stat-card.stat-rejected { background: #ffeff1; border-color: #f1c2cb; }
        .stat-card.stat-rejected .stat-value { color: #9f1f2e; }
        .details-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        .panel { padding: 15px; margin-bottom: 12px; }
        .panel h3 { margin: 0 0 10px; color: var(--maroon); font-size: 1rem; }
        .help { margin: 0 0 10px; color: var(--muted); font-size: 0.86rem; }
        .info-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .info-table th, .info-table td { padding: 8px 7px; border-bottom: 1px solid #f0e2e3; vertical-align: top; text-align: left; }
        .info-table th { width: 36%; color: #6a2730; font-weight: 700; background: #fff7f7; }
        .info-table td { color: #243142; word-break: break-word; }
        .inline-link { color: #0f6f95; font-weight: 700; text-decoration: none; }
        .inline-link:hover { text-decoration: underline; }
        .text-muted { color: #7b8494; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap: 10px; align-items: end; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1/-1; }
        label { color: #612229; font-size: 0.84rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; }
        input[type="text"], input[type="number"], input[type="file"], select, textarea {
            width: 100%;
            border: 1px solid #dbc6c8;
            border-radius: 10px;
            padding: 9px 10px;
            font-size: 0.92rem;
            color: #2f3a4b;
            background: #fff;
        }
        textarea { min-height: 88px; resize: vertical; }
        .input-readonly { background: #f8fafc; color: #415066; }
        .small-note { margin: 0; color: var(--muted); font-size: 0.82rem; }
        .pending-note { margin-bottom: 12px; padding: 10px 12px; border-radius: 10px; background: #fff7e9; border: 1px solid #f3ddad; color: #8a6000; font-size: 0.9rem; font-weight: 600; }
        .table-wrap { overflow-x: auto; }
        .list-table { width: 100%; border-collapse: collapse; table-layout: auto; font-size: 0.86rem; }
        .list-table th, .list-table td { padding: 8px 7px; border-bottom: 1px solid #ecdfe0; text-align: left; vertical-align: top; }
        .list-table th { background: #fff7f8; color: #6a2730; font-weight: 700; font-size: 0.84rem; text-transform: uppercase; letter-spacing: 0.04em; }
        @media (max-width: 1024px) {
            .details-layout { grid-template-columns: 1fr; }
            .page-head { flex-direction: column; }
            .head-right { text-align: left; min-width: 0; }
            .chip-row { justify-content: flex-start; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <div class="surface page-head">
        <div>
            <p class="eyebrow">Events / Registrations</p>
            <h1>Registration Detail</h1>
            <p class="subline">Registration ID #<?php echo (int)$registration['id']; ?> &middot; Created on <?php echo htmlspecialchars($createdAtText !== '' ? $createdAtText : '-'); ?></p>
            <p class="event-line">
                <strong>Event:</strong> <?php echo htmlspecialchars((string)$registration['event_title']); ?>
                &nbsp;|&nbsp;
                <strong>Package:</strong> <?php echo htmlspecialchars((string)$registration['package_name']); ?>
            </p>
        </div>
        <div class="head-right">
            <div class="ref-label">Booking Reference</div>
            <div class="ref-value"><?php echo htmlspecialchars((string)($registration['booking_reference'] ?? '-')); ?></div>
            <div class="chip-row">
                <span class="pill <?php echo htmlspecialchars($paymentStatusClass); ?>">Payment: <?php echo htmlspecialchars($paymentStatusText !== '' ? $paymentStatusText : '-'); ?></span>
                <span class="pill <?php echo htmlspecialchars($verificationStatusClass); ?>">Verification: <?php echo htmlspecialchars($verificationStatusText !== '' ? $verificationStatusText : '-'); ?></span>
                <span class="pill <?php echo htmlspecialchars($checkinStatusClass); ?>"><?php echo htmlspecialchars($checkinStatusText); ?></span>
            </div>
        </div>
    </div>

    <div class="surface action-bar">
        <a class="btn btn-secondary" href="<?php echo htmlspecialchars($returnUrl); ?>">Back To Registrations</a>
        <button type="button" class="btn btn-primary" onclick="printRegistrationTicket(<?php echo (int)$registration['id']; ?>);">Print</button>
        <?php if ($canCollectRemaining): ?>
            <a class="btn btn-outline" href="../../event-remaining-payment.php?booking_reference=<?php echo urlencode((string)$registration['booking_reference']); ?>&phone=<?php echo urlencode((string)$registration['phone']); ?>" target="_blank">Open Customer Remaining Payment Page</a>
        <?php endif; ?>
    </div>

    <?php if ($message !== ''): ?>
        <div class="notice <?php echo ($messageType === 'ok') ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="surface payment-spotlight payment-spotlight-<?php echo htmlspecialchars($paymentVisualState); ?>">
        <span class="payment-spotlight-icon" aria-hidden="true"><?php echo $paymentVisualIcon; ?></span>
        <div class="payment-spotlight-copy">
            <strong><?php echo htmlspecialchars($paymentVisualTitle); ?></strong>
            <span><?php echo htmlspecialchars($paymentVisualMessage); ?></span>
        </div>
        <div class="payment-spotlight-amounts">
            <div class="payment-spotlight-amount">
                <span>Total Amount</span>
                <strong>Rs <?php echo number_format($totalAmount, 0, '.', ''); ?></strong>
            </div>
            <div class="payment-spotlight-amount">
                <span>Paid Amount (Shown)</span>
                <strong>Rs <?php echo number_format($displayPaidSoFar, 0, '.', ''); ?></strong>
            </div>
            <div class="payment-spotlight-amount">
                <span>Remaining Amount</span>
                <strong>Rs <?php echo number_format($displayRemainingAmount, 0, '.', ''); ?></strong>
            </div>
            <?php if ($pendingSubmittedAmount > 0): ?>
                <div class="payment-spotlight-amount">
                    <span>Submitted For Verification</span>
                    <strong>Rs <?php echo number_format($pendingSubmittedAmount, 0, '.', ''); ?></strong>
                </div>
                <div class="payment-spotlight-amount">
                    <span>Confirmed Paid (Verified)</span>
                    <strong>Rs <?php echo number_format($verifiedPaidAmount, 0, '.', ''); ?></strong>
                </div>
            <?php endif; ?>
            <?php if ($rejectedSubmittedAmount > 0): ?>
                <div class="payment-spotlight-amount">
                    <span>Previous Rejected Submission</span>
                    <strong>Rs <?php echo number_format($rejectedSubmittedAmount, 0, '.', ''); ?></strong>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($paymentDisplayNote !== ''): ?>
            <p class="payment-spotlight-note"><?php echo htmlspecialchars($paymentDisplayNote); ?></p>
        <?php endif; ?>
    </div>

    <div class="stats-grid">
        <div class="surface stat-card">
            <p class="stat-label">Total Amount</p>
            <p class="stat-value">Rs <?php echo number_format($totalAmount, 0, '.', ''); ?></p>
        </div>
        <div class="surface stat-card <?php echo htmlspecialchars($paymentVisualStatClass); ?>">
            <p class="stat-label">Paid So Far</p>
            <p class="stat-value">Rs <?php echo number_format($displayPaidSoFar, 0, '.', ''); ?></p>
        </div>
        <div class="surface stat-card <?php echo htmlspecialchars($paymentVisualStatClass); ?>">
            <p class="stat-label">Remaining</p>
            <p class="stat-value">Rs <?php echo number_format($displayRemainingAmount, 0, '.', ''); ?></p>
        </div>
        <div class="surface stat-card">
            <p class="stat-label">Persons / Qty</p>
            <p class="stat-value"><?php echo (int)$registration['persons']; ?></p>
        </div>
        <div class="surface stat-card">
            <p class="stat-label">Event Date</p>
            <p class="stat-value" style="font-size:1rem;"><?php echo htmlspecialchars($eventDateDisplay); ?></p>
        </div>
    </div>

    <div class="details-layout">
        <section class="surface panel">
            <h3>Booking & Event Information</h3>
            <div class="table-wrap">
                <table class="info-table">
                    <tbody>
                    <tr>
                        <th>Booking Reference</th>
                        <td><?php echo htmlspecialchars((string)($registration['booking_reference'] ?? '-')); ?></td>
                    </tr>
                    <tr>
                        <th>Registrant Name</th>
                        <td><?php echo htmlspecialchars((string)$registration['name']); ?></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><?php echo htmlspecialchars((string)$registration['phone']); ?></td>
                    </tr>
                    <tr>
                        <th>Event</th>
                        <td><?php echo htmlspecialchars((string)$registration['event_title']); ?></td>
                    </tr>
                    <tr>
                        <th>Package</th>
                        <td><?php echo htmlspecialchars((string)$registration['package_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Event Date</th>
                        <td><?php echo htmlspecialchars($eventDateDisplay); ?></td>
                    </tr>
                    <tr>
                        <th>Location</th>
                        <td><?php echo htmlspecialchars((string)$registration['location']); ?></td>
                    </tr>
                    <tr>
                        <th>Persons / Qty</th>
                        <td><?php echo (int)$registration['persons']; ?></td>
                    </tr>
                    <tr>
                        <th>Created At</th>
                        <td><?php echo htmlspecialchars($createdAtText !== '' ? $createdAtText : '-'); ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="surface panel">
            <h3>Payment & Verification Information</h3>
            <div class="table-wrap">
                <table class="info-table">
                    <tbody>
                    <tr>
                        <th>Payment Status</th>
                        <td><span class="pill <?php echo htmlspecialchars($paymentStatusClass); ?>"><?php echo htmlspecialchars($paymentStatusText !== '' ? $paymentStatusText : '-'); ?></span></td>
                    </tr>
                    <tr>
                        <th>Verification Status</th>
                        <td><span class="pill <?php echo htmlspecialchars($verificationStatusClass); ?>"><?php echo htmlspecialchars($verificationStatusText !== '' ? $verificationStatusText : '-'); ?></span></td>
                    </tr>
                    <tr>
                        <th>Payment Type</th>
                        <td><?php echo htmlspecialchars($paymentTypeText); ?></td>
                    </tr>
                    <tr>
                        <th>Payment Method</th>
                        <td><?php echo htmlspecialchars($paymentMethodText !== '' ? $paymentMethodText : '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Current Payment Amount</th>
                        <td>Rs <?php echo number_format((float)($registration['payment_amount'] ?? 0), 0, '.', ''); ?></td>
                    </tr>
                    <tr>
                        <th>Paid Amount (Shown)</th>
                        <td>Rs <?php echo number_format($displayPaidSoFar, 0, '.', ''); ?></td>
                    </tr>
                    <tr>
                        <th>Remaining Amount (Shown)</th>
                        <td>Rs <?php echo number_format($displayRemainingAmount, 0, '.', ''); ?></td>
                    </tr>
                    <?php if ($pendingSubmittedAmount > 0): ?>
                        <tr>
                            <th>Submitted For Verification</th>
                            <td>Rs <?php echo number_format($pendingSubmittedAmount, 0, '.', ''); ?></td>
                        </tr>
                        <tr>
                            <th>Confirmed Paid (Verified)</th>
                            <td>Rs <?php echo number_format($verifiedPaidAmount, 0, '.', ''); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($rejectedSubmittedAmount > 0): ?>
                        <tr>
                            <th>Previous Rejected Submission</th>
                            <td>Rs <?php echo number_format($rejectedSubmittedAmount, 0, '.', ''); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($paymentDisplayNote !== ''): ?>
                        <tr>
                            <th>Payment Note</th>
                            <td><?php echo htmlspecialchars($paymentDisplayNote); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th>UPI Account Used</th>
                        <td><?php echo $detailUpiAccount !== '' ? htmlspecialchars($detailUpiAccount) : '-'; ?></td>
                    </tr>
                    <tr>
                        <th>UPI QR Used</th>
                        <td>
                            <?php if ($detailUpiQr !== ''): ?>
                                <a class="inline-link" href="../../<?php echo htmlspecialchars(ltrim($detailUpiQr, '/')); ?>" target="_blank">View UPI QR</a>
                            <?php else: ?>
                                <span class="text-muted">Not available</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Transaction ID</th>
                        <td><?php echo htmlspecialchars((string)($registration['transaction_id'] ?? '-')); ?></td>
                    </tr>
                    <tr>
                        <th>Payment Record Status</th>
                        <td><span class="pill <?php echo htmlspecialchars($paymentRecordStatusClass); ?>"><?php echo htmlspecialchars($paymentRecordStatusText !== '' ? $paymentRecordStatusText : '-'); ?></span></td>
                    </tr>
                    <tr>
                        <th>Payment Remark</th>
                        <td><?php echo nl2br(htmlspecialchars((string)($registration['remarks'] ?? '-'))); ?></td>
                    </tr>
                    <tr>
                        <th>Payment Proof</th>
                        <td>
                            <?php if (!empty($registration['screenshot'])): ?>
                                <a class="inline-link" href="../../<?php echo htmlspecialchars(ltrim((string)$registration['screenshot'], '/')); ?>" target="_blank">View Uploaded Proof</a>
                            <?php else: ?>
                                <span class="text-muted">Not available</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Check-In Status</th>
                        <td><span class="pill <?php echo htmlspecialchars($checkinStatusClass); ?>"><?php echo htmlspecialchars($checkinStatusText); ?></span></td>
                    </tr>
                    <tr>
                        <th>Check-In Time</th>
                        <td><?php echo htmlspecialchars($checkinTimeText !== '' ? $checkinTimeText : '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Checked-In By</th>
                        <td><?php echo !empty($registration['checkin_by_user_name']) ? htmlspecialchars((string)$registration['checkin_by_user_name']) : '-'; ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <?php if ($canCollectRemaining): ?>
        <section class="surface panel" style="background:#fffdf6;border-color:#f2debb;">
            <h3>Collect Remaining Payment</h3>
            <p class="help"><?php echo $isMainAdminActor
                ? 'Main admin collections are auto-approved and marked Auto Verified immediately.'
                : 'This submits remaining payment details for verification and sets registration status to Pending Verification.'; ?></p>
            <form method="post" enctype="multipart/form-data" autocomplete="off" class="form-grid">
                <input type="hidden" name="registration_id" value="<?php echo (int)$registration['id']; ?>">
                <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnUrl); ?>">
                <input type="hidden" name="collect_remaining_payment" value="1">

                <div class="form-group">
                    <label>Remaining Amount</label>
                    <input class="input-readonly" type="text" value="Rs <?php echo number_format($remainingAmount, 0, '.', ''); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="remaining_payment_method" required>
                        <option value="">Select Method</option>
                        <option value="upi">UPI</option>
                        <option value="cash">Cash</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Transaction / Receipt ID</label>
                    <input type="text" name="remaining_transaction_id" placeholder="UPI txn id or cash receipt no">
                </div>
                <div class="form-group">
                    <label>Payment Proof (Image)</label>
                    <input type="file" name="remaining_payment_proof" accept=".jpg,.jpeg,.png,.webp" required>
                </div>
                <div class="form-group full">
                    <label>Remark</label>
                    <textarea name="remaining_payment_remark" placeholder="Add collection details / remark" required></textarea>
                </div>
                <div class="form-group full">
                    <button type="submit" class="btn btn-success" onclick="return confirm('<?php echo $isMainAdminActor ? 'Collect and auto-verify remaining payment for this booking?' : 'Collect remaining payment for this booking?'; ?>');"><?php echo $isMainAdminActor ? 'Collect & Auto Verify' : 'Collect Remaining Payment'; ?></button>
                </div>
            </form>
        </section>
    <?php elseif ($paymentStatusLower === 'pending verification' || $verificationStatusLower === 'pending'): ?>
        <div class="pending-note">Remaining payment/payment details are already pending verification.</div>
    <?php endif; ?>

    <section class="surface panel">
        <h3>Admin Cancellation & Refund Control</h3>
        <?php if ($pendingCancelRequest !== null): ?>
            <div class="pending-note" style="margin-bottom:10px;">
                Cancellation request #<?php echo (int)($pendingCancelRequest['id'] ?? 0); ?> is pending approval.
            </div>
            <form method="post" class="form-grid" autocomplete="off">
                <input type="hidden" name="registration_id" value="<?php echo (int)$registration['id']; ?>">
                <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnUrl); ?>">
                <input type="hidden" name="review_cancel_request" value="1">
                <input type="hidden" name="request_id" value="<?php echo (int)($pendingCancelRequest['id'] ?? 0); ?>">
                <div class="form-group">
                    <label>Requested Persons</label>
                    <input class="input-readonly" type="text" value="<?php echo (int)($pendingCancelRequest['requested_persons'] ?? 0); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Request Type</label>
                    <input class="input-readonly" type="text" value="<?php echo htmlspecialchars(ucfirst((string)($pendingCancelRequest['request_type'] ?? 'full'))); ?>" readonly>
                </div>
                <div class="form-group full">
                    <label>Request Reason</label>
                    <textarea class="input-readonly" readonly><?php echo htmlspecialchars((string)($pendingCancelRequest['cancel_reason'] ?? '-')); ?></textarea>
                </div>
                <div class="form-group full">
                    <label>Decision Note (Optional)</label>
                    <textarea name="decision_note" placeholder="Add approval/rejection note"></textarea>
                </div>
                <div class="form-group full" style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="submit" name="request_action" value="approve" class="btn btn-success" onclick="return confirm('Approve this cancellation request? Booking will be cancelled and refund marked processed.');">Approve Request</button>
                    <button type="submit" name="request_action" value="reject" class="btn btn-danger" onclick="return confirm('Reject this cancellation request?');">Reject Request</button>
                </div>
            </form>
        <?php elseif ($viewCanCancel): ?>
            <p class="help">Admin cancellation is final. Refund status will be automatically marked as processed.</p>
            <form method="post" class="form-grid" autocomplete="off" onsubmit="return confirm('Cancel this booking from admin panel?');">
                <input type="hidden" name="registration_id" value="<?php echo (int)$registration['id']; ?>">
                <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnUrl); ?>">
                <input type="hidden" name="admin_cancel_booking" value="1">

                <div class="form-group">
                    <label>Cancel Persons</label>
                    <input type="number" name="cancel_persons" min="1" max="<?php echo $viewMaxCancelablePersons; ?>" value="<?php echo $viewMaxCancelablePersons; ?>" required>
                    <span class="small-note">Set lower value for partial cancellation.</span>
                </div>
                <div class="form-group full">
                    <label>Cancellation Reason</label>
                    <textarea name="cancel_reason" placeholder="Reason for admin cancellation (optional)"></textarea>
                </div>
                <div class="form-group full">
                    <button type="submit" class="btn btn-danger">Cancel Booking (Refund Processed)</button>
                </div>
            </form>
        <?php else: ?>
            <p class="small-note">This booking cannot be cancelled (already cancelled or checked in).</p>
        <?php endif; ?>
    </section>

    <?php if (!empty($cancelHistory)): ?>
        <section class="surface panel">
            <h3>Cancellation & Refund History</h3>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Cancelled Persons</th>
                            <th>Reason</th>
                            <th>Refund Amount</th>
                            <th>Refund Status</th>
                            <th>Cancelled At</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cancelHistory as $cancelRow): ?>
                        <tr>
                            <td><?php echo (int)($cancelRow['id'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars((string)($cancelRow['cancellation_type'] ?? 'full')); ?></td>
                            <td><?php echo (int)($cancelRow['cancelled_persons'] ?? 0); ?></td>
                            <td><?php echo nl2br(htmlspecialchars((string)($cancelRow['cancel_reason'] ?? '-'))); ?></td>
                            <td>Rs <?php echo number_format((float)($cancelRow['refund_amount'] ?? 0), 0, '.', ''); ?></td>
                            <td><?php echo htmlspecialchars((string)($cancelRow['refund_status'] ?? 'pending')); ?></td>
                            <td><?php echo htmlspecialchars((string)($cancelRow['cancelled_at'] ?? '-')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($cancelRequestHistory)): ?>
        <section class="surface panel">
            <h3>Cancellation Request History</h3>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Requested Persons</th>
                            <th>Type</th>
                            <th>Reason</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Requested At</th>
                            <th>Decided At</th>
                            <th>Decision Note</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cancelRequestHistory as $requestRow): ?>
                        <tr>
                            <td><?php echo (int)($requestRow['id'] ?? 0); ?></td>
                            <td><?php echo (int)($requestRow['requested_persons'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars((string)($requestRow['request_type'] ?? 'full')); ?></td>
                            <td><?php echo nl2br(htmlspecialchars((string)($requestRow['cancel_reason'] ?? '-'))); ?></td>
                            <td><?php echo htmlspecialchars((string)($requestRow['request_source'] ?? 'online')); ?></td>
                            <td><?php echo htmlspecialchars((string)($requestRow['request_status'] ?? 'pending')); ?></td>
                            <td><?php echo htmlspecialchars((string)($requestRow['requested_at'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars((string)($requestRow['decided_at'] ?? '-')); ?></td>
                            <td><?php echo nl2br(htmlspecialchars((string)($requestRow['decision_note'] ?? '-'))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($visibleRegistrationFields)): ?>
        <section class="surface panel">
            <h3>Dynamic Registration Form Data</h3>
            <div class="table-wrap">
                <table class="list-table">
                    <thead>
                        <tr><th>Field</th><th>Value</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visibleRegistrationFields as $field): ?>
                            <?php
                            $fieldName = (string)($field['field_name'] ?? '');
                            $fieldValue = (string)($field['value'] ?? '');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fieldName); ?></td>
                                <td>
                                    <?php if ($fieldValue !== '' && preg_match('/^uploads\//', $fieldValue)): ?>
                                        <a class="inline-link" href="../../<?php echo htmlspecialchars(ltrim($fieldValue, '/')); ?>" target="_blank">View File</a>
                                    <?php else: ?>
                                        <?php echo $fieldValue !== '' ? nl2br(htmlspecialchars($fieldValue)) : '<span class="text-muted">-</span>'; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>

<script>
function printRegistrationTicket(registrationId) {
    var rid = parseInt(registrationId, 10);
    if (!rid || rid <= 0) {
        return;
    }

    var frameId = 'event-admin-print-frame';
    var existing = document.getElementById(frameId);
    if (existing && existing.parentNode) {
        existing.parentNode.removeChild(existing);
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
    iframe.src = '../../event-booking-confirmation.php?registration_id=' + encodeURIComponent(String(rid)) + '&auto_print=1';
    document.body.appendChild(iframe);

    window.setTimeout(function () {
        var stale = document.getElementById(frameId);
        if (stale && stale.parentNode) {
            stale.parentNode.removeChild(stale);
        }
    }, 90000);
}
</script>
<script src="../includes/responsive-tables.js"></script>
</body>
</html>
