<?php
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json');

function normalizeSameDayOnlineBookingCutoffTime($value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '09:00';
    }

    if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $raw, $matches)) {
        return $matches[1] . ':' . $matches[2];
    }

    $timestamp = strtotime($raw);
    if ($timestamp !== false) {
        return date('H:i', $timestamp);
    }

    return '09:00';
}

function getSameDayOnlineBookingCutoffTime(PDO $pdo): string
{
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute(['same_day_online_booking_cutoff_time']);
        $value = $stmt->fetchColumn();
        return normalizeSameDayOnlineBookingCutoffTime($value);
    } catch (Throwable $e) {
        return '09:00';
    }
}

function isSameDayOnlineBookingClosed(string $selectedDate, string $cutoffTime): bool
{
    if ($selectedDate !== date('Y-m-d')) {
        return false;
    }

    $cutoffDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $selectedDate . ' ' . $cutoffTime . ':00');
    if (!$cutoffDateTime) {
        return false;
    }

    return new DateTime('now') >= $cutoffDateTime;
}

function formatCutoffTimeDisplay(string $cutoffTime): string
{
    $dt = DateTime::createFromFormat('H:i', $cutoffTime);
    return $dt ? $dt->format('g:i A') : $cutoffTime;
}

try {
    $date = $_GET['date'] ?? '';
    $location = $_GET['location'] ?? '';
    $sameDayCutoffTime = getSameDayOnlineBookingCutoffTime($pdo);
    $sameDayClosed = isSameDayOnlineBookingClosed($date, $sameDayCutoffTime);
    $sameDayCutoffDisplay = formatCutoffTimeDisplay($sameDayCutoffTime);

    if ($date === '' || $location === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Missing date or location.',
            'same_day_online_closed' => $sameDayClosed,
            'same_day_online_cutoff_time' => $sameDayCutoffTime,
            'same_day_online_cutoff_display' => $sameDayCutoffDisplay
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT token_date, from_time, to_time, unbooked_tokens, total_tokens, notes
         FROM token_management
         WHERE (
             DATE(token_date) = ?
             OR token_date = ?
             OR STR_TO_DATE(token_date, "%d-%m-%Y") = ?
         )
         AND LOWER(TRIM(location)) = LOWER(TRIM(?))
         LIMIT 1'
    );
    $stmt->execute([$date, $date, $date, trim($location)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode([
            'success' => true,
            'data' => $row,
            'same_day_online_closed' => $sameDayClosed,
            'same_day_online_cutoff_time' => $sameDayCutoffTime,
            'same_day_online_cutoff_display' => $sameDayCutoffDisplay
        ]);
        exit;
    }

    echo json_encode([
        'success' => false,
        'message' => 'No tokens available for this date/location.',
        'same_day_online_closed' => $sameDayClosed,
        'same_day_online_cutoff_time' => $sameDayCutoffTime,
        'same_day_online_cutoff_display' => $sameDayCutoffDisplay
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error fetching availability.',
        'same_day_online_closed' => false,
        'same_day_online_cutoff_time' => '09:00',
        'same_day_online_cutoff_display' => '9:00 AM'
    ]);
}
