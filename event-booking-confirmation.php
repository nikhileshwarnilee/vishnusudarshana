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

$buildBookingTimeline = static function (array $ctx): array {
    $createdAt = trim((string)($ctx['created_at'] ?? ''));
    $paymentStatus = strtolower(trim((string)($ctx['payment_status'] ?? '')));
    $verificationStatus = strtolower(trim((string)($ctx['verification_status'] ?? '')));
    $isCancelled = !empty($ctx['is_cancelled']);
    $isPaid = !empty($ctx['is_paid']);
    $isPartialPaid = !empty($ctx['is_partial_paid']);
    $isPendingManualApproval = !empty($ctx['is_pending_manual_approval']);
    $isRejectedByAdmin = !empty($ctx['is_rejected_by_admin']);
    $hasPendingCancelRequest = !empty($ctx['has_pending_cancel_request']);
    $checkinStatus = (int)($ctx['checkin_status'] ?? 0);
    $remainingAmount = round(max((float)($ctx['remaining_amount'] ?? 0), 0), 2);
    $totalAmount = round(max((float)($ctx['total_amount'] ?? 0), 0), 2);
    $isFreeBooking = ($totalAmount <= 0.0001);
    $showMakePaymentButton = !empty($ctx['show_make_payment_button']);
    $showRemainingPaymentButton = !empty($ctx['show_remaining_payment_button']);
    $canCancel = !empty($ctx['can_cancel']);
    $isCancellationAllowed = !empty($ctx['is_cancellation_allowed']);

    $cancelLatest = (isset($ctx['cancel_latest']) && is_array($ctx['cancel_latest'])) ? $ctx['cancel_latest'] : null;
    $cancelRequestLatest = (isset($ctx['cancel_request_latest']) && is_array($ctx['cancel_request_latest'])) ? $ctx['cancel_request_latest'] : null;
    $cancelHistory = isset($ctx['cancel_history']) && is_array($ctx['cancel_history']) ? $ctx['cancel_history'] : [];
    $cancelRequestHistory = isset($ctx['cancel_request_history']) && is_array($ctx['cancel_request_history']) ? $ctx['cancel_request_history'] : [];
    $personsText = static function (int $count): string {
        return ($count <= 1) ? 'single person' : ($count . ' persons');
    };

    $coreItems = [];

    $coreItems[] = [
        'title' => 'Booking Created',
        'detail' => 'Booking was created successfully and linked to your booking reference.',
        'state' => 'done',
        'time' => $createdAt,
    ];

    $paymentStepState = 'pending';
    $paymentStepDetail = 'Payment is still pending for this booking.';
    if ($isCancelled) {
        $paymentStepState = 'done';
        $paymentStepDetail = 'Payment actions are closed because this booking is cancelled.';
    } elseif ($isFreeBooking) {
        $paymentStepState = 'done';
        $paymentStepDetail = 'This is a free booking. No payment is required.';
    } elseif ($isPaid && !$isPendingManualApproval && !$isRejectedByAdmin) {
        $paymentStepState = 'done';
        $paymentStepDetail = 'Full payment is completed.';
    } elseif ($isPartialPaid && $remainingAmount > 0) {
        $paymentStepState = 'current';
        $paymentStepDetail = 'Partial payment received. Remaining amount: Rs ' . number_format($remainingAmount, 2, '.', '') . '.';
    } elseif ($isPendingManualApproval && !$isRejectedByAdmin) {
        $paymentStepState = 'current';
        $paymentStepDetail = 'Payment submitted and waiting for admin verification.';
    } elseif ($isRejectedByAdmin || in_array($paymentStatus, ['failed', 'rejected'], true)) {
        $paymentStepState = 'blocked';
        $paymentStepDetail = 'Last payment attempt was rejected/failed. Please pay again.';
    }
    $coreItems[] = [
        'title' => 'Payment Progress',
        'detail' => $paymentStepDetail,
        'state' => $paymentStepState,
        'time' => '',
    ];

    $verificationStepState = 'pending';
    $verificationStepDetail = 'Verification is pending.';
    if ($isCancelled && $verificationStatus === 'cancelled') {
        $verificationStepState = 'done';
        $verificationStepDetail = 'Verification closed after cancellation.';
    } elseif (in_array($verificationStatus, ['approved', 'auto verified'], true)) {
        $verificationStepState = 'done';
        $verificationStepDetail = 'Payment verification is approved.';
    } elseif (in_array($verificationStatus, ['pending', 'pending verification'], true) || $isPendingManualApproval) {
        $verificationStepState = 'current';
        $verificationStepDetail = 'Admin verification is in progress.';
    } elseif (in_array($verificationStatus, ['rejected'], true) || $isRejectedByAdmin) {
        $verificationStepState = 'blocked';
        $verificationStepDetail = 'Verification was rejected. New payment is required.';
    }
    $coreItems[] = [
        'title' => 'Verification Status',
        'detail' => $verificationStepDetail,
        'state' => $verificationStepState,
        'time' => '',
    ];

    $cancelStepState = 'pending';
    $cancelStepDetail = 'No cancellation request has been raised.';
    if ($isCancelled) {
        if ($cancelLatest) {
            $refundStatus = strtolower(trim((string)($cancelLatest['refund_status'] ?? 'pending')));
            $refundAmount = (float)($cancelLatest['refund_amount'] ?? 0);
            $cancelType = ucfirst((string)($cancelLatest['cancellation_type'] ?? 'full'));
            $cancelPersons = (int)($cancelLatest['cancelled_persons'] ?? 0);
            $cancelStepState = ($refundStatus === 'pending') ? 'current' : (($refundStatus === 'rejected') ? 'info' : 'done');
            $cancelStepDetail = $cancelType . ' cancellation processed for ' . $personsText($cancelPersons) . '. Refund ' . ucfirst($refundStatus) . ' (Rs ' . number_format($refundAmount, 2, '.', '') . ').';
        } else {
            $cancelStepState = 'done';
            $cancelStepDetail = 'Booking is marked as cancelled.';
        }
    } elseif ($hasPendingCancelRequest) {
        $requestedPersons = (int)($cancelRequestLatest['requested_persons'] ?? 0);
        $requestType = ucfirst((string)($cancelRequestLatest['request_type'] ?? 'full'));
        $cancelStepState = 'current';
        $cancelStepDetail = $requestType . ' cancellation request for ' . $personsText($requestedPersons) . ' is pending admin approval.';
    } elseif ($cancelRequestLatest && strtolower(trim((string)($cancelRequestLatest['request_status'] ?? ''))) === 'rejected') {
        $cancelStepState = 'info';
        $cancelStepDetail = 'Last cancellation request was rejected. Booking is still active.';
    } elseif (!$isCancellationAllowed) {
        $cancelStepState = 'info';
        $cancelStepDetail = 'Cancellation is not allowed for this package.';
    } elseif ($canCancel) {
        $cancelStepState = 'pending';
        $cancelStepDetail = 'You can request full or partial cancellation from available actions.';
    }
    $coreItems[] = [
        'title' => 'Cancellation Workflow',
        'detail' => $cancelStepDetail,
        'state' => $cancelStepState,
        'time' => '',
    ];

    $eligibilityState = 'pending';
    $eligibilityText = 'Booking is not yet eligible for event participation.';
    $nextAction = 'Complete pending booking steps.';

    if ($isCancelled) {
        $eligibilityState = 'blocked';
        $eligibilityText = 'Not eligible. Booking is cancelled.';
        $nextAction = 'Track refund/cancellation updates in this booking.';
    } elseif ($checkinStatus === 1) {
        $eligibilityState = 'done';
        $eligibilityText = 'Eligible and checked in successfully.';
        $nextAction = 'No payment action pending. Keep booking reference for support.';
    } elseif ($hasPendingCancelRequest) {
        $eligibilityState = 'pending';
        $eligibilityText = 'On hold until cancellation request is reviewed.';
        $nextAction = 'Wait for admin decision on cancellation request.';
    } elseif ($isFreeBooking) {
        $eligibilityState = 'done';
        $eligibilityText = 'Eligible. Free booking is confirmed.';
        $nextAction = 'Carry booking reference/QR at entry.';
    } elseif ($isPaid && !$isPendingManualApproval && !$isRejectedByAdmin) {
        $eligibilityState = 'done';
        $eligibilityText = 'Eligible. Payment is complete and verified.';
        $nextAction = 'Carry booking reference/QR at entry.';
    } elseif ($isPartialPaid && $remainingAmount > 0) {
        $eligibilityState = 'pending';
        $eligibilityText = 'Remaining payment is pending, so participation is not fully confirmed.';
        $nextAction = 'Pay remaining amount: Rs ' . number_format($remainingAmount, 2, '.', '') . '.';
    } elseif ($isPendingManualApproval && !$isRejectedByAdmin) {
        $eligibilityState = 'pending';
        $eligibilityText = 'Participation is pending admin verification of submitted payment.';
        $nextAction = 'Wait for verification. No duplicate payment is needed right now.';
    } elseif ($isRejectedByAdmin || in_array($paymentStatus, ['failed', 'rejected'], true)) {
        $eligibilityState = 'blocked';
        $eligibilityText = 'Not eligible until payment is resubmitted and verified.';
        $nextAction = 'Submit payment again from available actions.';
    } elseif ($showRemainingPaymentButton) {
        $eligibilityState = 'pending';
        $eligibilityText = 'Remaining amount is pending.';
        $nextAction = 'Complete remaining payment from the button below.';
    } elseif ($showMakePaymentButton) {
        $eligibilityState = 'pending';
        $eligibilityText = 'Initial payment is pending.';
        $nextAction = 'Complete payment from the button below.';
    } elseif ($canCancel) {
        $nextAction = 'If your plan changed, you can request full or partial cancellation.';
    }

    $coreItems[] = [
        'title' => 'Participation Eligibility',
        'detail' => $eligibilityText,
        'state' => $eligibilityState,
        'time' => '',
    ];

    $activityItems = [];
    foreach ($cancelRequestHistory as $requestRow) {
        $requestStatus = strtolower(trim((string)($requestRow['request_status'] ?? 'pending')));
        $requestType = ucfirst((string)($requestRow['request_type'] ?? 'full'));
        $requestedPersons = (int)($requestRow['requested_persons'] ?? 0);
        $requestState = 'info';
        if ($requestStatus === 'pending') {
            $requestState = 'current';
        } elseif ($requestStatus === 'approved') {
            $requestState = 'done';
        } elseif ($requestStatus === 'rejected') {
            $requestState = 'blocked';
        }
        $requestDetail = $requestType . ' cancellation request for ' . $personsText($requestedPersons) . '. Status: ' . ucfirst($requestStatus) . '.';
        $decisionNote = trim((string)($requestRow['decision_note'] ?? ''));
        if ($decisionNote !== '' && $requestStatus !== 'pending') {
            $requestDetail .= ' Note: ' . $decisionNote;
        }
        $activityItems[] = [
            'title' => 'Cancellation Request Submitted',
            'detail' => $requestDetail,
            'state' => $requestState,
            'time' => trim((string)($requestRow['requested_at'] ?? '')),
        ];
    }

    foreach ($cancelHistory as $cancelRow) {
        $cancelType = ucfirst((string)($cancelRow['cancellation_type'] ?? 'full'));
        $cancelPersons = (int)($cancelRow['cancelled_persons'] ?? 0);
        $refundAmount = (float)($cancelRow['refund_amount'] ?? 0);
        $refundStatus = strtolower(trim((string)($cancelRow['refund_status'] ?? 'pending')));
        $cancelState = 'info';
        if ($refundStatus === 'pending') {
            $cancelState = 'current';
        } elseif ($refundStatus === 'processed') {
            $cancelState = 'done';
        } elseif ($refundStatus === 'rejected') {
            $cancelState = 'blocked';
        }
        $activityItems[] = [
            'title' => 'Cancellation Processed',
            'detail' => $cancelType . ' cancellation for ' . $personsText($cancelPersons) . '. Refund ' . ucfirst($refundStatus) . ' (Rs ' . number_format($refundAmount, 2, '.', '') . ').',
            'state' => $cancelState,
            'time' => trim((string)($cancelRow['cancelled_at'] ?? '')),
        ];
    }

    usort($activityItems, static function (array $a, array $b): int {
        $tsA = strtotime((string)($a['time'] ?? ''));
        $tsB = strtotime((string)($b['time'] ?? ''));
        if ($tsA === false) {
            $tsA = 0;
        }
        if ($tsB === false) {
            $tsB = 0;
        }
        if ($tsA === $tsB) {
            return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        }
        return $tsA <=> $tsB;
    });

    $completedCoreSteps = 1;
    if ($paymentStepState === 'done') {
        $completedCoreSteps++;
    }
    if ($verificationStepState === 'done') {
        $completedCoreSteps++;
    }
    if ($eligibilityState === 'done') {
        $completedCoreSteps++;
    }
    $progressPercent = (int)round(($completedCoreSteps / 4) * 100);
    if ($isCancelled) {
        $progressPercent = 100;
    }

    $progressLabel = 'In Progress';
    if ($checkinStatus === 1) {
        $progressLabel = 'Checked In';
    } elseif ($isCancelled) {
        $progressLabel = 'Booking Cancelled';
    } elseif ($eligibilityState === 'done') {
        $progressLabel = 'Eligible For Event';
    } elseif ($hasPendingCancelRequest) {
        $progressLabel = 'Cancellation Request Pending';
    } elseif ($isPendingManualApproval) {
        $progressLabel = 'Verification Pending';
    } elseif ($showRemainingPaymentButton) {
        $progressLabel = 'Remaining Payment Pending';
    } elseif ($showMakePaymentButton) {
        $progressLabel = 'Payment Pending';
    }

    return [
        'core_items' => $coreItems,
        'activity_items' => $activityItems,
        'progress_percent' => $progressPercent,
        'progress_label' => $progressLabel,
        'eligibility_state' => $eligibilityState,
        'eligibility_text' => $eligibilityText,
        'next_action' => $nextAction,
    ];
};

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
$cancelHistoryStmt = $pdo->prepare("SELECT * FROM event_cancellations WHERE registration_id = ? ORDER BY cancelled_at ASC, id ASC");
$cancelHistoryStmt->execute([$registrationId]);
$cancelHistory = $cancelHistoryStmt->fetchAll(PDO::FETCH_ASSOC);
$cancelRequestInfoStmt = $pdo->prepare("SELECT * FROM event_cancellation_requests WHERE registration_id = ? ORDER BY id DESC LIMIT 1");
$cancelRequestInfoStmt->execute([$registrationId]);
$cancelRequestInfo = $cancelRequestInfoStmt->fetch(PDO::FETCH_ASSOC);
$cancelRequestHistoryStmt = $pdo->prepare("SELECT * FROM event_cancellation_requests WHERE registration_id = ? ORDER BY requested_at ASC, id ASC");
$cancelRequestHistoryStmt->execute([$registrationId]);
$cancelRequestHistory = $cancelRequestHistoryStmt->fetchAll(PDO::FETCH_ASSOC);
$registrationDataStmt = $pdo->prepare("SELECT field_name, value FROM event_registration_data WHERE registration_id = ? ORDER BY id ASC");
$registrationDataStmt->execute([$registrationId]);
$registrationDataRows = $registrationDataStmt->fetchAll(PDO::FETCH_ASSOC);
$registrationDataRows = array_values(array_filter($registrationDataRows, static function (array $item): bool {
    $name = (string)($item['field_name'] ?? '');
    return strpos($name, '__event_reminder_') !== 0;
}));
$extraRegistrationDataRows = array_values(array_filter($registrationDataRows, static function (array $item): bool {
    $field = strtolower(trim((string)($item['field_name'] ?? '')));
    return !in_array($field, ['name', 'phone', 'persons', 'booking reference', 'selected event date'], true);
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

$submittedManualAmount = round(max((float)($row['payment_amount'] ?? 0), 0), 2);
$displayAmountPaid = round(max($amountPaid, 0), 2);
if ($displayAmountPaid > $totalAmount && $totalAmount > 0) {
    $displayAmountPaid = $totalAmount;
}
$displayRemainingAmount = round(max((float)$remainingAmount, 0), 2);
$paymentAmountNote = '';
$paymentAmountNoteType = '';

if (!$isCancelled && $isPendingManualApproval && !$isRejectedByAdmin && $submittedManualAmount > 0) {
    $displayAmountPaid = round(min($totalAmount, $amountPaid + $submittedManualAmount), 2);
    $displayRemainingAmount = round(max($totalAmount - $displayAmountPaid, 0), 2);
    $paymentAmountNoteType = 'pending';
    $paymentAmountNote = 'Payment submission of Rs. '
        . number_format($submittedManualAmount, 0, '.', '')
        . ' is pending verification and will be treated as confirmed paid only after approval.';
} elseif (!$isCancelled && $isManualOrCash && $isRejectedByAdmin) {
    $displayAmountPaid = round(max($amountPaid, 0), 2);
    if ($displayAmountPaid > $totalAmount && $totalAmount > 0) {
        $displayAmountPaid = $totalAmount;
    }
    $displayRemainingAmount = round(max($totalAmount - $displayAmountPaid, 0), 2);
    $paymentAmountNoteType = 'failed';
    if ($displayAmountPaid <= 0) {
        $paymentAmountNote = 'Previous payment verification was rejected. Paid amount is Rs. 0 now. Please pay again.';
    } else {
        $paymentAmountNote = 'Previous payment verification was rejected. Only verified amounts are counted as paid.';
    }
}

$verifiedPaidAmount = round(max($amountPaid, 0), 2);
if ($verifiedPaidAmount > $totalAmount && $totalAmount > 0) {
    $verifiedPaidAmount = $totalAmount;
}
$pendingSubmittedAmount = (!$isCancelled && $isPendingManualApproval && !$isRejectedByAdmin && $submittedManualAmount > 0)
    ? $submittedManualAmount
    : 0.0;
$rejectedSubmittedAmount = (!$isCancelled && $isRejectedByAdmin && $submittedManualAmount > 0)
    ? $submittedManualAmount
    : 0.0;
$isApprovedByVerification = (
    in_array($verificationStatus, ['approved', 'auto verified'], true) ||
    in_array($paymentRecordStatus, ['approved', 'paid', 'success', 'successful'], true) ||
    in_array($paymentStatus, ['paid', 'partial paid'], true)
);

$paymentHighlightState = 'neutral';
$paymentHighlightIcon = '&#9432;';
$paymentHighlightTitle = 'Awaiting Payment Verification';
$paymentHighlightMessage = 'Payment is not yet verified for this booking.';
if ($isCancelled) {
    $paymentHighlightState = 'neutral';
    $paymentHighlightIcon = '&#9888;';
    $paymentHighlightTitle = 'Booking Cancelled';
    $paymentHighlightMessage = 'Payment updates are locked for cancelled bookings.';
} elseif ($isPendingManualApproval && !$isRejectedByAdmin) {
    $paymentHighlightState = 'pending';
    $paymentHighlightIcon = '&#9203;';
    $paymentHighlightTitle = 'Under Verification';
    $paymentHighlightMessage = 'Payment details are submitted and waiting for admin verification.';
} elseif ($isRejectedByAdmin) {
    $paymentHighlightState = 'rejected';
    $paymentHighlightIcon = '&#10006;';
    $paymentHighlightTitle = 'Verification Rejected';
    $paymentHighlightMessage = 'Last submitted payment was rejected. Please submit a new payment.';
} elseif ($isApprovedByVerification) {
    $paymentHighlightState = 'approved';
    $paymentHighlightIcon = '&#10004;';
    $paymentHighlightTitle = 'Payment Verified';
    $paymentHighlightMessage = 'Payment is verified and counted as confirmed paid amount.';
}

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

$bookingTimeline = $buildBookingTimeline([
    'created_at' => (string)($row['created_at'] ?? ''),
    'payment_status' => (string)($row['payment_status'] ?? ''),
    'verification_status' => (string)($row['verification_status'] ?? ''),
    'is_cancelled' => $isCancelled,
    'is_paid' => $isPaid,
    'is_partial_paid' => $isPartialPaid,
    'is_pending_manual_approval' => $isPendingManualApproval,
    'is_rejected_by_admin' => $isRejectedByAdmin,
    'has_pending_cancel_request' => $hasPendingCancelRequest,
    'checkin_status' => (int)($row['checkin_status'] ?? 0),
    'remaining_amount' => $displayRemainingAmount,
    'total_amount' => $totalAmount,
    'show_make_payment_button' => $showMakePaymentButton,
    'show_remaining_payment_button' => $showRemainingPaymentButton,
    'can_cancel' => $canCancel,
    'is_cancellation_allowed' => $isCancellationAllowed,
    'cancel_latest' => $cancelInfo,
    'cancel_request_latest' => $cancelRequestInfo,
    'cancel_history' => $cancelHistory,
    'cancel_request_history' => $cancelRequestHistory,
]);
?>
<main class="event-confirm-main" style="background-color:var(--cream-bg);">
    <section class="event-confirm-wrap">
        <div class="card hero-card status-<?php echo $statusClass; ?>">
            <div class="hero-top">
                <h1><?php echo htmlspecialchars($messageTitle); ?></h1>
                <div class="hero-top-actions">
                    <span class="reference-badge">Booking Reference: <?php echo htmlspecialchars((string)($row['booking_reference'] ?? '')); ?></span>
                    <a class="btn-main btn-track-top" href="event-track.php">Track Booking</a>
                    <button type="button" class="btn-main btn-print btn-print-top" onclick="printBookingSheet();">Print</button>
                </div>
            </div>
            <p><?php echo htmlspecialchars($messageBody); ?></p>
            <p class="hero-note">Keep this confirmation and print a copy for entry and support.</p>
            <div class="track-update-note">
                <strong>Current information is shown on this page.</strong>
                <span>If you want latest updates on this booking (payment verification, status changes, cancellation/refund progress), use Track Booking.</span>
                <a class="btn-main btn-track-inline" href="event-track.php">Track Latest Updates</a>
            </div>
            <?php if (!empty($cancelError)): ?>
                <p class="error-inline"><?php echo htmlspecialchars((string)$cancelError); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($qrTicketUrl !== ''): ?>
            <div class="card qr-card">
                <h2>Entry QR Ticket</h2>
                <p>Show this QR ticket at event entry.</p>
                <a href="<?php echo htmlspecialchars($qrTicketUrl); ?>" target="_blank">
                    <img src="<?php echo htmlspecialchars($qrTicketUrl); ?>" alt="Entry QR Ticket">
                </a>
            </div>
        <?php endif; ?>

        <div class="card timeline-card">
            <div class="timeline-head">
                <h2>Booking Timeline</h2>
                <span class="timeline-stage"><?php echo htmlspecialchars((string)($bookingTimeline['progress_label'] ?? 'In Progress')); ?></span>
            </div>
            <div class="timeline-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int)($bookingTimeline['progress_percent'] ?? 0); ?>">
                <span style="width:<?php echo (int)($bookingTimeline['progress_percent'] ?? 0); ?>%"></span>
            </div>
            <div class="timeline-eligibility timeline-eligibility-<?php echo htmlspecialchars((string)($bookingTimeline['eligibility_state'] ?? 'pending')); ?>">
                <strong>Participation Status: <?php echo htmlspecialchars((string)($bookingTimeline['eligibility_text'] ?? 'Status unavailable.')); ?></strong>
                <span>Next Action: <?php echo htmlspecialchars((string)($bookingTimeline['next_action'] ?? '')); ?></span>
            </div>
            <ol class="timeline-list">
                <?php foreach (($bookingTimeline['core_items'] ?? []) as $timelineItem): ?>
                    <li class="timeline-item timeline-state-<?php echo htmlspecialchars((string)($timelineItem['state'] ?? 'pending')); ?>">
                        <span class="timeline-dot" aria-hidden="true"></span>
                        <div class="timeline-content">
                            <div class="timeline-row">
                                <strong><?php echo htmlspecialchars((string)($timelineItem['title'] ?? 'Step')); ?></strong>
                                <?php if (!empty($timelineItem['time'])): ?>
                                    <span class="timeline-time"><?php echo htmlspecialchars((string)$timelineItem['time']); ?></span>
                                <?php endif; ?>
                            </div>
                            <p><?php echo htmlspecialchars((string)($timelineItem['detail'] ?? '')); ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php if (!empty($bookingTimeline['activity_items'])): ?>
                <h3 class="timeline-subtitle">Actions Taken</h3>
                <ol class="timeline-list timeline-list-compact">
                    <?php foreach (($bookingTimeline['activity_items'] ?? []) as $activityItem): ?>
                        <li class="timeline-item timeline-state-<?php echo htmlspecialchars((string)($activityItem['state'] ?? 'info')); ?>">
                            <span class="timeline-dot" aria-hidden="true"></span>
                            <div class="timeline-content">
                                <div class="timeline-row">
                                    <strong><?php echo htmlspecialchars((string)($activityItem['title'] ?? 'Activity')); ?></strong>
                                    <?php if (!empty($activityItem['time'])): ?>
                                        <span class="timeline-time"><?php echo htmlspecialchars((string)$activityItem['time']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p><?php echo htmlspecialchars((string)($activityItem['detail'] ?? '')); ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>

        <div class="section-grid">
            <div class="card detail-card">
                <h2>Personal Details</h2>
                <div class="detail-list">
                    <div><span>Name</span><strong><?php echo htmlspecialchars((string)$row['name']); ?></strong></div>
                    <div><span>Phone</span><strong><?php echo htmlspecialchars((string)$row['phone']); ?></strong></div>
                    <div><span>Persons</span><strong><?php echo (int)$row['persons']; ?></strong></div>
                </div>
            </div>
            <div class="card detail-card">
                <h2>Event Details</h2>
                <div class="detail-list">
                    <div><span>Booking Reference</span><strong><?php echo htmlspecialchars((string)($row['booking_reference'] ?? '')); ?></strong></div>
                    <div><span>Event</span><strong><?php echo htmlspecialchars((string)$row['event_title']); ?></strong></div>
                    <div><span>Package</span><strong><?php echo htmlspecialchars((string)$row['package_name']); ?></strong></div>
                    <div><span>Event Date</span><strong><?php echo htmlspecialchars($displayEventDate); ?></strong></div>
                    <div><span>Location</span><strong><?php echo htmlspecialchars((string)$row['location']); ?></strong></div>
                    <div><span>Booked At</span><strong><?php echo htmlspecialchars((string)$row['created_at']); ?></strong></div>
                </div>
            </div>
            <div class="card detail-card">
                <h2>Payment Details</h2>
                <div class="payment-state-banner payment-state-<?php echo htmlspecialchars($paymentHighlightState); ?>">
                    <span class="payment-state-icon" aria-hidden="true"><?php echo $paymentHighlightIcon; ?></span>
                    <div class="payment-state-content">
                        <strong><?php echo htmlspecialchars($paymentHighlightTitle); ?></strong>
                        <span><?php echo htmlspecialchars($paymentHighlightMessage); ?></span>
                    </div>
                </div>
                <div class="detail-list">
                    <div><span>Payment Status</span><strong><?php echo htmlspecialchars((string)$row['payment_status']); ?></strong></div>
                    <div><span>Verification</span><strong><?php echo htmlspecialchars((string)$row['verification_status']); ?></strong></div>
                    <div><span>Method</span><strong><?php echo htmlspecialchars((string)($row['payment_method'] ?: 'N/A')); ?></strong></div>
                    <div><span>Transaction ID</span><strong><?php echo htmlspecialchars((string)($row['transaction_id'] ?: 'N/A')); ?></strong></div>
                </div>
                <div class="amount-highlight-grid">
                    <div class="amount-highlight amount-highlight-total">
                        <span>Total Amount</span>
                        <strong>Rs. <?php echo number_format($totalAmount, 0, '.', ''); ?></strong>
                    </div>
                    <div class="amount-highlight amount-highlight-paid amount-highlight-<?php echo htmlspecialchars($paymentHighlightState); ?>">
                        <span>Paid Amount (Shown)</span>
                        <strong>Rs. <?php echo number_format($displayAmountPaid, 0, '.', ''); ?></strong>
                        <?php if ($pendingSubmittedAmount > 0): ?>
                            <small>Includes pending verification submission</small>
                        <?php endif; ?>
                    </div>
                    <div class="amount-highlight amount-highlight-remaining">
                        <span>Remaining Amount</span>
                        <strong>Rs. <?php echo number_format($displayRemainingAmount, 0, '.', ''); ?></strong>
                    </div>
                    <?php if ($pendingSubmittedAmount > 0): ?>
                        <div class="amount-highlight amount-highlight-confirmed">
                            <span>Confirmed Paid (Verified)</span>
                            <strong>Rs. <?php echo number_format($verifiedPaidAmount, 0, '.', ''); ?></strong>
                        </div>
                        <div class="amount-highlight amount-highlight-pending">
                            <span>Submitted For Verification</span>
                            <strong>Rs. <?php echo number_format($pendingSubmittedAmount, 0, '.', ''); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($rejectedSubmittedAmount > 0): ?>
                        <div class="amount-highlight amount-highlight-rejected">
                            <span>Previous Rejected Submission</span>
                            <strong>Rs. <?php echo number_format($rejectedSubmittedAmount, 0, '.', ''); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($paymentAmountNote !== ''): ?>
                    <p class="payment-note-text payment-note-<?php echo htmlspecialchars($paymentAmountNoteType); ?>">
                        <?php echo htmlspecialchars($paymentAmountNote); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($extraRegistrationDataRows)): ?>
            <div class="card">
                <h2>Additional Form Details</h2>
                <div class="extra-details-table-wrap">
                    <table class="extra-details-table">
                        <tbody>
                            <?php foreach ($extraRegistrationDataRows as $dataRow): ?>
                                <tr>
                                    <th><?php echo htmlspecialchars((string)$dataRow['field_name']); ?></th>
                                    <td>
                                        <?php if (is_string($dataRow['value']) && preg_match('/^uploads\//', (string)$dataRow['value'])): ?>
                                            <a href="<?php echo htmlspecialchars(ltrim((string)$dataRow['value'], '/')); ?>" target="_blank">View Uploaded File</a>
                                        <?php else: ?>
                                            <?php echo nl2br(htmlspecialchars((string)$dataRow['value'])); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($cancelInfo): ?>
            <div class="card note-card">
                <h2>Cancellation Record</h2>
                <div class="detail-list">
                    <div><span>Type</span><strong><?php echo htmlspecialchars((string)($cancelInfo['cancellation_type'] ?? 'full')); ?></strong></div>
                    <div><span>Cancelled Persons</span><strong><?php echo (int)($cancelInfo['cancelled_persons'] ?? 0); ?></strong></div>
                    <div><span>Refund Amount</span><strong>Rs. <?php echo number_format((float)$cancelInfo['refund_amount'], 0, '.', ''); ?></strong></div>
                    <div><span>Refund Status</span><strong><?php echo htmlspecialchars((string)$cancelInfo['refund_status']); ?></strong></div>
                    <div><span>Reason</span><strong><?php echo nl2br(htmlspecialchars((string)$cancelInfo['cancel_reason'])); ?></strong></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($cancelRequestInfo && strtolower((string)($cancelRequestInfo['request_status'] ?? '')) !== 'approved'): ?>
            <div class="card note-card">
                <h2>Cancellation Request</h2>
                <div class="detail-list">
                    <div><span>Type</span><strong><?php echo htmlspecialchars((string)($cancelRequestInfo['request_type'] ?? 'full')); ?></strong></div>
                    <div><span>Requested Persons</span><strong><?php echo (int)($cancelRequestInfo['requested_persons'] ?? 0); ?></strong></div>
                    <div><span>Status</span><strong><?php echo htmlspecialchars((string)($cancelRequestInfo['request_status'] ?? 'pending')); ?></strong></div>
                    <div><span>Requested At</span><strong><?php echo htmlspecialchars((string)($cancelRequestInfo['requested_at'] ?? '-')); ?></strong></div>
                    <?php if (!empty($cancelRequestInfo['decided_at'])): ?>
                        <div><span>Decided At</span><strong><?php echo htmlspecialchars((string)$cancelRequestInfo['decided_at']); ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($cancelRequestInfo['decision_note'])): ?>
                        <div><span>Decision Note</span><strong><?php echo nl2br(htmlspecialchars((string)$cancelRequestInfo['decision_note'])); ?></strong></div>
                    <?php endif; ?>
                    <div><span>Reason</span><strong><?php echo nl2br(htmlspecialchars((string)($cancelRequestInfo['cancel_reason'] ?? '-'))); ?></strong></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card action-card">
            <div class="btn-row">
                <?php if ($showMakePaymentButton): ?>
                    <a class="btn-main" href="event-payment.php?registration_id=<?php echo (int)$row['id']; ?>">Make Payment</a>
                <?php endif; ?>
                <?php if ($showRemainingPaymentButton): ?>
                    <a class="btn-main btn-alt" href="event-remaining-payment.php?booking_reference=<?php echo urlencode((string)$row['booking_reference']); ?>&phone=<?php echo urlencode((string)$row['phone']); ?>">Make Remaining Payment</a>
                <?php endif; ?>
                <?php if ($canCancel): ?>
                    <form method="post" class="cancel-form">
                        <input type="hidden" name="registration_id" value="<?php echo (int)$registrationId; ?>">
                        <input type="hidden" name="action" value="cancel_booking">
                        <input type="hidden" name="cancel_persons" value="<?php echo (int)$row['persons']; ?>">
                        <input type="text" name="cancel_reason" placeholder="Cancellation reason">
                        <button type="submit" class="btn-main btn-danger" onclick="return confirm('Submit cancellation request for this booking?');">Request Cancellation</button>
                    </form>
                <?php elseif ($hasPendingCancelRequest): ?>
                    <span class="status-note">Cancellation request is pending admin approval.</span>
                <?php elseif (!$isCancellationAllowed): ?>
                    <span class="status-note status-note-danger">Cancellation is not allowed for this package.</span>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<section class="booking-print-sheet" id="bookingPrintSheet" aria-hidden="true">
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
        <div class="print-payment-state print-payment-<?php echo htmlspecialchars($paymentHighlightState); ?>">
            <span class="print-payment-state-icon" aria-hidden="true"><?php echo $paymentHighlightIcon; ?></span>
            <div class="print-payment-state-copy">
                <strong><?php echo htmlspecialchars($paymentHighlightTitle); ?></strong>
                <span><?php echo htmlspecialchars($paymentHighlightMessage); ?></span>
            </div>
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
                <tr class="print-amount-row">
                    <th>Total Amount</th>
                    <td class="print-amount-total">Rs. <?php echo number_format($totalAmount, 0, '.', ''); ?></td>
                    <th>Paid Amount</th>
                    <td class="print-amount-paid print-amount-paid-<?php echo htmlspecialchars($paymentHighlightState); ?>">Rs. <?php echo number_format($displayAmountPaid, 0, '.', ''); ?></td>
                </tr>
                <tr class="print-amount-row">
                    <th>Remaining Amount</th>
                    <td class="print-amount-remaining">Rs. <?php echo number_format($displayRemainingAmount, 0, '.', ''); ?></td>
                    <th>Booked At</th>
                    <td><?php echo htmlspecialchars((string)$row['created_at']); ?></td>
                </tr>
                <?php if ($pendingSubmittedAmount > 0): ?>
                    <tr class="print-amount-row">
                        <th>Confirmed Paid (Verified)</th>
                        <td>Rs. <?php echo number_format($verifiedPaidAmount, 0, '.', ''); ?></td>
                        <th>Submitted For Verification</th>
                        <td class="print-amount-pending">Rs. <?php echo number_format($pendingSubmittedAmount, 0, '.', ''); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($rejectedSubmittedAmount > 0): ?>
                    <tr class="print-amount-row">
                        <th>Rejected Submission</th>
                        <td class="print-amount-rejected">Rs. <?php echo number_format($rejectedSubmittedAmount, 0, '.', ''); ?></td>
                        <th>Action</th>
                        <td>Submit payment again</td>
                    </tr>
                <?php endif; ?>
                <?php if ($paymentAmountNote !== ''): ?>
                    <tr><th>Payment Note</th><td colspan="3" class="print-payment-note"><?php echo htmlspecialchars($paymentAmountNote); ?></td></tr>
                <?php endif; ?>
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
.event-confirm-main{min-height:100vh;padding:1.8rem 0 4.6rem}
.event-confirm-wrap{max-width:1060px;margin:0 auto;padding:0 14px}
.card{background:#fff;border:1px solid #ecd8d8;border-radius:16px;box-shadow:0 10px 24px rgba(125,27,20,.08);padding:16px;margin-bottom:14px}
.hero-card{background:linear-gradient(180deg,#fffdf9 0%,#fff 58%)}
.hero-card.status-success{border-color:#b8dfc1;background:linear-gradient(180deg,#f6fff8 0%,#fff 65%)}
.hero-card.status-pending{border-color:#ecd7a9;background:linear-gradient(180deg,#fffdf4 0%,#fff 65%)}
.hero-card.status-failed{border-color:#efc1c1;background:linear-gradient(180deg,#fff5f5 0%,#fff 65%)}
.hero-top{display:flex;gap:12px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap}
.hero-top-actions{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}
h1{margin:0;color:#7d1b14;font-size:1.58rem}
h2{margin:0 0 10px;color:#7d1b14;font-size:1.16rem}
.reference-badge{display:inline-block;background:#f6ebe7;border:1px solid #e2cbc4;border-radius:999px;padding:6px 11px;font-weight:700;font-size:.83rem;color:#5b2d24}
.btn-print-top{padding:7px 12px;font-size:.84rem}
.hero-note{margin:8px 0 0;color:#6d4d3f;font-size:.9rem}
.track-update-note{margin-top:10px;border:1px solid #cfe2eb;background:#f4fbff;border-radius:12px;padding:10px 12px;display:flex;flex-direction:column;gap:6px}
.track-update-note strong{color:#0f5f77;font-size:.9rem}
.track-update-note span{color:#345061;font-size:.86rem;line-height:1.35}
.error-inline{margin-top:8px;color:#b00020;font-weight:700}
.qr-card p{margin:0 0 10px;color:#5a463f}
.qr-card img{width:190px;height:190px;max-width:100%;object-fit:contain;border:1px solid #e8d7d1;border-radius:12px;padding:4px;background:#fff}
.timeline-card{background:#fffdf8}
.timeline-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.timeline-stage{display:inline-block;background:#f7efe8;border:1px solid #e5d0c5;border-radius:999px;padding:5px 10px;font-size:.8rem;font-weight:700;color:#6f2e25}
.timeline-progress{height:8px;border-radius:999px;background:#f0e1db;overflow:hidden;margin:10px 0 12px}
.timeline-progress span{display:block;height:100%;background:linear-gradient(90deg,#7d1b14,#b23a2e);border-radius:999px}
.timeline-eligibility{display:flex;flex-direction:column;gap:3px;border-radius:12px;padding:10px 11px;margin-bottom:10px;border:1px solid #e2d6d6;background:#f8f8fa}
.timeline-eligibility strong{font-size:.9rem;color:#2f2624}
.timeline-eligibility span{font-size:.84rem;color:#4f5865}
.timeline-eligibility-done{background:#eef9f1;border-color:#cde7d4}
.timeline-eligibility-done strong{color:#145f35}
.timeline-eligibility-pending{background:#fff8e8;border-color:#f0ddad}
.timeline-eligibility-pending strong{color:#7b5500}
.timeline-eligibility-blocked{background:#ffeff1;border-color:#f1c2cb}
.timeline-eligibility-blocked strong{color:#992333}
.timeline-list{list-style:none;margin:0;padding:0;position:relative}
.timeline-list::before{content:'';position:absolute;left:8px;top:2px;bottom:2px;width:2px;background:#ecd8d1}
.timeline-item{position:relative;display:flex;gap:10px;padding-left:0;margin-bottom:9px}
.timeline-item:last-child{margin-bottom:0}
.timeline-dot{width:18px;height:18px;border-radius:50%;margin-top:2px;position:relative;z-index:1;border:2px solid #d6c4be;background:#fff}
.timeline-content{flex:1;min-width:0}
.timeline-row{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}
.timeline-row strong{font-size:.9rem;color:#2f2624}
.timeline-time{font-size:.78rem;color:#7f6b64}
.timeline-content p{margin:2px 0 0;font-size:.84rem;color:#5d4a45;line-height:1.35}
.timeline-state-done .timeline-dot{background:#1a8917;border-color:#1a8917}
.timeline-state-done .timeline-row strong{color:#1a6a30}
.timeline-state-current .timeline-dot{background:#b36b00;border-color:#b36b00}
.timeline-state-current .timeline-row strong{color:#8a5f00}
.timeline-state-pending .timeline-dot{background:#fff;border-color:#b6a09a}
.timeline-state-blocked .timeline-dot{background:#b00020;border-color:#b00020}
.timeline-state-blocked .timeline-row strong{color:#9f1f2e}
.timeline-state-info .timeline-dot{background:#0f5f77;border-color:#0f5f77}
.timeline-state-info .timeline-row strong{color:#0f5f77}
.timeline-subtitle{margin:11px 0 7px;color:#7d1b14;font-size:1rem}
.timeline-list-compact .timeline-content p{font-size:.82rem}
.section-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:12px}
.detail-list{display:flex;flex-direction:column;gap:7px}
.detail-list > div{display:flex;justify-content:space-between;gap:10px;border-bottom:1px dashed #efddd7;padding-bottom:6px}
.detail-list > div:last-child{border-bottom:none;padding-bottom:0}
.detail-list span{color:#6d5a54}
.detail-list strong{color:#2f2624;text-align:right}
.payment-state-banner{display:flex;align-items:flex-start;gap:10px;border-radius:12px;padding:10px 12px;margin:0 0 10px;border:1px solid #ddd}
.payment-state-icon{width:26px;height:26px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;font-weight:700;line-height:1}
.payment-state-content{display:flex;flex-direction:column;gap:2px}
.payment-state-content strong{font-size:.94rem;color:#2f2624}
.payment-state-content span{font-size:.84rem;color:#4b5968;line-height:1.35}
.payment-state-pending{background:#fff8e6;border-color:#f1deaf}
.payment-state-pending .payment-state-icon{background:#f7d98d;color:#6d4b00}
.payment-state-approved{background:#edf9f0;border-color:#cde7d4}
.payment-state-approved .payment-state-icon{background:#bde4c8;color:#14653a}
.payment-state-rejected{background:#ffeef0;border-color:#f2c4cc}
.payment-state-rejected .payment-state-icon{background:#f3b3be;color:#992333}
.payment-state-neutral{background:#f4f6fb;border-color:#d8dfeb}
.payment-state-neutral .payment-state-icon{background:#dbe1eb;color:#485566}
.amount-highlight-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;margin-top:10px}
.amount-highlight{border:1px solid #e7d7d7;border-radius:10px;padding:9px 10px;background:#fff8f8}
.amount-highlight span{display:block;font-size:.78rem;color:#695a57;text-transform:uppercase;letter-spacing:.03em;font-weight:700}
.amount-highlight strong{display:block;margin-top:4px;font-size:1.08rem;color:#2f2624;line-height:1.2}
.amount-highlight small{display:block;margin-top:4px;font-size:.74rem;color:#825f00;line-height:1.3}
.amount-highlight-total{background:#f7f9fc;border-color:#dbe2ec}
.amount-highlight-remaining{background:#fdf4f0;border-color:#efd1c8}
.amount-highlight-pending{background:#fff8e5;border-color:#f0ddab}
.amount-highlight-pending strong{color:#825f00}
.amount-highlight-confirmed{background:#eef8f1;border-color:#cde4d5}
.amount-highlight-confirmed strong{color:#1c6a3c}
.amount-highlight-rejected{background:#ffeff1;border-color:#f1c2cb}
.amount-highlight-rejected strong{color:#a12536}
.amount-highlight-paid.amount-highlight-approved{background:#ecf9ef;border-color:#cde8d5}
.amount-highlight-paid.amount-highlight-approved strong{color:#1b6a3b}
.amount-highlight-paid.amount-highlight-pending{background:#fff8e7;border-color:#f2ddad}
.amount-highlight-paid.amount-highlight-pending strong{color:#8a5f00}
.amount-highlight-paid.amount-highlight-rejected{background:#fff0f2;border-color:#f4c4cc}
.amount-highlight-paid.amount-highlight-rejected strong{color:#a12536}
.amount-highlight-paid.amount-highlight-neutral{background:#f5f7fb;border-color:#dde3ed}
.payment-note-text{font-size:.86rem;line-height:1.35;margin:10px 0 0}
.payment-note-pending{color:#8a5f00}
.payment-note-failed{color:#b00020}
.extra-details-table-wrap{border:1px solid #efddd7;border-radius:10px;overflow:hidden;background:#fffdfa}
.extra-details-table{width:100%;border-collapse:collapse;font-size:.93rem}
.extra-details-table th,.extra-details-table td{padding:10px 12px;border-bottom:1px solid #f1e3de;text-align:left;vertical-align:top}
.extra-details-table tr:last-child th,.extra-details-table tr:last-child td{border-bottom:none}
.extra-details-table th{width:34%;background:#fcf3f0;color:#70453a;font-weight:700}
.extra-details-table td{color:#2f2624}
.note-card{background:#fffaf7}
.action-card{background:#fffdf9}
.btn-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.btn-main{display:inline-block;border:none;border-radius:10px;background:#7d1b14;color:#fff;font-weight:700;padding:10px 14px;cursor:pointer;text-decoration:none;transition:all .2s ease}
.btn-main:hover{background:#65150f}
.btn-track-top{background:#0f5f77}
.btn-track-top:hover{background:#0b4a5d}
.btn-track-inline{background:#0f5f77;align-self:flex-start;padding:7px 12px;font-size:.84rem}
.btn-track-inline:hover{background:#0b4a5d}
.btn-print{background:#14532d}
.btn-print:hover{background:#0e4021}
.btn-alt{background:#0f5f77}
.btn-alt:hover{background:#0b4a5d}
.btn-danger{background:#b42318}
.btn-danger:hover{background:#8f1b13}
.cancel-form{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.cancel-form input[type=text]{min-width:240px;border:1px solid #e0c8c0;border-radius:10px;padding:9px 10px;font-size:.94rem}
.status-note{font-size:.88rem;color:#8a5f00;font-weight:700}
.status-note-danger{color:#b00020}
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
.print-payment-state{display:flex;align-items:flex-start;gap:7px;border:1px solid #eadede;border-radius:8px;padding:6px 8px;margin-bottom:8px}
.print-payment-state-icon{width:19px;height:19px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;line-height:1}
.print-payment-state-copy{display:flex;flex-direction:column;gap:1px}
.print-payment-state-copy strong{font-size:.78rem;color:#5b2626}
.print-payment-state-copy span{font-size:.72rem;color:#4f5968}
.print-payment-pending{background:#fff8e8;border-color:#f0ddad}
.print-payment-pending .print-payment-state-icon{background:#f7d98d;color:#6d4b00}
.print-payment-approved{background:#edf9f0;border-color:#cde7d4}
.print-payment-approved .print-payment-state-icon{background:#bde4c8;color:#14653a}
.print-payment-rejected{background:#ffeff1;border-color:#f1c2cb}
.print-payment-rejected .print-payment-state-icon{background:#f3b3be;color:#992333}
.print-payment-neutral{background:#f4f6fb;border-color:#d8dfeb}
.print-payment-neutral .print-payment-state-icon{background:#dbe1eb;color:#485566}
.print-section{border:1px solid #eadede;border-radius:8px;padding:6px 7px;background:#fff;margin-bottom:7px;break-inside:avoid}
.print-section h3{margin:0 0 5px;color:#800000;font-size:.86rem}
.print-qr-section .print-qr-wrap{display:flex;justify-content:center;align-items:center}
.print-qr-page{display:block;width:126px;height:126px;object-fit:contain;border:1px solid #ddd;border-radius:8px;background:#fff;padding:3px}
.print-table{width:100%;border-collapse:collapse;font-size:.74rem}
.print-table th,.print-table td{border:1px solid #f2e2e2;padding:3px 4px;vertical-align:top;text-align:left;line-height:1.25}
.print-table th{background:#fdf4f4;color:#7a3030;width:18%}
.print-amount-total{background:#f7f9fc}
.print-amount-remaining{background:#fdf4f0}
.print-amount-paid{font-weight:700}
.print-amount-paid-approved{background:#edf9f0;color:#1c6a3c}
.print-amount-paid-pending{background:#fff8e6;color:#825f00}
.print-amount-paid-rejected{background:#ffeff1;color:#a12536}
.print-amount-paid-neutral{background:#f4f6fb;color:#485566}
.print-amount-pending{background:#fff8e7;color:#825f00;font-weight:700}
.print-amount-rejected{background:#ffeff1;color:#a12536;font-weight:700}
.print-payment-note{background:#fff8ea}
.print-proof-wrap{margin:4px 0 0;display:flex;align-items:center;gap:7px;font-size:.78rem}
.print-proof-img{display:block;width:120px;max-height:74px;object-fit:contain;border:1px solid #eee;border-radius:6px;background:#fff;padding:2px}
.print-footer{margin-top:8px;border-top:1px dashed #b8b8b8;padding-top:5px;font-size:.72rem;color:#555;text-align:center}
@media (max-width:700px){
    .event-confirm-main{padding-top:1.1rem}
    h1{font-size:1.3rem}
    .card{padding:13px}
    .hero-top-actions{justify-content:flex-start;width:100%}
    .cancel-form input[type=text]{min-width:100%}
}
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
        + '.print-payment-state{display:flex;align-items:flex-start;gap:7px;border:1px solid #eadede;border-radius:8px;padding:6px 8px;margin-bottom:8px;}'
        + '.print-payment-state-icon{width:19px;height:19px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;line-height:1;}'
        + '.print-payment-state-copy{display:flex;flex-direction:column;gap:1px;}'
        + '.print-payment-state-copy strong{font-size:.78rem;color:#5b2626;}'
        + '.print-payment-state-copy span{font-size:.72rem;color:#4f5968;}'
        + '.print-payment-pending{background:#fff8e8;border-color:#f0ddad;}'
        + '.print-payment-pending .print-payment-state-icon{background:#f7d98d;color:#6d4b00;}'
        + '.print-payment-approved{background:#edf9f0;border-color:#cde7d4;}'
        + '.print-payment-approved .print-payment-state-icon{background:#bde4c8;color:#14653a;}'
        + '.print-payment-rejected{background:#ffeff1;border-color:#f1c2cb;}'
        + '.print-payment-rejected .print-payment-state-icon{background:#f3b3be;color:#992333;}'
        + '.print-payment-neutral{background:#f4f6fb;border-color:#d8dfeb;}'
        + '.print-payment-neutral .print-payment-state-icon{background:#dbe1eb;color:#485566;}'
        + '.print-section{border:1px solid #eadede;border-radius:8px;padding:6px 7px;background:#fff;margin-bottom:7px;break-inside:avoid;}'
        + '.print-section h3{margin:0 0 5px;color:#800000;font-size:.86rem;}'
        + '.print-qr-section .print-qr-wrap{display:flex;justify-content:center;align-items:center;}'
        + '.print-qr-page{display:block;width:116px;height:116px;object-fit:contain;border:1px solid #ddd;border-radius:8px;background:#fff;padding:3px;}'
        + '.print-table{width:100%;border-collapse:collapse;font-size:.72rem;}'
        + '.print-table th,.print-table td{border:1px solid #f2e2e2;padding:2px 3px;vertical-align:top;text-align:left;line-height:1.25;}'
        + '.print-table th{background:#fdf4f4;color:#7a3030;width:18%;}'
        + '.print-amount-total{background:#f7f9fc;}'
        + '.print-amount-remaining{background:#fdf4f0;}'
        + '.print-amount-paid{font-weight:700;}'
        + '.print-amount-paid-approved{background:#edf9f0;color:#1c6a3c;}'
        + '.print-amount-paid-pending{background:#fff8e6;color:#825f00;}'
        + '.print-amount-paid-rejected{background:#ffeff1;color:#a12536;}'
        + '.print-amount-paid-neutral{background:#f4f6fb;color:#485566;}'
        + '.print-amount-pending{background:#fff8e7;color:#825f00;font-weight:700;}'
        + '.print-amount-rejected{background:#ffeff1;color:#a12536;font-weight:700;}'
        + '.print-payment-note{background:#fff8ea;}'
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
