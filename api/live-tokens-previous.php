<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$date = date('Y-m-d');
$cities = ['solapur', 'hyderabad'];
$result = [
    'success' => true,
    'date' => $date,
    'cities' => []
];

foreach ($cities as $city) {
    // Get all completed tokens for today, ordered by updated_at
    $stmt = $pdo->prepare("SELECT token_no, updated_at FROM token_bookings WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) AND status = 'completed' AND updated_at IS NOT NULL ORDER BY updated_at ASC");
    $stmt->execute([$date, $city]);
    $completed = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $prevToken = '-';
    $currToken = '--';
    $nextToken = '--';

    if (count($completed) > 0) {
        $currToken = $completed[count($completed)-1]['token_no'];
        if (count($completed) > 1) {
            $prevToken = $completed[count($completed)-2]['token_no'];
        }
    }

    // Find next token (lowest not completed and not skipped)
    $pendingStmt = $pdo->prepare("SELECT token_no FROM token_bookings WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) AND (status IS NULL OR (status != 'completed' AND status != 'skip')) ORDER BY CAST(token_no AS UNSIGNED) ASC LIMIT 1");
    $pendingStmt->execute([$date, $city]);
    $pending = $pendingStmt->fetch(PDO::FETCH_ASSOC);
    if ($pending && isset($pending['token_no'])) {
        $nextToken = $pending['token_no'];
    }

    $result['cities'][$city] = [
        'previous_token' => $prevToken,
        'current_token' => $currToken,
        'next_token' => $nextToken
    ];
}

echo json_encode($result);
