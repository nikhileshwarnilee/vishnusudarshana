<?php
// API endpoint: Returns { available: true } if today has an accepted timeframe with available slots, else { available: false }
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');
$windowStart = '09:00:00';
$windowEnd = '18:00:00';
$slotDuration = 30 * 60; // 30 min

// Get all accepted slots for today
$stmt = $pdo->prepare("SELECT assigned_from_time, assigned_to_time FROM appointments WHERE preferred_date = ? AND status = 'accepted' AND assigned_from_time IS NOT NULL AND assigned_to_time IS NOT NULL ORDER BY assigned_from_time ASC");
$stmt->execute([$today]);
$slots = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $slots[] = [$row['assigned_from_time'], $row['assigned_to_time']];
}
$nextStart = strtotime($windowStart);
$end = strtotime($windowEnd);
$available = false;
foreach ($slots as $s) {
    $slotS = strtotime($s[0]);
    $slotE = strtotime($s[1]);
    if ($nextStart + $slotDuration <= $slotS) break;
    $nextStart = $slotE;
}
if ($nextStart + $slotDuration <= $end) {
    $available = true;
}
echo json_encode(['available' => $available]);
exit;