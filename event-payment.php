<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/event_module.php';

vs_event_ensure_tables($pdo);

$registrationId = isset($_GET['registration_id']) ? (int)$_GET['registration_id'] : (int)($_POST['registration_id'] ?? 0);
if ($registrationId <= 0) {
    header('Location: events.php');
    exit;
}

$regSql = "SELECT
    r.*,
    r.booking_reference,
    e.title AS event_title,
    e.slug AS event_slug,
    COALESCE(d.event_date, e.event_date) AS selected_event_date,
    e.location,
    p.package_name,
    COALESCE(NULLIF(pdp.price_total, 0), p.price) AS package_price,
    COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS price_total,
    p.advance_amount,
    p.payment_mode,
    p.is_paid,
    p.payment_methods,
    p.upi_id,
    p.upi_qr_image,
    r.package_upi_id_snapshot,
    r.package_upi_qr_snapshot,
    ep.id AS payment_id,
    ep.payment_method,
    ep.upi_id_used,
    ep.upi_qr_used,
    ep.transaction_id,
    ep.screenshot,
    ep.status AS payment_record_status,
    ep.amount AS payment_amount,
    ep.payment_type,
    ep.amount_paid,
    ep.remaining_amount
FROM event_registrations r
INNER JOIN events e ON e.id = r.event_id
LEFT JOIN event_dates d ON d.id = r.event_date_id
INNER JOIN event_packages p ON p.id = r.package_id
LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
LEFT JOIN event_payments ep ON ep.registration_id = r.id
WHERE r.id = ?
LIMIT 1";
$regStmt = $pdo->prepare($regSql);
$regStmt->execute([$registrationId]);
$registration = $regStmt->fetch(PDO::FETCH_ASSOC);
if (!$registration) {
    header('Location: events.php');
    exit;
}
$whatsappEnabledForEvent = vs_event_is_whatsapp_enabled($pdo, (int)($registration['event_id'] ?? 0));

$bookingReference = trim((string)($registration['booking_reference'] ?? ''));
if ($bookingReference === '') {
    $bookingReference = vs_event_assign_booking_reference($pdo, $registrationId);
    if ($bookingReference !== '') {
        $registration['booking_reference'] = $bookingReference;
    }
}
$registrationDisplayDate = vs_event_get_registration_date_display($pdo, $registration, (string)($registration['selected_event_date'] ?? ''));

