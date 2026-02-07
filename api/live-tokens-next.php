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
    'cities' => []
];

foreach ($cities as $city) {
    $bookStmt = $pdo->prepare("SELECT token_no, status FROM token_bookings WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) ORDER BY CAST(token_no AS UNSIGNED) ASC");
    $bookStmt->execute([$date, $city]);
    $bookings = $bookStmt->fetchAll(PDO::FETCH_ASSOC);

    $lastToken = 0;
    $currentToken = 0;
    $nextToken = '--';
    $pendingTokens = [];

    foreach ($bookings as $booking) {
        $tokenNo = (int)$booking['token_no'];
        if ($tokenNo > $lastToken) $lastToken = $tokenNo;
        if (isset($booking['status']) && $booking['status'] === 'completed' && $tokenNo > $currentToken) {
            $currentToken = $tokenNo;
        }
        if (!isset($booking['status']) || $booking['status'] !== 'completed') {
            $pendingTokens[] = $tokenNo;
        }
    }

    if (count($pendingTokens) > 0) {
        $nextToken = min($pendingTokens);
    }

    $result['cities'][$city] = [
        'last_token' => $lastToken ?: '--',
        'current_token' => $currentToken ?: '--',
        'next_token' => $nextToken,
    ];
}

echo json_encode($result);
