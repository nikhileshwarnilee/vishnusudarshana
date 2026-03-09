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

$tabs = ['all', 'non_checkin', 'checkin'];
$activeTab = trim((string)($_GET['tab'] ?? $_POST['tab'] ?? 'non_checkin'));
if (!in_array($activeTab, $tabs, true)) {
    $activeTab = 'non_checkin';
}

$message = trim((string)($_GET['msg'] ?? ''));
$messageType = trim((string)($_GET['msg_type'] ?? ''));
$error = '';

$isValidDate = static function (string $value): bool {
    return $value !== '' && (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
};

$checkinUserId = (int)($_GET['checkin_user_id'] ?? $_POST['checkin_user_id'] ?? 0);
$checkinDateFrom = trim((string)($_GET['checkin_date_from'] ?? $_POST['checkin_date_from'] ?? ''));
$checkinDateTo = trim((string)($_GET['checkin_date_to'] ?? $_POST['checkin_date_to'] ?? ''));
if (!$isValidDate($checkinDateFrom)) {
    $checkinDateFrom = '';
}
if (!$isValidDate($checkinDateTo)) {
    $checkinDateTo = '';
}
if ($checkinDateFrom !== '' && $checkinDateTo !== '' && strcmp($checkinDateFrom, $checkinDateTo) > 0) {
    [$checkinDateFrom, $checkinDateTo] = [$checkinDateTo, $checkinDateFrom];
}

$rawReturnUrl = trim((string)($_GET['return'] ?? $_POST['return'] ?? ''));
$returnUrl = '';
if (
    $rawReturnUrl !== '' &&
    strpos($rawReturnUrl, '..') === false &&
    !preg_match('/^[a-z]+:\/\//i', $rawReturnUrl) &&
    (
        strpos($rawReturnUrl, 'checkin.php') === 0 ||
        strpos($rawReturnUrl, 'pending-payments.php') === 0 ||
        strpos($rawReturnUrl, 'checkin-history.php') === 0
    )
) {
    $returnUrl = $rawReturnUrl;
}

$baseFilters = [];
if ($checkinUserId > 0) {
    $baseFilters['checkin_user_id'] = $checkinUserId;
}
if ($checkinDateFrom !== '') {
    $baseFilters['checkin_date_from'] = $checkinDateFrom;
}
if ($checkinDateTo !== '') {
    $baseFilters['checkin_date_to'] = $checkinDateTo;
}

$buildPageUrl = static function (array $params) use ($baseFilters): string {
    return 'pending-payments.php?' . http_build_query(array_merge($params, $baseFilters));
};

$redirectSelf = static function (string $tab, string $msg = '', string $msgType = '') use ($baseFilters): void {
    $params = array_merge(['tab' => $tab], $baseFilters);
    if ($msg !== '') {
        $params['msg'] = $msg;
        $params['msg_type'] = $msgType !== '' ? $msgType : 'ok';
    }
    header('Location: pending-payments.php?' . http_build_query($params));
    exit;
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
            r.persons,
            r.name,
            r.phone,
            r.booking_reference,
            r.payment_status,
            r.verification_status,
            r.checkin_status,
            r.checkin_time,
            r.checkin_by_user_id,
            r.checkin_by_user_name,
            r.package_upi_id_snapshot,
            r.package_upi_qr_snapshot,
            e.title AS event_title,
            e.event_type,
            COALESCE(d.event_date, e.event_date) AS selected_event_date,
            p.package_name,
            p.is_paid,
            p.upi_id,
            p.upi_qr_image,
            COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS package_price_total,
            COALESCE(ep.amount_paid, 0) AS amount_paid,
            COALESCE(ep.remaining_amount, 0) AS remaining_amount,
            COALESCE(ep.payment_method, '') AS payment_method,
            COALESCE(ep.status, '') AS payment_record_status,
            COALESCE(ep.transaction_id, '') AS transaction_id,
            COALESCE(ep.screenshot, '') AS screenshot,
            COALESCE(ep.remarks, '') AS remarks
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
    $remaining = round((float)($row['remaining_amount'] ?? 0), 2);
    if ($remaining <= 0 && $status !== 'cancelled') {
        $remaining = round(max($total - $paid, 0), 2);
    }
    return ['total' => $total, 'paid' => $paid, 'remaining' => $remaining];
};

$checkinUsers = [];
try {
    $checkinUsers = $pdo->query("SELECT DISTINCT
            COALESCE(r.checkin_by_user_id, 0) AS user_id,
            COALESCE(NULLIF(TRIM(r.checkin_by_user_name), ''), u.name, CONCAT('User #', COALESCE(r.checkin_by_user_id, 0))) AS user_name
        FROM event_registrations r
        LEFT JOIN users u ON u.id = r.checkin_by_user_id
        WHERE r.checkin_status = 1
          AND (
              COALESCE(r.checkin_by_user_id, 0) > 0
              OR COALESCE(NULLIF(TRIM(r.checkin_by_user_name), ''), '') <> ''
          )
        ORDER BY user_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $checkinUsers = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_payment'], $_POST['registration_id'])) {
    $registrationId = (int)$_POST['registration_id'];
    $activeTab = trim((string)($_POST['tab'] ?? $activeTab));
    if (!in_array($activeTab, $tabs, true)) {
        $activeTab = 'non_checkin';
    }
    $returnUrl = trim((string)($_POST['return'] ?? $returnUrl));
    if (
        $returnUrl !== '' &&
        (
            strpos($returnUrl, '..') !== false ||
            preg_match('/^[a-z]+:\/\//i', $returnUrl) ||
            (
                strpos($returnUrl, 'checkin.php') !== 0 &&
                strpos($returnUrl, 'pending-payments.php') !== 0 &&
                strpos($returnUrl, 'checkin-history.php') !== 0
            )
        )
    ) {
        $returnUrl = '';
    }

    try {
        $pdo->beginTransaction();
        $row = $fetchRegistration($pdo, $registrationId, true);
        if (!$row) {
            throw new RuntimeException('Registration not found.');
        }
        if ((int)($row['is_paid'] ?? 1) !== 1) {
            throw new RuntimeException('Payment collection is only for paid packages.');
        }

        $paymentStatus = strtolower(trim((string)($row['payment_status'] ?? '')));
        if (in_array($paymentStatus, ['paid', 'cancelled'], true)) {
            throw new RuntimeException('This registration is not eligible for pending payment collection.');
        }
        $paymentRecordStatus = strtolower(trim((string)($row['payment_record_status'] ?? '')));
        if ($paymentStatus === 'pending verification' && in_array($paymentRecordStatus, ['pending', 'pending verification'], true)) {
            throw new RuntimeException('Payment is already submitted and pending verification.');
        }

        $amounts = $computeAmounts($row);
        $remainingDue = (float)$amounts['remaining'];
        $alreadyPaid = (float)$amounts['paid'];
        if ($remainingDue <= 0) {
            throw new RuntimeException('No pending amount found for this registration.');
        }

        $amountInput = trim((string)($_POST['collect_amount'] ?? ''));
        if ($amountInput === '' || !is_numeric($amountInput)) {
            throw new RuntimeException('Please enter a valid collection amount.');
        }
        $collectAmount = round((float)$amountInput, 2);
        if ($collectAmount <= 0) {
            throw new RuntimeException('Collection amount must be greater than zero.');
        }
        if ($collectAmount > $remainingDue) {
            throw new RuntimeException('Collection amount cannot be greater than remaining amount.');
        }

        $methodInput = strtolower(trim((string)($_POST['collect_method'] ?? '')));
        if (!in_array($methodInput, ['upi', 'cash'], true)) {
            throw new RuntimeException('Please choose payment method (UPI or Cash).');
        }
        $paymentMethod = ($methodInput === 'upi') ? 'Manual UPI' : 'Cash';

        $transactionId = trim((string)($_POST['collect_transaction_id'] ?? ''));
        if ($methodInput === 'upi' && $transactionId === '') {
            throw new RuntimeException('Transaction ID is required for UPI collection.');
        }
        if ($methodInput === 'cash' && $transactionId === '') {
            $transactionId = 'CASH-PENDING-' . date('YmdHis');
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
            if ($returnUrl !== '') {
                header('Location: ' . $returnUrl);
                exit;
            }
            $redirectSelf($activeTab, 'Payment collected and auto-verified (main admin).', 'ok');
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
        if ($returnUrl !== '') {
            header('Location: ' . $returnUrl);
            exit;
        }
        $redirectSelf($activeTab, 'Payment collected and sent for verification.', 'ok');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to collect payment.';
    }
}

$selectedRegistrationId = isset($_GET['registration_id']) ? (int)$_GET['registration_id'] : (int)($_POST['registration_id'] ?? 0);
$selectedRegistration = null;
if ($selectedRegistrationId > 0) {
    $selectedRegistration = $fetchRegistration($pdo, $selectedRegistrationId, false);
    if ($selectedRegistration) {
        $selectedRegistration['event_date_display'] = vs_event_get_registration_date_display($pdo, $selectedRegistration, (string)($selectedRegistration['selected_event_date'] ?? ''));
        $selectedRegistration['amounts'] = $computeAmounts($selectedRegistration);
        $ref = trim((string)($selectedRegistration['booking_reference'] ?? ''));
        if ($ref === '') {
            $selectedRegistration['booking_reference'] = vs_event_assign_booking_reference($pdo, (int)$selectedRegistration['id']);
        }
    }
}

$where = ["p.is_paid = 1", "r.payment_status NOT IN ('Paid', 'Cancelled')"];
$params = [];
if ($activeTab === 'checkin') {
    $where[] = 'r.checkin_status = 1';
} elseif ($activeTab === 'non_checkin') {
    $where[] = 'r.checkin_status = 0';
}
if ($checkinUserId > 0) {
    $where[] = 'r.checkin_status = 1';
    $where[] = 'r.checkin_by_user_id = ?';
    $params[] = $checkinUserId;
}
if ($checkinDateFrom !== '') {
    $where[] = 'r.checkin_status = 1';
    $where[] = 'DATE(r.checkin_time) >= ?';
    $params[] = $checkinDateFrom;
}
if ($checkinDateTo !== '') {
    $where[] = 'r.checkin_status = 1';
    $where[] = 'DATE(r.checkin_time) <= ?';
    $params[] = $checkinDateTo;
}

$listStmt = $pdo->prepare("SELECT
        r.id,
        r.event_id,
        r.event_date_id,
        r.persons,
        r.name,
        r.phone,
        r.booking_reference,
        r.payment_status,
        r.verification_status,
        r.checkin_status,
        r.checkin_time,
        r.checkin_by_user_id,
        r.checkin_by_user_name,
        e.title AS event_title,
        e.event_type,
        COALESCE(d.event_date, e.event_date) AS selected_event_date,
        p.package_name,
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
    WHERE " . implode(' AND ', $where) . "
    ORDER BY r.checkin_status DESC, r.checkin_time DESC, r.id DESC");
$listStmt->execute($params);
$registrations = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$summary = ['records' => 0, 'pending_amount' => 0.0, 'checkin_records' => 0, 'checkin_pending_amount' => 0.0];
foreach ($registrations as &$row) {
    $ref = trim((string)($row['booking_reference'] ?? ''));
    if ($ref === '') {
        $ref = vs_event_assign_booking_reference($pdo, (int)$row['id']);
        $row['booking_reference'] = $ref;
    }
    $row['event_date_display'] = vs_event_get_registration_date_display($pdo, $row, (string)($row['selected_event_date'] ?? ''));
    $row['amounts'] = $computeAmounts($row);
    $due = max((float)($row['amounts']['remaining'] ?? 0), 0);
    $summary['records']++;
    $summary['pending_amount'] += $due;
    if ((int)($row['checkin_status'] ?? 0) === 1) {
        $summary['checkin_records']++;
        $summary['checkin_pending_amount'] += $due;
    }
}
unset($row);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Payments</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f7f7fa; margin:0; }
        .admin-container { max-width:1500px; margin:0 auto; padding:24px 12px; }
        h1 { color:#800000; margin-bottom:14px; }
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 12px #e0bebe22; padding:14px; margin-bottom:16px; }
        .notice { margin:10px 0; padding:10px 12px; border-radius:8px; font-weight:600; }
        .notice.ok { background:#e7f7ed; color:#1a8917; }
        .notice.err { background:#ffeaea; color:#b00020; }
        .tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px; }
        .tab-btn { display:inline-block; padding:8px 12px; border-radius:8px; text-decoration:none; font-weight:700; background:#ece2e2; color:#6a272f; }
        .tab-btn.active { background:#800000; color:#fff; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:10px; align-items:end; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        label { color:#800000; font-weight:700; font-size:0.9em; }
        input, select, textarea { width:100%; box-sizing:border-box; padding:9px 10px; border:1px solid #e0bebe; border-radius:8px; font-size:0.94em; }
        textarea { min-height:80px; resize:vertical; }
        .btn { display:inline-block; border:none; border-radius:8px; padding:8px 11px; font-weight:700; cursor:pointer; text-decoration:none; background:#800000; color:#fff; }
        .btn-alt { background:#0b7285; }
        .small { color:#666; font-size:0.84em; }
        .summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:10px; margin-bottom:14px; }
        .summary-card { border:1px solid #efd3d3; border-radius:10px; padding:10px 11px; background:#fffaf9; }
        .summary-label { margin:0; font-size:0.78rem; text-transform:uppercase; letter-spacing:.04em; color:#7a5151; font-weight:700; }
        .summary-value { margin:4px 0 0; color:#800000; font-size:1.15rem; font-weight:800; }
        .list-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; table-layout:auto; font-size:0.86em; }
        .list-table th, .list-table td { padding:8px 6px; border-bottom:1px solid #f3caca; text-align:left; vertical-align:top; }
        .list-table th { background:#f9eaea; color:#800000; font-size:0.9em; }
        .status-chip { display:inline-block; border-radius:12px; padding:3px 10px; font-size:0.82em; font-weight:700; }
        .status-ok { background:#e7f7ed; color:#1a8917; }
        .status-pending { background:#fff4db; color:#b36b00; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <h1>Pending Payments Collection</h1>
    <?php if ($message !== ''): ?><div class="notice <?php echo ($messageType === 'ok') ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="tabs">
        <a class="tab-btn <?php echo $activeTab === 'all' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($buildPageUrl(['tab' => 'all'])); ?>">All Pending</a>
        <a class="tab-btn <?php echo $activeTab === 'non_checkin' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($buildPageUrl(['tab' => 'non_checkin'])); ?>">Non Check-In</a>
        <a class="tab-btn <?php echo $activeTab === 'checkin' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($buildPageUrl(['tab' => 'checkin'])); ?>">Check-In Done</a>
    </div>

    <div class="card">
        <form method="get" class="grid">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
            <div class="form-group">
                <label>Checked-In By User</label>
                <select name="checkin_user_id">
                    <option value="">All Users</option>
                    <?php foreach ($checkinUsers as $checkinUser): ?>
                        <?php $tmpUserId = (int)($checkinUser['user_id'] ?? 0); ?>
                        <?php if ($tmpUserId <= 0) { continue; } ?>
                        <option value="<?php echo $tmpUserId; ?>" <?php echo ($checkinUserId === $tmpUserId) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)($checkinUser['user_name'] ?? ('User #' . $tmpUserId))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Check-In Date From</label>
                <input type="date" name="checkin_date_from" value="<?php echo htmlspecialchars($checkinDateFrom); ?>">
            </div>
            <div class="form-group">
                <label>Check-In Date To</label>
                <input type="date" name="checkin_date_to" value="<?php echo htmlspecialchars($checkinDateTo); ?>">
            </div>
            <div class="form-group"><button type="submit" class="btn">Apply Filters</button></div>
            <div class="form-group"><a class="btn btn-alt" href="<?php echo htmlspecialchars('pending-payments.php?tab=' . urlencode($activeTab)); ?>">Reset</a></div>
        </form>
    </div>

    <div class="summary-grid">
        <div class="summary-card"><p class="summary-label">Pending Registrations</p><p class="summary-value"><?php echo number_format((float)$summary['records'], 0, '.', ''); ?></p></div>
        <div class="summary-card"><p class="summary-label">Total Pending Amount</p><p class="summary-value">Rs <?php echo number_format((float)$summary['pending_amount'], 0, '.', ''); ?></p></div>
        <div class="summary-card"><p class="summary-label">Check-In Pending Count</p><p class="summary-value"><?php echo number_format((float)$summary['checkin_records'], 0, '.', ''); ?></p></div>
        <div class="summary-card"><p class="summary-label">Check-In Pending Amount</p><p class="summary-value">Rs <?php echo number_format((float)$summary['checkin_pending_amount'], 0, '.', ''); ?></p></div>
    </div>

    <div class="card">
        <div style="overflow:auto;">
            <table class="list-table">
                <thead>
                <tr>
                    <th>ID</th><th>Booking Ref</th><th>Event</th><th>Package</th><th>Name / Phone</th><th>Check-In</th><th>Checked-In By</th><th>Check-In Time</th><th>Payment</th><th>Verification</th><th>Total</th><th>Paid</th><th>Due</th><th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($registrations)): ?>
                    <tr><td colspan="14" style="text-align:center; padding:20px; color:#666;">No pending payment registrations found.</td></tr>
                <?php else: ?>
                    <?php foreach ($registrations as $row): ?>
                        <?php
                        $due = (float)($row['amounts']['remaining'] ?? 0);
                        $paymentStatusLower = strtolower(trim((string)($row['payment_status'] ?? '')));
                        $recordStatusLower = strtolower(trim((string)($row['payment_record_status'] ?? '')));
                        $isAwaitingVerification = ($paymentStatusLower === 'pending verification' && in_array($recordStatusLower, ['pending', 'pending verification'], true));
                        $checkinByName = trim((string)($row['checkin_by_user_name'] ?? ''));
                        if ($checkinByName === '' && (int)($row['checkin_by_user_id'] ?? 0) > 0) {
                            $checkinByName = 'User #' . (int)$row['checkin_by_user_id'];
                        }
                        ?>
                        <tr>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars((string)$row['booking_reference']); ?></strong></td>
                            <td><strong><?php echo htmlspecialchars((string)$row['event_title']); ?></strong><br><span class="small"><?php echo htmlspecialchars((string)($row['event_date_display'] ?? $row['selected_event_date'])); ?></span></td>
                            <td><?php echo htmlspecialchars((string)$row['package_name']); ?></td>
                            <td><strong><?php echo htmlspecialchars((string)$row['name']); ?></strong><br><span class="small"><?php echo htmlspecialchars((string)$row['phone']); ?></span></td>
                            <td><span class="status-chip <?php echo ((int)$row['checkin_status'] === 1) ? 'status-ok' : 'status-pending'; ?>"><?php echo ((int)$row['checkin_status'] === 1) ? 'Checked In' : 'Not Checked In'; ?></span></td>
                            <td><?php echo $checkinByName !== '' ? htmlspecialchars($checkinByName) : '-'; ?></td>
                            <td><?php echo !empty($row['checkin_time']) ? htmlspecialchars((string)$row['checkin_time']) : '-'; ?></td>
                            <td><span class="status-chip status-pending"><?php echo htmlspecialchars((string)$row['payment_status']); ?></span></td>
                            <td><?php echo htmlspecialchars((string)$row['verification_status']); ?></td>
                            <td>Rs <?php echo number_format((float)($row['amounts']['total'] ?? 0), 0, '.', ''); ?></td>
                            <td>Rs <?php echo number_format((float)($row['amounts']['paid'] ?? 0), 0, '.', ''); ?></td>
                            <td>Rs <?php echo number_format($due, 0, '.', ''); ?></td>
                            <td>
                                <a class="btn btn-alt" href="<?php echo htmlspecialchars($buildPageUrl(['tab' => $activeTab, 'registration_id' => (int)$row['id']])); ?>">Collect</a>
                                <?php if ($isAwaitingVerification): ?><div class="small" style="margin-top:4px;">Awaiting verification</div><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($selectedRegistration): ?>
        <?php
        $selectedAmounts = $selectedRegistration['amounts'] ?? ['total' => 0, 'paid' => 0, 'remaining' => 0];
        $selectedPaymentStatus = strtolower(trim((string)($selectedRegistration['payment_status'] ?? '')));
        $selectedRecordStatus = strtolower(trim((string)($selectedRegistration['payment_record_status'] ?? '')));
        $selectedAwaitingVerification = (
            $selectedPaymentStatus === 'pending verification' &&
            in_array($selectedRecordStatus, ['pending', 'pending verification'], true)
        );
        $selectedCheckinBy = trim((string)($selectedRegistration['checkin_by_user_name'] ?? ''));
        if ($selectedCheckinBy === '' && (int)($selectedRegistration['checkin_by_user_id'] ?? 0) > 0) {
            $selectedCheckinBy = 'User #' . (int)$selectedRegistration['checkin_by_user_id'];
        }
        ?>
        <div class="card">
            <h3 style="margin-top:0; color:#800000;">Collect Payment: <?php echo htmlspecialchars((string)$selectedRegistration['booking_reference']); ?></h3>
            <p class="small" style="margin:0 0 10px;">
                <?php echo htmlspecialchars((string)$selectedRegistration['event_title']); ?> |
                <?php echo htmlspecialchars((string)$selectedRegistration['package_name']); ?> |
                <?php echo htmlspecialchars((string)($selectedRegistration['event_date_display'] ?? $selectedRegistration['selected_event_date'])); ?> |
                <?php echo ((int)$selectedRegistration['checkin_status'] === 1) ? 'Checked In' : 'Not Checked In'; ?>
                <?php if ((int)$selectedRegistration['checkin_status'] === 1): ?>
                    | <?php echo htmlspecialchars($selectedCheckinBy !== '' ? $selectedCheckinBy : 'User'); ?>
                    | <?php echo htmlspecialchars((string)($selectedRegistration['checkin_time'] ?? '')); ?>
                <?php endif; ?>
            </p>
            <form method="post" enctype="multipart/form-data" autocomplete="off" class="grid">
                <input type="hidden" name="collect_payment" value="1">
                <input type="hidden" name="registration_id" value="<?php echo (int)$selectedRegistration['id']; ?>">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
                <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnUrl); ?>">
                <input type="hidden" name="checkin_user_id" value="<?php echo $checkinUserId > 0 ? (int)$checkinUserId : ''; ?>">
                <input type="hidden" name="checkin_date_from" value="<?php echo htmlspecialchars($checkinDateFrom); ?>">
                <input type="hidden" name="checkin_date_to" value="<?php echo htmlspecialchars($checkinDateTo); ?>">

                <div class="form-group"><label>Total Amount</label><input type="text" value="Rs <?php echo number_format((float)$selectedAmounts['total'], 0, '.', ''); ?>" readonly></div>
                <div class="form-group"><label>Paid So Far</label><input type="text" value="Rs <?php echo number_format((float)$selectedAmounts['paid'], 0, '.', ''); ?>" readonly></div>
                <div class="form-group"><label>Remaining Due</label><input type="text" value="Rs <?php echo number_format((float)$selectedAmounts['remaining'], 0, '.', ''); ?>" readonly></div>
                <div class="form-group"><label>Collect Amount</label><input type="number" step="0.01" min="1" max="<?php echo htmlspecialchars((string)max((float)$selectedAmounts['remaining'], 1)); ?>" name="collect_amount" value="<?php echo htmlspecialchars((string)((float)$selectedAmounts['remaining'] > 0 ? (float)$selectedAmounts['remaining'] : '')); ?>" required></div>
                <div class="form-group"><label>Payment Method</label><select name="collect_method" required><option value="">Select Method</option><option value="upi">UPI</option><option value="cash">Cash</option></select></div>
                <div class="form-group"><label>Transaction / Receipt ID</label><input type="text" name="collect_transaction_id" placeholder="UPI transaction id or cash receipt no"></div>
                <div class="form-group"><label>Payment Proof (Image)</label><input type="file" name="collect_proof" accept=".jpg,.jpeg,.png,.webp" <?php echo $selectedAwaitingVerification ? '' : 'required'; ?>></div>
                <div class="form-group" style="grid-column:1/-1;"><label>Remark</label><textarea name="collect_remark" placeholder="Add payment collection remark" required></textarea></div>
                <div class="form-group" style="grid-column:1/-1;">
                    <button type="submit" class="btn" <?php echo $selectedAwaitingVerification ? 'disabled' : ''; ?> onclick="return confirm('<?php echo $isMainAdminActor ? 'Collect and auto-verify this payment?' : 'Submit this payment for verification?'; ?>');"><?php echo $isMainAdminActor ? 'Collect & Auto Verify' : 'Submit For Verification'; ?></button>
                    <?php if ($selectedAwaitingVerification): ?><span class="small" style="margin-left:8px;">Payment is already pending verification for this registration.</span><?php endif; ?>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script src="../includes/responsive-tables.js"></script>
</body>
</html>
