<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/event_module.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    header('Location: events.php');
    exit;
}

vs_event_ensure_tables($pdo);

$eventStmt = $pdo->prepare('SELECT * FROM events WHERE slug = ? LIMIT 1');
$eventStmt->execute([$slug]);
$event = $eventStmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    header('Location: events.php');
    exit;
}

$eventType = vs_event_normalize_event_type((string)($event['event_type'] ?? 'single_day'));
$eventDates = vs_event_get_event_dates_cached($pdo, (int)$event['id'], true);
$singleDefaultDateId = !empty($eventDates) ? (int)$eventDates[0]['id'] : 0;
$rangeDateLabel = vs_event_build_range_label($eventDates, (string)$event['event_date']);

$selectedDateId = 0;
$selectedPackageId = isset($_GET['package']) ? (int)$_GET['package'] : 0;

if ($eventType === 'single_day') {
    $selectedDateId = $singleDefaultDateId;
} elseif ($eventType === 'date_range') {
    $selectedDateId = 0;
} elseif ($selectedDateId <= 0 && !empty($eventDates)) {
    $selectedDateId = (int)$eventDates[0]['id'];
}
$selectedDate = null;
foreach ($eventDates as $d) {
    if ((int)$d['id'] === $selectedDateId) {
        $selectedDate = $d;
        break;
    }
}
if ($eventType === 'multi_select_dates' && $selectedDate === null && !empty($eventDates)) {
    $selectedDate = $eventDates[0];
    $selectedDateId = (int)$selectedDate['id'];
}

$packageDateContextId = ($eventType === 'date_range') ? 0 : $selectedDateId;
$packages = vs_event_fetch_packages_with_seats($pdo, (int)$event['id'], true, $packageDateContextId);
$packageMap = [];
foreach ($packages as $pkg) {
    $packageMap[(int)$pkg['id']] = $pkg;
}
if ($selectedPackageId <= 0 && !empty($packages)) {
    $selectedPackageId = (int)$packages[0]['id'];
}
if ($selectedPackageId > 0 && !isset($packageMap[$selectedPackageId]) && !empty($packages)) {
    $selectedPackageId = (int)$packages[0]['id'];
}
$selectedPackage = $packageMap[$selectedPackageId] ?? null;

$fieldStmt = $pdo->prepare('SELECT * FROM event_form_fields WHERE event_id = ? ORDER BY id ASC');
$fieldStmt->execute([(int)$event['id']]);
$formFields = $fieldStmt->fetchAll(PDO::FETCH_ASSOC);
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

