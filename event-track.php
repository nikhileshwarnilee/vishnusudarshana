<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/event_module.php';

vs_event_ensure_tables($pdo);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$trackInput = trim((string)($_GET['track_input'] ?? $_POST['track_input'] ?? ''));
$trackToken = trim((string)($_GET['track_token'] ?? $_POST['track_token'] ?? ''));
$message = trim((string)($_GET['msg'] ?? ''));
$messageType = trim((string)($_GET['msg_type'] ?? ''));
$errorMsg = '';

$getTrackAuthContext = static function (string $token, string $input): ?array {
    $token = trim($token);
    $input = strtoupper(trim($input));
    if ($token === '' || $input === '') {
        return null;
    }
    if (!isset($_SESSION['event_track_auth']) || !is_array($_SESSION['event_track_auth'])) {
        return null;
    }
    if (!isset($_SESSION['event_track_auth'][$token]) || !is_array($_SESSION['event_track_auth'][$token])) {
        return null;
    }

    $ctx = $_SESSION['event_track_auth'][$token];
    $expiryTs = strtotime((string)($ctx['expiry'] ?? ''));
    if ($expiryTs === false || time() > $expiryTs) {
        unset($_SESSION['event_track_auth'][$token]);
        return null;
    }

    $sessionInput = strtoupper(trim((string)($ctx['track_input'] ?? '')));
    if ($sessionInput !== $input) {
        return null;
    }

    return $ctx;
};

$verifiedContext = $getTrackAuthContext($trackToken, $trackInput);
if ($verifiedContext === null && ($trackToken !== '' || $trackInput !== '')) {
    $errorMsg = 'Tracking session expired. Please request OTP again.';
}

$buildTrackWhereClause = static function (array $ctx): array {
    $trackType = (string)($ctx['track_type'] ?? '');
    $trackValue = trim((string)($ctx['track_input'] ?? ''));
    if ($trackType === 'mobile') {
        $last10 = substr(preg_replace('/[^0-9]/', '', $trackValue), -10);
        if ($last10 === '') {
            return ['', []];
        }
        return ['RIGHT(r.phone, 10) = ?', [$last10]];
    }

    if ($trackType === 'booking_reference') {
        if ($trackValue === '') {
            return ['', []];
        }
        return ['r.booking_reference = ?', [$trackValue]];
    }

    return ['', []];
};

