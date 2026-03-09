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

$checkinByUserId = (int)($_SESSION['user_id'] ?? 0);
$checkinByUserName = trim((string)($_SESSION['user_name'] ?? ''));
if ($checkinByUserId > 0 && $checkinByUserName === '') {
    $userNameStmt = $pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
    $userNameStmt->execute([$checkinByUserId]);
    $checkinByUserName = trim((string)$userNameStmt->fetchColumn());
}
if ($checkinByUserId <= 0) {
    $checkinByUserId = null;
}
if ($checkinByUserName === '') {
    $checkinByUserName = 'Admin User';
}

$message = '';
$error = '';
$autoRawBtPrint = false;
$autoRawBtPrintRegistrationId = 0;
$searchInput = trim((string)($_GET['booking_reference'] ?? $_POST['booking_reference'] ?? ''));
$bookingReference = vs_event_extract_booking_reference($searchInput);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_action'], $_POST['payment_id'], $_POST['registration_id'])) {
    $verifyAction = trim((string)$_POST['verify_action']);
    $paymentId = (int)$_POST['payment_id'];
    $registrationId = (int)$_POST['registration_id'];

    if (!in_array($verifyAction, ['approve', 'reject'], true)) {
        $error = 'Invalid verification action.';
    } elseif ($paymentId <= 0 || $registrationId <= 0) {
        $error = 'Invalid payment selected for verification.';
    } else {
        try {
            $pdo->beginTransaction();

            $verifyStmt = $pdo->prepare("SELECT
                    ep.id,
                    ep.registration_id,
                    ep.amount,
                    ep.payment_type,
                    ep.amount_paid,
                    ep.remaining_amount,
                    ep.payment_method,
                    ep.transaction_id,
                    ep.remarks,
                    ep.status,
                    r.event_id,
                    r.event_date_id,
                    r.booking_reference,
                    r.persons,
                    r.name,
                    r.phone,
                    r.qr_code_path,
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
                  AND ep.registration_id = ?
                LIMIT 1
                FOR UPDATE");
            $verifyStmt->execute([$paymentId, $registrationId]);
            $paymentRow = $verifyStmt->fetch(PDO::FETCH_ASSOC);

            if (!$paymentRow) {
                throw new RuntimeException('Payment record not found for this booking.');
            }

            $bookingReference = trim((string)($paymentRow['booking_reference'] ?? ''));
            if ($bookingReference === '') {
                $bookingReference = vs_event_assign_booking_reference($pdo, (int)$paymentRow['registration_id']);
            }
            $searchInput = $bookingReference;

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
                    $eventDateLabel = vs_event_get_registration_date_display(
                        $pdo,
                        $paymentRow,
                        (string)($paymentRow['selected_event_date'] ?? '')
                    );
                    if ($remainingAfter <= 0) {
                        $qrCodePath = vs_event_ensure_registration_qr($pdo, (int)$paymentRow['registration_id']);
                        vs_event_send_whatsapp_notice('ticket_delivery', (string)$paymentRow['phone'], [
                            'name' => (string)$paymentRow['name'],
                            'event_name' => (string)$paymentRow['event_title'],
                            'package_name' => (string)$paymentRow['package_name'],
                            'event_date' => $eventDateLabel,
                            'amount' => (string)$totalAmount,
                            'booking_reference' => $bookingReference,
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
                            'booking_reference' => $bookingReference,
                            'registration_id' => (int)$paymentRow['registration_id'],
                            'event_id' => (int)$paymentRow['event_id'],
                        ]);
                    }
                }

                $message = 'Payment approved successfully. You can proceed with check-in if payment is fully paid.';
            } else {
                $pdo->prepare("UPDATE event_payments SET status = 'Rejected' WHERE id = ?")
                    ->execute([$paymentId]);
                $fallbackStatus = ((float)($paymentRow['amount_paid'] ?? 0) > 0) ? 'Partial Paid' : 'Failed';
                $pdo->prepare("UPDATE event_registrations
                    SET payment_status = ?,
                        verification_status = 'Rejected'
                    WHERE id = ?")
                    ->execute([$fallbackStatus, (int)$paymentRow['registration_id']]);

                $message = 'Payment rejected. Collect payment again to continue.';
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to verify payment right now.';
            error_log('Event inline verification failed: ' . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin_action'], $_POST['registration_id'])) {
    $action = trim((string)$_POST['checkin_action']);
    $registrationId = (int)$_POST['registration_id'];

    if (!in_array($action, ['mark', 'mark_partial_settlement', 'undo'], true)) {
        $error = 'Invalid check-in action.';
    } elseif ($registrationId <= 0) {
        $error = 'Invalid registration selected.';
    } else {
        try {
            $pdo->beginTransaction();

            $rowStmt = $pdo->prepare("SELECT
                    r.id,
                    r.event_id,
                    r.event_date_id,
                    r.package_id,
                    r.persons,
                    r.name,
                    r.phone,
                    r.booking_reference,
                    r.payment_status,
                    r.verification_status,
                    r.checkin_status,
                    e.title AS event_title,
                    e.event_type,
                    COALESCE(d.event_date, e.event_date) AS selected_event_date,
                    p.package_name,
                    p.is_paid,
                    p.allow_checkin_without_payment,
                    COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS package_price_total,
                    COALESCE(ep.amount_paid, 0) AS amount_paid,
                    COALESCE(ep.remaining_amount, 0) AS remaining_amount,
                    COALESCE(ep.status, '') AS payment_record_status
                FROM event_registrations r
                INNER JOIN events e ON e.id = r.event_id
                LEFT JOIN event_dates d ON d.id = r.event_date_id
                INNER JOIN event_packages p ON p.id = r.package_id
                LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
                LEFT JOIN event_payments ep ON ep.registration_id = r.id
                WHERE r.id = ?
                LIMIT 1
                FOR UPDATE");
            $rowStmt->execute([$registrationId]);
            $row = $rowStmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new RuntimeException('Registration not found.');
            }

            $bookingReference = trim((string)($row['booking_reference'] ?? ''));
            if ($bookingReference === '') {
                $bookingReference = vs_event_assign_booking_reference($pdo, $registrationId);
            }
            $searchInput = $bookingReference;

            if ($action === 'mark') {
                if ((int)$row['checkin_status'] === 1) {
                    throw new RuntimeException('Already checked in. Multiple check-ins are not allowed.');
                }

                $isPaidPackage = ((int)($row['is_paid'] ?? 1) === 1);
                $allowWithoutPayment = ((int)($row['allow_checkin_without_payment'] ?? 0) === 1);
                $paymentStatusLower = strtolower(trim((string)($row['payment_status'] ?? '')));
                $verificationStatusLower = strtolower(trim((string)($row['verification_status'] ?? '')));
                if ($paymentStatusLower === 'cancelled' || $verificationStatusLower === 'cancelled') {
                    throw new RuntimeException('Cancelled booking cannot be checked in.');
                }

                $canCheckin = false;
                if (!$isPaidPackage) {
                    $canCheckin = true;
                } elseif ($paymentStatusLower === 'paid') {
                    $canCheckin = true;
                } elseif ($allowWithoutPayment) {
                    $canCheckin = true;
                }

                if (!$canCheckin) {
                    throw new RuntimeException('Check-in is blocked by package payment policy. Collect payment first.');
                }

                $pdo->prepare("UPDATE event_registrations
                    SET checkin_status = 1,
                        checkin_time = NOW(),
                        checkin_by_user_id = ?,
                        checkin_by_user_name = ?
                    WHERE id = ?")
                    ->execute([$checkinByUserId, $checkinByUserName, $registrationId]);
                $message = 'Check-in marked successfully.';
                $autoRawBtPrint = true;
                $autoRawBtPrintRegistrationId = $registrationId;
            } elseif ($action === 'mark_partial_settlement') {
                $currentPaymentStatus = strtolower(trim((string)($row['payment_status'] ?? '')));
                $currentVerificationStatus = strtolower(trim((string)($row['verification_status'] ?? '')));
                $currentPaymentRecordStatus = strtolower(trim((string)($row['payment_record_status'] ?? '')));
                if (in_array($currentPaymentStatus, ['paid', 'cancelled'], true) || $currentVerificationStatus === 'cancelled') {
                    throw new RuntimeException('This booking is not eligible for payment collection.');
                }
                if ((int)$row['checkin_status'] === 1) {
                    throw new RuntimeException('Already checked in. Multiple check-ins are not allowed.');
                }
                if (
                    $currentPaymentStatus === 'pending verification' &&
                    in_array($currentPaymentRecordStatus, ['pending', 'pending verification'], true)
                ) {
                    throw new RuntimeException('Payment is already pending verification. Please approve/reject it first.');
                }

                $totalAmount = round((float)$row['package_price_total'] * max((int)$row['persons'], 1), 2);
                $alreadyPaid = round(max((float)$row['amount_paid'], 0), 2);
                $remainingDue = round((float)$row['remaining_amount'], 2);
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
                    $transactionId = 'CASH-CHECKIN-' . date('YmdHis');
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

                $paymentType = 'remaining';
                if ($alreadyPaid <= 0) {
                    $paymentType = 'full';
                }

                if ($isMainAdminActor) {
                    $newPaid = round($alreadyPaid + $remainingDue, 2);
                    if ($newPaid > $totalAmount) {
                        $newPaid = $totalAmount;
                    }
                    $remainingAfter = round(max($totalAmount - $newPaid, 0), 2);
                    $newPaymentStatus = $remainingAfter > 0 ? 'Partial Paid' : 'Paid';
                    $newPaymentType = $remainingAfter > 0 ? $paymentType : 'full';

                    $upsert = $pdo->prepare("INSERT INTO event_payments (registration_id, amount, payment_type, amount_paid, remaining_amount, payment_method, transaction_id, screenshot, remarks, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Approved')
                        ON DUPLICATE KEY UPDATE
                            amount = VALUES(amount),
                            payment_type = VALUES(payment_type),
                            amount_paid = VALUES(amount_paid),
                            remaining_amount = VALUES(remaining_amount),
                            payment_method = VALUES(payment_method),
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
                        $transactionId,
                        $proofPath,
                        $remark,
                    ]);

                    $pdo->prepare("UPDATE event_registrations
                        SET payment_status = ?,
                            verification_status = 'Auto Verified'
                        WHERE id = ?")
                        ->execute([$newPaymentStatus, $registrationId]);

                    if (vs_event_is_whatsapp_enabled($pdo, (int)$row['event_id'])) {
                        $eventDateLabel = vs_event_get_registration_date_display(
                            $pdo,
                            $row,
                            (string)($row['selected_event_date'] ?? '')
                        );
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
                                'amount' => (string)$remainingDue,
                                'booking_reference' => $bookingReference,
                                'registration_id' => $registrationId,
                                'event_id' => (int)$row['event_id'],
                            ]);
                        }
                    }

                    $message = 'Payment collected and auto-verified. You can proceed with check-in.';
                } else {
                    $upsert = $pdo->prepare("INSERT INTO event_payments (registration_id, amount, payment_type, amount_paid, remaining_amount, payment_method, transaction_id, screenshot, remarks, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending Verification')
                        ON DUPLICATE KEY UPDATE
                            amount = VALUES(amount),
                            payment_type = VALUES(payment_type),
                            amount_paid = VALUES(amount_paid),
                            remaining_amount = VALUES(remaining_amount),
                            payment_method = VALUES(payment_method),
                            transaction_id = VALUES(transaction_id),
                            screenshot = VALUES(screenshot),
                            remarks = VALUES(remarks),
                            status = 'Pending Verification'");
                    $upsert->execute([
                        $registrationId,
                        $remainingDue,
                        $paymentType,
                        $alreadyPaid,
                        $remainingDue,
                        $paymentMethod,
                        $transactionId,
                        $proofPath,
                        $remark,
                    ]);

                    $pdo->prepare("UPDATE event_registrations
                        SET payment_status = 'Pending Verification',
                            verification_status = 'Pending'
                        WHERE id = ?")
                        ->execute([$registrationId]);

                    $message = 'Payment submitted for verification. Approve/reject below, then proceed with check-in.';
                }
            } else {
                if ((int)$row['checkin_status'] !== 1) {
                    throw new RuntimeException('Registration is not checked in yet.');
                }

                $pdo->prepare("UPDATE event_registrations
                    SET checkin_status = 0,
                        checkin_time = NULL,
                        checkin_by_user_id = NULL,
                        checkin_by_user_name = NULL
                    WHERE id = ?")
                    ->execute([$registrationId]);
                $message = 'Check-in undone successfully.';
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to update check-in.';
            error_log('Event checkin failed: ' . $e->getMessage());
        }
    }
}

$registration = null;
if ($bookingReference !== '') {
    $stmt = $pdo->prepare("SELECT
        r.*,
        e.title AS event_title,
        e.event_type,
        COALESCE(d.event_date, e.event_date) AS selected_event_date,
        e.location,
        p.package_name,
        p.is_paid,
        p.allow_checkin_without_payment,
        COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS package_price_total,
        ep.id AS payment_id,
        ep.payment_type,
        ep.amount AS payment_amount,
        ep.amount_paid,
        ep.remaining_amount,
        ep.payment_method,
        ep.upi_id_used,
        ep.transaction_id,
        ep.screenshot,
        ep.remarks,
        ep.status AS payment_record_status
    FROM event_registrations r
    INNER JOIN events e ON e.id = r.event_id
    LEFT JOIN event_dates d ON d.id = r.event_date_id
    INNER JOIN event_packages p ON p.id = r.package_id
    LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
    LEFT JOIN event_payments ep ON ep.registration_id = r.id
    WHERE r.booking_reference = ?
    LIMIT 1");
    $stmt->execute([$bookingReference]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($registration) {
        $resolvedRef = trim((string)($registration['booking_reference'] ?? ''));
        if ($resolvedRef === '') {
            $resolvedRef = vs_event_assign_booking_reference($pdo, (int)$registration['id']);
            $registration['booking_reference'] = $resolvedRef;
        }

        $registration['event_date_display'] = vs_event_get_registration_date_display(
            $pdo,
            $registration,
            (string)($registration['selected_event_date'] ?? '')
        );

        if ($autoRawBtPrintRegistrationId > 0 && (int)$registration['id'] !== $autoRawBtPrintRegistrationId) {
            $autoRawBtPrint = false;
        }
    }

    if (!$registration && $error === '') {
        $error = 'No registration found for this booking reference.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Check-In</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
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
        body { margin:0; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color:var(--ink); background:#f4f6fb; }
        .admin-container { max-width:1480px; margin:0 auto; padding:24px 14px 34px; }
        .surface {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
            box-shadow: 0 8px 20px rgba(79, 39, 39, 0.08);
        }
        .panel { padding:16px; margin-bottom:12px; }
        .page-header {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            flex-wrap:wrap;
            margin:0 0 12px;
        }
        .page-title { margin:0; color:var(--maroon); font-size:1.4rem; }
        .notice { margin:0 0 12px; padding:10px 12px; border-radius:10px; font-weight:700; border:1px solid transparent; }
        .notice.ok { background:#e7f7ed; border-color:#c6e8d4; color:#1a6e3f; }
        .notice.err { background:#ffecee; border-color:#f8c2ca; color:#9f1f2e; }

        .search-grid { display:grid; grid-template-columns:minmax(340px,1fr) auto; gap:12px; align-items:end; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        .form-group.full { grid-column:1/-1; }
        label { color:#612229; font-size:0.84rem; font-weight:700; text-transform:uppercase; letter-spacing:0.03em; }
        input, select, textarea {
            width:100%;
            border:1px solid #dbc6c8;
            border-radius:10px;
            padding:9px 10px;
            font-size:0.92rem;
            color:#2f3a4b;
            background:#fff;
        }
        textarea { min-height:88px; resize:vertical; }
        .small { color:var(--muted); font-size:0.82rem; }

        .btn {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-height:40px;
            padding:9px 14px;
            border-radius:10px;
            border:1px solid transparent;
            text-decoration:none;
            font-size:0.9rem;
            font-weight:700;
            cursor:pointer;
            transition:0.15s ease all;
        }
        .btn:disabled { opacity:0.55; cursor:not-allowed; }
        .btn-primary { background:var(--maroon); color:#fff; border-color:var(--maroon); }
        .btn-primary:hover { background:var(--maroon-dark); border-color:var(--maroon-dark); }
        .btn-secondary { background:#6b7280; color:#fff; border-color:#6b7280; }
        .btn-secondary:hover { background:#525962; border-color:#525962; }
        .btn-success { background:#1a7f3d; color:#fff; border-color:#1a7f3d; }
        .btn-success:hover { background:#156734; border-color:#156734; }
        .btn-danger { background:#c63642; color:#fff; border-color:#c63642; }
        .btn-danger:hover { background:#a72b36; border-color:#a72b36; }
        .btn-info { background:#0f6f95; color:#fff; border-color:#0f6f95; }
        .btn-info:hover { background:#0a5673; border-color:#0a5673; }
        .scanner-actions { margin-top:8px; display:flex; gap:8px; flex-wrap:wrap; }
        .scanner-wrap { margin-top:10px; border:1px solid #f1d6d6; border-radius:10px; background:#fffaf8; padding:10px; }
        .scanner-video { width:100%; max-width:420px; border-radius:8px; border:1px solid #e0bebe; background:#000; aspect-ratio:4/3; object-fit:cover; }

        .head-bar {
            display:flex;
            flex-wrap:wrap;
            gap:12px;
            align-items:flex-start;
            justify-content:space-between;
            margin-bottom:12px;
        }
        .head-title { margin:0; color:var(--maroon); font-size:1.2rem; line-height:1.2; }
        .head-meta { margin:6px 0 0; color:var(--muted); font-size:0.92rem; }
        .chip-row { display:flex; flex-wrap:wrap; gap:6px; justify-content:flex-end; }
        .pill {
            display:inline-flex;
            align-items:center;
            border-radius:999px;
            padding:4px 10px;
            font-size:0.74rem;
            font-weight:700;
            border:1px solid transparent;
            white-space:nowrap;
        }
        .pill-success { background:#e8f8ee; border-color:#c4ebd3; color:#14703f; }
        .pill-warning { background:#fff6e5; border-color:#f6dfaf; color:#8f5d00; }
        .pill-danger { background:#ffecee; border-color:#f8c2ca; color:#a12536; }
        .pill-neutral { background:#f2f4f8; border-color:#dbe1ea; color:#4c5564; }

        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(175px,1fr)); gap:10px; margin-bottom:12px; }
        .stat-card { padding:12px; }
        .stat-label { margin:0; color:#6f7584; font-size:0.78rem; text-transform:uppercase; letter-spacing:0.07em; font-weight:700; }
        .stat-value { margin:6px 0 0; font-size:1.22rem; font-weight:800; color:#2a303b; line-height:1.15; }

        .details-layout { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px; }
        .info-table { width:100%; border-collapse:collapse; font-size:0.9rem; }
        .info-table th, .info-table td { padding:8px 7px; border-bottom:1px solid #f0e2e3; vertical-align:top; text-align:left; }
        .info-table th { width:38%; color:#6a2730; font-weight:700; background:#fff7f7; }
        .info-table td { color:#243142; word-break:break-word; }
        .verify-table th { width:auto !important; }
        .inline-link { color:#0f6f95; font-weight:700; text-decoration:none; }
        .inline-link:hover { text-decoration:underline; }

        .actions-panel { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .actions-panel form { margin:0; }
        .qr-box { display:flex; flex-direction:column; align-items:flex-start; gap:6px; }
        .qr-img { width:170px; height:170px; object-fit:contain; border:1px solid #ecd3d3; border-radius:10px; background:#fff; }
        .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:10px; align-items:end; }
        .input-readonly { background:#f8fafc; color:#415066; }

        @media (max-width:1024px) {
            .details-layout { grid-template-columns:1fr; }
            .search-grid { grid-template-columns:1fr; }
            .chip-row { justify-content:flex-start; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <div class="page-header">
        <h1 class="page-title">Event Check-In</h1>
        <a href="checkin-history.php" class="btn btn-secondary">Check-In History</a>
    </div>

    <?php if ($message !== ''): ?><div class="notice ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <section class="surface panel">
        <form method="get" class="search-grid" autocomplete="off" id="checkinSearchForm">
            <div class="form-group">
                <label>Booking Reference / QR Payload</label>
                <input type="text" id="bookingReferenceInput" name="booking_reference" value="<?php echo htmlspecialchars($searchInput); ?>" placeholder="VS-EVT-2026-0005 or VS-EVT-2026-0005|12|3" required>
                <span class="small">Scanner devices can paste QR payload directly in this field.</span>
                <div class="scanner-actions">
                    <button type="button" class="btn btn-secondary" id="openCameraScanBtn">Scan With Camera</button>
                    <button type="button" class="btn btn-danger" id="stopCameraScanBtn" style="display:none;">Stop Camera</button>
                </div>
                <div id="cameraScannerWrap" class="scanner-wrap" style="display:none;">
                    <video id="cameraScannerVideo" class="scanner-video" playsinline muted></video>
                    <div id="cameraScannerStatus" class="small" style="margin-top:6px;">Starting camera...</div>
                </div>
            </div>
            <div class="form-group" style="justify-content:flex-end;">
                <button type="submit" class="btn btn-primary">Search Booking</button>
            </div>
        </form>
    </section>

    <?php if ($registration): ?>
        <?php
        $isCheckedIn = (int)$registration['checkin_status'] === 1;
        $paymentStatus = strtolower(trim((string)$registration['payment_status']));
        $isPaid = ($paymentStatus === 'paid');
        $isPartialPaid = ($paymentStatus === 'partial paid');
        $isPaidPackage = ((int)($registration['is_paid'] ?? 1) === 1);
        $allowCheckinWithoutPayment = ((int)($registration['allow_checkin_without_payment'] ?? 0) === 1);
        $verificationStatus = strtolower(trim((string)($registration['verification_status'] ?? '')));
        $isCancelledBooking = ($paymentStatus === 'cancelled' || $verificationStatus === 'cancelled');
        $paymentRecordStatus = strtolower(trim((string)($registration['payment_record_status'] ?? '')));
        $packagePriceTotal = (float)($registration['package_price_total'] ?? 0);
        $totalAmount = round($packagePriceTotal * max((int)$registration['persons'], 1), 2);
        $paidSoFar = round(max((float)($registration['amount_paid'] ?? 0), 0), 2);
        if ($isPaid && $paidSoFar <= 0) {
            $paidSoFar = $totalAmount;
        }
        $remainingDue = round((float)($registration['remaining_amount'] ?? 0), 2);
        if ($remainingDue <= 0) {
            $remainingDue = round(max($totalAmount - $paidSoFar, 0), 2);
        }
        if ($isPaid) {
            $remainingDue = 0.0;
        }
        $hasPendingManualVerification = (
            $paymentStatus === 'pending verification' &&
            in_array($paymentRecordStatus, ['pending', 'pending verification'], true)
        );
        $checkinBlockedByPaymentPolicy = (
            !$isCheckedIn &&
            !$isCancelledBooking &&
            $isPaidPackage &&
            !$allowCheckinWithoutPayment &&
            !$isPaid
        );
        $canRegularCheckin = (
            !$isCheckedIn &&
            !$isCancelledBooking &&
            (!$isPaidPackage || $isPaid || $allowCheckinWithoutPayment)
        );
        $canCollectRemaining = (
            !$isCheckedIn &&
            $checkinBlockedByPaymentPolicy &&
            !$hasPendingManualVerification &&
            $remainingDue > 0
        );
        $canInlineVerifyPayment = (
            !$isCheckedIn &&
            !$isCancelledBooking &&
            $hasPendingManualVerification &&
            in_array(strtolower(trim((string)($registration['payment_method'] ?? ''))), ['manual upi', 'cash'], true) &&
            (int)($registration['payment_id'] ?? 0) > 0
        );
        $checkedInByLabel = trim((string)($registration['checkin_by_user_name'] ?? ''));
        if ($checkedInByLabel === '' && (int)($registration['checkin_by_user_id'] ?? 0) > 0) {
            $checkedInByLabel = 'User #' . (int)$registration['checkin_by_user_id'];
        }
        $paymentUpiAccount = trim((string)($registration['upi_id_used'] ?? ''));
        if ($paymentUpiAccount === '') {
            $paymentUpiAccount = trim((string)($registration['package_upi_id_snapshot'] ?? ''));
        }
        $qrUrl = '';
        if ($isPaid) {
            $qrPath = vs_event_ensure_registration_qr($pdo, (int)$registration['id']);
            if ($qrPath !== '') {
                $qrUrl = '../../event-qr-ticket.php?registration_id=' . (int)$registration['id'] . '&ref=' . urlencode((string)$registration['booking_reference']);
            }
        }

        $paymentStatusClass = 'pill-neutral';
        if ($isPaid) {
            $paymentStatusClass = 'pill-success';
        } elseif (in_array($paymentStatus, ['partial paid', 'pending', 'pending verification', 'unpaid'], true)) {
            $paymentStatusClass = 'pill-warning';
        } elseif (in_array($paymentStatus, ['failed', 'cancelled', 'rejected'], true)) {
            $paymentStatusClass = 'pill-danger';
        }

        $verificationStatusClass = 'pill-neutral';
        if (in_array($verificationStatus, ['approved', 'auto verified'], true)) {
            $verificationStatusClass = 'pill-success';
        } elseif ($verificationStatus === 'pending') {
            $verificationStatusClass = 'pill-warning';
        } elseif (in_array($verificationStatus, ['rejected', 'cancelled'], true)) {
            $verificationStatusClass = 'pill-danger';
        }

        $checkinStatusClass = $isCheckedIn ? 'pill-success' : ($isCancelledBooking ? 'pill-danger' : 'pill-warning');
        $paymentRecordStatusClass = 'pill-neutral';
        if (in_array($paymentRecordStatus, ['approved', 'paid', 'success'], true)) {
            $paymentRecordStatusClass = 'pill-success';
        } elseif (in_array($paymentRecordStatus, ['pending', 'pending verification'], true)) {
            $paymentRecordStatusClass = 'pill-warning';
        } elseif (in_array($paymentRecordStatus, ['rejected', 'failed'], true)) {
            $paymentRecordStatusClass = 'pill-danger';
        }
        ?>
        <section class="surface panel">
            <div class="head-bar">
                <div>
                    <h2 class="head-title">Booking Ref: <?php echo htmlspecialchars((string)$registration['booking_reference']); ?></h2>
                    <p class="head-meta">
                        Event: <?php echo htmlspecialchars((string)$registration['event_title']); ?> |
                        Package: <?php echo htmlspecialchars((string)$registration['package_name']); ?> |
                        Date: <?php echo htmlspecialchars((string)($registration['event_date_display'] ?? $registration['selected_event_date'])); ?>
                    </p>
                </div>
                <div class="chip-row">
                    <span class="pill <?php echo htmlspecialchars($checkinStatusClass); ?>"><?php echo $isCheckedIn ? 'Checked In' : 'Not Checked In'; ?></span>
                    <span class="pill <?php echo htmlspecialchars($paymentStatusClass); ?>">Payment: <?php echo htmlspecialchars((string)$registration['payment_status']); ?></span>
                    <span class="pill <?php echo htmlspecialchars($verificationStatusClass); ?>">Verification: <?php echo htmlspecialchars((string)($registration['verification_status'] ?? '-')); ?></span>
                </div>
            </div>
        </section>

        <div class="stats-grid">
            <div class="surface stat-card">
                <p class="stat-label">Total Amount</p>
                <p class="stat-value">Rs <?php echo number_format($totalAmount, 0, '.', ''); ?></p>
            </div>
            <div class="surface stat-card">
                <p class="stat-label">Paid So Far</p>
                <p class="stat-value">Rs <?php echo number_format($paidSoFar, 0, '.', ''); ?></p>
            </div>
            <div class="surface stat-card">
                <p class="stat-label">Remaining Due</p>
                <p class="stat-value">Rs <?php echo number_format($remainingDue, 0, '.', ''); ?></p>
            </div>
            <div class="surface stat-card">
                <p class="stat-label">Persons / Qty</p>
                <p class="stat-value"><?php echo (int)$registration['persons']; ?></p>
            </div>
        </div>

        <section class="surface panel">
            <h3 style="margin-top:0; color:#800000;">Check-In Actions</h3>
            <div class="actions-panel">
                <form method="post" style="display:inline;">
                    <input type="hidden" name="booking_reference" value="<?php echo htmlspecialchars((string)$registration['booking_reference']); ?>">
                    <input type="hidden" name="registration_id" value="<?php echo (int)$registration['id']; ?>">
                    <button type="submit" name="checkin_action" value="mark" class="btn btn-success" <?php echo !$canRegularCheckin ? 'disabled' : ''; ?>>Mark Check-In</button>
                </form>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="booking_reference" value="<?php echo htmlspecialchars((string)$registration['booking_reference']); ?>">
                    <input type="hidden" name="registration_id" value="<?php echo (int)$registration['id']; ?>">
                    <button type="submit" name="checkin_action" value="undo" class="btn btn-danger" <?php echo !$isCheckedIn ? 'disabled' : ''; ?>>Undo Check-In</button>
                </form>
                <?php if ($isCheckedIn): ?>
                    <button
                        type="button"
                        class="btn btn-info"
                        id="printCheckinSlipBtn"
                        data-booking-reference="<?php echo htmlspecialchars((string)$registration['booking_reference'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-name="<?php echo htmlspecialchars((string)$registration['name'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-phone="<?php echo htmlspecialchars((string)$registration['phone'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-event-title="<?php echo htmlspecialchars((string)$registration['event_title'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-package-name="<?php echo htmlspecialchars((string)$registration['package_name'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-persons="<?php echo (int)$registration['persons']; ?>"
                        data-event-date="<?php echo htmlspecialchars((string)($registration['event_date_display'] ?? $registration['selected_event_date']), ENT_QUOTES, 'UTF-8'); ?>"
                        data-checkin-time="<?php echo htmlspecialchars((string)($registration['checkin_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-checkin-by="<?php echo htmlspecialchars($checkedInByLabel, ENT_QUOTES, 'UTF-8'); ?>"
                        data-payment-status="<?php echo htmlspecialchars((string)$registration['payment_status'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-total-amount="<?php echo htmlspecialchars((string)number_format($totalAmount, 0, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-paid-amount="<?php echo htmlspecialchars((string)number_format($paidSoFar, 0, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-due-amount="<?php echo htmlspecialchars((string)number_format($remainingDue, 0, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                    >Print Check-In Slip</button>
                <?php endif; ?>
            </div>
            <?php if ($isCancelledBooking): ?>
                <p class="small" style="margin:8px 0 0; color:#b00020;">This booking is cancelled. Check-in is disabled.</p>
            <?php elseif ($checkinBlockedByPaymentPolicy): ?>
                <?php if ($hasPendingManualVerification): ?>
                    <p class="small" style="margin:8px 0 0;">Check-in is disabled until pending payment is verified.</p>
                <?php else: ?>
                    <p class="small" style="margin:8px 0 0;">Check-in is disabled by package policy until payment is collected and verified.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <?php if ($qrUrl !== ''): ?>
            <section class="surface panel">
                <h3 style="margin-top:0; color:#800000;">Entry QR</h3>
                <div class="qr-box">
                    <img class="qr-img" src="<?php echo htmlspecialchars($qrUrl); ?>" alt="Entry QR">
                    <span class="small">Show this QR at entry for quick validation.</span>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($canCollectRemaining): ?>
            <section class="surface panel" style="background:#fffdf5; border-color:#f3d7a4;">
                <h3 style="margin-top:0; color:#800000;"><?php echo $isMainAdminActor ? 'Collect Payment (Auto Verify)' : 'Collect Payment For Verification'; ?></h3>
                <p class="small" style="margin-top:0;">Submit manual payment proof here. After approval/rejection below, check-in action will be enabled/disabled accordingly.</p>
                <form method="post" enctype="multipart/form-data" autocomplete="off" class="form-grid">
                        <input type="hidden" name="booking_reference" value="<?php echo htmlspecialchars((string)$registration['booking_reference']); ?>">
                        <input type="hidden" name="registration_id" value="<?php echo (int)$registration['id']; ?>">
                        <input type="hidden" name="checkin_action" value="mark_partial_settlement">

                        <div class="form-group">
                            <label>Amount To Collect</label>
                            <input type="text" class="input-readonly" value="Rs <?php echo number_format($remainingDue, 0, '.', ''); ?>" readonly>
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
                            <button type="submit" class="btn btn-success" onclick="return confirm('<?php echo $isMainAdminActor ? 'Collect and auto-verify this payment?' : 'Submit this payment for verification?'; ?>');"><?php echo $isMainAdminActor ? 'Collect & Auto Verify' : 'Submit Payment For Verification'; ?></button>
                        </div>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($canInlineVerifyPayment): ?>
            <section class="surface panel" style="background:#f7fbff; border-color:#c9deee;">
                <h3 style="margin-top:0; color:#800000;">Pending Payment Verification</h3>
                <p class="small" style="margin-top:0;">Verify this payment now. Check-in will remain disabled until payment is approved.</p>
                <div style="overflow:auto;">
                    <table class="info-table verify-table" style="min-width:960px;">
                        <thead>
                        <tr>
                            <th>Payment ID</th>
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
                        <tr>
                            <td><?php echo (int)($registration['payment_id'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst((string)($registration['payment_type'] ?? 'full'))); ?></td>
                            <td><?php echo htmlspecialchars((string)($registration['payment_method'] ?? '')); ?></td>
                            <td><?php echo $paymentUpiAccount !== '' ? htmlspecialchars($paymentUpiAccount) : '-'; ?></td>
                            <td>Rs <?php echo number_format((float)($registration['payment_amount'] ?? 0), 0, '.', ''); ?></td>
                            <td>Rs <?php echo number_format((float)($registration['amount_paid'] ?? 0), 0, '.', ''); ?></td>
                            <td>Rs <?php echo number_format((float)($registration['remaining_amount'] ?? 0), 0, '.', ''); ?></td>
                            <td><?php echo htmlspecialchars((string)($registration['transaction_id'] ?? '')); ?></td>
                            <td><?php echo nl2br(htmlspecialchars((string)($registration['remarks'] ?? ''))); ?></td>
                            <td>
                                <?php if (!empty($registration['screenshot'])): ?>
                                    <a class="inline-link" href="../../<?php echo htmlspecialchars(ltrim((string)$registration['screenshot'], '/')); ?>" target="_blank">View Upload</a>
                                <?php else: ?>
                                    <span class="small">Not uploaded</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="pill <?php echo htmlspecialchars($paymentRecordStatusClass); ?>"><?php echo htmlspecialchars((string)($registration['payment_record_status'] ?? 'Pending')); ?></span></td>
                            <td>
                                <form method="post" style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <input type="hidden" name="booking_reference" value="<?php echo htmlspecialchars((string)$registration['booking_reference']); ?>">
                                    <input type="hidden" name="registration_id" value="<?php echo (int)$registration['id']; ?>">
                                    <input type="hidden" name="payment_id" value="<?php echo (int)($registration['payment_id'] ?? 0); ?>">
                                    <button type="submit" name="verify_action" value="approve" class="btn btn-success" onclick="return confirm('Approve this payment?');">Approve</button>
                                    <button type="submit" name="verify_action" value="reject" class="btn btn-danger" onclick="return confirm('Reject this payment?');">Reject</button>
                                </form>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
(function() {
    var searchForm = document.getElementById('checkinSearchForm');
    var bookingInput = document.getElementById('bookingReferenceInput');
    var openBtn = document.getElementById('openCameraScanBtn');
    var stopBtn = document.getElementById('stopCameraScanBtn');
    var scannerWrap = document.getElementById('cameraScannerWrap');
    var videoEl = document.getElementById('cameraScannerVideo');
    var statusEl = document.getElementById('cameraScannerStatus');
    var printBtn = document.getElementById('printCheckinSlipBtn');
    var AUTO_RAWBT_PRINT = <?php echo ($autoRawBtPrint && $registration) ? 'true' : 'false'; ?>;
    var RAWBT_TEXT_SCHEME_PREFIX = 'rawbt:';
    var THERMAL_PRINT_IFRAME_ID = 'event-checkin-thermal-print-frame';
    var THERMAL_RECEIPT_WIDTH_MM = 58;

    var detector = null;
    var cameraStream = null;
    var scanTimer = null;
    var scanBusy = false;

    function setScannerStatus(text) {
        if (statusEl) {
            statusEl.textContent = text;
        }
    }

    function stopCameraTracks() {
        if (!cameraStream) {
            return;
        }
        var tracks = cameraStream.getTracks();
        tracks.forEach(function(track) {
            try {
                track.stop();
            } catch (e) {}
        });
        cameraStream = null;
    }

    function stopScanner() {
        if (scanTimer) {
            window.clearInterval(scanTimer);
            scanTimer = null;
        }
        stopCameraTracks();
        if (videoEl) {
            try {
                videoEl.pause();
            } catch (e) {}
            videoEl.srcObject = null;
        }
        if (scannerWrap) {
            scannerWrap.style.display = 'none';
        }
        if (openBtn) {
            openBtn.style.display = '';
        }
        if (stopBtn) {
            stopBtn.style.display = 'none';
        }
    }

    function extractRawValue(barcode) {
        if (!barcode) {
            return '';
        }
        if (typeof barcode.rawValue === 'string') {
            return barcode.rawValue;
        }
        if (typeof barcode.data === 'string') {
            return barcode.data;
        }
        return '';
    }

    async function ensureDetector() {
        if (detector) {
            return detector;
        }
        if (!('BarcodeDetector' in window)) {
            throw new Error('Camera scanner is not supported in this browser.');
        }
        var formats = [];
        if (typeof window.BarcodeDetector.getSupportedFormats === 'function') {
            formats = await window.BarcodeDetector.getSupportedFormats();
        }
        if (formats.indexOf('qr_code') !== -1) {
            detector = new window.BarcodeDetector({ formats: ['qr_code'] });
        } else {
            detector = new window.BarcodeDetector();
        }
        return detector;
    }

    async function startScanner() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setScannerStatus('Camera access is not available in this browser.');
            if (scannerWrap) {
                scannerWrap.style.display = '';
            }
            return;
        }

        if (!bookingInput || !searchForm || !videoEl) {
            return;
        }

        try {
            await ensureDetector();
        } catch (e) {
            setScannerStatus((e && e.message) ? e.message : 'Scanner initialization failed.');
            if (scannerWrap) {
                scannerWrap.style.display = '';
            }
            return;
        }

        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false
            });
            videoEl.srcObject = cameraStream;
            await videoEl.play();

            if (scannerWrap) {
                scannerWrap.style.display = '';
            }
            if (openBtn) {
                openBtn.style.display = 'none';
            }
            if (stopBtn) {
                stopBtn.style.display = '';
            }
            setScannerStatus('Camera active. Point to booking QR code.');

            scanTimer = window.setInterval(async function() {
                if (!detector || !videoEl || videoEl.readyState < 2 || scanBusy) {
                    return;
                }
                scanBusy = true;
                try {
                    var results = await detector.detect(videoEl);
                    if (results && results.length > 0) {
                        var raw = extractRawValue(results[0]).trim();
                        if (raw !== '') {
                            bookingInput.value = raw;
                            setScannerStatus('QR matched. Opening booking details...');
                            stopScanner();
                            searchForm.submit();
                        }
                    }
                } catch (e) {
                    setScannerStatus('Unable to read QR. Keep camera steady.');
                } finally {
                    scanBusy = false;
                }
            }, 350);
        } catch (e) {
            setScannerStatus('Unable to open camera. Please allow permission.');
            if (scannerWrap) {
                scannerWrap.style.display = '';
            }
            if (openBtn) {
                openBtn.style.display = '';
            }
            if (stopBtn) {
                stopBtn.style.display = 'none';
            }
            stopCameraTracks();
        }
    }

    if (openBtn) {
        openBtn.addEventListener('click', function() {
            startScanner();
        });
    }
    if (stopBtn) {
        stopBtn.addEventListener('click', function() {
            stopScanner();
            setScannerStatus('Camera stopped.');
        });
    }
    window.addEventListener('beforeunload', function() {
        stopScanner();
    });

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getThermalPrintFrame() {
        var frame = document.getElementById(THERMAL_PRINT_IFRAME_ID);
        if (frame) {
            return frame;
        }
        frame = document.createElement('iframe');
        frame.id = THERMAL_PRINT_IFRAME_ID;
        frame.setAttribute('aria-hidden', 'true');
        frame.style.position = 'fixed';
        frame.style.width = '0';
        frame.style.height = '0';
        frame.style.border = '0';
        frame.style.opacity = '0';
        frame.style.pointerEvents = 'none';
        frame.style.right = '0';
        frame.style.bottom = '0';
        document.body.appendChild(frame);
        return frame;
    }

    function isAndroidDevice() {
        var ua = (window.navigator && window.navigator.userAgent) ? window.navigator.userAgent : '';
        return /android/i.test(ua);
    }

    function tryNavigateToRawBt(rawBtUrl) {
        try {
            window.location.href = rawBtUrl;
            return true;
        } catch (e) {
            return false;
        }
    }

    function buildRawBtCheckinText(data) {
        var lines = [
            'Vishnusudarshana',
            'Event Check-In Slip',
            '------------',
            'Booking Ref : ' + String(data.bookingReference || ''),
            'Name : ' + String(data.name || ''),
            'Phone : ' + String(data.phone || ''),
            'Event : ' + String(data.eventTitle || ''),
            'Package : ' + String(data.packageName || ''),
            'Persons : ' + String(data.persons || ''),
            'Event Date : ' + String(data.eventDate || ''),
            'Payment Status : ' + String(data.paymentStatus || ''),
            'Total Amount : Rs ' + String(data.totalAmount || '0'),
            'Paid Amount : Rs ' + String(data.paidAmount || '0'),
            'Unpaid Amount : Rs ' + String(data.dueAmount || '0'),
            'Check-In Time : ' + String(data.checkinTime || '--'),
            'Checked-In By : ' + String(data.checkinBy || '--'),
            '------------',
            'Printed: ' + new Date().toLocaleString(),
            '',
            '',
            ''
        ];
        return lines.join('\n');
    }

    function printViaRawBt(data) {
        if (!isAndroidDevice()) {
            return false;
        }
        var receiptText = buildRawBtCheckinText(data);
        var rawBtUrl = RAWBT_TEXT_SCHEME_PREFIX + encodeURIComponent(receiptText);
        return tryNavigateToRawBt(rawBtUrl);
    }

    function buildThermalSlipHtml(data) {
        var logoUrl = new URL('../../assets/images/logo/logomain.png', window.location.href).toString();
        return '<!DOCTYPE html>' +
            '<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">' +
            '<title>Event Check-In Slip</title>' +
            '<style>' +
            '@page{size:' + THERMAL_RECEIPT_WIDTH_MM + 'mm auto;margin:0;}' +
            'html,body{margin:0;padding:0;width:' + THERMAL_RECEIPT_WIDTH_MM + 'mm;background:#fff;color:#000;}' +
            'body{font-family:Arial,sans-serif;font-size:11px;line-height:1.25;padding:2.5mm 2.2mm;box-sizing:border-box;}' +
            '.center{text-align:center;}' +
            '.logo{display:block;width:100%;height:auto;margin:0 auto 1mm auto;}' +
            '.brand{font-weight:700;font-size:12px;}' +
            '.title{font-weight:700;margin-top:1mm;font-size:12px;}' +
            '.line{width:74%;margin:1mm auto;border-top:1px dashed #888;}' +
            '.row{display:flex;justify-content:space-between;gap:3px;margin-top:0.5mm;}' +
            '.label{font-weight:700;max-width:52%;}' +
            '.value{text-align:right;max-width:46%;word-break:break-word;}' +
            '.foot{margin-top:1.5mm;text-align:center;font-size:10px;color:#333;}' +
            '</style></head><body>' +
            '<img class="logo" src="' + escapeHtml(logoUrl) + '" alt="Vishnusudarshana Logo">' +
            '<div class="center brand">Vishnusudarshana</div>' +
            '<div class="center title">Event Check-In Slip</div>' +
            '<div class="line"></div>' +
            '<div class="row"><span class="label">Booking Ref</span><span class="value">' + escapeHtml(data.bookingReference) + '</span></div>' +
            '<div class="row"><span class="label">Name</span><span class="value">' + escapeHtml(data.name) + '</span></div>' +
            '<div class="row"><span class="label">Phone</span><span class="value">' + escapeHtml(data.phone) + '</span></div>' +
            '<div class="row"><span class="label">Event</span><span class="value">' + escapeHtml(data.eventTitle) + '</span></div>' +
            '<div class="row"><span class="label">Package</span><span class="value">' + escapeHtml(data.packageName) + '</span></div>' +
            '<div class="row"><span class="label">Persons</span><span class="value">' + escapeHtml(data.persons) + '</span></div>' +
            '<div class="row"><span class="label">Event Date</span><span class="value">' + escapeHtml(data.eventDate) + '</span></div>' +
            '<div class="row"><span class="label">Payment Status</span><span class="value">' + escapeHtml(data.paymentStatus || '--') + '</span></div>' +
            '<div class="row"><span class="label">Total Amount</span><span class="value">Rs ' + escapeHtml(data.totalAmount || '0') + '</span></div>' +
            '<div class="row"><span class="label">Paid Amount</span><span class="value">Rs ' + escapeHtml(data.paidAmount || '0') + '</span></div>' +
            '<div class="row"><span class="label">Unpaid Amount</span><span class="value">Rs ' + escapeHtml(data.dueAmount || '0') + '</span></div>' +
            '<div class="row"><span class="label">Check-In Time</span><span class="value">' + escapeHtml(data.checkinTime || '--') + '</span></div>' +
            '<div class="row"><span class="label">Checked-In By</span><span class="value">' + escapeHtml(data.checkinBy || '--') + '</span></div>' +
            '<div class="line"></div>' +
            '<div class="foot">Printed: ' + escapeHtml(new Date().toLocaleString()) + '</div>' +
            '</body></html>';
    }

    function getSlipDataFromButton(buttonEl) {
        return {
            bookingReference: buttonEl.getAttribute('data-booking-reference') || '',
            name: buttonEl.getAttribute('data-name') || '',
            phone: buttonEl.getAttribute('data-phone') || '',
            eventTitle: buttonEl.getAttribute('data-event-title') || '',
            packageName: buttonEl.getAttribute('data-package-name') || '',
            persons: buttonEl.getAttribute('data-persons') || '',
            eventDate: buttonEl.getAttribute('data-event-date') || '',
            checkinTime: buttonEl.getAttribute('data-checkin-time') || '',
            checkinBy: buttonEl.getAttribute('data-checkin-by') || '',
            paymentStatus: buttonEl.getAttribute('data-payment-status') || '',
            totalAmount: buttonEl.getAttribute('data-total-amount') || '0',
            paidAmount: buttonEl.getAttribute('data-paid-amount') || '0',
            dueAmount: buttonEl.getAttribute('data-due-amount') || '0'
        };
    }

    function printThermalSlipFromButton(buttonEl) {
        if (!buttonEl) {
            return;
        }
        var data = getSlipDataFromButton(buttonEl);

        try {
            var frame = getThermalPrintFrame();
            if (!frame || !frame.contentWindow || !frame.contentWindow.document) {
                return;
            }
            var doc = frame.contentWindow.document;
            doc.open();
            doc.write(buildThermalSlipHtml(data));
            doc.close();

            window.setTimeout(function() {
                try {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                } catch (e) {}
            }, 120);
        } catch (e) {}
    }

    if (printBtn) {
        printBtn.addEventListener('click', function() {
            printThermalSlipFromButton(printBtn);
        });
    }

    if (AUTO_RAWBT_PRINT && printBtn) {
        window.setTimeout(function() {
            var data = getSlipDataFromButton(printBtn);
            var rawBtStarted = printViaRawBt(data);
            if (!rawBtStarted) {
                printThermalSlipFromButton(printBtn);
            }
        }, 250);
    }
})();
</script>
</body>
</html>
