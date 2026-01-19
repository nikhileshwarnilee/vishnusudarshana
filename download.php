<?php
// Secure file download handler for service files
require_once __DIR__ . '/config/db.php';

$trackingId = $_GET['tracking_id'] ?? '';
$fileName = $_GET['file'] ?? '';
$downloadToken = $_GET['token'] ?? '';

function errorMsg($msg) {
    echo '<!DOCTYPE html><html><head><title>Download Error</title><style>@import url(https://fonts.googleapis.com/css2?family=Marcellus&display=swap);html,body{font-family:\'Marcellus\',serif!important;}</style></head><body style="font-family:Arial,sans-serif;background:#f7f7fa;padding:32px;"><div style="max-width:400px;margin:0 auto;background:#fff1f0;color:#cf1322;padding:18px 14px;border-radius:10px;text-align:center;font-weight:600;">' . htmlspecialchars($msg) . '</div></body></html>';
    exit;
}

if (!$trackingId || !$fileName) {
    errorMsg('Invalid download request.');
}

// Verify download token if provided (required for OTP verification)
if ($downloadToken) {
    session_start();
    
    $tokenKey = 'download_token_' . $downloadToken;
    if (!isset($_SESSION[$tokenKey])) {
        error_log('[DOWNLOAD] Invalid or expired download token for tracking_id=' . $trackingId);
        errorMsg('Download token is invalid or expired. Please request a new one.');
    }
    
    $tokenData = $_SESSION[$tokenKey];
    
    // Verify token data matches
    if ($tokenData['tracking_id'] !== $trackingId) {
        error_log('[DOWNLOAD] Token tracking_id mismatch: ' . $tokenData['tracking_id'] . ' vs ' . $trackingId);
        errorMsg('Token does not match this tracking ID.');
    }
    
    // Check token expiry (5 minutes)
    if (time() > $tokenData['expiry']) {
        unset($_SESSION[$tokenKey]);
        error_log('[DOWNLOAD] Download token expired for tracking_id=' . $trackingId);
        errorMsg('Download token has expired. Please verify OTP again.');
    }
    
    // Token is valid - log successful verification
    error_log('[DOWNLOAD] OTP-verified download initiated for tracking_id=' . $trackingId . ', file=' . $fileName);
    
    // Clean up token after use
    unset($_SESSION[$tokenKey]);
} else {
    // No token provided - this is an old-style direct download (might fail with security reasons)
    // For now, we can allow it but log it
    error_log('[DOWNLOAD] Direct download attempt (no OTP token) for tracking_id=' . $trackingId);
}

// Fetch uploaded_files for tracking_id
$stmt = $pdo->prepare('SELECT uploaded_files FROM service_requests WHERE tracking_id = ?');
$stmt->execute([$trackingId]);
$uf = $stmt->fetchColumn();
if (!$uf) {
    errorMsg('No files found for this service.');
}
$files = json_decode($uf, true) ?: [];
$found = false;
foreach ($files as $file) {
    if ($file['file'] === $fileName) {
        $found = $file;
        break;
    }
}
if (!$found) {
    errorMsg('Requested file not found for this service.');
}
// Build file path
$baseDir = __DIR__ . '/uploads/services/' . $trackingId . '/';
$fullPath = $baseDir . $fileName;
if (!file_exists($fullPath)) {
    errorMsg('File does not exist on server.');
}
// Serve file
$mime = mime_content_type($fullPath);
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($found['name']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
