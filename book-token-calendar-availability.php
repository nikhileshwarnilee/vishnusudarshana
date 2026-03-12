<?php
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json');

function getBookingTimezone(): DateTimeZone
{
    static $timezone = null;
    if ($timezone instanceof DateTimeZone) {
        return $timezone;
    }

    try {
        $timezone = new DateTimeZone('Asia/Kolkata');
    } catch (Throwable $e) {
        $timezone = new DateTimeZone(date_default_timezone_get());
    }

    return $timezone;
}

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
    $selectedDate = trim($selectedDate);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
        return false;
    }

    $timezone = getBookingTimezone();
    $now = new DateTimeImmutable('now', $timezone);
    if ($selectedDate !== $now->format('Y-m-d')) {
        return false;
    }

    $cutoffDateTime = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        $selectedDate . ' ' . $cutoffTime . ':00',
        $timezone
    );
    if (!$cutoffDateTime) {
        return false;
    }

    $parseErrors = DateTimeImmutable::getLastErrors();
    if (is_array($parseErrors) && (($parseErrors['warning_count'] ?? 0) > 0 || ($parseErrors['error_count'] ?? 0) > 0)) {
        return false;
    }

    return $now >= $cutoffDateTime;
}

function formatCutoffTimeDisplay(string $cutoffTime): string
{
    $dt = DateTime::createFromFormat('H:i', $cutoffTime);
    return $dt ? $dt->format('g:i A') : $cutoffTime;
}

try {
    $timezone = getBookingTimezone();
    $now = new DateTimeImmutable('now', $timezone);

    $location = trim((string) ($_GET['location'] ?? ''));
    $year = (int) ($_GET['year'] ?? $now->format('Y'));
    $month = (int) ($_GET['month'] ?? $now->format('n'));

    if ($location === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Missing location.'
        ]);
        exit;
    }

    if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid month/year.'
        ]);
        exit;
    }

    $monthDate = DateTimeImmutable::createFromFormat('!Y-n-j', $year . '-' . $month . '-1', $timezone);
    if (!$monthDate) {
        echo json_encode([
            'success' => false,
            'message' => 'Unable to parse month/year.'
        ]);
        exit;
    }

    $monthStart = $monthDate->format('Y-m-01');
    $monthEnd = $monthDate->format('Y-m-t');

    $sameDayCutoffTime = getSameDayOnlineBookingCutoffTime($pdo);
    $sameDayCutoffDisplay = formatCutoffTimeDisplay($sameDayCutoffTime);
    $todayIso = $now->format('Y-m-d');
    $sameDayClosedToday = isSameDayOnlineBookingClosed($todayIso, $sameDayCutoffTime);

    $normalizedDateExpr = 'COALESCE(
        STR_TO_DATE(token_date, "%Y-%m-%d"),
        STR_TO_DATE(token_date, "%d-%m-%Y"),
        STR_TO_DATE(token_date, "%d/%m/%Y"),
        STR_TO_DATE(token_date, "%Y/%m/%d"),
        DATE(token_date)
    )';

    $sql = "
        SELECT
            {$normalizedDateExpr} AS normalized_date,
            SUM(CASE WHEN total_tokens IS NULL THEN 0 ELSE CAST(total_tokens AS SIGNED) END) AS total_tokens,
            SUM(CASE WHEN unbooked_tokens IS NULL THEN 0 ELSE CAST(unbooked_tokens AS SIGNED) END) AS available_tokens
        FROM token_management
        WHERE LOWER(TRIM(location)) = LOWER(TRIM(?))
          AND {$normalizedDateExpr} BETWEEN ? AND ?
        GROUP BY normalized_date
        ORDER BY normalized_date ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$location, $monthStart, $monthEnd]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $days = [];
    foreach ($rows as $row) {
        $dayKey = trim((string) ($row['normalized_date'] ?? ''));
        if ($dayKey === '') {
            continue;
        }

        $totalTokens = max(0, (int) ($row['total_tokens'] ?? 0));
        $availableTokens = max(0, (int) ($row['available_tokens'] ?? 0));
        if ($availableTokens > $totalTokens) {
            $availableTokens = $totalTokens;
        }
        $bookedTokens = max(0, $totalTokens - $availableTokens);

        $status = 'no_tokens';
        if ($totalTokens > 0 && $availableTokens > 0) {
            $status = 'available';
        } elseif ($totalTokens > 0 && $availableTokens <= 0) {
            $status = 'full';
        }

        $sameDayCutoffClosed = false;
        if ($dayKey === $todayIso && $sameDayClosedToday) {
            $sameDayCutoffClosed = true;
            if ($status === 'available') {
                $status = 'full';
            }
        }

        $days[$dayKey] = [
            'status' => $status,
            'total_tokens' => $totalTokens,
            'available_tokens' => $availableTokens,
            'booked_tokens' => $bookedTokens,
            'same_day_cutoff_closed' => $sameDayCutoffClosed
        ];
    }

    echo json_encode([
        'success' => true,
        'year' => (int) $year,
        'month' => (int) $month,
        'today' => $todayIso,
        'same_day_online_closed_today' => $sameDayClosedToday,
        'same_day_online_cutoff_time' => $sameDayCutoffTime,
        'same_day_online_cutoff_display' => $sameDayCutoffDisplay,
        'days' => $days
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error fetching calendar availability.',
        'days' => []
    ]);
}

