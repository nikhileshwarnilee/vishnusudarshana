<?php
/**
 * FCM Database Diagnostics
 * Check if database tables are created and properly configured
 */

require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

try {
    if (!$connection) {
        throw new Exception('Database connection failed');
    }

    $diagnostics = [
        'database_connected' => true,
        'tables' => [],
        'all_tables_exist' => true,
        'recommendations' => []
    ];

    // Check for fcm_tokens table
    $result = mysqli_query($connection, "SHOW TABLES LIKE 'fcm_tokens'");
    if (mysqli_num_rows($result) > 0) {
        $diagnostics['tables']['fcm_tokens'] = 'EXISTS ✓';
        
        // Check columns
        $columns = mysqli_query($connection, "DESCRIBE fcm_tokens");
        $columnNames = [];
        while ($col = mysqli_fetch_assoc($columns)) {
            $columnNames[] = $col['Field'];
        }
        $diagnostics['tables']['fcm_tokens_columns'] = $columnNames;
    } else {
        $diagnostics['tables']['fcm_tokens'] = 'MISSING ✗';
        $diagnostics['all_tables_exist'] = false;
        $diagnostics['recommendations'][] = 'Execute FCM_DATABASE_SETUP.sql to create fcm_tokens table';
    }

    // Check for fcm_topic_subscriptions table
    $result = mysqli_query($connection, "SHOW TABLES LIKE 'fcm_topic_subscriptions'");
    if (mysqli_num_rows($result) > 0) {
        $diagnostics['tables']['fcm_topic_subscriptions'] = 'EXISTS ✓';
    } else {
        $diagnostics['tables']['fcm_topic_subscriptions'] = 'MISSING ✗';
        $diagnostics['all_tables_exist'] = false;
    }

    // Check for fcm_notification_logs table
    $result = mysqli_query($connection, "SHOW TABLES LIKE 'fcm_notification_logs'");
    if (mysqli_num_rows($result) > 0) {
        $diagnostics['tables']['fcm_notification_logs'] = 'EXISTS ✓';
    } else {
        $diagnostics['tables']['fcm_notification_logs'] = 'MISSING ✗';
        $diagnostics['all_tables_exist'] = false;
    }

    // Check token count
    if (isset($diagnostics['tables']['fcm_tokens']) && strpos($diagnostics['tables']['fcm_tokens'], 'EXISTS') !== false) {
        $countResult = mysqli_query($connection, "SELECT COUNT(*) as cnt FROM fcm_tokens");
        $row = mysqli_fetch_assoc($countResult);
        $diagnostics['token_count'] = $row['cnt'];
    }

    // Check service account file
    $serviceAccountPath = __DIR__ . '/../firebase-service-account.json';
    $diagnostics['service_account_file'] = file_exists($serviceAccountPath) ? 'EXISTS ✓' : 'MISSING ✗';
    
    if (file_exists($serviceAccountPath)) {
        $jsonData = json_decode(file_get_contents($serviceAccountPath), true);
        $diagnostics['service_account_valid'] = isset($jsonData['client_email']) && isset($jsonData['private_key']);
    }

    echo json_encode([
        'success' => true,
        'diagnostics' => $diagnostics,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
