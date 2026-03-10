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

$normalizeDate = static function (string $rawDate): string {
    $rawDate = trim($rawDate);
    if ($rawDate === '') {
        return '';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
        return '';
    }
    return $rawDate;
};

$formatMoney = static function (float $amount): string {
    return number_format($amount, 2, '.', ',');
};

$formatPercent = static function (float $value): string {
    if ($value < 0) {
        $value = 0;
    }
    return number_format($value, 1) . '%';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminder_event_id'])) {
    $reminderEventId = (int)$_POST['send_reminder_event_id'];
    $force = isset($_POST['force_send']) && (string)$_POST['force_send'] === '1';

    if ($reminderEventId <= 0) {
        $error = 'Please select a valid event to send reminders.';
    } else {
        $result = vs_event_send_event_reminders($pdo, $reminderEventId, $force);
        if (!empty($result['event_found'])) {
            $message = 'Reminder process completed. Sent: ' . (int)$result['sent'] . ', Skipped: ' . (int)$result['skipped'] . '.';
        } else {
            $error = 'Event not found for reminder.';
        }
    }
}

$events = $pdo->query("SELECT id, title, event_date, event_type, location, status FROM events ORDER BY event_date DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
$eventsById = [];
foreach ($events as &$eventRow) {
    $eventRow['event_date_display'] = vs_event_get_event_date_display(
        $pdo,
        (int)$eventRow['id'],
        (string)($eventRow['event_date'] ?? ''),
        (string)($eventRow['event_type'] ?? 'single_day')
    );
    $eventsById[(int)$eventRow['id']] = $eventRow;
}
unset($eventRow);

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$fromDate = $normalizeDate((string)($_GET['from_date'] ?? ''));
$toDate = $normalizeDate((string)($_GET['to_date'] ?? ''));

if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
    $tmp = $fromDate;
    $fromDate = $toDate;
    $toDate = $tmp;
}

$selectedEvent = null;
if ($eventId > 0) {
    $selectedEvent = $eventsById[$eventId] ?? null;
    if ($selectedEvent === null) {
        $error = 'Selected event not found.';
        $eventId = 0;
    }
}

