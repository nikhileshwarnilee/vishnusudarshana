<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/event_module.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

vs_event_ensure_tables($pdo);

$registrationId = isset($_GET['registration_id']) ? (int)$_GET['registration_id'] : (int)($_POST['registration_id'] ?? 0);
$draftToken = trim((string)($_GET['draft_token'] ?? $_POST['draft_token'] ?? ''));
$isDraftMode = false;
$draftData = null;
if ($registrationId <= 0 && $draftToken !== '' && isset($_SESSION['event_registration_drafts'][$draftToken]) && is_array($_SESSION['event_registration_drafts'][$draftToken])) {
    $isDraftMode = true;
    $draftData = $_SESSION['event_registration_drafts'][$draftToken];
}
if (!$isDraftMode && $registrationId <= 0) {
    header('Location: events.php');
    exit;
}

$respondJson = static function (array $payload): void {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

$loadRegistrationById = static function (PDO $pdo, int $regId): ?array {
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
    $stmt = $pdo->prepare($regSql);
    $stmt->execute([$regId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
};

$registration = null;
$registrationDisplayDate = '';
if ($isDraftMode) {
    $draftEventId = (int)($draftData['event_id'] ?? 0);
    $draftPackageId = (int)($draftData['package_id'] ?? 0);
    $draftPersons = max((int)($draftData['persons'] ?? 1), 1);
    $draftEventType = vs_event_normalize_event_type((string)($draftData['event_type'] ?? 'single_day'));
    $draftDateId = max((int)($draftData['event_date_id'] ?? 0), 0);

    $eventStmt = $pdo->prepare("SELECT * FROM events WHERE id = ? LIMIT 1");
    $eventStmt->execute([$draftEventId]);
    $draftEvent = $eventStmt->fetch(PDO::FETCH_ASSOC);
    if (!$draftEvent) {
        header('Location: events.php');
        exit;
    }

    $activeDates = vs_event_get_event_dates_cached($pdo, $draftEventId, true);
    if ($draftEventType === 'single_day') {
        $draftDateId = !empty($activeDates) ? (int)$activeDates[0]['id'] : 0;
    } elseif ($draftEventType === 'date_range') {
        $draftDateId = 0;
    } elseif ($draftDateId <= 0 && !empty($activeDates)) {
        $draftDateId = (int)$activeDates[0]['id'];
    }

    $selectedDateLabel = (string)$draftEvent['event_date'];
    if ($draftEventType === 'date_range') {
        $selectedDateLabel = vs_event_build_range_label($activeDates, (string)$draftEvent['event_date']);
    } elseif ($draftDateId > 0) {
        foreach ($activeDates as $dr) {
            if ((int)($dr['id'] ?? 0) === $draftDateId) {
                $selectedDateLabel = (string)($dr['event_date'] ?? $selectedDateLabel);
                break;
            }
        }
    }

    $pkgStmt = $pdo->prepare("SELECT
            p.*,
            COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS effective_price_total
        FROM event_packages p
        LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = ?
        WHERE p.id = ? AND p.event_id = ?
        LIMIT 1");
    $pkgStmt->execute([$draftDateId, $draftPackageId, $draftEventId]);
    $draftPackage = $pkgStmt->fetch(PDO::FETCH_ASSOC);
    if (!$draftPackage) {
        header('Location: events.php');
        exit;
    }
    $effectivePrice = (float)($draftPackage['effective_price_total'] ?? $draftPackage['price_total'] ?? $draftPackage['price'] ?? 0);

    $registration = [
        'id' => 0,
        'booking_reference' => '',
        'event_id' => $draftEventId,
        'event_title' => (string)$draftEvent['title'],
        'event_slug' => (string)$draftEvent['slug'],
        'selected_event_date' => $selectedDateLabel,
        'location' => (string)$draftEvent['location'],
        'package_id' => $draftPackageId,
        'package_name' => (string)$draftPackage['package_name'],
        'package_price' => $effectivePrice,
        'price_total' => $effectivePrice,
        'advance_amount' => (float)($draftPackage['advance_amount'] ?? 0),
        'payment_mode' => (string)($draftPackage['payment_mode'] ?? 'full'),
        'is_paid' => (int)($draftPackage['is_paid'] ?? 1),
        'payment_methods' => (string)($draftPackage['payment_methods'] ?? ''),
        'upi_id' => (string)($draftPackage['upi_id'] ?? ''),
        'upi_qr_image' => (string)($draftPackage['upi_qr_image'] ?? ''),
        'package_upi_id_snapshot' => '',
        'package_upi_qr_snapshot' => '',
        'payment_method' => '',
        'upi_id_used' => '',
        'upi_qr_used' => '',
        'transaction_id' => '',
        'screenshot' => '',
        'payment_record_status' => '',
        'payment_amount' => 0,
        'payment_type' => 'full',
        'amount_paid' => 0,
        'remaining_amount' => 0,
        'name' => (string)($draftData['name'] ?? ''),
        'phone' => (string)($draftData['phone'] ?? ''),
        'persons' => $draftPersons,
        'payment_status' => 'Unpaid',
        'verification_status' => 'Pending',
        'event_type' => $draftEventType,
        'event_date_id' => $draftDateId,
    ];
    $registrationDisplayDate = $selectedDateLabel;
} else {
    $registration = $loadRegistrationById($pdo, $registrationId);
    if (!$registration) {
        header('Location: events.php');
        exit;
    }
    $bookingReference = trim((string)($registration['booking_reference'] ?? ''));
    if ($bookingReference === '') {
        $bookingReference = vs_event_assign_booking_reference($pdo, $registrationId);
        if ($bookingReference !== '') {
            $registration['booking_reference'] = $bookingReference;
        }
    }
    $registrationDisplayDate = vs_event_get_registration_date_display($pdo, $registration, (string)($registration['selected_event_date'] ?? ''));
}

$whatsappEnabledForEvent = vs_event_is_whatsapp_enabled($pdo, (int)($registration['event_id'] ?? 0));
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

$materializeDraft = static function (PDO $pdo, array $draft, bool $markAsPaidForFree = false) use ($loadRegistrationById): int {
    $existingRegId = (int)($draft['registration_id'] ?? 0);
    if ($existingRegId > 0) {
        $existingRow = $loadRegistrationById($pdo, $existingRegId);
        if ($existingRow) {
            return $existingRegId;
        }
    }

    $eventId = (int)($draft['event_id'] ?? 0);
    $packageId = (int)($draft['package_id'] ?? 0);
    $eventType = vs_event_normalize_event_type((string)($draft['event_type'] ?? 'single_day'));
    $selectedDateId = max((int)($draft['event_date_id'] ?? 0), 0);
    if ($eventType === 'date_range') {
        $selectedDateId = 0;
    }
    $name = trim((string)($draft['name'] ?? ''));
    $phone = trim((string)($draft['phone'] ?? ''));
    $persons = max((int)($draft['persons'] ?? 1), 1);
    $dynamicValues = isset($draft['dynamic_values']) && is_array($draft['dynamic_values']) ? $draft['dynamic_values'] : [];

    if ($eventId <= 0 || $packageId <= 0 || $name === '' || $phone === '') {
        throw new RuntimeException('Draft registration data is invalid. Please register again.');
    }

    $pdo->beginTransaction();
    try {
        $lockStmt = $pdo->prepare('SELECT p.id, p.event_id, p.package_name, p.is_paid, p.payment_methods, p.upi_id, p.upi_qr_image, p.price, p.price_total,
                COALESCE(pdp.price_total, (CASE WHEN p.price_total > 0 THEN p.price_total ELSE p.price END)) AS effective_price_total,
                p.seat_limit, p.status, e.title AS event_title, e.event_type, e.event_date, e.registration_start, e.registration_end, e.status AS event_status
            FROM event_packages p
            LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = ?
            INNER JOIN events e ON e.id = p.event_id
            WHERE p.id = ? AND p.event_id = ? LIMIT 1 FOR UPDATE');
        $lockStmt->execute([$selectedDateId, $packageId, $eventId]);
        $lockedPackage = $lockStmt->fetch(PDO::FETCH_ASSOC);
        if ($lockedPackage) {
            $lockedPrice = (float)($lockedPackage['effective_price_total'] ?? $lockedPackage['price_total'] ?? $lockedPackage['price'] ?? 0);
            $lockedPackage['price_total'] = $lockedPrice;
            $lockedPackage['price'] = $lockedPrice;
        }
        if (!$lockedPackage || (string)$lockedPackage['status'] !== 'Active') {
            throw new RuntimeException('Selected package is not available.');
        }
        if ((string)$lockedPackage['event_status'] !== 'Active') {
            throw new RuntimeException('Event is currently closed.');
        }
        $today = date('Y-m-d');
        if ($today < (string)$lockedPackage['registration_start'] || $today > (string)$lockedPackage['registration_end']) {
            throw new RuntimeException('Registration window is closed for this event.');
        }

        $actualEventType = vs_event_normalize_event_type((string)($lockedPackage['event_type'] ?? $eventType));
        $lockedDate = null;
        if ($actualEventType === 'single_day') {
            $singleDateStmt = $pdo->prepare("SELECT id, event_date, seat_limit, status
                FROM event_dates
                WHERE event_id = ? AND status = 'Active'
                ORDER BY event_date ASC, id ASC
                LIMIT 1
                FOR UPDATE");
            $singleDateStmt->execute([$eventId]);
            $lockedDate = $singleDateStmt->fetch(PDO::FETCH_ASSOC);
            if (!$lockedDate) {
                throw new RuntimeException('Configured event date is not available.');
            }
            $selectedDateId = (int)$lockedDate['id'];
        } elseif ($actualEventType === 'multi_select_dates') {
            if ($selectedDateId <= 0) {
                throw new RuntimeException('Please select a valid event date.');
            }
            $dateLockStmt = $pdo->prepare("SELECT id, event_date, seat_limit, status
                FROM event_dates
                WHERE id = ? AND event_id = ?
                LIMIT 1
                FOR UPDATE");
            $dateLockStmt->execute([$selectedDateId, $eventId]);
            $lockedDate = $dateLockStmt->fetch(PDO::FETCH_ASSOC);
            if (!$lockedDate || (string)$lockedDate['status'] !== 'Active') {
                throw new RuntimeException('Selected event date is not available.');
            }
        } else {
            $selectedDateId = 0;
        }

        $packageSeatLimit = isset($lockedPackage['seat_limit']) ? (int)$lockedPackage['seat_limit'] : 0;
        if ($packageSeatLimit > 0) {
            $usedStmt = $pdo->prepare("SELECT COALESCE(SUM(persons),0)
                FROM event_registrations
                WHERE package_id = ?
                  AND verification_status IN ('Pending', 'Approved', 'Auto Verified')
                  AND payment_status NOT IN ('Failed', 'Cancelled')
                  AND (? = 0 OR event_date_id = ?)
                FOR UPDATE");
            $usedStmt->execute([$packageId, $selectedDateId, $selectedDateId]);
            $usedSeats = (int)$usedStmt->fetchColumn();
            if (($usedSeats + $persons) > $packageSeatLimit) {
                throw new RuntimeException('Seats are full for the selected package and date.');
            }
        }

        if ($lockedDate && (int)($lockedDate['seat_limit'] ?? 0) > 0) {
            $dateSeatLimit = (int)$lockedDate['seat_limit'];
            $dateUsedStmt = $pdo->prepare("SELECT COALESCE(SUM(persons),0)
                FROM event_registrations
                WHERE event_id = ?
                  AND event_date_id = ?
                  AND verification_status IN ('Pending', 'Approved', 'Auto Verified')
                  AND payment_status NOT IN ('Failed', 'Cancelled')
                FOR UPDATE");
            $dateUsedStmt->execute([$eventId, $selectedDateId]);
            $dateUsed = (int)$dateUsedStmt->fetchColumn();
            if (($dateUsed + $persons) > $dateSeatLimit) {
                throw new RuntimeException('Selected date is fully booked. You can join waitlist.');
            }
        }

        $lockedIsPaid = vs_event_is_package_paid($lockedPackage);
        if ($markAsPaidForFree && $lockedIsPaid) {
            throw new RuntimeException('Free confirmation is not available for this package.');
        }
        $registrationPaymentStatus = ($markAsPaidForFree || !$lockedIsPaid) ? 'Paid' : 'Unpaid';
        $registrationVerificationStatus = ($markAsPaidForFree || !$lockedIsPaid) ? 'Auto Verified' : 'Pending';

        $lockedPackageMethods = vs_event_payment_methods_from_csv((string)($lockedPackage['payment_methods'] ?? ''), $lockedIsPaid);
        $registrationUpiIdSnapshot = null;
        $registrationUpiQrSnapshot = null;
        if ($lockedIsPaid && in_array('upi', $lockedPackageMethods, true)) {
            $tmpUpiId = trim((string)($lockedPackage['upi_id'] ?? ''));
            $tmpUpiQr = trim((string)($lockedPackage['upi_qr_image'] ?? ''));
            $registrationUpiIdSnapshot = ($tmpUpiId !== '') ? $tmpUpiId : null;
            $registrationUpiQrSnapshot = ($tmpUpiQr !== '') ? $tmpUpiQr : null;
        }

        $insertReg = $pdo->prepare('INSERT INTO event_registrations (event_id, package_id, event_date_id, package_upi_id_snapshot, package_upi_qr_snapshot, name, phone, persons, payment_status, verification_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insertReg->execute([
            $eventId,
            $packageId,
            $selectedDateId > 0 ? $selectedDateId : null,
            $registrationUpiIdSnapshot,
            $registrationUpiQrSnapshot,
            $name,
            $phone,
            $persons,
            $registrationPaymentStatus,
            $registrationVerificationStatus,
        ]);
        $newRegId = (int)$pdo->lastInsertId();
        $bookingReference = vs_event_assign_booking_reference($pdo, $newRegId);

        $dataInsert = $pdo->prepare('INSERT INTO event_registration_data (registration_id, field_name, value) VALUES (?, ?, ?)');
        $dataInsert->execute([$newRegId, 'Name', $name]);
        $dataInsert->execute([$newRegId, 'Phone', $phone]);
        $dataInsert->execute([$newRegId, 'Persons', (string)$persons]);
        $dataInsert->execute([$newRegId, 'Booking Reference', $bookingReference]);
        if ($lockedDate) {
            $dataInsert->execute([$newRegId, 'Selected Event Date', (string)$lockedDate['event_date']]);
        } elseif ($actualEventType === 'date_range') {
            $rangeLabel = vs_event_get_event_date_display($pdo, $eventId, (string)$lockedPackage['event_date'], 'date_range');
            $dataInsert->execute([$newRegId, 'Selected Event Date', $rangeLabel]);
        } else {
            $dataInsert->execute([$newRegId, 'Selected Event Date', (string)$lockedPackage['event_date']]);
        }
        foreach ($dynamicValues as $dv) {
            $fieldName = trim((string)($dv['field_name'] ?? ''));
            if ($fieldName === '') {
                continue;
            }
            $dataInsert->execute([$newRegId, $fieldName, (string)($dv['value'] ?? '')]);
        }

        if ($markAsPaidForFree || !$lockedIsPaid) {
            $freePayment = $pdo->prepare("INSERT INTO event_payments (registration_id, amount, payment_type, amount_paid, remaining_amount, payment_method, transaction_id, screenshot, status)
                VALUES (?, 0, 'full', 0, 0, 'Free', 'FREE', '', 'Paid')
                ON DUPLICATE KEY UPDATE
                    amount = VALUES(amount),
                    payment_type = VALUES(payment_type),
                    amount_paid = VALUES(amount_paid),
                    remaining_amount = VALUES(remaining_amount),
                    payment_method = VALUES(payment_method),
                    transaction_id = VALUES(transaction_id),
                    screenshot = VALUES(screenshot),
                    status = VALUES(status)");
            $freePayment->execute([$newRegId]);
        }

        $pdo->commit();
        return $newRegId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof RuntimeException) {
            throw $e;
        }
        throw new RuntimeException('Unable to start payment. Please try again.');
    }
};

$rememberDraftRegistration = static function (string $token, int $regId): void {
    if ($token === '' || $regId <= 0) {
        return;
    }
    if (!isset($_SESSION['event_registration_drafts'][$token]) || !is_array($_SESSION['event_registration_drafts'][$token])) {
        return;
    }
    $_SESSION['event_registration_drafts'][$token]['registration_id'] = $regId;
};
$clearDraft = static function (string $token): void {
    if ($token === '') {
        return;
    }
    if (isset($_SESSION['event_registration_drafts'][$token])) {
        unset($_SESSION['event_registration_drafts'][$token]);
    }
};

if (!$isDraftMode && !$packageIsPaid) {
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

        $receiptSeed = $isDraftMode ? ('draft_' . substr(sha1((string)$draftToken), 0, 10)) : ('reg_' . $registrationId);
        $orderData = [
            'receipt' => 'event_' . $receiptSeed . '_' . time(),
            'amount' => (int)round($dueNow * 100),
            'currency' => 'INR',
            'payment_capture' => 1,
        ];
        $order = $api->order->create($orderData);
        $orderId = (string)$order['id'];

        if (!$isDraftMode && $registrationId > 0) {
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
        }

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
    if (!$isDraftMode && (string)$registration['payment_status'] === 'Paid') {
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

        $targetRegistrationId = $registrationId;
        if ($isDraftMode) {
            if (!$draftData || !is_array($draftData)) {
                throw new RuntimeException('Registration session expired. Please register again.');
            }
            $targetRegistrationId = $materializeDraft($pdo, $draftData, false);
            $rememberDraftRegistration($draftToken, $targetRegistrationId);
        }
        if ($targetRegistrationId <= 0) {
            throw new RuntimeException('Registration not found.');
        }

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
        $lockStmt->execute([$targetRegistrationId]);
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
            if ($isDraftMode) {
                $clearDraft($draftToken);
            }
            $respondJson(['success' => true, 'redirect' => 'event-booking-confirmation.php?registration_id=' . $targetRegistrationId . '&status=paid']);
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
        $upsert->execute([$targetRegistrationId, $dueNow, $newPaymentType, $newPaid, $remainingAfter, $paymentId]);

        $pdo->prepare("UPDATE event_registrations SET payment_status = ?, verification_status = ? WHERE id = ?")
            ->execute([$newPaymentStatus, $newVerificationStatus, $targetRegistrationId]);

        if ($whatsappEnabledForEvent) {
            if ($remainingAfter <= 0) {
                $qrCodePath = vs_event_ensure_registration_qr($pdo, $targetRegistrationId);
                vs_event_send_whatsapp_notice('ticket_delivery', (string)$locked['phone'], [
                    'name' => (string)$locked['name'],
                    'event_name' => (string)$locked['event_title'],
                    'package_name' => (string)$locked['package_name'],
                    'event_date' => vs_event_get_registration_date_display($pdo, $locked, (string)($locked['selected_event_date'] ?? '')),
                    'amount' => (string)$totalAmount,
                    'booking_reference' => (string)($locked['booking_reference'] ?? ''),
                    'registration_id' => $targetRegistrationId,
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
                    'registration_id' => $targetRegistrationId,
                    'event_id' => (int)($locked['event_id'] ?? 0),
                ]);
            }
        }

        $pdo->commit();
        if ($isDraftMode) {
            $clearDraft($draftToken);
        }
        $redirectStatus = $remainingAfter <= 0 ? 'paid' : 'partial';
        $respondJson(['success' => true, 'redirect' => 'event-booking-confirmation.php?registration_id=' . $targetRegistrationId . '&status=' . $redirectStatus]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Event Razorpay verification failed: ' . $e->getMessage());
        $message = ($e instanceof RuntimeException && trim((string)$e->getMessage()) !== '') ? (string)$e->getMessage() : 'Payment verification failed.';
        $respondJson(['success' => false, 'message' => $message]);
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_free_booking') {
    if ($packageIsPaid) {
        $errors[] = 'This package requires payment.';
    } else {
        try {
            $targetRegistrationId = $registrationId;
            if ($isDraftMode) {
                if (!$draftData || !is_array($draftData)) {
                    throw new RuntimeException('Registration session expired. Please register again.');
                }
                $targetRegistrationId = $materializeDraft($pdo, $draftData, true);
                $rememberDraftRegistration($draftToken, $targetRegistrationId);
                $clearDraft($draftToken);
            }
            if ($targetRegistrationId <= 0) {
                throw new RuntimeException('Unable to confirm booking right now.');
            }
            header('Location: event-booking-confirmation.php?registration_id=' . $targetRegistrationId . '&status=paid');
            exit;
        } catch (Throwable $e) {
            $errors[] = ($e instanceof RuntimeException && trim((string)$e->getMessage()) !== '')
                ? (string)$e->getMessage()
                : 'Unable to confirm free booking. Please try again.';
            error_log('Free booking confirmation failed: ' . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'manual_submit') {
    if (!$allowUpi) {
        $errors[] = 'Manual UPI is not enabled for this package.';
    }
    if ($allowUpi && trim($effectiveUpiId) === '') {
        $errors[] = 'UPI ID is not configured for this package. Please contact admin.';
    }
    if (!$isDraftMode && (string)$registration['payment_status'] === 'Paid') {
        header('Location: event-booking-confirmation.php?registration_id=' . $registrationId . '&status=paid');
        exit;
    }
    if (!$isDraftMode && strtolower((string)$registration['payment_status']) === 'pending verification') {
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
            $targetRegistrationId = $registrationId;
            if ($isDraftMode) {
                if (!$draftData || !is_array($draftData)) {
                    throw new RuntimeException('Registration session expired. Please register again.');
                }
                $targetRegistrationId = $materializeDraft($pdo, $draftData, false);
                $rememberDraftRegistration($draftToken, $targetRegistrationId);
                $registrationId = $targetRegistrationId;
                $loadedRegistration = $loadRegistrationById($pdo, $targetRegistrationId);
                if ($loadedRegistration) {
                    $registration = $loadedRegistration;
                    $registrationDisplayDate = vs_event_get_registration_date_display($pdo, $registration, (string)($registration['selected_event_date'] ?? $registrationDisplayDate));
                }
            }
            if ($targetRegistrationId <= 0) {
                throw new RuntimeException('Unable to process this booking.');
            }

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
                $targetRegistrationId,
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
                ->execute([$targetRegistrationId]);

            if ($whatsappEnabledForEvent) {
                vs_event_send_whatsapp_notice('payment_pending_verification', (string)$registration['phone'], [
                    'name' => (string)$registration['name'],
                    'event_name' => (string)$registration['event_title'],
                    'package_name' => (string)$registration['package_name'],
                    'event_date' => $registrationDisplayDate,
                    'amount' => (string)$dueNow,
                    'booking_reference' => (string)($registration['booking_reference'] ?? ''),
                    'registration_id' => $targetRegistrationId,
                    'event_id' => (int)($registration['event_id'] ?? 0),
                ]);
            }

            $pdo->commit();
            if ($isDraftMode) {
                $clearDraft($draftToken);
            }
            header('Location: event-booking-confirmation.php?registration_id=' . $targetRegistrationId . '&status=pending');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = ($e instanceof RuntimeException && trim((string)$e->getMessage()) !== '')
                ? (string)$e->getMessage()
                : 'Unable to submit manual payment details. Please try again.';
            error_log('Manual UPI submit failed: ' . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cash_submit') {
    if (!$allowCash) {
        $errors[] = 'Cash payment is not enabled for this package.';
    }
    if (!$isDraftMode && (string)$registration['payment_status'] === 'Paid') {
        header('Location: event-booking-confirmation.php?registration_id=' . $registrationId . '&status=paid');
        exit;
    }
    if (!$isDraftMode && strtolower((string)$registration['payment_status']) === 'pending verification') {
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
            $targetRegistrationId = $registrationId;
            if ($isDraftMode) {
                if (!$draftData || !is_array($draftData)) {
                    throw new RuntimeException('Registration session expired. Please register again.');
                }
                $targetRegistrationId = $materializeDraft($pdo, $draftData, false);
                $rememberDraftRegistration($draftToken, $targetRegistrationId);
                $registrationId = $targetRegistrationId;
                $loadedRegistration = $loadRegistrationById($pdo, $targetRegistrationId);
                if ($loadedRegistration) {
                    $registration = $loadedRegistration;
                    $registrationDisplayDate = vs_event_get_registration_date_display($pdo, $registration, (string)($registration['selected_event_date'] ?? $registrationDisplayDate));
                }
            }
            if ($targetRegistrationId <= 0) {
                throw new RuntimeException('Unable to process this booking.');
            }

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
                $targetRegistrationId,
                $dueNow,
                (string)$cashContext['payment_type'],
                (float)$cashContext['amount_paid'],
                (float)$cashContext['remaining_before_payment'],
                $cashReference,
            ]);

            $pdo->prepare("UPDATE event_registrations SET payment_status = 'Pending Verification', verification_status = 'Pending' WHERE id = ?")
                ->execute([$targetRegistrationId]);

            if ($whatsappEnabledForEvent) {
                vs_event_send_whatsapp_notice('payment_pending_verification', (string)$registration['phone'], [
                    'name' => (string)$registration['name'],
                    'event_name' => (string)$registration['event_title'],
                    'package_name' => (string)$registration['package_name'],
                    'event_date' => $registrationDisplayDate,
                    'amount' => (string)$dueNow,
                    'booking_reference' => (string)($registration['booking_reference'] ?? ''),
                    'registration_id' => $targetRegistrationId,
                    'event_id' => (int)($registration['event_id'] ?? 0),
                ]);
            }

            $pdo->commit();
            if ($isDraftMode) {
                $clearDraft($draftToken);
            }
            header('Location: event-booking-confirmation.php?registration_id=' . $targetRegistrationId . '&status=pending');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = ($e instanceof RuntimeException && trim((string)$e->getMessage()) !== '')
                ? (string)$e->getMessage()
                : 'Unable to submit cash payment details. Please try again.';
            error_log('Cash submit failed: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'Event Payment';
require_once 'header.php';

$upiId = trim($effectiveUpiId);
$upiQrImage = trim($effectiveUpiQr);
$upiIntentBase = '';
if ($upiId !== '') {
    $upiNoteRef = trim((string)($registration['booking_reference'] ?? ''));
    if ($upiNoteRef === '') {
        $upiNoteRef = $isDraftMode ? ('DRAFT-' . strtoupper(substr((string)$draftToken, 0, 8))) : ('REG-' . $registrationId);
    }
    $upiNote = 'Event Booking ' . $upiNoteRef;
    $upiIntentBase = 'upi://pay?pa=' . rawurlencode($upiId)
        . '&pn=' . rawurlencode('Vishnusudarshana')
        . '&tn=' . rawurlencode($upiNote)
        . '&cu=INR&am=';
}
$isPaid = ((string)$registration['payment_status'] === 'Paid');
$showChoiceSelector = ((string)$packageData['payment_mode'] === 'optional' && !$isRemainingStage && !$isPaid);
$displayNowAmount = round($dueNow, 2);
$flowQuery = $isDraftMode
    ? ('draft_token=' . urlencode((string)$draftToken))
    : ('registration_id=' . (int)$registrationId);
$methodLabelMap = [
    'razorpay' => 'Razorpay',
    'upi' => 'Manual UPI',
    'cash' => 'Cash',
];
?>
<main class="event-payment-main" style="background-color:var(--cream-bg);">
    <section class="event-payment-wrap">
        <a href="event-detail.php?slug=<?php echo urlencode((string)$registration['event_slug']); ?>" class="back-link">&larr; Back to Event</a>

        <div class="card review-card">
            <div class="review-head">
                <h1>Review Registration Details</h1>
                <span class="payment-status-pill status-<?php echo strtolower(str_replace(' ', '-', (string)$registration['payment_status'])); ?>">
                    <?php echo htmlspecialchars((string)$registration['payment_status']); ?>
                </span>
            </div>

            <div class="review-grid">
                <div class="info-block">
                    <h3>Personal Details</h3>
                    <div class="info-line"><span>Name</span><strong><?php echo htmlspecialchars((string)$registration['name']); ?></strong></div>
                    <div class="info-line"><span>Phone</span><strong><?php echo htmlspecialchars((string)$registration['phone']); ?></strong></div>
                    <div class="info-line"><span>Persons</span><strong><?php echo (int)$registration['persons']; ?></strong></div>
                </div>
                <div class="info-block">
                    <h3>Event Details</h3>
                    <div class="info-line"><span>Event</span><strong><?php echo htmlspecialchars((string)$registration['event_title']); ?></strong></div>
                    <div class="info-line"><span>Event Date</span><strong><?php echo htmlspecialchars($registrationDisplayDate); ?></strong></div>
                    <div class="info-line"><span>Package</span><strong><?php echo htmlspecialchars((string)$registration['package_name']); ?></strong></div>
                </div>
                <div class="info-block">
                    <h3>Payment Summary</h3>
                    <div class="info-line"><span>Total Amount</span><strong>Rs. <?php echo number_format($totalAmount, 0, '.', ''); ?></strong></div>
                    <div class="info-line"><span>Paid Amount</span><strong>Rs. <?php echo number_format($amountPaid, 0, '.', ''); ?></strong></div>
                    <div class="info-line"><span>Amount Payable Now</span><strong>Rs. <span id="payNowAmount"><?php echo number_format($displayNowAmount, 0, '.', ''); ?></span></strong></div>
                </div>
            </div>

            <?php if ($showChoiceSelector): ?>
                <div class="payment-term-wrap">
                    <label for="paymentChoice">Choose Payment Term</label>
                    <select id="paymentChoice">
                        <option value="full" <?php echo ($paymentChoice === 'full') ? 'selected' : ''; ?>>
                            Pay Full Amount (Rs. <?php echo number_format((float)$optionalFullPlan['due_now'], 0, '.', ''); ?>)
                        </option>
                        <option value="advance" <?php echo ($paymentChoice === 'advance') ? 'selected' : ''; ?>>
                            Pay Advance Amount (Rs. <?php echo number_format((float)$optionalAdvancePlan['due_now'], 0, '.', ''); ?>)
                        </option>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($isRemainingStage): ?>
                <p class="review-note">Advance payment is already received. Complete the remaining amount now.</p>
            <?php endif; ?>

            <?php if (!empty($allowedPaymentMethods)): ?>
                <div class="method-badges">
                    <?php foreach ($allowedPaymentMethods as $m): ?>
                        <?php $badgeText = $methodLabelMap[$m] ?? ucfirst((string)$m); ?>
                        <span><?php echo htmlspecialchars($badgeText); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="card notice err">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars((string)$error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($isPaid): ?>
            <div class="card success-box">
                <h3>Payment already completed.</h3>
                <a class="btn-main" href="event-booking-confirmation.php?registration_id=<?php echo (int)$registrationId; ?>&status=paid">View Booking Confirmation</a>
            </div>
        <?php elseif (!$packageIsPaid): ?>
            <div class="card free-card">
                <h3>Free Registration</h3>
                <p>No payment is required for this package. Confirm your booking to continue.</p>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="action" value="confirm_free_booking">
                    <?php if ($isDraftMode): ?>
                        <input type="hidden" name="draft_token" value="<?php echo htmlspecialchars((string)$draftToken); ?>">
                    <?php else: ?>
                        <input type="hidden" name="registration_id" value="<?php echo (int)$registrationId; ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn-main btn-confirm">Confirm Free Booking</button>
                </form>
            </div>
        <?php else: ?>
            <div class="payment-grid">
                <?php if ($allowRazorpay): ?>
                    <div class="card method-card">
                        <h3>Online Payment (Razorpay)</h3>
                        <p>Instant payment via UPI, card, net banking, or wallet.</p>
                        <button id="razorpayPayBtn" class="btn-main">
                            Pay Rs. <span id="rzpAmountText"><?php echo number_format($displayNowAmount, 0, '.', ''); ?></span> Securely
                        </button>
                        <p id="razorpayMsg" class="small"></p>
                    </div>
                <?php endif; ?>

                <?php if ($allowUpi): ?>
                    <div class="card method-card">
                        <h3>Manual UPI Payment</h3>
                        <p>Transfer using your UPI app and submit transaction details for verification.</p>
                        <p><strong>UPI ID:</strong> <?php echo htmlspecialchars($upiId); ?></p>
                        <?php if ($upiQrImage !== ''): ?>
                            <div class="qr-wrap">
                                <img src="<?php echo htmlspecialchars($upiQrImage); ?>" alt="UPI QR">
                            </div>
                        <?php endif; ?>
                        <?php if ($upiIntentBase !== ''): ?>
                            <a href="<?php echo htmlspecialchars($upiIntentBase . number_format($displayNowAmount, 0, '.', '')); ?>" class="btn-main btn-link" id="upiIntentBtn">
                                Pay Rs. <span id="upiIntentAmountText"><?php echo number_format($displayNowAmount, 0, '.', ''); ?></span> in UPI App
                            </a>
                        <?php endif; ?>
                        <form method="post" enctype="multipart/form-data" autocomplete="off">
                            <input type="hidden" name="action" value="manual_submit">
                            <?php if ($isDraftMode): ?>
                                <input type="hidden" name="draft_token" value="<?php echo htmlspecialchars((string)$draftToken); ?>">
                            <?php else: ?>
                                <input type="hidden" name="registration_id" value="<?php echo (int)$registrationId; ?>">
                            <?php endif; ?>
                            <input type="hidden" id="manualPaymentChoice" name="payment_choice" value="<?php echo htmlspecialchars($paymentChoice); ?>">
                            <div class="form-group">
                                <label>Transaction ID</label>
                                <input type="text" name="manual_transaction_id" required>
                            </div>
                            <div class="form-group">
                                <label>Payment Screenshot</label>
                                <input type="file" name="manual_screenshot" accept=".jpg,.jpeg,.png,.webp" required>
                            </div>
                            <button type="submit" class="btn-main btn-alt2">Submit for Verification</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($allowCash): ?>
                    <div class="card method-card">
                        <h3>Cash Payment</h3>
                        <p>Share your cash receipt/reference for verification.</p>
                        <form method="post" autocomplete="off">
                            <input type="hidden" name="action" value="cash_submit">
                            <?php if ($isDraftMode): ?>
                                <input type="hidden" name="draft_token" value="<?php echo htmlspecialchars((string)$draftToken); ?>">
                            <?php else: ?>
                                <input type="hidden" name="registration_id" value="<?php echo (int)$registrationId; ?>">
                            <?php endif; ?>
                            <input type="hidden" id="cashPaymentChoice" name="payment_choice" value="<?php echo htmlspecialchars($paymentChoice); ?>">
                            <div class="form-group">
                                <label>Cash Reference (optional)</label>
                                <input type="text" name="cash_reference" placeholder="Receipt number / note">
                            </div>
                            <button type="submit" class="btn-main btn-alt2">Submit for Verification</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$allowRazorpay && !$allowUpi && !$allowCash): ?>
                <div class="card notice warn">
                    <p>No payment method is available right now for this package. Please contact support.</p>
                </div>
            <?php endif; ?>
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
    const postUrl = <?php echo json_encode('event-payment.php?' . $flowQuery); ?>;
    const flowData = <?php if ($isDraftMode): ?>{ draft_token: <?php echo json_encode((string)$draftToken); ?> }<?php else: ?>{ registration_id: <?php echo json_encode((int)$registrationId); ?> }<?php endif; ?>;
    const optionalAmounts = {
        full: <?php echo json_encode((float)$optionalFullPlan['due_now']); ?>,
        advance: <?php echo json_encode((float)$optionalAdvancePlan['due_now']); ?>
    };
    const defaultAmount = <?php echo json_encode((float)$displayNowAmount); ?>;

    function selectedChoice() {
        if (!paymentChoiceEl) return <?php echo json_encode((string)$paymentChoice); ?>;
        return paymentChoiceEl.value || 'full';
    }

    function formatAmount(amount) {
        return String(Math.round(Number(amount) || 0));
    }

    function refreshAmounts() {
        const choice = selectedChoice();
        const amount = (choice in optionalAmounts) ? optionalAmounts[choice] : defaultAmount;
        const displayAmount = formatAmount(amount);
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
        const merged = Object.assign({}, flowData, data || {});
        const body = new URLSearchParams(merged);
        return fetch(postUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function(r) { return r.json(); });
    }

    payBtn.addEventListener('click', function() {
        const choice = selectedChoice();
        payBtn.disabled = true;
        if (msgEl) msgEl.textContent = 'Creating Razorpay order...';

        postForm({ action: 'create_order', payment_choice: choice })
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
                    theme: { color: '#8e2d1f' },
                    handler: function(response) {
                        if (msgEl) msgEl.textContent = 'Verifying payment...';
                        postForm({
                            action: 'verify_razorpay',
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
                            if (msgEl) msgEl.textContent = err.message;
                            payBtn.disabled = false;
                        });
                    }
                };
                const rzp = new Razorpay(options);
                rzp.on('payment.failed', function(resp) {
                    const reason = (resp && resp.error && resp.error.description) ? resp.error.description : 'Payment failed. Please try again.';
                    if (msgEl) msgEl.textContent = reason;
                    payBtn.disabled = false;
                });
                rzp.open();
                if (msgEl) msgEl.textContent = '';
                payBtn.disabled = false;
            })
            .catch(function(err) {
                if (msgEl) msgEl.textContent = err.message;
                payBtn.disabled = false;
            });
    });
})();
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html,body{font-family:'Marcellus',serif!important}
.event-payment-main{min-height:100vh;padding:1.8rem 0 4.5rem}
.event-payment-wrap{max-width:1080px;margin:0 auto;padding:0 14px}
.back-link{display:inline-block;color:#7d1b14;text-decoration:none;font-weight:700;margin-bottom:12px}
.card{background:#fff;border:1px solid #ecd8d8;border-radius:16px;box-shadow:0 10px 26px rgba(128,40,24,.08);padding:16px;margin-bottom:14px}
.review-card{background:linear-gradient(180deg,#fffdf8 0%,#fff 62%)}
.review-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
h1{margin:0;color:#7d1b14;font-size:1.6rem}
h3{margin:0 0 10px;color:#7d1b14;font-size:1.06rem}
.payment-status-pill{display:inline-block;padding:6px 10px;border-radius:999px;font-size:.82rem;font-weight:700;border:1px solid #ddc1b9;background:#fff7ef;color:#7d1b14}
.payment-status-pill.status-paid{background:#eaf8ef;color:#1d6b3d;border-color:#b9e2c8}
.payment-status-pill.status-partial-paid{background:#fff7e5;color:#8a5b00;border-color:#efd59f}
.payment-status-pill.status-pending-verification,.payment-status-pill.status-unpaid{background:#fff5e8;color:#8a4b17;border-color:#ebcfaa}
.review-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;margin-top:12px}
.info-block{border:1px solid #efdfd9;border-radius:12px;padding:11px;background:#fffcfa}
.info-line{display:flex;justify-content:space-between;gap:10px;padding:5px 0;font-size:.93rem;border-bottom:1px dashed #efdcd4}
.info-line:last-child{border-bottom:none}
.info-line span{color:#6f5a54}
.info-line strong{color:#3d2d2a;text-align:right}
.payment-term-wrap{max-width:420px;margin-top:14px}
.payment-term-wrap label{display:block;color:#7d1b14;font-weight:700;font-size:.94rem;margin-bottom:6px}
.payment-term-wrap select{width:100%;border:1px solid #ddc1b9;border-radius:10px;padding:9px 10px;background:#fff;font-size:.95rem}
.review-note{margin:10px 0 0;color:#8a4b17;font-size:.9rem;font-weight:700}
.method-badges{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.method-badges span{display:inline-block;background:#f6ebe8;border:1px solid #e4cbc4;color:#5b2c24;border-radius:999px;padding:4px 10px;font-size:.8rem;font-weight:700}
.payment-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:12px}
.method-card p{margin:0 0 10px;color:#4b3d3b;font-size:.92rem}
.qr-wrap{margin:8px 0 10px}
.qr-wrap img{width:180px;max-width:100%;height:auto;border:1px solid #e8d7d1;border-radius:10px;background:#fff;padding:3px}
.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:10px}
.form-group label{color:#7d1b14;font-weight:700;font-size:.9rem}
input,select{box-sizing:border-box}
.form-group input{width:100%;border:1px solid #e0c8c0;border-radius:10px;padding:9px 10px;font-size:.94rem}
.btn-main{display:inline-block;border:none;border-radius:10px;background:#7d1b14;color:#fff;font-weight:700;padding:10px 14px;cursor:pointer;text-decoration:none;transition:all .2s ease}
.btn-main:hover{background:#65150f}
.btn-link{display:inline-block;margin-bottom:10px}
.btn-alt2{background:#1f4f8a}
.btn-alt2:hover{background:#173d6b}
.btn-confirm{background:#1d6b3d}
.btn-confirm:hover{background:#15512d}
.small{min-height:1.2em;color:#6b5955;font-size:.85rem;margin-top:8px}
.notice.err{background:#ffeaea;color:#b00020;font-weight:700}
.notice.warn{background:#fff5e8;color:#8a4b17;font-weight:700}
.success-box h3,.free-card h3{margin-top:0}
.success-box{background:#eaf8ef;border-color:#b9e2c8}
@media (max-width:700px){
    .event-payment-main{padding-top:1.1rem}
    h1{font-size:1.32rem}
    .card{padding:13px}
}
</style>
<?php require_once 'footer.php'; ?>
