<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('edit');

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
$actorId = (int)($_SESSION['user_id'] ?? 0);
$actorName = trim((string)($_SESSION['user_name'] ?? 'Admin'));
$actorRole = $isMainAdminActor ? 'admin' : 'staff';
if ($actorName === '') {
    $actorName = $isMainAdminActor ? 'Admin' : 'Staff';
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/event_module.php';

vs_event_ensure_tables($pdo);

$events = $pdo->query("SELECT id, title, event_date, event_type, registration_start, registration_end, registration_end_time, status
    FROM events
    ORDER BY status = 'Active' DESC, event_date DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($events as &$eventRow) {
    $eventRow['event_date_display'] = vs_event_get_event_date_display(
        $pdo,
        (int)$eventRow['id'],
        (string)($eventRow['event_date'] ?? ''),
        (string)($eventRow['event_type'] ?? 'single_day')
    );
}
unset($eventRow);

$selectedEventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : (int)($_POST['event_id'] ?? 0);
$selectedDateId = isset($_GET['event_date_id']) ? (int)$_GET['event_date_id'] : (int)($_POST['event_date_id'] ?? 0);
$selectedPackageId = isset($_GET['package_id']) ? (int)$_GET['package_id'] : (int)($_POST['package_id'] ?? 0);

$event = null;
if ($selectedEventId > 0) {
    $eventStmt = $pdo->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
    $eventStmt->execute([$selectedEventId]);
    $event = $eventStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$eventType = $event ? vs_event_normalize_event_type((string)($event['event_type'] ?? 'single_day')) : 'single_day';
$eventDates = $event ? vs_event_get_event_dates_cached($pdo, (int)$event['id'], true) : [];
$singleDefaultDateId = !empty($eventDates) ? (int)($eventDates[0]['id'] ?? 0) : 0;
$rangeDateLabel = $event ? vs_event_build_range_label($eventDates, (string)($event['event_date'] ?? '')) : '';

if ($event) {
    if ($eventType === 'single_day') {
        $selectedDateId = $singleDefaultDateId;
    } elseif ($eventType === 'date_range') {
        $selectedDateId = 0;
    } elseif ($selectedDateId <= 0 && !empty($eventDates)) {
        $selectedDateId = (int)($eventDates[0]['id'] ?? 0);
    }
}

$contextDateIds = [];
if ($event) {
    if ($eventType === 'date_range') {
        $contextDateIds = [0];
    } elseif (!empty($eventDates)) {
        foreach ($eventDates as $dateRow) {
            $contextDateIds[] = (int)($dateRow['id'] ?? 0);
        }
    } else {
        $contextDateIds = [0];
    }
}

$packageMapsByDate = [];
$packageClientMap = [];
$currentPackageRows = [];
if ($event) {
    foreach ($contextDateIds as $contextDateId) {
        $tmpPackages = vs_event_fetch_packages_with_seats($pdo, (int)$event['id'], true, max($contextDateId, 0));
        $packageMapsByDate[$contextDateId] = [];
        foreach ($tmpPackages as $tmpPackage) {
            $tmpPackageId = (int)($tmpPackage['id'] ?? 0);
            if ($tmpPackageId <= 0) {
                continue;
            }

            $packageMapsByDate[$contextDateId][$tmpPackageId] = $tmpPackage;
            if (!isset($packageClientMap[$tmpPackageId])) {
                $packageClientMap[$tmpPackageId] = [];
            }

            $tmpIsPaid = vs_event_is_package_paid($tmpPackage);
            $tmpMethods = vs_event_payment_methods_from_csv((string)($tmpPackage['payment_methods'] ?? ''), $tmpIsPaid);
            $packageClientMap[$tmpPackageId][(string)$contextDateId] = [
                'package_name' => (string)($tmpPackage['package_name'] ?? ''),
                'description' => (string)($tmpPackage['description'] ?? ''),
                'is_paid' => $tmpIsPaid ? 1 : 0,
                'payment_mode' => (string)($tmpPackage['payment_mode'] ?? 'full'),
                'payment_methods' => array_values($tmpMethods),
                'price_total' => (float)($tmpPackage['price_total'] ?? $tmpPackage['price'] ?? 0),
                'advance_amount' => (float)($tmpPackage['advance_amount'] ?? 0),
                'total_seats' => $tmpPackage['total_seats'] === null ? null : (int)$tmpPackage['total_seats'],
                'seats_left' => $tmpPackage['seats_left'] === null ? null : (int)$tmpPackage['seats_left'],
                'is_full' => !empty($tmpPackage['is_full']) ? 1 : 0,
            ];
        }
    }

    $currentDateKey = ($eventType === 'date_range') ? 0 : $selectedDateId;
    $currentPackageRows = $packageMapsByDate[$currentDateKey] ?? [];
}

if ($selectedPackageId <= 0 && !empty($currentPackageRows)) {
    $currentPackageIds = array_keys($currentPackageRows);
    $selectedPackageId = (int)$currentPackageIds[0];
}
if ($selectedPackageId > 0 && !isset($currentPackageRows[$selectedPackageId]) && !empty($currentPackageRows)) {
    $currentPackageIds = array_keys($currentPackageRows);
    $selectedPackageId = (int)$currentPackageIds[0];
}

$selectedPackage = $selectedPackageId > 0 && isset($currentPackageRows[$selectedPackageId])
    ? $currentPackageRows[$selectedPackageId]
    : null;

$formFields = [];
if ($event) {
    $fieldStmt = $pdo->prepare('SELECT * FROM event_form_fields WHERE event_id = ? ORDER BY id ASC');
    $fieldStmt->execute([(int)$event['id']]);
    $formFields = $fieldStmt->fetchAll(PDO::FETCH_ASSOC);
}

$hasNameField = false;
$hasPhoneField = false;
foreach ($formFields as $fieldMeta) {
    $metaName = strtolower(preg_replace('/\s+/', ' ', trim((string)($fieldMeta['field_name'] ?? ''))));
    $metaType = strtolower(trim((string)($fieldMeta['field_type'] ?? 'text')));
    if (!$hasNameField && strpos($metaName, 'name') !== false) {
        $hasNameField = true;
    }
    if (
        !$hasPhoneField
        && (
            in_array($metaType, ['phone', 'tel'], true)
            || strpos($metaName, 'phone') !== false
            || strpos($metaName, 'mobile') !== false
            || strpos($metaName, 'whatsapp') !== false
            || strpos($metaName, 'contact') !== false
        )
    ) {
        $hasPhoneField = true;
    }
}

$errors = [];
$onlineInstrumentOptions = [
    'upi' => 'UPI',
    'net_banking' => 'Net Banking',
    'card' => 'Card',
    'qr_payment' => 'QR Payment',
    'other_online' => 'Other Online',
];
$old = [
    'name' => '',
    'phone' => '',
    'country_code' => '+91',
    'custom_country_code' => '',
    'persons' => '1',
    'event_date_id' => (string)$selectedDateId,
    'package_id' => (string)$selectedPackageId,
    'dynamic' => [],
    'payment_collection_mode' => 'collect_later',
    'payment_choice' => 'full',
    'payment_instrument' => '',
    'payment_reference' => '',
    'payment_comment' => '',
];

$appendRegistrationAudit = static function (PDO $pdoRef, int $registrationId, int $userId, string $userName): void {
    if ($registrationId <= 0) {
        return;
    }
    $insertAudit = $pdoRef->prepare('INSERT INTO event_registration_data (registration_id, field_name, value) VALUES (?, ?, ?)');
    $insertAudit->execute([$registrationId, 'Created Via', 'Admin Panel']);
    $insertAudit->execute([$registrationId, 'Created By Admin', $userName !== '' ? $userName : ('User #' . $userId)]);
    if ($userId > 0) {
        $insertAudit->execute([$registrationId, 'Created By User ID', (string)$userId]);
    }
};

$loadRegistrationForPayment = static function (PDO $pdoRef, int $registrationId, bool $forUpdate = false): ?array {
    if ($registrationId <= 0) {
        return null;
    }

    $sql = "SELECT
            r.*,
            r.booking_reference,
            e.title AS event_title,
            e.slug AS event_slug,
            COALESCE(d.event_date, e.event_date) AS selected_event_date,
            p.package_name,
            p.advance_amount,
            p.payment_mode,
            p.is_paid,
            p.payment_methods,
            p.upi_id,
            p.upi_qr_image,
            COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS package_price_total,
            ep.id AS payment_id,
            ep.amount AS payment_amount,
            ep.amount_paid,
            ep.remaining_amount,
            ep.payment_type,
            ep.payment_method,
            ep.upi_id_used,
            ep.upi_qr_used,
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
        WHERE r.id = ?
        LIMIT 1";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdoRef->prepare($sql);
    $stmt->execute([$registrationId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
};

$applyCollectedPayment = static function (
    PDO $pdoRef,
    int $registrationId,
    string $paymentMethod,
    string $paymentChoice,
    string $transactionId,
    ?string $screenshotPath,
    string $remark,
    bool $autoVerify
) use ($loadRegistrationForPayment): array {
    $row = $loadRegistrationForPayment($pdoRef, $registrationId, true);
    if (!$row) {
        throw new RuntimeException('Registration not found.');
    }
    if ((int)($row['is_paid'] ?? 1) !== 1) {
        throw new RuntimeException('Collected payment is only available for paid packages.');
    }

    $paymentStatusLower = strtolower(trim((string)($row['payment_status'] ?? '')));
    $verificationStatusLower = strtolower(trim((string)($row['verification_status'] ?? '')));
    if ($paymentStatusLower === 'cancelled' || $verificationStatusLower === 'cancelled') {
        throw new RuntimeException('Cancelled booking cannot accept payment.');
    }
    if ($paymentStatusLower === 'paid') {
        throw new RuntimeException('Booking is already fully paid.');
    }

    $packageData = [
        'price' => (float)($row['package_price_total'] ?? 0),
        'price_total' => (float)($row['package_price_total'] ?? 0),
        'advance_amount' => (float)($row['advance_amount'] ?? 0),
        'payment_mode' => (string)($row['payment_mode'] ?? 'full'),
    ];
    $paymentRow = [
        'amount_paid' => (float)($row['amount_paid'] ?? 0),
        'remaining_amount' => (float)($row['remaining_amount'] ?? 0),
    ];
    $context = vs_event_build_registration_payment_context($row, $packageData, $paymentRow, $paymentChoice);
    $dueNow = round((float)($context['due_now'] ?? 0), 2);
    if ($dueNow <= 0) {
        throw new RuntimeException('No payable amount is pending for this booking.');
    }

    $bookingReference = trim((string)($row['booking_reference'] ?? ''));
    if ($bookingReference === '') {
        $bookingReference = vs_event_assign_booking_reference($pdoRef, $registrationId);
        $row['booking_reference'] = $bookingReference;
    }

    $upiIdUsed = null;
    $upiQrUsed = null;
    if ($paymentMethod === 'Manual UPI') {
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

    $eventDateLabel = vs_event_get_registration_date_display(
        $pdoRef,
        $row,
        (string)($row['selected_event_date'] ?? '')
    );

    if ($autoVerify) {
        $totalAmount = round((float)($context['total_amount'] ?? 0), 2);
        $newPaid = round((float)($context['amount_paid'] ?? 0) + $dueNow, 2);
        if ($newPaid > $totalAmount) {
            $newPaid = $totalAmount;
        }
        $remainingAfter = round(max($totalAmount - $newPaid, 0), 2);
        $newPaymentType = $remainingAfter > 0 ? (string)($context['payment_type'] ?? 'advance') : 'full';
        $newPaymentStatus = $remainingAfter > 0 ? 'Partial Paid' : 'Paid';

        $upsert = $pdoRef->prepare("INSERT INTO event_payments (registration_id, amount, payment_type, amount_paid, remaining_amount, payment_method, upi_id_used, upi_qr_used, transaction_id, screenshot, remarks, status)
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
            $dueNow,
            $newPaymentType,
            $newPaid,
            $remainingAfter,
            $paymentMethod,
            $upiIdUsed,
            $upiQrUsed,
            $transactionId,
            $screenshotPath,
            $remark,
        ]);
        vs_event_record_payment_activity($pdoRef, [
            'registration_id' => $registrationId,
            'payment_method' => $paymentMethod,
            'payment_type' => $newPaymentType,
            'amount' => $dueNow,
            'transaction_id' => $transactionId,
            'screenshot' => $screenshotPath,
            'remarks' => $remark,
            'status' => 'Approved',
            'collected_by_user_id' => (int)($GLOBALS['actorId'] ?? 0),
            'collected_by_user_name' => (string)($GLOBALS['actorName'] ?? ''),
            'collected_by_role' => (string)($GLOBALS['actorRole'] ?? ''),
            'reviewed_by_user_id' => (int)($GLOBALS['actorId'] ?? 0),
            'reviewed_by_user_name' => (string)($GLOBALS['actorName'] ?? ''),
            'reviewed_by_role' => (string)($GLOBALS['actorRole'] ?? ''),
            'reviewed_at' => date('Y-m-d H:i:s'),
            'source_label' => 'Admin Create Registration',
        ]);

        $pdoRef->prepare("UPDATE event_registrations
            SET payment_status = ?,
                verification_status = 'Auto Verified'
            WHERE id = ?")
            ->execute([$newPaymentStatus, $registrationId]);

        if (vs_event_is_whatsapp_enabled($pdoRef, (int)($row['event_id'] ?? 0))) {
            if ($remainingAfter <= 0) {
                $qrCodePath = vs_event_ensure_registration_qr($pdoRef, $registrationId);
                vs_event_send_whatsapp_notice('ticket_delivery', (string)$row['phone'], [
                    'name' => (string)$row['name'],
                    'event_name' => (string)$row['event_title'],
                    'package_name' => (string)$row['package_name'],
                    'event_date' => $eventDateLabel,
                    'amount' => (string)$totalAmount,
                    'booking_reference' => $bookingReference,
                    'registration_id' => $registrationId,
                    'event_id' => (int)($row['event_id'] ?? 0),
                    'qr_code_path' => $qrCodePath,
                ]);
            } else {
                vs_event_send_whatsapp_notice('payment_approved', (string)$row['phone'], [
                    'name' => (string)$row['name'],
                    'event_name' => (string)$row['event_title'],
                    'package_name' => (string)$row['package_name'],
                    'event_date' => $eventDateLabel,
                    'amount' => (string)$dueNow,
                    'booking_reference' => $bookingReference,
                    'registration_id' => $registrationId,
                    'event_id' => (int)($row['event_id'] ?? 0),
                ]);
            }
        }

        return [
            'message' => $remainingAfter <= 0
                ? 'Registration created and payment auto approved.'
                : 'Registration created and advance payment auto approved.',
        ];
    }

    $upsert = $pdoRef->prepare("INSERT INTO event_payments (registration_id, amount, payment_type, amount_paid, remaining_amount, payment_method, upi_id_used, upi_qr_used, transaction_id, screenshot, remarks, status)
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
        $dueNow,
        (string)($context['payment_type'] ?? 'full'),
        (float)($context['amount_paid'] ?? 0),
        (float)($context['remaining_before_payment'] ?? 0),
        $paymentMethod,
        $upiIdUsed,
        $upiQrUsed,
        $transactionId,
        $screenshotPath,
        $remark,
    ]);
    vs_event_record_payment_activity($pdoRef, [
        'registration_id' => $registrationId,
        'payment_method' => $paymentMethod,
        'payment_type' => (string)($context['payment_type'] ?? 'full'),
        'amount' => $dueNow,
        'transaction_id' => $transactionId,
        'screenshot' => $screenshotPath,
        'remarks' => $remark,
        'status' => 'Pending Verification',
        'collected_by_user_id' => (int)($GLOBALS['actorId'] ?? 0),
        'collected_by_user_name' => (string)($GLOBALS['actorName'] ?? ''),
        'collected_by_role' => (string)($GLOBALS['actorRole'] ?? ''),
        'source_label' => 'Admin Create Registration',
    ]);

    $pdoRef->prepare("UPDATE event_registrations
        SET payment_status = 'Pending Verification',
            verification_status = 'Pending'
        WHERE id = ?")
        ->execute([$registrationId]);

    if (vs_event_is_whatsapp_enabled($pdoRef, (int)($row['event_id'] ?? 0))) {
        vs_event_send_whatsapp_notice('payment_pending_verification', (string)$row['phone'], [
            'name' => (string)$row['name'],
            'event_name' => (string)$row['event_title'],
            'package_name' => (string)$row['package_name'],
            'event_date' => $eventDateLabel,
            'amount' => (string)$dueNow,
            'booking_reference' => $bookingReference,
            'registration_id' => $registrationId,
            'event_id' => (int)($row['event_id'] ?? 0),
        ]);
    }

    return [
        'message' => 'Registration created and payment submitted for verification.',
    ];
};

$applyMarkPaid = static function (
    PDO $pdoRef,
    int $registrationId,
    string $manualMethod,
    string $transactionId,
    string $remark,
    bool $isMainAdmin
) use ($loadRegistrationForPayment): array {
    $row = $loadRegistrationForPayment($pdoRef, $registrationId, true);
    if (!$row) {
        throw new RuntimeException('Registration not found.');
    }
    if ((int)($row['is_paid'] ?? 1) !== 1) {
        throw new RuntimeException('Direct mark paid is only available for paid packages.');
    }

    $paymentStatusLower = strtolower(trim((string)($row['payment_status'] ?? '')));
    $verificationStatusLower = strtolower(trim((string)($row['verification_status'] ?? '')));
    if ($paymentStatusLower === 'cancelled' || $verificationStatusLower === 'cancelled') {
        throw new RuntimeException('Cancelled booking cannot be marked paid.');
    }

    $totalAmount = round((float)($row['package_price_total'] ?? 0) * max((int)($row['persons'] ?? 1), 1), 2);
    $alreadyPaid = round(max((float)($row['amount_paid'] ?? 0), 0), 2);
    $currentCollectionAmount = round(max($totalAmount - $alreadyPaid, 0), 2);
    if ($currentCollectionAmount <= 0) {
        $currentCollectionAmount = $totalAmount;
    }

    if (!in_array($manualMethod, ['Manual Approved', 'Cash', 'Manual UPI'], true)) {
        $manualMethod = 'Manual Approved';
    }
    if ($transactionId === '') {
        $transactionId = 'MANUAL-PAID-' . date('YmdHis');
    }

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
        $update = $pdoRef->prepare("UPDATE event_payments
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
            $currentCollectionAmount > 0 ? $currentCollectionAmount : $totalAmount,
            $totalAmount,
            $manualMethod,
            $upiIdUsed,
            $upiQrUsed,
            $transactionId,
                $remark,
                $registrationId,
            ]);
        } else {
        $insert = $pdoRef->prepare("INSERT INTO event_payments (registration_id, amount, payment_type, amount_paid, remaining_amount, payment_method, upi_id_used, upi_qr_used, transaction_id, screenshot, remarks, status)
            VALUES (?, ?, 'full', ?, 0, ?, ?, ?, ?, NULL, ?, 'Approved')");
        $insert->execute([
            $registrationId,
            $currentCollectionAmount > 0 ? $currentCollectionAmount : $totalAmount,
            $totalAmount,
            $manualMethod,
            $upiIdUsed,
            $upiQrUsed,
            $transactionId,
                $remark,
            ]);
        }
        vs_event_record_payment_activity($pdoRef, [
            'registration_id' => $registrationId,
            'payment_method' => $manualMethod,
            'payment_type' => 'full',
            'amount' => ($currentCollectionAmount > 0 ? $currentCollectionAmount : $totalAmount),
            'transaction_id' => $transactionId,
            'remarks' => $remark,
            'status' => 'Approved',
            'collected_by_user_id' => (int)($GLOBALS['actorId'] ?? 0),
            'collected_by_user_name' => (string)($GLOBALS['actorName'] ?? ''),
            'collected_by_role' => (string)($GLOBALS['actorRole'] ?? ''),
            'reviewed_by_user_id' => (int)($GLOBALS['actorId'] ?? 0),
            'reviewed_by_user_name' => (string)($GLOBALS['actorName'] ?? ''),
            'reviewed_by_role' => (string)($GLOBALS['actorRole'] ?? ''),
            'reviewed_at' => date('Y-m-d H:i:s'),
            'source_label' => 'Admin Create Registration',
        ]);

    $verificationStatus = $isMainAdmin ? 'Auto Verified' : 'Approved';
    $pdoRef->prepare("UPDATE event_registrations
        SET payment_status = 'Paid',
            verification_status = ?
        WHERE id = ?")
        ->execute([$verificationStatus, $registrationId]);

    $bookingReference = trim((string)($row['booking_reference'] ?? ''));
    if ($bookingReference === '') {
        $bookingReference = vs_event_assign_booking_reference($pdoRef, $registrationId);
    }

    if (vs_event_is_whatsapp_enabled($pdoRef, (int)($row['event_id'] ?? 0))) {
        $eventDateLabel = vs_event_get_registration_date_display(
            $pdoRef,
            $row,
            (string)($row['selected_event_date'] ?? '')
        );
        $qrCodePath = vs_event_ensure_registration_qr($pdoRef, $registrationId);
        vs_event_send_whatsapp_notice('ticket_delivery', (string)$row['phone'], [
            'name' => (string)$row['name'],
            'event_name' => (string)$row['event_title'],
            'package_name' => (string)$row['package_name'],
            'event_date' => $eventDateLabel,
            'amount' => (string)$totalAmount,
            'booking_reference' => $bookingReference,
            'registration_id' => $registrationId,
            'event_id' => (int)($row['event_id'] ?? 0),
            'qr_code_path' => $qrCodePath,
        ]);
    }

    return [
        'message' => $isMainAdmin
            ? 'Registration created and marked paid with auto verification.'
            : 'Registration created and marked paid.',
    ];
};

$redirectToRegistration = static function (int $registrationId, string $message, string $messageType = 'ok'): void {
    $params = [
        'id' => $registrationId,
        'return' => 'registrations.php',
        'msg' => $message,
        'msg_type' => $messageType,
    ];
    header('Location: registration-view.php?' . http_build_query($params));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_registration'])) {
    $selectedEventId = (int)($_POST['event_id'] ?? $selectedEventId);
    $selectedDateId = (int)($_POST['event_date_id'] ?? $selectedDateId);
    $selectedPackageId = (int)($_POST['package_id'] ?? $selectedPackageId);

    $name = trim((string)($_POST['name'] ?? ''));
    $phoneRaw = trim((string)($_POST['phone'] ?? ''));
    $countryContext = vs_event_resolve_country_context(
        (string)($_POST['country_code'] ?? '+91'),
        (string)($_POST['custom_country_code'] ?? '')
    );
    $countryCode = (string)($countryContext['country_code'] ?? '+91');
    $customCountryCode = (string)($countryContext['custom_country_code'] ?? '');
    $persons = max((int)($_POST['persons'] ?? 1), 1);
    $dynamicInput = isset($_POST['dynamic']) && is_array($_POST['dynamic']) ? $_POST['dynamic'] : [];
    $paymentCollectionMode = trim((string)($_POST['payment_collection_mode'] ?? 'collect_later'));
    $paymentChoice = trim((string)($_POST['payment_choice'] ?? 'full'));
    $paymentInstrument = trim((string)($_POST['payment_instrument'] ?? ''));
    $paymentReference = '';
    $paymentComment = '';
    if ($paymentCollectionMode === 'cash') {
        $paymentReference = trim((string)($_POST['payment_reference_cash'] ?? ''));
        $paymentComment = trim((string)($_POST['payment_comment_cash'] ?? ''));
    } elseif ($paymentCollectionMode === 'online') {
        $paymentReference = trim((string)($_POST['payment_reference_online'] ?? ''));
        $paymentComment = trim((string)($_POST['payment_comment_online'] ?? ''));
    }

    $old['name'] = $name;
    $old['phone'] = $phoneRaw;
    $old['country_code'] = $countryCode;
    $old['custom_country_code'] = $customCountryCode;
    $old['persons'] = (string)$persons;
    $old['event_date_id'] = (string)$selectedDateId;
    $old['package_id'] = (string)$selectedPackageId;
    $old['dynamic'] = $dynamicInput;
    $old['payment_collection_mode'] = $paymentCollectionMode;
    $old['payment_choice'] = $paymentChoice;
    $old['payment_instrument'] = $paymentInstrument;
    $old['payment_reference'] = $paymentReference;
    $old['payment_comment'] = $paymentComment;

    if (!$event) {
        $errors[] = 'Please select a valid event.';
    }
    if (!$selectedPackage) {
        $errors[] = 'Please select a valid package.';
    }

    $dynamicValues = [];
    $nameFromDynamic = '';
    $phoneFromDynamic = '';
    $normalizedPhone = '';
    if (empty($errors)) {
        if ($eventType === 'multi_select_dates') {
            $selectedDateOk = false;
            foreach ($eventDates as $dateRow) {
                if ((int)($dateRow['id'] ?? 0) === $selectedDateId) {
                    $selectedDateOk = true;
                    break;
                }
            }
            if (!$selectedDateOk) {
                $errors[] = 'Please select a valid event date.';
            }
        }

        foreach ($formFields as $field) {
            $fieldId = (int)($field['id'] ?? 0);
            $fieldName = (string)($field['field_name'] ?? '');
            $fieldType = strtolower((string)($field['field_type'] ?? 'text'));
            $required = (int)($field['required'] ?? 0) === 1;

            if ($fieldType === 'file') {
                $fileKey = 'dynamic_file_' . $fieldId;
                $fileVal = '';
                if (isset($_FILES[$fileKey]) && (int)($_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $uploaded = vs_event_store_upload($_FILES[$fileKey], 'registrations', ['jpg', 'jpeg', 'png', 'webp', 'pdf']);
                    if ($uploaded === null) {
                        $errors[] = 'Invalid upload for field: ' . $fieldName;
                    } else {
                        $fileVal = $uploaded;
                    }
                }
                if ($required && $fileVal === '') {
                    $errors[] = $fieldName . ' is required.';
                }
                $dynamicValues[] = ['field_name' => $fieldName, 'value' => $fileVal];
                continue;
            }

            $value = trim((string)($dynamicInput[$fieldId] ?? ''));
            if ($required && $value === '') {
                $errors[] = $fieldName . ' is required.';
            }

            $fieldNameKey = strtolower(preg_replace('/\s+/', ' ', trim($fieldName)));
            if ($value !== '') {
                if ($nameFromDynamic === '' && strpos($fieldNameKey, 'name') !== false) {
                    $nameFromDynamic = $value;
                }
                if (
                    $phoneFromDynamic === ''
                    && (
                        in_array($fieldType, ['phone', 'tel'], true)
                        || strpos($fieldNameKey, 'phone') !== false
                        || strpos($fieldNameKey, 'mobile') !== false
                        || strpos($fieldNameKey, 'whatsapp') !== false
                        || strpos($fieldNameKey, 'contact') !== false
                    )
                ) {
                    $phoneFromDynamic = $value;
                }
            }

            if ($fieldType === 'select' && $value !== '') {
                $options = array_filter(array_map('trim', preg_split('/[,\n\r]+/', (string)($field['field_options'] ?? ''))));
                if (!empty($options) && !in_array($value, $options, true)) {
                    $errors[] = 'Invalid value selected for ' . $fieldName . '.';
                }
            }

            $dynamicValues[] = ['field_name' => $fieldName, 'value' => $value];
        }

        if ($name === '' && $nameFromDynamic !== '') {
            $name = $nameFromDynamic;
            $old['name'] = $name;
        }
        if ($phoneRaw === '' && $phoneFromDynamic !== '') {
            $phoneRaw = $phoneFromDynamic;
            $old['phone'] = $phoneRaw;
        }

        $normalizedPhone = vs_event_normalize_phone($phoneRaw, $countryCode, $customCountryCode);

        if ($countryCode === 'other' && $customCountryCode === '') {
            $errors[] = 'Please enter a valid country code.';
        }
        if ($name === '') {
            $errors[] = 'Name is required in registration form.';
        }
        if ($normalizedPhone === '') {
            $errors[] = 'A valid phone is required in registration form.';
        }
    }

    if (empty($errors) && $selectedPackage) {
        $selectedPackageIsPaid = vs_event_is_package_paid($selectedPackage);
        $shouldJoinWaitlist = ($selectedPackage['seats_left'] !== null && (int)$selectedPackage['seats_left'] < $persons);

        if ($shouldJoinWaitlist) {
            try {
                $waitResult = vs_event_create_waitlisted_registration(
                    $pdo,
                    (int)$event['id'],
                    $selectedPackageId,
                    $selectedDateId,
                    $name,
                    $normalizedPhone,
                    $persons,
                    $dynamicValues,
                    false,
                    $countryCode,
                    $customCountryCode
                );
                $waitRegistrationId = (int)($waitResult['registration_id'] ?? 0);
                if ($waitRegistrationId > 0) {
                    try {
                        $pdo->beginTransaction();
                        $appendRegistrationAudit($pdo, $waitRegistrationId, $actorId, $actorName);
                        $pdo->commit();
                    } catch (Throwable $auditError) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        error_log('Admin waitlist audit save failed: ' . $auditError->getMessage());
                    }
                    $redirectToRegistration($waitRegistrationId, 'Seats were full, so a waitlisted booking was created.', 'ok');
                }
            } catch (Throwable $e) {
                $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to create waitlisted booking.';
            }
        } else {
            if (!$selectedPackageIsPaid) {
                $paymentCollectionMode = 'collect_later';
                $old['payment_collection_mode'] = $paymentCollectionMode;
            }
            if (!in_array($paymentChoice, ['full', 'advance'], true)) {
                $paymentChoice = 'full';
                $old['payment_choice'] = $paymentChoice;
            }
            if (!in_array($paymentCollectionMode, ['collect_later', 'cash', 'online'], true)) {
                $errors[] = 'Invalid payment method selected.';
            }

            $paymentMethod = '';
            $paymentProofPath = null;
            $paymentInstrumentLabel = '';
            $paymentProofField = '';
            if ($paymentCollectionMode === 'cash') {
                $paymentMethod = 'Cash';
                $paymentInstrument = 'cash';
                $paymentInstrumentLabel = 'Cash';
                $old['payment_instrument'] = $paymentInstrument;
                $paymentProofField = 'payment_proof_cash';
            } elseif ($paymentCollectionMode === 'online') {
                $paymentMethod = 'Online Payment';
                $paymentProofField = 'payment_proof_online';
                if (!isset($onlineInstrumentOptions[$paymentInstrument])) {
                    $errors[] = 'Please choose how the online payment was received.';
                } else {
                    $paymentInstrumentLabel = (string)$onlineInstrumentOptions[$paymentInstrument];
                }
            }

            if (empty($errors) && $paymentCollectionMode !== 'collect_later') {
                if ($paymentReference === '') {
                    $errors[] = 'Reference number is required for collected payments.';
                }
                if ($paymentProofField === '' || !isset($_FILES[$paymentProofField]) || (int)($_FILES[$paymentProofField]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    $errors[] = 'Payment proof image is required.';
                } else {
                    $paymentProofPath = vs_event_store_upload($_FILES[$paymentProofField], 'payments', ['jpg', 'jpeg', 'png', 'webp']);
                    if ($paymentProofPath === null) {
                        $errors[] = 'Invalid payment proof upload. Allowed formats: jpg, jpeg, png, webp.';
                    }
                }
            }

            $finalPaymentRemark = '';
            if ($paymentCollectionMode !== 'collect_later') {
                $remarkParts = [];
                if ($paymentInstrumentLabel !== '') {
                    $remarkParts[] = 'Instrument: ' . $paymentInstrumentLabel;
                }
                if ($paymentComment !== '') {
                    $remarkParts[] = 'Comment: ' . $paymentComment;
                }
                $finalPaymentRemark = implode(PHP_EOL, $remarkParts);
            }

            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();

                    $createResult = vs_event_create_registration($pdo, [
                        'event_id' => (int)$event['id'],
                        'package_id' => $selectedPackageId,
                        'event_type' => $eventType,
                        'event_date_id' => $selectedDateId,
                        'name' => $name,
                        'phone' => $normalizedPhone,
                        'country_code' => $countryCode,
                        'custom_country_code' => $customCountryCode,
                        'persons' => $persons,
                        'dynamic_values' => $dynamicValues,
                    ], [
                        'source_label' => 'Admin Panel',
                        'ignore_registration_window' => true,
                        'use_existing_transaction' => true,
                    ]);

                    $newRegistrationId = (int)($createResult['registration_id'] ?? 0);
                    if ($newRegistrationId <= 0) {
                        throw new RuntimeException('Unable to create registration.');
                    }

                    $appendRegistrationAudit($pdo, $newRegistrationId, $actorId, $actorName);

                    $successMessage = 'Registration created successfully.';
                    if ($selectedPackageIsPaid) {
                        if ($paymentCollectionMode === 'cash' || $paymentCollectionMode === 'online') {
                            $paymentResult = $applyCollectedPayment(
                                $pdo,
                                $newRegistrationId,
                                $paymentMethod,
                                $paymentChoice,
                                $paymentReference,
                                $paymentProofPath,
                                $finalPaymentRemark,
                                $isMainAdminActor
                            );
                            $successMessage = (string)($paymentResult['message'] ?? $successMessage);
                        } else {
                            $successMessage = 'Registration created as pay later. You can collect payment later or open the customer payment page from the detail screen.';
                        }
                    }

                    $pdo->commit();
                    $redirectToRegistration($newRegistrationId, $successMessage, 'ok');
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to create registration right now.';
                }
            }
        }
    }
}

$selectedPackageIsPaid = $selectedPackage ? vs_event_is_package_paid($selectedPackage) : true;
$selectedPackageClient = null;
$currentClientDateKey = $event ? (string)(($eventType === 'date_range') ? 0 : $selectedDateId) : '0';
if ($selectedPackageId > 0 && isset($packageClientMap[$selectedPackageId][$currentClientDateKey])) {
    $selectedPackageClient = $packageClientMap[$selectedPackageId][$currentClientDateKey];
}

$personsQty = max((int)$old['persons'], 1);
$selectedPackageForPlan = $selectedPackage;
if ($selectedPackageForPlan) {
    $currentPrice = isset($selectedPackageClient['price_total']) ? (float)$selectedPackageClient['price_total'] : (float)($selectedPackageForPlan['price_total'] ?? $selectedPackageForPlan['price'] ?? 0);
    if ($selectedPackageIsPaid) {
        $selectedPackageForPlan['price_total'] = $currentPrice;
        $selectedPackageForPlan['price'] = $currentPrice;
    } else {
        $selectedPackageForPlan['price_total'] = 0;
        $selectedPackageForPlan['price'] = 0;
        $selectedPackageForPlan['advance_amount'] = 0;
        $selectedPackageForPlan['payment_mode'] = 'full';
    }
}
$paymentPlan = $selectedPackageForPlan
    ? vs_event_calculate_payment_plan($selectedPackageForPlan, $personsQty, (string)$old['payment_choice'])
    : [
        'payment_mode' => 'full',
        'payment_choice' => 'full',
        'price_total' => 0,
        'advance_amount' => 0,
        'total_amount' => 0,
        'advance_total' => 0,
        'due_now' => 0,
        'remaining_after_due' => 0,
    ];

$seatLimitDisplay = 'Unlimited';
$seatsLeftDisplay = 'Unlimited';
$initialWaitlistMode = false;
if (is_array($selectedPackageClient)) {
    if ($selectedPackageClient['total_seats'] !== null) {
        $seatLimitDisplay = (string)(int)$selectedPackageClient['total_seats'];
    }
    if ($selectedPackageClient['seats_left'] !== null) {
        $seatsLeftDisplay = (string)(int)$selectedPackageClient['seats_left'];
        if ((int)$selectedPackageClient['seats_left'] < $personsQty) {
            $initialWaitlistMode = true;
        }
    }
}

$registrationOpen = $event ? vs_event_is_registration_open($event) : false;
$eventIsActive = $event && (string)($event['status'] ?? '') === 'Active';
$adminCanCreateForEvent = (bool)$eventIsActive;
$registrationWindowLabel = $event ? vs_event_format_registration_window($event) : '';
$packageMethodSummary = [];
if ($selectedPackage) {
    $packageMethodSummary = vs_event_payment_methods_from_csv((string)($selectedPackage['payment_methods'] ?? ''), $selectedPackageIsPaid);
}

$pageTitle = 'Create Event Registration';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f7f7fa; margin:0; }
        .admin-shell { max-width:1200px; margin:0 auto; padding:24px 12px 40px; }
        .page-head { display:flex; justify-content:space-between; gap:14px; align-items:flex-start; flex-wrap:wrap; margin-bottom:16px; }
        .page-head h1 { margin:0; color:#800000; }
        .page-head p { margin:4px 0 0; color:#666; }
        .surface { background:#fff; border-radius:14px; box-shadow:0 2px 12px rgba(128,0,0,.08); padding:16px; margin-bottom:16px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        label { color:#800000; font-weight:700; font-size:.92rem; }
        input, select, textarea { width:100%; box-sizing:border-box; border:1px solid #e0bebe; border-radius:8px; padding:10px 11px; font-size:.94rem; background:#fff; }
        textarea { min-height:88px; resize:vertical; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; border:none; border-radius:9px; padding:10px 14px; font-weight:700; cursor:pointer; text-decoration:none; }
        .btn-primary { background:#800000; color:#fff; }
        .btn-secondary { background:#0b7285; color:#fff; }
        .btn-light { background:#f4f5f7; color:#333; border:1px solid #d8dde3; }
        .notice { margin:10px 0; padding:11px 12px; border-radius:10px; font-weight:600; }
        .notice.err { background:#ffeaea; color:#b00020; }
        .notice.ok { background:#e7f7ed; color:#1a8917; }
        .small { color:#666; font-size:.84rem; }
        .small strong { color:#333; }
        .event-summary { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:10px; margin-top:12px; }
        .event-stat { border:1px solid #f0d8d8; border-radius:10px; padding:10px 11px; background:#fffaf9; }
        .event-stat span { display:block; color:#7b6760; font-size:.76rem; text-transform:uppercase; letter-spacing:.04em; font-weight:700; }
        .event-stat strong { display:block; margin-top:4px; color:#2f2624; font-size:.98rem; line-height:1.3; }
        .phone-row { display:flex; gap:8px; align-items:center; }
        .phone-row .country-select { width:150px; flex:0 0 150px; }
        .phone-row .country-custom { width:92px; flex:0 0 92px; display:none; }
        .summary-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px; margin-top:12px; }
        .summary-card { border:1px solid #ead7d2; border-radius:10px; padding:10px 11px; background:#fff; }
        .summary-card span { display:block; color:#6b6f7b; font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; font-weight:700; }
        .summary-card strong { display:block; margin-top:5px; color:#2a303b; font-size:1.02rem; }
        .summary-card.warn { background:#fff8e8; border-color:#f2ddad; }
        .summary-card.ok { background:#eef9f1; border-color:#cee7d5; }
        .summary-card.note { background:#f4fbff; border-color:#cfe2eb; }
        .section-title { margin:0 0 10px; color:#800000; font-size:1.08rem; }
        .action-card { border:1px solid #ead7d2; border-radius:12px; padding:14px; background:#fffdf9; }
        .action-note { margin-top:10px; padding:10px 12px; border-radius:10px; background:#f4fbff; color:#0f5f77; border:1px solid #cfe2eb; font-size:.9rem; }
        .waitlist-note { margin-top:10px; padding:10px 12px; border-radius:10px; background:#eef9fc; color:#0b5d70; border:1px solid #cfe2eb; font-size:.9rem; }
        .help-row { margin-top:8px; color:#666; font-size:.84rem; line-height:1.4; }
        #package_description_text { white-space:pre-line; }
        .actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:16px; }
        .inline-hidden { display:none; }
        .req { color:#b00020; }
        .proof-input-row { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
        .proof-input-row input[type="file"] { flex:1 1 240px; }
        .camera-launch-btn { white-space:nowrap; }
        .proof-file-name { margin-top:4px; }
        .camera-modal { position:fixed; inset:0; background:rgba(20, 24, 32, .72); display:none; align-items:center; justify-content:center; padding:16px; z-index:1200; }
        .camera-modal.open { display:flex; }
        .camera-panel { width:min(100%, 720px); background:#fff; border-radius:16px; box-shadow:0 18px 42px rgba(20, 24, 32, .24); padding:18px; }
        .camera-panel h3 { margin:0 0 6px; color:#800000; }
        .camera-panel p { margin:0 0 12px; color:#666; }
        .camera-preview { width:100%; aspect-ratio:4 / 3; border-radius:14px; background:#111; object-fit:cover; }
        .camera-status { min-height:20px; margin-top:10px; color:#666; font-size:.88rem; }
        .camera-status.err { color:#b00020; }
        @media (max-width:760px) {
            .admin-shell { padding:16px 8px 28px; }
            .phone-row { flex-wrap:wrap; }
            .phone-row .country-select, .phone-row .country-custom { width:100%; flex:1 1 auto; }
            .proof-input-row input[type="file"], .camera-launch-btn { width:100%; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-shell">
    <div class="page-head">
        <div>
            <h1>Create Registration</h1>
            <p>Use the same event configuration, field rules, waitlist logic, and payment lifecycle as the website.</p>
        </div>
        <div class="actions" style="margin-top:0;">
            <a class="btn btn-secondary" href="registrations.php">Back To Registrations</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="notice err">
            <?php foreach ($errors as $error): ?>
                <div><?php echo htmlspecialchars((string)$error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($event && !$registrationOpen): ?>
        <div class="notice" style="background:#fff8e8;color:#8a5a00;">
            Website registration window is closed for this event<?php echo $registrationWindowLabel !== '' ? ' (' . htmlspecialchars($registrationWindowLabel) . ')' : ''; ?>.
            Admin panel can still create registrations.
        </div>
    <?php endif; ?>

    <div class="surface">
        <form method="get" class="grid" autocomplete="off">
            <div class="form-group">
                <label>Select Event</label>
                <select name="event_id" required onchange="this.form.submit()">
                    <option value="">Choose an event</option>
                    <?php foreach ($events as $eventOption): ?>
                        <option value="<?php echo (int)$eventOption['id']; ?>" <?php echo ($selectedEventId === (int)$eventOption['id']) ? 'selected' : ''; ?>>
                            <?php
                            $eventLabel = (string)$eventOption['title']
                                . ' (' . (string)($eventOption['event_date_display'] ?? $eventOption['event_date']) . ')'
                                . ' - ' . (string)($eventOption['status'] ?? 'Active');
                            echo htmlspecialchars($eventLabel);
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($selectedEventId > 0 && $event): ?>
                <div class="form-group">
                    <label>Registration Window</label>
                    <input type="text" value="<?php echo htmlspecialchars($registrationWindowLabel !== '' ? $registrationWindowLabel : ((string)($event['registration_start'] ?? '') . ' to ' . (string)($event['registration_end'] ?? ''))); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Window Status</label>
                    <input type="text" value="<?php echo htmlspecialchars($registrationOpen ? 'Open' : 'Closed for website / Open in admin'); ?>" readonly>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($event): ?>
        <div class="surface">
            <h2 class="section-title"><?php echo htmlspecialchars((string)$event['title']); ?></h2>
            <p class="small"><?php echo htmlspecialchars((string)($event['short_description'] ?? '')); ?></p>
            <div class="event-summary">
                <div class="event-stat">
                    <span>Event Date</span>
                    <strong><?php echo htmlspecialchars($eventType === 'date_range' ? $rangeDateLabel : (string)($eventDates[0]['event_date'] ?? $event['event_date'] ?? '')); ?></strong>
                </div>
                <div class="event-stat">
                    <span>Location</span>
                    <strong><?php echo htmlspecialchars((string)($event['location'] ?? '-')); ?></strong>
                </div>
                <div class="event-stat">
                    <span>Type</span>
                    <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $eventType))); ?></strong>
                </div>
                <div class="event-stat">
                    <span>Registration Status</span>
                    <strong><?php echo htmlspecialchars($registrationOpen ? 'Open for booking' : 'Closed online, open in admin'); ?></strong>
                </div>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="create_registration" value="1">
            <input type="hidden" name="event_id" value="<?php echo (int)$selectedEventId; ?>">

            <div class="surface">
                <h3 class="section-title">Registration Details</h3>
                <div class="grid">
                    <div class="form-group">
                        <label>Event Date</label>
                        <select id="event_date_id" name="event_date_id" required>
                            <?php if ($eventType === 'multi_select_dates'): ?>
                                <?php foreach ($eventDates as $dateRow): ?>
                                    <option value="<?php echo (int)$dateRow['id']; ?>" <?php echo ($selectedDateId === (int)$dateRow['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars((string)($dateRow['event_date'] ?? '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php elseif ($eventType === 'date_range'): ?>
                                <option value="0" selected><?php echo htmlspecialchars($rangeDateLabel); ?></option>
                            <?php else: ?>
                                <option value="<?php echo (int)$selectedDateId; ?>" selected>
                                    <?php echo htmlspecialchars((string)($eventDates[0]['event_date'] ?? $event['event_date'] ?? '')); ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Package</label>
                        <select id="package_id" name="package_id" required>
                            <option value="">Select package</option>
                            <?php foreach ($currentPackageRows as $packageOption): ?>
                                <?php
                                $tmpPackageId = (int)($packageOption['id'] ?? 0);
                                $tmpIsPaid = vs_event_is_package_paid($packageOption);
                                $tmpLabel = (string)($packageOption['package_name'] ?? 'Package')
                                    . ' - ' . ($tmpIsPaid ? 'Paid' : 'Free');
                                ?>
                                <option value="<?php echo $tmpPackageId; ?>" <?php echo ($selectedPackageId === $tmpPackageId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tmpLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($hasNameField): ?>
                        <input type="hidden" name="name" value="">
                    <?php else: ?>
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars((string)$old['name']); ?>" required>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Country Code</label>
                        <div class="phone-row">
                            <select name="country_code" id="country_code_select" class="country-select">
                                <option value="+91" <?php echo ((string)$old['country_code'] === '+91') ? 'selected' : ''; ?>>+91 India</option>
                                <option value="other" <?php echo ((string)$old['country_code'] === 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <input
                                type="text"
                                name="custom_country_code"
                                id="custom_country_code_input"
                                class="country-custom"
                                placeholder="+__"
                                value="<?php echo htmlspecialchars((string)$old['custom_country_code']); ?>"
                                pattern="^\+\d{1,4}$"
                            >
                        </div>
                        <small class="small">Default is +91. Change this only if the customer uses another country code.</small>
                    </div>

                    <?php if ($hasPhoneField): ?>
                        <input type="hidden" name="phone" value="">
                    <?php else: ?>
                        <div class="form-group">
                            <label>WhatsApp Number</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars((string)$old['phone']); ?>" required>
                            <small class="small">Use the number that should receive payment and ticket updates.</small>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Persons / Qty</label>
                        <input type="number" id="persons_qty" name="persons" min="1" max="25" value="<?php echo htmlspecialchars((string)$old['persons']); ?>" required>
                    </div>
                </div>

                <?php if ($selectedPackage): ?>
                    <div class="summary-grid">
                        <div class="summary-card">
                            <span>Package Type</span>
                            <strong id="summary_package_type"><?php echo $selectedPackageIsPaid ? 'Paid Package' : 'Free Package'; ?></strong>
                        </div>
                        <div class="summary-card">
                            <span>Total Seats</span>
                            <strong id="summary_total_seats"><?php echo htmlspecialchars($seatLimitDisplay); ?></strong>
                        </div>
                        <div class="summary-card">
                            <span>Seats Left</span>
                            <strong id="summary_seats_left"><?php echo htmlspecialchars($seatsLeftDisplay); ?></strong>
                        </div>
                        <div class="summary-card">
                            <span>Price Per Person</span>
                            <strong>Rs <span id="summary_price_total"><?php echo number_format((float)($paymentPlan['price_total'] ?? 0), 2, '.', ''); ?></span></strong>
                        </div>
                        <div class="summary-card">
                            <span>Advance Per Person</span>
                            <strong>Rs <span id="summary_advance_amount"><?php echo number_format((float)($paymentPlan['advance_amount'] ?? 0), 2, '.', ''); ?></span></strong>
                        </div>
                        <div class="summary-card <?php echo $initialWaitlistMode ? 'warn' : 'ok'; ?>">
                            <span>Booking Outcome</span>
                            <strong id="summary_booking_outcome"><?php echo $initialWaitlistMode ? 'Waitlist Required' : 'Seats Available'; ?></strong>
                        </div>
                        <div class="summary-card note">
                            <span>Amount Due Now</span>
                            <strong>Rs <span id="summary_due_now"><?php echo number_format((float)($paymentPlan['due_now'] ?? 0), 2, '.', ''); ?></span></strong>
                        </div>
                        <div class="summary-card note">
                            <span>Remaining After Payment</span>
                            <strong>Rs <span id="summary_remaining_after"><?php echo number_format((float)($paymentPlan['remaining_after_due'] ?? 0), 2, '.', ''); ?></span></strong>
                        </div>
                    </div>
                    <div class="help-row" id="package_description_block"<?php echo trim((string)($selectedPackage['description'] ?? '')) === '' ? ' style="display:none;"' : ''; ?>>
                        <strong>Package Description:</strong>
                        <span id="package_description_text"><?php echo htmlspecialchars((string)($selectedPackage['description'] ?? '')); ?></span>
                    </div>
                <?php else: ?>
                    <div class="notice err">No active package is available for this event/date combination.</div>
                <?php endif; ?>
            </div>

            <?php if (!empty($formFields)): ?>
                <div class="surface">
                    <h3 class="section-title">Dynamic Registration Fields</h3>
                    <div class="grid">
                        <?php foreach ($formFields as $field): ?>
                            <?php
                            $fieldId = (int)($field['id'] ?? 0);
                            $fieldName = (string)($field['field_name'] ?? '');
                            $fieldType = strtolower((string)($field['field_type'] ?? 'text'));
                            $required = (int)($field['required'] ?? 0) === 1;
                            $saved = (string)($old['dynamic'][$fieldId] ?? '');
                            $placeholder = trim((string)($field['field_placeholder'] ?? ''));
                            $placeholderAttr = $placeholder !== '' ? ' placeholder="' . htmlspecialchars($placeholder) . '"' : '';
                            $fieldNameKey = strtolower(preg_replace('/\s+/', ' ', trim($fieldName)));
                            $isPhoneField = (
                                in_array($fieldType, ['phone', 'tel'], true)
                                || strpos($fieldNameKey, 'phone') !== false
                                || strpos($fieldNameKey, 'mobile') !== false
                                || strpos($fieldNameKey, 'whatsapp') !== false
                                || strpos($fieldNameKey, 'contact') !== false
                            );
                            $phoneLabelSuffix = ($isPhoneField && strpos($fieldNameKey, 'whatsapp') === false) ? ' (WhatsApp Number)' : '';
                            $options = array_filter(array_map('trim', preg_split('/[,\n\r]+/', (string)($field['field_options'] ?? ''))));
                            ?>
                            <div class="form-group">
                                <label><?php echo htmlspecialchars($fieldName . $phoneLabelSuffix); ?><?php if ($required): ?> <span class="req">*</span><?php endif; ?></label>
                                <?php if ($fieldType === 'textarea'): ?>
                                    <textarea name="dynamic[<?php echo $fieldId; ?>]"<?php echo $placeholderAttr; ?> <?php echo $required ? 'required' : ''; ?>><?php echo htmlspecialchars($saved); ?></textarea>
                                <?php elseif ($fieldType === 'select'): ?>
                                    <select name="dynamic[<?php echo $fieldId; ?>]" <?php echo $required ? 'required' : ''; ?>>
                                        <option value=""><?php echo htmlspecialchars($placeholder !== '' ? $placeholder : '-- Select --'); ?></option>
                                        <?php foreach ($options as $optionValue): ?>
                                            <option value="<?php echo htmlspecialchars($optionValue); ?>" <?php echo ($saved === $optionValue) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($optionValue); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($fieldType === 'file'): ?>
                                    <input type="file" name="dynamic_file_<?php echo $fieldId; ?>" accept=".jpg,.jpeg,.png,.webp,.pdf" <?php echo $required ? 'required' : ''; ?>>
                                <?php else: ?>
                                    <?php $inputType = ($fieldType === 'phone') ? 'tel' : (($fieldType === 'number') ? 'number' : (($fieldType === 'date') ? 'date' : 'text')); ?>
                                    <input type="<?php echo $inputType; ?>" name="dynamic[<?php echo $fieldId; ?>]" value="<?php echo htmlspecialchars($saved); ?>"<?php echo $placeholderAttr; ?> <?php echo $required ? 'required' : ''; ?>>
                                <?php endif; ?>
                                <?php if ($isPhoneField): ?>
                                    <small class="small">This number will be used for customer communication if the top-level phone is blank.</small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="surface action-card">
                <h3 class="section-title">Payment And Fulfillment</h3>

                <?php if ($selectedPackage && !$selectedPackageIsPaid): ?>
                    <div class="action-note">This is a free package. Creating the registration will auto confirm the booking immediately.</div>
                <?php elseif ($selectedPackage): ?>
                    <div class="grid">
                        <div class="form-group">
                            <label>Amount To Collect Now</label>
                            <select id="payment_choice" name="payment_choice">
                                <option value="full" <?php echo ((string)$old['payment_choice'] === 'full') ? 'selected' : ''; ?>>Full Amount</option>
                                <option value="advance" <?php echo ((string)$old['payment_choice'] === 'advance') ? 'selected' : ''; ?>>Advance Only</option>
                            </select>
                            <small class="small" id="payment_choice_help">This follows the package payment mode.</small>
                        </div>

                        <div class="form-group">
                            <label>Payment Method</label>
                            <select id="payment_collection_mode" name="payment_collection_mode">
                                <option value="collect_later" <?php echo ((string)$old['payment_collection_mode'] === 'collect_later') ? 'selected' : ''; ?>>Book Now Pay Later</option>
                                <option value="cash" <?php echo ((string)$old['payment_collection_mode'] === 'cash') ? 'selected' : ''; ?>>Cash</option>
                                <option value="online" <?php echo ((string)$old['payment_collection_mode'] === 'online') ? 'selected' : ''; ?>>Online Payment</option>
                            </select>
                            <small class="small" id="payment_collection_help">Admin registration always supports pay later, cash, and online payment.</small>
                        </div>
                    </div>

                    <div id="cash_fields" class="grid inline-hidden" style="margin-top:12px;">
                        <div class="form-group">
                            <label>Instrument</label>
                            <input type="text" value="Cash" readonly>
                        </div>
                        <div class="form-group">
                            <label>Reference / Receipt Number</label>
                            <input type="text" name="payment_reference_cash" value="<?php echo ((string)$old['payment_collection_mode'] === 'cash') ? htmlspecialchars((string)$old['payment_reference']) : ''; ?>" placeholder="Enter cash receipt or reference number">
                        </div>
                        <div class="form-group" style="grid-column:1 / -1;">
                            <label>Comment</label>
                            <textarea name="payment_comment_cash" placeholder="Optional collection note"><?php echo ((string)$old['payment_collection_mode'] === 'cash') ? htmlspecialchars((string)$old['payment_comment']) : ''; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Proof Image</label>
                            <div class="proof-input-row">
                                <input type="file" id="payment_proof_cash" name="payment_proof_cash" accept="image/*" capture="environment" data-proof-upload>
                                <button type="button" class="btn btn-light camera-launch-btn" data-camera-target="payment_proof_cash">Capture From Camera</button>
                            </div>
                            <small class="small">You can click a picture with the camera or upload an image.</small>
                            <div class="small proof-file-name" data-proof-file-for="payment_proof_cash"></div>
                        </div>
                    </div>

                    <div id="online_fields" class="grid inline-hidden" style="margin-top:12px;">
                        <div class="form-group">
                            <label>Instrument</label>
                            <select name="payment_instrument">
                                <option value="">Select Online Instrument</option>
                                <?php foreach ($onlineInstrumentOptions as $instrumentValue => $instrumentLabel): ?>
                                    <option value="<?php echo htmlspecialchars($instrumentValue); ?>" <?php echo ((string)$old['payment_instrument'] === $instrumentValue) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($instrumentLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Reference Number</label>
                            <input type="text" name="payment_reference_online" value="<?php echo ((string)$old['payment_collection_mode'] === 'online') ? htmlspecialchars((string)$old['payment_reference']) : ''; ?>" placeholder="UPI ref, card ref, bank ref, etc.">
                        </div>
                        <div class="form-group" style="grid-column:1 / -1;">
                            <label>Comment</label>
                            <textarea name="payment_comment_online" placeholder="Optional collection note"><?php echo ((string)$old['payment_collection_mode'] === 'online') ? htmlspecialchars((string)$old['payment_comment']) : ''; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Proof Image</label>
                            <div class="proof-input-row">
                                <input type="file" id="payment_proof_online" name="payment_proof_online" accept="image/*" capture="environment" data-proof-upload>
                                <button type="button" class="btn btn-light camera-launch-btn" data-camera-target="payment_proof_online">Capture From Camera</button>
                            </div>
                            <small class="small">You can click a picture with the camera or upload an image.</small>
                            <div class="small proof-file-name" data-proof-file-for="payment_proof_online"></div>
                        </div>
                    </div>

                    <div class="action-note" id="admin_action_note">
                        <?php if (!$registrationOpen): ?>
                            Website registration is closed, but admin can still create this booking from the panel.
                        <?php elseif ($isMainAdminActor): ?>
                            Admin cash and online collections are auto approved immediately.
                        <?php else: ?>
                            Staff cash and online collections will create the booking and send the payment to admin for approval.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="notice err">Select an event package to continue.</div>
                <?php endif; ?>

                <div id="waitlist_note" class="waitlist-note"<?php echo $initialWaitlistMode ? '' : ' style="display:none;"'; ?>>
                    Seats are currently full for the selected quantity. Submitting this form will create a waitlisted booking instead of collecting payment.
                </div>

                <div class="actions">
                    <button type="submit" id="create_submit_btn" class="btn btn-primary" <?php echo (!$event || !$selectedPackage || !$adminCanCreateForEvent) ? 'disabled' : ''; ?>>
                        <?php
                        if ($initialWaitlistMode) {
                            echo 'Create Waitlisted Booking';
                        } elseif ($selectedPackage && !$selectedPackageIsPaid) {
                            echo 'Create Free Booking';
                        } elseif ((string)$old['payment_collection_mode'] === 'cash') {
                            echo $isMainAdminActor ? 'Create & Auto Approve Cash Payment' : 'Create & Submit Cash Payment';
                        } elseif ((string)$old['payment_collection_mode'] === 'online') {
                            echo $isMainAdminActor ? 'Create & Auto Approve Online Payment' : 'Create & Submit Online Payment';
                        } else {
                            echo 'Book Now Pay Later';
                        }
                        ?>
                    </button>
                </div>
            </div>
        </form>
    <?php elseif ($selectedEventId > 0): ?>
        <div class="notice err">Selected event was not found.</div>
    <?php endif; ?>
</div>

<div id="camera_capture_modal" class="camera-modal" aria-hidden="true">
    <div class="camera-panel" role="dialog" aria-modal="true" aria-labelledby="camera_capture_title">
        <h3 id="camera_capture_title">Capture Payment Proof</h3>
        <p>Allow camera access, frame the proof image, then click take photo.</p>
        <video id="camera_capture_video" class="camera-preview" autoplay playsinline muted></video>
        <canvas id="camera_capture_canvas" class="inline-hidden"></canvas>
        <div id="camera_capture_status" class="camera-status">Click the camera button to request access.</div>
        <div class="actions">
            <button type="button" class="btn btn-primary" id="camera_capture_take_btn" disabled>Take Photo</button>
            <button type="button" class="btn btn-light" id="camera_capture_cancel_btn">Cancel</button>
        </div>
    </div>
</div>

<script>
(function () {
    const countrySelect = document.getElementById('country_code_select');
    const customCountryInput = document.getElementById('custom_country_code_input');
    if (countrySelect && customCountryInput) {
        const syncCountryCodeInput = function () {
            const showCustom = countrySelect.value === 'other';
            customCountryInput.style.display = showCustom ? 'block' : 'none';
            customCountryInput.required = showCustom;
            if (!showCustom) {
                customCountryInput.value = '';
            }
        };
        syncCountryCodeInput();
        countrySelect.addEventListener('change', syncCountryCodeInput);
    }

    const packageData = <?php echo json_encode($packageClientMap, JSON_UNESCAPED_UNICODE); ?> || {};
    const eventType = <?php echo json_encode($eventType); ?>;
    const packageSelect = document.getElementById('package_id');
    const dateSelect = document.getElementById('event_date_id');
    const qtyInput = document.getElementById('persons_qty');
    const paymentChoiceSelect = document.getElementById('payment_choice');
    const paymentCollectionSelect = document.getElementById('payment_collection_mode');
    const submitBtn = document.getElementById('create_submit_btn');
    const waitlistNote = document.getElementById('waitlist_note');
    const paymentChoiceHelp = document.getElementById('payment_choice_help');
    const paymentCollectionHelp = document.getElementById('payment_collection_help');
    const cashFields = document.getElementById('cash_fields');
    const onlineFields = document.getElementById('online_fields');
    const cashReferenceInput = document.querySelector('input[name="payment_reference_cash"]');
    const cashProofInput = document.querySelector('input[name="payment_proof_cash"]');
    const onlineInstrumentSelect = document.querySelector('select[name="payment_instrument"]');
    const onlineReferenceInput = document.querySelector('input[name="payment_reference_online"]');
    const onlineProofInput = document.querySelector('input[name="payment_proof_online"]');
    const summaryType = document.getElementById('summary_package_type');
    const summaryTotalSeats = document.getElementById('summary_total_seats');
    const summarySeatsLeft = document.getElementById('summary_seats_left');
    const summaryPrice = document.getElementById('summary_price_total');
    const summaryAdvance = document.getElementById('summary_advance_amount');
    const summaryOutcome = document.getElementById('summary_booking_outcome');
    const summaryDueNow = document.getElementById('summary_due_now');
    const summaryRemaining = document.getElementById('summary_remaining_after');
    const packageDescriptionBlock = document.getElementById('package_description_block');
    const packageDescriptionText = document.getElementById('package_description_text');
    const isMainAdminActor = <?php echo $isMainAdminActor ? 'true' : 'false'; ?>;
    const proofInputs = Array.from(document.querySelectorAll('input[data-proof-upload]'));
    const cameraButtons = Array.from(document.querySelectorAll('[data-camera-target]'));
    const cameraModal = document.getElementById('camera_capture_modal');
    const cameraVideo = document.getElementById('camera_capture_video');
    const cameraCanvas = document.getElementById('camera_capture_canvas');
    const cameraStatus = document.getElementById('camera_capture_status');
    const cameraTakeBtn = document.getElementById('camera_capture_take_btn');
    const cameraCancelBtn = document.getElementById('camera_capture_cancel_btn');
    let activeCameraStream = null;
    let activeProofInput = null;

    if (!packageSelect || !qtyInput) {
        return;
    }

    function setCameraStatus(message, isError) {
        if (!cameraStatus) {
            return;
        }
        cameraStatus.textContent = message || '';
        cameraStatus.classList.toggle('err', !!isError);
    }

    function stopCameraStream() {
        if (activeCameraStream) {
            activeCameraStream.getTracks().forEach(function (track) {
                track.stop();
            });
            activeCameraStream = null;
        }
        if (cameraVideo) {
            cameraVideo.srcObject = null;
        }
    }

    function closeCameraModal() {
        stopCameraStream();
        activeProofInput = null;
        if (cameraModal) {
            cameraModal.classList.remove('open');
            cameraModal.setAttribute('aria-hidden', 'true');
        }
        if (cameraTakeBtn) {
            cameraTakeBtn.disabled = false;
        }
        setCameraStatus('Click the camera button to request access.', false);
    }

    function updateProofFileName(input) {
        if (!input || !input.id) {
            return;
        }
        const fileNameNode = document.querySelector('[data-proof-file-for="' + input.id + '"]');
        if (!fileNameNode) {
            return;
        }
        fileNameNode.textContent = (input.files && input.files.length > 0)
            ? ('Selected: ' + input.files[0].name)
            : '';
    }

    async function openCameraForInput(input) {
        if (!input) {
            return;
        }
        if (!cameraModal || !cameraVideo || !cameraCanvas || !cameraTakeBtn) {
            input.click();
            return;
        }
        if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
            input.click();
            return;
        }

        activeProofInput = input;
        cameraModal.classList.add('open');
        cameraModal.setAttribute('aria-hidden', 'false');
        cameraTakeBtn.disabled = true;
        setCameraStatus('Requesting camera access...', false);

        try {
            stopCameraStream();
            activeCameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false
            });
            cameraVideo.srcObject = activeCameraStream;
            await cameraVideo.play().catch(function () {
                return null;
            });
            cameraTakeBtn.disabled = false;
            setCameraStatus('Camera ready. Frame the proof image and click Take Photo.', false);
        } catch (error) {
            cameraTakeBtn.disabled = true;
            setCameraStatus('Camera access was blocked or is not available. You can upload the image instead.', true);
        }
    }

    async function captureProofPhoto() {
        if (!activeProofInput || !cameraVideo || !cameraCanvas) {
            return;
        }
        const frameWidth = cameraVideo.videoWidth || 1280;
        const frameHeight = cameraVideo.videoHeight || 960;
        cameraCanvas.width = frameWidth;
        cameraCanvas.height = frameHeight;

        const ctx = cameraCanvas.getContext('2d');
        if (!ctx) {
            setCameraStatus('Could not start camera capture. Please upload the image instead.', true);
            return;
        }

        ctx.drawImage(cameraVideo, 0, 0, frameWidth, frameHeight);
        const blob = await new Promise(function (resolve) {
            cameraCanvas.toBlob(resolve, 'image/jpeg', 0.92);
        });
        if (!blob) {
            setCameraStatus('Could not capture the photo. Please try again.', true);
            return;
        }

        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const file = new File([blob], 'payment-proof-' + timestamp + '.jpg', { type: 'image/jpeg' });
        const transfer = new DataTransfer();
        transfer.items.add(file);
        activeProofInput.files = transfer.files;
        activeProofInput.dispatchEvent(new Event('change', { bubbles: true }));
        closeCameraModal();
    }

    proofInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            updateProofFileName(input);
        });
        updateProofFileName(input);
    });

    cameraButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = button.getAttribute('data-camera-target') || '';
            const input = document.getElementById(targetId);
            if (input) {
                openCameraForInput(input);
            }
        });
    });

    if (cameraTakeBtn) {
        cameraTakeBtn.addEventListener('click', captureProofPhoto);
    }
    if (cameraCancelBtn) {
        cameraCancelBtn.addEventListener('click', closeCameraModal);
    }
    if (cameraModal) {
        cameraModal.addEventListener('click', function (event) {
            if (event.target === cameraModal) {
                closeCameraModal();
            }
        });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && cameraModal && cameraModal.classList.contains('open')) {
            closeCameraModal();
        }
    });
    window.addEventListener('beforeunload', stopCameraStream);

    function currentDateKey() {
        if (eventType === 'date_range') {
            return '0';
        }
        return String(dateSelect ? (dateSelect.value || '0') : '0');
    }

    function currentPackageData() {
        const packageId = String(packageSelect.value || '');
        const dateKey = currentDateKey();
        if (!packageId || !packageData[packageId]) {
            return null;
        }
        if (packageData[packageId][dateKey]) {
            return packageData[packageId][dateKey];
        }
        const fallbackKeys = Object.keys(packageData[packageId]);
        if (fallbackKeys.length > 0) {
            return packageData[packageId][fallbackKeys[0]];
        }
        return null;
    }

    function safeNumber(value, fallback) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function formatMoney(value) {
        return safeNumber(value, 0).toFixed(2);
    }

    function computePlan(pkg) {
        const persons = Math.max(parseInt(qtyInput.value || '1', 10) || 1, 1);
        const priceTotal = safeNumber(pkg ? pkg.price_total : 0, 0);
        const advanceAmount = safeNumber(pkg ? pkg.advance_amount : 0, 0);
        let paymentMode = pkg && pkg.payment_mode ? String(pkg.payment_mode) : 'full';
        if (!['full', 'advance', 'optional'].includes(paymentMode)) {
            paymentMode = 'full';
        }

        let choice = paymentChoiceSelect ? String(paymentChoiceSelect.value || 'full') : 'full';
        if (paymentMode === 'full') {
            choice = 'full';
        } else if (paymentMode === 'advance') {
            choice = 'advance';
        } else if (choice !== 'advance') {
            choice = 'full';
        }

        const totalAmount = priceTotal * persons;
        let advanceTotal = advanceAmount * persons;
        if (advanceTotal > totalAmount) {
            advanceTotal = totalAmount;
        }

        let dueNow = totalAmount;
        if (choice === 'advance' && advanceTotal > 0 && advanceTotal < totalAmount) {
            dueNow = advanceTotal;
        }

        return {
            priceTotal: priceTotal,
            advanceAmount: advanceAmount,
            totalAmount: totalAmount,
            dueNow: dueNow,
            remainingAfter: Math.max(totalAmount - dueNow, 0),
            paymentMode: paymentMode,
            paymentChoice: choice
        };
    }

    function updateSummary() {
        const pkg = currentPackageData();
        const plan = computePlan(pkg);
        const seatsLeft = pkg && pkg.seats_left !== null ? safeNumber(pkg.seats_left, 0) : null;
        const persons = Math.max(parseInt(qtyInput.value || '1', 10) || 1, 1);
        const waitlistMode = (seatsLeft !== null && seatsLeft < persons);
        const isPaid = pkg ? safeNumber(pkg.is_paid, 1) === 1 : true;

        if (summaryType) summaryType.textContent = isPaid ? 'Paid Package' : 'Free Package';
        if (summaryTotalSeats) summaryTotalSeats.textContent = pkg && pkg.total_seats !== null ? String(pkg.total_seats) : 'Unlimited';
        if (summarySeatsLeft) summarySeatsLeft.textContent = seatsLeft !== null ? String(seatsLeft) : 'Unlimited';
        if (summaryPrice) summaryPrice.textContent = formatMoney(plan.priceTotal);
        if (summaryAdvance) summaryAdvance.textContent = formatMoney(plan.advanceAmount);
        if (summaryDueNow) summaryDueNow.textContent = formatMoney(plan.dueNow);
        if (summaryRemaining) summaryRemaining.textContent = formatMoney(plan.remainingAfter);
        if (summaryOutcome) summaryOutcome.textContent = waitlistMode ? 'Waitlist Required' : 'Seats Available';

        if (packageDescriptionBlock && packageDescriptionText) {
            const desc = pkg && pkg.description ? String(pkg.description) : '';
            packageDescriptionBlock.style.display = desc !== '' ? 'block' : 'none';
            packageDescriptionText.textContent = desc;
        }

        if (paymentChoiceSelect && pkg) {
            paymentChoiceSelect.value = plan.paymentChoice;
            paymentChoiceSelect.disabled = (!isPaid || plan.paymentMode !== 'optional');
            if (paymentChoiceHelp) {
                if (!isPaid) {
                    paymentChoiceHelp.textContent = 'Free package, so no payment choice is needed.';
                } else if (plan.paymentMode === 'optional') {
                    paymentChoiceHelp.textContent = 'This package allows full or advance collection.';
                } else if (plan.paymentMode === 'advance') {
                    paymentChoiceHelp.textContent = 'This package only allows advance collection first.';
                } else {
                    paymentChoiceHelp.textContent = 'This package requires the full amount.';
                }
            }
        }

        const currentMode = paymentCollectionSelect ? String(paymentCollectionSelect.value || 'collect_later') : 'collect_later';
        const collectionAvailability = {
            collect_later: true,
            cash: true,
            online: true
        };
        if (paymentCollectionSelect) {
            Array.from(paymentCollectionSelect.options).forEach(function (option) {
                const mode = String(option.value || '');
                const enabled = !!collectionAvailability[mode];
                option.hidden = !enabled;
                option.disabled = !enabled;
            });
            if (!collectionAvailability[currentMode]) {
                if (collectionAvailability.collect_later) {
                    paymentCollectionSelect.value = 'collect_later';
                }
            }
        }

        const chosenMode = paymentCollectionSelect ? String(paymentCollectionSelect.value || 'collect_later') : 'collect_later';
        if (paymentCollectionHelp) {
            if (!isPaid) {
                paymentCollectionHelp.textContent = 'Free package, so no payment collection is needed.';
            } else if (chosenMode === 'cash') {
                paymentCollectionHelp.textContent = isMainAdminActor
                    ? 'Cash collection will be auto approved immediately.'
                    : 'Cash collection will be sent to admin for approval.';
            } else if (chosenMode === 'online') {
                paymentCollectionHelp.textContent = isMainAdminActor
                    ? 'Online payment will be auto approved immediately.'
                    : 'Online payment will be sent to admin for approval.';
            } else {
                paymentCollectionHelp.textContent = 'Create the booking now and collect payment later from admin or the customer payment page.';
            }
        }

        if (cashFields) cashFields.style.display = (chosenMode === 'cash' && !waitlistMode && isPaid) ? 'grid' : 'none';
        if (onlineFields) onlineFields.style.display = (chosenMode === 'online' && !waitlistMode && isPaid) ? 'grid' : 'none';
        if (waitlistNote) waitlistNote.style.display = waitlistMode ? 'block' : 'none';

        if (cashReferenceInput) cashReferenceInput.required = (chosenMode === 'cash' && !waitlistMode && isPaid);
        if (cashProofInput) cashProofInput.required = (chosenMode === 'cash' && !waitlistMode && isPaid);
        if (onlineInstrumentSelect) onlineInstrumentSelect.required = (chosenMode === 'online' && !waitlistMode && isPaid);
        if (onlineReferenceInput) onlineReferenceInput.required = (chosenMode === 'online' && !waitlistMode && isPaid);
        if (onlineProofInput) onlineProofInput.required = (chosenMode === 'online' && !waitlistMode && isPaid);

        if (submitBtn) {
            if (waitlistMode) {
                submitBtn.textContent = 'Create Waitlisted Booking';
            } else if (!isPaid) {
                submitBtn.textContent = 'Create Free Booking';
            } else if (chosenMode === 'cash') {
                submitBtn.textContent = isMainAdminActor ? 'Create & Auto Approve Cash Payment' : 'Create & Submit Cash Payment';
            } else if (chosenMode === 'online') {
                submitBtn.textContent = isMainAdminActor ? 'Create & Auto Approve Online Payment' : 'Create & Submit Online Payment';
            } else {
                submitBtn.textContent = 'Book Now Pay Later';
            }
        }
    }

    if (dateSelect) {
        dateSelect.addEventListener('change', updateSummary);
    }
    packageSelect.addEventListener('change', updateSummary);
    qtyInput.addEventListener('input', updateSummary);
    if (paymentChoiceSelect) {
        paymentChoiceSelect.addEventListener('change', updateSummary);
    }
    if (paymentCollectionSelect) {
        paymentCollectionSelect.addEventListener('change', updateSummary);
    }

    updateSummary();
})();
</script>
</body>
</html>
