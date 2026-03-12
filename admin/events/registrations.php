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

$message = trim((string)($_GET['msg'] ?? ''));
$messageType = trim((string)($_GET['msg_type'] ?? ''));

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : (int)($_POST['event_id'] ?? 0);
$paymentStatus = trim((string)($_GET['payment_status'] ?? $_POST['payment_status'] ?? ''));
$verificationStatus = trim((string)($_GET['verification_status'] ?? $_POST['verification_status'] ?? ''));

$quickMode = trim((string)($_GET['quick_mode'] ?? ''));
if (!in_array($quickMode, ['collect', 'verify', 'mark_paid', 'refund'], true)) {
    $quickMode = '';
}
$quickRegistrationId = isset($_GET['quick_registration_id']) ? (int)$_GET['quick_registration_id'] : 0;

$legacyViewId = isset($_GET['view_id']) ? (int)$_GET['view_id'] : 0;
if ($legacyViewId > 0) {
    $legacyParams = $_GET;
    unset($legacyParams['view_id']);
    $legacyReturn = 'registrations.php';
    if (!empty($legacyParams)) {
        $legacyReturn .= '?' . http_build_query($legacyParams);
    }
    header('Location: registration-view.php?id=' . $legacyViewId . '&return=' . urlencode($legacyReturn));
    exit;
}

$events = $pdo->query("SELECT id, title, event_date, event_type FROM events ORDER BY event_date DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($events as &$eventRow) {
    $eventRow['event_date_display'] = vs_event_get_event_date_display(
        $pdo,
        (int)$eventRow['id'],
        (string)($eventRow['event_date'] ?? ''),
        (string)($eventRow['event_type'] ?? 'single_day')
    );
}
unset($eventRow);

$baseFilters = [];
if ($eventId > 0) {
    $baseFilters['event_id'] = $eventId;
}
if ($paymentStatus !== '') {
    $baseFilters['payment_status'] = $paymentStatus;
}
if ($verificationStatus !== '') {
    $baseFilters['verification_status'] = $verificationStatus;
}

$buildListUrl = static function (array $extra = []) use ($baseFilters): string {
    $params = $baseFilters;
    foreach ($extra as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
            continue;
        }
        $params[$key] = $value;
    }
    if (empty($params)) {
        return 'registrations.php';
    }
    return 'registrations.php?' . http_build_query($params);
};

$redirectList = static function (string $msg, string $msgType = 'ok', array $extra = []) use ($buildListUrl): void {
    $params = array_merge($extra, ['msg' => $msg, 'msg_type' => $msgType]);
    header('Location: ' . $buildListUrl($params));
    exit;
};

$buildFilterHiddenInputs = static function () use ($eventId, $paymentStatus, $verificationStatus): string {
    $html = '';
    if ($eventId > 0) {
        $html .= '<input type="hidden" name="event_id" value="' . (int)$eventId . '">';
    }
    if ($paymentStatus !== '') {
        $html .= '<input type="hidden" name="payment_status" value="' . htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') . '">';
    }
    if ($verificationStatus !== '') {
        $html .= '<input type="hidden" name="verification_status" value="' . htmlspecialchars($verificationStatus, ENT_QUOTES, 'UTF-8') . '">';
    }
    return $html;
};

$fetchRegistration = static function (PDO $pdo, int $registrationId, bool $forUpdate = false): ?array {
    if ($registrationId <= 0) {
        return null;
    }
    $sql = "SELECT
            r.id,
            r.event_id,
            r.event_date_id,
            r.package_id,
            r.name,
            r.phone,
            r.persons,
            r.booking_reference,
            r.payment_status,
            r.verification_status,
            r.checkin_status,
            r.checkin_time,
            r.checkin_by_user_name,
            r.package_upi_id_snapshot,
            r.package_upi_qr_snapshot,
            r.created_at,
            e.title AS event_title,
            e.event_type,
            COALESCE(d.event_date, e.event_date) AS selected_event_date,
            p.package_name,
            p.is_paid,
            p.upi_id,
            p.upi_qr_image,
            COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS package_price_total,
            ep.id AS payment_id,
            COALESCE(ep.amount, 0) AS payment_amount,
            COALESCE(ep.amount_paid, 0) AS amount_paid,
            COALESCE(ep.remaining_amount, 0) AS remaining_amount,
            COALESCE(ep.payment_method, '') AS payment_method,
            COALESCE(ep.status, '') AS payment_record_status,
            COALESCE(ep.payment_type, '') AS payment_type,
            COALESCE(ep.transaction_id, '') AS transaction_id,
            COALESCE(ep.screenshot, '') AS screenshot,
            COALESCE(ep.remarks, '') AS remarks,
            COALESCE(ep.upi_id_used, '') AS upi_id_used,
            COALESCE(ep.upi_qr_used, '') AS upi_qr_used
        FROM event_registrations r
        INNER JOIN events e ON e.id = r.event_id
        LEFT JOIN event_dates d ON d.id = r.event_date_id
        INNER JOIN event_packages p ON p.id = r.package_id
        LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
        LEFT JOIN event_payments ep ON ep.registration_id = r.id
        WHERE r.id = ?
        LIMIT 1";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$registrationId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
};

$computeAmounts = static function (array $row): array {
    $persons = max((int)($row['persons'] ?? 1), 1);
    $total = round((float)($row['package_price_total'] ?? 0) * $persons, 2);
    $paid = round(max((float)($row['amount_paid'] ?? 0), 0), 2);
    $status = strtolower(trim((string)($row['payment_status'] ?? '')));
    if ($status === 'paid' && $paid <= 0) {
        $paid = $total;
    }
    if ($paid > $total && $total > 0) {
        $paid = $total;
    }
    $remaining = round((float)($row['remaining_amount'] ?? 0), 2);
    if ($remaining <= 0 && $status !== 'cancelled') {
        $remaining = round(max($total - $paid, 0), 2);
    }
    if ($status === 'paid') {
        $remaining = 0.0;
    }
    return ['total' => $total, 'paid' => $paid, 'remaining' => $remaining];
};

$computeDisplayAmounts = static function (array $row, array $baseAmounts): array {
    $total = round(max((float)($baseAmounts['total'] ?? 0), 0), 2);
    $paid = round(max((float)($baseAmounts['paid'] ?? 0), 0), 2);
    if ($paid > $total && $total > 0) {
        $paid = $total;
    }
    $remaining = round(max((float)($baseAmounts['remaining'] ?? 0), 0), 2);

    $paymentMethod = strtolower(trim((string)($row['payment_method'] ?? '')));
    $paymentStatus = strtolower(trim((string)($row['payment_status'] ?? '')));
    $verificationStatus = strtolower(trim((string)($row['verification_status'] ?? '')));
    $paymentRecordStatus = strtolower(trim((string)($row['payment_record_status'] ?? '')));
    $submittedAmount = round(max((float)($row['payment_amount'] ?? 0), 0), 2);

    $isManualOrCash = in_array($paymentMethod, ['manual upi', 'cash'], true);
    $isPendingVerification = $isManualOrCash && (
        in_array($paymentStatus, ['pending', 'pending verification'], true) ||
        in_array($verificationStatus, ['pending', 'pending verification'], true) ||
        in_array($paymentRecordStatus, ['pending', 'pending verification'], true)
    );
    $isRejectedVerification = $isManualOrCash && (
        in_array($verificationStatus, ['rejected'], true) ||
        in_array($paymentRecordStatus, ['rejected'], true) ||
        in_array($paymentStatus, ['failed', 'rejected'], true)
    );

    $displayPaid = $paid;
    $displayRemaining = $remaining;
    $note = '';

    if ($isPendingVerification && $submittedAmount > 0) {
        $displayPaid = round(min($total, $paid + $submittedAmount), 2);
        $displayRemaining = round(max($total - $displayPaid, 0), 2);
        $note = 'Submitted amount is pending verification.';
    } elseif ($isRejectedVerification) {
        $displayPaid = $paid;
        if ($displayPaid > $total && $total > 0) {
            $displayPaid = $total;
        }
        $displayRemaining = round(max($total - $displayPaid, 0), 2);
        $note = ($displayPaid <= 0)
            ? 'Previous payment was rejected. Paid amount is Rs 0.'
            : 'Previous payment was rejected. Only verified amount is counted.';
    }

    return [
        'paid' => $displayPaid,
        'remaining' => $displayRemaining,
        'submitted_amount' => $submittedAmount,
        'is_pending_submission' => ($isPendingVerification && $submittedAmount > 0),
        'is_rejected_submission' => $isRejectedVerification,
        'note' => $note,
    ];
};

