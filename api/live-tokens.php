<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

function minsToTime($mins) {
    $h = floor($mins / 60);
    $m = $mins % 60;
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h % 12;
    if ($h12 == 0) $h12 = 12;
    return sprintf('%02d:%02d %s', $h12, $m, $ampm);
}

$date = date('Y-m-d');
$cities = ['solapur', 'hyderabad'];
$result = [
    'success' => true,
    'date' => $date,
    'cities' => [],
    'all' => [
        'last_token' => 0,
        'current_token' => 0,
        'next_token' => null,
        'times' => []
    ]
];

foreach ($cities as $city) {
    // Fetch bookings for today
    $bookStmt = $pdo->prepare("SELECT token_no, status FROM token_bookings WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) ORDER BY CAST(token_no AS UNSIGNED) ASC");
    $bookStmt->execute([$date, $city]);
    $bookings = $bookStmt->fetchAll(PDO::FETCH_ASSOC);

    // Slot info for schedule
    $slotStmt = $pdo->prepare("SELECT from_time, to_time, total_tokens FROM token_management WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) LIMIT 1");
    $slotStmt->execute([$date, $city]);
    $slot = $slotStmt->fetch(PDO::FETCH_ASSOC);

    $perTokenMins = 0;
    $fromMins = 0;
    if ($slot && $slot['from_time'] && $slot['to_time'] && (int)$slot['total_tokens'] > 0) {
        $fromParts = explode(':', $slot['from_time']);
        $toParts = explode(':', $slot['to_time']);
        if (count($fromParts) >= 2 && count($toParts) >= 2) {
            $fromMins = intval($fromParts[0]) * 60 + intval($fromParts[1]);
            $toMins = intval($toParts[0]) * 60 + intval($toParts[1]);
            $diffMins = $toMins - $fromMins;
            if ($diffMins > 0) {
                $perTokenMins = floor($diffMins / (int)$slot['total_tokens']);
            }
        }
    }

    $lastToken = 0;
    $currentToken = 0;
    $times = [];


    foreach ($bookings as $booking) {
        // Exclude skipped tokens from all live logic
        if (isset($booking['status']) && $booking['status'] === 'skip') {
            continue;
        }
        $tokenNo = (int)$booking['token_no'];
        if ($tokenNo > $lastToken) $lastToken = $tokenNo;
        if (isset($booking['status']) && $booking['status'] === 'completed' && $tokenNo > $currentToken) {
            $currentToken = $tokenNo;
        }

        $slotLabel = '-';
        if ($perTokenMins > 0) {
            $rowIndex = $tokenNo - 1;
            $start = $fromMins + ($rowIndex * $perTokenMins);
            $end = $start + $perTokenMins;
            $slotLabel = minsToTime($start) . ' - ' . minsToTime($end);
        }

        $times[] = [
            'token_no' => $tokenNo,
            'slot' => $slotLabel,
            'status' => $booking['status'] ?? 'pending',
            'city' => $city
        ];
    }

    // Find next token (lowest not completed and not skipped)
    $nextToken = null;
    if ($lastToken > 0 && $currentToken < $lastToken) {
        // Find the next token_no greater than currentToken that is not skipped or completed
        foreach ($bookings as $booking) {
            $tokenNo = (int)$booking['token_no'];
            if ($tokenNo > $currentToken && (!isset($booking['status']) || ($booking['status'] !== 'completed' && $booking['status'] !== 'skip'))) {
                $nextToken = $tokenNo;
                break;
            }
        }
    }

    $result['cities'][$city] = [
        'last_token' => $lastToken ?: '--',
        'current_token' => $currentToken ?: '--',
        'next_token' => $nextToken ?: '--',
        'times' => $times
    ];

    $result['all']['last_token'] += $lastToken;
    $result['all']['current_token'] += $currentToken;
    $result['all']['times'] = array_merge($result['all']['times'], $times);
}

if ($result['all']['last_token'] > 0 && $result['all']['current_token'] < $result['all']['last_token']) {
    $result['all']['next_token'] = $result['all']['current_token'] + 1;
} else {
    $result['all']['next_token'] = '--';
}

// Sort all times by city then token_no
usort($result['all']['times'], function($a, $b) {
    if ($a['city'] === $b['city']) {
        return $a['token_no'] - $b['token_no'];
    }
    return strcmp($a['city'], $b['city']);
});

if ($result['all']['last_token'] === 0) {
    $result['all']['last_token'] = '--';
}
if ($result['all']['current_token'] === 0) {
    $result['all']['current_token'] = '--';
}

echo json_encode($result);
