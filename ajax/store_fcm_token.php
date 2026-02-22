<?php
/**
 * Store FCM Token
 * Stores Firebase Cloud Messaging token for the current device/user
 */

// Clear any output buffering to ensure JSON is returned
if (ob_get_level()) {
    ob_end_clean();
}

require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Verify database connection
    if (!$connection) {
        throw new Exception('Database connection failed');
    }

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

    // Check if table exists, if not create it
    $tableCheckSQL = "SHOW TABLES LIKE 'fcm_tokens'";
    $tableCheckResult = mysqli_query($connection, $tableCheckSQL);
    
    if (mysqli_num_rows($tableCheckResult) == 0) {
        throw new Exception('Table fcm_tokens does not exist. Please create tables first using FCM_DATABASE_SETUP.sql');
    }

    // Check if token already exists
    $checkSQL = "SELECT id FROM fcm_tokens WHERE token = ?";
    $checkStmt = mysqli_prepare($connection, $checkSQL);
    
    if (!$checkStmt) {
        throw new Exception('Prepare failed: ' . mysqli_error($connection));
    }
    
    mysqli_stmt_bind_param($checkStmt, 's', $token);
    
    if (!mysqli_stmt_execute($checkStmt)) {
        throw new Exception('Execute failed: ' . mysqli_stmt_error($checkStmt));
    }
    
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
        
        if (!$updateStmt) {
            throw new Exception('Prepare failed: ' . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($updateStmt, 'issss', $user_id, $device_id, $ip_address, $user_agent, $token);
        
        if (!mysqli_stmt_execute($updateStmt)) {
            throw new Exception('Update failed: ' . mysqli_stmt_error($updateStmt));
        }
        
        $success = true;
        mysqli_stmt_close($updateStmt);
    } else {
        // Insert new token
        $insertSQL = "INSERT INTO fcm_tokens (token, user_id, device_id, ip_address, user_agent) 
                      VALUES (?, ?, ?, ?, ?)";
        $insertStmt = mysqli_prepare($connection, $insertSQL);
        
        if (!$insertStmt) {
            throw new Exception('Prepare failed: ' . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($insertStmt, 'sisss', $token, $user_id, $device_id, $ip_address, $user_agent);
        
        if (!mysqli_stmt_execute($insertStmt)) {
            throw new Exception('Insert failed: ' . mysqli_stmt_error($insertStmt));
        }
        
        $success = true;
        mysqli_stmt_close($insertStmt);
    }
    
    mysqli_stmt_close($checkStmt);

    if ($success) {
        // Set device_id cookie if not already set
        if (!isset($_COOKIE['device_id'])) {
            setcookie('device_id', $device_id, time() + (365 * 24 * 60 * 60), '/');
        }

        echo json_encode([
            'success' => true,
            'message' => 'FCM token stored successfully',
            'device_id' => $device_id,
            'token_preview' => substr($token, 0, 50) . '...'
        ]);
    } else {
        throw new Exception('Failed to store FCM token');
    }

} catch (Exception $e) {
    http_response_code(400);
    error_log('FCM Token Storage Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'database_error'
    ]);
    exit;
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}
?>

