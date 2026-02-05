<?php
/**
 * FCM Debug & Test Endpoint
 * Shows current FCM token status and allows testing
 */

require_once __DIR__ . '/../../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FCM Debug - Vishnusudarshana</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5e6d3 0%, #e8d5c4 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            background: #800000;
            color: #FFD700;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .header h1 {
            font-size: 1.8em;
            margin-bottom: 5px;
        }

        .header p {
            opacity: 0.9;
            font-size: 0.9em;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            color: #800000;
            margin-bottom: 15px;
            font-size: 1.2em;
            border-bottom: 2px solid #FFD700;
            padding-bottom: 10px;
        }

        .status-box {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            line-height: 1.6;
        }

        .status-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .status-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .status-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .status-label {
            font-weight: bold;
            color: #333;
        }

        button {
            background: #800000;
            color: #FFD700;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            margin-right: 10px;
            margin-top: 10px;
            transition: all 0.3s ease;
        }

        button:hover {
            background: #600000;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3);
        }

        button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .token-box {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 12px;
            border-radius: 4px;
            word-break: break-all;
            font-family: 'Courier New', monospace;
            font-size: 0.8em;
            margin: 10px 0;
        }

        .checklist {
            list-style: none;
            padding: 0;
        }

        .checklist li {
            padding: 8px 0;
            padding-left: 25px;
            position: relative;
        }

        .checklist li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
        }

        .checklist li.error::before {
            content: '✗';
            color: #dc3545;
        }

        .loading {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid #800000;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .db-tokens {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .db-token-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-family: 'Courier New', monospace;
            font-size: 0.8em;
        }

        .db-token-item:last-child {
            border-bottom: none;
        }
    </style>

    <!-- Firebase Configuration -->
    <script src="../../config/firebase-config.js"></script>
    
    <!-- Firebase SDK -->
    <script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js"></script>
    
    <!-- Firebase Cloud Messaging Service -->
    <script src="../../assets/js/firebase-messaging.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 FCM Debug Console</h1>
            <p>Check Firebase Cloud Messaging token status and connectivity</p>
        </div>

        <!-- Browser Status -->
        <div class="card">
            <h2>Browser Status</h2>
            <ul class="checklist">
                <li id="service-worker-check">Service Worker Support</li>
                <li id="notification-check">Notification API Support</li>
                <li id="permission-check">Notification Permission</li>
                <li id="local-token-check">FCM Token in Local Storage</li>
                <li id="firebase-check">Firebase SDK Loaded</li>
            </ul>
        </div>

        <!-- Current Token -->
        <div class="card">
            <h2>Current FCM Token</h2>
            <div id="token-status" class="status-box status-info">
                <span class="loading"></span> Checking...
            </div>
            <button onclick="checkBrowserStatus()">🔄 Refresh Status</button>
            <button onclick="requestPermission()">📢 Request Permission</button>
        </div>

        <!-- System Diagnostics -->
        <div class="card">
            <h2>System Diagnostics</h2>
            <p style="margin-bottom: 15px; color: #666; font-size: 0.9em;">
                Check if database tables are created and properly configured
            </p>
            <div id="diagnostics-status">Loading diagnostics...</div>
            <button onclick="checkDiagnostics()">🔍 Check System Status</button>
        </div>

        <!-- Database Tokens -->
        <div class="card">
            <h2>Database Tokens (fcm_tokens table)</h2>
            <div id="db-status">Loading...</div>
            <button onclick="loadDatabaseTokens()">🔄 Refresh from DB</button>
        </div>

        <!-- Test Notification -->
        <div class="card">
            <h2>Send Test Notification</h2>
            <p style="margin-bottom: 15px; color: #666; font-size: 0.9em;">
                ⚠️ First get a token, then send a test notification to your current device.
            </p>
            <div>
                <input type="text" id="test-title" placeholder="Title" value="Test Notification" style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px;">
                <textarea id="test-body" placeholder="Message body" style="width: 100%; padding: 8px; margins-bottom: 10px; border: 1px solid #ddd; border-radius: 4px; height: 60px;">This is a test notification from FCM Debug Console</textarea>
                <button onclick="sendTestNotification()">📤 Send Test Notification</button>
            </div>
            <div id="test-response" style="margin-top: 15px;"></div>
        </div>

        <!-- Logs -->
        <div class="card">
            <h2>Console Output</h2>
            <div id="logs" class="status-box status-info" style="max-height: 200px; overflow-y: auto;">
                Ready. Open browser DevTools (F12) to see detailed logs.
            </div>
            <button onclick="clearLogs()">Clear Logs</button>
        </div>
    </div>

    <script>
        // Log messages to page
        let logMessages = [];
        const originalLog = console.log;
        console.log = function(...args) {
            originalLog(...args);
            const msg = args.map(a => typeof a === 'object' ? JSON.stringify(a) : a).join(' ');
            logMessages.push(new Date().toLocaleTimeString() + ': ' + msg);
            updateLogsDisplay();
        };

        function updateLogsDisplay() {
            const logsDiv = document.getElementById('logs');
            logsDiv.innerHTML = logMessages.slice(-10).join('<br>');
            logsDiv.scrollTop = logsDiv.scrollHeight;
        }

        function clearLogs() {
            logMessages = [];
            updateLogsDisplay();
        }

        // Check browser status
        function checkBrowserStatus() {
            console.log('Checking browser status...');

            // Service Worker
            if ('serviceWorker' in navigator) {
                document.getElementById('service-worker-check').classList.remove('error');
                console.log('✓ Service Workers supported');
            } else {
                document.getElementById('service-worker-check').classList.add('error');
                console.log('✗ Service Workers NOT supported');
            }

            // Notifications
            if ('Notification' in window) {
                document.getElementById('notification-check').classList.remove('error');
                console.log('✓ Notifications API supported');
            } else {
                document.getElementById('notification-check').classList.add('error');
                console.log('✗ Notifications API NOT supported');
            }

            // Permission
            if ('Notification' in window) {
                const perm = Notification.permission;
                const check = document.getElementById('permission-check');
                check.innerHTML = `<span class="status-label">Notification Permission:</span> ${perm}`;
                if (perm === 'granted') {
                    check.classList.remove('error');
                } else {
                    check.classList.add('error');
                }
                console.log('Permission: ' + perm);
            }

            // Local Token
            const token = localStorage.getItem('fcmToken');
            const tokenCheck = document.getElementById('local-token-check');
            if (token) {
                tokenCheck.innerHTML = `<span class="status-label">Token:</span> ${token.substring(0, 50)}...`;
                tokenCheck.classList.remove('error');
                console.log('✓ Token in localStorage');
            } else {
                tokenCheck.innerHTML = '<span class="status-label">Token:</span> Not found in localStorage';
                tokenCheck.classList.add('error');
                console.log('✗ No token in localStorage');
            }

            // Firebase
            if (typeof firebase !== 'undefined') {
                document.getElementById('firebase-check').classList.remove('error');
                console.log('✓ Firebase SDK loaded');
            } else {
                document.getElementById('firebase-check').classList.add('error');
                console.log('✗ Firebase SDK NOT loaded');
            }

            displayToken();
        }

        // Display current token
        function displayToken() {
            const token = localStorage.getItem('fcmToken');
            const box = document.getElementById('token-status');

            if (token) {
                box.innerHTML = `<div class="status-success"><strong>✓ Token found:</strong><div class="token-box">${token}</div></div>`;
            } else {
                box.innerHTML = `<div class="status-error"><strong>✗ No token</strong><br>Allow notifications and refresh to generate token</div>`;
            }
        }

        // Request permission and generate token
        async function requestPermission() {
            console.log('Requesting notification permission...');
            if (typeof initializeFirebaseCM !== 'undefined') {
                const success = await initializeFirebaseCM();
                if (success) {
                    console.log('✓ FCM initialized successfully');
                    setTimeout(checkBrowserStatus, 1000);
                } else {
                    console.log('✗ FCM initialization failed');
                }
            } else {
                console.log('✗ initializeFirebaseCM function not available');
            }
        }

        // Check system diagnostics
        async function checkDiagnostics() {
            const box = document.getElementById('diagnostics-status');
            box.innerHTML = '<span class="loading"></span> Checking...';
            console.log('Checking system diagnostics...');

            try {
                const response = await fetch('./diagnostics.php');
                const data = await response.json();

                if (data.success) {
                    const diag = data.diagnostics;
                    let html = '<div class="status-box status-info">';
                    
                    // Database connection
                    html += `<div><strong>Database Connected:</strong> ${diag.database_connected ? '✓ Yes' : '✗ No'}</div>`;
                    
                    // Tables status
                    html += '<div style="margin-top: 10px;"><strong>Database Tables:</strong><ul style="list-style: none; margin-top: 5px; margin-left: 0;">';
                    for (const [tableName, status] of Object.entries(diag.tables)) {
                        if (!tableName.includes('_columns')) {
                            const isOk = status.includes('EXISTS');
                            html += `<li style="color: ${isOk ? '#155724' : '#721c24'}; margin-bottom: 5px;">${tableName}: ${status}</li>`;
                        }
                    }
                    html += '</ul></div>';
                    
                    // Token count
                    if (diag.token_count !== undefined) {
                        html += `<div style="margin-top: 10px;"><strong>Stored Tokens:</strong> ${diag.token_count}</div>`;
                    }
                    
                    // Service account
                    html += `<div style="margin-top: 10px;"><strong>Service Account:</strong> ${diag.service_account_file}</div>`;
                    
                    // Recommendations
                    if (diag.recommendations && diag.recommendations.length > 0) {
                        html += '<div style="margin-top: 10px; background: #fff3cd; border: 1px solid #ffc107; padding: 10px; border-radius: 4px;"><strong style="color: #856404;">⚠️ Recommendations:</strong><ul style="margin: 5px 0 0 20px;">';
                        diag.recommendations.forEach(rec => {
                            html += `<li style="color: #856404;">${rec}</li>`;
                        });
                        html += '</ul></div>';
                    }
                    
                    html += '</div>';
                    box.innerHTML = html;
                    console.log('✓ Diagnostics loaded');
                } else {
                    box.innerHTML = `<div class="status-error"><strong>Error:</strong> ${data.message}</div>`;
                    console.log('✗ Error: ' + data.message);
                }
            } catch (error) {
                box.innerHTML = `<div class="status-error"><strong>Error:</strong> ${error.message}</div>`;
                console.log('✗ Error: ' + error.message);
            }
        }

        // Load tokens from database
        async function loadDatabaseTokens() {
            const box = document.getElementById('db-status');
            box.innerHTML = '<span class="loading"></span> Loading...';

            try {
                const response = await fetch('./debug.php?action=get_tokens');
                const text = await response.text();
                
                // Try to parse as JSON
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response. Response: ' + text.substring(0, 100));
                }

                if (data.success && data.tokens.length > 0) {
                    let html = `<p>Found <strong>${data.tokens.length}</strong> token(s):</p><div class="db-tokens">`;
                    data.tokens.forEach(token => {
                        html += `<div class="db-token-item"><strong>ID:</strong> ${token.id}<br><strong>Token:</strong> ${token.token.substring(0, 60)}...<br><strong>Created:</strong> ${token.created_at}<br><strong>Active:</strong> ${token.is_active ? 'Yes' : 'No'}</div>`;
                    });
                    html += '</div>';
                    box.innerHTML = html;
                    console.log('✓ Loaded ' + data.tokens.length + ' tokens from database');
                } else {
                    box.innerHTML = '<div class="status-error"><strong>No tokens in database</strong><br>Allow notifications first to generate a token</div>';
                    console.log('✗ No tokens found in database');
                }
            } catch (error) {
                box.innerHTML = '<div class="status-error"><strong>Error loading tokens:</strong> ' + error.message + '</div>';
                console.log('✗ Error: ' + error.message);
            }
        }

        // Send test notification
        async function sendTestNotification() {
            const token = localStorage.getItem('fcmToken');
            if (!token) {
                alert('No FCM token found. Allow notifications first.');
                return;
            }

            const title = document.getElementById('test-title').value || 'Test';
            const body = document.getElementById('test-body').value || 'Test message';
            const responseDiv = document.getElementById('test-response');

            responseDiv.innerHTML = '<span class="loading"></span> Sending...';
            console.log('Sending test notification to device token...');

            try {
                const response = await fetch('./send.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        title: title,
                        body: body,
                        recipient_type: 'device',
                        device_token: token
                    })
                });

                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                }

                if (data.success) {
                    responseDiv.innerHTML = `<div class="status-success"><strong>✓ Notification sent!</strong><br>Check your notifications<br><pre style="font-size: 0.8em; margin-top: 10px; white-space: pre-wrap;">${JSON.stringify(data, null, 2)}</pre></div>`;
                    console.log('✓ Notification sent successfully');
                } else {
                    responseDiv.innerHTML = `<div class="status-error"><strong>✗ Failed to send</strong><br>${data.message}<br><pre style="font-size: 0.8em; margin-top: 10px; white-space: pre-wrap;">${JSON.stringify(data, null, 2)}</pre></div>`;
                    console.log('✗ Error: ' + data.message);
                }
            } catch (error) {
                responseDiv.innerHTML = `<div class="status-error"><strong>Error:</strong> ${error.message}</div>`;
                console.log('✗ Error: ' + error.message);
            }
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            console.log('FCM Debug Console loaded');
            checkBrowserStatus();
            checkDiagnostics();
            loadDatabaseTokens();
            setInterval(loadDatabaseTokens, 10000); // Refresh every 10 seconds
        });
    </script>
</body>
</html>

<?php
// Handle AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'get_tokens') {
    header('Content-Type: application/json');
    try {
        $query = "SELECT id, token, created_at, is_active FROM fcm_tokens ORDER BY created_at DESC LIMIT 20";
        $result = mysqli_query($connection, $query);
        
        if (!$result) {
            throw new Exception('Query error: ' . mysqli_error($connection));
        }
        
        $tokens = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $tokens[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'tokens' => $tokens
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
?>
