<?php
/**
 * Event reminder cron script.
 *
 * Recommended cron (daily):
 * php /path/to/project/scripts/event_reminder_cronjob.php
 */

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/event_module.php';

vs_event_ensure_tables($pdo);

$today = date('Y-m-d');
$target = date('Y-m-d', strtotime('+1 day'));

// Auto-close events where registration window has ended.
$closeStmt = $pdo->prepare("UPDATE events
    SET status = 'Closed'
    WHERE status = 'Active'
      AND registration_end < ?");
$closeStmt->execute([$today]);
$autoClosed = $closeStmt->rowCount();

$stmt = $pdo->prepare("SELECT
        e.id,
        e.title,
        e.event_type,
        COALESCE(MIN(ed.event_date), e.event_date) AS event_date
    FROM events e
    LEFT JOIN event_dates ed ON ed.event_id = e.id
        AND ed.status = 'Active'
        AND ed.event_date BETWEEN ? AND ?
    WHERE e.status = 'Active'
      AND (
          ed.id IS NOT NULL
          OR e.event_date BETWEEN ? AND ?
      )
    GROUP BY e.id
    ORDER BY event_date ASC, e.id ASC");
$stmt->execute([$today, $target, $today, $target]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '[' . date('Y-m-d H:i:s') . "] Event reminder cron started\n";
echo "Events auto-closed by registration_end rule: " . (int)$autoClosed . "\n";

if (empty($events)) {
    echo "No upcoming active events for reminder window ($today to $target).\n";
    exit;
}

foreach ($events as $event) {
    $result = vs_event_send_event_reminders($pdo, (int)$event['id'], false);
    $eventDateLabel = vs_event_get_event_date_display(
        $pdo,
        (int)$event['id'],
        (string)($event['event_date'] ?? ''),
        (string)($event['event_type'] ?? 'single_day')
    );
    echo sprintf(
        "Event #%d (%s, %s): sent=%d skipped=%d\n",
        (int)$event['id'],
        (string)$event['title'],
        $eventDateLabel,
        (int)($result['sent'] ?? 0),
        (int)($result['skipped'] ?? 0)
    );
}

echo '[' . date('Y-m-d H:i:s') . "] Event reminder cron completed\n";
