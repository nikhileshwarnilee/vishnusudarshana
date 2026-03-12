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

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/event_module.php';

vs_event_ensure_tables($pdo);

$message = '';
$error = '';

$events = $pdo->query("SELECT id, title, event_date, event_type, status FROM events ORDER BY event_date DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($events as &$eventRow) {
    $eventRow['event_date_display'] = vs_event_get_event_date_display(
        $pdo,
        (int)$eventRow['id'],
        (string)($eventRow['event_date'] ?? ''),
        (string)($eventRow['event_type'] ?? 'single_day')
    );
}
unset($eventRow);
$getEventById = static function (PDO $pdo, int $eventId): ?array {
    if ($eventId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id, title, event_date, event_type, status FROM events WHERE id = ? LIMIT 1");
    $stmt->execute([$eventId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
};
$selectedEventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : (isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0);
$selectedDateId = isset($_GET['event_date_id']) ? (int)$_GET['event_date_id'] : (isset($_POST['event_date_id']) ? (int)$_POST['event_date_id'] : 0);

$editPackageId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$editPackage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_package_id'])) {
    $deleteId = (int)$_POST['delete_package_id'];
    if ($deleteId > 0) {
        $stmt = $pdo->prepare('DELETE FROM event_packages WHERE id = ? LIMIT 1');
        $stmt->execute([$deleteId]);
        $message = $stmt->rowCount() > 0 ? 'Package deleted successfully.' : 'Package not found.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_package'])) {
    $packageId = (int)($_POST['package_id'] ?? 0);
    $selectedEventId = (int)($_POST['event_id'] ?? 0);
    $selectedDateId = (int)($_POST['event_date_id'] ?? 0);
    $packageName = trim((string)($_POST['package_name'] ?? ''));
    $displayOrderRaw = trim((string)($_POST['display_order'] ?? '0'));
    $displayOrder = ($displayOrderRaw === '') ? 0 : (int)$displayOrderRaw;
    $packageType = trim((string)($_POST['package_type'] ?? 'paid'));
    $isPaid = ($packageType === 'free') ? 0 : 1;
    $priceTotal = (float)($_POST['price_total'] ?? 0);
    $advanceAmount = (float)($_POST['advance_amount'] ?? 0);
    $paymentMode = trim((string)($_POST['payment_mode'] ?? 'full'));
    $paymentMethodsInput = (isset($_POST['payment_methods']) && is_array($_POST['payment_methods'])) ? $_POST['payment_methods'] : [];
    $paymentMethods = [];
    foreach ($paymentMethodsInput as $method) {
        $method = strtolower(trim((string)$method));
        if (!in_array($method, ['razorpay', 'upi', 'cash'], true)) {
            continue;
        }
        if (!in_array($method, $paymentMethods, true)) {
            $paymentMethods[] = $method;
        }
    }
    if ($isPaid !== 1) {
        $paymentMethods = [];
    }
    $paymentMethodsCsv = vs_event_payment_methods_to_csv($paymentMethods, $isPaid === 1);
    $cancellationAllowed = isset($_POST['cancellation_allowed']) ? 1 : 0;
    $refundAllowed = isset($_POST['refund_allowed']) ? 1 : 0;
    $allowCheckinWithoutPayment = isset($_POST['allow_checkin_without_payment']) ? 1 : 0;
    $waitlistConfirmationMode = strtolower(trim((string)($_POST['waitlist_confirmation_mode'] ?? 'auto')));
    if (!in_array($waitlistConfirmationMode, ['auto', 'manual'], true)) {
        $waitlistConfirmationMode = 'auto';
    }
    if ($cancellationAllowed !== 1) {
        $refundAllowed = 0;
    }
    $datePricingMode = trim((string)($_POST['date_pricing_mode'] ?? 'same'));
    $datePriceInput = (isset($_POST['date_price']) && is_array($_POST['date_price'])) ? $_POST['date_price'] : [];
    $seatLimitRaw = trim((string)($_POST['seat_limit'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'Active'));
    $upiId = trim((string)($_POST['upi_id'] ?? ''));
    $existingUpiQrPath = '';
    $removeUpiQr = ((string)($_POST['remove_upi_qr_image'] ?? '0') === '1');
    $upiQrPath = '';
    if ($packageId > 0) {
        $existingUpiStmt = $pdo->prepare('SELECT upi_qr_image FROM event_packages WHERE id = ? LIMIT 1');
        $existingUpiStmt->execute([$packageId]);
        $existingUpiRow = $existingUpiStmt->fetch(PDO::FETCH_ASSOC);
        if ($existingUpiRow) {
            $existingUpiQrPath = trim((string)($existingUpiRow['upi_qr_image'] ?? ''));
            $upiQrPath = $existingUpiQrPath;
        }
    }
    if ($removeUpiQr) {
        $upiQrPath = '';
    }
    if (isset($_FILES['upi_qr_image']) && (int)($_FILES['upi_qr_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadedUpiQr = vs_event_store_upload($_FILES['upi_qr_image'], 'package-upi', ['jpg', 'jpeg', 'png', 'webp']);
        if ($uploadedUpiQr === null) {
            $error = 'Invalid UPI QR upload. Allowed formats: jpg, jpeg, png, webp.';
        } else {
            $upiQrPath = $uploadedUpiQr;
        }
    }
    $selectedEventForSave = $getEventById($pdo, $selectedEventId);
    $selectedEventTypeForSave = vs_event_normalize_event_type((string)($selectedEventForSave['event_type'] ?? 'single_day'));
    $multiDatePriceMap = [];
    $upiRequired = ($isPaid === 1 && in_array('upi', $paymentMethods, true));

    if ($isPaid === 1 && $selectedEventTypeForSave === 'multi_select_dates') {
        if (!in_array($datePricingMode, ['same', 'separate'], true)) {
            $error = 'Invalid multi-date pricing mode.';
        } else {
            $dateRowsForSave = vs_event_fetch_event_dates($pdo, $selectedEventId, false);
            $validDateIds = [];
            foreach ($dateRowsForSave as $dateRow) {
                $validDateIds[] = (int)$dateRow['id'];
            }
            if (empty($validDateIds)) {
                $error = 'This event has no dates configured.';
            } elseif ($datePricingMode === 'separate') {
                foreach ($validDateIds as $dateId) {
                    $rawPrice = trim((string)($datePriceInput[$dateId] ?? ''));
                    if ($rawPrice === '') {
                        $error = 'Please enter price for each event date.';
                        break;
                    }
                    if (!is_numeric($rawPrice)) {
                        $error = 'Date-wise prices must be numeric values.';
                        break;
                    }
                    $datePrice = (float)$rawPrice;
                    if ($datePrice < 0) {
                        $error = 'Date-wise prices must be zero or more.';
                        break;
                    }
                    $multiDatePriceMap[$dateId] = $datePrice;
                }
                if ($error === '' && !empty($multiDatePriceMap)) {
                    $priceTotal = (float)reset($multiDatePriceMap);
                }
            }
        }
    } else {
        $datePricingMode = 'same';
    }

    if ($isPaid !== 1) {
        $priceTotal = 0.0;
        $advanceAmount = 0.0;
        $paymentMode = 'full';
        $paymentMethods = [];
        $paymentMethodsCsv = '';
        $multiDatePriceMap = [];
        $upiId = '';
        $upiQrPath = '';
        $upiRequired = false;
        $allowCheckinWithoutPayment = 1;
    }

    if ($selectedEventId <= 0) {
        $error = 'Please select an event.';
    } elseif (!$selectedEventForSave) {
        $error = 'Selected event not found.';
    } elseif ($packageName === '') {
        $error = 'Package name is required.';
    } elseif ($displayOrderRaw !== '' && !preg_match('/^-?\d+$/', $displayOrderRaw)) {
        $error = 'Sort order must be a whole number.';
    } elseif ($displayOrder < 0) {
        $error = 'Sort order must be zero or more.';
    } elseif (!in_array($packageType, ['paid', 'free'], true)) {
        $error = 'Invalid package type.';
    } elseif ($priceTotal < 0) {
        $error = 'Price total must be zero or more.';
    } elseif ($advanceAmount < 0) {
        $error = 'Advance amount must be zero or more.';
    } elseif ($isPaid === 1 && !in_array($paymentMode, ['full', 'advance', 'optional'], true)) {
        $error = 'Invalid payment mode.';
    } elseif ($isPaid === 1 && empty($paymentMethods)) {
        $error = 'Please select at least one payment method for paid package.';
    } elseif ($upiRequired && $upiId === '') {
        $error = 'UPI ID is required when UPI method is enabled.';
    } elseif (!in_array($status, ['Active', 'Inactive'], true)) {
        $error = 'Invalid package status.';
    } else {
        if (!$upiRequired) {
            $upiId = '';
            $upiQrPath = '';
        }
        $seatLimit = null;
        if ($seatLimitRaw !== '') {
            $seatLimit = (int)$seatLimitRaw;
            if ($seatLimit <= 0) {
                $error = 'Seat limit must be blank or greater than zero.';
            }
        }
        if ($error === '' && $seatLimit !== null && $packageId > 0) {
            $bookedSeats = vs_event_count_booked_seats_for_package($pdo, $packageId);
            if ($seatLimit < $bookedSeats) {
                $error = 'Seat limit cannot be less than current bookings (' . $bookedSeats . '). Cancel registrations first, then reduce seats.';
            }
        }
        $validationPrice = $priceTotal;
        if ($selectedEventTypeForSave === 'multi_select_dates' && $datePricingMode === 'separate' && !empty($multiDatePriceMap)) {
            $validationPrice = min(array_map('floatval', array_values($multiDatePriceMap)));
        }
        if ($isPaid === 1 && $paymentMode === 'advance' && ($advanceAmount <= 0 || $advanceAmount >= $validationPrice)) {
            $error = 'For advance mode, advance amount must be greater than zero and less than total price.';
        }
        if ($isPaid === 1 && $paymentMode === 'optional' && $advanceAmount >= $validationPrice && $validationPrice > 0) {
            $error = 'For optional mode, advance amount should be lower than total price.';
        }

        if ($error === '') {
            try {
                $pdo->beginTransaction();
                $cleanupOldUpiQr = '';
                if ($packageId > 0) {
                    $stmt = $pdo->prepare('UPDATE event_packages
                        SET event_id = ?, package_name = ?, display_order = ?, is_paid = ?, price = ?, price_total = ?, advance_amount = ?, payment_mode = ?, payment_methods = ?, upi_id = ?, upi_qr_image = ?, cancellation_allowed = ?, refund_allowed = ?, allow_checkin_without_payment = ?, waitlist_confirmation_mode = ?, seat_limit = ?, description = ?, status = ?
                        WHERE id = ?');
                    $stmt->execute([$selectedEventId, $packageName, $displayOrder, $isPaid, $priceTotal, $priceTotal, $advanceAmount, $paymentMode, $paymentMethodsCsv, ($upiId !== '' ? $upiId : null), ($upiQrPath !== '' ? $upiQrPath : null), $cancellationAllowed, $refundAllowed, $allowCheckinWithoutPayment, $waitlistConfirmationMode, $seatLimit, $description, $status, $packageId]);
                    $savedPackageId = $packageId;
                    if ($existingUpiQrPath !== '' && $existingUpiQrPath !== $upiQrPath) {
                        $cleanupOldUpiQr = $existingUpiQrPath;
                    }
                    $message = 'Package updated successfully.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO event_packages
                        (event_id, package_name, display_order, is_paid, price, price_total, advance_amount, payment_mode, payment_methods, upi_id, upi_qr_image, cancellation_allowed, refund_allowed, allow_checkin_without_payment, waitlist_confirmation_mode, seat_limit, description, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$selectedEventId, $packageName, $displayOrder, $isPaid, $priceTotal, $priceTotal, $advanceAmount, $paymentMode, $paymentMethodsCsv, ($upiId !== '' ? $upiId : null), ($upiQrPath !== '' ? $upiQrPath : null), $cancellationAllowed, $refundAllowed, $allowCheckinWithoutPayment, $waitlistConfirmationMode, $seatLimit, $description, $status]);
                    $savedPackageId = (int)$pdo->lastInsertId();
                    $message = 'Package added successfully.';
                }

                if ($selectedEventTypeForSave === 'multi_select_dates' && $savedPackageId > 0) {
                    if ($datePricingMode === 'separate') {
                        vs_event_replace_package_date_prices($pdo, $savedPackageId, $multiDatePriceMap);
                    } else {
                        vs_event_replace_package_date_prices($pdo, $savedPackageId, []);
                    }
                } elseif ($savedPackageId > 0) {
                    // Keep date-wise pricing table clean for non-multi events.
                    vs_event_replace_package_date_prices($pdo, $savedPackageId, []);
                }

                $pdo->commit();

                if ($cleanupOldUpiQr !== '') {
                    $cleanupPath = __DIR__ . '/../../' . ltrim($cleanupOldUpiQr, '/');
                    if (is_file($cleanupPath)) {
                        @unlink($cleanupPath);
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Unable to save package pricing.';
                $message = '';
                error_log('Event package save failed: ' . $e->getMessage());
            }
        }
    }
}

if ($editPackageId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM event_packages WHERE id = ? LIMIT 1');
    $stmt->execute([$editPackageId]);
    $editPackage = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($editPackage) {
        $selectedEventId = (int)$editPackage['event_id'];
    }
}

$editPackageBookedSeats = 0;
if ($editPackage && (int)($editPackage['id'] ?? 0) > 0) {
    $editPackageBookedSeats = vs_event_count_booked_seats_for_package($pdo, (int)$editPackage['id']);
}

$selectedEvent = $selectedEventId > 0 ? $getEventById($pdo, $selectedEventId) : null;
if (!$selectedEvent && $selectedEventId > 0) {
    $selectedEventId = 0;
    $selectedDateId = 0;
}

$eventDates = [];
if ($selectedEventId > 0) {
    $eventDates = vs_event_fetch_event_dates($pdo, $selectedEventId, false);
}

$selectedEventType = 'single_day';
$selectedEventTypeText = 'Single Day';
$showDateDropdown = false;
$dashboardScopeLabel = 'Seat Dashboard Date';
$dashboardScopeText = 'All Dates (Combined)';
if ($selectedEvent) {
    $selectedEventType = (string)($selectedEvent['event_type'] ?? 'single_day');
    if (!in_array($selectedEventType, ['single_day', 'multi_select_dates', 'date_range'], true)) {
        $selectedEventType = 'single_day';
    }
    $selectedEventTypeText = ucwords(str_replace('_', ' ', $selectedEventType));

    if ($selectedEventType === 'multi_select_dates') {
        $showDateDropdown = true;
        $dashboardScopeLabel = 'Seat Dashboard Date';
        $dashboardScopeText = 'All Dates (Combined)';
        $dateMap = [];
        foreach ($eventDates as $d) {
            $dateMap[(int)$d['id']] = (string)$d['event_date'];
        }
        if ($selectedDateId > 0 && !isset($dateMap[$selectedDateId])) {
            $selectedDateId = 0;
        }
        if ($selectedDateId > 0 && isset($dateMap[$selectedDateId])) {
            $dashboardScopeText = $dateMap[$selectedDateId];
        }
    } elseif ($selectedEventType === 'single_day') {
        $showDateDropdown = false;
        $dashboardScopeLabel = 'Seat Dashboard Date';
        if (!empty($eventDates)) {
            $selectedDateId = (int)$eventDates[0]['id'];
            $dashboardScopeText = (string)$eventDates[0]['event_date'];
        } else {
            $selectedDateId = 0;
            $dashboardScopeText = (string)$selectedEvent['event_date'];
        }
    } else {
        $showDateDropdown = false;
        $selectedDateId = 0;
        $dashboardScopeLabel = 'Seat Dashboard Range';
        if (!empty($eventDates)) {
            $rangeStart = (string)$eventDates[0]['event_date'];
            $rangeEnd = (string)$eventDates[count($eventDates) - 1]['event_date'];
            $dashboardScopeText = ($rangeStart === $rangeEnd) ? $rangeStart : ($rangeStart . ' to ' . $rangeEnd);
        } else {
            $dashboardScopeText = (string)$selectedEvent['event_date'];
        }
    }
}

$editDatePriceMap = [];
if ($editPackage && (int)($editPackage['id'] ?? 0) > 0) {
    $editDatePriceMap = vs_event_fetch_package_date_price_map($pdo, (int)$editPackage['id']);
}
$formDatePricingMode = 'same';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_package'])) {
    $postedPricingMode = trim((string)($_POST['date_pricing_mode'] ?? 'same'));
    $formDatePricingMode = in_array($postedPricingMode, ['same', 'separate'], true) ? $postedPricingMode : 'same';
} elseif (!empty($editDatePriceMap)) {
    $formDatePricingMode = 'separate';
}
$postedDatePriceMap = (isset($_POST['date_price']) && is_array($_POST['date_price'])) ? $_POST['date_price'] : [];
$formPackageType = 'paid';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_package'])) {
    $postedType = trim((string)($_POST['package_type'] ?? 'paid'));
    if (in_array($postedType, ['paid', 'free'], true)) {
        $formPackageType = $postedType;
    }
} elseif ($editPackage && !vs_event_is_package_paid($editPackage)) {
    $formPackageType = 'free';
}
$formIsPaid = ($formPackageType === 'paid');
$formPaymentMethods = ['razorpay', 'upi'];
if (!$formIsPaid) {
    $formPaymentMethods = [];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_package'])) {
    $postedMethods = (isset($_POST['payment_methods']) && is_array($_POST['payment_methods'])) ? $_POST['payment_methods'] : [];
    $formPaymentMethods = [];
    foreach ($postedMethods as $method) {
        $method = strtolower(trim((string)$method));
        if (!in_array($method, ['razorpay', 'upi', 'cash'], true)) {
            continue;
        }
        if (!in_array($method, $formPaymentMethods, true)) {
            $formPaymentMethods[] = $method;
        }
    }
} elseif ($editPackage) {
    $formPaymentMethods = vs_event_payment_methods_from_csv((string)($editPackage['payment_methods'] ?? ''), true);
}
$formUpiId = '';
$formUpiQrPath = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_package'])) {
    $formUpiId = trim((string)($_POST['upi_id'] ?? ''));
    $formUpiQrPath = trim((string)($upiQrPath ?? ''));
} elseif ($editPackage) {
    $formUpiId = trim((string)($editPackage['upi_id'] ?? ''));
    $formUpiQrPath = trim((string)($editPackage['upi_qr_image'] ?? ''));
}
$formCancellationAllowed = true;
$formRefundAllowed = true;
$formAllowCheckinWithoutPayment = false;
$formWaitlistConfirmationMode = 'auto';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_package'])) {
    $formCancellationAllowed = isset($_POST['cancellation_allowed']);
    $formRefundAllowed = isset($_POST['refund_allowed']);
    $formAllowCheckinWithoutPayment = isset($_POST['allow_checkin_without_payment']);
    $postedWaitlistMode = strtolower(trim((string)($_POST['waitlist_confirmation_mode'] ?? 'auto')));
    if (in_array($postedWaitlistMode, ['auto', 'manual'], true)) {
        $formWaitlistConfirmationMode = $postedWaitlistMode;
    }
    if (!$formCancellationAllowed) {
        $formRefundAllowed = false;
    }
} elseif ($editPackage) {
    $formCancellationAllowed = ((int)($editPackage['cancellation_allowed'] ?? 1) === 1);
    $formRefundAllowed = ((int)($editPackage['refund_allowed'] ?? 1) === 1);
    $formAllowCheckinWithoutPayment = ((int)($editPackage['allow_checkin_without_payment'] ?? 0) === 1);
    $savedWaitlistMode = strtolower(trim((string)($editPackage['waitlist_confirmation_mode'] ?? 'auto')));
    if (in_array($savedWaitlistMode, ['auto', 'manual'], true)) {
        $formWaitlistConfirmationMode = $savedWaitlistMode;
    }
}

if (!$formIsPaid) {
    $formAllowCheckinWithoutPayment = true;
}

$packages = [];
if ($selectedEventId > 0) {
    $packages = vs_event_fetch_packages_with_seats($pdo, $selectedEventId, false, $selectedDateId);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Packages</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f7f7fa;margin:0}
        .admin-container{max-width:1340px;margin:0 auto;padding:24px 12px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 12px #e0bebe22;padding:16px;margin-bottom:16px}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
        .form-group{display:flex;flex-direction:column;gap:6px}
        label{color:#800000;font-weight:700;font-size:.9em}
        input,select,textarea{width:100%;box-sizing:border-box;padding:9px 10px;border:1px solid #e0bebe;border-radius:8px;font-size:.94em}
        textarea{min-height:88px;resize:vertical}
        .btn-main{display:inline-block;border:none;border-radius:8px;padding:9px 12px;font-weight:700;cursor:pointer;text-decoration:none;background:#800000;color:#fff}
        .btn-alt{background:#6c757d}
        .notice{margin:10px 0;padding:10px 12px;border-radius:8px;font-weight:600}
        .ok{background:#e7f7ed;color:#1a8917}.err{background:#ffeaea;color:#b00020}
        .status-badge{display:inline-block;padding:4px 10px;border-radius:14px;font-size:.82em;font-weight:700}
        .status-active{background:#e5ffe5;color:#1a8917}.status-inactive{background:#ffeaea;color:#b00020}
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1 style="color:#800000;">Event Packages</h1>
    <?php if ($message !== ''): ?><div class="notice ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card">
        <form method="get" class="grid" style="align-items:end;">
            <div class="form-group">
                <label>Select Event</label>
                <select name="event_id" required>
                    <option value="">-- Select Event --</option>
                    <?php foreach ($events as $event): ?>
                        <?php $eventTypeText = ucwords(str_replace('_', ' ', (string)($event['event_type'] ?? 'single_day'))); ?>
                        <option value="<?php echo (int)$event['id']; ?>" <?php echo ($selectedEventId === (int)$event['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$event['title'] . ' (' . (string)($event['event_date_display'] ?? $event['event_date']) . ') - ' . $eventTypeText); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($selectedEventId > 0 && $showDateDropdown): ?>
                <div class="form-group">
                    <label>Seat Dashboard Date</label>
                    <select name="event_date_id">
                        <option value="0">All Dates (Combined)</option>
                        <?php foreach ($eventDates as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>" <?php echo ($selectedDateId === (int)$d['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string)$d['event_date'] . ((int)($d['seat_limit'] ?? 0) > 0 ? (' | Seats: ' . (int)$d['seat_limit']) : ' | Seats: Unlimited')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php elseif ($selectedEventId > 0): ?>
                <div class="form-group">
                    <label><?php echo htmlspecialchars($dashboardScopeLabel); ?></label>
                    <input type="text" value="<?php echo htmlspecialchars($dashboardScopeText); ?>" readonly>
                    <input type="hidden" name="event_date_id" value="<?php echo (int)$selectedDateId; ?>">
                </div>
            <?php endif; ?>
            <div class="form-group"><button type="submit" class="btn-main">Load Packages</button></div>
        </form>
    </div>

    <?php if ($selectedEventId > 0): ?>
    <div class="card">
        <h3 style="margin-top:0;color:#800000;"><?php echo $editPackage ? 'Edit Package' : 'Add Package'; ?></h3>
        <form method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="save_package" value="1">
            <input type="hidden" name="package_id" value="<?php echo (int)($editPackage['id'] ?? 0); ?>">
            <input type="hidden" name="event_id" value="<?php echo (int)$selectedEventId; ?>">
            <input type="hidden" name="event_date_id" value="<?php echo (int)$selectedDateId; ?>">

            <div class="grid">
                <div class="form-group"><label>Package Name</label><input type="text" name="package_name" value="<?php echo htmlspecialchars((string)($editPackage['package_name'] ?? '')); ?>" required></div>
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="display_order" min="0" step="1" value="<?php echo htmlspecialchars((string)($_POST['display_order'] ?? $editPackage['display_order'] ?? '0')); ?>">
                    <span style="font-size:12px;color:#666;">Lower number shows first on website.</span>
                </div>
                <div class="form-group">
                    <label>Package Type</label>
                    <select name="package_type" id="package_type">
                        <option value="paid" <?php echo $formIsPaid ? 'selected' : ''; ?>>Paid</option>
                        <option value="free" <?php echo !$formIsPaid ? 'selected' : ''; ?>>Free</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Seat Limit (optional)</label>
                    <input type="number" name="seat_limit" min="<?php echo (int)max(1, $editPackageBookedSeats); ?>" value="<?php echo htmlspecialchars((string)($_POST['seat_limit'] ?? $editPackage['seat_limit'] ?? '')); ?>">
                    <?php if ($editPackageBookedSeats > 0): ?><span style="font-size:12px;color:#666;">Current bookings: <?php echo (int)$editPackageBookedSeats; ?></span><?php endif; ?>
                </div>
                <div class="form-group"><label>Status</label><select name="status"><option value="Active" <?php echo ((string)($editPackage['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option><option value="Inactive" <?php echo ((string)($editPackage['status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option></select></div>
            </div>

            <div id="paid_fields_wrap">
                <div class="grid">
                    <?php if ($selectedEventType === 'multi_select_dates'): ?>
                        <div class="form-group">
                            <label>Multi-Date Pricing</label>
                            <select name="date_pricing_mode" id="date_pricing_mode">
                                <option value="same" <?php echo ($formDatePricingMode === 'same') ? 'selected' : ''; ?>>Same Price For All Dates</option>
                                <option value="separate" <?php echo ($formDatePricingMode === 'separate') ? 'selected' : ''; ?>>Separate Price Per Date</option>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="form-group" id="same_price_wrap">
                        <label>Total Price</label>
                        <input type="number" id="price_total_input" name="price_total" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($_POST['price_total'] ?? $editPackage['price_total'] ?? $editPackage['price'] ?? '0')); ?>">
                    </div>
                    <div class="form-group"><label>Advance Amount</label><input type="number" id="advance_amount_input" name="advance_amount" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($_POST['advance_amount'] ?? $editPackage['advance_amount'] ?? '0')); ?>"></div>
                    <div class="form-group"><label>Payment Mode</label><select name="payment_mode" id="payment_mode_select"><option value="full" <?php echo ((string)($_POST['payment_mode'] ?? $editPackage['payment_mode'] ?? 'full') === 'full') ? 'selected' : ''; ?>>Full</option><option value="advance" <?php echo ((string)($_POST['payment_mode'] ?? $editPackage['payment_mode'] ?? '') === 'advance') ? 'selected' : ''; ?>>Advance</option><option value="optional" <?php echo ((string)($_POST['payment_mode'] ?? $editPackage['payment_mode'] ?? '') === 'optional') ? 'selected' : ''; ?>>Optional</option></select></div>
                </div>

                <?php if ($selectedEventType === 'multi_select_dates'): ?>
                    <div id="separate_price_wrap" style="margin-top:10px;">
                        <label style="display:block;margin-bottom:6px;">Date-wise Price</label>
                        <div class="grid">
                            <?php foreach ($eventDates as $dateRow): ?>
                                <?php
                                $dateId = (int)$dateRow['id'];
                                $rawPostedPrice = '';
                                if (isset($postedDatePriceMap[$dateId])) {
                                    $rawPostedPrice = (string)$postedDatePriceMap[$dateId];
                                } elseif (isset($postedDatePriceMap[(string)$dateId])) {
                                    $rawPostedPrice = (string)$postedDatePriceMap[(string)$dateId];
                                }
                                $dateInputValue = $rawPostedPrice !== ''
                                    ? $rawPostedPrice
                                    : (isset($editDatePriceMap[$dateId]) ? (string)$editDatePriceMap[$dateId] : (string)($editPackage['price_total'] ?? $editPackage['price'] ?? '0'));
                                ?>
                                <div class="form-group">
                                    <label><?php echo htmlspecialchars((string)$dateRow['event_date']); ?></label>
                                    <input type="number" class="date-price-input" data-date-id="<?php echo $dateId; ?>" name="date_price[<?php echo $dateId; ?>]" min="0" step="0.01" value="<?php echo htmlspecialchars($dateInputValue); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group" id="payment_methods_wrap" style="margin-top:10px;">
                    <?php
                    $hasRazorpay = in_array('razorpay', $formPaymentMethods, true);
                    $hasUpi = in_array('upi', $formPaymentMethods, true);
                    $hasCash = in_array('cash', $formPaymentMethods, true);
                    $hasAllMethods = ($hasRazorpay && $hasUpi && $hasCash);
                    ?>
                    <label>Payment Methods</label>
                    <div style="display:flex;gap:16px;flex-wrap:wrap;">
                        <label style="font-weight:600;color:#333;"><input type="checkbox" id="payment_method_all"> All</label>
                        <label style="font-weight:600;color:#333;"><input type="checkbox" class="payment-method-item" name="payment_methods[]" value="razorpay" <?php echo $hasRazorpay ? 'checked' : ''; ?>> Razorpay</label>
                        <label style="font-weight:600;color:#333;"><input type="checkbox" class="payment-method-item" name="payment_methods[]" value="upi" <?php echo $hasUpi ? 'checked' : ''; ?>> UPI</label>
                        <label style="font-weight:600;color:#333;"><input type="checkbox" class="payment-method-item" name="payment_methods[]" value="cash" <?php echo $hasCash ? 'checked' : ''; ?>> Cash</label>
                    </div>
                    <input type="hidden" id="payment_method_all_state" value="<?php echo $hasAllMethods ? '1' : '0'; ?>">
                </div>

                <div id="upi_config_wrap" style="margin-top:10px;display:none;">
                    <div class="grid">
                        <div class="form-group">
                            <label>UPI ID</label>
                            <input type="text" id="upi_id_input" name="upi_id" value="<?php echo htmlspecialchars($formUpiId); ?>" placeholder="example@upi">
                        </div>
                        <div class="form-group">
                            <label>UPI QR Image</label>
                            <?php $removeUpiQrValue = (isset($_POST['remove_upi_qr_image']) && (string)$_POST['remove_upi_qr_image'] === '1') ? '1' : '0'; ?>
                            <input type="hidden" id="remove_upi_qr_image" name="remove_upi_qr_image" value="<?php echo $removeUpiQrValue; ?>">
                            <?php if ($formUpiQrPath !== ''): ?>
                                <div id="upi_qr_preview_wrap" style="position:relative;display:inline-block;margin-bottom:8px;">
                                    <a href="../../<?php echo htmlspecialchars(ltrim($formUpiQrPath, '/')); ?>" target="_blank">
                                        <img src="../../<?php echo htmlspecialchars(ltrim($formUpiQrPath, '/')); ?>" alt="UPI QR" style="width:90px;height:90px;object-fit:cover;border:1px solid #e0bebe;border-radius:8px;">
                                    </a>
                                    <button
                                        type="button"
                                        id="remove_upi_qr_btn"
                                        title="Delete QR image"
                                        aria-label="Delete QR image"
                                        style="position:absolute;top:-7px;right:-7px;width:22px;height:22px;border:none;border-radius:50%;background:#dc3545;color:#fff;font-size:16px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;"
                                    >&times;</button>
                                    <div style="font-size:12px;color:#666;margin-top:3px;">Current QR image</div>
                                </div>
                                <div id="upi_qr_remove_note" style="display:none;font-size:12px;color:#b00020;margin:0 0 8px;">
                                    QR image marked for deletion.
                                    <button type="button" id="undo_remove_upi_qr_btn" style="border:none;background:none;color:#0b7285;font-weight:700;cursor:pointer;padding:0;margin-left:4px;text-decoration:underline;">Undo</button>
                                </div>
                            <?php endif; ?>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;"><input type="file" id="upi_qr_input" name="upi_qr_image" accept=".jpg,.jpeg,.png,.webp"></div>
                            <span style="font-size:12px;color:#666;">Optional. You can keep only UPI ID without QR image.</span>
                        </div>
                    </div>
                    <input type="hidden" id="upi_qr_existing_state" value="<?php echo ($formUpiQrPath !== '') ? '1' : '0'; ?>">
                </div>

                <div class="form-group" id="cancellation_options_wrap" style="margin-top:10px;">
                    <label>Cancellation / Refund Policy</label>
                    <div style="display:flex;gap:16px;flex-wrap:wrap;">
                        <label style="font-weight:600;color:#333;">
                            <input type="checkbox" id="cancellation_allowed" name="cancellation_allowed" value="1" <?php echo $formCancellationAllowed ? 'checked' : ''; ?>>
                            Cancellation Allowed
                        </label>
                        <label style="font-weight:600;color:#333;">
                            <input type="checkbox" id="refund_allowed" name="refund_allowed" value="1" <?php echo $formRefundAllowed ? 'checked' : ''; ?>>
                            Refund Allowed
                        </label>
                        <label style="font-weight:600;color:#333;" id="allow_checkin_without_payment_wrap">
                            <input type="checkbox" id="allow_checkin_without_payment" name="allow_checkin_without_payment" value="1" <?php echo $formAllowCheckinWithoutPayment ? 'checked' : ''; ?>>
                            Allow Check-In Without Payment
                        </label>
                    </div>
                    <span style="font-size:12px;color:#666;">If cancellation is disabled, refund is automatically disabled. Check-in policy applies to paid packages.</span>
                </div>
            </div>
            <div class="form-group" style="margin-top:10px;">
                <label>Waitlist Confirmation Mode</label>
                <select name="waitlist_confirmation_mode">
                    <option value="auto" <?php echo ($formWaitlistConfirmationMode === 'auto') ? 'selected' : ''; ?>>Auto Confirm (FIFO)</option>
                    <option value="manual" <?php echo ($formWaitlistConfirmationMode === 'manual') ? 'selected' : ''; ?>>Manual Confirm (Admin Picks)</option>
                </select>
                <span style="font-size:12px;color:#666;">Auto mode confirms first waitlisted booking when seats free up. Manual mode requires admin confirmation.</span>
            </div>
            <div class="form-group" style="margin-top:10px;"><label>Package Description</label><textarea name="description"><?php echo htmlspecialchars((string)($editPackage['description'] ?? '')); ?></textarea></div>
            <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                <button type="submit" class="btn-main"><?php echo $editPackage ? 'Update Package' : 'Add Package'; ?></button>
                <?php if ($editPackage): ?><a class="btn-main btn-alt" href="event-packages.php?event_id=<?php echo (int)$selectedEventId; ?>&event_date_id=<?php echo (int)$selectedDateId; ?>">Cancel Edit</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h3 style="margin-top:0;color:#800000;">Package Seat Dashboard</h3>
        <p style="margin:0 0 8px;color:#5f5f5f;">Use <strong>Sort</strong> column to control website display. Lower number appears first.</p>
        <p style="margin:0 0 10px;color:#5f5f5f;"><strong>Event Type:</strong> <?php echo htmlspecialchars($selectedEventTypeText); ?> | <strong><?php echo htmlspecialchars($dashboardScopeLabel); ?>:</strong> <?php echo htmlspecialchars($dashboardScopeText); ?></p>
        <div style="overflow:auto;">
            <table class="list-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Sort</th><th>Package</th><th>Type</th><th>Total Price</th><th>Advance</th><th>Mode</th><th>Methods</th><th>Cancellation</th><th>Refund</th><th>Check-In (Unpaid)</th><th>Waitlist Confirm</th><th>Total Seats</th><th>Booked Seats</th><th>Remaining Seats</th><th>Revenue Generated</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($packages)): ?>
                    <tr><td colspan="18" style="text-align:center;padding:18px;color:#666;">No packages found for this event.</td></tr>
                <?php else: ?>
                    <?php foreach ($packages as $pkg): ?>
                        <tr>
                            <td><?php echo (int)$pkg['id']; ?></td>
                            <td>
                                <input
                                    type="number"
                                    class="pkg-order-input"
                                    data-id="<?php echo (int)$pkg['id']; ?>"
                                    data-event-id="<?php echo (int)$selectedEventId; ?>"
                                    min="0"
                                    step="1"
                                    value="<?php echo (int)($pkg['display_order'] ?? 0); ?>"
                                    style="width:82px;padding:4px 6px;border:1px solid #ddd;border-radius:4px;"
                                >
                            </td>
                            <td><strong><?php echo htmlspecialchars((string)$pkg['package_name']); ?></strong></td>
                            <?php
                            $pkgPaid = vs_event_is_package_paid($pkg);
                            $pkgMethods = vs_event_payment_methods_from_csv((string)($pkg['payment_methods'] ?? ''), $pkgPaid);
                            ?>
                            <td><?php echo $pkgPaid ? 'Paid' : 'Free'; ?></td>
                            <td><?php echo $pkgPaid ? ('Rs ' . number_format((float)($pkg['price_total'] ?? $pkg['price']), 2)) : 'Free'; ?></td>
                            <td><?php echo $pkgPaid ? ('Rs ' . number_format((float)($pkg['advance_amount'] ?? 0), 2)) : '-'; ?></td>
                            <td><?php echo $pkgPaid ? htmlspecialchars(ucfirst((string)($pkg['payment_mode'] ?? 'full'))) : '-'; ?></td>
                            <td>
                                <?php echo $pkgPaid ? htmlspecialchars(implode(', ', array_map('ucfirst', $pkgMethods))) : '-'; ?>
                                <?php if ($pkgPaid && in_array('upi', $pkgMethods, true)): ?>
                                    <br><span class="small">UPI: <?php echo htmlspecialchars((string)($pkg['upi_id'] ?? '')); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo ((int)($pkg['cancellation_allowed'] ?? 1) === 1) ? 'Allowed' : 'Not Allowed'; ?></td>
                            <td><?php echo ((int)($pkg['refund_allowed'] ?? 1) === 1) ? 'Allowed' : 'Not Allowed'; ?></td>
                            <td><?php echo $pkgPaid ? (((int)($pkg['allow_checkin_without_payment'] ?? 0) === 1) ? 'Allowed' : 'Not Allowed') : 'Allowed'; ?></td>
                            <td><?php echo htmlspecialchars(ucfirst((string)($pkg['waitlist_confirmation_mode'] ?? 'auto'))); ?></td>
                            <td><?php echo ($pkg['total_seats'] === null) ? 'Unlimited' : (int)$pkg['total_seats']; ?></td>
                            <td><?php echo (int)$pkg['seats_booked']; ?></td>
                            <td><?php echo ($pkg['seats_left'] === null) ? 'Unlimited' : (int)$pkg['seats_left']; ?></td>
                            <td>Rs <?php echo number_format((float)($pkg['revenue_generated'] ?? 0), 2); ?></td>
                            <td><span class="status-badge <?php echo ((string)$pkg['status'] === 'Active') ? 'status-active' : 'status-inactive'; ?>"><?php echo htmlspecialchars((string)$pkg['status']); ?></span></td>
                            <td>
                                <a class="btn-main" href="event-packages.php?event_id=<?php echo (int)$selectedEventId; ?>&event_date_id=<?php echo (int)$selectedDateId; ?>&edit_id=<?php echo (int)$pkg['id']; ?>">Edit</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this package?');">
                                    <input type="hidden" name="event_id" value="<?php echo (int)$selectedEventId; ?>">
                                    <input type="hidden" name="event_date_id" value="<?php echo (int)$selectedDateId; ?>">
                                    <input type="hidden" name="delete_package_id" value="<?php echo (int)$pkg['id']; ?>">
                                    <button type="submit" class="btn-main" style="background:#dc3545;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
        <div class="card">Select an event to manage packages and see seat metrics.</div>
    <?php endif; ?>
</div>
<script>
(function () {
    const formEl = document.querySelector('form[method="post"][autocomplete="off"]');
    const packageTypeEl = document.getElementById('package_type');
    const paidFieldsWrap = document.getElementById('paid_fields_wrap');
    const pricingModeEl = document.getElementById('date_pricing_mode');
    const sameWrap = document.getElementById('same_price_wrap');
    const separateWrap = document.getElementById('separate_price_wrap');
    const totalPriceInput = document.getElementById('price_total_input');
    const advanceInput = document.getElementById('advance_amount_input');
    const paymentModeSelect = document.getElementById('payment_mode_select');
    const dateInputs = separateWrap ? Array.from(separateWrap.querySelectorAll('.date-price-input')) : [];
    const allMethodsEl = document.getElementById('payment_method_all');
    const allMethodsInitial = document.getElementById('payment_method_all_state');
    const methodItems = Array.from(document.querySelectorAll('.payment-method-item'));
    const upiConfigWrap = document.getElementById('upi_config_wrap');
    const upiIdInput = document.getElementById('upi_id_input');
    const upiQrInput = document.getElementById('upi_qr_input');
    const removeUpiQrInput = document.getElementById('remove_upi_qr_image');
    const removeUpiQrBtn = document.getElementById('remove_upi_qr_btn');
    const undoRemoveUpiQrBtn = document.getElementById('undo_remove_upi_qr_btn');
    const upiQrPreviewWrap = document.getElementById('upi_qr_preview_wrap');
    const upiQrRemoveNote = document.getElementById('upi_qr_remove_note');
    const upiQrExistingState = document.getElementById('upi_qr_existing_state');
    const cancellationAllowedEl = document.getElementById('cancellation_allowed');
    const refundAllowedEl = document.getElementById('refund_allowed');
    const allowCheckinWithoutPaymentEl = document.getElementById('allow_checkin_without_payment');
    const allowCheckinWithoutPaymentWrap = document.getElementById('allow_checkin_without_payment_wrap');

    if (!formEl || !packageTypeEl) {
        return;
    }

    function syncDateInputsFromTotal() {
        if (!totalPriceInput) {
            return;
        }
        const totalValue = totalPriceInput.value;
        dateInputs.forEach(function (inputEl) {
            if (inputEl && inputEl.value === '') {
                inputEl.value = totalValue;
            }
        });
    }

    function applyPricingMode() {
        const isPaid = packageTypeEl.value === 'paid';
        if (!pricingModeEl) {
            if (totalPriceInput) {
                totalPriceInput.required = isPaid;
            }
            return;
        }
        const isSeparate = (pricingModeEl.value === 'separate');
        if (sameWrap) {
            sameWrap.style.display = isSeparate ? 'none' : '';
        }
        if (separateWrap) {
            separateWrap.style.display = isSeparate ? '' : 'none';
        }
        if (totalPriceInput) {
            totalPriceInput.required = (isPaid && !isSeparate);
        }
        dateInputs.forEach(function (inputEl) {
            inputEl.required = (isPaid && isSeparate);
        });
        if (isPaid && !isSeparate) {
            syncDateInputsFromTotal();
        }
    }

    function syncAllMethodsCheckbox() {
        if (!allMethodsEl || methodItems.length === 0) {
            return;
        }
        const allChecked = methodItems.every(function (el) { return el.checked; });
        allMethodsEl.checked = allChecked;
    }

    function isUpiEnabled() {
        return methodItems.some(function (el) {
            return el.value === 'upi' && el.checked;
        });
    }

    function syncUpiConfigVisibility() {
        const isPaid = packageTypeEl.value === 'paid';
        const enableUpiConfig = isPaid && isUpiEnabled();
        if (upiConfigWrap) {
            upiConfigWrap.style.display = enableUpiConfig ? '' : 'none';
        }
        if (upiIdInput) {
            upiIdInput.required = enableUpiConfig;
        }
        if (upiQrInput) {
            upiQrInput.required = false;
        }
    }

    function syncUpiQrRemovalState() {
        const removeEnabled = !!(removeUpiQrInput && removeUpiQrInput.value === '1');
        if (upiQrPreviewWrap) {
            upiQrPreviewWrap.style.display = removeEnabled ? 'none' : 'inline-block';
        }
        if (upiQrRemoveNote) {
            upiQrRemoveNote.style.display = removeEnabled ? 'block' : 'none';
        }
        if (upiQrExistingState) {
            const defaultValue = upiQrExistingState.dataset.defaultValue || upiQrExistingState.value || '0';
            upiQrExistingState.value = removeEnabled ? '0' : defaultValue;
        }
    }

    function syncCancellationRefund() {
        if (!cancellationAllowedEl || !refundAllowedEl) {
            return;
        }
        const cancellationEnabled = cancellationAllowedEl.checked;
        const isPaid = packageTypeEl.value === 'paid';

        if (!cancellationEnabled) {
            refundAllowedEl.checked = false;
            refundAllowedEl.disabled = true;
            return;
        }

        if (!isPaid) {
            refundAllowedEl.checked = false;
            refundAllowedEl.disabled = true;
            return;
        }

        refundAllowedEl.disabled = false;
    }

    function syncUnpaidCheckinPolicy() {
        if (!allowCheckinWithoutPaymentEl) {
            return;
        }
        const isPaid = packageTypeEl.value === 'paid';
        if (allowCheckinWithoutPaymentWrap) {
            allowCheckinWithoutPaymentWrap.style.opacity = isPaid ? '1' : '0.7';
        }
        if (!isPaid) {
            allowCheckinWithoutPaymentEl.checked = true;
            allowCheckinWithoutPaymentEl.disabled = true;
            return;
        }
        allowCheckinWithoutPaymentEl.disabled = false;
    }

    function togglePaidFields() {
        const isPaid = packageTypeEl.value === 'paid';
        if (paidFieldsWrap) {
            paidFieldsWrap.style.display = isPaid ? '' : 'none';
        }
        if (totalPriceInput) {
            totalPriceInput.required = isPaid;
        }
        if (advanceInput) {
            advanceInput.required = false;
        }
        if (paymentModeSelect && !isPaid) {
            paymentModeSelect.value = 'full';
        }
        if (!isPaid) {
            if (totalPriceInput) {
                totalPriceInput.value = '0';
            }
            if (advanceInput) {
                advanceInput.value = '0';
            }
            methodItems.forEach(function (el) { el.checked = false; });
            if (allMethodsEl) {
                allMethodsEl.checked = false;
            }
            if (refundAllowedEl) {
                refundAllowedEl.checked = false;
            }
            if (upiIdInput) {
                upiIdInput.required = false;
            }
            if (upiQrInput) {
                upiQrInput.required = false;
            }
            if (removeUpiQrInput) {
                removeUpiQrInput.value = '0';
            }
        } else if (allMethodsInitial && allMethodsInitial.value === '1' && methodItems.length > 0) {
            methodItems.forEach(function (el) { el.checked = true; });
        }
        applyPricingMode();
        syncAllMethodsCheckbox();
        syncCancellationRefund();
        syncUnpaidCheckinPolicy();
        syncUpiConfigVisibility();
        syncUpiQrRemovalState();
    }

    if (pricingModeEl) {
        pricingModeEl.addEventListener('change', applyPricingMode);
    }
    if (totalPriceInput) {
        totalPriceInput.addEventListener('input', function () {
            if (!pricingModeEl || pricingModeEl.value !== 'separate') {
                syncDateInputsFromTotal();
            }
        });
    }
    if (allMethodsEl) {
        allMethodsEl.addEventListener('change', function () {
            methodItems.forEach(function (el) {
                el.checked = allMethodsEl.checked;
            });
            syncUpiConfigVisibility();
        });
    }
    methodItems.forEach(function (el) {
        el.addEventListener('change', function () {
            syncAllMethodsCheckbox();
            syncUpiConfigVisibility();
        });
    });
    if (cancellationAllowedEl) {
        cancellationAllowedEl.addEventListener('change', syncCancellationRefund);
    }
    if (upiQrExistingState && !upiQrExistingState.dataset.defaultValue) {
        upiQrExistingState.dataset.defaultValue = upiQrExistingState.value;
    }
    if (removeUpiQrBtn && removeUpiQrInput) {
        removeUpiQrBtn.addEventListener('click', function () {
            removeUpiQrInput.value = '1';
            if (upiQrInput) {
                upiQrInput.value = '';
            }
            syncUpiQrRemovalState();
        });
    }
    if (undoRemoveUpiQrBtn && removeUpiQrInput) {
        undoRemoveUpiQrBtn.addEventListener('click', function () {
            removeUpiQrInput.value = '0';
            syncUpiQrRemovalState();
        });
    }
    if (upiQrInput) {
        upiQrInput.addEventListener('change', function () {
            if (upiQrInput.files && upiQrInput.files.length > 0 && removeUpiQrInput) {
                removeUpiQrInput.value = '0';
            }
            syncUpiQrRemovalState();
        });
    }
    packageTypeEl.addEventListener('change', togglePaidFields);
    formEl.addEventListener('submit', function (e) {
        if (packageTypeEl.value !== 'paid') {
            return;
        }
        const anyMethod = methodItems.some(function (el) { return el.checked; });
        if (!anyMethod) {
            e.preventDefault();
            alert('Please select at least one payment method for paid package.');
            return;
        }
        if (isUpiEnabled()) {
            const upiId = upiIdInput ? upiIdInput.value.trim() : '';
            if (upiId === '') {
                e.preventDefault();
                alert('UPI ID is required when UPI method is enabled.');
            }
        }
    });

    togglePaidFields();

    const orderInputs = Array.from(document.querySelectorAll('.pkg-order-input'));
    orderInputs.forEach(function (inputEl) {
        inputEl.addEventListener('change', function () {
            const packageId = inputEl.getAttribute('data-id');
            const eventId = inputEl.getAttribute('data-event-id');
            const rawValue = inputEl.value.trim();
            const parsedValue = Number.parseInt(rawValue, 10);

            if (!Number.isFinite(parsedValue) || parsedValue < 0) {
                alert('Sort order must be zero or more.');
                inputEl.value = inputEl.defaultValue || '0';
                return;
            }

            inputEl.style.opacity = '0.6';
            fetch('update-package-order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'package_id=' + encodeURIComponent(packageId)
                    + '&event_id=' + encodeURIComponent(eventId || '')
                    + '&display_order=' + encodeURIComponent(String(parsedValue))
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                inputEl.style.opacity = '1';
                if (data && data.success) {
                    inputEl.defaultValue = String(parsedValue);
                    inputEl.style.borderColor = '#1a8917';
                    setTimeout(function () {
                        inputEl.style.borderColor = '#ddd';
                    }, 1000);
                    return;
                }
                inputEl.value = inputEl.defaultValue || '0';
                inputEl.style.borderColor = '#dc3545';
                setTimeout(function () {
                    inputEl.style.borderColor = '#ddd';
                }, 1500);
                alert((data && data.error) ? data.error : 'Unable to update sort order.');
            })
            .catch(function () {
                inputEl.style.opacity = '1';
                inputEl.value = inputEl.defaultValue || '0';
                alert('Unable to update sort order right now.');
            });
        });
    });
})();
</script>
<script src="../includes/responsive-tables.js"></script>
</body>
</html>
