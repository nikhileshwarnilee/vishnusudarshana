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

            $isCancelled = ($paymentStatus === 'cancelled');
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

            $row['amount_total'] = $totalAmount;
            $row['amount_paid_display'] = $amountPaid;
            $row['amount_remaining_display'] = $remainingAmount;
            $row['show_make_payment_button'] = $showMakePaymentButton ? 1 : 0;
            $row['show_remaining_payment_button'] = $showRemainingPaymentButton ? 1 : 0;
            $row['can_cancel'] = (((int)($row['cancellation_allowed'] ?? 1) === 1) && !$isCancelled && (int)($row['checkin_status'] ?? 0) !== 1) ? 1 : 0;
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
        <div class="hero-card">
            <h1>Track Event Booking</h1>
            <p>Enter mobile number or booking reference, verify OTP on WhatsApp, and view your event booking details.</p>
        </div>

        <?php if ($message !== '' && $messageType === 'ok'): ?>
            <div class="notice ok"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($errorMsg !== ''): ?>
            <div class="notice err"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>

        <?php if (!$verifiedContext): ?>
            <div class="card">
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
                    <p style="margin:0;color:#666;">No event bookings found for the provided details.</p>
                <?php else: ?>
                    <div class="booking-list">
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $registrationId = (int)$row['id'];
                            $isCancelled = (strtolower((string)($row['payment_status'] ?? '')) === 'cancelled');
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
                            ?>
                            <article class="booking-card">
                                <div class="booking-grid">
                                    <div><strong>Booking Reference:</strong> <?php echo htmlspecialchars((string)$row['booking_reference']); ?></div>
                                    <div><strong>Event:</strong> <?php echo htmlspecialchars((string)$row['event_title']); ?></div>
                                    <div><strong>Package:</strong> <?php echo htmlspecialchars((string)$row['package_name']); ?></div>
                                    <div><strong>Event Date:</strong> <?php echo htmlspecialchars((string)($row['event_date_display'] ?? $row['selected_event_date'])); ?></div>
                                    <div><strong>Name:</strong> <?php echo htmlspecialchars((string)$row['name']); ?></div>
                                    <div><strong>Phone:</strong> <?php echo htmlspecialchars((string)$row['phone']); ?></div>
                                    <div><strong>Persons:</strong> <?php echo (int)$row['persons']; ?></div>
                                    <div><strong>Payment Status:</strong> <?php echo htmlspecialchars((string)$row['payment_status']); ?></div>
                                    <div><strong>Verification:</strong> <?php echo htmlspecialchars((string)$row['verification_status']); ?></div>
                                    <div><strong>Cancellation Request:</strong> <?php echo $hasPendingCancelRequest ? 'Pending Approval' : 'None'; ?></div>
                                    <div><strong>Check-In Status:</strong> <?php echo ((int)($row['checkin_status'] ?? 0) === 1) ? 'Checked In' : 'Not Checked In'; ?></div>
                                    <div><strong>Check-In Time:</strong> <?php echo !empty($row['checkin_time']) ? htmlspecialchars((string)$row['checkin_time']) : '-'; ?></div>
                                    <div><strong>Checked-In By:</strong> <?php echo !empty($row['checkin_by_user_name']) ? htmlspecialchars((string)$row['checkin_by_user_name']) : '-'; ?></div>
                                    <div><strong>Payment Method:</strong> <?php echo htmlspecialchars((string)($row['payment_method'] ?? '')); ?></div>
                                    <div><strong>Total Amount:</strong> Rs. <?php echo number_format((float)($row['amount_total'] ?? 0), 0, '.', ''); ?></div>
                                    <div><strong>Paid Amount:</strong> Rs. <?php echo number_format((float)($row['amount_paid_display'] ?? 0), 0, '.', ''); ?></div>
                                    <div><strong>Remaining Amount:</strong> Rs. <?php echo number_format((float)($row['amount_remaining_display'] ?? 0), 0, '.', ''); ?></div>
                                    <div><strong>Booked At:</strong> <?php echo htmlspecialchars((string)$row['created_at']); ?></div>
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
                                <?php elseif ($isCancelled): ?>
                                    <p class="small-note">This booking is already cancelled.</p>
                                <?php endif; ?>

                                <?php if (!empty($cancelRequestHistory)): ?>
                                    <div class="cancel-history">
                                        <h4>Cancellation Request Status</h4>
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
                                <?php endif; ?>

                                <?php if (!empty($cancelHistory)): ?>
                                    <div class="cancel-history">
                                        <h4>Refund Tracking</h4>
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
                                                        <td>Rs. <?php echo number_format((float)($cancelItem['refund_amount'] ?? 0), 0, '.', ''); ?></td>
                                                        <td><?php echo htmlspecialchars((string)($cancelItem['refund_status'] ?? 'pending')); ?></td>
                                                        <td><?php echo htmlspecialchars((string)($cancelItem['cancelled_at'] ?? '')); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
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
.event-track-main{min-height:100vh;padding:1.5rem 0 4.5rem}
.event-track-wrap{max-width:1120px;margin:0 auto;padding:0 14px}
.hero-card{background:#fff;border:1px solid #ecd3d3;border-radius:14px;box-shadow:0 4px 14px rgba(128,0,0,.08);padding:14px;margin-bottom:12px}
.hero-card h1{margin:0;color:#800000}
.hero-card p{margin:8px 0 0;color:#555}
.card{background:#fff;border:1px solid #ecd3d3;border-radius:14px;box-shadow:0 4px 14px rgba(128,0,0,.08);padding:14px;margin-bottom:12px}
.notice{margin:10px 0;padding:10px 12px;border-radius:8px;font-weight:600}
.notice.ok{background:#e7f7ed;color:#1a8917}
.notice.err{background:#ffeaea;color:#b00020}
.track-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
.track-head h2{margin:0;color:#800000}
.form-group{display:flex;flex-direction:column;gap:6px}
label{color:#800000;font-weight:700;font-size:.92rem}
input,select{width:100%;box-sizing:border-box;border:1px solid #e0bebe;border-radius:8px;padding:9px 10px;font-size:.94rem;background:#fff}
.btn-main{display:inline-block;border:none;border-radius:8px;background:#800000;color:#fff;font-weight:700;padding:9px 12px;cursor:pointer;text-decoration:none}
.btn-alt{background:#0b7285}
.btn-danger{background:#b00020}
.booking-list{display:grid;grid-template-columns:1fr;gap:12px;margin-top:10px}
.booking-card{border:1px solid #f1d6d6;border-radius:12px;padding:12px;background:#fffaf8}
.booking-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;color:#444;font-size:.92rem}
.action-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.cancel-form{margin-top:10px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;align-items:end}
.small-note{margin:8px 0 0;color:#b00020;font-weight:700;font-size:.88rem}
.cancel-history{margin-top:12px;border-top:1px dashed #e5c8c8;padding-top:10px}
.cancel-history h4{margin:0 0 8px;color:#800000}
.cancel-history table{width:100%;border-collapse:collapse;background:#fff}
.cancel-history th,.cancel-history td{border:1px solid #f1d6d6;padding:6px 7px;text-align:left;font-size:.85rem}
.cancel-history th{background:#f9eaea;color:#800000}
.otp-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:12px}
.otp-modal-content{background:#fff;border:1px solid #ecd3d3;border-radius:14px;padding:16px;max-width:420px;width:100%}
.otp-modal-header{color:#800000;font-weight:900;font-size:1.2rem}
.otp-modal-subtitle{margin:6px 0 10px;color:#666}
.otp-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
.otp-error{display:none;color:#b00020;font-weight:700;margin-bottom:8px}
.otp-success{display:none;color:#1a8917;font-weight:700;margin-bottom:8px}
</style>

<?php require_once 'footer.php'; ?>
