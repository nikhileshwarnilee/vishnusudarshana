<?php
/**
 * API: Verify OTP for File Download
 * 
 * Handles:
 * 1. Generate OTP and send via WhatsApp
 * 2. Verify OTP and provide download token
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/send_whatsapp.php';

$action = $_POST['action'] ?? '';
$trackingId = trim($_POST['tracking_id'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$otp = trim($_POST['otp'] ?? '');
$fileName = $_POST['file'] ?? '';

// Response helper
function respond($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/* ===========================
   ACTION 1: GENERATE & SEND OTP
   =========================== */

if ($action === 'send_otp') {
    if (!$trackingId || !$mobile) {
        respond(false, 'Tracking ID and mobile number required.');
    }

    // Validate tracking ID and mobile exist together
    $stmt = $pdo->prepare('SELECT mobile FROM service_requests WHERE tracking_id = ?');
    $stmt->execute([$trackingId]);
    $dbMobile = $stmt->fetchColumn();
    
    if (!$dbMobile) {
        respond(false, 'Service not found.');
    }

    // Verify mobile matches (security)
    if (ltrim($dbMobile, '+91') !== ltrim($mobile, '+91') && 
        substr($dbMobile, -10) !== substr($mobile, -10)) {
        respond(false, 'Mobile number does not match service records.');
    }

    // Generate 4-digit OTP
    $otp = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    
    // Store OTP in session with 10-minute expiry
    session_start();
    $_SESSION['download_otp'] = [
        'otp' => $otp,
        'tracking_id' => $trackingId,
        'mobile' => $mobile,
        'generated_at' => date('Y-m-d H:i:s'),
        'attempts' => 0,
        'max_attempts' => 5,
        'expiry' => date('Y-m-d H:i:s', strtotime('+10 minutes')),
    ];

    // Send OTP via WhatsApp
    try {
        $whatsappResult = sendWhatsAppMessage(
            $mobile,
            'OTP_VERIFICATION',
            [
                'otp_code' => $otp
            ]
        );

        if (!$whatsappResult['success']) {
            error_log('OTP WhatsApp send failed for ' . $trackingId . ': ' . $whatsappResult['message']);
            respond(false, 'Failed to send OTP. Please try again.');
        }

        // Also log for audit
        error_log('[DOWNLOAD-OTP] Generated and sent OTP for tracking_id=' . $trackingId . ', mobile=' . $mobile);

        respond(true, 'OTP sent successfully to ' . substr($mobile, -10), [
            'otp_sent_to' => substr($mobile, -10)
        ]);

    } catch (Exception $e) {
        error_log('OTP send exception: ' . $e->getMessage());
        respond(false, 'Error sending OTP: ' . $e->getMessage());
    }
}

/* ===========================
   ACTION 2: VERIFY OTP
   =========================== */

if ($action === 'verify_otp') {
    if (!$trackingId || !$mobile || !$otp) {
        respond(false, 'Missing required parameters.');
    }

    session_start();

    // Check if OTP exists in session
    if (!isset($_SESSION['download_otp'])) {
        respond(false, 'No OTP generated. Please request a new OTP.');
    }

    $otpSession = $_SESSION['download_otp'];

    // Verify tracking ID and mobile match
    if ($otpSession['tracking_id'] !== $trackingId || $otpSession['mobile'] !== $mobile) {
        respond(false, 'OTP mismatch with current download request.');
    }

    // Check expiry
    if (strtotime('now') > strtotime($otpSession['expiry'])) {
        unset($_SESSION['download_otp']);
        respond(false, 'OTP has expired. Please request a new OTP.');
    }

    // Check attempts
    if ($otpSession['attempts'] >= $otpSession['max_attempts']) {
        unset($_SESSION['download_otp']);
        respond(false, 'Maximum OTP attempts exceeded. Please request a new OTP.');
    }

    // Verify OTP
    if ($otp !== $otpSession['otp']) {
        $_SESSION['download_otp']['attempts']++;
        $remaining = $otpSession['max_attempts'] - $_SESSION['download_otp']['attempts'];
        respond(false, 'Invalid OTP. ' . $remaining . ' attempts remaining.');
    }

    // OTP verified! Generate download token
    $downloadToken = bin2hex(random_bytes(32));
    $_SESSION['download_token_' . $downloadToken] = [
        'tracking_id' => $trackingId,
        'mobile' => $mobile,
        'file' => $_POST['file'] ?? null,
        'created_at' => date('Y-m-d H:i:s'),
        'expiry' => date('Y-m-d H:i:s', strtotime('+5 minutes')),
    ];

    // Clear OTP session
    unset($_SESSION['download_otp']);

    error_log('[DOWNLOAD-OTP] OTP verified successfully for tracking_id=' . $trackingId);

    respond(true, 'OTP verified! Preparing download...', [
        'download_token' => $downloadToken,
        'tracking_id' => $trackingId,
        'file' => $_POST['file'] ?? null
    ]);
}

respond(false, 'Invalid action.');
?>
