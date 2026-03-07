<?php
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json');

function getBookingTimezone(): DateTimeZone
{
    static $timezone = null;
    if ($timezone instanceof DateTimeZone) {
        return $timezone;
    }

    try {
        $timezone = new DateTimeZone('Asia/Kolkata');
    } catch (Throwable $e) {
        $timezone = new DateTimeZone(date_default_timezone_get());
    }

    return $timezone;
}

function normalizeSameDayOnlineBookingCutoffTime($value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '09:00';
    }

    if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $raw, $matches)) {
        return $matches[1] . ':' . $matches[2];
    }

    $timestamp = strtotime($raw);
    if ($timestamp !== false) {
        return date('H:i', $timestamp);
    }

    return '09:00';
}

function getSameDayOnlineBookingCutoffTime(PDO $pdo): string
{
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute(['same_day_online_booking_cutoff_time']);
        $value = $stmt->fetchColumn();
        return normalizeSameDayOnlineBookingCutoffTime($value);
    } catch (Throwable $e) {
        return '09:00';
    }
}

function isSameDayOnlineBookingClosed(string $selectedDate, string $cutoffTime): bool
{
    $selectedDate = trim($selectedDate);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
        return false;
    }

    $timezone = getBookingTimezone();
    $now = new DateTimeImmutable('now', $timezone);
    if ($selectedDate !== $now->format('Y-m-d')) {
        return false;
    }

    $cutoffDateTime = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        $selectedDate . ' ' . $cutoffTime . ':00',
        $timezone
    );
    if (!$cutoffDateTime) {
        return false;
    }

    $parseErrors = DateTimeImmutable::getLastErrors();
    if (is_array($parseErrors) && (($parseErrors['warning_count'] ?? 0) > 0 || ($parseErrors['error_count'] ?? 0) > 0)) {
        return false;
    }

    return $now >= $cutoffDateTime;
}

function formatCutoffTimeDisplay(string $cutoffTime): string
{
    $dt = DateTime::createFromFormat('H:i', $cutoffTime);
    return $dt ? $dt->format('g:i A') : $cutoffTime;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location = trim($_POST['location'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $token_date = trim($_POST['token_date'] ?? '');
    $service_time = trim($_POST['service_time'] ?? '');
    if ($location && $name && $mobile && $token_date) {
        $sameDayCutoffTime = getSameDayOnlineBookingCutoffTime($pdo);
        $sameDayCutoffDisplay = formatCutoffTimeDisplay($sameDayCutoffTime);

        if (isSameDayOnlineBookingClosed($token_date, $sameDayCutoffTime)) {
            $slotStmt = $pdo->prepare(
                'SELECT unbooked_tokens, total_tokens
                 FROM token_management
                 WHERE (
                     DATE(token_date) = ?
                     OR token_date = ?
                     OR STR_TO_DATE(token_date, "%d-%m-%Y") = ?
                 )
                 AND LOWER(TRIM(location)) = LOWER(TRIM(?))
                 LIMIT 1'
            );
            $slotStmt->execute([$token_date, $token_date, $token_date, $location]);
            $slot = $slotStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $remainingTokens = isset($slot['unbooked_tokens']) ? (int)$slot['unbooked_tokens'] : 0;
            $totalTokens = isset($slot['total_tokens']) ? (int)$slot['total_tokens'] : null;

            echo json_encode([
                'success' => false,
                'same_day_closed' => true,
                'same_day_online_cutoff_time' => $sameDayCutoffTime,
                'same_day_online_cutoff_display' => $sameDayCutoffDisplay,
                'remaining_tokens' => $remainingTokens,
                'total_tokens' => $totalTokens,
                'error' => 'आजच्या दिवसाची ऑनलाइन टोकन बुकिंग सकाळी ' . $sameDayCutoffDisplay . ' वाजता बंद झाली आहे.'
            ]);
            exit;
        }

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
            require_once __DIR__ . '/helpers/send_whatsapp.php';
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
