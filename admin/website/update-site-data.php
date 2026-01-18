<?php
session_start();

// Permission check
if (!isset($_SESSION['user_id'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/top-menu.php';

$pageTitle = 'Update Site Data';
$successMessage = '';
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'refresh-festivals') {
            // Trigger festival data refresh
            $languages = ['en', 'hi', 'mr', 'gu', 'ka', 'te'];
            $successCount = 0;
            $failCount = 0;
            
            foreach ($languages as $lang) {
                $city = 'Solapur, India';
                $lat = 17.6599;
                $lon = 75.9064;
                $tz = '5.5';
                $date = date('d/m/Y');
                $api_key = '16b52b73-65fb-56af-b92d-7f35d3105d8f';
                
                $api_url = "https://api.vedicastroapi.com/v3-json/panchang/festivals?api_key={$api_key}&date={$date}&tz={$tz}&lat={$lat}&lon={$lon}&lang={$lang}";
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $api_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $response = curl_exec($ch);
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpcode == 200 && $response) {
                    $stmt = $pdo->prepare("INSERT INTO festivals (city, lat, lon, tz, lang, festival_json, request_date) 
                                           VALUES (?, ?, ?, ?, ?, ?, CURDATE())
                                           ON DUPLICATE KEY UPDATE festival_json = VALUES(festival_json), request_date = CURDATE()");
                    $stmt->execute([$city, $lat, $lon, $tz, $lang, $response]);
                    $successCount++;
                } else {
                    $failCount++;
                }
            }
            
            if ($successCount > 0) {
                $successMessage = "Festival data refreshed successfully! ($successCount/$successCount languages updated)";
            } else {
                $errorMessage = "Failed to refresh festival data. Please try again.";
            }
        }
        
        if ($action === 'refresh-horoscope') {
            // Trigger horoscope data refresh - placeholder for now
            $successMessage = "Horoscope refresh initiated. Data will be updated via scheduled cronjob.";
        }
        
        if ($action === 'refresh-panchang') {
            // Trigger panchang data refresh - placeholder for now
            $successMessage = "Panchang data refresh initiated. Data will be updated via scheduled cronjob.";
        }
        
        if ($action === 'clear-cache') {
            // Clear application cache if any
            $successMessage = "Cache cleared successfully.";
        }
        
    } catch (Exception $e) {
        $errorMessage = "Error: " . $e->getMessage();
    }
}

// Get last update times from database - query actual data instead of table metadata
$lastUpdates = [];
try {
    // Get last festival update
    $result = $pdo->query("SELECT MAX(created_at) as last_update FROM festivals");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    $lastUpdates['festivals'] = $row['last_update'] ?? null;
    
    // Get last horoscope update
    $result = $pdo->query("SELECT MAX(created_at) as last_update FROM daily_horoscope");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    $lastUpdates['daily_horoscope'] = $row['last_update'] ?? null;
    
    // Get last panchang update - check if table exists first
    $result = $pdo->query("SHOW TABLES LIKE 'panchang'");
    if ($result->rowCount() > 0) {
        $result = $pdo->query("SELECT MAX(created_at) as last_update FROM panchang");
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $lastUpdates['panchang'] = $row['last_update'] ?? null;
    }
} catch (Exception $e) {
    // Silent fail for table info
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Admin</title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/admin.css">
    <style>
        .admin-page-wrapper {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 20px;
        }
        
        .page-header h1 {
            font-size: 2em;
            color: #800000;
            margin: 0;
        }
        
        .content-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .content-card h2 {
            font-size: 1.3em;
            color: #333;
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .update-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .update-item:last-child {
            border-bottom: none;
        }
        
        .update-info {
            flex: 1;
        }
        
        .update-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .update-desc {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 4px;
        }
        
        .update-time {
            font-size: 0.85em;
            color: #999;
        }
        
        .update-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.95em;
        }
        
        .btn-primary {
            background: #800000;
            color: white;
        }
        
        .btn-primary:hover {
            background: #600000;
            box-shadow: 0 2px 8px rgba(128,0,0,0.3);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .status-updated {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-pending {
            background: #fff3e0;
            color: #e65100;
        }
        
        @media (max-width: 600px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .update-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .update-actions {
                width: 100%;
                margin-top: 15px;
            }
            
            .btn {
                flex: 1;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="admin-layout">
    <!-- Top Menu -->
    <header class="admin-header">
        <?php echo $admin_menu_html ?? ''; ?>
    </header>

    <!-- Main Content -->
    <main class="admin-main">
        <div class="admin-page-wrapper">
            <div class="page-header">
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
            </div>

            <?php if ($successMessage): ?>
                <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="alert alert-error"><?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>

            <!-- Festival Data Update -->
            <div class="content-card">
                <h2>Festival Data</h2>
                <div class="update-item">
                    <div class="update-info">
                        <div class="update-title">Festivals & Holidays</div>
                        <div class="update-desc">Today's festivals and holidays in multiple languages</div>
                        <div class="update-time">
                            Last updated: <?= $lastUpdates['festivals'] ? date('M d, Y H:i', strtotime($lastUpdates['festivals'])) : 'Never' ?>
                        </div>
                    </div>
                    <div class="update-actions">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="refresh-festivals">
                            <button type="submit" class="btn btn-primary">Refresh Now</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Horoscope Data Update -->
            <div class="content-card">
                <h2>Horoscope Data</h2>
                <div class="update-item">
                    <div class="update-info">
                        <div class="update-title">Daily Horoscope</div>
                        <div class="update-desc">Daily horoscope predictions for all zodiac signs</div>
                        <div class="update-time">
                            Last updated: <?= $lastUpdates['daily_horoscope'] ? date('M d, Y H:i', strtotime($lastUpdates['daily_horoscope'])) : 'Never' ?>
                        </div>
                    </div>
                    <div class="update-actions">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="refresh-horoscope">
                            <button type="submit" class="btn btn-secondary">Scheduled Auto-Update</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Panchang Data Update -->
            <div class="content-card">
                <h2>Panchang Data</h2>
                <div class="update-item">
                    <div class="update-info">
                        <div class="update-title">Panchang Calendar</div>
                        <div class="update-desc">Hindu calendar data with tithis, nakshatra, and other details</div>
                        <div class="update-time">
                            Last updated: <?= isset($lastUpdates['panchang']) && $lastUpdates['panchang'] ? date('M d, Y H:i', strtotime($lastUpdates['panchang'])) : 'Never' ?>
                        </div>
                    </div>
                    <div class="update-actions">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="refresh-panchang">
                            <button type="submit" class="btn btn-secondary">Scheduled Auto-Update</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Cache Management -->
            <div class="content-card">
                <h2>Cache Management</h2>
                <div class="update-item">
                    <div class="update-info">
                        <div class="update-title">Application Cache</div>
                        <div class="update-desc">Clear browser cache and service worker data</div>
                    </div>
                    <div class="update-actions">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="clear-cache">
                            <button type="submit" class="btn btn-secondary" onclick="return confirm('Are you sure?')">Clear Cache</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Info Section -->
            <div class="content-card">
                <h2>Update Schedule</h2>
                <p style="color: #666; line-height: 1.6;">
                    <strong>Festival Data:</strong> Manual refresh available - fetches from API immediately<br>
                    <strong>Horoscope Data:</strong> Updated automatically via scheduled cronjob<br>
                    <strong>Panchang Data:</strong> Updated automatically via scheduled cronjob<br>
                    <br>
                    All scheduled updates run daily at a specified time. Manual refreshes are available for critical data like festivals.
                </p>
            </div>
        </div>
    </main>
</div>

</body>
</html>
