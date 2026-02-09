<?php
// skip-booking.php
require_once __DIR__ . '/../../config/db.php';


$id = $_POST['id'] ?? null;
$unskip = isset($_POST['unskip']) ? true : false;
$response = ['success' => false];

if ($id && is_numeric($id)) {
    if ($unskip) {
        $stmt = $pdo->prepare("UPDATE token_bookings SET status = '' WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("UPDATE token_bookings SET status = 'skip' WHERE id = ?");
    }
    $stmt->execute([$id]);
    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
    }
}
header('Content-Type: application/json');
echo json_encode($response);
