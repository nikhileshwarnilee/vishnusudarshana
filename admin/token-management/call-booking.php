<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, token_no, token_date, location, status FROM token_bookings WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Booking not found.']);
        exit;
    }

    $status = strtolower(trim((string)($booking['status'] ?? '')));
    if ($status === 'completed' || $status === 'skip') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'This booking cannot be called.']);
        exit;
    }

    $clearStmt = $pdo->prepare(
        "UPDATE token_bookings
         SET status = NULL, updated_at = NOW()
         WHERE id != ?
           AND token_date = ?
           AND LOWER(TRIM(location)) = LOWER(TRIM(?))
           AND status = 'called'"
    );
    $clearStmt->execute([$id, $booking['token_date'], $booking['location']]);

    $updateStmt = $pdo->prepare("UPDATE token_bookings SET status = 'called', updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'token_no' => $booking['token_no'],
        'message' => 'Token called.',
    ]);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('call-booking: ' . $e->getMessage());
}

echo json_encode(['success' => false, 'message' => 'Failed to call booking.']);
exit;
