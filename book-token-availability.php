<?php
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json');

try {
    $date = $_GET['date'] ?? '';
    $location = $_GET['location'] ?? '';

    if ($date === '' || $location === '') {
        echo json_encode(['success' => false, 'message' => 'Missing date or location.']);
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
        echo json_encode(['success' => true, 'data' => $row]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'No tokens available for this date/location.']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error fetching availability.']);
}