$registrationOpen = vs_event_is_registration_open($event);
$errors = [];
$old = [
    'name' => '',
    'phone' => '',
    'persons' => '1',
    'package_id' => $selectedPackageId > 0 ? (string)$selectedPackageId : '',
    'event_date_id' => (string)$selectedDateId,
    'dynamic' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientIp = vs_event_get_client_ip();
    $rateLimitBlocked = false;
    $isLocalRequest = in_array($clientIp, ['127.0.0.1', '::1'], true);
    try {
        if (!$isLocalRequest) {
            vs_event_record_registration_attempt($pdo, $clientIp);
            if (vs_event_count_recent_attempts($pdo, $clientIp, 60) > 5) {
                $errors[] = 'Too many registration attempts. Please try again later.';
                $rateLimitBlocked = true;
            }
        }
    } catch (Throwable $e) {
        error_log('Event attempt tracking failed: ' . $e->getMessage());
    }

    $selectedPackageId = (int)($_POST['package_id'] ?? $selectedPackageId);
    $selectedDateId = (int)($_POST['event_date_id'] ?? 0);
    if ($eventType === 'single_day') {
        $selectedDateId = $singleDefaultDateId;
    } elseif ($eventType === 'date_range') {
        $selectedDateId = 0;
    } elseif ($selectedDateId <= 0 && !empty($eventDates)) {
        $selectedDateId = (int)$eventDates[0]['id'];
    }

    $packageDateContextId = ($eventType === 'date_range') ? 0 : $selectedDateId;
    $packages = vs_event_fetch_packages_with_seats($pdo, (int)$event['id'], true, $packageDateContextId);
    $packageMap = [];
    foreach ($packages as $pkg) {
        $packageMap[(int)$pkg['id']] = $pkg;
    }
    if ($selectedPackageId <= 0 && !empty($packages)) {
        $selectedPackageId = (int)$packages[0]['id'];
    }
    $selectedPackage = $packageMap[$selectedPackageId] ?? null;

    $name = trim((string)($_POST['name'] ?? ''));
    $phoneRaw = trim((string)($_POST['phone'] ?? ''));
    $persons = (int)($_POST['persons'] ?? 1);
    if ($persons <= 0) {
        $persons = 1;
    }
    $dynamicInput = isset($_POST['dynamic']) && is_array($_POST['dynamic']) ? $_POST['dynamic'] : [];

    $old['name'] = $name;
    $old['phone'] = $phoneRaw;
    $old['persons'] = (string)$persons;
    $old['package_id'] = (string)$selectedPackageId;
    $old['event_date_id'] = (string)$selectedDateId;
    $old['dynamic'] = $dynamicInput;

    $dynamicValues = [];
    $nameFromDynamic = '';
    $phoneFromDynamic = '';
    $phone = '';
    if (!$rateLimitBlocked) {
        if (!$registrationOpen) {
            $errors[] = 'Registration is currently closed for this event.';
        }
        if (!isset($packageMap[$selectedPackageId])) {
            $errors[] = 'Selected package is not available for this event date.';
        }

        $selectedDate = null;
        if ($eventType === 'multi_select_dates' && !empty($eventDates)) {
            foreach ($eventDates as $d) {
                if ((int)$d['id'] === $selectedDateId) {
                    $selectedDate = $d;
                    break;
                }
            }
            if ($selectedDate === null) {
                $errors[] = 'Please select a valid event date.';
            }
        } elseif ($eventType === 'single_day' && $singleDefaultDateId > 0) {
            foreach ($eventDates as $d) {
                if ((int)$d['id'] === $singleDefaultDateId) {
                    $selectedDate = $d;
                    break;
                }
            }
            if ($selectedDate === null) {
                $errors[] = 'Configured event date is not available.';
            }
        }

        foreach ($formFields as $field) {
            $fieldId = (int)$field['id'];
            $fieldName = (string)$field['field_name'];
            $fieldType = strtolower((string)$field['field_type']);
            $required = (int)$field['required'] === 1;

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
                $options = array_filter(array_map('trim', preg_split('/[,\n\r]+/', (string)$field['field_options'])));
                if (!empty($options) && !in_array($value, $options, true)) {
                    $errors[] = 'Invalid value selected for ' . $fieldName . '.';
                }
            }
            $dynamicValues[] = ['field_name' => $fieldName, 'value' => $value];
        }

        if ($name === '' && $nameFromDynamic !== '') {
            $name = $nameFromDynamic;
        }
        if ($phoneRaw === '' && $phoneFromDynamic !== '') {
            $phoneRaw = $phoneFromDynamic;
        }
        $phone = preg_replace('/[^0-9]/', '', $phoneRaw);
        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }
        $old['name'] = $name;
        $old['phone'] = $phoneRaw;

        if ($name === '') {
            $errors[] = 'Name is required in registration form.';
        }
        if ($phone === '' || strlen($phone) < 10) {
            $errors[] = 'A valid phone is required in registration form.';
        }
    }

    if (empty($errors)) {
        try {
            $validateStmt = $pdo->prepare('SELECT p.id, p.event_id, p.package_name, p.price, p.price_total,
                    COALESCE(pdp.price_total, (CASE WHEN p.price_total > 0 THEN p.price_total ELSE p.price END)) AS effective_price_total,
                    p.seat_limit, p.status, e.title AS event_title, e.registration_start, e.registration_end, e.status AS event_status
                FROM event_packages p
                LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = ?
                INNER JOIN events e ON e.id = p.event_id
                WHERE p.id = ? AND p.event_id = ? LIMIT 1');
            $validateStmt->execute([$selectedDateId, $selectedPackageId, (int)$event['id']]);
            $selectedPackageRow = $validateStmt->fetch(PDO::FETCH_ASSOC);
            if ($selectedPackageRow) {
                $selectedPackageRow['price_total'] = (float)($selectedPackageRow['effective_price_total'] ?? $selectedPackageRow['price_total'] ?? $selectedPackageRow['price'] ?? 0);
            }

            if (!$selectedPackageRow || (string)$selectedPackageRow['status'] !== 'Active') {
                throw new RuntimeException('Selected package is not available.');
            }
            if ((string)$selectedPackageRow['event_status'] !== 'Active') {
                throw new RuntimeException('Event is currently closed.');
            }

            $today = date('Y-m-d');
            if ($today < (string)$selectedPackageRow['registration_start'] || $today > (string)$selectedPackageRow['registration_end']) {
                throw new RuntimeException('Registration window is closed for this event.');
            }

            if (($eventType === 'single_day' || $eventType === 'multi_select_dates') && $selectedDateId > 0) {
                $dateCheckStmt = $pdo->prepare("SELECT id, status
                    FROM event_dates
                    WHERE id = ? AND event_id = ?
                    LIMIT 1");
                $dateCheckStmt->execute([$selectedDateId, (int)$event['id']]);
                $selectedDateRow = $dateCheckStmt->fetch(PDO::FETCH_ASSOC);
                if (!$selectedDateRow || (string)$selectedDateRow['status'] !== 'Active') {
                    throw new RuntimeException('Selected event date is not available.');
                }
            }

            if (!isset($_SESSION['event_registration_drafts']) || !is_array($_SESSION['event_registration_drafts'])) {
                $_SESSION['event_registration_drafts'] = [];
            }
            $expireBefore = time() - (3 * 60 * 60);
            foreach ($_SESSION['event_registration_drafts'] as $k => $draftRow) {
                $createdAt = (int)($draftRow['created_at'] ?? 0);
                if ($createdAt > 0 && $createdAt < $expireBefore) {
                    unset($_SESSION['event_registration_drafts'][$k]);
                }
            }

            $draftToken = bin2hex(random_bytes(16));
            $_SESSION['event_registration_drafts'][$draftToken] = [
                'token' => $draftToken,
                'created_at' => time(),
                'event_id' => (int)$event['id'],
                'event_slug' => (string)$event['slug'],
                'event_type' => $eventType,
                'event_date_id' => $selectedDateId > 0 ? $selectedDateId : 0,
                'package_id' => $selectedPackageId,
                'name' => $name,
                'phone' => $phone,
                'persons' => $persons,
                'dynamic_values' => $dynamicValues,
            ];

            header('Location: event-payment.php?draft_token=' . urlencode($draftToken));
            exit;
        } catch (Throwable $e) {
            $msg = (string)$e->getMessage();
            if ($e instanceof RuntimeException) {
                $errors[] = $msg;
            } else {
                $errors[] = 'Unable to proceed to payment. Please try again.';
            }
            error_log('Event registration draft failed: ' . $msg);
        }
    }
}

$pageTitle = $event['title'] . ' | Register';
$displayDateId = (int)$old['event_date_id'];
$displayDate = (string)$event['event_date'];
if ($eventType === 'date_range') {
    $displayDate = $rangeDateLabel;
} elseif ($eventType === 'single_day' && !empty($eventDates)) {
    $displayDate = (string)$eventDates[0]['event_date'];
    $displayDateId = (int)$eventDates[0]['id'];
} else {
    foreach ($eventDates as $d) {
        if ((int)$d['id'] === $displayDateId) {
            $displayDate = (string)$d['event_date'];
            break;
        }
    }
}
$multiDateInfo = '';
if ($eventType === 'multi_select_dates') {
    $dateParts = [];
    foreach ($eventDates as $dateRow) {
        $dateParts[] = (string)$dateRow['event_date'];
    }
    $multiDateInfo = !empty($dateParts) ? implode(', ', $dateParts) : $displayDate;
}
$eventDateInfo = $displayDate;
if ($eventType === 'multi_select_dates') {
    $eventDateInfo = $multiDateInfo;
} elseif ($eventType === 'date_range') {
    $eventDateInfo = $rangeDateLabel;
}
$selectedPackageId = (int)$old['package_id'] > 0 ? (int)$old['package_id'] : $selectedPackageId;
if ($selectedPackageId > 0 && isset($packageMap[$selectedPackageId])) {
    $selectedPackage = $packageMap[$selectedPackageId];
}
$selectedPackageIsPaid = $selectedPackage ? vs_event_is_package_paid($selectedPackage) : true;
$selectedPackageSeatMap = [];
$selectedPackagePriceMap = [];
if ($selectedPackage) {
    if ($eventType === 'multi_select_dates') {
        foreach ($eventDates as $dateRow) {
            $dateId = (int)$dateRow['id'];
            $datePackages = vs_event_fetch_packages_with_seats($pdo, (int)$event['id'], true, $dateId);
            foreach ($datePackages as $datePackage) {
                if ((int)$datePackage['id'] === $selectedPackageId) {
                    $selectedPackageSeatMap[$dateId] = [
                        'seat_limit' => isset($datePackage['seat_limit']) ? (int)$datePackage['seat_limit'] : 0,
                        'seats_left' => ($datePackage['seats_left'] === null) ? null : (int)$datePackage['seats_left'],
                    ];
                    $selectedPackagePriceMap[$dateId] = $selectedPackageIsPaid ? (float)($datePackage['price_total'] ?? $datePackage['price'] ?? 0) : 0.0;
                    break;
                }
            }
        }
    } else {
        $seatKey = ($eventType === 'date_range') ? 0 : max($displayDateId, 0);
        $selectedPackageSeatMap[$seatKey] = [
            'seat_limit' => isset($selectedPackage['seat_limit']) ? (int)$selectedPackage['seat_limit'] : 0,
            'seats_left' => ($selectedPackage['seats_left'] === null) ? null : (int)$selectedPackage['seats_left'],
        ];
        $selectedPackagePriceMap[$seatKey] = $selectedPackageIsPaid ? (float)($selectedPackage['price_total'] ?? $selectedPackage['price'] ?? 0) : 0.0;
    }
}
$currentSeatKey = ($eventType === 'date_range') ? 0 : max($displayDateId, 0);
$currentSeatData = $selectedPackageSeatMap[$currentSeatKey] ?? null;
if ($currentSeatData === null && $selectedPackage) {
    $currentSeatData = [
        'seat_limit' => isset($selectedPackage['seat_limit']) ? (int)$selectedPackage['seat_limit'] : 0,
        'seats_left' => ($selectedPackage['seats_left'] === null) ? null : (int)$selectedPackage['seats_left'],
    ];
}
$seatLimitDisplay = ($currentSeatData === null || (int)$currentSeatData['seat_limit'] <= 0) ? 'Unlimited' : (string)((int)$currentSeatData['seat_limit']);
$seatsLeftDisplay = ($currentSeatData === null || $currentSeatData['seats_left'] === null) ? 'Unlimited' : (string)((int)$currentSeatData['seats_left']);
$fallbackPackagePrice = 0.0;
if (is_array($selectedPackage)) {
    $fallbackPackagePrice = (float)($selectedPackage['price_total'] ?? $selectedPackage['price'] ?? 0);
}
$currentPricePerPerson = (float)($selectedPackagePriceMap[$currentSeatKey] ?? $fallbackPackagePrice);
$personsQty = max((int)$old['persons'], 1);
$selectedPackageForPlan = $selectedPackage;
if ($selectedPackageForPlan) {
    if ($selectedPackageIsPaid) {
        $selectedPackageForPlan['price_total'] = $currentPricePerPerson;
        $selectedPackageForPlan['price'] = $currentPricePerPerson;
    } else {
        $selectedPackageForPlan['price_total'] = 0;
        $selectedPackageForPlan['price'] = 0;
        $selectedPackageForPlan['advance_amount'] = 0;
        $selectedPackageForPlan['payment_mode'] = 'full';
    }
}
$paymentPlan = $selectedPackageForPlan ? vs_event_calculate_payment_plan($selectedPackageForPlan, $personsQty, '') : [
    'payment_mode' => 'full',
    'price_total' => 0,
    'advance_amount' => 0,
    'total_amount' => 0,
    'advance_total' => 0,
    'due_now' => 0,
    'remaining_after_due' => 0,
];
require_once 'header.php';
?>
<main class="event-register-main" style="background-color:var(--cream-bg);">
    <section class="event-register-wrap">
        <a href="event-detail.php?slug=<?php echo urlencode((string)$event['slug']); ?>" class="back-link">&larr; Back to Event</a>
        <div class="card">
            <h1>Register For <?php echo htmlspecialchars((string)$event['title']); ?></h1>
            <p class="small">Event Date: <?php echo htmlspecialchars((string)$eventDateInfo); ?> | Location: <?php echo htmlspecialchars((string)$event['location']); ?></p>
            <p class="small">Registration window: <?php echo htmlspecialchars((string)$event['registration_start']); ?> to <?php echo htmlspecialchars((string)$event['registration_end']); ?></p>

            <?php if (!$registrationOpen): ?><div class="notice err">Registration is closed for this event.</div><?php endif; ?>
            <?php if (!empty($errors)): ?><div class="notice err"><?php foreach ($errors as $error) { echo '<div>' . htmlspecialchars((string)$error) . '</div>'; } ?></div><?php endif; ?>

            <form method="post" enctype="multipart/form-data" autocomplete="off">
                <div class="grid">
                    <div class="form-group">
                        <label>Select Event Date</label>
                        <select id="event_date_id" name="event_date_id" required>
                            <?php if ($eventType === 'multi_select_dates'): ?>
                                <?php foreach ($eventDates as $d): ?>
                                    <option value="<?php echo (int)$d['id']; ?>" <?php echo ((int)$displayDateId === (int)$d['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$d['event_date']); ?></option>
                                <?php endforeach; ?>
                            <?php elseif ($eventType === 'date_range'): ?>
                                <option value="0" selected><?php echo htmlspecialchars($rangeDateLabel); ?></option>
                            <?php else: ?>
                                <option value="<?php echo (int)$displayDateId; ?>" selected><?php echo htmlspecialchars($displayDate); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <input type="hidden" name="package_id" value="<?php echo (int)$selectedPackageId; ?>">
                    <?php if ($hasNameField): ?>
                        <input type="hidden" name="name" value="<?php echo htmlspecialchars((string)$old['name']); ?>">
                    <?php else: ?>
                        <div class="form-group"><label>Full Name</label><input type="text" name="name" value="<?php echo htmlspecialchars((string)$old['name']); ?>" required></div>
                    <?php endif; ?>
                    <?php if ($hasPhoneField): ?>
                        <input type="hidden" name="phone" value="<?php echo htmlspecialchars((string)$old['phone']); ?>">
                    <?php else: ?>
                        <div class="form-group">
                            <label>WhatsApp Number</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars((string)$old['phone']); ?>" required>
                            <small class="phone-note">Use your active WhatsApp number for further updates and communication.</small>
                        </div>
                    <?php endif; ?>
                    <div class="form-group"><label>Persons/Qty</label><input type="number" id="persons_qty" name="persons" min="1" max="25" value="<?php echo htmlspecialchars((string)$old['persons']); ?>" required></div>
                </div>

                <?php if ($selectedPackage): ?>
                    <div class="package-summary">
                        <h3 class="section-title">Selected Package Details</h3>
                        <div class="pkg-groups">
                            <div class="pkg-group">
                                <h4 class="pkg-group-title">Package Information</h4>
                                <div class="package-summary-grid">
                                    <div><strong>Package Name:</strong> <?php echo htmlspecialchars((string)$selectedPackage['package_name']); ?></div>
                                    <div><strong>Package Type:</strong> <?php echo $selectedPackageIsPaid ? 'Paid' : 'Free'; ?></div>
                                </div>
                            </div>
                            <div class="pkg-group">
                                <h4 class="pkg-group-title">Seat Availability</h4>
                                <div class="package-summary-grid">
                                    <div><strong>Total Seats:</strong> <span id="pkg_total_seats"><?php echo htmlspecialchars($seatLimitDisplay); ?></span></div>
                                    <div><strong>Available Seats:</strong> <span id="pkg_available_seats"><?php echo htmlspecialchars($seatsLeftDisplay); ?></span></div>
                                </div>
                            </div>
                            <div class="pkg-group">
                                <h4 class="pkg-group-title">Amount Summary</h4>
                                <div class="package-summary-grid">
                                    <div><strong>Package Price (Per Person):</strong> Rs. <span id="pkg_price_per_person"><?php echo number_format((float)$paymentPlan['price_total'], 0, '.', ''); ?></span></div>
                                    <div><strong>Advance (Per Person):</strong> Rs. <span id="pkg_advance_per_person"><?php echo number_format((float)$paymentPlan['advance_amount'], 0, '.', ''); ?></span></div>
                                    <div><strong>Total Amount:</strong> Rs. <span id="pkg_total_amount"><?php echo number_format((float)$paymentPlan['total_amount'], 0, '.', ''); ?></span></div>
                                    <div><strong>Pay Advance Amount:</strong> Rs. <span id="pkg_advance_total"><?php echo number_format((float)$paymentPlan['advance_total'], 0, '.', ''); ?></span></div>
                                </div>
                            </div>
                        </div>
                        <p class="pkg-note">Amounts above update automatically based on selected date and persons quantity.</p>
                        <?php if (trim((string)$selectedPackage['description']) !== ''): ?>
                            <p class="pkg-desc-block"><?php echo nl2br(htmlspecialchars((string)$selectedPackage['description'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="notice err">Selected package not found. Please go back and choose a package again.</div>
                <?php endif; ?>

                <?php if (!empty($formFields)): ?>
                    <h3 class="section-title">Fill Registration Form</h3>
                    <div class="grid">
                        <?php foreach ($formFields as $field): ?>
                            <?php
                            $fieldId = (int)$field['id'];
                            $fname = (string)$field['field_name'];
                            $ftype = strtolower((string)$field['field_type']);
                            $frequired = (int)$field['required'] === 1;
                            $saved = (string)($old['dynamic'][$fieldId] ?? '');
                            $placeholder = trim((string)($field['field_placeholder'] ?? ''));
                            $placeholderAttr = $placeholder !== '' ? ' placeholder="' . htmlspecialchars($placeholder) . '"' : '';
                            $fieldNameKey = strtolower(preg_replace('/\s+/', ' ', trim($fname)));
                            $isPhoneField = (
                                in_array($ftype, ['phone', 'tel'], true)
                                || strpos($fieldNameKey, 'phone') !== false
                                || strpos($fieldNameKey, 'mobile') !== false
                                || strpos($fieldNameKey, 'whatsapp') !== false
                                || strpos($fieldNameKey, 'contact') !== false
                            );
                            $phoneLabelSuffix = ($isPhoneField && strpos($fieldNameKey, 'whatsapp') === false) ? ' (WhatsApp Number)' : '';
                            $options = array_filter(array_map('trim', preg_split('/[,\n\r]+/', (string)$field['field_options'])));
                            ?>
                            <div class="form-group">
                                <label><?php echo htmlspecialchars($fname); ?><?php echo $phoneLabelSuffix; ?><?php if ($frequired): ?> <span class="req">*</span><?php endif; ?></label>
                                <?php if ($ftype === 'textarea'): ?>
                                    <textarea name="dynamic[<?php echo $fieldId; ?>]"<?php echo $placeholderAttr; ?> <?php echo $frequired ? 'required' : ''; ?>><?php echo htmlspecialchars($saved); ?></textarea>
                                <?php elseif ($ftype === 'select'): ?>
                                    <select name="dynamic[<?php echo $fieldId; ?>]" <?php echo $frequired ? 'required' : ''; ?>><option value=""><?php echo htmlspecialchars($placeholder !== '' ? $placeholder : '-- Select --'); ?></option><?php foreach ($options as $opt): ?><option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($saved === $opt) ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option><?php endforeach; ?></select>
                                <?php elseif ($ftype === 'file'): ?>
                                    <input type="file" name="dynamic_file_<?php echo $fieldId; ?>" <?php echo $frequired ? 'required' : ''; ?> accept=".jpg,.jpeg,.png,.webp,.pdf">
                                <?php else: ?>
                                    <?php $inputType = ($ftype === 'phone') ? 'tel' : (($ftype === 'number') ? 'number' : (($ftype === 'date') ? 'date' : 'text')); ?>
                                    <input type="<?php echo $inputType; ?>" name="dynamic[<?php echo $fieldId; ?>]" value="<?php echo htmlspecialchars($saved); ?>"<?php echo $placeholderAttr; ?> <?php echo $frequired ? 'required' : ''; ?>>
                                <?php endif; ?>
                                <?php if ($isPhoneField): ?>
                                    <small class="phone-note">Use your active WhatsApp number for further updates and communication.</small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <button type="submit" class="submit-btn" <?php echo (!$registrationOpen || !$selectedPackage) ? 'disabled' : ''; ?>>Proceed To Payment</button>
            </form>
        </div>
    </section>
</main>
<script>
(function () {
    const qtyInput = document.getElementById('persons_qty');
    const dateSelect = document.getElementById('event_date_id');
    if (!qtyInput) {
        return;
    }

    const defaultPricePerPerson = <?php echo json_encode((float)($paymentPlan['price_total'] ?? 0)); ?>;
    const advancePerPerson = <?php echo json_encode((float)($paymentPlan['advance_amount'] ?? 0)); ?>;
    const seatMap = <?php echo json_encode($selectedPackageSeatMap); ?> || {};
    const priceMap = <?php echo json_encode($selectedPackagePriceMap); ?> || {};

    const totalSeatsEl = document.getElementById('pkg_total_seats');
    const availableSeatsEl = document.getElementById('pkg_available_seats');
    const pricePerPersonEl = document.getElementById('pkg_price_per_person');
    const totalAmountEl = document.getElementById('pkg_total_amount');
    const advanceTotalEl = document.getElementById('pkg_advance_total');

    function asInt(value, fallback) {
        const parsed = parseInt(value, 10);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function formatAmount(value) {
        return String(Math.max(0, Math.round(value)));
    }

    function currentPricePerPerson() {
        if (!dateSelect) {
            return Number(defaultPricePerPerson) || 0;
        }
        const key = String(dateSelect.value || '0');
        if (Object.prototype.hasOwnProperty.call(priceMap, key)) {
            return Number(priceMap[key]) || 0;
        }
        return Number(defaultPricePerPerson) || 0;
    }

    function updateSeats() {
        if (!dateSelect || !totalSeatsEl || !availableSeatsEl) {
            return;
        }
        const key = String(dateSelect.value || '0');
        const data = seatMap[key];
        if (!data) {
            return;
        }
        const totalSeats = asInt(data.seat_limit, 0);
        const seatsLeftRaw = data.seats_left;
        totalSeatsEl.textContent = totalSeats > 0 ? String(totalSeats) : 'Unlimited';
        availableSeatsEl.textContent = (seatsLeftRaw === null || seatsLeftRaw === undefined) ? 'Unlimited' : String(asInt(seatsLeftRaw, 0));
    }

    function updateTotals() {
        const qty = Math.max(asInt(qtyInput.value, 1), 1);
        const pricePerPerson = currentPricePerPerson();
        const totalAmount = pricePerPerson * qty;
        const advanceTotal = Math.min(advancePerPerson * qty, totalAmount);

        if (pricePerPersonEl) {
            pricePerPersonEl.textContent = formatAmount(pricePerPerson);
        }

        if (totalAmountEl) {
            totalAmountEl.textContent = formatAmount(totalAmount);
        }
        if (advanceTotalEl) {
            advanceTotalEl.textContent = formatAmount(advanceTotal);
        }
    }

    qtyInput.addEventListener('input', updateTotals);
    if (dateSelect) {
        dateSelect.addEventListener('change', function () {
            updateSeats();
            updateTotals();
        });
    }

    updateTotals();
    updateSeats();
})();
</script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html,body{font-family:'Marcellus',serif!important}.event-register-main{min-height:100vh;padding:1.5rem 0 5rem}.event-register-wrap{max-width:980px;margin:0 auto;padding:0 14px}.back-link{display:inline-block;color:#800000;text-decoration:none;font-weight:700;margin-bottom:10px}.card{background:#fff;border:1px solid #ecd3d3;border-radius:14px;box-shadow:0 4px 14px rgba(128,0,0,.08);padding:14px}h1{margin:0 0 8px;color:#800000;font-size:1.6rem}.small{margin:4px 0;color:#555;font-size:.9rem}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px;margin-top:10px}.form-group{display:flex;flex-direction:column;gap:6px}label{color:#800000;font-weight:700;font-size:.92rem}input,select,textarea{width:100%;box-sizing:border-box;border:1px solid #e0bebe;border-radius:8px;padding:9px 10px;font-size:.94rem;background:#fff}.package-summary{margin-top:12px;border:1px solid #f1d6d6;background:#fffaf8;border-radius:10px;padding:12px}.package-summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:8px 14px;color:#4a3f3f;font-size:.93rem}.pkg-groups{display:flex;flex-direction:column;gap:10px}.pkg-group{background:#fff;border:1px solid #ecdede;border-radius:8px;padding:10px}.pkg-group-title{margin:0 0 8px;color:#7b1f1f;font-size:.94rem;letter-spacing:.2px}.pkg-note{margin:10px 0 0;font-size:.84rem;color:#6a5a5a}.pkg-desc-block{margin:10px 0 0;color:#444;line-height:1.45}.section-title{color:#800000;margin:14px 0 6px;font-size:1.1rem}.req{color:#b00020}.notice{margin:10px 0;padding:10px 12px;border-radius:8px;font-weight:600}.notice.err{background:#ffeaea;color:#b00020}textarea{min-height:90px;resize:vertical}.submit-btn{margin-top:14px;width:100%;border:none;border-radius:8px;background:#800000;color:#fff;font-weight:700;font-size:1rem;padding:11px 12px;cursor:pointer}.submit-btn:disabled{background:#999;cursor:not-allowed}.phone-note{margin-top:2px;color:#666;font-size:.82rem;line-height:1.35}
</style>
<?php require_once 'footer.php'; ?>