$respondJson = static function (array $payload): void {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

$requestedChoice = trim((string)($_POST['payment_choice'] ?? $_GET['payment_choice'] ?? ''));
$packageData = [
    'price' => (float)($registration['package_price'] ?? 0),
    'price_total' => (float)($registration['price_total'] ?? 0),
    'advance_amount' => (float)($registration['advance_amount'] ?? 0),
    'payment_mode' => (string)($registration['payment_mode'] ?? 'full'),
];
$paymentRow = [
    'amount_paid' => (float)($registration['amount_paid'] ?? 0),
    'remaining_amount' => (float)($registration['remaining_amount'] ?? 0),
];
$paymentContext = vs_event_build_registration_payment_context($registration, $packageData, $paymentRow, $requestedChoice);
$totalAmount = (float)$paymentContext['total_amount'];
$amountPaid = (float)$paymentContext['amount_paid'];
$dueNow = (float)$paymentContext['due_now'];
$remainingBeforePayment = (float)$paymentContext['remaining_before_payment'];
$paymentChoice = (string)$paymentContext['payment_choice'];
$paymentType = (string)$paymentContext['payment_type'];

$optionalFullPlan = vs_event_build_registration_payment_context($registration, $packageData, $paymentRow, 'full');
$optionalAdvancePlan = vs_event_build_registration_payment_context($registration, $packageData, $paymentRow, 'advance');
$isRemainingStage = (strtolower((string)$registration['payment_status']) === 'partial paid' && $remainingBeforePayment > 0);
$packageIsPaid = vs_event_is_package_paid($registration);
$allowedPaymentMethods = vs_event_payment_methods_from_csv((string)($registration['payment_methods'] ?? ''), $packageIsPaid);
$allowRazorpay = in_array('razorpay', $allowedPaymentMethods, true);
$allowUpi = in_array('upi', $allowedPaymentMethods, true);
$allowCash = in_array('cash', $allowedPaymentMethods, true);
$registrationUpiSnapshotId = trim((string)($registration['package_upi_id_snapshot'] ?? ''));
$registrationUpiSnapshotQr = trim((string)($registration['package_upi_qr_snapshot'] ?? ''));
$packageUpiId = trim((string)($registration['upi_id'] ?? ''));
$packageUpiQr = trim((string)($registration['upi_qr_image'] ?? ''));
$effectiveUpiId = $registrationUpiSnapshotId !== '' ? $registrationUpiSnapshotId : $packageUpiId;
$effectiveUpiQr = $registrationUpiSnapshotQr !== '' ? $registrationUpiSnapshotQr : $packageUpiQr;
if ($effectiveUpiId === '') {
    $effectiveUpiId = (string)(getenv('EVENT_UPI_ID') ?: getenv('EVENT_MANUAL_UPI') ?: '');
}

if (!$packageIsPaid) {
    try {
        if ((string)$registration['payment_status'] !== 'Paid') {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE event_registrations SET payment_status = 'Paid', verification_status = 'Auto Verified' WHERE id = ?")
                ->execute([$registrationId]);
            $pdo->prepare("INSERT INTO event_payments (registration_id, amount, payment_type, amount_paid, remaining_amount, payment_method, upi_id_used, upi_qr_used, transaction_id, screenshot, status)
                VALUES (?, 0, 'full', 0, 0, 'Free', NULL, NULL, 'FREE', '', 'Paid')
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
                    status = VALUES(status)")
                ->execute([$registrationId]);
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Free event auto-confirm failed: ' . $e->getMessage());
    }
    header('Location: event-booking-confirmation.php?registration_id=' . $registrationId . '&status=paid');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_order') {
    if (!$allowRazorpay) {
        $respondJson(['success' => false, 'message' => 'Razorpay is not enabled for this package.']);
    }
    if ((string)$registration['payment_status'] === 'Paid') {
        $respondJson(['success' => false, 'message' => 'Payment is already completed.']);
    }
    if (strtolower((string)$registration['payment_status']) === 'pending verification') {
        $respondJson(['success' => false, 'message' => 'Payment is already submitted and pending verification.']);
    }

    $requestChoice = trim((string)($_POST['payment_choice'] ?? $paymentChoice));
    $paymentContext = vs_event_build_registration_payment_context($registration, $packageData, $paymentRow, $requestChoice);
    $dueNow = (float)$paymentContext['due_now'];
    $paymentType = (string)$paymentContext['payment_type'];
    if ($dueNow <= 0) {
        $respondJson(['success' => false, 'message' => 'No payable amount remaining for this booking.']);
    }

    $keys = vs_event_load_razorpay_keys();
    if ($keys['key_id'] === '' || $keys['key_secret'] === '') {
        $respondJson(['success' => false, 'message' => 'Razorpay keys are not configured.']);
    }

    try {
        require_once __DIR__ . '/vendor/autoload.php';
        $api = new Razorpay\Api\Api($keys['key_id'], $keys['key_secret']);

        $orderData = [
            'receipt' => 'event_reg_' . $registrationId . '_' . time(),
            'amount' => (int)round($dueNow * 100),
            'currency' => 'INR',
            'payment_capture' => 1,
        ];
        $order = $api->order->create($orderData);
        $orderId = (string)$order['id'];

        $upsert = $pdo->prepare("INSERT INTO event_payments (registration_id, amount, payment_type, amount_paid, remaining_amount, payment_method, upi_id_used, upi_qr_used, transaction_id, screenshot, status)
            VALUES (?, ?, ?, ?, ?, 'Razorpay', NULL, NULL, ?, '', 'Created')
            ON DUPLICATE KEY UPDATE
                amount = VALUES(amount),
                payment_type = VALUES(payment_type),
                amount_paid = VALUES(amount_paid),
                remaining_amount = VALUES(remaining_amount),
                payment_method = VALUES(payment_method),
                upi_id_used = VALUES(upi_id_used),
                upi_qr_used = VALUES(upi_qr_used),
                transaction_id = VALUES(transaction_id),
                screenshot = '',
                status = 'Created'");
        $upsert->execute([
            $registrationId,
            $dueNow,
            $paymentType,
            (float)$paymentContext['amount_paid'],
            (float)$paymentContext['remaining_before_payment'],
            $orderId,
        ]);

        $respondJson([
            'success' => true,
            'order_id' => $orderId,
            'key_id' => $keys['key_id'],
            'amount' => (int)round($dueNow * 100),
            'name' => (string)$registration['name'],
            'email' => '',
            'contact' => (string)$registration['phone'],
            'payment_choice' => (string)$paymentContext['payment_choice'],
        ]);
    } catch (Throwable $e) {
        error_log('Event Razorpay order creation failed: ' . $e->getMessage());
        $respondJson(['success' => false, 'message' => 'Unable to create Razorpay order.']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_razorpay') {
    if (!$allowRazorpay) {
        $respondJson(['success' => false, 'message' => 'Razorpay is not enabled for this package.']);
    }
    if ((string)$registration['payment_status'] === 'Paid') {
        $respondJson(['success' => true, 'redirect' => 'event-booking-confirmation.php?registration_id=' . $registrationId . '&status=paid']);
    }

    $orderId = trim((string)($_POST['razorpay_order_id'] ?? ''));
    $paymentId = trim((string)($_POST['razorpay_payment_id'] ?? ''));
    $signature = trim((string)($_POST['razorpay_signature'] ?? ''));
    $requestChoice = trim((string)($_POST['payment_choice'] ?? $paymentChoice));
    if ($orderId === '' || $paymentId === '' || $signature === '') {
        $respondJson(['success' => false, 'message' => 'Missing Razorpay payment details.']);
    }

    $keys = vs_event_load_razorpay_keys();
    if ($keys['key_id'] === '' || $keys['key_secret'] === '') {
        $respondJson(['success' => false, 'message' => 'Razorpay keys are not configured.']);
    }

    try {
        require_once __DIR__ . '/vendor/autoload.php';
        $api = new Razorpay\Api\Api($keys['key_id'], $keys['key_secret']);
        $api->utility->verifyPaymentSignature([
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
        ]);

        $pdo->beginTransaction();
        $lockStmt = $pdo->prepare("SELECT
                r.*,
                p.price,
                COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS price_total,
                p.advance_amount,
                p.payment_mode,
                p.is_paid,
                p.payment_methods,
                e.title AS event_title,
                COALESCE(d.event_date, e.event_date) AS selected_event_date,
                p.package_name,
                ep.amount_paid,
                ep.remaining_amount
            FROM event_registrations r
            INNER JOIN event_packages p ON p.id = r.package_id
            INNER JOIN events e ON e.id = r.event_id
            LEFT JOIN event_dates d ON d.id = r.event_date_id
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

        $lockedPackageData = [
            'price' => (float)($locked['price'] ?? 0),
            'price_total' => (float)($locked['price_total'] ?? 0),
            'advance_amount' => (float)($locked['advance_amount'] ?? 0),
            'payment_mode' => (string)($locked['payment_mode'] ?? 'full'),
        ];
        $lockedPaymentRow = [
            'amount_paid' => (float)($locked['amount_paid'] ?? 0),
            'remaining_amount' => (float)($locked['remaining_amount'] ?? 0),
        ];
        $lockedContext = vs_event_build_registration_payment_context($locked, $lockedPackageData, $lockedPaymentRow, $requestChoice);
        $dueNow = (float)$lockedContext['due_now'];
        if ($dueNow <= 0) {
            $pdo->commit();
            $respondJson(['success' => true, 'redirect' => 'event-booking-confirmation.php?registration_id=' . $registrationId . '&status=paid']);
        }

        $newPaid = round((float)$lockedContext['amount_paid'] + $dueNow, 2);
        $totalAmount = (float)$lockedContext['total_amount'];
        if ($newPaid > $totalAmount) {
            $newPaid = $totalAmount;
        }
        $remainingAfter = round(max($totalAmount - $newPaid, 0), 2);
        $newPaymentStatus = $remainingAfter > 0 ? 'Partial Paid' : 'Paid';
        $newVerificationStatus = 'Auto Verified';
        $newPaymentType = $remainingAfter > 0 ? (string)$lockedContext['payment_type'] : 'full';

        $upsert = $pdo->prepare("INSERT INTO event_payments (registration_id, amount, payment_type, amount_paid, remaining_amount, payment_method, upi_id_used, upi_qr_used, transaction_id, screenshot, status)
            VALUES (?, ?, ?, ?, ?, 'Razorpay', NULL, NULL, ?, '', 'Paid')
            ON DUPLICATE KEY UPDATE
                amount = VALUES(amount),
                payment_type = VALUES(payment_type),
                amount_paid = VALUES(amount_paid),
                remaining_amount = VALUES(remaining_amount),
                payment_method = VALUES(payment_method),
                upi_id_used = VALUES(upi_id_used),
                upi_qr_used = VALUES(upi_qr_used),
                transaction_id = VALUES(transaction_id),
                screenshot = '',
                status = 'Paid'");
        $upsert->execute([$registrationId, $dueNow, $newPaymentType, $newPaid, $remainingAfter, $paymentId]);

        $pdo->prepare("UPDATE event_registrations SET payment_status = ?, verification_status = ? WHERE id = ?")
            ->execute([$newPaymentStatus, $newVerificationStatus, $registrationId]);

        if ($whatsappEnabledForEvent) {
            if ($remainingAfter <= 0) {
                $qrCodePath = vs_event_ensure_registration_qr($pdo, $registrationId);
                vs_event_send_whatsapp_notice('ticket_delivery', (string)$locked['phone'], [
                    'name' => (string)$locked['name'],
                    'event_name' => (string)$locked['event_title'],
                    'package_name' => (string)$locked['package_name'],
                    'event_date' => vs_event_get_registration_date_display($pdo, $locked, (string)($locked['selected_event_date'] ?? '')),
                    'amount' => (string)$totalAmount,
                    'booking_reference' => (string)($locked['booking_reference'] ?? ''),
                    'registration_id' => $registrationId,
                    'event_id' => (int)($locked['event_id'] ?? 0),
                    'qr_code_path' => $qrCodePath,
                ]);
            } else {
                vs_event_send_whatsapp_notice('payment_successful', (string)$locked['phone'], [
                    'name' => (string)$locked['name'],
                    'event_name' => (string)$locked['event_title'],
                    'package_name' => (string)$locked['package_name'],
                    'event_date' => vs_event_get_registration_date_display($pdo, $locked, (string)($locked['selected_event_date'] ?? '')),
                    'amount' => (string)$dueNow,
                    'booking_reference' => (string)($locked['booking_reference'] ?? ''),
                    'registration_id' => $registrationId,
                    'event_id' => (int)($locked['event_id'] ?? 0),
                ]);
            }
        }

        $pdo->commit();
        $redirectStatus = $remainingAfter <= 0 ? 'paid' : 'partial';
        $respondJson(['success' => true, 'redirect' => 'event-booking-confirmation.php?registration_id=' . $registrationId . '&status=' . $redirectStatus]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Event Razorpay verification failed: ' . $e->getMessage());
        $respondJson(['success' => false, 'message' => 'Payment verification failed.']);
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'manual_submit') {
    if (!$allowUpi) {
        $errors[] = 'Manual UPI is not enabled for this package.';
    }
    if ($allowUpi && trim($effectiveUpiId) === '') {
        $errors[] = 'UPI ID is not configured for this package. Please contact admin.';
    }
    if ($allowUpi && trim($effectiveUpiQr) === '') {
        $errors[] = 'UPI QR is not configured for this package. Please contact admin.';
    }
    if ((string)$registration['payment_status'] === 'Paid') {
        header('Location: event-booking-confirmation.php?registration_id=' . $registrationId . '&status=paid');
        exit;
    }
    if (strtolower((string)$registration['payment_status']) === 'pending verification') {
        $errors[] = 'Payment is already submitted and pending verification.';
    }

    $requestChoice = trim((string)($_POST['payment_choice'] ?? $paymentChoice));
    $manualContext = vs_event_build_registration_payment_context($registration, $packageData, $paymentRow, $requestChoice);
    $dueNow = (float)$manualContext['due_now'];
    if ($dueNow <= 0) {
        $errors[] = 'No payable amount remaining for this booking.';
    }

    $transactionId = trim((string)($_POST['manual_transaction_id'] ?? ''));
    if ($transactionId === '') {
        $errors[] = 'Transaction ID is required for manual UPI payment.';
    }

    $screenshotPath = null;
    if (isset($_FILES['manual_screenshot']) && (int)($_FILES['manual_screenshot']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $screenshotPath = vs_event_store_upload($_FILES['manual_screenshot'], 'payments', ['jpg', 'jpeg', 'png', 'webp']);
        if ($screenshotPath === null) {
            $errors[] = 'Invalid screenshot upload. Allowed formats: jpg, jpeg, png, webp.';
        }
    } else {
        $errors[] = 'Payment screenshot is required for manual verification.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $upsert = $pdo->prepare("INSERT INTO event_payments (registration_id, amount, payment_type, amount_paid, remaining_amount, payment_method, upi_id_used, upi_qr_used, transaction_id, screenshot, status)
                VALUES (?, ?, ?, ?, ?, 'Manual UPI', ?, ?, ?, ?, 'Pending Verification')
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
                    status = 'Pending Verification'");
            $upsert->execute([
                $registrationId,
                $dueNow,
                (string)$manualContext['payment_type'],
                (float)$manualContext['amount_paid'],
                (float)$manualContext['remaining_before_payment'],
                trim($effectiveUpiId) !== '' ? trim($effectiveUpiId) : null,
                trim($effectiveUpiQr) !== '' ? trim($effectiveUpiQr) : null,
                $transactionId,
                $screenshotPath,
            ]);

            $pdo->prepare("UPDATE event_registrations SET payment_status = 'Pending Verification', verification_status = 'Pending' WHERE id = ?")
                ->execute([$registrationId]);

            if ($whatsappEnabledForEvent) {
                vs_event_send_whatsapp_notice('payment_pending_verification', (string)$registration['phone'], [
                    'name' => (string)$registration['name'],
                    'event_name' => (string)$registration['event_title'],
                    'package_name' => (string)$registration['package_name'],
                    'event_date' => $registrationDisplayDate,
                    'amount' => (string)$dueNow,
                    'booking_reference' => (string)($registration['booking_reference'] ?? ''),
                    'registration_id' => $registrationId,
                    'event_id' => (int)($registration['event_id'] ?? 0),
                ]);
            }

            $pdo->commit();
            header('Location: event-booking-confirmation.php?registration_id=' . $registrationId . '&status=pending');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Manual UPI submit failed: ' . $e->getMessage());
            $errors[] = 'Unable to submit manual payment details. Please try again.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cash_submit') {
    if (!$allowCash) {
        $errors[] = 'Cash payment is not enabled for this package.';
    }
    if ((string)$registration['payment_status'] === 'Paid') {
        header('Location: event-booking-confirmation.php?registration_id=' . $registrationId . '&status=paid');
        exit;
    }
    if (strtolower((string)$registration['payment_status']) === 'pending verification') {
        $errors[] = 'Payment is already submitted and pending verification.';
    }

    $requestChoice = trim((string)($_POST['payment_choice'] ?? $paymentChoice));
    $cashContext = vs_event_build_registration_payment_context($registration, $packageData, $paymentRow, $requestChoice);
    $dueNow = (float)$cashContext['due_now'];
    if ($dueNow <= 0) {
        $errors[] = 'No payable amount remaining for this booking.';
    }

    $cashReference = trim((string)($_POST['cash_reference'] ?? ''));
    if ($cashReference === '') {
        $cashReference = 'CASH-' . date('YmdHis');
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $upsert = $pdo->prepare("INSERT INTO event_payments (registration_id, amount, payment_type, amount_paid, remaining_amount, payment_method, upi_id_used, upi_qr_used, transaction_id, screenshot, status)
                VALUES (?, ?, ?, ?, ?, 'Cash', NULL, NULL, ?, '', 'Pending Verification')
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
                    status = 'Pending Verification'");
            $upsert->execute([
                $registrationId,
                $dueNow,
                (string)$cashContext['payment_type'],
                (float)$cashContext['amount_paid'],
                (float)$cashContext['remaining_before_payment'],
                $cashReference,
            ]);

            $pdo->prepare("UPDATE event_registrations SET payment_status = 'Pending Verification', verification_status = 'Pending' WHERE id = ?")
                ->execute([$registrationId]);

            if ($whatsappEnabledForEvent) {
                vs_event_send_whatsapp_notice('payment_pending_verification', (string)$registration['phone'], [
                    'name' => (string)$registration['name'],
                    'event_name' => (string)$registration['event_title'],
                    'package_name' => (string)$registration['package_name'],
                    'event_date' => $registrationDisplayDate,
                    'amount' => (string)$dueNow,
                    'booking_reference' => (string)($registration['booking_reference'] ?? ''),
                    'registration_id' => $registrationId,
                    'event_id' => (int)($registration['event_id'] ?? 0),
                ]);
            }

            $pdo->commit();
            header('Location: event-booking-confirmation.php?registration_id=' . $registrationId . '&status=pending');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Cash submit failed: ' . $e->getMessage());
            $errors[] = 'Unable to submit cash payment details. Please try again.';
        }
    }
}

$pageTitle = 'Event Payment';
require_once 'header.php';

$upiId = trim($effectiveUpiId);
$upiQrImage = trim($effectiveUpiQr);
$upiIntentBase = '';
if ($upiId !== '') {
    $upiNote = 'Event Booking ' . (string)($registration['booking_reference'] ?? ('REG-' . $registrationId));
    $upiIntentBase = 'upi://pay?pa=' . rawurlencode($upiId)
        . '&pn=' . rawurlencode('Vishnusudarshana')
        . '&tn=' . rawurlencode($upiNote)
        . '&cu=INR&am=';
}
$isPaid = ((string)$registration['payment_status'] === 'Paid');
$showChoiceSelector = ((string)$packageData['payment_mode'] === 'optional' && !$isRemainingStage && !$isPaid);
$allowedMethodsText = implode(', ', array_map('ucfirst', $allowedPaymentMethods));
?>
<main class="event-payment-main" style="background-color:var(--cream-bg);">
    <section class="event-payment-wrap">
        <a href="event-detail.php?slug=<?php echo urlencode((string)$registration['event_slug']); ?>" class="back-link">&larr; Back to Event</a>
        <div class="card">
            <h1>Complete Payment</h1>
            <div class="summary-grid">
                <div><strong>Registration ID:</strong> <?php echo (int)$registrationId; ?></div>
                <div><strong>Booking Reference:</strong> <?php echo htmlspecialchars((string)($registration['booking_reference'] ?? '')); ?></div>
                <div><strong>Event:</strong> <?php echo htmlspecialchars((string)$registration['event_title']); ?></div>
                <div><strong>Event Date:</strong> <?php echo htmlspecialchars($registrationDisplayDate); ?></div>
                <div><strong>Package:</strong> <?php echo htmlspecialchars((string)$registration['package_name']); ?></div>
                <div><strong>Persons:</strong> <?php echo (int)$registration['persons']; ?></div>
                <div><strong>Total Amount:</strong> Rs. <?php echo number_format($totalAmount, 0, '.', ''); ?></div>
                <div><strong>Amount Paid:</strong> Rs. <?php echo number_format($amountPaid, 0, '.', ''); ?></div>
                <div><strong>Remaining Amount:</strong> Rs. <?php echo number_format($remainingBeforePayment, 0, '.', ''); ?></div>
                <div><strong>Pay Now:</strong> Rs. <span id="payNowAmount"><?php echo number_format($dueNow, 0, '.', ''); ?></span></div>
                <div><strong>Payment Status:</strong> <?php echo htmlspecialchars((string)$registration['payment_status']); ?></div>
                <div><strong>Payment Mode:</strong> <?php echo htmlspecialchars(ucfirst((string)$packageData['payment_mode'])); ?></div>
                <div><strong>Allowed Methods:</strong> <?php echo htmlspecialchars($allowedMethodsText); ?></div>
            </div>
            <?php if ($isRemainingStage): ?><p class="small">Advance payment already received. Please complete the remaining amount.</p><?php endif; ?>
        </div>

        <?php if ($isPaid): ?>
            <div class="card success-box">
                <h3>Payment already completed.</h3>
                <a class="btn-main" href="event-booking-confirmation.php?registration_id=<?php echo $registrationId; ?>&status=paid">View Confirmation</a>
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?><div class="card notice err"><?php foreach ($errors as $error) { echo '<div>' . htmlspecialchars((string)$error) . '</div>'; } ?></div><?php endif; ?>
            <div class="payment-grid">
                <?php if ($showChoiceSelector): ?>
                    <div class="card" style="grid-column:1/-1;">
                        <div class="form-group" style="max-width:380px;margin:0;">
                            <label>Choose Payment Type</label>
                            <select id="paymentChoice">
                                <option value="full" <?php echo ($paymentChoice === 'full') ? 'selected' : ''; ?>>Pay Full Amount (Rs. <?php echo number_format((float)$optionalFullPlan['due_now'], 0, '.', ''); ?>)</option>
                                <option value="advance" <?php echo ($paymentChoice === 'advance') ? 'selected' : ''; ?>>Pay Advance (Rs. <?php echo number_format((float)$optionalAdvancePlan['due_now'], 0, '.', ''); ?>)</option>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($allowRazorpay): ?>
                    <div class="card">
                        <h3>Razorpay</h3>
                        <p>Pay instantly via Razorpay using UPI, card, net banking or wallet.</p>
                        <button id="razorpayPayBtn" class="btn-main">Pay Rs. <span id="rzpAmountText"><?php echo number_format($dueNow, 0, '.', ''); ?></span> With Razorpay</button>
                        <p id="razorpayMsg" class="small"></p>
                    </div>
                <?php endif; ?>

                <?php if ($allowUpi): ?>
                    <div class="card">
                        <h3>Manual UPI</h3>
                        <p>Pay using your UPI app and upload screenshot for admin verification.</p>
                        <p><strong>UPI ID:</strong> <?php echo htmlspecialchars($upiId); ?></p>
                        <?php if ($upiQrImage !== ''): ?>
                            <div style="margin:8px 0 10px;">
                                <img src="<?php echo htmlspecialchars($upiQrImage); ?>" alt="UPI QR" style="width:180px;max-width:100%;height:auto;border:1px solid #e0bebe;border-radius:8px;">
                            </div>
                        <?php endif; ?>
                        <?php if ($upiIntentBase !== ''): ?>
                            <a href="<?php echo htmlspecialchars($upiIntentBase . number_format($dueNow, 0, '.', '')); ?>" class="btn-main" id="upiIntentBtn" style="display:inline-block;margin-bottom:10px;">
                                Pay Rs. <span id="upiIntentAmountText"><?php echo number_format($dueNow, 0, '.', ''); ?></span> In UPI App
                            </a>
                        <?php endif; ?>
                        <form method="post" enctype="multipart/form-data" autocomplete="off">
                            <input type="hidden" name="action" value="manual_submit">
                            <input type="hidden" name="registration_id" value="<?php echo $registrationId; ?>">
                            <input type="hidden" id="manualPaymentChoice" name="payment_choice" value="<?php echo htmlspecialchars($paymentChoice); ?>">
                            <div class="form-group"><label>Transaction ID</label><input type="text" name="manual_transaction_id" required></div>
                            <div class="form-group"><label>Payment Screenshot</label><input type="file" name="manual_screenshot" accept=".jpg,.jpeg,.png,.webp" required></div>
                            <button type="submit" class="btn-main">Submit For Verification</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($allowCash): ?>
                    <div class="card">
                        <h3>Cash</h3>
                        <p>Choose cash payment and submit for admin verification.</p>
                        <form method="post" autocomplete="off">
                            <input type="hidden" name="action" value="cash_submit">
                            <input type="hidden" name="registration_id" value="<?php echo $registrationId; ?>">
                            <input type="hidden" id="cashPaymentChoice" name="payment_choice" value="<?php echo htmlspecialchars($paymentChoice); ?>">
                            <div class="form-group"><label>Cash Reference (optional)</label><input type="text" name="cash_reference" placeholder="Receipt no / note"></div>
                            <button type="submit" class="btn-main">Submit for Verification</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if (!$allowRazorpay && !$allowUpi && !$allowCash): ?>
                    <div class="card">
                        <p>No payment method is available for this package. Please contact admin.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php if ($allowRazorpay): ?><script src="https://checkout.razorpay.com/v1/checkout.js"></script><?php endif; ?>
<script>
(function() {
    const payBtn = document.getElementById('razorpayPayBtn');
    const msgEl = document.getElementById('razorpayMsg');
    const paymentChoiceEl = document.getElementById('paymentChoice');
    const manualChoiceEl = document.getElementById('manualPaymentChoice');
    const cashChoiceEl = document.getElementById('cashPaymentChoice');
    const payNowAmountEl = document.getElementById('payNowAmount');
    const rzpAmountText = document.getElementById('rzpAmountText');
    const upiIntentBtn = document.getElementById('upiIntentBtn');
    const upiIntentAmountText = document.getElementById('upiIntentAmountText');
    const upiIntentBase = <?php echo json_encode($upiIntentBase); ?>;
    const optionalAmounts = {
        full: <?php echo json_encode((float)$optionalFullPlan['due_now']); ?>,
        advance: <?php echo json_encode((float)$optionalAdvancePlan['due_now']); ?>
    };

    function selectedChoice() {
        if (!paymentChoiceEl) return '<?php echo addslashes($paymentChoice); ?>';
        return paymentChoiceEl.value || 'full';
    }

    function refreshAmounts() {
        const choice = selectedChoice();
        const amount = (choice in optionalAmounts) ? optionalAmounts[choice] : <?php echo json_encode((float)$dueNow); ?>;
        const displayAmount = String(Math.round(Number(amount) || 0));
        if (payNowAmountEl) payNowAmountEl.textContent = displayAmount;
        if (rzpAmountText) rzpAmountText.textContent = displayAmount;
        if (manualChoiceEl) manualChoiceEl.value = choice;
        if (cashChoiceEl) cashChoiceEl.value = choice;
        if (upiIntentAmountText) upiIntentAmountText.textContent = displayAmount;
        if (upiIntentBtn && upiIntentBase) upiIntentBtn.href = upiIntentBase + encodeURIComponent(displayAmount);
    }
    refreshAmounts();
    if (paymentChoiceEl) paymentChoiceEl.addEventListener('change', refreshAmounts);

    if (!payBtn) {
        return;
    }

    function postForm(data) {
        const body = new URLSearchParams(data);
        return fetch('event-payment.php?registration_id=<?php echo $registrationId; ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function(r) { return r.json(); });
    }

    payBtn.addEventListener('click', function() {
        const choice = selectedChoice();
        payBtn.disabled = true;
        msgEl.textContent = 'Creating Razorpay order...';

        postForm({ action: 'create_order', registration_id: '<?php echo $registrationId; ?>', payment_choice: choice })
            .then(function(res) {
                if (!res.success) throw new Error(res.message || 'Unable to create payment order.');
                const options = {
                    key: res.key_id,
                    amount: res.amount,
                    currency: 'INR',
                    name: 'Vishnusudarshana',
                    description: 'Event Registration Payment',
                    order_id: res.order_id,
                    prefill: { name: res.name || '', email: res.email || '', contact: res.contact || '' },
                    theme: { color: '#800000' },
                    handler: function(response) {
                        msgEl.textContent = 'Verifying payment...';
                        postForm({
                            action: 'verify_razorpay',
                            registration_id: '<?php echo $registrationId; ?>',
                            payment_choice: choice,
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_signature: response.razorpay_signature
                        }).then(function(verifyRes) {
                            if (verifyRes.success && verifyRes.redirect) {
                                window.location.href = verifyRes.redirect;
                            } else {
                                throw new Error(verifyRes.message || 'Payment verification failed.');
                            }
                        }).catch(function(err) {
                            msgEl.textContent = err.message;
                            payBtn.disabled = false;
                        });
                    }
                };
                const rzp = new Razorpay(options);
                rzp.on('payment.failed', function(resp) {
                    const reason = (resp && resp.error && resp.error.description) ? resp.error.description : 'Payment failed. Please try again.';
                    msgEl.textContent = reason;
                    payBtn.disabled = false;
                });
                rzp.open();
                msgEl.textContent = '';
                payBtn.disabled = false;
            })
            .catch(function(err) {
                msgEl.textContent = err.message;
                payBtn.disabled = false;
            });
    });
})();
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html,body{font-family:'Marcellus',serif!important}.event-payment-main{min-height:100vh;padding:1.5rem 0 5rem}.event-payment-wrap{max-width:980px;margin:0 auto;padding:0 14px}.back-link{display:inline-block;color:#800000;text-decoration:none;font-weight:700;margin-bottom:10px}.card{background:#fff;border:1px solid #ecd3d3;border-radius:14px;box-shadow:0 4px 14px rgba(128,0,0,.08);padding:14px;margin-bottom:14px}h1{margin:0 0 10px;color:#800000}.summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:8px;font-size:.93rem;color:#444}.payment-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px}.btn-main{display:inline-block;border:none;border-radius:8px;background:#800000;color:#fff;font-weight:700;padding:9px 12px;cursor:pointer;text-decoration:none}.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:10px}label{color:#800000;font-weight:700;font-size:.92rem}input,select{width:100%;box-sizing:border-box;border:1px solid #e0bebe;border-radius:8px;padding:9px 10px;font-size:.94rem}.small{color:#666;font-size:.86rem;min-height:1.1em}.notice.err{background:#ffeaea;color:#b00020;font-weight:600}.success-box h3{margin-top:0;color:#1a8917}
</style>
<?php require_once 'footer.php'; ?>
