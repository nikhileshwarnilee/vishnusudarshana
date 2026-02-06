<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id > 0 && in_array($action, ['delete', 'complete'])) {
        // Get booking details before delete
        $stmt = $pdo->prepare('SELECT token_date, location FROM token_bookings WHERE id = ?');
        $stmt->execute([$id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        $token_date = $booking['token_date'] ?? null;
        $location = $booking['location'] ?? null;

        // Delete booking
        $stmt = $pdo->prepare('DELETE FROM token_bookings WHERE id = ?');
        $stmt->execute([$id]);

        // Recalculate and update time slots for remaining bookings (carry forward token_no, update service_time)
        if ($token_date && $location) {
            // Get slot info
            $slotStmt = $pdo->prepare('SELECT from_time, to_time, total_tokens FROM token_management WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) LIMIT 1');
            $slotStmt->execute([$token_date, $location]);
            $slot = $slotStmt->fetch(PDO::FETCH_ASSOC);
            if ($slot && $slot['from_time'] && $slot['to_time'] && $slot['total_tokens'] > 0) {
                $fromParts = explode(':', $slot['from_time']);
                $toParts = explode(':', $slot['to_time']);
                if (count($fromParts) >= 2 && count($toParts) >= 2) {
                    $fromMins = intval($fromParts[0]) * 60 + intval($fromParts[1]);
                    $toMins = intval($toParts[0]) * 60 + intval($toParts[1]);
                    $diffMins = $toMins - $fromMins;
                    if ($diffMins > 0) {
                        $perMins = floor($diffMins / $slot['total_tokens']);
                        // Get all bookings for this date/location, ordered by token_no
                        $bookingsStmt = $pdo->prepare('SELECT id, token_no FROM token_bookings WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) ORDER BY token_no ASC');
                        $bookingsStmt->execute([$token_date, $location]);
                        $bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);
                        $slotIndex = 0;
                        foreach ($bookings as $b) {
                            $startMins = $fromMins + $slotIndex * $perMins;
                            $endMins = $fromMins + ($slotIndex + 1) * $perMins;
                            $service_time = sprintf('%02d:%02d', floor($startMins / 60), $startMins % 60) . ' to ' . sprintf('%02d:%02d', floor($endMins / 60), $endMins % 60);
                            $updateStmt = $pdo->prepare('UPDATE token_bookings SET service_time = ? WHERE id = ?');
                            $updateStmt->execute([$service_time, $b['id']]);
                            $slotIndex++;
                        }
                    }
                }
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }
}
echo json_encode(['success' => false]);
exit;