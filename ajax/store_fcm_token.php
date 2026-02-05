<?php
/**
 * Store FCM Token
 * Stores Firebase Cloud Messaging token for the current device/user
 */

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

try {
    // Get JSON payload
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['token'])) {
        throw new Exception('FCM token is required');
    }

    $token = sanitize($data['token']);
    
    // Get device/session info
    $device_id = isset($_COOKIE['device_id']) ? $_COOKIE['device_id'] : bin2hex(random_bytes(16));
    $user_id = null;
    
    // Check if user is logged in (if you have authentication system)
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }


    // Check if token already exists
    $checkSQL = "SELECT id FROM fcm_tokens WHERE token = ?";
    $checkStmt = mysqli_prepare($connection, $checkSQL);
    mysqli_stmt_bind_param($checkStmt, 's', $token);
    mysqli_stmt_execute($checkStmt);
    $result = mysqli_stmt_get_result($checkStmt);

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (mysqli_num_rows($result) > 0) {
        // Update existing token
        $updateSQL = "UPDATE fcm_tokens 
                      SET user_id = ?, device_id = ?, ip_address = ?, user_agent = ?, 
                          last_updated = CURRENT_TIMESTAMP, is_active = TRUE
                      WHERE token = ?";
        $updateStmt = mysqli_prepare($connection, $updateSQL);
        mysqli_stmt_bind_param($updateStmt, 'issss', $user_id, $device_id, $ip_address, $user_agent, $token);
        $success = mysqli_stmt_execute($updateStmt);
    } else {
        // Insert new token
        $insertSQL = "INSERT INTO fcm_tokens (token, user_id, device_id, ip_address, user_agent) 
                      VALUES (?, ?, ?, ?, ?)";
        $insertStmt = mysqli_prepare($connection, $insertSQL);
        mysqli_stmt_bind_param($insertStmt, 'sisss', $token, $user_id, $device_id, $ip_address, $user_agent);
        $success = mysqli_stmt_execute($insertStmt);
    }

    if ($success) {
        // Set device_id cookie if not already set
        if (!isset($_COOKIE['device_id'])) {
            setcookie('device_id', $device_id, time() + (365 * 24 * 60 * 60), '/');
        }

        echo json_encode([
            'success' => true,
            'message' => 'FCM token stored successfully',
            'device_id' => $device_id
        ]);
    } else {
        throw new Exception('Failed to store FCM token: ' . mysqli_error($connection));
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}
?>
