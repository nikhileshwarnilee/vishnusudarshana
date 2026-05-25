<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function normalize_cursor_timestamp($value)
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function normalize_cursor_id($value)
{
    return max(0, (int) $value);
}

function city_placeholder_sql(array $cities)
{
    return implode(', ', array_fill(0, count($cities), '?'));
}

function to_devanagari_number($value)
{
    return strtr((string) $value, [
        '0' => '०',
        '1' => '१',
        '2' => '२',
        '3' => '३',
        '4' => '४',
        '5' => '५',
        '6' => '६',
        '7' => '७',
        '8' => '८',
        '9' => '९',
    ]);
}

function fetch_city_snapshot(PDO $pdo, $date, $city)
{
    $completedStmt = $pdo->prepare(
        "SELECT id, token_no, updated_at
         FROM token_bookings
         WHERE token_date = ?
           AND LOWER(TRIM(location)) = LOWER(TRIM(?))
           AND status = 'completed'
           AND updated_at IS NOT NULL
         ORDER BY updated_at ASC, id ASC"
    );
    $completedStmt->execute([$date, $city]);
    $completed = $completedStmt->fetchAll(PDO::FETCH_ASSOC);

    $previousToken = '-';
    $currentToken = '--';
    $nextToken = '--';
    $displayToken = '--';
    $displayState = 'idle';
    $displayMessage = 'टोकन स्थिती लवकरच उपलब्ध होईल';

    $completedCount = count($completed);
    if ($completedCount > 0) {
        $currentToken = $completed[$completedCount - 1]['token_no'];
        if ($completedCount > 1) {
            $previousToken = $completed[$completedCount - 2]['token_no'];
        }
    }

    $calledStmt = $pdo->prepare(
        "SELECT id, token_no, updated_at
         FROM token_bookings
         WHERE token_date = ?
           AND LOWER(TRIM(location)) = LOWER(TRIM(?))
           AND status = 'called'
           AND updated_at IS NOT NULL
         ORDER BY updated_at DESC, id DESC
         LIMIT 1"
    );
    $calledStmt->execute([$date, $city]);
    $called = $calledStmt->fetch(PDO::FETCH_ASSOC);

    $pendingStmt = $pdo->prepare(
        "SELECT token_no
         FROM token_bookings
         WHERE token_date = ?
           AND LOWER(TRIM(location)) = LOWER(TRIM(?))
           AND (status IS NULL OR (status != 'completed' AND status != 'skip'))
         ORDER BY CAST(token_no AS UNSIGNED) ASC
         LIMIT 1"
    );
    $pendingStmt->execute([$date, $city]);
    $pending = $pendingStmt->fetch(PDO::FETCH_ASSOC);

    if ($pending && isset($pending['token_no'])) {
        $nextToken = $pending['token_no'];
    }

    if ($called && isset($called['token_no'])) {
        $displayToken = $called['token_no'];
        $displayState = 'called';
        $displayMessage = 'टोकन क्रमांक ' . to_devanagari_number($displayToken) . ' उपस्थित राहावे';
    } elseif ($currentToken !== '--') {
        $displayToken = $currentToken;
        $displayState = 'present';
        $displayMessage = 'सध्या टोकन क्रमांक ' . to_devanagari_number($displayToken) . ' चालू आहे';
    }

    return [
        'previous_token' => $previousToken,
        'current_token' => $currentToken,
        'next_token' => $nextToken,
        'display_token' => $displayToken,
        'display_state' => $displayState,
        'display_message' => $displayMessage,
    ];
}

function fetch_latest_announcement_cursor(PDO $pdo, $date, array $cities)
{
    $params = array_merge([$date], $cities);
    $sql = city_placeholder_sql($cities);
    $stmt = $pdo->prepare(
        "SELECT id, updated_at
         FROM token_bookings
         WHERE token_date = ?
           AND status = 'completed'
           AND updated_at IS NOT NULL
           AND LOWER(TRIM(location)) IN ($sql)
         ORDER BY updated_at DESC, id DESC
         LIMIT 1"
    );
    $stmt->execute($params);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['updated_at'])) {
        return null;
    }

    return [
        'updated_at' => (string) $row['updated_at'],
        'booking_id' => (int) $row['id'],
    ];
}

function fetch_announcement_events(PDO $pdo, $date, array $cities, $sinceUpdatedAt, $sinceId)
{
    if ($sinceUpdatedAt === null) {
        return [];
    }

    $params = array_merge([$date], $cities, [$sinceUpdatedAt, $sinceUpdatedAt, $sinceId]);
    $sql = city_placeholder_sql($cities);
    $stmt = $pdo->prepare(
        "SELECT id, LOWER(TRIM(location)) AS city, token_no, updated_at
         FROM token_bookings
         WHERE token_date = ?
           AND status = 'completed'
           AND updated_at IS NOT NULL
           AND LOWER(TRIM(location)) IN ($sql)
           AND (
                updated_at > ?
                OR (updated_at = ? AND id > ?)
           )
         ORDER BY updated_at ASC, id ASC"
    );
    $stmt->execute($params);

    $events = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $events[] = [
            'booking_id' => (int) ($row['id'] ?? 0),
            'city' => strtolower(trim((string) ($row['city'] ?? ''))),
            'token_no' => (int) ($row['token_no'] ?? 0),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    return $events;
}

$date = date('Y-m-d');
$cities = ['solapur', 'hyderabad'];
$sinceUpdatedAt = normalize_cursor_timestamp($_GET['announcement_since'] ?? null);
$sinceId = normalize_cursor_id($_GET['announcement_after_id'] ?? 0);

$result = [
    'success' => true,
    'date' => $date,
    'cities' => [],
    'announcement' => [
        'cursor' => null,
        'events' => [],
    ],
];

foreach ($cities as $city) {
    $result['cities'][$city] = fetch_city_snapshot($pdo, $date, $city);
}

$result['announcement']['events'] = fetch_announcement_events($pdo, $date, $cities, $sinceUpdatedAt, $sinceId);
$result['announcement']['cursor'] = fetch_latest_announcement_cursor($pdo, $date, $cities);

echo json_encode($result);
