<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $date = $_POST['token_date'] ?? '';
    $from_time = $_POST['from_time'] ?? '';
    $to_time = $_POST['to_time'] ?? '';
    $total_tokens = intval($_POST['total_tokens'] ?? 0);
    $booked_tokens = intval($_POST['booked_tokens'] ?? 0);
    $location = $_POST['location'] ?? 'solapur';
    if ($id > 0) {
        // Fetch current total and unbooked tokens
        $stmt = $pdo->prepare("SELECT total_tokens, unbooked_tokens FROM token_management WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Token entry not found.']);
            exit;
        }
        $old_total = (int)$row['total_tokens'];
        $old_unbooked = (int)$row['unbooked_tokens'];
        $diff = $total_tokens - $old_total;
        $new_unbooked = $old_unbooked + $diff;
        // Prevent negative unbooked tokens
        if ($new_unbooked < 0) {
            echo json_encode(['success' => false, 'error' => 'Cannot reduce tokens below number already booked.']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE token_management SET token_date = ?, from_time = ?, to_time = ?, total_tokens = ?, unbooked_tokens = ?, location = ? WHERE id = ?");
        $stmt->execute([$date, $from_time, $to_time, $total_tokens, $new_unbooked, $location, $id]);

        // Update service_time in token_bookings for all bookings on the same date and location
        if ($from_time && $to_time && $date && $location) {
            $service_time = $from_time . ' to ' . $to_time;
            $updateBookings = $pdo->prepare("UPDATE token_bookings SET service_time = ? WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?))");
            $updateBookings->execute([$service_time, $date, $location]);
        }

        echo json_encode(['success' => true]);
        exit;
    }
}
echo json_encode(['success' => false]);
exit;