$buildBookingTimeline = static function (array $ctx): array {
    $createdAt = trim((string)($ctx['created_at'] ?? ''));
    $paymentStatus = strtolower(trim((string)($ctx['payment_status'] ?? '')));
    $verificationStatus = strtolower(trim((string)($ctx['verification_status'] ?? '')));
    $isCancelled = !empty($ctx['is_cancelled']);
    $isWaitlisted = !empty($ctx['is_waitlisted']);
    $waitlistPosition = (int)($ctx['waitlist_position'] ?? 0);
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
    if ($isWaitlisted) {
        $paymentStepState = 'info';
        $paymentStepDetail = $waitlistPosition > 0
            ? ('Booking is on waitlist at position #' . $waitlistPosition . '. Payment will open after confirmation.')
            : 'Booking is on waitlist. Payment will open after confirmation.';
    } elseif ($isCancelled) {
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
    if ($isWaitlisted) {
        $verificationStepState = 'info';
        $verificationStepDetail = 'Verification starts only after waitlist confirmation.';
    } elseif ($isCancelled && $verificationStatus === 'cancelled') {
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
    if ($isWaitlisted) {
        $cancelStepState = 'info';
        $cancelStepDetail = 'Waitlist booking is pending seat confirmation.';
    } elseif ($isCancelled) {
        if ($cancelLatest) {
            $refundStatus = strtolower(trim((string)($cancelLatest['refund_status'] ?? 'pending')));
            $refundAmount = (float)($cancelLatest['display_refund_amount'] ?? ($cancelLatest['refund_amount'] ?? 0));
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

    if ($isWaitlisted) {
        $eligibilityState = 'pending';
        $eligibilityText = 'Booking is currently waitlisted and not yet confirmed.';
        $nextAction = 'Wait for seat confirmation. Payment will open automatically after confirmation.';
    } elseif ($isCancelled) {
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
        $refundAmount = (float)($cancelRow['display_refund_amount'] ?? ($cancelRow['refund_amount'] ?? 0));
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
    } elseif ($isWaitlisted) {
        $progressPercent = 25;
    }

    $progressLabel = 'In Progress';
    if ($checkinStatus === 1) {
        $progressLabel = 'Checked In';
    } elseif ($isWaitlisted) {
        $progressLabel = 'Waitlisted';
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
    if (!$verifiedContext) {
        $errorMsg = 'Your tracking session expired. Please verify OTP again.';
    } else {
        $registrationId = (int)($_POST['registration_id'] ?? 0);
        $cancelPersons = (int)($_POST['cancel_persons'] ?? 0);
        $cancelReason = trim((string)($_POST['cancel_reason'] ?? ''));

        if ($registrationId <= 0) {
            $errorMsg = 'Invalid booking selected for cancellation.';
        } else {
            [$whereSql, $whereParams] = $buildTrackWhereClause($verifiedContext);
            if ($whereSql === '') {
                $errorMsg = 'Invalid tracking session. Please verify OTP again.';
            } else {
                $authSql = "SELECT r.id, r.persons
                    FROM event_registrations r
                    WHERE r.id = ?
                      AND {$whereSql}
                    LIMIT 1";
                $authStmt = $pdo->prepare($authSql);
                $authStmt->execute(array_merge([$registrationId], $whereParams));
                $authRow = $authStmt->fetch(PDO::FETCH_ASSOC);

                if (!$authRow) {
                    $errorMsg = 'You are not authorized to manage this booking from current tracking session.';
                } elseif ($cancelPersons > 0 && $cancelPersons > (int)$authRow['persons']) {
                    $errorMsg = 'Cancellation persons cannot exceed booked persons.';
                } else {
                    try {
                        $requestResult = vs_event_submit_cancellation_request($pdo, $registrationId, $cancelPersons, $cancelReason, 'online');
                        $statusMsg = ((string)($requestResult['request_type'] ?? 'full') === 'partial')
                            ? 'Partial cancellation request submitted. Awaiting admin approval.'
                            : 'Cancellation request submitted. Awaiting admin approval.';
                        $redirectParams = [
                            'track_input' => $trackInput,
                            'track_token' => $trackToken,
                            'msg' => $statusMsg,
                            'msg_type' => 'ok',
                        ];
                        header('Location: event-track.php?' . http_build_query($redirectParams));
                        exit;
                    } catch (Throwable $e) {
                        $errorMsg = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to cancel booking.';
                    }
                }
            }
        }
    }
}

$rows = [];
$cancellationMap = [];
$cancellationRequestMap = [];
$registrationRefundContext = [];
if ($verifiedContext) {
    [$whereSql, $whereParams] = $buildTrackWhereClause($verifiedContext);
    if ($whereSql === '') {
        $errorMsg = 'Invalid tracking session. Please verify OTP again.';
        $verifiedContext = null;
    } else {
        $sql = "SELECT
                r.*,
                r.booking_reference,
                e.title AS event_title,
                e.slug AS event_slug,
                e.event_type,
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
            WHERE {$whereSql}
            ORDER BY r.created_at DESC, r.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($whereParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $bookingReference = trim((string)($row['booking_reference'] ?? ''));
            if ($bookingReference === '') {
                $bookingReference = vs_event_assign_booking_reference($pdo, (int)$row['id']);
                $row['booking_reference'] = $bookingReference;
            }
            $row['event_date_display'] = vs_event_get_registration_date_display(
                $pdo,
                $row,
                (string)($row['selected_event_date'] ?? '')
            );

            $totalAmount = (float)($row['package_price_total'] ?? 0) * max((int)($row['persons'] ?? 1), 1);
            $amountPaid = (float)($row['amount_paid'] ?? 0);
            if (strtolower((string)($row['payment_status'] ?? '')) === 'paid' && $amountPaid <= 0) {
                $amountPaid = (float)($row['payment_amount'] ?? $totalAmount);
            }
            if ($amountPaid < 0) {
                $amountPaid = 0;
            }
            $remainingAmount = (float)($row['remaining_amount'] ?? 0);
            if ($remainingAmount <= 0 && strtolower((string)($row['payment_status'] ?? '')) !== 'cancelled') {
                $remainingAmount = round(max($totalAmount - $amountPaid, 0), 2);
            }

            $paymentStatus = strtolower(trim((string)($row['payment_status'] ?? '')));
            $verificationStatus = strtolower(trim((string)($row['verification_status'] ?? '')));
            $paymentMethod = strtolower(trim((string)($row['payment_method'] ?? '')));
            $paymentRecordStatus = strtolower(trim((string)($row['payment_record_status'] ?? '')));

            $isWaitlisted = vs_event_is_waitlisted_registration($row);
            $waitlistPosition = $isWaitlisted ? vs_event_get_waitlist_position($pdo, (int)($row['id'] ?? 0)) : 0;
            $isCancelled = (!$isWaitlisted && ($paymentStatus === 'cancelled' || $verificationStatus === 'cancelled'));
            $isPaid = ($paymentStatus === 'paid');
            $isPartialPaid = ($paymentStatus === 'partial paid');
            $isManualOrCash = in_array($paymentMethod, ['manual upi', 'cash'], true);
            $isPendingManualApproval = $isManualOrCash && (
                in_array($paymentStatus, ['pending', 'pending verification'], true)
                || in_array($verificationStatus, ['pending', 'pending verification'], true)
                || in_array($paymentRecordStatus, ['pending', 'pending verification'], true)
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

            $submittedManualAmount = round(max((float)($row['payment_amount'] ?? 0), 0), 2);
            $verifiedPaidAmount = round(max($amountPaid, 0), 2);
            if ($verifiedPaidAmount > $totalAmount && $totalAmount > 0) {
                $verifiedPaidAmount = $totalAmount;
            }

            $displayAmountPaid = $verifiedPaidAmount;
            $displayRemainingAmount = round(max((float)$remainingAmount, 0), 2);
            $pendingSubmittedAmount = 0.0;
            $rejectedSubmittedAmount = 0.0;
            $paymentAmountNote = '';

            if (!$isCancelled && $isPendingManualApproval && !$isRejectedByAdmin && $submittedManualAmount > 0) {
                $displayAmountPaid = round(min($totalAmount, $verifiedPaidAmount + $submittedManualAmount), 2);
                $displayRemainingAmount = round(max($totalAmount - $displayAmountPaid, 0), 2);
                $pendingSubmittedAmount = $submittedManualAmount;
                $paymentAmountNote = 'Submitted payment is pending verification and will be treated as confirmed paid only after admin approval.';
            } elseif (!$isCancelled && $isManualOrCash && $isRejectedByAdmin) {
                $displayAmountPaid = $verifiedPaidAmount;
                $displayRemainingAmount = round(max($totalAmount - $displayAmountPaid, 0), 2);
                if ($submittedManualAmount > 0) {
                    $rejectedSubmittedAmount = $submittedManualAmount;
                }
                $paymentAmountNote = ($displayAmountPaid <= 0)
                    ? 'Previous payment verification was rejected. Paid amount is currently Rs. 0. Please pay again.'
                    : 'Previous payment verification was rejected. Only verified amount is counted as paid.';
            }

            if ($isWaitlisted) {
                $isPaid = false;
                $isPartialPaid = false;
                $isPendingManualApproval = false;
                $isRejectedByAdmin = false;
                $showMakePaymentButton = false;
                $showRemainingPaymentButton = false;
                $displayAmountPaid = 0.0;
                $displayRemainingAmount = 0.0;
                $pendingSubmittedAmount = 0.0;
                $rejectedSubmittedAmount = 0.0;
                $verifiedPaidAmount = 0.0;
                $paymentAmountNote = $waitlistPosition > 0
                    ? ('Waitlisted at position #' . $waitlistPosition . '. Payment will open after confirmation.')
                    : 'Waitlisted booking. Payment will open after confirmation.';
            }

            $isApprovedByVerification = (
                in_array($verificationStatus, ['approved', 'auto verified'], true) ||
                in_array($paymentRecordStatus, ['approved', 'paid', 'success', 'successful'], true) ||
                in_array($paymentStatus, ['paid', 'partial paid'], true)
            );

            $paymentState = 'neutral';
            $paymentStateIcon = '&#9432;';
            $paymentStateTitle = 'Awaiting Payment Verification';
            $paymentStateMessage = 'Payment is not yet verified for this booking.';
            if ($isCancelled) {
                $paymentState = 'neutral';
                $paymentStateIcon = '&#9888;';
                $paymentStateTitle = 'Booking Cancelled';
                $paymentStateMessage = 'Payment updates are locked because this booking is cancelled.';
            } elseif ($isWaitlisted) {
                $paymentState = 'neutral';
                $paymentStateIcon = '&#9203;';
                $paymentStateTitle = 'Waitlisted Booking';
                $paymentStateMessage = $waitlistPosition > 0
                    ? ('Waitlist position #' . $waitlistPosition . '. Payment opens after confirmation.')
                    : 'Booking is waitlisted. Payment opens after confirmation.';
            } elseif ($isPendingManualApproval && !$isRejectedByAdmin) {
                $paymentState = 'pending';
                $paymentStateIcon = '&#9203;';
                $paymentStateTitle = 'Under Verification';
                $paymentStateMessage = 'Payment proof is submitted and waiting for admin verification.';
            } elseif ($isRejectedByAdmin) {
                $paymentState = 'rejected';
                $paymentStateIcon = '&#10006;';
                $paymentStateTitle = 'Verification Rejected';
                $paymentStateMessage = 'Last submitted payment was rejected. Please submit payment again.';
            } elseif ($isApprovedByVerification) {
                $paymentState = 'approved';
                $paymentStateIcon = '&#10004;';
                $paymentStateTitle = 'Payment Verified';
                $paymentStateMessage = 'Payment is verified and counted as confirmed paid amount.';
            }

            $paymentBadgeClass = 'warning';
            if ($paymentStatus === 'paid') {
                $paymentBadgeClass = 'success';
            } elseif (in_array($paymentStatus, ['failed', 'cancelled', 'rejected'], true)) {
                $paymentBadgeClass = 'danger';
            }
            $verificationBadgeClass = 'neutral';
            if (in_array($verificationStatus, ['approved', 'auto verified'], true)) {
                $verificationBadgeClass = 'success';
            } elseif (in_array($verificationStatus, ['pending', 'pending verification'], true)) {
                $verificationBadgeClass = 'warning';
            } elseif (in_array($verificationStatus, ['rejected', 'cancelled'], true)) {
                $verificationBadgeClass = 'danger';
            }
            $checkinBadgeClass = ((int)($row['checkin_status'] ?? 0) === 1) ? 'success' : 'neutral';

            $row['amount_total'] = $totalAmount;
            $row['amount_paid_display'] = $displayAmountPaid;
            $row['amount_remaining_display'] = $displayRemainingAmount;
            $row['amount_paid_verified'] = $verifiedPaidAmount;
            $row['amount_pending_submitted'] = $pendingSubmittedAmount;
            $row['amount_rejected_submitted'] = $rejectedSubmittedAmount;
            $row['payment_amount_note'] = $paymentAmountNote;
            $row['payment_state'] = $paymentState;
            $row['payment_state_icon'] = $paymentStateIcon;
            $row['payment_state_title'] = $paymentStateTitle;
            $row['payment_state_message'] = $paymentStateMessage;
            $row['payment_badge_class'] = $paymentBadgeClass;
            $row['verification_badge_class'] = $verificationBadgeClass;
            $row['checkin_badge_class'] = $checkinBadgeClass;
            $row['is_paid_status'] = $isPaid ? 1 : 0;
            $row['is_partial_paid_status'] = $isPartialPaid ? 1 : 0;
            $row['is_pending_manual_approval'] = $isPendingManualApproval ? 1 : 0;
            $row['is_rejected_by_admin'] = $isRejectedByAdmin ? 1 : 0;
            $row['show_make_payment_button'] = $showMakePaymentButton ? 1 : 0;
            $row['show_remaining_payment_button'] = $showRemainingPaymentButton ? 1 : 0;
            $row['can_cancel'] = (((int)($row['cancellation_allowed'] ?? 1) === 1) && !$isCancelled && !$isWaitlisted && (int)($row['checkin_status'] ?? 0) !== 1) ? 1 : 0;
            $row['is_waitlisted_status'] = $isWaitlisted ? 1 : 0;
            $row['waitlist_position'] = $waitlistPosition;

            $registrationRefundContext[(int)($row['id'] ?? 0)] = [
                'payment_status' => (string)($row['payment_status'] ?? ''),
                'verification_status' => (string)($row['verification_status'] ?? ''),
                'paid_amount' => (float)$verifiedPaidAmount,
            ];
        }
        unset($row);

        if (!empty($rows)) {
            $registrationIds = [];
            foreach ($rows as $row) {
                $registrationIds[] = (int)$row['id'];
            }
            $registrationIds = array_values(array_unique(array_filter($registrationIds, static function (int $id): bool {
                return $id > 0;
            })));

            if (!empty($registrationIds)) {
                $placeholders = implode(',', array_fill(0, count($registrationIds), '?'));
                $cancelStmt = $pdo->prepare("SELECT *
                    FROM event_cancellations
                    WHERE registration_id IN ($placeholders)
                    ORDER BY id DESC");
                $cancelStmt->execute($registrationIds);
                $cancelRows = $cancelStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cancelRows as $cancelRow) {
                    $regId = (int)($cancelRow['registration_id'] ?? 0);
                    if ($regId <= 0) {
                        continue;
                    }
                    $refundContext = $registrationRefundContext[$regId] ?? [];
                    $cancelRow['display_refund_amount'] = vs_event_resolve_refund_amount(array_merge($cancelRow, $refundContext));
                    if (!isset($cancellationMap[$regId])) {
                        $cancellationMap[$regId] = [];
                    }
                    $cancellationMap[$regId][] = $cancelRow;
                }

                $cancelReqStmt = $pdo->prepare("SELECT *
                    FROM event_cancellation_requests
                    WHERE registration_id IN ($placeholders)
                    ORDER BY id DESC");
                $cancelReqStmt->execute($registrationIds);
                $cancelReqRows = $cancelReqStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cancelReqRows as $cancelReqRow) {
                    $regId = (int)($cancelReqRow['registration_id'] ?? 0);
                    if ($regId <= 0) {
                        continue;
                    }
                    if (!isset($cancellationRequestMap[$regId])) {
                        $cancellationRequestMap[$regId] = [];
                    }
                    $cancellationRequestMap[$regId][] = $cancelReqRow;
                }
            }
        }
    }
}

$pageTitle = 'Track Event Booking';
require_once 'header.php';
?>
<main class="event-track-main" style="background-color:var(--cream-bg);">
    <section class="event-track-wrap">
        <div class="hero-card track-hero">
            <h1>Track Event Booking</h1>
            <p>Track your booking with mobile number or booking reference, verify OTP on WhatsApp, and manage payment or cancellation status in one place.</p>
        </div>

        <?php if ($message !== '' && $messageType === 'ok'): ?>
            <div class="notice ok"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($errorMsg !== ''): ?>
            <div class="notice err"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>

        <?php if (!$verifiedContext): ?>
            <div class="card track-form-card">
                <h2>Find Your Booking</h2>
                <p class="form-note">Use your registered mobile number or booking reference. OTP will be sent to your WhatsApp for secure access.</p>
                <form id="eventTrackForm" autocomplete="off">
                    <div class="form-group">
                        <label for="track_input">Mobile Number or Booking Reference</label>
                        <input type="text" id="track_input" name="track_input" placeholder="Enter mobile number or booking reference" value="<?php echo htmlspecialchars($trackInput); ?>" required>
                    </div>
                    <button type="submit" class="btn-main">Get OTP</button>
                </form>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="track-head">
                    <h2>Your Bookings</h2>
                    <a class="btn-main btn-alt" href="event-track.php">Track Another Booking</a>
                </div>

                <?php if (empty($rows)): ?>
                    <p class="empty-state">No event bookings found for the provided details.</p>
                <?php else: ?>
                    <div class="booking-list">
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $registrationId = (int)$row['id'];
                            $isWaitlisted = ((int)($row['is_waitlisted_status'] ?? 0) === 1);
                            $isCancelled = (
                                strtolower((string)($row['payment_status'] ?? '')) === 'cancelled' ||
                                strtolower((string)($row['verification_status'] ?? '')) === 'cancelled'
                            );
                            $cancelHistory = $cancellationMap[$registrationId] ?? [];
                            $cancelRequestHistory = $cancellationRequestMap[$registrationId] ?? [];
                            $pendingCancelRequest = null;
                            foreach ($cancelRequestHistory as $requestRow) {
                                if (strtolower((string)($requestRow['request_status'] ?? '')) === 'pending') {
                                    $pendingCancelRequest = $requestRow;
                                    break;
                                }
                            }
                            $hasPendingCancelRequest = ($pendingCancelRequest !== null);
                            $canCancel = ((int)($row['can_cancel'] ?? 0) === 1) && !$hasPendingCancelRequest;
                            $canPartialCancel = $canCancel && (int)$row['persons'] > 1;
                            $latestCancel = !empty($cancelHistory) ? $cancelHistory[0] : null;
                            $latestCancelRequest = !empty($cancelRequestHistory) ? $cancelRequestHistory[0] : null;
                            $bookingTimeline = $buildBookingTimeline([
                                'created_at' => (string)($row['created_at'] ?? ''),
                                'payment_status' => (string)($row['payment_status'] ?? ''),
                                'verification_status' => (string)($row['verification_status'] ?? ''),
                                'is_cancelled' => $isCancelled,
                                'is_waitlisted' => $isWaitlisted,
                                'waitlist_position' => (int)($row['waitlist_position'] ?? 0),
                                'is_paid' => ((int)($row['is_paid_status'] ?? 0) === 1),
                                'is_partial_paid' => ((int)($row['is_partial_paid_status'] ?? 0) === 1),
                                'is_pending_manual_approval' => ((int)($row['is_pending_manual_approval'] ?? 0) === 1),
                                'is_rejected_by_admin' => ((int)($row['is_rejected_by_admin'] ?? 0) === 1),
                                'has_pending_cancel_request' => $hasPendingCancelRequest,
                                'checkin_status' => (int)($row['checkin_status'] ?? 0),
                                'remaining_amount' => (float)($row['amount_remaining_display'] ?? 0),
                                'total_amount' => (float)($row['amount_total'] ?? 0),
                                'show_make_payment_button' => ((int)($row['show_make_payment_button'] ?? 0) === 1),
                                'show_remaining_payment_button' => ((int)($row['show_remaining_payment_button'] ?? 0) === 1),
                                'can_cancel' => $canCancel,
                                'is_cancellation_allowed' => ((int)($row['cancellation_allowed'] ?? 1) === 1),
                                'cancel_latest' => $latestCancel,
                                'cancel_request_latest' => $latestCancelRequest,
                                'cancel_history' => $cancelHistory,
                                'cancel_request_history' => $cancelRequestHistory,
                            ]);
                            ?>
                            <article class="booking-card booking-state-<?php echo htmlspecialchars((string)($row['payment_state'] ?? 'neutral')); ?>">
                                <div class="booking-card-head">
                                    <div class="booking-ref-wrap">
                                        <p class="booking-ref-label">Booking Reference</p>
                                        <h3 class="booking-ref-value"><?php echo htmlspecialchars((string)$row['booking_reference']); ?></h3>
                                        <p class="booking-subline">
                                            <?php echo htmlspecialchars((string)$row['event_title']); ?>
                                            <span>&middot;</span>
                                            <?php echo htmlspecialchars((string)$row['package_name']); ?>
                                        </p>
                                    </div>
                                    <div class="booking-badge-row">
                                        <span class="status-pill status-<?php echo htmlspecialchars((string)($row['payment_badge_class'] ?? 'neutral')); ?>">
                                            Payment: <?php echo htmlspecialchars((string)$row['payment_status']); ?>
                                        </span>
                                        <span class="status-pill status-<?php echo htmlspecialchars((string)($row['verification_badge_class'] ?? 'neutral')); ?>">
                                            Verification: <?php echo htmlspecialchars((string)$row['verification_status']); ?>
                                        </span>
                                        <span class="status-pill status-<?php echo htmlspecialchars((string)($row['checkin_badge_class'] ?? 'neutral')); ?>">
                                            <?php echo ((int)($row['checkin_status'] ?? 0) === 1) ? 'Checked In' : 'Not Checked In'; ?>
                                        </span>
                                        <?php if ($hasPendingCancelRequest): ?>
                                            <span class="status-pill status-warning">Cancellation Request: Pending</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="payment-spotlight payment-spotlight-<?php echo htmlspecialchars((string)($row['payment_state'] ?? 'neutral')); ?>">
                                    <span class="payment-spotlight-icon" aria-hidden="true"><?php echo (string)($row['payment_state_icon'] ?? '&#9432;'); ?></span>
                                    <div class="payment-spotlight-copy">
                                        <strong><?php echo htmlspecialchars((string)($row['payment_state_title'] ?? 'Payment Status')); ?></strong>
                                        <span><?php echo htmlspecialchars((string)($row['payment_state_message'] ?? '')); ?></span>
                                    </div>
                                    <div class="payment-amount-grid">
                                        <div class="payment-amount-card">
                                            <span>Total Amount</span>
                                            <strong>Rs. <?php echo number_format((float)($row['amount_total'] ?? 0), 0, '.', ''); ?></strong>
                                        </div>
                                        <div class="payment-amount-card payment-amount-card-strong">
                                            <span>Paid Amount (Shown)</span>
                                            <strong>Rs. <?php echo number_format((float)($row['amount_paid_display'] ?? 0), 0, '.', ''); ?></strong>
                                        </div>
                                        <div class="payment-amount-card">
                                            <span>Remaining Amount</span>
                                            <strong>Rs. <?php echo number_format((float)($row['amount_remaining_display'] ?? 0), 0, '.', ''); ?></strong>
                                        </div>
                                        <?php if ((float)($row['amount_pending_submitted'] ?? 0) > 0): ?>
                                            <div class="payment-amount-card payment-amount-card-warning">
                                                <span>Submitted For Verification</span>
                                                <strong>Rs. <?php echo number_format((float)$row['amount_pending_submitted'], 0, '.', ''); ?></strong>
                                            </div>
                                            <div class="payment-amount-card payment-amount-card-success">
                                                <span>Confirmed Paid (Verified)</span>
                                                <strong>Rs. <?php echo number_format((float)($row['amount_paid_verified'] ?? 0), 0, '.', ''); ?></strong>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ((float)($row['amount_rejected_submitted'] ?? 0) > 0): ?>
                                            <div class="payment-amount-card payment-amount-card-danger">
                                                <span>Previous Rejected Submission</span>
                                                <strong>Rs. <?php echo number_format((float)$row['amount_rejected_submitted'], 0, '.', ''); ?></strong>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($row['payment_amount_note'])): ?>
                                        <p class="payment-spotlight-note"><?php echo htmlspecialchars((string)$row['payment_amount_note']); ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="booking-timeline">
                                    <div class="booking-timeline-head">
                                        <h4>Booking Timeline</h4>
                                        <span class="booking-timeline-stage"><?php echo htmlspecialchars((string)($bookingTimeline['progress_label'] ?? 'In Progress')); ?></span>
                                    </div>
                                    <div class="booking-timeline-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int)($bookingTimeline['progress_percent'] ?? 0); ?>">
                                        <span style="width:<?php echo (int)($bookingTimeline['progress_percent'] ?? 0); ?>%"></span>
                                    </div>
                                    <div class="booking-timeline-eligibility booking-timeline-eligibility-<?php echo htmlspecialchars((string)($bookingTimeline['eligibility_state'] ?? 'pending')); ?>">
                                        <strong>Participation Status: <?php echo htmlspecialchars((string)($bookingTimeline['eligibility_text'] ?? '')); ?></strong>
                                        <span>Next Action: <?php echo htmlspecialchars((string)($bookingTimeline['next_action'] ?? '')); ?></span>
                                    </div>
                                    <ol class="booking-timeline-list">
                                        <?php foreach (($bookingTimeline['core_items'] ?? []) as $timelineItem): ?>
                                            <li class="booking-timeline-item booking-timeline-state-<?php echo htmlspecialchars((string)($timelineItem['state'] ?? 'pending')); ?>">
                                                <span class="booking-timeline-dot" aria-hidden="true"></span>
                                                <div class="booking-timeline-copy">
                                                    <div class="booking-timeline-row">
                                                        <strong><?php echo htmlspecialchars((string)($timelineItem['title'] ?? 'Step')); ?></strong>
                                                        <?php if (!empty($timelineItem['time'])): ?>
                                                            <span><?php echo htmlspecialchars((string)$timelineItem['time']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p><?php echo htmlspecialchars((string)($timelineItem['detail'] ?? '')); ?></p>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ol>
                                    <?php if (!empty($bookingTimeline['activity_items'])): ?>
                                        <h5 class="booking-timeline-subtitle">Actions Taken</h5>
                                        <ol class="booking-timeline-list booking-timeline-list-compact">
                                            <?php foreach (($bookingTimeline['activity_items'] ?? []) as $activityItem): ?>
                                                <li class="booking-timeline-item booking-timeline-state-<?php echo htmlspecialchars((string)($activityItem['state'] ?? 'info')); ?>">
                                                    <span class="booking-timeline-dot" aria-hidden="true"></span>
                                                    <div class="booking-timeline-copy">
                                                        <div class="booking-timeline-row">
                                                            <strong><?php echo htmlspecialchars((string)($activityItem['title'] ?? 'Activity')); ?></strong>
                                                            <?php if (!empty($activityItem['time'])): ?>
                                                                <span><?php echo htmlspecialchars((string)$activityItem['time']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p><?php echo htmlspecialchars((string)($activityItem['detail'] ?? '')); ?></p>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    <?php endif; ?>
                                </div>

                                <div class="booking-grid">
                                    <div class="info-item"><span>Event Date</span><strong><?php echo htmlspecialchars((string)($row['event_date_display'] ?? $row['selected_event_date'])); ?></strong></div>
                                    <div class="info-item"><span>Name</span><strong><?php echo htmlspecialchars((string)$row['name']); ?></strong></div>
                                    <div class="info-item"><span>Phone</span><strong><?php echo htmlspecialchars((string)$row['phone']); ?></strong></div>
                                    <div class="info-item"><span>Persons</span><strong><?php echo (int)$row['persons']; ?></strong></div>
                                    <div class="info-item"><span>Payment Method</span><strong><?php echo $isWaitlisted ? 'Not Available (Waitlisted)' : htmlspecialchars((string)($row['payment_method'] ?: 'N/A')); ?></strong></div>
                                    <?php if ($isWaitlisted): ?>
                                        <div class="info-item"><span>Waitlist Position</span><strong><?php echo (int)($row['waitlist_position'] ?? 0) > 0 ? ('#' . (int)$row['waitlist_position']) : 'Pending'; ?></strong></div>
                                    <?php endif; ?>
                                    <div class="info-item"><span>Check-In Time</span><strong><?php echo !empty($row['checkin_time']) ? htmlspecialchars((string)$row['checkin_time']) : '-'; ?></strong></div>
                                    <div class="info-item"><span>Checked-In By</span><strong><?php echo !empty($row['checkin_by_user_name']) ? htmlspecialchars((string)$row['checkin_by_user_name']) : '-'; ?></strong></div>
                                    <div class="info-item"><span>Booked At</span><strong><?php echo htmlspecialchars((string)$row['created_at']); ?></strong></div>
                                </div>

                                <div class="action-row">
                                    <a class="btn-main btn-alt" href="event-booking-confirmation.php?registration_id=<?php echo $registrationId; ?>" target="_blank">View / Print</a>
                                    <?php if ((int)($row['show_make_payment_button'] ?? 0) === 1 && !$hasPendingCancelRequest): ?>
                                        <a class="btn-main" href="event-payment.php?registration_id=<?php echo $registrationId; ?>">Make Payment</a>
                                    <?php endif; ?>
                                    <?php if ((int)($row['show_remaining_payment_button'] ?? 0) === 1 && !$hasPendingCancelRequest): ?>
                                        <a class="btn-main" href="event-remaining-payment.php?booking_reference=<?php echo urlencode((string)$row['booking_reference']); ?>&phone=<?php echo urlencode((string)$row['phone']); ?>">Make Remaining Payment</a>
                                    <?php endif; ?>
                                </div>

                                <?php if ($canCancel): ?>
                                    <form method="post" class="cancel-form" autocomplete="off" onsubmit="return confirm('Confirm cancellation request?');">
                                        <input type="hidden" name="action" value="cancel_booking">
                                        <input type="hidden" name="track_input" value="<?php echo htmlspecialchars($trackInput); ?>">
                                        <input type="hidden" name="track_token" value="<?php echo htmlspecialchars($trackToken); ?>">
                                        <input type="hidden" name="registration_id" value="<?php echo $registrationId; ?>">
                                        <?php if ($canPartialCancel): ?>
                                            <div class="form-group">
                                                <label>Cancel Persons</label>
                                                <input type="number" name="cancel_persons" min="1" max="<?php echo (int)$row['persons']; ?>" value="1" required>
                                            </div>
                                        <?php else: ?>
                                            <input type="hidden" name="cancel_persons" value="<?php echo (int)$row['persons']; ?>">
                                        <?php endif; ?>
                                        <div class="form-group">
                                            <label>Cancellation Reason</label>
                                            <input type="text" name="cancel_reason" placeholder="Enter reason (optional)">
                                        </div>
                                        <button type="submit" class="btn-main btn-danger"><?php echo $canPartialCancel ? 'Submit Cancellation Request' : 'Request Cancellation'; ?></button>
                                    </form>
                                <?php elseif ((int)($row['cancellation_allowed'] ?? 1) !== 1): ?>
                                    <p class="small-note">Cancellation is not allowed for this package.</p>
                                <?php elseif ($hasPendingCancelRequest): ?>
                                    <p class="small-note">
                                        Cancellation request is pending admin approval
                                        (Requested: <?php echo htmlspecialchars((string)($pendingCancelRequest['requested_at'] ?? '-')); ?>).
                                    </p>
                                <?php elseif ($isWaitlisted): ?>
                                    <p class="small-note">
                                        This booking is currently waitlisted<?php echo ((int)($row['waitlist_position'] ?? 0) > 0) ? (' at position #' . (int)$row['waitlist_position']) : ''; ?>.
                                        Payment will be available after confirmation.
                                    </p>
                                <?php elseif ($isCancelled): ?>
                                    <p class="small-note">This booking is already cancelled.</p>
                                <?php endif; ?>

                                <?php if (!empty($cancelRequestHistory)): ?>
                                    <div class="cancel-history">
                                        <h4>Cancellation Request Status</h4>
                                        <div class="table-wrap">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Requested Persons</th>
                                                    <th>Status</th>
                                                    <th>Requested At</th>
                                                    <th>Decided At</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($cancelRequestHistory as $requestItem): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars(ucfirst((string)($requestItem['request_type'] ?? 'full'))); ?></td>
                                                        <td><?php echo (int)($requestItem['requested_persons'] ?? 0); ?></td>
                                                        <td><?php echo htmlspecialchars((string)($requestItem['request_status'] ?? 'pending')); ?></td>
                                                        <td><?php echo htmlspecialchars((string)($requestItem['requested_at'] ?? '')); ?></td>
                                                        <td><?php echo htmlspecialchars((string)($requestItem['decided_at'] ?? '-')); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($cancelHistory)): ?>
                                    <div class="cancel-history">
                                        <h4>Refund Tracking</h4>
                                        <div class="table-wrap">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Cancelled Persons</th>
                                                    <th>Refund Amount</th>
                                                    <th>Refund Status</th>
                                                    <th>Cancelled At</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($cancelHistory as $cancelItem): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars(ucfirst((string)($cancelItem['cancellation_type'] ?? 'full'))); ?></td>
                                                        <td><?php echo (int)($cancelItem['cancelled_persons'] ?? 0); ?></td>
                                                        <td>Rs. <?php echo number_format((float)($cancelItem['display_refund_amount'] ?? $cancelItem['refund_amount'] ?? 0), 0, '.', ''); ?></td>
                                                        <td><?php echo htmlspecialchars((string)($cancelItem['refund_status'] ?? 'pending')); ?></td>
                                                        <td><?php echo htmlspecialchars((string)($cancelItem['cancelled_at'] ?? '')); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<div id="eventTrackOtpModal" class="otp-modal">
    <div class="otp-modal-content">
        <div class="otp-modal-header">Verify OTP</div>
        <div class="otp-modal-subtitle">OTP has been sent to your registered WhatsApp number.</div>
        <div id="otpError" class="otp-error"></div>
        <div id="otpSuccess" class="otp-success"></div>
        <form id="otpForm" autocomplete="off">
            <div class="form-group">
                <label for="otpCodeInput">Enter 4-digit OTP</label>
                <input type="text" id="otpCodeInput" maxlength="4" inputmode="numeric" required>
            </div>
            <div class="otp-actions">
                <button type="submit" class="btn-main" id="verifyOtpBtn">Verify</button>
                <button type="button" class="btn-main btn-alt" id="cancelOtpBtn">Cancel</button>
                <button type="button" class="btn-main btn-alt" id="resendOtpBtn">Resend OTP</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('eventTrackForm');
    var trackInputEl = document.getElementById('track_input');
    var modal = document.getElementById('eventTrackOtpModal');
    var otpForm = document.getElementById('otpForm');
    var otpCodeInput = document.getElementById('otpCodeInput');
    var otpError = document.getElementById('otpError');
    var otpSuccess = document.getElementById('otpSuccess');
    var verifyOtpBtn = document.getElementById('verifyOtpBtn');
    var cancelOtpBtn = document.getElementById('cancelOtpBtn');
    var resendOtpBtn = document.getElementById('resendOtpBtn');
    var currentTrackInput = '';

    if (!form) {
        return;
    }

    function showError(message) {
        if (!otpError) return;
        otpError.textContent = message || '';
        otpError.style.display = message ? 'block' : 'none';
    }

    function showSuccess(message) {
        if (!otpSuccess) return;
        otpSuccess.textContent = message || '';
        otpSuccess.style.display = message ? 'block' : 'none';
    }

    function openModal() {
        if (!modal) return;
        modal.style.display = 'flex';
        showError('');
        showSuccess('');
        if (otpCodeInput) {
            otpCodeInput.value = '';
            otpCodeInput.focus();
        }
    }

    function closeModal() {
        if (!modal) return;
        modal.style.display = 'none';
        showError('');
        showSuccess('');
    }

    function postOtpApi(payload) {
        var body = new URLSearchParams(payload);
        return fetch('api/verify_event_track_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (res) { return res.json(); });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!trackInputEl) {
            return;
        }
        currentTrackInput = String(trackInputEl.value || '').trim();
        if (currentTrackInput === '') {
            alert('Please enter mobile number or booking reference.');
            return;
        }

        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending OTP...';
        }

        postOtpApi({
            action: 'send_otp',
            track_input: currentTrackInput
        }).then(function (data) {
            if (!data || !data.success) {
                throw new Error((data && data.message) ? data.message : 'Unable to send OTP.');
            }
            openModal();
            showSuccess(data.message || 'OTP sent successfully.');
        }).catch(function (err) {
            alert(err.message || 'Unable to send OTP.');
        }).finally(function () {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Get OTP';
            }
        });
    });

    if (otpForm) {
        otpForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var otp = otpCodeInput ? String(otpCodeInput.value || '').trim() : '';
            if (otp.length !== 4) {
                showError('Please enter valid 4-digit OTP.');
                return;
            }
            showError('');
            showSuccess('');

            if (verifyOtpBtn) {
                verifyOtpBtn.disabled = true;
                verifyOtpBtn.textContent = 'Verifying...';
            }

            postOtpApi({
                action: 'verify_otp',
                track_input: currentTrackInput,
                otp: otp
            }).then(function (data) {
                if (!data || !data.success) {
                    throw new Error((data && data.message) ? data.message : 'OTP verification failed.');
                }
                showSuccess('OTP verified. Loading bookings...');
                var token = (data.data && data.data.track_token) ? data.data.track_token : '';
                if (!token) {
                    throw new Error('Invalid OTP verification response.');
                }
                var url = 'event-track.php?track_input=' + encodeURIComponent(currentTrackInput) + '&track_token=' + encodeURIComponent(token);
                window.location.href = url;
            }).catch(function (err) {
                showError(err.message || 'OTP verification failed.');
            }).finally(function () {
                if (verifyOtpBtn) {
                    verifyOtpBtn.disabled = false;
                    verifyOtpBtn.textContent = 'Verify';
                }
            });
        });
    }

    if (cancelOtpBtn) {
        cancelOtpBtn.addEventListener('click', function () {
            closeModal();
        });
    }

    if (resendOtpBtn) {
        resendOtpBtn.addEventListener('click', function () {
            if (!currentTrackInput) {
                return;
            }
            resendOtpBtn.disabled = true;
            resendOtpBtn.textContent = 'Resending...';
            postOtpApi({
                action: 'send_otp',
                track_input: currentTrackInput
            }).then(function (data) {
                if (!data || !data.success) {
                    throw new Error((data && data.message) ? data.message : 'Unable to resend OTP.');
                }
                showSuccess(data.message || 'OTP resent successfully.');
            }).catch(function (err) {
                showError(err.message || 'Unable to resend OTP.');
            }).finally(function () {
                resendOtpBtn.disabled = false;
                resendOtpBtn.textContent = 'Resend OTP';
            });
        });
    }

    window.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });
})();
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html,body{font-family:'Marcellus',serif!important}
.event-track-main{min-height:100vh;padding:1.8rem 0 4.8rem}
.event-track-wrap{max-width:1140px;margin:0 auto;padding:0 14px}
.hero-card{background:#fff;border:1px solid #ecd8d8;border-radius:16px;box-shadow:0 10px 24px rgba(125,27,20,.08);padding:16px;margin-bottom:14px}
.track-hero{background:linear-gradient(180deg,#fffdf9 0%,#fff 58%)}
.hero-card h1{margin:0;color:#7d1b14;font-size:1.6rem}
.hero-card p{margin:8px 0 0;color:#5d4a44;line-height:1.45}
.card{background:#fff;border:1px solid #ecd8d8;border-radius:16px;box-shadow:0 10px 24px rgba(125,27,20,.08);padding:16px;margin-bottom:14px}
.track-form-card h2{margin:0;color:#7d1b14;font-size:1.15rem}
.form-note{margin:8px 0 14px;color:#6d4d3f;font-size:.9rem;line-height:1.4}
.notice{margin:10px 0;padding:10px 12px;border-radius:10px;font-weight:700}
.notice.ok{background:#e7f7ed;color:#1a8917;border:1px solid #cbe8d3}
.notice.err{background:#ffeaea;color:#b00020;border:1px solid #f4c5cd}
.track-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.track-head h2{margin:0;color:#7d1b14;font-size:1.2rem}
.empty-state{margin:10px 0 0;color:#666;padding:10px 12px;border:1px dashed #e4c9c9;border-radius:10px;background:#fffaf7}
.form-group{display:flex;flex-direction:column;gap:6px}
label{color:#7d1b14;font-weight:700;font-size:.9rem}
input,select{width:100%;box-sizing:border-box;border:1px solid #e0c8c0;border-radius:10px;padding:10px 11px;font-size:.94rem;background:#fff}
input:focus,select:focus{outline:none;border-color:#c07f6e;box-shadow:0 0 0 3px rgba(125,27,20,.12)}
.btn-main{display:inline-block;border:none;border-radius:10px;background:#7d1b14;color:#fff;font-weight:700;padding:10px 14px;cursor:pointer;text-decoration:none;transition:all .2s ease}
.btn-main:hover{background:#65150f}
.btn-alt{background:#0f5f77}
.btn-alt:hover{background:#0b4a5d}
.btn-danger{background:#b42318}
.btn-danger:hover{background:#901d14}
.booking-list{display:grid;grid-template-columns:1fr;gap:14px;margin-top:12px}
.booking-card{border:1px solid #efd6d6;border-radius:14px;padding:14px;background:#fffdfa;box-shadow:0 8px 20px rgba(125,27,20,.06)}
.booking-state-pending{border-color:#e9d596;background:linear-gradient(180deg,#fffdf4 0%,#fffdfa 65%)}
.booking-state-approved{border-color:#cde4d5;background:linear-gradient(180deg,#f7fff9 0%,#fffdfa 65%)}
.booking-state-rejected{border-color:#f0c4cc;background:linear-gradient(180deg,#fff5f6 0%,#fffdfa 65%)}
.booking-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;margin-bottom:10px}
.booking-ref-wrap{min-width:240px}
.booking-ref-label{margin:0;color:#7f6a63;font-size:.77rem;letter-spacing:.08em;text-transform:uppercase;font-weight:700}
.booking-ref-value{margin:2px 0 4px;color:#6c2b23;font-size:1.2rem;line-height:1.2}
.booking-subline{margin:0;color:#5f4a44;font-size:.9rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.booking-subline span{color:#b09086}
.booking-badge-row{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}
.status-pill{display:inline-flex;align-items:center;border-radius:999px;padding:4px 10px;font-size:.76rem;font-weight:700;border:1px solid transparent;white-space:nowrap}
.status-success{background:#eaf9ee;border-color:#cde9d5;color:#186f40}
.status-warning{background:#fff6e3;border-color:#f3deac;color:#8a5f00}
.status-danger{background:#ffeef0;border-color:#f3c4cc;color:#a12536}
.status-neutral{background:#f2f4f8;border-color:#dbe1ea;color:#4c5564}
.payment-spotlight{margin:0 0 12px;border:1px solid #e6d8d8;border-radius:12px;padding:10px 12px}
.payment-spotlight-pending{background:#fff8e8;border-color:#f2ddad}
.payment-spotlight-approved{background:#eef9f1;border-color:#cee7d5}
.payment-spotlight-rejected{background:#ffeff1;border-color:#f1c2cb}
.payment-spotlight-neutral{background:#f4f6fb;border-color:#d8dfeb}
.payment-spotlight-icon{width:26px;height:26px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;font-weight:700;line-height:1;vertical-align:top}
.payment-spotlight-pending .payment-spotlight-icon{background:#f7d98d;color:#6d4b00}
.payment-spotlight-approved .payment-spotlight-icon{background:#bde4c8;color:#14653a}
.payment-spotlight-rejected .payment-spotlight-icon{background:#f3b3be;color:#992333}
.payment-spotlight-neutral .payment-spotlight-icon{background:#dbe1eb;color:#485566}
.payment-spotlight-copy{display:inline-flex;flex-direction:column;gap:2px;margin-left:8px;vertical-align:top;max-width:calc(100% - 42px)}
.payment-spotlight-copy strong{font-size:.93rem;color:#3a2020}
.payment-spotlight-copy span{font-size:.84rem;color:#4c586c;line-height:1.35}
.payment-amount-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;margin-top:10px}
.payment-amount-card{background:#fff;border:1px solid #e4d8d9;border-radius:10px;padding:8px 9px}
.payment-amount-card span{display:block;font-size:.74rem;color:#6b6f7b;text-transform:uppercase;letter-spacing:.04em;font-weight:700}
.payment-amount-card strong{display:block;margin-top:3px;font-size:1.02rem;color:#2a303b;line-height:1.2}
.payment-amount-card-strong{border-color:#d6c5c8;background:#fffaf7}
.payment-amount-card-warning{border-color:#f2ddad;background:#fff8e8}
.payment-amount-card-warning strong{color:#8a5f00}
.payment-amount-card-success{border-color:#cee7d5;background:#eef9f1}
.payment-amount-card-success strong{color:#186f40}
.payment-amount-card-danger{border-color:#f1c2cb;background:#ffeff1}
.payment-amount-card-danger strong{color:#a12536}
.payment-spotlight-note{margin:10px 0 0;font-size:.84rem;font-weight:700;line-height:1.35;color:#8a5f00}
.payment-spotlight-rejected .payment-spotlight-note{color:#9f1f2e}
.booking-timeline{margin:0 0 12px;border:1px solid #ead9d2;border-radius:12px;padding:10px;background:#fff}
.booking-timeline-head{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.booking-timeline-head h4{margin:0;color:#7d1b14;font-size:.98rem}
.booking-timeline-stage{display:inline-block;background:#f7efe8;border:1px solid #e5d0c5;border-radius:999px;padding:4px 9px;font-size:.76rem;font-weight:700;color:#6f2e25}
.booking-timeline-progress{height:7px;border-radius:999px;background:#f0e1db;overflow:hidden;margin:9px 0 10px}
.booking-timeline-progress span{display:block;height:100%;background:linear-gradient(90deg,#7d1b14,#b23a2e);border-radius:999px}
.booking-timeline-eligibility{display:flex;flex-direction:column;gap:2px;border-radius:10px;padding:8px 9px;margin-bottom:9px;border:1px solid #e2d6d6;background:#f8f8fa}
.booking-timeline-eligibility strong{font-size:.84rem;color:#2f2624;line-height:1.35}
.booking-timeline-eligibility span{font-size:.8rem;color:#4f5865;line-height:1.35}
.booking-timeline-eligibility-done{background:#eef9f1;border-color:#cde7d4}
.booking-timeline-eligibility-done strong{color:#145f35}
.booking-timeline-eligibility-pending{background:#fff8e8;border-color:#f0ddad}
.booking-timeline-eligibility-pending strong{color:#7b5500}
.booking-timeline-eligibility-blocked{background:#ffeff1;border-color:#f1c2cb}
.booking-timeline-eligibility-blocked strong{color:#992333}
.booking-timeline-list{list-style:none;margin:0;padding:0;position:relative}
.booking-timeline-list::before{content:'';position:absolute;left:8px;top:2px;bottom:2px;width:2px;background:#ecd8d1}
.booking-timeline-item{position:relative;display:flex;gap:10px;margin-bottom:8px}
.booking-timeline-item:last-child{margin-bottom:0}
.booking-timeline-dot{width:18px;height:18px;border-radius:50%;margin-top:2px;position:relative;z-index:1;border:2px solid #d6c4be;background:#fff}
.booking-timeline-copy{flex:1;min-width:0}
.booking-timeline-row{display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap}
.booking-timeline-row strong{font-size:.84rem;color:#2f2624}
.booking-timeline-row span{font-size:.75rem;color:#7f6b64}
.booking-timeline-copy p{margin:2px 0 0;font-size:.8rem;color:#5d4a45;line-height:1.34}
.booking-timeline-state-done .booking-timeline-dot{background:#1a8917;border-color:#1a8917}
.booking-timeline-state-done .booking-timeline-row strong{color:#1a6a30}
.booking-timeline-state-current .booking-timeline-dot{background:#b36b00;border-color:#b36b00}
.booking-timeline-state-current .booking-timeline-row strong{color:#8a5f00}
.booking-timeline-state-pending .booking-timeline-dot{background:#fff;border-color:#b6a09a}
.booking-timeline-state-blocked .booking-timeline-dot{background:#b00020;border-color:#b00020}
.booking-timeline-state-blocked .booking-timeline-row strong{color:#9f1f2e}
.booking-timeline-state-info .booking-timeline-dot{background:#0f5f77;border-color:#0f5f77}
.booking-timeline-state-info .booking-timeline-row strong{color:#0f5f77}
.booking-timeline-subtitle{margin:10px 0 6px;color:#7d1b14;font-size:.9rem}
.booking-timeline-list-compact .booking-timeline-copy p{font-size:.78rem}
.booking-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(185px,1fr));gap:8px}
.info-item{border:1px dashed #ead7d2;border-radius:10px;padding:8px 9px;background:#fff}
.info-item span{display:block;font-size:.74rem;color:#7b6760;text-transform:uppercase;letter-spacing:.05em;font-weight:700}
.info-item strong{display:block;margin-top:4px;color:#2f2624;font-size:.93rem;line-height:1.28;word-break:break-word}
.action-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.cancel-form{margin-top:12px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;align-items:end}
.small-note{margin:10px 0 0;padding:8px 10px;background:#fff4f4;border:1px solid #efc9ce;border-radius:10px;color:#9f1f2e;font-weight:700;font-size:.86rem;line-height:1.35}
.cancel-history{margin-top:12px;border-top:1px dashed #e5c8c8;padding-top:10px}
.cancel-history h4{margin:0 0 8px;color:#7d1b14;font-size:.98rem}
.table-wrap{overflow-x:auto}
.cancel-history table{width:100%;border-collapse:collapse;background:#fff}
.cancel-history th,.cancel-history td{border:1px solid #f1d6d6;padding:6px 7px;text-align:left;font-size:.83rem}
.cancel-history th{background:#f9eaea;color:#800000;font-weight:700}
.otp-modal{display:none;position:fixed;inset:0;background:rgba(20,18,18,.52);z-index:9999;align-items:center;justify-content:center;padding:12px;backdrop-filter:blur(2px)}
.otp-modal-content{background:#fff;border:1px solid #ecd3d3;border-radius:16px;padding:18px;max-width:430px;width:100%;box-shadow:0 16px 32px rgba(25,18,18,.2)}
.otp-modal-header{color:#7d1b14;font-weight:900;font-size:1.25rem}
.otp-modal-subtitle{margin:6px 0 10px;color:#666;line-height:1.4}
.otp-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.otp-error{display:none;color:#b00020;font-weight:700;margin-bottom:8px}
.otp-success{display:none;color:#1a8917;font-weight:700;margin-bottom:8px}
@media (max-width:760px){
    .event-track-main{padding-top:1.2rem}
    .hero-card h1{font-size:1.35rem}
    .booking-ref-value{font-size:1.08rem}
    .booking-badge-row{justify-content:flex-start}
    .payment-spotlight-copy{max-width:100%;display:flex;margin-left:0;margin-top:8px}
    .payment-spotlight-icon{display:inline-flex}
}
</style>

<?php require_once 'footer.php'; ?>