$formatMoney = static function (float $amount): string {
    return number_format($amount, 2, '.', '');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_collect_payment'], $_POST['registration_id'])) {
    $registrationId = (int)$_POST['registration_id'];
    try {
        $pdo->beginTransaction();
        $row = $fetchRegistration($pdo, $registrationId, true);
        if (!$row) {
            throw new RuntimeException('Registration not found.');
        }

        if ((int)($row['is_paid'] ?? 1) !== 1) {
            throw new RuntimeException('Payment collection is only available for paid packages.');
        }

        $paymentStatusLower = strtolower(trim((string)($row['payment_status'] ?? '')));
        $verificationStatusLower = strtolower(trim((string)($row['verification_status'] ?? '')));
        if ($paymentStatusLower === 'cancelled' || $verificationStatusLower === 'cancelled') {
            throw new RuntimeException('Cancelled booking is not eligible for payment collection.');
        }
        if ($paymentStatusLower === 'paid') {
            throw new RuntimeException('Booking is already fully paid.');
        }

        $paymentRecordStatus = strtolower(trim((string)($row['payment_record_status'] ?? '')));
        if ($paymentStatusLower === 'pending verification' && in_array($paymentRecordStatus, ['pending', 'pending verification'], true)) {
            throw new RuntimeException('Payment is already submitted and pending verification.');
        }

        $amounts = $computeAmounts($row);
        $remainingDue = (float)$amounts['remaining'];
        $alreadyPaid = (float)$amounts['paid'];
        if ($remainingDue <= 0) {
            throw new RuntimeException('No pending amount found for this booking.');
        }

        $amountInput = trim((string)($_POST['collect_amount'] ?? ''));
        $collectAmount = $amountInput === '' ? $remainingDue : round((float)$amountInput, 2);
        if ($collectAmount <= 0) {
            throw new RuntimeException('Collection amount must be greater than zero.');
        }
        if ($collectAmount > $remainingDue) {
            throw new RuntimeException('Collection amount cannot be greater than pending amount.');
        }

        $methodInput = strtolower(trim((string)($_POST['collect_method'] ?? '')));
        if (!in_array($methodInput, ['upi', 'cash'], true)) {
            throw new RuntimeException('Please choose payment method (UPI or Cash).');
        }
        $paymentMethod = $methodInput === 'upi' ? 'Manual UPI' : 'Cash';

        $transactionId = trim((string)($_POST['collect_transaction_id'] ?? ''));
        if ($methodInput === 'upi' && $transactionId === '') {
            throw new RuntimeException('Transaction ID is required for UPI collection.');
        }
        if ($methodInput === 'cash' && $transactionId === '') {
            $transactionId = 'CASH-REG-' . date('YmdHis');
        }

        $remark = trim((string)($_POST['collect_remark'] ?? ''));
        if ($remark === '') {
            throw new RuntimeException('Remark is required for payment collection.');
        }

        if (!isset($_FILES['collect_proof']) || (int)($_FILES['collect_proof']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Payment proof image is required.');
        }
        $proofPath = vs_event_store_upload($_FILES['collect_proof'], 'payments', ['jpg', 'jpeg', 'png', 'webp']);
        if ($proofPath === null) {
            throw new RuntimeException('Invalid payment proof upload. Allowed formats: jpg, jpeg, png, webp.');
        }

        $paymentType = ($alreadyPaid > 0) ? 'remaining' : (($collectAmount < $remainingDue) ? 'advance' : 'full');
        $upiIdUsed = null;
        $upiQrUsed = null;
        if ($methodInput === 'upi') {
            $tmpUpiId = trim((string)($row['package_upi_id_snapshot'] ?? ''));
            if ($tmpUpiId === '') {
                $tmpUpiId = trim((string)($row['upi_id'] ?? ''));
            }
            $tmpUpiQr = trim((string)($row['package_upi_qr_snapshot'] ?? ''));
            if ($tmpUpiQr === '') {
                $tmpUpiQr = trim((string)($row['upi_qr_image'] ?? ''));
            }
            $upiIdUsed = $tmpUpiId !== '' ? $tmpUpiId : null;
            $upiQrUsed = $tmpUpiQr !== '' ? $tmpUpiQr : null;
        }

        if ($isMainAdminActor) {
            $totalAmount = (float)$amounts['total'];
            $newPaid = round($alreadyPaid + $collectAmount, 2);
            if ($newPaid > $totalAmount) {
                $newPaid = $totalAmount;
            }
            $remainingAfter = round(max($totalAmount - $newPaid, 0), 2);
            $newPaymentStatus = $remainingAfter > 0 ? 'Partial Paid' : 'Paid';
            $newPaymentType = $remainingAfter > 0 ? $paymentType : 'full';

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
                $collectAmount,
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

            $bookingReference = trim((string)($row['booking_reference'] ?? ''));
            if ($bookingReference === '') {
                $bookingReference = vs_event_assign_booking_reference($pdo, $registrationId);
            }

            if (vs_event_is_whatsapp_enabled($pdo, (int)$row['event_id'])) {
                $eventDateLabel = vs_event_get_registration_date_display($pdo, $row, (string)($row['selected_event_date'] ?? ''));
                if ($remainingAfter <= 0) {
                    $qrCodePath = vs_event_ensure_registration_qr($pdo, $registrationId);
                    vs_event_send_whatsapp_notice('ticket_delivery', (string)$row['phone'], [
                        'name' => (string)$row['name'],
                        'event_name' => (string)$row['event_title'],
                        'package_name' => (string)$row['package_name'],
                        'event_date' => $eventDateLabel,
                        'amount' => (string)$totalAmount,
                        'booking_reference' => $bookingReference,
                        'registration_id' => $registrationId,
                        'event_id' => (int)$row['event_id'],
                        'qr_code_path' => $qrCodePath,
                    ]);
                } else {
                    vs_event_send_whatsapp_notice('payment_approved', (string)$row['phone'], [
                        'name' => (string)$row['name'],
                        'event_name' => (string)$row['event_title'],
                        'package_name' => (string)$row['package_name'],
                        'event_date' => $eventDateLabel,
                        'amount' => (string)$collectAmount,
                        'booking_reference' => $bookingReference,
                        'registration_id' => $registrationId,
                        'event_id' => (int)$row['event_id'],
                    ]);
                }
            }

            $pdo->commit();
            $redirectList('Payment collected and auto-verified (main admin).', 'ok', [
                'quick_registration_id' => $registrationId,
            ]);
        }

        $upsert = $pdo->prepare("INSERT INTO event_payments (registration_id, amount, payment_type, amount_paid, remaining_amount, payment_method, upi_id_used, upi_qr_used, transaction_id, screenshot, remarks, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending Verification')
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
            $collectAmount,
            $paymentType,
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
        $redirectList('Payment submitted for verification.', 'ok', [
            'quick_mode' => 'verify',
            'quick_registration_id' => $registrationId,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errMessage = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to collect payment right now.';
        $redirectList($errMessage, 'err', [
            'quick_mode' => 'collect',
            'quick_registration_id' => $registrationId,
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_verify_payment'], $_POST['registration_id'], $_POST['payment_id'], $_POST['verify_action'])) {
    $registrationId = (int)$_POST['registration_id'];
    $paymentId = (int)$_POST['payment_id'];
    $verifyAction = strtolower(trim((string)$_POST['verify_action']));
    if (!in_array($verifyAction, ['approve', 'reject'], true)) {
        $redirectList('Invalid verification action.', 'err', [
            'quick_mode' => 'verify',
            'quick_registration_id' => $registrationId,
        ]);
    }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT
                ep.id,
                ep.amount,
                ep.payment_type,
                ep.amount_paid,
                ep.remaining_amount,
                ep.payment_method,
                ep.upi_id_used,
                ep.upi_qr_used,
                ep.transaction_id,
                ep.status AS payment_record_status,
                r.id AS registration_id,
                r.event_id,
                r.event_date_id,
                r.booking_reference,
                r.persons,
                r.name,
                r.phone,
                r.qr_code_path,
                r.payment_status,
                r.verification_status,
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
              AND r.id = ?
            LIMIT 1
            FOR UPDATE");
        $stmt->execute([$paymentId, $registrationId]);
        $paymentRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$paymentRow) {
            throw new RuntimeException('Payment record not found for this registration.');
        }

        $bookingReference = trim((string)($paymentRow['booking_reference'] ?? ''));
        if ($bookingReference === '') {
            $bookingReference = vs_event_assign_booking_reference($pdo, (int)$paymentRow['registration_id']);
            $paymentRow['booking_reference'] = $bookingReference;
        }

        $registrationStatusLower = strtolower(trim((string)($paymentRow['payment_status'] ?? '')));
        $verificationStatusLower = strtolower(trim((string)($paymentRow['verification_status'] ?? '')));
        if ($registrationStatusLower === 'cancelled' || $verificationStatusLower === 'cancelled') {
            throw new RuntimeException('Cancelled booking payments cannot be verified.');
        }

        if ($verifyAction === 'approve') {
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
                SET status = 'Approved',
                    payment_type = ?,
                    amount_paid = ?,
                    remaining_amount = ?
                WHERE id = ?")
                ->execute([$newPaymentType, $newPaid, $remainingAfter, $paymentId]);

            $pdo->prepare("UPDATE event_registrations
                SET payment_status = ?,
                    verification_status = 'Approved'
                WHERE id = ?")
                ->execute([$newPaymentStatus, (int)$paymentRow['registration_id']]);

            if (vs_event_is_whatsapp_enabled($pdo, (int)$paymentRow['event_id'])) {
                $eventDateLabel = vs_event_get_registration_date_display($pdo, $paymentRow, (string)($paymentRow['selected_event_date'] ?? ''));
                if ($remainingAfter <= 0) {
                    $qrCodePath = vs_event_ensure_registration_qr($pdo, (int)$paymentRow['registration_id']);
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

            $okMessage = 'Payment approved successfully.';
        } else {
            $pdo->prepare("UPDATE event_payments SET status = 'Rejected' WHERE id = ?")->execute([$paymentId]);
            $fallbackStatus = ((float)($paymentRow['amount_paid'] ?? 0) > 0) ? 'Partial Paid' : 'Failed';
            $pdo->prepare("UPDATE event_registrations
                SET payment_status = ?,
                    verification_status = 'Rejected'
                WHERE id = ?")
                ->execute([$fallbackStatus, (int)$paymentRow['registration_id']]);

            $okMessage = 'Payment rejected successfully.';
        }

        $pdo->commit();
        $redirectList($okMessage, 'ok', [
            'quick_mode' => 'verify',
            'quick_registration_id' => $registrationId,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errMessage = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to verify this payment right now.';
        $redirectList($errMessage, 'err', [
            'quick_mode' => 'verify',
            'quick_registration_id' => $registrationId,
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_mark_paid'], $_POST['registration_id'])) {
    $registrationId = (int)$_POST['registration_id'];
    try {
        $pdo->beginTransaction();
        $row = $fetchRegistration($pdo, $registrationId, true);
        if (!$row) {
            throw new RuntimeException('Registration not found.');
        }
        if ((int)($row['is_paid'] ?? 1) !== 1) {
            throw new RuntimeException('Mark paid is only available for paid packages.');
        }

        $paymentStatusLower = strtolower(trim((string)($row['payment_status'] ?? '')));
        $verificationStatusLower = strtolower(trim((string)($row['verification_status'] ?? '')));
        if ($paymentStatusLower === 'cancelled' || $verificationStatusLower === 'cancelled') {
            throw new RuntimeException('Cancelled booking cannot be marked as paid.');
        }

        $amounts = $computeAmounts($row);
        $totalAmount = (float)$amounts['total'];
        $alreadyPaid = (float)$amounts['paid'];
        if ($totalAmount < 0) {
            $totalAmount = 0.0;
        }
        if ($alreadyPaid < 0) {
            $alreadyPaid = 0.0;
        }
        $currentCollectionAmount = round(max($totalAmount - $alreadyPaid, 0), 2);
        if ($currentCollectionAmount <= 0) {
            $currentCollectionAmount = $totalAmount;
        }

        $manualMethod = trim((string)($_POST['mark_paid_method'] ?? 'Manual Approved'));
        if (!in_array($manualMethod, ['Manual Approved', 'Cash', 'Manual UPI'], true)) {
            $manualMethod = 'Manual Approved';
        }
        $transactionId = trim((string)($_POST['mark_paid_transaction_id'] ?? ''));
        if ($transactionId === '') {
            $transactionId = 'MANUAL-PAID-' . date('YmdHis');
        }

        $remark = trim((string)($_POST['mark_paid_remark'] ?? ''));
        $finalRemark = $remark !== '' ? $remark : trim((string)($row['remarks'] ?? ''));

        $upiIdUsed = null;
        $upiQrUsed = null;
        if ($manualMethod === 'Manual UPI') {
            $tmpUpiId = trim((string)($row['package_upi_id_snapshot'] ?? ''));
            if ($tmpUpiId === '') {
                $tmpUpiId = trim((string)($row['upi_id'] ?? ''));
            }
            $tmpUpiQr = trim((string)($row['package_upi_qr_snapshot'] ?? ''));
            if ($tmpUpiQr === '') {
                $tmpUpiQr = trim((string)($row['upi_qr_image'] ?? ''));
            }
            $upiIdUsed = $tmpUpiId !== '' ? $tmpUpiId : null;
            $upiQrUsed = $tmpUpiQr !== '' ? $tmpUpiQr : null;
        }

        if ((int)($row['payment_id'] ?? 0) > 0) {
            $update = $pdo->prepare("UPDATE event_payments
                SET amount = ?,
                    payment_type = 'full',
                    amount_paid = ?,
                    remaining_amount = 0,
                    payment_method = ?,
                    upi_id_used = COALESCE(?, upi_id_used),
                    upi_qr_used = COALESCE(?, upi_qr_used),
                    transaction_id = ?,
                    status = 'Approved',
                    remarks = ?
                WHERE registration_id = ?");
            $update->execute([
                $currentCollectionAmount,
                $totalAmount,
                $manualMethod,
                $upiIdUsed,
                $upiQrUsed,
                $transactionId,
                $finalRemark,
                $registrationId,
            ]);
        } else {
            $insert = $pdo->prepare("INSERT INTO event_payments (registration_id, amount, payment_type, amount_paid, remaining_amount, payment_method, upi_id_used, upi_qr_used, transaction_id, screenshot, remarks, status)
                VALUES (?, ?, 'full', ?, 0, ?, ?, ?, ?, NULL, ?, 'Approved')");
            $insert->execute([
                $registrationId,
                $currentCollectionAmount > 0 ? $currentCollectionAmount : $totalAmount,
                $totalAmount,
                $manualMethod,
                $upiIdUsed,
                $upiQrUsed,
                $transactionId,
                $finalRemark,
            ]);
        }

        $markPaidVerificationStatus = $isMainAdminActor ? 'Auto Verified' : 'Approved';
        $pdo->prepare("UPDATE event_registrations
            SET payment_status = 'Paid',
                verification_status = ?
            WHERE id = ?")
            ->execute([$markPaidVerificationStatus, $registrationId]);

        $bookingReference = trim((string)($row['booking_reference'] ?? ''));
        if ($bookingReference === '') {
            $bookingReference = vs_event_assign_booking_reference($pdo, $registrationId);
        }

        if (vs_event_is_whatsapp_enabled($pdo, (int)$row['event_id'])) {
            $eventDateLabel = vs_event_get_registration_date_display($pdo, $row, (string)($row['selected_event_date'] ?? ''));
            $qrCodePath = vs_event_ensure_registration_qr($pdo, $registrationId);
            vs_event_send_whatsapp_notice('ticket_delivery', (string)$row['phone'], [
                'name' => (string)$row['name'],
                'event_name' => (string)$row['event_title'],
                'package_name' => (string)$row['package_name'],
                'event_date' => $eventDateLabel,
                'amount' => (string)$totalAmount,
                'booking_reference' => $bookingReference,
                'registration_id' => $registrationId,
                'event_id' => (int)$row['event_id'],
                'qr_code_path' => $qrCodePath,
            ]);
        }

        $pdo->commit();
        $redirectList($isMainAdminActor ? 'Booking marked as paid and auto-verified (main admin).' : 'Booking marked as paid and approved.', 'ok', [
            'quick_registration_id' => $registrationId,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errMessage = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to mark this booking as paid.';
        $redirectList($errMessage, 'err', [
            'quick_mode' => 'mark_paid',
            'quick_registration_id' => $registrationId,
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_refund_action'], $_POST['cancel_id'], $_POST['refund_action'])) {
    $cancelId = (int)$_POST['cancel_id'];
    $registrationId = (int)($_POST['registration_id'] ?? 0);
    $refundAction = strtolower(trim((string)$_POST['refund_action']));
    if ($cancelId <= 0 || !in_array($refundAction, ['approve', 'reject'], true)) {
        $redirectList('Invalid refund action.', 'err', [
            'quick_mode' => 'refund',
            'quick_registration_id' => $registrationId,
        ]);
    }
    $newStatus = $refundAction === 'approve' ? 'processed' : 'rejected';
    $stmt = $pdo->prepare("UPDATE event_cancellations SET refund_status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $cancelId]);
    if ($stmt->rowCount() > 0) {
        $redirectList($refundAction === 'approve' ? 'Refund marked as processed.' : 'Refund marked as rejected.', 'ok', [
            'quick_mode' => 'refund',
            'quick_registration_id' => $registrationId,
        ]);
    }
    $redirectList('Cancellation record not found.', 'err', [
        'quick_mode' => 'refund',
        'quick_registration_id' => $registrationId,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_registration'], $_POST['registration_id'])) {
    $registrationId = (int)$_POST['registration_id'];
    admin_enforce_mapped_permission('delete');
    try {
        vs_event_delete_registration_if_eligible($pdo, $registrationId);
        $redirectList('Registration deleted permanently.', 'ok');
    } catch (Throwable $e) {
        $errMessage = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to delete registration right now.';
        $redirectList($errMessage, 'err', [
            'quick_registration_id' => $registrationId,
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_waitlisted_registration'], $_POST['registration_id'])) {
    $registrationId = (int)$_POST['registration_id'];
    try {
        admin_enforce_mapped_permission('edit');
        vs_event_confirm_waitlisted_registration($pdo, $registrationId);
        $redirectList('Waitlisted booking confirmed. Payment is now open for this booking.', 'ok', [
            'quick_registration_id' => $registrationId,
        ]);
    } catch (Throwable $e) {
        $errMessage = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to confirm waitlisted booking right now.';
        $redirectList($errMessage, 'err', [
            'quick_registration_id' => $registrationId,
        ]);
    }
}

$where = [];
$params = [];
if ($eventId > 0) {
    $where[] = 'r.event_id = ?';
    $params[] = $eventId;
}
if ($paymentStatus !== '') {
    $where[] = 'r.payment_status = ?';
    $params[] = $paymentStatus;
}
if ($verificationStatus !== '') {
    $where[] = 'r.verification_status = ?';
    $params[] = $verificationStatus;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT
        r.*,
        r.booking_reference,
        e.title AS event_title,
        e.event_type,
        COALESCE(d.event_date, e.event_date) AS selected_event_date,
        p.package_name,
        p.is_paid,
        COALESCE(NULLIF(p.waitlist_confirmation_mode, ''), 'auto') AS waitlist_confirmation_mode,
        COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS package_price_total,
        r.package_upi_id_snapshot,
        r.package_upi_qr_snapshot,
        ep.id AS payment_id,
        ep.payment_method,
        ep.upi_id_used,
        ep.upi_qr_used,
        ep.transaction_id,
        ep.screenshot,
        ep.status AS payment_record_status,
        ep.payment_type,
        ep.amount AS payment_amount,
        COALESCE(ep.amount_paid, 0) AS amount_paid,
        COALESCE(ep.remaining_amount, 0) AS remaining_amount,
        ep.remarks,
        c_tot.cancelled_persons_total,
        c_tot.refund_amount_total,
        c_tot.processed_refund_amount_total,
        c_last.id AS latest_cancel_id,
        c_last.cancelled_persons AS latest_cancelled_persons,
        c_last.refund_amount AS latest_refund_amount,
        c_last.refund_status AS latest_refund_status,
        c_last.cancel_reason AS latest_cancel_reason,
        c_last.cancelled_at AS latest_cancelled_at,
        c_req.id AS pending_request_id,
        c_req.request_type AS pending_request_type,
        c_req.requested_persons AS pending_requested_persons,
        c_req.requested_at AS pending_requested_at
    FROM event_registrations r
    INNER JOIN events e ON e.id = r.event_id
    LEFT JOIN event_dates d ON d.id = r.event_date_id
    INNER JOIN event_packages p ON p.id = r.package_id
    LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
    LEFT JOIN event_payments ep ON ep.registration_id = r.id
    LEFT JOIN (
        SELECT registration_id,
            SUM(cancelled_persons) AS cancelled_persons_total,
            SUM(refund_amount) AS refund_amount_total,
            SUM(CASE WHEN refund_status = 'processed' THEN refund_amount ELSE 0 END) AS processed_refund_amount_total
        FROM event_cancellations
        GROUP BY registration_id
    ) c_tot ON c_tot.registration_id = r.id
    LEFT JOIN (
        SELECT c1.registration_id,
            c1.id,
            c1.cancelled_persons,
            c1.refund_amount,
            c1.refund_status,
            c1.cancel_reason,
            c1.cancelled_at
        FROM event_cancellations c1
        INNER JOIN (
            SELECT registration_id, MAX(id) AS latest_id
            FROM event_cancellations
            GROUP BY registration_id
        ) c2 ON c2.latest_id = c1.id
    ) c_last ON c_last.registration_id = r.id
    LEFT JOIN (
        SELECT cr1.registration_id,
            cr1.id,
            cr1.request_type,
            cr1.requested_persons,
            cr1.requested_at
        FROM event_cancellation_requests cr1
        INNER JOIN (
            SELECT registration_id, MAX(id) AS latest_id
            FROM event_cancellation_requests
            WHERE request_status = 'pending'
            GROUP BY registration_id
        ) cr2 ON cr2.latest_id = cr1.id
    ) c_req ON c_req.registration_id = r.id
    $whereSql
    ORDER BY r.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$queryParams = $baseFilters;
$exportUrl = 'export-registrations.php';
if (!empty($queryParams)) {
    $exportUrl .= '?' . http_build_query($queryParams);
}
$pdfUrl = 'export-pdf.php';
if (!empty($queryParams)) {
    $pdfUrl .= '?' . http_build_query($queryParams);
}
$currentListUrl = $buildListUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registrations</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f7f7fa; margin:0; }
        .admin-container { max-width:1500px; margin:0 auto; padding:24px 12px; }
        h1 { color:#800000; margin-bottom:14px; }
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 12px #e0bebe22; padding:14px; margin-bottom:16px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:10px; align-items:end; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        label { color:#800000; font-weight:700; font-size:0.9em; }
        select, input, textarea { width:100%; box-sizing:border-box; padding:9px 10px; border:1px solid #e0bebe; border-radius:8px; font-size:0.94em; }
        textarea { min-height:80px; resize:vertical; }
        .btn-main { display:inline-block; border:none; border-radius:8px; padding:9px 12px; font-weight:700; cursor:pointer; text-decoration:none; background:#800000; color:#fff; }
        .btn-alt { background:#0b7285; }
        .btn-danger { background:#dc3545; }
        .btn-success { background:#1a8917; }
        .btn-warning { background:#b36b00; }
        .notice { margin:10px 0; padding:10px 12px; border-radius:8px; font-weight:600; }
        .notice.ok { background:#e7f7ed; color:#1a8917; }
        .notice.err { background:#ffeaea; color:#b00020; }
        .list-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px #e0bebe22; table-layout:auto; font-size:0.84em; }
        .list-table th, .list-table td { padding:8px 6px; border-bottom:1px solid #f3caca; text-align:left; vertical-align:top; }
        .list-table th { background:#f9eaea; color:#800000; font-size:0.88em; white-space:nowrap; }
        .status-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:14px; font-size:0.82em; font-weight:700; }
        .paid { background:#e5ffe5; color:#1a8917; }
        .pending { background:#fff4db; color:#b36b00; }
        .waitlisted { background:#e6f7ff; color:#0b7285; }
        .failed { background:#ffeaea; color:#b00020; }
        .approved { background:#e5ffe5; color:#1a8917; }
        .auto-verified { background:#dff6ff; color:#0b7285; }
        .rejected { background:#ffeaea; color:#b00020; }
        .cancelled { background:#ffeaea; color:#b00020; }
        .checkin-ok { background:#e7f7ed; color:#1a8917; }
        .checkin-no { background:#f1f3f5; color:#555; }
        .checkin-link { text-decoration:none; }
        .checkin-icon { width:16px; height:16px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:0.85em; font-weight:700; line-height:1; }
        .checkin-ok .checkin-icon { background:#1a8917; color:#fff; }
        .checkin-no .checkin-icon { background:#6c757d; color:#fff; }
        .small { color:#666; font-size:0.85em; }
        .money-link { color:#0b7285; text-decoration:none; font-weight:700; }
        .money-link:hover { text-decoration:underline; }
        .action-wrap { display:flex; flex-wrap:wrap; gap:6px; }
        .action-link { display:inline-block; padding:5px 8px; border-radius:6px; color:#fff; text-decoration:none; font-weight:700; font-size:0.82em; }
        .action-view { background:#17a2b8; }
        .action-verify { background:#6f42c1; }
        .action-mark { background:#1a8917; }
        .action-confirm { background:#0b7285; }
        .action-print { background:#444; }
        .action-refund { background:#dc3545; }
        .action-delete { background:#8b0000; }
        .action-btn { border:none; cursor:pointer; font-family:inherit; }
        .action-request { background:#c92a2a; box-shadow:0 0 0 1px #ffffff55 inset; }
        .row-cancel-request td { background:#fff7f7; }
        .row-cancel-request td:first-child { border-left:4px solid #c92a2a; }
        .cancel-request-alert { margin-top:2px; padding:8px 9px; border:1px solid #f1b0b7; border-left:4px solid #c92a2a; border-radius:8px; background:linear-gradient(135deg,#fff5f5,#ffe3e3); }
        .cancel-request-title { color:#a4161a; font-size:0.82em; font-weight:800; letter-spacing:0.02em; text-transform:uppercase; display:flex; align-items:center; gap:6px; }
        .cancel-request-icon { width:16px; height:16px; border-radius:50%; background:#c92a2a; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:0.74em; line-height:1; }
        .cancel-request-meta { margin-top:4px; color:#6a1b1b; font-size:0.82em; font-weight:600; }
        .cancel-request-link { display:inline-block; margin-top:6px; padding:3px 8px; border-radius:999px; background:#c92a2a; color:#fff; text-decoration:none; font-size:0.78em; font-weight:700; }
        .cancel-request-link:hover { background:#a61e1e; color:#fff; text-decoration:none; }
        .amount-cell { white-space:nowrap; font-weight:700; }
        .quick-row td { background:#fffdf5; }
        .quick-panel { border:1px solid #f0d8a8; border-radius:10px; padding:12px; background:#fff8e8; }
        .quick-title { margin:0 0 10px; color:#800000; font-size:1.02em; }
        .quick-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px; }
        .quick-actions { margin-top:10px; display:flex; gap:8px; flex-wrap:wrap; }
        .inline-link { color:#0b7285; text-decoration:none; font-weight:700; }
        .inline-link:hover { text-decoration:underline; }
        .chip { display:inline-block; padding:2px 8px; border-radius:10px; font-size:0.78em; font-weight:700; background:#f1f3f5; color:#444; }
        .chip.pending { background:#fff4db; color:#b36b00; }
        .chip.processed { background:#e5ffe5; color:#1a8917; }
        .chip.rejected { background:#ffeaea; color:#b00020; }
        @media (max-width:900px) {
            .admin-container { padding:16px 8px; }
            .list-table { font-size:0.8em; }
            .action-wrap { gap:4px; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <h1>Registrations</h1>
    <?php if ($message !== ''): ?>
        <div class="notice <?php echo ($messageType === 'ok') ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="get" class="grid">
            <div class="form-group">
                <label>Event</label>
                <select name="event_id">
                    <option value="">All Events</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo (int)$event['id']; ?>" <?php echo ($eventId === (int)$event['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$event['title'] . ' (' . (string)($event['event_date_display'] ?? $event['event_date']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Payment Status</label>
                <select name="payment_status">
                    <option value="">All</option>
                    <?php foreach (['Unpaid', 'Partial Paid', 'Paid', 'Pending Verification', 'Waitlisted', 'Failed', 'Cancelled'] as $st): ?>
                        <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($paymentStatus === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Verification Status</label>
                <select name="verification_status">
                    <option value="">All</option>
                    <?php foreach (['Pending', 'Approved', 'Auto Verified', 'Waitlisted', 'Rejected', 'Cancelled'] as $st): ?>
                        <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($verificationStatus === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <button class="btn-main" type="submit">Apply Filters</button>
            </div>
            <div class="form-group">
                <a class="btn-main btn-alt" href="<?php echo htmlspecialchars($exportUrl); ?>">Export To Excel</a>
            </div>
            <div class="form-group">
                <a class="btn-main btn-alt" href="<?php echo htmlspecialchars($pdfUrl); ?>" target="_blank">Export PDF</a>
            </div>
        </form>
    </div>

    <table class="list-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Booking Ref</th>
                <th>Event</th>
                <th>Package</th>
                <th>Name / Phone</th>
                <th>Persons</th>
                <th>Payment</th>
                <th>Verification</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Pending / Cancel</th>
                <th>Check-In</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($registrations)): ?>
            <tr><td colspan="14" style="text-align:center; padding:20px; color:#666;">No registrations found.</td></tr>
        <?php else: ?>
            <?php foreach ($registrations as $row): ?>
                <?php
                $registrationId = (int)$row['id'];
                $bookingRef = trim((string)($row['booking_reference'] ?? ''));
                if ($bookingRef === '') {
                    $bookingRef = vs_event_assign_booking_reference($pdo, $registrationId);
                }
                $eventDateDisplay = vs_event_get_registration_date_display(
                    $pdo,
                    $row,
                    (string)($row['selected_event_date'] ?? '')
                );

                $paymentStatusText = trim((string)$row['payment_status']);
                $paymentStatusLower = strtolower($paymentStatusText);
                $verificationStatusText = trim((string)$row['verification_status']);
                $verificationStatusLower = strtolower($verificationStatusText);
                $isWaitlisted = vs_event_is_waitlisted_registration($row);
                $waitlistPosition = $isWaitlisted ? vs_event_get_waitlist_position($pdo, $registrationId) : 0;
                $waitlistMode = strtolower(trim((string)($row['waitlist_confirmation_mode'] ?? 'auto')));
                if (!in_array($waitlistMode, ['auto', 'manual'], true)) {
                    $waitlistMode = 'auto';
                }
                $waitlistModeText = ucfirst($waitlistMode);
                $paymentRecordStatusLower = strtolower(trim((string)($row['payment_record_status'] ?? '')));
                $isCancelled = (!$isWaitlisted && ($paymentStatusLower === 'cancelled' || $verificationStatusLower === 'cancelled'));
                $isPaidPackage = ((int)($row['is_paid'] ?? 1) === 1);

                $paymentClass = 'pending';
                if ($isWaitlisted) {
                    $paymentClass = 'waitlisted';
                } elseif ($paymentStatusLower === 'paid') {
                    $paymentClass = 'paid';
                } elseif (in_array($paymentStatusLower, ['failed', 'cancelled', 'rejected'], true)) {
                    $paymentClass = 'failed';
                }
                $verifyClass = strtolower(str_replace(' ', '-', $verificationStatusText));
                if (!in_array($verifyClass, ['pending', 'approved', 'auto-verified', 'waitlisted', 'rejected', 'cancelled'], true)) {
                    $verifyClass = 'pending';
                }

                $isCheckedIn = ((int)($row['checkin_status'] ?? 0) === 1);
                $checkinClass = $isCheckedIn ? 'checkin-ok' : 'checkin-no';

                $amounts = $computeAmounts($row);
                $totalAmount = (float)$amounts['total'];
                if ($isWaitlisted) {
                    $paidAmount = 0.0;
                    $pendingAmount = 0.0;
                    $paidAmountDisplay = 0.0;
                    $pendingAmountDisplay = 0.0;
                    $pendingSubmittedAmount = 0.0;
                    $hasPendingSubmittedAmount = false;
                    $hasRejectedSubmittedAmount = false;
                    $paymentAmountNote = 'Payment opens after waitlist confirmation.';
                } else {
                    $paidAmount = (float)$amounts['paid'];
                    $pendingAmount = (float)$amounts['remaining'];

                    $displayAmounts = $computeDisplayAmounts($row, $amounts);
                    $paidAmountDisplay = (float)$displayAmounts['paid'];
                    $pendingAmountDisplay = (float)$displayAmounts['remaining'];
                    $pendingSubmittedAmount = (float)$displayAmounts['submitted_amount'];
                    $hasPendingSubmittedAmount = (bool)$displayAmounts['is_pending_submission'];
                    $hasRejectedSubmittedAmount = (bool)$displayAmounts['is_rejected_submission'];
                    $paymentAmountNote = (string)$displayAmounts['note'];
                }

                $hasPendingVerification = (
                    !$isWaitlisted &&
                    $paymentStatusLower === 'pending verification' &&
                    in_array($paymentRecordStatusLower, ['pending', 'pending verification'], true) &&
                    (int)($row['payment_id'] ?? 0) > 0
                );
                $hasPendingCancelRequest = (!$isWaitlisted && ((int)($row['pending_request_id'] ?? 0) > 0));
                $canCollect = (
                    !$isWaitlisted &&
                    !$isCancelled &&
                    !$hasPendingCancelRequest &&
                    $isPaidPackage &&
                    !$hasPendingVerification &&
                    $paymentStatusLower !== 'paid' &&
                    $pendingAmount > 0
                );
                $canMarkPaid = (
                    !$isWaitlisted &&
                    !$isCancelled &&
                    !$hasPendingCancelRequest &&
                    $isPaidPackage &&
                    $paymentStatusLower !== 'paid'
                );
                $canConfirmWaitlisted = $isWaitlisted;

                $quickCollectUrl = $buildListUrl([
                    'quick_mode' => 'collect',
                    'quick_registration_id' => $registrationId,
                ]) . '#reg-' . $registrationId;
                $quickVerifyUrl = $buildListUrl([
                    'quick_mode' => 'verify',
                    'quick_registration_id' => $registrationId,
                ]) . '#reg-' . $registrationId;
                $quickMarkPaidUrl = $buildListUrl([
                    'quick_mode' => 'mark_paid',
                    'quick_registration_id' => $registrationId,
                ]) . '#reg-' . $registrationId;
                $quickRefundUrl = $buildListUrl([
                    'quick_mode' => 'refund',
                    'quick_registration_id' => $registrationId,
                ]) . '#reg-' . $registrationId;
                $closeQuickUrl = $buildListUrl() . '#reg-' . $registrationId;
                $pendingRequestReviewUrl = 'cancellations.php';
                if ((int)($row['event_id'] ?? 0) > 0) {
                    $pendingRequestReviewUrl .= '?event_id=' . (int)$row['event_id'];
                }

                $cancelledPersonsTotal = (int)($row['cancelled_persons_total'] ?? 0);
                if ($cancelledPersonsTotal <= 0) {
                    $cancelledPersonsTotal = (int)($row['latest_cancelled_persons'] ?? 0);
                }
                if ($cancelledPersonsTotal <= 0) {
                    $cancelledPersonsTotal = max((int)($row['persons'] ?? 1), 1);
                }
                $cancelAmount = round($cancelledPersonsTotal * (float)($row['package_price_total'] ?? 0), 2);
                $rawRefundAmount = round((float)($row['refund_amount_total'] ?? 0), 2);
                if ($rawRefundAmount <= 0) {
                    $rawRefundAmount = round((float)($row['latest_refund_amount'] ?? 0), 2);
                }
                $refundAmount = vs_event_resolve_refund_amount([
                    'payment_status' => (string)($row['payment_status'] ?? ''),
                    'verification_status' => (string)($row['verification_status'] ?? ''),
                    'paid_amount' => $paidAmount,
                    'refund_amount' => $rawRefundAmount,
                ]);
                $latestRefundStatus = strtolower(trim((string)($row['latest_refund_status'] ?? '')));
                if ($latestRefundStatus === '') {
                    $latestRefundStatus = 'pending';
                }
                $latestCancelId = (int)($row['latest_cancel_id'] ?? 0);
                if ($isWaitlisted) {
                    $canDeleteRegistration = false;
                    $deleteEligibilityReason = 'Waitlisted booking cannot be deleted directly.';
                } else {
                    $deleteEligibility = vs_event_evaluate_registration_delete_eligibility([
                        'persons' => (int)($row['persons'] ?? 1),
                        'payment_status' => (string)($row['payment_status'] ?? ''),
                        'verification_status' => (string)($row['verification_status'] ?? ''),
                        'checkin_status' => (int)($row['checkin_status'] ?? 0),
                        'payment_id' => (int)($row['payment_id'] ?? 0),
                        'amount_paid' => (float)($row['amount_paid'] ?? 0),
                        'payment_record_status' => (string)($row['payment_record_status'] ?? ''),
                        'transaction_id' => (string)($row['transaction_id'] ?? ''),
                        'cancelled_persons_total' => (int)($row['cancelled_persons_total'] ?? 0),
                        'processed_refund_amount_total' => (float)($row['processed_refund_amount_total'] ?? 0),
                        'latest_cancelled_persons' => (int)($row['latest_cancelled_persons'] ?? 0),
                        'latest_refund_amount' => (float)($row['latest_refund_amount'] ?? 0),
                        'latest_refund_status' => (string)($row['latest_refund_status'] ?? ''),
                    ]);
                    $canDeleteRegistration = (bool)($deleteEligibility['eligible'] ?? false);
                    $deleteEligibilityReason = (string)($deleteEligibility['reason'] ?? '');
                }

                $paymentRecordText = trim((string)($row['payment_record_status'] ?? ''));
                $paymentMethodText = trim((string)($row['payment_method'] ?? ''));
                $transactionIdText = trim((string)($row['transaction_id'] ?? ''));
                $pendingRequestTypeText = ucfirst(strtolower(trim((string)($row['pending_request_type'] ?? 'full'))));
                if ($pendingRequestTypeText === '') {
                    $pendingRequestTypeText = 'Full';
                }
                $pendingRequestedPersons = (int)($row['pending_requested_persons'] ?? 0);
                if ($pendingRequestedPersons <= 0) {
                    $pendingRequestedPersons = max((int)($row['persons'] ?? 1), 1);
                }
                $pendingRequestedAtText = trim((string)($row['pending_requested_at'] ?? ''));
                ?>
                <tr id="reg-<?php echo $registrationId; ?>"<?php echo $hasPendingCancelRequest ? ' class="row-cancel-request"' : ''; ?>>
                    <td><?php echo $registrationId; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($bookingRef); ?></strong>
                        <?php if ($isWaitlisted): ?>
                            <br><span class="small">Waitlist <?php echo $waitlistPosition > 0 ? ('#' . (int)$waitlistPosition) : 'Pending'; ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars((string)$row['event_title']); ?></strong><br>
                        <span class="small"><?php echo htmlspecialchars($eventDateDisplay); ?></span>
                    </td>
                    <td>
                        <?php echo htmlspecialchars((string)$row['package_name']); ?>
                        <?php if ($isWaitlisted): ?>
                            <br><span class="small">Confirm: <?php echo htmlspecialchars($waitlistModeText); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars((string)$row['name']); ?></strong><br>
                        <span class="small"><?php echo htmlspecialchars((string)$row['phone']); ?></span>
                    </td>
                    <td><?php echo (int)$row['persons']; ?></td>
                    <td>
                        <?php if ($isWaitlisted): ?>
                            <span class="status-badge waitlisted">Waitlisted</span>
                            <br><span class="small">Payment locked until confirmation</span>
                        <?php else: ?>
                            <?php if ($canMarkPaid && !$hasPendingVerification): ?>
                                <a class="money-link" href="<?php echo htmlspecialchars($quickMarkPaidUrl); ?>">
                                    <span class="status-badge <?php echo htmlspecialchars($paymentClass); ?>"><?php echo htmlspecialchars($paymentStatusText); ?></span>
                                </a>
                            <?php else: ?>
                                <span class="status-badge <?php echo htmlspecialchars($paymentClass); ?>"><?php echo htmlspecialchars($paymentStatusText); ?></span>
                            <?php endif; ?>
                            <?php if ($paymentMethodText !== ''): ?><br><span class="small"><?php echo htmlspecialchars($paymentMethodText); ?></span><?php endif; ?>
                            <?php if ($transactionIdText !== ''): ?><br><span class="small">Txn: <?php echo htmlspecialchars($transactionIdText); ?></span><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isWaitlisted): ?>
                            <span class="status-badge waitlisted">Waitlisted</span>
                            <br><span class="small">Position: <?php echo $waitlistPosition > 0 ? ('#' . (int)$waitlistPosition) : 'Pending'; ?></span>
                        <?php else: ?>
                            <?php if ($hasPendingVerification): ?>
                                <a class="money-link" href="<?php echo htmlspecialchars($quickVerifyUrl); ?>">
                                    <span class="status-badge <?php echo htmlspecialchars($verifyClass); ?>"><?php echo htmlspecialchars($verificationStatusText); ?></span>
                                </a>
                            <?php else: ?>
                                <span class="status-badge <?php echo htmlspecialchars($verifyClass); ?>"><?php echo htmlspecialchars($verificationStatusText); ?></span>
                            <?php endif; ?>
                            <?php if ($paymentRecordText !== ''): ?><br><span class="small">Payment Rec: <?php echo htmlspecialchars($paymentRecordText); ?></span><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="amount-cell">Rs <?php echo htmlspecialchars($formatMoney($totalAmount)); ?></td>
                    <td class="amount-cell">
                        Rs <?php echo htmlspecialchars($formatMoney($paidAmountDisplay)); ?>
                        <?php if (!$isWaitlisted): ?>
                            <?php if ($hasPendingSubmittedAmount): ?><br><span class="small">Pending verification</span><?php endif; ?>
                            <?php if ($hasRejectedSubmittedAmount && $paidAmountDisplay <= 0): ?><br><span class="small" style="color:#b00020;">Previous payment rejected</span><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isWaitlisted): ?>
                            <div class="small"><strong>Waitlist Position:</strong> <?php echo $waitlistPosition > 0 ? ('#' . (int)$waitlistPosition) : 'Pending'; ?></div>
                            <div class="small"><strong>Confirmation:</strong> <?php echo htmlspecialchars($waitlistModeText); ?></div>
                            <div class="small"><strong>Payment:</strong> Not available until confirmed</div>
                        <?php elseif ($isCancelled): ?>
                            <div class="small"><strong>Cancel Amt:</strong> Rs <?php echo htmlspecialchars($formatMoney($cancelAmount)); ?></div>
                            <div class="small"><strong>Refund:</strong> Rs <?php echo htmlspecialchars($formatMoney($refundAmount)); ?></div>
                            <?php if ($paidAmount <= 0): ?>
                                <div class="small"><strong>Paid:</strong> Rs <?php echo htmlspecialchars($formatMoney(0)); ?></div>
                            <?php endif; ?>
                            <div class="small">
                                <span class="chip <?php echo htmlspecialchars($latestRefundStatus); ?>">
                                    <?php echo htmlspecialchars(ucfirst($latestRefundStatus)); ?>
                                </span>
                            </div>
                            <?php if ($latestCancelId > 0 && $latestRefundStatus === 'pending'): ?>
                                <div class="small" style="margin-top:4px;">
                                    <a class="money-link" href="<?php echo htmlspecialchars($quickRefundUrl); ?>">Update Refund</a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($hasPendingCancelRequest): ?>
                                <div class="cancel-request-alert">
                                    <div class="cancel-request-title">
                                        <span class="cancel-request-icon">!</span>
                                        <span>Cancel Request Pending</span>
                                    </div>
                                    <div class="cancel-request-meta">Type: <?php echo htmlspecialchars($pendingRequestTypeText); ?></div>
                                    <div class="cancel-request-meta">Persons: <?php echo (int)$pendingRequestedPersons; ?></div>
                                    <?php if ($pendingRequestedAtText !== ''): ?>
                                        <div class="cancel-request-meta">Requested: <?php echo htmlspecialchars($pendingRequestedAtText); ?></div>
                                    <?php endif; ?>
                                    <a class="cancel-request-link" href="<?php echo htmlspecialchars($pendingRequestReviewUrl); ?>">Review Request</a>
                                </div>
                            <?php elseif ($hasPendingVerification && $hasPendingSubmittedAmount): ?>
                                <a class="money-link" href="<?php echo htmlspecialchars($quickVerifyUrl); ?>">
                                    Rs <?php echo htmlspecialchars($formatMoney($pendingSubmittedAmount)); ?>
                                </a>
                                <br><span class="small">Recently submitted (pending verification)</span>
                            <?php elseif ($hasRejectedSubmittedAmount && $paidAmountDisplay <= 0): ?>
                                <span class="small" style="color:#b00020;"><strong>Previous payment rejected.</strong></span><br>
                                <?php if ($pendingAmount > 0 && $canCollect): ?>
                                    <a class="money-link" href="<?php echo htmlspecialchars($quickCollectUrl); ?>">
                                        Pay again: Rs <?php echo htmlspecialchars($formatMoney($pendingAmount)); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="amount-cell">Rs <?php echo htmlspecialchars($formatMoney($pendingAmountDisplay)); ?></span>
                                <?php endif; ?>
                            <?php elseif ($pendingAmount > 0 && $canCollect): ?>
                                <a class="money-link" href="<?php echo htmlspecialchars($quickCollectUrl); ?>">
                                    Rs <?php echo htmlspecialchars($formatMoney($pendingAmount)); ?>
                                </a>
                            <?php else: ?>
                                <span class="amount-cell">Rs <?php echo htmlspecialchars($formatMoney($pendingAmountDisplay)); ?></span>
                            <?php endif; ?>
                            <?php if ($paymentAmountNote !== '' && !($hasPendingVerification && $hasPendingSubmittedAmount) && !($hasRejectedSubmittedAmount && $paidAmountDisplay <= 0)): ?>
                                <br><span class="small"><?php echo htmlspecialchars($paymentAmountNote); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="checkin-link" href="checkin.php?booking_reference=<?php echo urlencode($bookingRef); ?>" title="Open check-in page">
                            <span class="status-badge <?php echo htmlspecialchars($checkinClass); ?>">
                                <span class="checkin-icon" aria-hidden="true"><?php echo $isCheckedIn ? '&#10003;' : '&#9679;'; ?></span>
                                <span><?php echo $isCheckedIn ? 'Checked In' : 'Not Checked In'; ?></span>
                            </span>
                        </a>
                        <?php if (!empty($row['checkin_time'])): ?><br><span class="small"><?php echo htmlspecialchars((string)$row['checkin_time']); ?></span><?php endif; ?>
                        <?php if (!empty($row['checkin_by_user_name'])): ?><br><span class="small">By: <?php echo htmlspecialchars((string)$row['checkin_by_user_name']); ?></span><?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars((string)$row['created_at']); ?></td>
                    <td>
                        <div class="action-wrap">
                            <a class="action-link action-view" href="registration-view.php?id=<?php echo $registrationId; ?>&return=<?php echo urlencode($currentListUrl); ?>">View</a>
                            <?php if ($canConfirmWaitlisted): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Confirm this waitlisted booking and open payment?');">
                                    <?php echo $buildFilterHiddenInputs(); ?>
                                    <input type="hidden" name="confirm_waitlisted_registration" value="1">
                                    <input type="hidden" name="registration_id" value="<?php echo $registrationId; ?>">
                                    <button type="submit" class="action-link action-confirm action-btn">Confirm Waitlist</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($hasPendingVerification): ?>
                                <a class="action-link action-verify" href="<?php echo htmlspecialchars($quickVerifyUrl); ?>">Verify</a>
                            <?php endif; ?>
                            <?php if ($canMarkPaid): ?>
                                <a class="action-link action-mark" href="<?php echo htmlspecialchars($quickMarkPaidUrl); ?>">Mark Paid</a>
                            <?php endif; ?>
                            <?php if ($hasPendingCancelRequest): ?>
                                <a class="action-link action-request" href="<?php echo htmlspecialchars($pendingRequestReviewUrl); ?>">Review Cancel</a>
                            <?php endif; ?>
                            <?php if ($latestCancelId > 0 && $latestRefundStatus === 'pending' && $isCancelled): ?>
                                <a class="action-link action-refund" href="<?php echo htmlspecialchars($quickRefundUrl); ?>">Refund</a>
                            <?php endif; ?>
                            <?php if ($paymentStatusLower === 'paid' || $isWaitlisted): ?>
                                <a class="action-link action-print" href="../../event-booking-confirmation.php?registration_id=<?php echo $registrationId; ?>&auto_print=1" target="_blank">Print</a>
                            <?php endif; ?>
                            <?php if ($canDeleteRegistration): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this registration permanently? This action cannot be undone.');">
                                    <?php echo $buildFilterHiddenInputs(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="delete_registration" value="1">
                                    <input type="hidden" name="registration_id" value="<?php echo $registrationId; ?>">
                                    <button type="submit" class="action-link action-delete action-btn">Delete</button>
                                </form>
                            <?php elseif ($isCancelled || $isWaitlisted): ?>
                                <span class="small" title="<?php echo htmlspecialchars($deleteEligibilityReason); ?>">Delete Locked</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <?php if ($quickRegistrationId === $registrationId && $quickMode !== ''): ?>
                    <tr class="quick-row">
                        <td colspan="14">
                            <div class="quick-panel">
                                <?php if ($quickMode === 'collect'): ?>
                                    <h3 class="quick-title">Collect Payment (Booking: <?php echo htmlspecialchars($bookingRef); ?>)</h3>
                                    <?php if (!$canCollect): ?>
                                        <p class="small">This booking is not eligible for pending collection right now.</p>
                                    <?php else: ?>
                                        <form method="post" enctype="multipart/form-data" autocomplete="off">
                                            <?php echo $buildFilterHiddenInputs(); ?>
                                            <input type="hidden" name="quick_collect_payment" value="1">
                                            <input type="hidden" name="registration_id" value="<?php echo $registrationId; ?>">
                                            <div class="quick-grid">
                                                <div class="form-group">
                                                    <label>Pending Amount</label>
                                                    <input type="number" step="0.01" min="0.01" max="<?php echo htmlspecialchars($formatMoney($pendingAmount)); ?>" name="collect_amount" value="<?php echo htmlspecialchars($formatMoney($pendingAmount)); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Payment Method</label>
                                                    <select name="collect_method" required>
                                                        <option value="">Select Method</option>
                                                        <option value="upi">UPI</option>
                                                        <option value="cash">Cash</option>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label>Transaction / Receipt ID</label>
                                                    <input type="text" name="collect_transaction_id" placeholder="UPI txn id or cash receipt no">
                                                </div>
                                                <div class="form-group">
                                                    <label>Payment Proof (Image)</label>
                                                    <input type="file" name="collect_proof" accept=".jpg,.jpeg,.png,.webp" required>
                                                </div>
                                                <div class="form-group" style="grid-column:1 / -1;">
                                                    <label>Remark</label>
                                                    <textarea name="collect_remark" placeholder="Add collection note" required></textarea>
                                                </div>
                                            </div>
                                            <div class="quick-actions">
                                                <button type="submit" class="btn-main btn-warning" onclick="return confirm('<?php echo $isMainAdminActor ? 'Collect and auto-verify this payment?' : 'Submit this payment for verification?'; ?>');"><?php echo $isMainAdminActor ? 'Collect & Auto Verify' : 'Submit For Verification'; ?></button>
                                                <a class="btn-main btn-alt" href="<?php echo htmlspecialchars($closeQuickUrl); ?>">Close</a>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif ($quickMode === 'verify'): ?>
                                    <h3 class="quick-title">Verify Payment (Booking: <?php echo htmlspecialchars($bookingRef); ?>)</h3>
                                    <?php if (!$hasPendingVerification): ?>
                                        <p class="small">No pending verification found for this booking.</p>
                                    <?php else: ?>
                                        <div class="quick-grid">
                                            <div class="small"><strong>Submission:</strong> Rs <?php echo htmlspecialchars($formatMoney((float)($row['payment_amount'] ?? 0))); ?></div>
                                            <div class="small"><strong>Method:</strong> <?php echo htmlspecialchars($paymentMethodText !== '' ? $paymentMethodText : '-'); ?></div>
                                            <div class="small"><strong>Txn ID:</strong> <?php echo htmlspecialchars($transactionIdText !== '' ? $transactionIdText : '-'); ?></div>
                                            <div class="small"><strong>Remark:</strong> <?php echo nl2br(htmlspecialchars((string)($row['remarks'] ?? '-'))); ?></div>
                                            <div class="small">
                                                <strong>Proof:</strong>
                                                <?php if (!empty($row['screenshot'])): ?>
                                                    <a class="inline-link" href="../../<?php echo htmlspecialchars(ltrim((string)$row['screenshot'], '/')); ?>" target="_blank">View Upload</a>
                                                <?php else: ?>
                                                    Not available
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <form method="post" style="margin-top:10px;">
                                            <?php echo $buildFilterHiddenInputs(); ?>
                                            <input type="hidden" name="quick_verify_payment" value="1">
                                            <input type="hidden" name="registration_id" value="<?php echo $registrationId; ?>">
                                            <input type="hidden" name="payment_id" value="<?php echo (int)($row['payment_id'] ?? 0); ?>">
                                            <div class="quick-actions">
                                                <button type="submit" name="verify_action" value="approve" class="btn-main btn-success" onclick="return confirm('Approve this payment?');">Approve</button>
                                                <button type="submit" name="verify_action" value="reject" class="btn-main btn-danger" onclick="return confirm('Reject this payment?');">Reject</button>
                                                <a class="btn-main btn-alt" href="<?php echo htmlspecialchars($closeQuickUrl); ?>">Close</a>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif ($quickMode === 'mark_paid'): ?>
                                    <h3 class="quick-title"><?php echo $isMainAdminActor ? 'Mark As Paid & Auto Verify' : 'Mark As Paid & Approve'; ?> (Booking: <?php echo htmlspecialchars($bookingRef); ?>)</h3>
                                    <?php if (!$canMarkPaid): ?>
                                        <p class="small">This booking cannot be marked paid right now.</p>
                                    <?php else: ?>
                                        <form method="post" autocomplete="off">
                                            <?php echo $buildFilterHiddenInputs(); ?>
                                            <input type="hidden" name="quick_mark_paid" value="1">
                                            <input type="hidden" name="registration_id" value="<?php echo $registrationId; ?>">
                                            <div class="quick-grid">
                                                <div class="form-group">
                                                    <label>Amount To Close</label>
                                                    <input type="text" value="Rs <?php echo htmlspecialchars($formatMoney($pendingAmount > 0 ? $pendingAmount : $totalAmount)); ?>" readonly>
                                                </div>
                                                <div class="form-group">
                                                    <label>Method</label>
                                                    <select name="mark_paid_method" required>
                                                        <option value="Manual Approved">Manual Approved</option>
                                                        <option value="Cash">Cash</option>
                                                        <option value="Manual UPI">Manual UPI</option>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label>Transaction ID</label>
                                                    <input type="text" name="mark_paid_transaction_id" placeholder="Optional manual ref">
                                                </div>
                                                <div class="form-group" style="grid-column:1 / -1;">
                                                    <label>Remark</label>
                                                    <textarea name="mark_paid_remark" placeholder="Optional internal note"></textarea>
                                                </div>
                                            </div>
                                            <div class="quick-actions">
                                                <button type="submit" class="btn-main btn-success" onclick="return confirm('<?php echo $isMainAdminActor ? 'Mark this booking as paid and auto-verified?' : 'Mark this booking as paid and approved?'; ?>');"><?php echo $isMainAdminActor ? 'Confirm Mark Paid (Auto Verify)' : 'Confirm Mark Paid'; ?></button>
                                                <a class="btn-main btn-alt" href="<?php echo htmlspecialchars($closeQuickUrl); ?>">Close</a>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <h3 class="quick-title">Refund Update (Booking: <?php echo htmlspecialchars($bookingRef); ?>)</h3>
                                    <?php if (!$isCancelled || $latestCancelId <= 0): ?>
                                        <p class="small">No cancellation refund record found for this booking.</p>
                                    <?php else: ?>
                                        <div class="quick-grid">
                                            <div class="small"><strong>Cancel Amount:</strong> Rs <?php echo htmlspecialchars($formatMoney($cancelAmount)); ?></div>
                                            <div class="small"><strong>Refund Amount:</strong> Rs <?php echo htmlspecialchars($formatMoney($refundAmount)); ?></div>
                                            <div class="small"><strong>Current Refund Status:</strong> <span class="chip <?php echo htmlspecialchars($latestRefundStatus); ?>"><?php echo htmlspecialchars(ucfirst($latestRefundStatus)); ?></span></div>
                                            <div class="small"><strong>Cancelled At:</strong> <?php echo htmlspecialchars((string)($row['latest_cancelled_at'] ?? '-')); ?></div>
                                        </div>
                                        <?php if ($latestRefundStatus === 'pending'): ?>
                                            <form method="post" style="margin-top:10px;">
                                                <?php echo $buildFilterHiddenInputs(); ?>
                                                <input type="hidden" name="quick_refund_action" value="1">
                                                <input type="hidden" name="registration_id" value="<?php echo $registrationId; ?>">
                                                <input type="hidden" name="cancel_id" value="<?php echo $latestCancelId; ?>">
                                                <div class="quick-actions">
                                                    <button type="submit" name="refund_action" value="approve" class="btn-main btn-success" onclick="return confirm('Mark refund as processed?');">Mark Processed</button>
                                                    <button type="submit" name="refund_action" value="reject" class="btn-main btn-danger" onclick="return confirm('Reject this refund?');">Reject Refund</button>
                                                    <a class="btn-main btn-alt" href="<?php echo htmlspecialchars($closeQuickUrl); ?>">Close</a>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <div class="quick-actions">
                                                <a class="btn-main btn-alt" href="<?php echo htmlspecialchars($closeQuickUrl); ?>">Close</a>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="../includes/responsive-tables.js"></script>
</body>
</html>
