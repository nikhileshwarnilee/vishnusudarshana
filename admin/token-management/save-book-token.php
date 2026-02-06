<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location = trim($_POST['location'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $token_date = trim($_POST['token_date'] ?? '');
    $service_time = trim($_POST['service_time'] ?? '');
    if ($location && $name && $mobile && $token_date) {
        $pdo->beginTransaction();
        try {
            // Find next token_no for this date/location
            $stmt = $pdo->prepare("SELECT MAX(token_no) AS max_token_no FROM token_bookings WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?))");
            $stmt->execute([$token_date, $location]);
            $maxTokenNo = $stmt->fetchColumn();
            $nextTokenNo = ($maxTokenNo !== null && $maxTokenNo > 0) ? ($maxTokenNo + 1) : 1;

            // Insert booking with token_no
            $stmt = $pdo->prepare("INSERT INTO token_bookings (location, name, mobile, token_date, service_time, token_no) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$location, $name, $mobile, $token_date, $service_time, $nextTokenNo]);

            // Decrement unbooked_tokens
            $update = $pdo->prepare("UPDATE token_management SET unbooked_tokens = unbooked_tokens - 1 WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) AND unbooked_tokens > 0");
            $update->execute([$token_date, $location]);
            if ($update->rowCount() === 0) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'No tokens left to book.']);
                exit;
            }
            $pdo->commit();
            // Send WhatsApp notification using AiSensy
            require_once __DIR__ . '/../../helpers/send_whatsapp.php';
            $waResult = sendWhatsAppMessage(
                $mobile,
                'token_booking_confirmation',
                [
                    'name' => $name,
                    'date' => $token_date,
                    'time' => $service_time,
                    'token_no' => $nextTokenNo
                ]
            );
            echo json_encode(['success' => true, 'token_no' => $nextTokenNo, 'wa_status' => $waResult['success']]);
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Booking failed.']);
            exit;
        }
    }
}
echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
