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
require_once __DIR__ . '/export-report-helper.php';

vs_event_ensure_tables($pdo);

$filters = vs_event_export_collect_filters($_GET);
$report = vs_event_export_fetch_dataset($pdo, $filters);
$rows = $report['rows'];
$summary = $report['summary'];
$selectedEvent = $report['selected_event'];
$filterChips = $report['filter_chips'];
$generatedAt = $report['generated_at'];
$autoPrint = isset($_GET['autoprint']) && (string)$_GET['autoprint'] === '1';

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

$exportQuery = [];
foreach (['event_id', 'payment_status', 'verification_status', 'from_date', 'to_date'] as $key) {
    if (($filters[$key] ?? '') !== '' && (string)$filters[$key] !== '0') {
        $exportQuery[$key] = $filters[$key];
    }
}

$excelUrl = 'export-registrations.php';
if (!empty($exportQuery)) {
    $excelUrl .= '?' . http_build_query($exportQuery);
}

$formatMoney = static function (float $amount): string {
    return number_format($amount, 2, '.', ',');
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Report PDF Export</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="export-actions.css">
    <link rel="stylesheet" href="export-pdf-report.css">
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="page-shell">
    <section class="report-hero no-print">
        <div class="hero-copy">
            <p class="eyebrow">Event Reports</p>
            <h1>PDF / Print Export</h1>
            <p>Prepared as a print-friendly event report with filter context, payment summary, and registration details ready for save-as-PDF.</p>
        </div>
        <div class="hero-meta">
            <div class="hero-meta-title">Rows in Report</div>
            <div class="hero-meta-value"><?php echo count($rows); ?></div>
            <div class="hero-meta-note">Generated on <?php echo htmlspecialchars($generatedAt); ?></div>
        </div>
    </section>

    <div class="card no-print">
        <form method="get" class="filter-grid">
            <div class="form-group">
                <label>Event</label>
                <select name="event_id">
                    <option value="0">All Events</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo (int)$event['id']; ?>" <?php echo ($filters['event_id'] === (int)$event['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$event['title'] . ' (' . (string)($event['event_date_display'] ?? $event['event_date']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Payment Status</label>
                <select name="payment_status">
                    <option value="">All</option>
                    <?php foreach (['Unpaid', 'Partial Paid', 'Pending Verification', 'Paid', 'Waitlisted', 'Failed', 'Cancelled'] as $statusOption): ?>
                        <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo ($filters['payment_status'] === $statusOption) ? 'selected' : ''; ?>><?php echo htmlspecialchars($statusOption); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Verification Status</label>
                <select name="verification_status">
                    <option value="">All</option>
                    <?php foreach (['Pending', 'Approved', 'Auto Verified', 'Waitlisted', 'Rejected', 'Cancelled'] as $statusOption): ?>
                        <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo ($filters['verification_status'] === $statusOption) ? 'selected' : ''; ?>><?php echo htmlspecialchars($statusOption); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>From Date (Booking)</label>
                <input type="date" name="from_date" value="<?php echo htmlspecialchars($filters['from_date']); ?>">
            </div>
            <div class="form-group">
                <label>To Date (Booking)</label>
                <input type="date" name="to_date" value="<?php echo htmlspecialchars($filters['to_date']); ?>">
            </div>

            <div class="report-filter-actions">
                <button type="submit" class="report-action report-action--filter">
                    <span class="report-action__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M3 5h18l-7 8v5l-4 2v-7L3 5z"/></svg>
                    </span>
                    <span class="report-action__body">
                        <span class="report-action__title">Refresh Report</span>
                        <span class="report-action__meta">Apply the current filters</span>
                    </span>
                </button>
                <a class="report-action report-action--excel" href="<?php echo htmlspecialchars($excelUrl); ?>">
                    <span class="report-action__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zm0 2.5L17.5 9H14zM8.8 16.9 10.9 13l-2-3.9h1.9l1.1 2.5 1.1-2.5H15l-2 3.8 2.1 4H13l-1.2-2.6-1.2 2.6z"/></svg>
                    </span>
                    <span class="report-action__body">
                        <span class="report-action__title">Export Excel</span>
                        <span class="report-action__meta">Styled sheet with separate data columns</span>
                    </span>
                </a>
                <button type="button" class="report-action report-action--pdf" onclick="window.print()">
                    <span class="report-action__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M7 3h10v4H7zm11 6a3 3 0 0 1 3 3v4h-3v5H6v-5H3v-4a3 3 0 0 1 3-3zm-2 10v-5H8v5zm2-5h1v-2a1 1 0 0 0-1-1H6a1 1 0 0 0-1 1v2h1v-2h12z"/></svg>
                    </span>
                    <span class="report-action__body">
                        <span class="report-action__title">Print / Save PDF</span>
                        <span class="report-action__meta">Open the browser PDF dialog</span>
                    </span>
                </button>
            </div>
        </form>
    </div>

    <article class="print-sheet">
        <div class="sheet-header">
            <div>
                <h1 class="sheet-title">Event Registrations Report</h1>
                <p class="sheet-subtitle">Dedicated printable report for registrations, payment visibility, and custom event form data.</p>
            </div>
            <div class="sheet-meta">
                <div class="meta-label">Generated</div>
                <div class="meta-value"><?php echo htmlspecialchars($generatedAt); ?></div>
            </div>
        </div>

        <div class="event-banner">
            <?php if ($selectedEvent !== null): ?>
                <h2 class="event-banner-title"><?php echo htmlspecialchars((string)($selectedEvent['title'] ?? 'Selected Event')); ?></h2>
                <div class="event-banner-grid">
                    <div><div class="event-banner-label">Event Date</div><div class="event-banner-value"><?php echo htmlspecialchars((string)($selectedEvent['event_date_display'] ?? $selectedEvent['event_date'] ?? '-')); ?></div></div>
                    <div><div class="event-banner-label">Event Type</div><div class="event-banner-value"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($selectedEvent['event_type'] ?? 'single_day')))); ?></div></div>
                    <div><div class="event-banner-label">Location</div><div class="event-banner-value"><?php echo htmlspecialchars((string)($selectedEvent['location'] ?? '-')); ?></div></div>
                    <div><div class="event-banner-label">Status</div><div class="event-banner-value"><?php echo htmlspecialchars((string)($selectedEvent['status'] ?? 'Active')); ?></div></div>
                </div>
            <?php else: ?>
                <h2 class="event-banner-title">All Events</h2>
                <div class="event-banner-grid">
                    <div><div class="event-banner-label">Scope</div><div class="event-banner-value">Combined report across all events</div></div>
                    <div><div class="event-banner-label">Booking Window</div><div class="event-banner-value"><?php echo htmlspecialchars($filters['from_date'] !== '' ? $filters['from_date'] : 'Start'); ?> to <?php echo htmlspecialchars($filters['to_date'] !== '' ? $filters['to_date'] : 'Now'); ?></div></div>
                    <div><div class="event-banner-label">Payment Filter</div><div class="event-banner-value"><?php echo htmlspecialchars($filters['payment_status'] !== '' ? $filters['payment_status'] : 'All'); ?></div></div>
                    <div><div class="event-banner-label">Verification Filter</div><div class="event-banner-value"><?php echo htmlspecialchars($filters['verification_status'] !== '' ? $filters['verification_status'] : 'All'); ?></div></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="chip-row">
            <?php foreach ($filterChips as $chip): ?>
                <div class="chip"><strong><?php echo htmlspecialchars((string)($chip['label'] ?? '')); ?></strong><span><?php echo htmlspecialchars((string)($chip['value'] ?? '')); ?></span></div>
            <?php endforeach; ?>
        </div>

        <div class="summary-grid">
            <div class="summary-card soft"><div class="summary-label">Total Registrations</div><div class="summary-value"><?php echo (int)$summary['total_registrations']; ?></div><div class="summary-note"><?php echo (int)$summary['total_persons']; ?> persons across this report</div></div>
            <div class="summary-card good"><div class="summary-label">Report Paid</div><div class="summary-value">Rs <?php echo htmlspecialchars($formatMoney((float)$summary['report_paid_amount'])); ?></div><div class="summary-note">Visible paid amount in current report view</div></div>
            <div class="summary-card"><div class="summary-label">Expected Amount</div><div class="summary-value">Rs <?php echo htmlspecialchars($formatMoney((float)$summary['expected_amount'])); ?></div><div class="summary-note">For active, non-cancelled bookings</div></div>
            <div class="summary-card <?php echo ((float)$summary['pending_due_amount'] > 0) ? 'soft' : 'good'; ?>"><div class="summary-label">Pending Due</div><div class="summary-value">Rs <?php echo htmlspecialchars($formatMoney((float)$summary['pending_due_amount'])); ?></div><div class="summary-note">Outstanding collection on active bookings</div></div>
            <div class="summary-card good"><div class="summary-label">Paid</div><div class="summary-value"><?php echo (int)$summary['paid_registrations']; ?></div><div class="summary-note">Fully paid registrations</div></div>
            <div class="summary-card"><div class="summary-label">Pending Verification</div><div class="summary-value"><?php echo (int)$summary['pending_verification_registrations']; ?></div><div class="summary-note">Payments waiting for review</div></div>
            <div class="summary-card <?php echo ((int)$summary['waitlisted_registrations'] > 0) ? 'soft' : ''; ?>"><div class="summary-label">Waitlisted</div><div class="summary-value"><?php echo (int)$summary['waitlisted_registrations']; ?></div><div class="summary-note">Payment remains locked for these bookings</div></div>
            <div class="summary-card <?php echo ((int)$summary['cancelled_registrations'] > 0) ? 'soft' : ''; ?>"><div class="summary-label">Cancelled</div><div class="summary-value"><?php echo (int)$summary['cancelled_registrations']; ?></div><div class="summary-note">Excluded from active due summary</div></div>
        </div>

        <div class="section-head">
            <h2>Registration Ledger</h2>
            <p><?php echo count($rows); ?> row(s) included</p>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Booking Ref</th>
                        <th>Registrant</th>
                        <th>Event / Package</th>
                        <th>Persons</th>
                        <th>Payment Snapshot</th>
                        <th>Amounts</th>
                        <th>Check-In</th>
                        <th>Additional Details</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="10" class="empty-state">No registrations found for the selected filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $index => $row): ?>
                        <?php
                        $paymentStatusLower = strtolower(trim((string)($row['payment_status'] ?? '')));
                        $statusClass = 'pill-neutral';
                        if (!empty($row['is_cancelled'])) {
                            $statusClass = 'pill-cancelled';
                        } elseif (!empty($row['is_waitlisted'])) {
                            $statusClass = 'pill-pending';
                        } elseif ($paymentStatusLower === 'paid') {
                            $statusClass = 'pill-paid';
                        } elseif (in_array($paymentStatusLower, ['pending verification', 'partial paid', 'unpaid'], true)) {
                            $statusClass = 'pill-pending';
                        } elseif (in_array($paymentStatusLower, ['failed', 'rejected'], true)) {
                            $statusClass = 'pill-failed';
                        }
                        $detailLines = [];
                        if ((string)($row['payment_note'] ?? '') !== '') {
                            $detailLines[] = 'Payment Note: ' . (string)$row['payment_note'];
                        }
                        if ((string)($row['dynamic_summary'] ?? '') !== '') {
                            $detailLines[] = (string)$row['dynamic_summary'];
                        }
                        ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><div class="stack"><strong><?php echo htmlspecialchars((string)($row['booking_reference'] ?? '-')); ?></strong><span class="subtle">ID <?php echo (int)$row['id']; ?></span></div></td>
                            <td><div class="stack"><strong><?php echo htmlspecialchars((string)($row['name'] ?? '-')); ?></strong><span><?php echo htmlspecialchars((string)($row['phone_display'] ?? $row['phone'] ?? '-')); ?></span></div></td>
                            <td><div class="stack"><strong><?php echo htmlspecialchars((string)($row['event_title'] ?? '-')); ?></strong><span><?php echo htmlspecialchars((string)($row['event_date_display'] ?? '-')); ?></span><span class="subtle"><?php echo htmlspecialchars((string)($row['package_name'] ?? '-')); ?></span></div></td>
                            <td><?php echo (int)($row['persons'] ?? 0); ?></td>
                            <td><div class="stack"><span class="pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars((string)($row['payment_status'] ?? '-')); ?></span><span class="subtle">Verification: <?php echo htmlspecialchars((string)($row['verification_status'] ?? '-')); ?></span><span class="subtle">Method: <?php echo htmlspecialchars((string)($row['payment_method'] !== '' ? $row['payment_method'] : '-')); ?></span><span class="subtle">Record: <?php echo htmlspecialchars((string)($row['payment_record_status'] !== '' ? $row['payment_record_status'] : '-')); ?></span><?php if (trim((string)($row['transaction_id'] ?? '')) !== ''): ?><span class="subtle">Txn: <?php echo htmlspecialchars((string)$row['transaction_id']); ?></span><?php endif; ?></div></td>
                            <td><div class="stack"><strong>Total: Rs <?php echo htmlspecialchars($formatMoney((float)($row['total_amount'] ?? 0))); ?></strong><span class="subtle">Confirmed Paid: Rs <?php echo htmlspecialchars($formatMoney((float)($row['confirmed_paid_amount'] ?? 0))); ?></span><span class="subtle">Report Paid: Rs <?php echo htmlspecialchars($formatMoney((float)($row['report_paid_amount'] ?? 0))); ?></span><span class="subtle">Due: Rs <?php echo htmlspecialchars($formatMoney((float)($row['pending_due_amount'] ?? 0))); ?></span></div></td>
                            <td><div class="stack"><span class="pill <?php echo !empty($row['is_checked_in']) ? 'pill-ok' : 'pill-neutral'; ?>"><?php echo htmlspecialchars((string)($row['checkin_status_text'] ?? 'Not Checked In')); ?></span><?php if (trim((string)($row['checkin_time'] ?? '')) !== ''): ?><span class="subtle"><?php echo htmlspecialchars((string)$row['checkin_time']); ?></span><?php endif; ?></div></td>
                            <td><?php if (empty($detailLines)): ?><span class="subtle">No extra details</span><?php else: ?><div class="stack"><?php foreach ($detailLines as $detailLine): ?><span><?php echo nl2br(htmlspecialchars($detailLine)); ?></span><?php endforeach; ?></div><?php endif; ?></td>
                            <td><div class="stack"><strong><?php echo htmlspecialchars((string)($row['created_at'] ?? '-')); ?></strong></div></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</div>

<?php if ($autoPrint): ?>
<script>
window.addEventListener('load', function () {
    window.setTimeout(function () {
        window.print();
    }, 350);
});
</script>
<?php endif; ?>
</body>
</html>
