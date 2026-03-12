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
$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : (int)($_POST['event_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_waitlist_id'])) {
    $waitlistId = (int)$_POST['delete_waitlist_id'];
    if ($waitlistId > 0) {
        $stmt = $pdo->prepare("DELETE FROM event_waitlist WHERE id = ? LIMIT 1");
        $stmt->execute([$waitlistId]);
        if ($stmt->rowCount() > 0) {
            $message = 'Waitlist entry removed.';
        } else {
            $error = 'Waitlist entry not found.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_waitlist_id'])) {
    $waitlistId = (int)$_POST['convert_waitlist_id'];
    if ($waitlistId <= 0) {
        $error = 'Invalid waitlist entry.';
    } else {
        try {
            $pdo->beginTransaction();

            $waitStmt = $pdo->prepare("SELECT *
                FROM event_waitlist
                WHERE id = ?
                LIMIT 1
                FOR UPDATE");
            $waitStmt->execute([$waitlistId]);
            $waitRow = $waitStmt->fetch(PDO::FETCH_ASSOC);
            if (!$waitRow) {
                throw new RuntimeException('Waitlist entry not found.');
            }

            $packageStmt = $pdo->prepare("SELECT
                p.id,
                p.event_id,
                p.package_name,
                p.price,
                p.price_total,
                p.seat_limit,
                p.status,
                e.title AS event_title,
                e.event_date,
                e.event_type,
                e.status AS event_status
            FROM event_packages p
            INNER JOIN events e ON e.id = p.event_id
            WHERE p.id = ?
              AND p.event_id = ?
            LIMIT 1
            FOR UPDATE");
            $packageStmt->execute([(int)$waitRow['package_id'], (int)$waitRow['event_id']]);
            $packageRow = $packageStmt->fetch(PDO::FETCH_ASSOC);
            if (!$packageRow || (string)$packageRow['status'] !== 'Active') {
                throw new RuntimeException('Selected package is not active.');
            }
            if ((string)$packageRow['event_status'] !== 'Active') {
                throw new RuntimeException('Event is closed.');
            }

            $dateRow = null;
            $waitEventDateId = (int)($waitRow['event_date_id'] ?? 0);
            if ($waitEventDateId > 0) {
                $dateStmt = $pdo->prepare("SELECT id, event_date, seat_limit, status
                    FROM event_dates
                    WHERE id = ?
                      AND event_id = ?
                    LIMIT 1
                    FOR UPDATE");
                $dateStmt->execute([$waitEventDateId, (int)$waitRow['event_id']]);
                $dateRow = $dateStmt->fetch(PDO::FETCH_ASSOC);
                if (!$dateRow || (string)$dateRow['status'] !== 'Active') {
                    throw new RuntimeException('Selected waitlist date is not active.');
                }
            }

            $duplicateStmt = $pdo->prepare("SELECT id FROM event_registrations
                WHERE package_id = ?
                  AND phone = ?
                LIMIT 1
                FOR UPDATE");
            $duplicateStmt->execute([(int)$waitRow['package_id'], (string)$waitRow['phone']]);
            if ($duplicateStmt->fetch()) {
                throw new RuntimeException('This phone is already registered for this package.');
            }

            $seatLimit = isset($packageRow['seat_limit']) ? (int)$packageRow['seat_limit'] : 0;
            $persons = max((int)$waitRow['persons'], 1);
            if ($seatLimit > 0) {
                $usedStmt = $pdo->prepare("SELECT COALESCE(SUM(persons),0)
                    FROM event_registrations
                    WHERE package_id = ?
                      AND (? = 0 OR event_date_id = ?)
                      AND verification_status IN ('Pending', 'Approved', 'Auto Verified')
                      AND payment_status NOT IN ('Failed', 'Cancelled')
                    FOR UPDATE");
                $usedStmt->execute([(int)$waitRow['package_id'], $waitEventDateId, $waitEventDateId]);
                $usedSeats = (int)$usedStmt->fetchColumn();
                if (($usedSeats + $persons) > $seatLimit) {
                    throw new RuntimeException('Seats are still full for this package.');
                }
            }

            if ($dateRow && (int)($dateRow['seat_limit'] ?? 0) > 0) {
                $dateSeatLimit = (int)$dateRow['seat_limit'];
                $dateUsedStmt = $pdo->prepare("SELECT COALESCE(SUM(persons),0)
                    FROM event_registrations
                    WHERE event_id = ?
                      AND event_date_id = ?
                      AND verification_status IN ('Pending', 'Approved', 'Auto Verified')
                      AND payment_status NOT IN ('Failed', 'Cancelled')
                    FOR UPDATE");
                $dateUsedStmt->execute([(int)$waitRow['event_id'], $waitEventDateId]);
                $dateUsed = (int)$dateUsedStmt->fetchColumn();
                if (($dateUsed + $persons) > $dateSeatLimit) {
                    throw new RuntimeException('Selected date is fully booked.');
                }
            }

            $insertReg = $pdo->prepare("INSERT INTO event_registrations
                (event_id, package_id, event_date_id, name, phone, persons, payment_status, verification_status)
                VALUES (?, ?, ?, ?, ?, ?, 'Unpaid', 'Pending')");
            $insertReg->execute([
                (int)$waitRow['event_id'],
                (int)$waitRow['package_id'],
                $waitEventDateId > 0 ? $waitEventDateId : null,
                (string)$waitRow['name'],
                (string)$waitRow['phone'],
                $persons,
            ]);
            $registrationId = (int)$pdo->lastInsertId();
            $bookingReference = vs_event_assign_booking_reference($pdo, $registrationId);

            $dataInsert = $pdo->prepare("INSERT INTO event_registration_data (registration_id, field_name, value) VALUES (?, ?, ?)");
            $dataInsert->execute([$registrationId, 'Name', (string)$waitRow['name']]);
            $dataInsert->execute([$registrationId, 'Phone', (string)$waitRow['phone']]);
            $dataInsert->execute([$registrationId, 'Persons', (string)$persons]);
            $dataInsert->execute([$registrationId, 'Source', 'Waitlist Conversion']);
            $dataInsert->execute([$registrationId, 'Booking Reference', (string)$bookingReference]);
            if ($dateRow) {
                $dataInsert->execute([$registrationId, 'Selected Event Date', (string)$dateRow['event_date']]);
            } elseif (vs_event_normalize_event_type((string)($packageRow['event_type'] ?? 'single_day')) === 'date_range') {
                $dataInsert->execute([$registrationId, 'Selected Event Date', vs_event_get_event_date_display($pdo, (int)$waitRow['event_id'], (string)($packageRow['event_date'] ?? ''), 'date_range')]);
            }

            $pdo->prepare("DELETE FROM event_waitlist WHERE id = ?")->execute([$waitlistId]);

            $pdo->commit();
            $message = 'Waitlist converted to registration. Booking reference: ' . $bookingReference;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to convert waitlist entry.';
            error_log('Waitlist conversion failed: ' . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_waitlisted_registration'])) {
    $registrationId = (int)($_POST['registration_id'] ?? 0);
    if ($registrationId <= 0) {
        $error = 'Invalid waitlisted registration selected.';
    } else {
        try {
            vs_event_confirm_waitlisted_registration($pdo, $registrationId);
            $message = 'Waitlisted booking confirmed successfully.';
        } catch (Throwable $e) {
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to confirm waitlisted booking right now.';
        }
    }
}

$waitlistedWhere = ["(r.payment_status = 'Waitlisted' OR r.verification_status = 'Waitlisted')"];
$waitlistedParams = [];
if ($eventId > 0) {
    $waitlistedWhere[] = 'r.event_id = ?';
    $waitlistedParams[] = $eventId;
}
$waitlistedWhereSql = 'WHERE ' . implode(' AND ', $waitlistedWhere);

$waitlistedSql = "SELECT
    r.id,
    r.event_id,
    r.event_date_id,
    r.package_id,
    r.booking_reference,
    r.name,
    r.phone,
    r.persons,
    r.created_at,
    e.title AS event_title,
    e.event_type,
    COALESCE(d.event_date, e.event_date) AS selected_event_date,
    p.package_name,
    p.seat_limit,
    COALESCE(NULLIF(p.waitlist_confirmation_mode, ''), 'auto') AS waitlist_confirmation_mode,
    d.seat_limit AS date_seat_limit,
    COALESCE((
        SELECT SUM(r2.persons)
        FROM event_registrations r2
        WHERE r2.package_id = r.package_id
          AND (COALESCE(r.event_date_id, 0) = 0 OR r2.event_date_id = r.event_date_id)
          AND r2.id <> r.id
          AND r2.verification_status IN ('Pending', 'Approved', 'Auto Verified')
          AND r2.payment_status NOT IN ('Failed', 'Cancelled')
    ), 0) AS booked_package_seats,
    COALESCE((
        SELECT SUM(r3.persons)
        FROM event_registrations r3
        WHERE r3.event_id = r.event_id
          AND COALESCE(r.event_date_id, 0) > 0
          AND r3.event_date_id = r.event_date_id
          AND r3.id <> r.id
          AND r3.verification_status IN ('Pending', 'Approved', 'Auto Verified')
          AND r3.payment_status NOT IN ('Failed', 'Cancelled')
    ), 0) AS booked_date_seats
FROM event_registrations r
INNER JOIN events e ON e.id = r.event_id
INNER JOIN event_packages p ON p.id = r.package_id
LEFT JOIN event_dates d ON d.id = r.event_date_id
$waitlistedWhereSql
ORDER BY r.created_at ASC, r.id ASC";
$waitlistedStmt = $pdo->prepare($waitlistedSql);
$waitlistedStmt->execute($waitlistedParams);
$waitlistedRegistrations = $waitlistedStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($waitlistedRegistrations as &$waitlistedRow) {
    $waitlistedRow['event_date_display'] = vs_event_get_registration_date_display(
        $pdo,
        $waitlistedRow,
        (string)($waitlistedRow['selected_event_date'] ?? '')
    );
    $waitlistedRow['waitlist_position'] = vs_event_get_waitlist_position($pdo, (int)$waitlistedRow['id']);

    $packageSeatLimit = isset($waitlistedRow['seat_limit']) ? (int)$waitlistedRow['seat_limit'] : 0;
    $dateSeatLimit = isset($waitlistedRow['date_seat_limit']) ? (int)$waitlistedRow['date_seat_limit'] : 0;
    $bookedPackageSeats = (int)($waitlistedRow['booked_package_seats'] ?? 0);
    $bookedDateSeats = (int)($waitlistedRow['booked_date_seats'] ?? 0);
    $packageRemaining = $packageSeatLimit > 0 ? max($packageSeatLimit - $bookedPackageSeats, 0) : null;
    $dateRemaining = $dateSeatLimit > 0 ? max($dateSeatLimit - $bookedDateSeats, 0) : null;

    if ($packageRemaining !== null && $dateRemaining !== null) {
        $remainingSeats = min($packageRemaining, $dateRemaining);
    } elseif ($packageRemaining !== null) {
        $remainingSeats = $packageRemaining;
    } else {
        $remainingSeats = $dateRemaining;
    }

    $waitlistedRow['remaining_seats'] = $remainingSeats;
    $waitlistedRow['can_confirm_waitlist'] = ($remainingSeats === null || $remainingSeats >= max((int)($waitlistedRow['persons'] ?? 1), 1)) ? 1 : 0;
}
unset($waitlistedRow);

$legacyWhere = '';
$legacyParams = [];
if ($eventId > 0) {
    $legacyWhere = 'WHERE w.event_id = ?';
    $legacyParams[] = $eventId;
}

$legacySql = "SELECT
    w.*,
    e.title AS event_title,
    e.event_type,
    COALESCE(d.event_date, e.event_date) AS selected_event_date,
    p.package_name,
    COALESCE(NULLIF(p.price_total, 0), p.price) AS price,
    p.seat_limit,
    d.seat_limit AS date_seat_limit,
    COALESCE((
        SELECT SUM(r.persons)
        FROM event_registrations r
        WHERE r.package_id = w.package_id
          AND (w.event_date_id IS NULL OR w.event_date_id = 0 OR r.event_date_id = w.event_date_id)
          AND r.verification_status IN ('Pending', 'Approved', 'Auto Verified')
          AND r.payment_status NOT IN ('Failed', 'Cancelled')
    ), 0) AS booked_package_seats,
    COALESCE((
        SELECT SUM(r.persons)
        FROM event_registrations r
        WHERE r.event_id = w.event_id
          AND w.event_date_id IS NOT NULL
          AND w.event_date_id > 0
          AND r.event_date_id = w.event_date_id
          AND r.verification_status IN ('Pending', 'Approved', 'Auto Verified')
          AND r.payment_status NOT IN ('Failed', 'Cancelled')
    ), 0) AS booked_seats
FROM event_waitlist w
INNER JOIN events e ON e.id = w.event_id
INNER JOIN event_packages p ON p.id = w.package_id
LEFT JOIN event_dates d ON d.id = w.event_date_id
$legacyWhere
ORDER BY w.id DESC";
$legacyStmt = $pdo->prepare($legacySql);
$legacyStmt->execute($legacyParams);
$legacyRows = $legacyStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($legacyRows as &$legacyRow) {
    $legacyRow['event_date_display'] = vs_event_get_registration_date_display(
        $pdo,
        $legacyRow,
        (string)($legacyRow['selected_event_date'] ?? '')
    );
}
unset($legacyRow);
$waitlistReturnUrl = 'waitlist.php';
if ($eventId > 0) {
    $waitlistReturnUrl .= '?event_id=' . (int)$eventId;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Waitlist</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f7f7fa; margin:0; }
        .admin-container { max-width:1300px; margin:0 auto; padding:24px 12px; }
        h1 { color:#800000; margin-bottom:14px; }
        .notice { margin:10px 0; padding:10px 12px; border-radius:8px; font-weight:600; }
        .notice.ok { background:#e7f7ed; color:#1a8917; }
        .notice.err { background:#ffeaea; color:#b00020; }
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 12px #e0bebe22; padding:14px; margin-bottom:16px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:10px; align-items:end; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        label { color:#800000; font-weight:700; font-size:0.9em; }
        select { width:100%; box-sizing:border-box; padding:9px 10px; border:1px solid #e0bebe; border-radius:8px; font-size:0.94em; }
        .btn-main { display:inline-block; border:none; border-radius:8px; padding:9px 12px; font-weight:700; cursor:pointer; text-decoration:none; background:#800000; color:#fff; }
        .btn-alt { background:#0b7285; }
        .btn-ok { background:#1a8917; }
        .btn-danger { background:#dc3545; }
        .list-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px #e0bebe22; table-layout:auto; font-size:0.86em; }
        .list-table th, .list-table td { padding:8px 6px; border-bottom:1px solid #f3caca; text-align:left; vertical-align:top; }
        .list-table th { background:#f9eaea; color:#800000; font-size:0.9em; }
        .small { color:#666; font-size:0.84em; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <h1>Event Waitlist</h1>

    <?php if ($message !== ''): ?><div class="notice ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card">
        <form method="get" class="grid">
            <div class="form-group">
                <label>Event</label>
                <select name="event_id">
                    <option value="0">All Events</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo (int)$event['id']; ?>" <?php echo ($eventId === (int)$event['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$event['title'] . ' (' . (string)($event['event_date_display'] ?? $event['event_date']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn-main">Apply Filter</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h3 style="margin:0 0 10px;color:#800000;">Waitlisted Registrations</h3>
        <table class="list-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Booking Ref</th>
                    <th>Event</th>
                    <th>Package</th>
                    <th>Name / Phone</th>
                    <th>Persons</th>
                    <th>Position</th>
                    <th>Confirm Mode</th>
                    <th>Seats</th>
                    <th>Joined At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($waitlistedRegistrations)): ?>
                <tr><td colspan="11" style="text-align:center; padding:18px; color:#666;">No waitlisted registrations found.</td></tr>
            <?php else: ?>
                <?php foreach ($waitlistedRegistrations as $row): ?>
                    <?php
                    $remainingSeats = $row['remaining_seats'];
                    $remainingSeatsText = ($remainingSeats === null) ? 'Unlimited' : (string)(int)$remainingSeats;
                    $confirmModeText = ucfirst(strtolower((string)($row['waitlist_confirmation_mode'] ?? 'auto')));
                    if (!in_array(strtolower((string)($row['waitlist_confirmation_mode'] ?? 'auto')), ['auto', 'manual'], true)) {
                        $confirmModeText = 'Auto';
                    }
                    ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars((string)($row['booking_reference'] ?? '-')); ?></strong></td>
                        <td>
                            <strong><?php echo htmlspecialchars((string)$row['event_title']); ?></strong><br>
                            <span class="small"><?php echo htmlspecialchars((string)($row['event_date_display'] ?? $row['selected_event_date'])); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars((string)$row['package_name']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars((string)$row['name']); ?></strong><br>
                            <span class="small"><?php echo htmlspecialchars(vs_format_mobile_for_display((string)$row['phone'])); ?></span>
                        </td>
                        <td><?php echo (int)$row['persons']; ?></td>
                        <td><?php echo (int)($row['waitlist_position'] ?? 0) > 0 ? ('#' . (int)$row['waitlist_position']) : 'Pending'; ?></td>
                        <td><?php echo htmlspecialchars($confirmModeText); ?></td>
                        <td>
                            <?php if ($remainingSeats === null): ?>
                                <span class="small">Unlimited</span>
                            <?php else: ?>
                                <span class="small">Booked (Package): <?php echo (int)$row['booked_package_seats']; ?><?php if ((int)($row['seat_limit'] ?? 0) > 0): ?> / <?php echo (int)$row['seat_limit']; ?><?php endif; ?></span><br>
                                <?php if ((int)($row['date_seat_limit'] ?? 0) > 0): ?><span class="small">Booked (Date): <?php echo (int)$row['booked_date_seats']; ?> / <?php echo (int)$row['date_seat_limit']; ?></span><br><?php endif; ?>
                                <strong>Left: <?php echo htmlspecialchars($remainingSeatsText); ?></strong>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string)$row['created_at']); ?></td>
                        <td>
                            <a class="btn-main btn-alt" href="registration-view.php?id=<?php echo (int)$row['id']; ?>&return=<?php echo urlencode($waitlistReturnUrl); ?>">View</a>
                            <?php if ((int)($row['can_confirm_waitlist'] ?? 0) === 1): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="event_id" value="<?php echo (int)$eventId; ?>">
                                    <input type="hidden" name="confirm_waitlisted_registration" value="1">
                                    <input type="hidden" name="registration_id" value="<?php echo (int)$row['id']; ?>">
                                    <button type="submit" class="btn-main btn-ok" onclick="return confirm('Confirm this waitlisted booking now?');">Confirm</button>
                                </form>
                            <?php else: ?>
                                <span class="small">No seats</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin:0 0 6px;color:#800000;">Legacy Waitlist Entries</h3>
        <p class="small" style="margin:0 0 10px;">These are entries from old `event_waitlist` table. New flow uses registrations with `Waitlisted` status.</p>
        <table class="list-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Event</th>
                    <th>Package</th>
                    <th>Name / Phone</th>
                    <th>Persons</th>
                    <th>Seats</th>
                    <th>Joined At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($legacyRows)): ?>
                <tr><td colspan="8" style="text-align:center; padding:18px; color:#666;">No legacy waitlist entries found.</td></tr>
            <?php else: ?>
                <?php foreach ($legacyRows as $row): ?>
                    <?php
                    $packageSeatLimit = isset($row['seat_limit']) ? (int)$row['seat_limit'] : 0;
                    $dateSeatLimit = isset($row['date_seat_limit']) ? (int)$row['date_seat_limit'] : 0;
                    $bookedPackageSeats = (int)$row['booked_package_seats'];
                    $bookedDateSeats = (int)$row['booked_seats'];
                    $packageRemaining = $packageSeatLimit > 0 ? max($packageSeatLimit - $bookedPackageSeats, 0) : null;
                    $dateRemaining = $dateSeatLimit > 0 ? max($dateSeatLimit - $bookedDateSeats, 0) : null;
                    if ($packageRemaining !== null && $dateRemaining !== null) {
                        $remaining = min($packageRemaining, $dateRemaining);
                    } elseif ($packageRemaining !== null) {
                        $remaining = $packageRemaining;
                    } else {
                        $remaining = $dateRemaining;
                    }
                    ?>
                    <tr>
                        <td><?php echo (int)$row['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars((string)$row['event_title']); ?></strong><br>
                            <span class="small"><?php echo htmlspecialchars((string)($row['event_date_display'] ?? $row['selected_event_date'])); ?></span>
                        </td>
                        <td>
                            <?php echo htmlspecialchars((string)$row['package_name']); ?><br>
                            <span class="small">Rs <?php echo number_format((float)$row['price'], 2); ?></span>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars((string)$row['name']); ?></strong><br>
                            <span class="small"><?php echo htmlspecialchars(vs_format_mobile_for_display((string)$row['phone'])); ?></span>
                        </td>
                        <td><?php echo (int)$row['persons']; ?></td>
                        <td>
                            <?php if ($remaining === null): ?>
                                <span class="small">Unlimited</span>
                            <?php else: ?>
                                <span class="small">Booked (Package): <?php echo $bookedPackageSeats; ?><?php if ($packageSeatLimit > 0): ?> / <?php echo $packageSeatLimit; ?><?php endif; ?></span><br>
                                <?php if ($dateSeatLimit > 0): ?><span class="small">Booked (Date): <?php echo $bookedDateSeats; ?> / <?php echo $dateSeatLimit; ?></span><br><?php endif; ?>
                                <strong>Left: <?php echo $remaining; ?></strong>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string)$row['created_at']); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="event_id" value="<?php echo (int)$eventId; ?>">
                                <input type="hidden" name="convert_waitlist_id" value="<?php echo (int)$row['id']; ?>">
                                <button type="submit" class="btn-main btn-ok" onclick="return confirm('Convert this waitlist entry to registration?');">Convert</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="event_id" value="<?php echo (int)$eventId; ?>">
                                <input type="hidden" name="delete_waitlist_id" value="<?php echo (int)$row['id']; ?>">
                                <button type="submit" class="btn-main btn-danger" onclick="return confirm('Remove this waitlist entry?');">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="../includes/responsive-tables.js"></script>
</body>
</html>