$where = [];
$params = [];
if ($eventId > 0) {
    $where[] = 'r.event_id = ?';
    $params[] = $eventId;
}
if ($fromDate !== '') {
    $where[] = 'DATE(r.created_at) >= ?';
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $where[] = 'DATE(r.created_at) <= ?';
    $params[] = $toDate;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$summarySql = "SELECT
    COUNT(*) AS total_registrations,
    SUM(CASE WHEN LOWER(r.payment_status) = 'paid' THEN 1 ELSE 0 END) AS paid_registrations,
    SUM(CASE WHEN LOWER(r.payment_status) = 'partial paid' THEN 1 ELSE 0 END) AS partial_paid_registrations,
    SUM(CASE WHEN LOWER(r.payment_status) = 'unpaid' THEN 1 ELSE 0 END) AS unpaid_registrations,
    SUM(CASE WHEN LOWER(r.payment_status) = 'pending verification' THEN 1 ELSE 0 END) AS pending_verification,
    SUM(CASE WHEN LOWER(r.payment_status) = 'failed' THEN 1 ELSE 0 END) AS failed_registrations,
    SUM(CASE WHEN LOWER(r.payment_status) = 'cancelled' OR LOWER(r.verification_status) = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_registrations,
    COALESCE(SUM(CASE
        WHEN LOWER(r.payment_status) <> 'cancelled' AND LOWER(r.verification_status) <> 'cancelled'
        THEN COALESCE(r.persons, 0)
        ELSE 0
    END), 0) AS active_persons,
    COALESCE(SUM(COALESCE(ep.amount_paid, ep.amount, 0)), 0) AS total_collected
FROM event_registrations r
LEFT JOIN event_payments ep ON ep.registration_id = r.id
$whereSql";
$summaryStmt = $pdo->prepare($summarySql);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$reportSql = "SELECT
    e.id AS event_id,
    e.title AS event_title,
    e.event_type,
    e.event_date,
    p.id AS package_id,
    p.package_name,
    p.is_paid,
    p.price,
    p.price_total,
    p.seat_limit,
    COUNT(r.id) AS registrations,
    SUM(CASE WHEN LOWER(r.payment_status) = 'paid' THEN 1 ELSE 0 END) AS paid_count,
    SUM(CASE WHEN LOWER(r.payment_status) = 'partial paid' THEN 1 ELSE 0 END) AS partial_paid_count,
    SUM(CASE WHEN LOWER(r.payment_status) = 'unpaid' THEN 1 ELSE 0 END) AS unpaid_count,
    SUM(CASE WHEN LOWER(r.payment_status) = 'pending verification' THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN LOWER(r.payment_status) = 'failed' THEN 1 ELSE 0 END) AS failed_count,
    SUM(CASE WHEN LOWER(r.payment_status) = 'cancelled' OR LOWER(r.verification_status) = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
    COALESCE(SUM(CASE
        WHEN LOWER(r.payment_status) <> 'cancelled' AND LOWER(r.verification_status) <> 'cancelled'
        THEN COALESCE(r.persons, 0)
        ELSE 0
    END), 0) AS active_persons,
    COALESCE(SUM(COALESCE(ep.amount_paid, ep.amount, 0)), 0) AS collected_amount
FROM events e
INNER JOIN event_packages p ON p.event_id = e.id
LEFT JOIN event_registrations r ON r.package_id = p.id
LEFT JOIN event_payments ep ON ep.registration_id = r.id
";

$reportWhere = [];
$reportParams = [];
if ($eventId > 0) {
    $reportWhere[] = 'e.id = ?';
    $reportParams[] = $eventId;
}
if ($fromDate !== '') {
    $reportWhere[] = '(r.id IS NULL OR DATE(r.created_at) >= ?)';
    $reportParams[] = $fromDate;
}
if ($toDate !== '') {
    $reportWhere[] = '(r.id IS NULL OR DATE(r.created_at) <= ?)';
    $reportParams[] = $toDate;
}
if (!empty($reportWhere)) {
    $reportSql .= ' WHERE ' . implode(' AND ', $reportWhere);
}
$reportSql .= ' GROUP BY e.id, p.id ORDER BY e.event_date DESC, p.id DESC';

$reportStmt = $pdo->prepare($reportSql);
$reportStmt->execute($reportParams);
$reportRows = $reportStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($reportRows as &$row) {
    $row['event_date_display'] = vs_event_get_event_date_display(
        $pdo,
        (int)$row['event_id'],
        (string)($row['event_date'] ?? ''),
        (string)($row['event_type'] ?? 'single_day')
    );
}
unset($row);

$eventInsights = null;
$packageInsights = [];
$eventDateInsights = [];
$insightNotes = [];

if ($eventId > 0 && $selectedEvent !== null) {
    $packageStmt = $pdo->prepare("SELECT
            id,
            package_name,
            is_paid,
            price,
            price_total,
            payment_mode,
            advance_amount,
            seat_limit,
            status
        FROM event_packages
        WHERE event_id = ?
        ORDER BY CASE WHEN status = 'Active' THEN 0 ELSE 1 END, id DESC");
    $packageStmt->execute([$eventId]);
    $packageRows = $packageStmt->fetchAll(PDO::FETCH_ASSOC);

    $packageInsightsMap = [];
    foreach ($packageRows as $pkgRow) {
        $packagePrice = (float)($pkgRow['price_total'] ?? 0);
        if ($packagePrice <= 0) {
            $packagePrice = (float)($pkgRow['price'] ?? 0);
        }

        $pkgId = (int)$pkgRow['id'];
        $seatLimitRaw = (int)($pkgRow['seat_limit'] ?? 0);
        $packageInsightsMap[$pkgId] = [
            'id' => $pkgId,
            'package_name' => (string)($pkgRow['package_name'] ?? ''),
            'status' => (string)($pkgRow['status'] ?? 'Active'),
            'is_paid' => ((int)($pkgRow['is_paid'] ?? 1) === 1),
            'price_per_person' => max($packagePrice, 0),
            'seat_limit' => ($seatLimitRaw > 0 ? $seatLimitRaw : null),
            'total_registrations' => 0,
            'active_registrations' => 0,
            'cancelled_registrations' => 0,
            'paid_registrations' => 0,
            'partial_paid_registrations' => 0,
            'unpaid_registrations' => 0,
            'pending_verification' => 0,
            'failed_registrations' => 0,
            'other_status_registrations' => 0,
            'persons_total_snapshot' => 0,
            'active_persons' => 0,
            'cancelled_persons' => 0,
            'checked_in_registrations' => 0,
            'checked_in_persons' => 0,
            'pending_cancel_request_registrations' => 0,
            'collected_amount' => 0.0,
            'expected_amount' => 0.0,
            'due_amount' => 0.0,
            'seats_remaining' => null,
            'seats_overbooked' => 0,
            'occupancy_rate' => null,
            'collection_rate' => null,
        ];
    }

    $detailWhere = ['r.event_id = ?'];
    $detailParams = [$eventId];
    if ($fromDate !== '') {
        $detailWhere[] = 'DATE(r.created_at) >= ?';
        $detailParams[] = $fromDate;
    }
    if ($toDate !== '') {
        $detailWhere[] = 'DATE(r.created_at) <= ?';
        $detailParams[] = $toDate;
    }
    $detailWhereSql = 'WHERE ' . implode(' AND ', $detailWhere);

    $detailSql = "SELECT
            r.id,
            r.package_id,
            r.persons,
            r.payment_status,
            r.verification_status,
            r.checkin_status,
            r.created_at,
            COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price, 0) AS package_price_total,
            COALESCE(p.is_paid, 1) AS package_is_paid,
            COALESCE(ep.amount_paid, ep.amount, 0) AS amount_paid,
            COALESCE(ep.remaining_amount, 0) AS remaining_amount,
            COALESCE(c_tot.cancelled_persons_total, 0) AS cancelled_persons_total,
            COALESCE(c_tot.refund_amount_total, 0) AS refund_amount_total,
            COALESCE(c_req.pending_request_count, 0) AS pending_request_count
        FROM event_registrations r
        INNER JOIN event_packages p ON p.id = r.package_id
        LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
        LEFT JOIN event_payments ep ON ep.registration_id = r.id
        LEFT JOIN (
            SELECT registration_id,
                SUM(cancelled_persons) AS cancelled_persons_total,
                SUM(refund_amount) AS refund_amount_total
            FROM event_cancellations
            GROUP BY registration_id
        ) c_tot ON c_tot.registration_id = r.id
        LEFT JOIN (
            SELECT registration_id,
                COUNT(*) AS pending_request_count
            FROM event_cancellation_requests
            WHERE request_status = 'pending'
            GROUP BY registration_id
        ) c_req ON c_req.registration_id = r.id
        $detailWhereSql";

    $detailStmt = $pdo->prepare($detailSql);
    $detailStmt->execute($detailParams);
    $detailRows = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
    $cancelSummarySql = "SELECT
            COUNT(*) AS cancellation_records,
            COALESCE(SUM(c.cancelled_persons), 0) AS cancelled_persons_total,
            COALESCE(SUM(c.refund_amount), 0) AS refund_amount_total,
            SUM(CASE WHEN c.refund_status = 'pending' THEN 1 ELSE 0 END) AS refund_pending_count,
            COALESCE(SUM(CASE WHEN c.refund_status = 'pending' THEN c.refund_amount ELSE 0 END), 0) AS refund_pending_amount,
            SUM(CASE WHEN c.refund_status = 'processed' THEN 1 ELSE 0 END) AS refund_processed_count,
            COALESCE(SUM(CASE WHEN c.refund_status = 'processed' THEN c.refund_amount ELSE 0 END), 0) AS refund_processed_amount,
            SUM(CASE WHEN c.refund_status = 'rejected' THEN 1 ELSE 0 END) AS refund_rejected_count,
            COALESCE(SUM(CASE WHEN c.refund_status = 'rejected' THEN c.refund_amount ELSE 0 END), 0) AS refund_rejected_amount
        FROM event_cancellations c
        INNER JOIN event_registrations r ON r.id = c.registration_id
        $detailWhereSql";
    $cancelSummaryStmt = $pdo->prepare($cancelSummarySql);
    $cancelSummaryStmt->execute($detailParams);
    $cancelSummary = $cancelSummaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $pendingRequestSql = "SELECT
            COUNT(*) AS pending_request_count,
            COALESCE(SUM(cr.requested_persons), 0) AS pending_requested_persons
        FROM event_cancellation_requests cr
        INNER JOIN event_registrations r ON r.id = cr.registration_id
        $detailWhereSql
          AND cr.request_status = 'pending'";
    $pendingRequestStmt = $pdo->prepare($pendingRequestSql);
    $pendingRequestStmt->execute($detailParams);
    $pendingRequestSummary = $pendingRequestStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $eventInsights = [
        'total_bookings' => 0,
        'active_registrations' => 0,
        'cancelled_registrations' => 0,
        'paid_registrations' => 0,
        'partial_paid_registrations' => 0,
        'unpaid_registrations' => 0,
        'pending_verification' => 0,
        'failed_registrations' => 0,
        'other_status_registrations' => 0,
        'persons_active' => 0,
        'persons_cancelled' => 0,
        'persons_gross' => 0,
        'checked_in_registrations' => 0,
        'checked_in_persons' => 0,
        'gross_collected' => 0.0,
        'active_collected' => 0.0,
        'expected_active_revenue' => 0.0,
        'due_active' => 0.0,
        'limited_seat_capacity' => 0,
        'limited_seat_booked' => 0,
        'limited_seat_remaining' => 0,
        'limited_seat_overbooked' => 0,
        'limited_package_count' => 0,
        'unlimited_package_count' => 0,
        'pending_cancel_requests' => (int)($pendingRequestSummary['pending_request_count'] ?? 0),
        'pending_cancel_requested_persons' => (int)($pendingRequestSummary['pending_requested_persons'] ?? 0),
        'cancellation_records' => (int)($cancelSummary['cancellation_records'] ?? 0),
        'refund_total' => (float)($cancelSummary['refund_amount_total'] ?? 0),
        'refund_pending_count' => (int)($cancelSummary['refund_pending_count'] ?? 0),
        'refund_pending_amount' => (float)($cancelSummary['refund_pending_amount'] ?? 0),
        'refund_processed_count' => (int)($cancelSummary['refund_processed_count'] ?? 0),
        'refund_processed_amount' => (float)($cancelSummary['refund_processed_amount'] ?? 0),
        'refund_rejected_count' => (int)($cancelSummary['refund_rejected_count'] ?? 0),
        'refund_rejected_amount' => (float)($cancelSummary['refund_rejected_amount'] ?? 0),
        'avg_persons_per_booking' => 0.0,
        'payment_completion_rate' => 0.0,
        'payment_coverage_rate' => 0.0,
        'collection_efficiency' => 0.0,
        'limited_occupancy_rate' => 0.0,
        'checkin_rate' => 0.0,
    ];

    foreach ($detailRows as $regRow) {
        $eventInsights['total_bookings']++;

        $packageId = (int)($regRow['package_id'] ?? 0);
        if (!isset($packageInsightsMap[$packageId])) {
            $packageInsightsMap[$packageId] = [
                'id' => $packageId,
                'package_name' => 'Package #' . $packageId,
                'status' => 'Active',
                'is_paid' => true,
                'price_per_person' => max((float)($regRow['package_price_total'] ?? 0), 0),
                'seat_limit' => null,
                'total_registrations' => 0,
                'active_registrations' => 0,
                'cancelled_registrations' => 0,
                'paid_registrations' => 0,
                'partial_paid_registrations' => 0,
                'unpaid_registrations' => 0,
                'pending_verification' => 0,
                'failed_registrations' => 0,
                'other_status_registrations' => 0,
                'persons_total_snapshot' => 0,
                'active_persons' => 0,
                'cancelled_persons' => 0,
                'checked_in_registrations' => 0,
                'checked_in_persons' => 0,
                'pending_cancel_request_registrations' => 0,
                'collected_amount' => 0.0,
                'expected_amount' => 0.0,
                'due_amount' => 0.0,
                'seats_remaining' => null,
                'seats_overbooked' => 0,
                'occupancy_rate' => null,
                'collection_rate' => null,
            ];
        }

        $persons = max((int)($regRow['persons'] ?? 0), 0);
        $amountPaid = max((float)($regRow['amount_paid'] ?? 0), 0);
        $pricePerPerson = max((float)($regRow['package_price_total'] ?? 0), 0);
        $isPaidPackage = ((int)($regRow['package_is_paid'] ?? 1) === 1);

        $paymentStatusLower = strtolower(trim((string)($regRow['payment_status'] ?? '')));
        $verificationStatusLower = strtolower(trim((string)($regRow['verification_status'] ?? '')));
        $isCancelled = ($paymentStatusLower === 'cancelled' || $verificationStatusLower === 'cancelled');

        $eventInsights['gross_collected'] += $amountPaid;

        if ($isCancelled) {
            $eventInsights['cancelled_registrations']++;
            $packageInsightsMap[$packageId]['cancelled_registrations']++;
        } else {
            $eventInsights['active_registrations']++;
            $eventInsights['persons_active'] += $persons;
            $eventInsights['active_collected'] += $amountPaid;

            $packageInsightsMap[$packageId]['active_registrations']++;
            $packageInsightsMap[$packageId]['active_persons'] += $persons;

            if ($isPaidPackage) {
                $expectedForRow = round($pricePerPerson * $persons, 2);
                $dueForRow = round(max($expectedForRow - $amountPaid, 0), 2);

                $eventInsights['expected_active_revenue'] += $expectedForRow;
                $eventInsights['due_active'] += $dueForRow;

                $packageInsightsMap[$packageId]['expected_amount'] += $expectedForRow;
                $packageInsightsMap[$packageId]['due_amount'] += $dueForRow;
            }
        }

        if ((int)($regRow['checkin_status'] ?? 0) === 1) {
            $eventInsights['checked_in_registrations']++;
            $eventInsights['checked_in_persons'] += $persons;
            $packageInsightsMap[$packageId]['checked_in_registrations']++;
            $packageInsightsMap[$packageId]['checked_in_persons'] += $persons;
        }

        $packageInsightsMap[$packageId]['total_registrations']++;
        $packageInsightsMap[$packageId]['persons_total_snapshot'] += $persons;
        $packageInsightsMap[$packageId]['collected_amount'] += $amountPaid;
        $packageInsightsMap[$packageId]['cancelled_persons'] += max((int)($regRow['cancelled_persons_total'] ?? 0), 0);

        if ((int)($regRow['pending_request_count'] ?? 0) > 0) {
            $packageInsightsMap[$packageId]['pending_cancel_request_registrations']++;
        }

        switch ($paymentStatusLower) {
            case 'paid':
                $eventInsights['paid_registrations']++;
                $packageInsightsMap[$packageId]['paid_registrations']++;
                break;
            case 'partial paid':
                $eventInsights['partial_paid_registrations']++;
                $packageInsightsMap[$packageId]['partial_paid_registrations']++;
                break;
            case 'unpaid':
                $eventInsights['unpaid_registrations']++;
                $packageInsightsMap[$packageId]['unpaid_registrations']++;
                break;
            case 'pending verification':
                $eventInsights['pending_verification']++;
                $packageInsightsMap[$packageId]['pending_verification']++;
                break;
            case 'failed':
                $eventInsights['failed_registrations']++;
                $packageInsightsMap[$packageId]['failed_registrations']++;
                break;
            case 'cancelled':
                break;
            default:
                $eventInsights['other_status_registrations']++;
                $packageInsightsMap[$packageId]['other_status_registrations']++;
                break;
        }
    }

    $eventInsights['persons_cancelled'] = max((int)($cancelSummary['cancelled_persons_total'] ?? 0), 0);
    $eventInsights['persons_gross'] = $eventInsights['persons_active'] + $eventInsights['persons_cancelled'];

    foreach ($packageInsightsMap as &$pkgInsight) {
        $seatLimit = $pkgInsight['seat_limit'];
        if ($seatLimit !== null && $seatLimit > 0) {
            $eventInsights['limited_package_count']++;
            $eventInsights['limited_seat_capacity'] += $seatLimit;
            $eventInsights['limited_seat_booked'] += $pkgInsight['active_persons'];

            $pkgInsight['seats_remaining'] = max($seatLimit - $pkgInsight['active_persons'], 0);
            $pkgInsight['seats_overbooked'] = max($pkgInsight['active_persons'] - $seatLimit, 0);
            $pkgInsight['occupancy_rate'] = ($seatLimit > 0) ? (($pkgInsight['active_persons'] / $seatLimit) * 100) : null;

            $eventInsights['limited_seat_remaining'] += $pkgInsight['seats_remaining'];
            $eventInsights['limited_seat_overbooked'] += $pkgInsight['seats_overbooked'];
        } else {
            $eventInsights['unlimited_package_count']++;
        }

        if ($pkgInsight['expected_amount'] > 0) {
            $pkgInsight['collection_rate'] = ($pkgInsight['collected_amount'] / $pkgInsight['expected_amount']) * 100;
        }
    }
    unset($pkgInsight);

    if ($eventInsights['total_bookings'] > 0) {
        $eventInsights['avg_persons_per_booking'] = $eventInsights['persons_gross'] / $eventInsights['total_bookings'];
    }
    if ($eventInsights['active_registrations'] > 0) {
        $eventInsights['payment_completion_rate'] = ($eventInsights['paid_registrations'] / $eventInsights['active_registrations']) * 100;
        $eventInsights['payment_coverage_rate'] = (($eventInsights['paid_registrations'] + $eventInsights['partial_paid_registrations']) / $eventInsights['active_registrations']) * 100;
        $eventInsights['checkin_rate'] = ($eventInsights['checked_in_registrations'] / $eventInsights['active_registrations']) * 100;
    }
    if ($eventInsights['expected_active_revenue'] > 0) {
        $eventInsights['collection_efficiency'] = ($eventInsights['active_collected'] / $eventInsights['expected_active_revenue']) * 100;
    }
    if ($eventInsights['limited_seat_capacity'] > 0) {
        $eventInsights['limited_occupancy_rate'] = ($eventInsights['limited_seat_booked'] / $eventInsights['limited_seat_capacity']) * 100;
    }

    $insightNotes = [];
    if ($eventInsights['total_bookings'] <= 0) {
        $insightNotes[] = 'No bookings found for the selected filters.';
    }
    if ($eventInsights['pending_cancel_requests'] > 0) {
        $insightNotes[] = $eventInsights['pending_cancel_requests'] . ' cancellation request(s) pending review for ' . $eventInsights['pending_cancel_requested_persons'] . ' person(s).';
    }
    if ($eventInsights['refund_pending_count'] > 0) {
        $insightNotes[] = $eventInsights['refund_pending_count'] . ' refund(s) are pending, amounting to Rs ' . $formatMoney($eventInsights['refund_pending_amount']) . '.';
    }
    if ($eventInsights['due_active'] > 0) {
        $insightNotes[] = 'Outstanding dues across active paid bookings: Rs ' . $formatMoney($eventInsights['due_active']) . '.';
    }
    if ($eventInsights['pending_verification'] > 0) {
        $insightNotes[] = $eventInsights['pending_verification'] . ' booking(s) are awaiting payment verification.';
    }
    if ($eventInsights['failed_registrations'] > 0) {
        $insightNotes[] = $eventInsights['failed_registrations'] . ' booking(s) are in failed payment status and may need follow-up.';
    }
    if ($eventInsights['limited_seat_overbooked'] > 0) {
        $insightNotes[] = 'Limited-seat packages are overbooked by ' . $eventInsights['limited_seat_overbooked'] . ' seat(s).';
    } elseif ($eventInsights['limited_seat_capacity'] > 0 && $eventInsights['limited_occupancy_rate'] >= 90) {
        $insightNotes[] = 'Limited-seat packages are above 90% occupancy.';
    }

    $packageInsights = array_values($packageInsightsMap);
    usort($packageInsights, static function (array $a, array $b): int {
        if ($a['active_persons'] === $b['active_persons']) {
            if ($a['total_registrations'] === $b['total_registrations']) {
                return $b['id'] <=> $a['id'];
            }
            return $b['total_registrations'] <=> $a['total_registrations'];
        }
        return $b['active_persons'] <=> $a['active_persons'];
    });

    $eventDateInsights = vs_event_fetch_event_dates_with_booked_seats($pdo, $eventId);
    foreach ($eventDateInsights as &$dateRow) {
        $seatLimitRaw = (int)($dateRow['seat_limit'] ?? 0);
        $bookedSeats = (int)($dateRow['booked_seats'] ?? 0);
        if ($seatLimitRaw > 0) {
            $dateRow['seats_left'] = max($seatLimitRaw - $bookedSeats, 0);
            $dateRow['occupancy_rate'] = ($bookedSeats / $seatLimitRaw) * 100;
            $dateRow['overbooked'] = max($bookedSeats - $seatLimitRaw, 0);
        } else {
            $dateRow['seats_left'] = null;
            $dateRow['occupancy_rate'] = null;
            $dateRow['overbooked'] = 0;
        }
    }
    unset($dateRow);
}

$exportQuery = $_GET;
$exportUrl = 'export-registrations.php';
if (!empty($exportQuery)) {
    $exportUrl .= '?' . http_build_query($exportQuery);
}
$pdfUrl = 'export-pdf.php';
if (!empty($exportQuery)) {
    $pdfUrl .= '?' . http_build_query($exportQuery);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Reports</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f7f7fa; margin:0; }
        .admin-container { max-width:1500px; margin:0 auto; padding:24px 12px; }
        h1 { color:#800000; margin-bottom:14px; }
        .notice { margin:10px 0; padding:10px 12px; border-radius:8px; font-weight:600; }
        .notice.ok { background:#e7f7ed; color:#1a8917; }
        .notice.err { background:#ffeaea; color:#b00020; }
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 12px #e0bebe22; padding:14px; margin-bottom:16px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:10px; align-items:end; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        label { color:#800000; font-weight:700; font-size:0.9em; }
        select, input[type="date"] { width:100%; box-sizing:border-box; padding:9px 10px; border:1px solid #e0bebe; border-radius:8px; font-size:0.94em; }
        .btn-main { display:inline-block; border:none; border-radius:8px; padding:9px 12px; font-weight:700; cursor:pointer; text-decoration:none; background:#800000; color:#fff; }
        .btn-alt { background:#0b7285; }
        .btn-warning { background:#e67700; }

        .event-head { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start; }
        .event-name { font-size:1.25em; color:#3a1f1f; font-weight:800; margin-bottom:2px; }
        .meta-line { color:#5f5f5f; font-size:0.9em; }
        .quick-links { display:flex; gap:8px; flex-wrap:wrap; }
        .quick-link { display:inline-block; background:#f1f3f5; color:#444; border-radius:8px; padding:6px 10px; text-decoration:none; font-size:0.84em; font-weight:700; }
        .quick-link:hover { background:#e9ecef; }

        .insight-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:10px; }
        .insight-card { border:1px solid #f1d6d6; border-radius:10px; padding:10px; background:#fffbe9; }
        .insight-card.alert { background:#fff1f1; border-color:#f3b5bd; }
        .insight-card.good { background:#edfaef; border-color:#b6e4bf; }
        .insight-title { color:#7c3a3a; font-weight:700; font-size:0.82em; text-transform:uppercase; letter-spacing:0.02em; }
        .insight-value { font-size:1.35em; font-weight:800; color:#222; margin-top:4px; line-height:1.2; }
        .insight-note { margin-top:4px; color:#666; font-size:0.82em; }

        .insight-list { margin:0; padding-left:18px; }
        .insight-list li { margin:4px 0; color:#5e2a2a; }

        .list-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px #e0bebe22; table-layout:auto; font-size:0.84em; }
        .list-table th, .list-table td { padding:8px 6px; border-bottom:1px solid #f3caca; text-align:left; vertical-align:top; }
        .list-table th { background:#f9eaea; color:#800000; font-size:0.88em; white-space:nowrap; }

        .status-chip { display:inline-block; padding:3px 8px; border-radius:12px; font-weight:700; font-size:0.78em; }
        .status-active { background:#e7f7ed; color:#1a8917; }
        .status-inactive { background:#f1f3f5; color:#555; }
        .small { color:#666; font-size:0.83em; }
        .txt-danger { color:#b00020; font-weight:700; }
        .txt-warning { color:#b36b00; font-weight:700; }
        .txt-ok { color:#1a8917; font-weight:700; }

        @media (max-width:900px) {
            .admin-container { padding:16px 8px; }
            .list-table { font-size:0.8em; }
            .insight-value { font-size:1.2em; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <h1>Event Reports</h1>

    <?php if ($message !== ''): ?><div class="notice ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card">
        <form method="get" class="grid">
            <div class="form-group">
                <label>Event</label>
                <select name="event_id">
                    <option value="">All Events</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo (int)$event['id']; ?>" <?php echo ($eventId === (int)$event['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($event['title'] . ' (' . ($event['event_date_display'] ?? $event['event_date']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>From Date (Booking)</label>
                <input type="date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
            </div>
            <div class="form-group">
                <label>To Date (Booking)</label>
                <input type="date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
            </div>
            <div class="form-group"><button class="btn-main" type="submit">Apply</button></div>
            <div class="form-group"><a class="btn-main btn-alt" href="<?php echo htmlspecialchars($exportUrl); ?>">Export To Excel</a></div>
            <div class="form-group"><a class="btn-main btn-alt" href="<?php echo htmlspecialchars($pdfUrl); ?>" target="_blank">Export PDF</a></div>
        </form>
    </div>

    <?php if ($eventInsights !== null && $selectedEvent !== null): ?>
        <div class="card">
            <div class="event-head">
                <div>
                    <div class="event-name"><?php echo htmlspecialchars((string)($selectedEvent['title'] ?? '')); ?></div>
                    <div class="meta-line">
                        Date: <?php echo htmlspecialchars((string)($selectedEvent['event_date_display'] ?? $selectedEvent['event_date'] ?? '')); ?> |
                        Type: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($selectedEvent['event_type'] ?? 'single_day')))); ?> |
                        Status: <?php echo htmlspecialchars((string)($selectedEvent['status'] ?? 'Active')); ?>
                    </div>
                    <div class="meta-line">Location: <?php echo htmlspecialchars((string)($selectedEvent['location'] ?? '-')); ?></div>
                    <?php if ($fromDate !== '' || $toDate !== ''): ?>
                        <div class="meta-line">Report Window (booking date): <?php echo htmlspecialchars($fromDate !== '' ? $fromDate : 'Start'); ?> to <?php echo htmlspecialchars($toDate !== '' ? $toDate : 'Now'); ?></div>
                    <?php endif; ?>
                </div>
                <div class="quick-links">
                    <a class="quick-link" href="registrations.php?event_id=<?php echo (int)$eventId; ?>">View Registrations</a>
                    <a class="quick-link" href="cancellations.php?event_id=<?php echo (int)$eventId; ?>">View Cancellations</a>
                    <a class="quick-link" href="event-packages.php?event_id=<?php echo (int)$eventId; ?>">Manage Packages</a>
                    <a class="quick-link" href="pending-payments.php?event_id=<?php echo (int)$eventId; ?>">Pending Payments</a>
                </div>
            </div>
        </div>

        <div class="card">
            <h3 style="margin:0 0 10px; color:#800000;">Bookings, Persons, Payment & Capacity Overview</h3>
            <div class="insight-grid">
                <div class="insight-card">
                    <div class="insight-title">Total Bookings</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['total_bookings']; ?></div>
                    <div class="insight-note">All registrations in selected window</div>
                </div>
                <div class="insight-card good">
                    <div class="insight-title">Active Registrations</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['active_registrations']; ?></div>
                    <div class="insight-note">Not cancelled</div>
                </div>
                <div class="insight-card <?php echo ((int)$eventInsights['cancelled_registrations'] > 0) ? 'alert' : ''; ?>">
                    <div class="insight-title">Cancelled Registrations</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['cancelled_registrations']; ?></div>
                    <div class="insight-note">Full-cancelled bookings</div>
                </div>
                <div class="insight-card">
                    <div class="insight-title">Persons Booked (Gross)</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['persons_gross']; ?></div>
                    <div class="insight-note">Active + cancelled persons</div>
                </div>
                <div class="insight-card">
                    <div class="insight-title">Persons Active</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['persons_active']; ?></div>
                    <div class="insight-note">Current seats occupied</div>
                </div>
                <div class="insight-card <?php echo ((int)$eventInsights['persons_cancelled'] > 0) ? 'alert' : ''; ?>">
                    <div class="insight-title">Persons Cancelled</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['persons_cancelled']; ?></div>
                    <div class="insight-note">From cancellation records</div>
                </div>
                <div class="insight-card <?php echo ((int)$eventInsights['pending_cancel_requests'] > 0) ? 'alert' : ''; ?>">
                    <div class="insight-title">Pending Cancel Requests</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['pending_cancel_requests']; ?></div>
                    <div class="insight-note">Requested persons: <?php echo (int)$eventInsights['pending_cancel_requested_persons']; ?></div>
                </div>
                <div class="insight-card">
                    <div class="insight-title">Average Group Size</div>
                    <div class="insight-value"><?php echo number_format((float)$eventInsights['avg_persons_per_booking'], 2); ?></div>
                    <div class="insight-note">Persons per booking</div>
                </div>

                <div class="insight-card good">
                    <div class="insight-title">Paid</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['paid_registrations']; ?></div>
                    <div class="insight-note">Completion: <?php echo htmlspecialchars($formatPercent((float)$eventInsights['payment_completion_rate'])); ?></div>
                </div>
                <div class="insight-card">
                    <div class="insight-title">Partial Paid</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['partial_paid_registrations']; ?></div>
                    <div class="insight-note">Coverage: <?php echo htmlspecialchars($formatPercent((float)$eventInsights['payment_coverage_rate'])); ?></div>
                </div>
                <div class="insight-card <?php echo ((int)$eventInsights['unpaid_registrations'] > 0) ? 'alert' : ''; ?>">
                    <div class="insight-title">Unpaid</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['unpaid_registrations']; ?></div>
                    <div class="insight-note">Payment pending</div>
                </div>
                <div class="insight-card <?php echo ((int)$eventInsights['pending_verification'] > 0) ? 'alert' : ''; ?>">
                    <div class="insight-title">Pending Verification</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['pending_verification']; ?></div>
                    <div class="insight-note">Needs admin review</div>
                </div>
                <div class="insight-card <?php echo ((int)$eventInsights['failed_registrations'] > 0) ? 'alert' : ''; ?>">
                    <div class="insight-title">Failed</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['failed_registrations']; ?></div>
                    <div class="insight-note">Retry / follow-up needed</div>
                </div>
                <div class="insight-card">
                    <div class="insight-title">Gross Collected</div>
                    <div class="insight-value">Rs <?php echo htmlspecialchars($formatMoney((float)$eventInsights['gross_collected'])); ?></div>
                    <div class="insight-note">Including cancelled bookings</div>
                </div>
                <div class="insight-card">
                    <div class="insight-title">Expected (Active Paid)</div>
                    <div class="insight-value">Rs <?php echo htmlspecialchars($formatMoney((float)$eventInsights['expected_active_revenue'])); ?></div>
                    <div class="insight-note">Current payable on active seats</div>
                </div>
                <div class="insight-card <?php echo ((float)$eventInsights['due_active'] > 0) ? 'alert' : 'good'; ?>">
                    <div class="insight-title">Pending Due</div>
                    <div class="insight-value">Rs <?php echo htmlspecialchars($formatMoney((float)$eventInsights['due_active'])); ?></div>
                    <div class="insight-note">Collection efficiency: <?php echo htmlspecialchars($formatPercent((float)$eventInsights['collection_efficiency'])); ?></div>
                </div>

                <div class="insight-card">
                    <div class="insight-title">Limited Seat Capacity</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['limited_seat_capacity']; ?></div>
                    <div class="insight-note">Packages with finite seats: <?php echo (int)$eventInsights['limited_package_count']; ?></div>
                </div>
                <div class="insight-card">
                    <div class="insight-title">Limited Seats Remaining</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['limited_seat_remaining']; ?></div>
                    <div class="insight-note">Occupancy: <?php echo htmlspecialchars($formatPercent((float)$eventInsights['limited_occupancy_rate'])); ?></div>
                </div>
                <div class="insight-card <?php echo ((int)$eventInsights['limited_seat_overbooked'] > 0) ? 'alert' : ''; ?>">
                    <div class="insight-title">Overbooked Seats</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['limited_seat_overbooked']; ?></div>
                    <div class="insight-note">Unlimited packages: <?php echo (int)$eventInsights['unlimited_package_count']; ?></div>
                </div>
                <div class="insight-card">
                    <div class="insight-title">Check-In Progress</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['checked_in_registrations']; ?></div>
                    <div class="insight-note"><?php echo (int)$eventInsights['checked_in_persons']; ?> persons | <?php echo htmlspecialchars($formatPercent((float)$eventInsights['checkin_rate'])); ?> of active bookings</div>
                </div>

                <div class="insight-card <?php echo ((int)$eventInsights['refund_pending_count'] > 0) ? 'alert' : ''; ?>">
                    <div class="insight-title">Refund Pending</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['refund_pending_count']; ?></div>
                    <div class="insight-note">Rs <?php echo htmlspecialchars($formatMoney((float)$eventInsights['refund_pending_amount'])); ?></div>
                </div>
                <div class="insight-card good">
                    <div class="insight-title">Refund Processed</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['refund_processed_count']; ?></div>
                    <div class="insight-note">Rs <?php echo htmlspecialchars($formatMoney((float)$eventInsights['refund_processed_amount'])); ?></div>
                </div>
                <div class="insight-card">
                    <div class="insight-title">Refund Rejected</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['refund_rejected_count']; ?></div>
                    <div class="insight-note">Rs <?php echo htmlspecialchars($formatMoney((float)$eventInsights['refund_rejected_amount'])); ?></div>
                </div>
                <div class="insight-card">
                    <div class="insight-title">Cancellation Records</div>
                    <div class="insight-value"><?php echo (int)$eventInsights['cancellation_records']; ?></div>
                    <div class="insight-note">Refund total: Rs <?php echo htmlspecialchars($formatMoney((float)$eventInsights['refund_total'])); ?></div>
                </div>
            </div>
        </div>

        <div class="card">
            <h3 style="margin:0 0 10px; color:#800000;">Actionable Insights</h3>
            <?php if (empty($insightNotes)): ?>
                <p class="small" style="margin:0;">No critical alerts right now. Event metrics look stable for the selected period.</p>
            <?php else: ?>
                <ul class="insight-list">
                    <?php foreach ($insightNotes as $note): ?>
                        <li><?php echo htmlspecialchars($note); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="margin-top:0; color:#800000;">Package-Wise Detailed Metrics</h3>
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Package</th>
                        <th>Status</th>
                        <th>Price / Seat</th>
                        <th>Seat Limit</th>
                        <th>Active Persons</th>
                        <th>Seat Remaining</th>
                        <th>Registrations</th>
                        <th>Payment Split</th>
                        <th>Cancellations</th>
                        <th>Pending Cancel Req</th>
                        <th>Collected</th>
                        <th>Expected</th>
                        <th>Due</th>
                        <th>Check-In</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($packageInsights)): ?>
                    <tr><td colspan="14" style="text-align:center; padding:18px; color:#666;">No package data found for this event.</td></tr>
                <?php else: ?>
                    <?php foreach ($packageInsights as $pkg): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars((string)$pkg['package_name']); ?></strong><br>
                                <span class="small"><?php echo $pkg['is_paid'] ? 'Paid Package' : 'Free Package'; ?></span>
                            </td>
                            <td>
                                <span class="status-chip <?php echo ((string)$pkg['status'] === 'Active') ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo htmlspecialchars((string)$pkg['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $pkg['is_paid'] ? ('Rs ' . htmlspecialchars($formatMoney((float)$pkg['price_per_person']))) : 'Free'; ?></td>
                            <td><?php echo ($pkg['seat_limit'] !== null) ? (int)$pkg['seat_limit'] : 'Unlimited'; ?></td>
                            <td><?php echo (int)$pkg['active_persons']; ?></td>
                            <td>
                                <?php if ($pkg['seat_limit'] === null): ?>
                                    Unlimited
                                <?php else: ?>
                                    <?php echo (int)$pkg['seats_remaining']; ?>
                                    <?php if ((int)$pkg['seats_overbooked'] > 0): ?><br><span class="txt-danger">Over by <?php echo (int)$pkg['seats_overbooked']; ?></span><?php endif; ?>
                                    <?php if ($pkg['occupancy_rate'] !== null): ?><br><span class="small">Occ: <?php echo htmlspecialchars($formatPercent((float)$pkg['occupancy_rate'])); ?></span><?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                Total: <?php echo (int)$pkg['total_registrations']; ?><br>
                                <span class="small">Active: <?php echo (int)$pkg['active_registrations']; ?> | Cancelled: <?php echo (int)$pkg['cancelled_registrations']; ?></span>
                            </td>
                            <td>
                                <span class="small">Paid: <?php echo (int)$pkg['paid_registrations']; ?></span><br>
                                <span class="small">Partial: <?php echo (int)$pkg['partial_paid_registrations']; ?></span><br>
                                <span class="small">Unpaid: <?php echo (int)$pkg['unpaid_registrations']; ?></span><br>
                                <span class="small">Pending Ver.: <?php echo (int)$pkg['pending_verification']; ?></span><br>
                                <span class="small">Failed: <?php echo (int)$pkg['failed_registrations']; ?></span>
                            </td>
                            <td>
                                Persons: <?php echo (int)$pkg['cancelled_persons']; ?><br>
                                <span class="small">Cancelled regs: <?php echo (int)$pkg['cancelled_registrations']; ?></span>
                            </td>
                            <td><?php echo (int)$pkg['pending_cancel_request_registrations']; ?></td>
                            <td>Rs <?php echo htmlspecialchars($formatMoney((float)$pkg['collected_amount'])); ?></td>
                            <td><?php echo $pkg['is_paid'] ? ('Rs ' . htmlspecialchars($formatMoney((float)$pkg['expected_amount']))) : '-'; ?></td>
                            <td>
                                <?php if ($pkg['is_paid']): ?>
                                    Rs <?php echo htmlspecialchars($formatMoney((float)$pkg['due_amount'])); ?>
                                    <?php if ($pkg['collection_rate'] !== null): ?><br><span class="small">Collection: <?php echo htmlspecialchars($formatPercent((float)$pkg['collection_rate'])); ?></span><?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo (int)$pkg['checked_in_registrations']; ?> reg<br>
                                <span class="small"><?php echo (int)$pkg['checked_in_persons']; ?> persons</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($eventDateInsights)): ?>
            <div class="card">
                <h3 style="margin-top:0; color:#800000;">Event Date Seat Occupancy</h3>
                <table class="list-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Date Status</th>
                            <th>Seat Limit</th>
                            <th>Booked Persons</th>
                            <th>Seat Remaining</th>
                            <th>Occupancy</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($eventDateInsights as $dateRow): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)($dateRow['event_date'] ?? '-')); ?></td>
                            <td>
                                <span class="status-chip <?php echo ((string)($dateRow['status'] ?? 'Active') === 'Active') ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo htmlspecialchars((string)($dateRow['status'] ?? 'Active')); ?>
                                </span>
                            </td>
                            <td><?php echo ((int)($dateRow['seat_limit'] ?? 0) > 0) ? (int)$dateRow['seat_limit'] : 'Unlimited'; ?></td>
                            <td><?php echo (int)($dateRow['booked_seats'] ?? 0); ?></td>
                            <td>
                                <?php if ((int)($dateRow['seat_limit'] ?? 0) > 0): ?>
                                    <?php echo (int)($dateRow['seats_left'] ?? 0); ?>
                                    <?php if ((int)($dateRow['overbooked'] ?? 0) > 0): ?><br><span class="txt-danger">Over by <?php echo (int)$dateRow['overbooked']; ?></span><?php endif; ?>
                                <?php else: ?>
                                    Unlimited
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($dateRow['occupancy_rate'] !== null): ?>
                                    <?php echo htmlspecialchars($formatPercent((float)$dateRow['occupancy_rate'])); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="card">
            <h3 style="margin:0 0 10px; color:#800000;">Overall Snapshot</h3>
            <div class="insight-grid">
                <div class="insight-card">
                    <div class="insight-title">Total Registrations</div>
                    <div class="insight-value"><?php echo (int)($summary['total_registrations'] ?? 0); ?></div>
                </div>
                <div class="insight-card good">
                    <div class="insight-title">Paid</div>
                    <div class="insight-value"><?php echo (int)($summary['paid_registrations'] ?? 0); ?></div>
                </div>
                <div class="insight-card">
                    <div class="insight-title">Partial Paid</div>
                    <div class="insight-value"><?php echo (int)($summary['partial_paid_registrations'] ?? 0); ?></div>
                </div>
                <div class="insight-card">
                    <div class="insight-title">Unpaid</div>
                    <div class="insight-value"><?php echo (int)($summary['unpaid_registrations'] ?? 0); ?></div>
                </div>
                <div class="insight-card <?php echo ((int)($summary['pending_verification'] ?? 0) > 0) ? 'alert' : ''; ?>">
                    <div class="insight-title">Pending Verification</div>
                    <div class="insight-value"><?php echo (int)($summary['pending_verification'] ?? 0); ?></div>
                </div>
                <div class="insight-card <?php echo ((int)($summary['failed_registrations'] ?? 0) > 0) ? 'alert' : ''; ?>">
                    <div class="insight-title">Failed</div>
                    <div class="insight-value"><?php echo (int)($summary['failed_registrations'] ?? 0); ?></div>
                </div>
                <div class="insight-card <?php echo ((int)($summary['cancelled_registrations'] ?? 0) > 0) ? 'alert' : ''; ?>">
                    <div class="insight-title">Cancelled</div>
                    <div class="insight-value"><?php echo (int)($summary['cancelled_registrations'] ?? 0); ?></div>
                </div>
                <div class="insight-card">
                    <div class="insight-title">Active Persons</div>
                    <div class="insight-value"><?php echo (int)($summary['active_persons'] ?? 0); ?></div>
                </div>
                <div class="insight-card">
                    <div class="insight-title">Collected Amount</div>
                    <div class="insight-value">Rs <?php echo htmlspecialchars($formatMoney((float)($summary['total_collected'] ?? 0))); ?></div>
                </div>
            </div>
            <p class="small" style="margin:10px 0 0;">Select a specific event above to view the full one-screen in-depth report with package-level seats, cancellation persons, payment split and actionable insights.</p>
        </div>

        <div class="card">
            <h3 style="margin-top:0; color:#800000;">Event / Package Snapshot</h3>
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Package</th>
                        <th>Price</th>
                        <th>Seat Limit</th>
                        <th>Registrations</th>
                        <th>Active Persons</th>
                        <th>Paid</th>
                        <th>Partial</th>
                        <th>Unpaid</th>
                        <th>Pending</th>
                        <th>Failed</th>
                        <th>Cancelled</th>
                        <th>Collected</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($reportRows)): ?>
                    <tr><td colspan="13" style="text-align:center; padding:20px; color:#666;">No report rows found for selected filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($reportRows as $row): ?>
                        <?php
                        $displayPrice = (float)($row['price_total'] ?? 0);
                        if ($displayPrice <= 0) {
                            $displayPrice = (float)($row['price'] ?? 0);
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars((string)$row['event_title']); ?></strong><br>
                                <span class="small"><?php echo htmlspecialchars((string)($row['event_date_display'] ?? $row['event_date'])); ?></span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars((string)$row['package_name']); ?><br>
                                <span class="small"><?php echo ((int)($row['is_paid'] ?? 1) === 1) ? 'Paid' : 'Free'; ?></span>
                            </td>
                            <td><?php echo ((int)($row['is_paid'] ?? 1) === 1) ? ('Rs ' . htmlspecialchars($formatMoney($displayPrice))) : 'Free'; ?></td>
                            <td><?php echo ($row['seat_limit'] !== null && $row['seat_limit'] !== '') ? (int)$row['seat_limit'] : 'Unlimited'; ?></td>
                            <td><?php echo (int)$row['registrations']; ?></td>
                            <td><?php echo (int)$row['active_persons']; ?></td>
                            <td><?php echo (int)$row['paid_count']; ?></td>
                            <td><?php echo (int)$row['partial_paid_count']; ?></td>
                            <td><?php echo (int)$row['unpaid_count']; ?></td>
                            <td><?php echo (int)$row['pending_count']; ?></td>
                            <td><?php echo (int)$row['failed_count']; ?></td>
                            <td><?php echo (int)$row['cancelled_count']; ?></td>
                            <td>Rs <?php echo htmlspecialchars($formatMoney((float)$row['collected_amount'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3 style="margin-top:0; color:#800000;">Event Reminder</h3>
        <p class="small">Sends WhatsApp reminders to paid and approved registrations of the selected event.</p>
        <form method="post" class="grid">
            <div class="form-group">
                <label>Event</label>
                <select name="send_reminder_event_id" required>
                    <option value="">-- Select Event --</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo (int)$event['id']; ?>" <?php echo ($eventId === (int)$event['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($event['title'] . ' (' . ($event['event_date_display'] ?? $event['event_date']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="justify-content:center;">
                <label><input type="checkbox" name="force_send" value="1"> Force resend (ignore already-sent markers)</label>
            </div>
            <div class="form-group">
                <button type="submit" class="btn-main btn-warning">Send Event Reminder</button>
            </div>
        </form>
    </div>
</div>

<script src="../includes/responsive-tables.js"></script>
</body>
</html>
