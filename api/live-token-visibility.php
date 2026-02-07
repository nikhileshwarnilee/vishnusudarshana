<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$date = date('Y-m-d');
$cities = ['solapur', 'hyderabad'];
$visible = [];

foreach ($cities as $city) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM token_bookings WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) AND status = 'completed'");
    $stmt->execute([$date, $city]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && (int)$row['cnt'] > 0) {
        $visible[] = $city;
    }
}

echo json_encode(['success' => true, 'visible' => $visible]);
