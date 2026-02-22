<?php
/**
 * Admin Notification Center
 * UI for managing and sending notifications
 */
require_once __DIR__ . '/../../helpers/favicon.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Center - Vishnusudarshana Admin</title>
    <?php echo vs_favicon_tags(); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Marcellus', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5e6d3 0%, #e8d5c4 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            background: #800000;
            color: #FFD700;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(128, 0, 0, 0.2);
        }

        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 0.95em;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            color: #800000;
            margin-bottom: 20px;
            font-size: 1.3em;
            border-bottom: 2px solid #FFD700;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            color: #333;
            margin-bottom: 5px;
            font-weight: 500;
        }

        input[type="text"],
        input[type="email"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            font-size: 0.95em;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        input[type="text"]:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #800000;
            box-shadow: 0 0 5px rgba(128, 0, 0, 0.2);
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .radio-option input[type="radio"] {
            cursor: pointer;
        }

        .radio-option label {
            margin: 0;
            cursor: pointer;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #800000;
            color: #FFD700;
        }

        .btn-primary:hover {
            background: #600000;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3);
        }

        .btn-secondary {
            background: #ddd;
            color: #333;
        }

        .btn-secondary:hover {
            background: #ccc;
        }

        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            display: none;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block !important;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block !important;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            display: block !important;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #ddd;
            border-top-color: #800000;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            background: #f9f9f9;
            border-left: 4px solid #FFD700;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 2px;
            font-size: 0.9em;
        }

        .notification-item.sent {
            border-left-color: #28a745;
        }

        .notification-item.error {
            border-left-color: #dc3545;
        }

        .notification-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }

        .notification-time {
            font-size: 0.8em;
            color: #999;
        }

        .stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: #f9f9f9;
            border-radius: 4px;
            padding: 15px;
            text-align: center;
        }

        .stat-number {
            font-size: 1.8em;
            font-weight: bold;
            color: #800000;
        }

        .stat-label {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }

        .recipient-input {
            display: none;
        }

        .recipient-input.active {
            display: block;
        }

        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
            }

            .radio-group {
                flex-direction: column;
                gap: 10px;
            }

            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 Notification Center</h1>
            <p>Send notifications to your app users via Firebase Cloud Messaging</p>
        </div>

        <div class="main-grid">
            <!-- Send Notification Card -->
            <div class="card">
                <h2>Send Notification</h2>

                <div id="alert" class="alert"></div>

                <form id="notificationForm">
                    <!-- Recipient Type Selection -->
                    <div class="form-group">
                        <label>Send To:</label>
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" id="broadcast" name="recipient_type" value="broadcast" checked>
                                <label for="broadcast">All Users</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="topic" name="recipient_type" value="topic">
                                <label for="topic">Topic</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="device" name="recipient_type" value="device">
                                <label for="device">Device</label>
                            </div>
                        </div>
                    </div>

                    <!-- Topic Input -->
                    <div id="topic-input" class="recipient-input">
                        <div class="form-group">
                            <label for="topic_name">Topic Name *</label>
                            <input type="text" id="topic_name" name="topic" placeholder="e.g., news, promotions, updates">
                        </div>
                    </div>

                    <!-- Device Token Input -->
                    <div id="device-input" class="recipient-input">
                        <div class="form-group">
                            <label for="device_token">Device Token *</label>
                            <input type="text" id="device_token" name="device_token" placeholder="Paste FCM token here">
                        </div>
                    </div>

                    <!-- Notification Content -->
                    <div class="form-group">
                        <label for="title">Title *</label>
                        <input type="text" id="title" name="title" placeholder="Notification title" required>
                    </div>

                    <div class="form-group">
                        <label for="body">Message *</label>
                        <textarea id="body" name="body" placeholder="Notification message" required></textarea>
                    </div>

                    <!-- Additional Options -->
                    <details style="margin-bottom: 15px;">
                        <summary style="cursor: pointer; color: #800000; font-weight: 500;">Advanced Options</summary>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                            <div class="form-group">
                                <label for="click_action">Link/Action URL</label>
                                <input type="text" id="click_action" placeholder="/services.php">
                            </div>
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" id="require_interaction"> Require user interaction
                                </label>
                            </div>
                        </div>
                    </details>

                    <!-- Buttons -->
                    <div class="button-group">
                        <button type="submit" class="btn-primary">
                            <span id="submit-text">Send Notification</span>
                            <span id="loading" class="loading" style="display: none;"></span>
                        </button>
                        <button type="reset" class="btn-secondary">Clear</button>
                    </div>
                </form>
            </div>

            <!-- History Card -->
            <div class="card">
                <h2>Recent Notifications</h2>

                <div class="stats">
                    <div class="stat-box">
                        <div class="stat-number" id="total-notifications">0</div>
                        <div class="stat-label">Total Sent</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number" id="today-notifications">0</div>
                        <div class="stat-label">Today</div>
                    </div>
                </div>

                <div class="notification-list" id="notification-list">
                    <p style="color: #999; text-align: center;">Loading notifications...</p>
                </div>

                <button style="width: 100%; margin-top: 15px;" class="btn-secondary" onclick="loadNotifications()">
                    Refresh History
                </button>
            </div>
        </div>
    </div>

    <script>
        // Handle recipient type change
        document.querySelectorAll('input[name="recipient_type"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                document.querySelectorAll('.recipient-input').forEach(input => {
                    input.classList.remove('active');
                });

                const type = e.target.value;
                if (type === 'topic') {
                    document.getElementById('topic-input').classList.add('active');
                } else if (type === 'device') {
                    document.getElementById('device-input').classList.add('active');
                }
            });
        });

        // Handle form submission
        document.getElementById('notificationForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            document.getElementById('submit-text').style.display = 'none';
            document.getElementById('loading').style.display = 'inline-block';

            try {
                const formData = new FormData(e.target);
                const data = {
                    title: formData.get('title'),
                    body: formData.get('body'),
                    recipient_type: formData.get('recipient_type'),
                };

                if (data.recipient_type === 'topic') {
                    data.topic = formData.get('topic');
                } else if (data.recipient_type === 'device') {
                    data.device_token = formData.get('device_token');
                }

                if (formData.get('click_action')) {
                    data.options = {
                        clickAction: formData.get('click_action')
                    };
                }

                if (formData.get('require_interaction')) {
                    if (!data.options) data.options = {};
                    data.options.requireInteraction = true;
                }

                const response = await fetch('./send.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                const alertDiv = document.getElementById('alert');
                if (result.success) {
                    alertDiv.className = 'alert alert-success';
                    alertDiv.textContent = '✓ ' + result.message;
                    e.target.reset();
                } else {
                    alertDiv.className = 'alert alert-error';
                    alertDiv.textContent = '✗ ' + result.message;
                }

                // Reload history
                setTimeout(() => loadNotifications(), 500);

            } catch (error) {
                const alertDiv = document.getElementById('alert');
                alertDiv.className = 'alert alert-error';
                alertDiv.textContent = '✗ Error: ' + error.message;
            } finally {
                submitBtn.disabled = false;
                document.getElementById('submit-text').style.display = 'inline';
                document.getElementById('loading').style.display = 'none';
            }
        });

        // Load notification history
        async function loadNotifications() {
            try {
                const response = await fetch('./history.php?limit=20');
                const result = await response.json();

                if (result.success) {
                    const list = document.getElementById('notification-list');
                    
                    if (result.notifications.length === 0) {
                        list.innerHTML = '<p style="color: #999; text-align: center;">No notifications sent yet</p>';
                    } else {
                        let html = '';
                        result.notifications.forEach(notif => {
                            const time = new Date(notif.sent_at);
                            const timeStr = time.toLocaleString();
                            html += `
                                <div class="notification-item ${notif.status}">
                                    <div class="notification-title">${notif.title}</div>
                                    <div style="color: #666; font-size: 0.85em; margin-bottom: 5px;">${notif.body}</div>
                                    <div style="display: flex; justify-content: space-between; font-size: 0.8em;">
                                        <span>${notif.type}</span>
                                        <span class="notification-time">${timeStr}</span>
                                    </div>
                                </div>
                            `;
                        });
                        list.innerHTML = html;

                        // Update stats
                        document.getElementById('total-notifications').textContent = result.count;
                        
                        // Count today's notifications
                        const today = new Date().toDateString();
                        const todayCount = result.notifications.filter(n => 
                            new Date(n.sent_at).toDateString() === today
                        ).length;
                        document.getElementById('today-notifications').textContent = todayCount;
                    }
                }
            } catch (error) {
                console.error('Error loading notifications:', error);
            }
        }

        // Load notifications on page load
        document.addEventListener('DOMContentLoaded', loadNotifications);
    </script>
</body>
</html>
