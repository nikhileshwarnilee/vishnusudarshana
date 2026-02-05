<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Get FCM token statistics
$stats = [];

// Total tokens
$stmt = $pdo->query("SELECT COUNT(*) FROM fcm_tokens");
$stats['total'] = $stmt->fetchColumn();

// Active tokens
$stmt = $pdo->query("SELECT COUNT(*) FROM fcm_tokens WHERE is_active = 1");
$stats['active'] = $stmt->fetchColumn();

// Inactive tokens
$stats['inactive'] = $stats['total'] - $stats['active'];

// Tokens added today
$stmt = $pdo->prepare("SELECT COUNT(*) FROM fcm_tokens WHERE DATE(created_at) = ?");
$stmt->execute([date('Y-m-d')]);
$stats['today'] = $stmt->fetchColumn();

// Recent tokens
$stmt = $pdo->query("SELECT token_hash, created_at, last_seen, is_active FROM fcm_tokens ORDER BY created_at DESC LIMIT 10");
$recentTokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Read notification log
$logFile = __DIR__ . '/../logs/fcm_notifications.log';
$logLines = [];
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logLines = array_slice(array_reverse($lines), 0, 50); // Get last 50 lines
}

// Check if service account exists
$serviceAccountExists = file_exists(__DIR__ . '/../config/firebase-service-account.json');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FCM Monitoring Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        html,body{font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif!important;}
        .admin-container { max-width: 1400px; margin: 0 auto; padding: 24px 12px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #800000;
        }
        
        .stat-card.active { border-left-color: #28a745; }
        .stat-card.inactive { border-left-color: #dc3545; }
        .stat-card.today { border-left-color: #17a2b8; }
        
        .stat-value {
            font-size: 2.5em;
            font-weight: 700;
            color: #800000;
            margin: 8px 0;
        }
        
        .stat-card.active .stat-value { color: #28a745; }
        .stat-card.inactive .stat-value { color: #dc3545; }
        .stat-card.today .stat-value { color: #17a2b8; }
        
        .stat-label {
            font-size: 0.9em;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .section-card {
            background: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .section-title {
            font-size: 1.3em;
            font-weight: 700;
            color: #333;
        }
        
        .log-container {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 16px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            max-height: 500px;
            overflow-y: auto;
            line-height: 1.6;
        }
        
        .log-line {
            margin: 4px 0;
            padding: 4px 0;
        }
        
        .log-line.error { color: #f48771; }
        .log-line.success { color: #89d185; }
        .log-line.warning { color: #dcdcaa; }
        .log-line.info { color: #9cdcfe; }
        
        .btn-refresh {
            padding: 10px 20px;
            background: #800000;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-refresh:hover {
            background: #600000;
        }
        
        .btn-test {
            padding: 10px 20px;
            background: #28a745;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .btn-test:hover {
            background: #218838;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .config-status {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .config-status.ok {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .config-status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .token-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .token-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        
        .token-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .token-table tr:hover {
            background: #f8f9fa;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/top-menu.php'; ?>
<div class="admin-container">
    <h1 style="margin-bottom: 24px; color: #800000;">🔔 FCM Notification Monitoring</h1>
    
    <!-- Configuration Status -->
    <?php if ($serviceAccountExists): ?>
        <div class="config-status ok">
            ✅ <strong>FCM v1 API Configured</strong> - Service account file detected
        </div>
    <?php else: ?>
        <div class="config-status error">
            ⚠️ <strong>FCM v1 API Not Configured</strong> - Service account file missing. <a href="../test_fcm_config.php">Run configuration test</a>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Subscribers</div>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
        </div>
        
        <div class="stat-card active">
            <div class="stat-label">Active Tokens</div>
            <div class="stat-value"><?= number_format($stats['active']) ?></div>
        </div>
        
        <div class="stat-card inactive">
            <div class="stat-label">Inactive Tokens</div>
            <div class="stat-value"><?= number_format($stats['inactive']) ?></div>
        </div>
        
        <div class="stat-card today">
            <div class="stat-label">New Today</div>
            <div class="stat-value"><?= number_format($stats['today']) ?></div>
        </div>
    </div>
    
    <!-- Recent Notification Logs -->
    <div class="section-card">
        <div class="section-header">
            <h2 class="section-title">📋 Recent Notification Logs</h2>
            <div>
                <a href="?refresh=1" class="btn-refresh">🔄 Refresh</a>
                <a href="push-notifications.php" class="btn-test">📤 Send Test Notification</a>
            </div>
        </div>
        
        <?php if (!empty($logLines)): ?>
            <div class="log-container">
                <?php foreach ($logLines as $line): 
                    $class = 'info';
                    if (stripos($line, '[ERROR]') !== false || stripos($line, '[CRITICAL]') !== false) {
                        $class = 'error';
                    } elseif (stripos($line, '[SUCCESS]') !== false) {
                        $class = 'success';
                    } elseif (stripos($line, '[WARNING]') !== false) {
                        $class = 'warning';
                    }
                ?>
                    <div class="log-line <?= $class ?>"><?= htmlspecialchars($line) ?></div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-data">
                📭 No notification logs yet. Send your first notification to see logs here.
            </div>
        <?php endif; ?>
        
        <p style="margin-top: 12px; font-size: 0.9em; color: #666;">
            <strong>Log Location:</strong> <code>logs/fcm_notifications.log</code>
        </p>
    </div>
    
    <!-- Recent Subscribers -->
    <div class="section-card">
        <div class="section-header">
            <h2 class="section-title">👥 Recent Subscribers</h2>
        </div>
        
        <?php if (!empty($recentTokens)): ?>
            <table class="token-table">
                <thead>
                    <tr>
                        <th>Token ID (Hash)</th>
                        <th>Created At</th>
                        <th>Last Seen</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTokens as $token): ?>
                        <tr>
                            <td><code><?= htmlspecialchars(substr($token['token_hash'], 0, 16)) ?>...</code></td>
                            <td><?= date('d M Y, h:i A', strtotime($token['created_at'])) ?></td>
                            <td><?= date('d M Y, h:i A', strtotime($token['last_seen'])) ?></td>
                            <td>
                                <?php if ($token['is_active']): ?>
                                    <span class="status-badge active">✓ Active</span>
                                <?php else: ?>
                                    <span class="status-badge inactive">✗ Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">
                👤 No subscribers yet. Wait for users to allow notifications on your website.
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Tips -->
    <div class="section-card">
        <div class="section-header">
            <h2 class="section-title">💡 Monitoring Tips</h2>
        </div>
        
        <div style="line-height: 1.8;">
            <p><strong>✅ How to verify notifications are working:</strong></p>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li>Check the <strong>"Recent Notification Logs"</strong> section above after sending</li>
                <li>Look for <span style="color: #28a745; font-weight: 600;">[SUCCESS]</span> entries in green</li>
                <li>Monitor <strong>"Active Tokens"</strong> count - should match your subscriber count</li>
                <li>Send a test notification via <strong>"Settings → Push Notifications"</strong></li>
            </ul>
            
            <p style="margin-top: 20px;"><strong>📊 Log Levels:</strong></p>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li><span style="color: #9cdcfe; font-weight: 600;">[INFO]</span> - General information (token count, steps)</li>
                <li><span style="color: #89d185; font-weight: 600;">[SUCCESS]</span> - Notifications sent successfully</li>
                <li><span style="color: #dcdcaa; font-weight: 600;">[WARNING]</span> - Non-critical issues (no active tokens)</li>
                <li><span style="color: #f48771; font-weight: 600;">[ERROR]</span> - Failed deliveries or configuration issues</li>
            </ul>
            
            <p style="margin-top: 20px;"><strong>🔍 Troubleshooting:</strong></p>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li>If no logs appear: Check file permissions on <code>logs/fcm_notifications.log</code></li>
                <li>If all fail: Verify service account JSON file is correct</li>
                <li>If some fail: Those tokens are invalid/expired (automatically deactivated)</li>
            </ul>
        </div>
    </div>
    
</div>

<script>
// Auto-refresh every 60 seconds
setTimeout(function() {
    location.reload();
}, 60000);
</script>

</body>
</html>
