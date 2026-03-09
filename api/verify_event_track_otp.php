<?php
/**
 * API: Verify OTP for Event Booking Tracking
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/event_module.php';
require_once __DIR__ . '/../helpers/send_whatsapp.php';

vs_event_ensure_tables($pdo);

$action = trim((string)($_POST['action'] ?? ''));
$trackInput = trim((string)($_POST['track_input'] ?? ''));
$otp = trim((string)($_POST['otp'] ?? ''));

function vs_event_track_respond(bool $success, string $message, array $data = []): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function vs_event_track_mask_phone(string $phone): string
{
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if ($digits === '') {
        return '';
    }
    $last4 = substr($digits, -4);
    return '******' . $last4;
}

function vs_event_track_resolve_target(PDO $pdo, string $trackInput): ?array
{
    $trackInput = trim($trackInput);
    if ($trackInput === '') {
        return null;
    }

    $rawDigits = preg_replace('/[^0-9]/', '', $trackInput);
    if ($rawDigits !== '' && strlen($rawDigits) >= 10) {
        $last10 = substr($rawDigits, -10);
        $stmt = $pdo->prepare("SELECT id, booking_reference, phone
            FROM event_registrations
            WHERE RIGHT(phone, 10) = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 1");
        $stmt->execute([$last10]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $phone = vs_event_normalize_phone((string)($row['phone'] ?? ''));
        if ($phone === '') {
            return null;
        }

        return [
            'track_input' => $last10,
            'track_type' => 'mobile',
            'phone' => $phone,
            'registration_id' => (int)$row['id'],
            'booking_reference' => (string)($row['booking_reference'] ?? ''),
        ];
    }

    $bookingReference = strtoupper(trim(vs_event_extract_booking_reference($trackInput)));
    $stmt = $pdo->prepare("SELECT id, booking_reference, phone
        FROM event_registrations
        WHERE booking_reference = ?
        ORDER BY id DESC
        LIMIT 1");
    $stmt->execute([$bookingReference]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $phone = vs_event_normalize_phone((string)($row['phone'] ?? ''));
    if ($phone === '') {
        return null;
    }

    return [
        'track_input' => $bookingReference,
        'track_type' => 'booking_reference',
        'phone' => $phone,
        'registration_id' => (int)$row['id'],
        'booking_reference' => (string)($row['booking_reference'] ?? ''),
    ];
}

if ($action === 'send_otp') {
    if ($trackInput === '') {
        vs_event_track_respond(false, 'Mobile number or booking reference is required.');
    }

    $target = vs_event_track_resolve_target($pdo, $trackInput);
    if (!$target) {
        vs_event_track_respond(false, 'No booking found for the provided details.');
    }

    $otpCode = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $expiryTs = time() + (10 * 60);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['event_track_otp'] = [
        'otp' => $otpCode,
        'track_input' => (string)$target['track_input'],
        'track_type' => (string)$target['track_type'],
        'mobile' => (string)$target['phone'],
        'registration_id' => (int)$target['registration_id'],
        'generated_at' => date('Y-m-d H:i:s'),
        'expiry' => date('Y-m-d H:i:s', $expiryTs),
        'attempts' => 0,
        'max_attempts' => 5,
    ];

    try {
        $sendResult = sendWhatsAppMessage(
            (string)$target['phone'],
            'OTP_VERIFICATION',
            ['otp_code' => $otpCode]
        );
        if (empty($sendResult['success'])) {
            error_log('Event track OTP send failed: ' . (string)($sendResult['message'] ?? 'Unknown error'));
            vs_event_track_respond(false, 'Unable to send OTP right now. Please try again.');
        }
    } catch (Throwable $e) {
        error_log('Event track OTP exception: ' . $e->getMessage());
        vs_event_track_respond(false, 'Unable to send OTP right now. Please try again.');
    }

    vs_event_track_respond(true, 'OTP sent to your registered WhatsApp number.', [
        'masked_phone' => vs_event_track_mask_phone((string)$target['phone']),
    ]);
}

if ($action === 'verify_otp') {
    if ($trackInput === '' || $otp === '') {
        vs_event_track_respond(false, 'Track input and OTP are required.');
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['event_track_otp']) || !is_array($_SESSION['event_track_otp'])) {
        vs_event_track_respond(false, 'OTP expired. Please request a new OTP.');
    }

    $otpSession = $_SESSION['event_track_otp'];
    $sessionTrackInput = strtoupper(trim((string)($otpSession['track_input'] ?? '')));
    $requestTrackInput = strtoupper(trim($trackInput));
    if ($sessionTrackInput !== $requestTrackInput) {
        vs_event_track_respond(false, 'OTP does not match the current track request.');
    }

    $expiryTs = strtotime((string)($otpSession['expiry'] ?? ''));
    if ($expiryTs === false || time() > $expiryTs) {
        unset($_SESSION['event_track_otp']);
        vs_event_track_respond(false, 'OTP has expired. Please request a new OTP.');
    }

    $attempts = (int)($otpSession['attempts'] ?? 0);
    $maxAttempts = (int)($otpSession['max_attempts'] ?? 5);
    if ($attempts >= $maxAttempts) {
        unset($_SESSION['event_track_otp']);
        vs_event_track_respond(false, 'Maximum OTP attempts exceeded. Please request a new OTP.');
    }

    if (!hash_equals((string)($otpSession['otp'] ?? ''), $otp)) {
        $attempts++;
        $_SESSION['event_track_otp']['attempts'] = $attempts;
        $remainingAttempts = max($maxAttempts - $attempts, 0);
        vs_event_track_respond(false, 'Invalid OTP. ' . $remainingAttempts . ' attempts left.');
    }

    $token = bin2hex(random_bytes(24));
    if (!isset($_SESSION['event_track_auth']) || !is_array($_SESSION['event_track_auth'])) {
        $_SESSION['event_track_auth'] = [];
    }
    $_SESSION['event_track_auth'][$token] = [
        'track_input' => (string)($otpSession['track_input'] ?? ''),
        'track_type' => (string)($otpSession['track_type'] ?? ''),
        'mobile' => (string)($otpSession['mobile'] ?? ''),
        'registration_id' => (int)($otpSession['registration_id'] ?? 0),
        'created_at' => date('Y-m-d H:i:s'),
        'expiry' => date('Y-m-d H:i:s', time() + (15 * 60)),
    ];

    unset($_SESSION['event_track_otp']);

    vs_event_track_respond(true, 'OTP verified successfully.', [
        'track_token' => $token,
        'track_input' => (string)($sessionTrackInput),
    ]);
}

vs_event_track_respond(false, 'Invalid action.');
