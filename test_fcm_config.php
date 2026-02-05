<?php
/**
 * FCM v1 API Configuration Test Script
 * Run this file to verify your Firebase service account setup
 * 
 * Access via: http://localhost/test_fcm_config.php
 */

require_once __DIR__ . '/config/fcm_config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Google\Auth\Credentials\ServiceAccountCredentials;

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FCM v1 API Configuration Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #800000; margin-bottom: 20px; }
        .test-item { padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #ddd; }
        .success { background: #d4edda; border-left-color: #28a745; color: #155724; }
        .error { background: #f8d7da; border-left-color: #dc3545; color: #721c24; }
        .info { background: #d1ecf1; border-left-color: #17a2b8; color: #0c5460; }
        .warning { background: #fff3cd; border-left-color: #ffc107; color: #856404; }
        .label { font-weight: 600; margin-bottom: 5px; }
        .value { font-family: 'Courier New', monospace; font-size: 0.9em; background: rgba(0,0,0,0.05); padding: 8px; border-radius: 4px; margin-top: 5px; word-break: break-all; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }
        .section { margin-top: 30px; }
        .icon { font-size: 1.2em; margin-right: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔥 Firebase Cloud Messaging v1 API - Configuration Test</h1>
        
        <?php
        $allTestsPassed = true;
        
        // Test 1: Check if constants are defined
        echo '<div class="section">';
        echo '<h2>📋 Configuration Constants</h2>';
        
        if (defined('FCM_PROJECT_ID')) {
            echo '<div class="test-item success">';
            echo '<div class="label">✓ FCM_PROJECT_ID</div>';
            echo '<div class="value">' . htmlspecialchars(FCM_PROJECT_ID) . '</div>';
            echo '</div>';
        } else {
            echo '<div class="test-item error"><span class="icon">✗</span> FCM_PROJECT_ID not defined</div>';
            $allTestsPassed = false;
        }
        
        if (defined('FCM_SERVICE_ACCOUNT_PATH')) {
            echo '<div class="test-item success">';
            echo '<div class="label">✓ FCM_SERVICE_ACCOUNT_PATH</div>';
            echo '<div class="value">' . htmlspecialchars(FCM_SERVICE_ACCOUNT_PATH) . '</div>';
            echo '</div>';
        } else {
            echo '<div class="test-item error"><span class="icon">✗</span> FCM_SERVICE_ACCOUNT_PATH not defined</div>';
            $allTestsPassed = false;
        }
        echo '</div>';
        
        // Test 2: Check if service account file exists
        echo '<div class="section">';
        echo '<h2>📁 Service Account File</h2>';
        
        if (file_exists(FCM_SERVICE_ACCOUNT_PATH)) {
            echo '<div class="test-item success">';
            echo '<span class="icon">✓</span> Service account file found';
            echo '<div class="value">Path: ' . htmlspecialchars(realpath(FCM_SERVICE_ACCOUNT_PATH)) . '</div>';
            echo '</div>';
            
            // Test 3: Check if file is readable
            if (is_readable(FCM_SERVICE_ACCOUNT_PATH)) {
                echo '<div class="test-item success"><span class="icon">✓</span> File is readable</div>';
                
                // Test 4: Try to parse JSON
                $jsonContent = file_get_contents(FCM_SERVICE_ACCOUNT_PATH);
                $serviceAccount = json_decode($jsonContent, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo '<div class="test-item success"><span class="icon">✓</span> JSON file is valid</div>';
                    
                    // Check required fields
                    $requiredFields = ['type', 'project_id', 'private_key', 'client_email'];
                    $missingFields = [];
                    
                    foreach ($requiredFields as $field) {
                        if (!isset($serviceAccount[$field])) {
                            $missingFields[] = $field;
                        }
                    }
                    
                    if (empty($missingFields)) {
                        echo '<div class="test-item success">';
                        echo '<span class="icon">✓</span> All required fields present';
                        echo '<div class="value">';
                        echo 'Type: ' . htmlspecialchars($serviceAccount['type'] ?? 'N/A') . '<br>';
                        echo 'Project ID: ' . htmlspecialchars($serviceAccount['project_id'] ?? 'N/A') . '<br>';
                        echo 'Client Email: ' . htmlspecialchars($serviceAccount['client_email'] ?? 'N/A');
                        echo '</div>';
                        echo '</div>';
                    } else {
                        echo '<div class="test-item error">';
                        echo '<span class="icon">✗</span> Missing required fields: ' . implode(', ', $missingFields);
                        echo '</div>';
                        $allTestsPassed = false;
                    }
                } else {
                    echo '<div class="test-item error"><span class="icon">✗</span> Invalid JSON: ' . json_last_error_msg() . '</div>';
                    $allTestsPassed = false;
                }
            } else {
                echo '<div class="test-item error"><span class="icon">✗</span> File exists but is not readable. Check file permissions.</div>';
                $allTestsPassed = false;
            }
        } else {
            echo '<div class="test-item error">';
            echo '<span class="icon">✗</span> <strong>Service account file not found!</strong><br><br>';
            echo '<strong>What to do:</strong><br>';
            echo '1. Go to <a href="https://console.firebase.google.com/" target="_blank">Firebase Console</a><br>';
            echo '2. Select your project: <code>vishnusudarshana-cfcf7</code><br>';
            echo '3. Go to Project Settings → Service Accounts<br>';
            echo '4. Click "Generate new private key"<br>';
            echo '5. Save as: <code>firebase-service-account.json</code><br>';
            echo '6. Place in: <code>' . htmlspecialchars(dirname(FCM_SERVICE_ACCOUNT_PATH)) . '</code>';
            echo '</div>';
            $allTestsPassed = false;
        }
        echo '</div>';
        
        // Test 5: Try to generate an access token
        if ($allTestsPassed) {
            echo '<div class="section">';
            echo '<h2>🔐 OAuth 2.0 Token Generation</h2>';
            
            try {
                $credentials = new ServiceAccountCredentials(
                    FCM_SCOPE,
                    FCM_SERVICE_ACCOUNT_PATH
                );
                
                $token = $credentials->fetchAuthToken();
                
                if (isset($token['access_token']) && !empty($token['access_token'])) {
                    echo '<div class="test-item success">';
                    echo '<span class="icon">✓</span> <strong>Access token generated successfully!</strong><br>';
                    echo '<div class="value">';
                    echo 'Token (first 50 chars): ' . htmlspecialchars(substr($token['access_token'], 0, 50)) . '...<br>';
                    if (isset($token['expires_in'])) {
                        echo 'Expires in: ' . $token['expires_in'] . ' seconds (~' . round($token['expires_in'] / 60) . ' minutes)';
                    }
                    echo '</div>';
                    echo '</div>';
                } else {
                    echo '<div class="test-item error"><span class="icon">✗</span> Token generated but access_token field is missing</div>';
                    $allTestsPassed = false;
                }
            } catch (Exception $e) {
                echo '<div class="test-item error">';
                echo '<span class="icon">✗</span> <strong>Failed to generate access token</strong><br><br>';
                echo 'Error: ' . htmlspecialchars($e->getMessage()) . '<br><br>';
                echo '<strong>Possible solutions:</strong><br>';
                echo '• Verify the service account JSON is valid and complete<br>';
                echo '• Enable Firebase Cloud Messaging API in <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a><br>';
                echo '• Check that the service account has necessary permissions';
                echo '</div>';
                $allTestsPassed = false;
            }
            echo '</div>';
        }
        
        // Final summary
        echo '<div class="section">';
        if ($allTestsPassed) {
            echo '<div class="test-item success" style="font-size: 1.1em;">';
            echo '<span class="icon">🎉</span> <strong>All tests passed! Your FCM v1 API is configured correctly.</strong><br><br>';
            echo 'You can now send push notifications via:<br>';
            echo '• Admin Panel → Settings → Push Notifications<br>';
            echo '• Direct API: <code>POST /send_notification.php</code>';
            echo '</div>';
        } else {
            echo '<div class="test-item error" style="font-size: 1.1em;">';
            echo '<span class="icon">⚠️</span> <strong>Configuration incomplete. Please fix the errors above.</strong><br><br>';
            echo 'See <a href="FCM_V1_SETUP_GUIDE.md" target="_blank">FCM_V1_SETUP_GUIDE.md</a> for detailed instructions.';
            echo '</div>';
        }
        echo '</div>';
        ?>
        
        <div class="section">
            <div class="test-item info">
                <strong>📚 Next Steps:</strong><br>
                1. Test sending notifications via Admin Panel<br>
                2. Check Firebase Console for delivery reports<br>
                3. Monitor token database for active subscriptions<br>
                4. Delete this test file (<code>test_fcm_config.php</code>) in production
            </div>
        </div>
    </div>
</body>
</html>
