<?php
// scripts/horoscope_cronjob.php - To be run daily to fetch horoscope data
// Fetches daily horoscope details for all zodiac signs and stores in DB

require_once __DIR__ . '/../config/db.php';

// Detect if running from web browser or CLI
$isWebRequest = php_sapi_name() !== 'cli';

// Languages to fetch
$languages = ['en', 'hi', 'mr', 'gu', 'ka', 'te'];

// Zodiac signs (1-12)
// 1: Aries, 2: Taurus, 3: Gemini, 4: Cancer, 5: Leo, 6: Virgo
// 7: Libra, 8: Scorpio, 9: Sagittarius, 10: Capricorn, 11: Aquarius, 12: Pisces
$zodiacs = range(1, 12);

// Zodiac name mapping
$zodiacNames = [
    1 => 'Aries',
    2 => 'Taurus',
    3 => 'Gemini',
    4 => 'Cancer',
    5 => 'Leo',
    6 => 'Virgo',
    7 => 'Libra',
    8 => 'Scorpio',
    9 => 'Sagittarius',
    10 => 'Capricorn',
    11 => 'Aquarius',
    12 => 'Pisces'
];

$date = date('d/m/Y');
$api_key = '16b52b73-65fb-56af-b92d-7f35d3105d8f';

// API parameters:
// split: true/false - true returns detailed/split data format
// type: big/small - big returns comprehensive horoscope, small returns summary

$split = 'true';
$type = 'big';

$successCount = 0;
$failCount = 0;
$messages = [];

// Delete all previous data before inserting new
try {
    $pdo->exec("TRUNCATE TABLE daily_horoscope");
    $createdAt = date('Y-m-d H:i:s');
    // Use $createdAt for all inserts
    $msg = "Cleared previous horoscope data";
    $messages[] = ['type' => 'success', 'text' => $msg];
    if (!$isWebRequest) echo $msg . "\n";
} catch (Exception $e) {
    $msg = "Error truncating daily_horoscope table: " . $e->getMessage();
    $messages[] = ['type' => 'error', 'text' => $msg];
    if (!$isWebRequest) echo $msg . "\n";
}

foreach ($languages as $lang) {
    foreach ($zodiacs as $zodiac) {
        // API endpoint for daily horoscope data
        $api_url = "https://api.vedicastroapi.com/v3-json/prediction/daily-sun?zodiac={$zodiac}&date={$date}&api_key={$api_key}&lang={$lang}&split={$split}&type={$type}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpcode == 200 && $response) {
            try {
                $responseData = json_decode($response, true);
                
                // Get zodiac name from mapping
                $zodiac_name = $zodiacNames[$zodiac];
                
                // Insert horoscope data with full JSON response
                $stmt = $pdo->prepare("INSERT INTO daily_horoscope (
                    zodiac_number, 
                    zodiac_name, 
                    lang, 
                    horoscope_json, 
                    request_date
                ) VALUES (?, ?, ?, ?, CURDATE())");
                
                $stmt->execute([
                    $zodiac,
                    $zodiac_name,
                    $lang,
                    $response
                ]);
                
                $successCount++;
                $msg = "Successfully inserted horoscope data for zodiac: $zodiac ($zodiac_name), language: $lang";
                $messages[] = ['type' => 'success', 'text' => $msg];
                if (!$isWebRequest) echo $msg . "\n";
            } catch (Exception $e) {
                $msg = "DB Error for zodiac $zodiac, lang $lang: " . $e->getMessage();
                $messages[] = ['type' => 'error', 'text' => $msg];
                if (!$isWebRequest) echo $msg . "\n";
                $failCount++;
            }
        } else {
            $msg = "Failed to fetch horoscope data from API for zodiac $zodiac, lang $lang. HTTP Code: $httpcode";
            $messages[] = ['type' => 'error', 'text' => $msg];
            if (!$isWebRequest) echo $msg . "\n";
            $failCount++;
        }
    }
}

echo "\nSummary: Inserted $successCount horoscope records. $failCount failed.\n";

// Display HTML output for web requests
if ($isWebRequest) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horoscope Cronjob Results</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .header {
            background-color: #800000;
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 8px 8px 0 0;
            margin-bottom: 0;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .header p {
            margin: 10px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin: 20px auto;
            max-width: 600px;
            overflow: hidden;
        }
        .messages {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 400px;
            overflow-y: auto;
        }
        .message-item {
            padding: 15px 20px;
            border-left: 5px solid #ddd;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #eee;
        }
        .message-item:last-child {
            border-bottom: none;
        }
        .message-item.success {
            border-left-color: #28a745;
            background-color: #f0f8f5;
        }
        .message-item.error {
            border-left-color: #dc3545;
            background-color: #fdf5f5;
        }
        .message-icon {
            font-size: 20px;
            font-weight: bold;
            min-width: 24px;
            text-align: center;
        }
        .message-item.success .message-icon {
            color: #28a745;
        }
        .message-item.error .message-icon {
            color: #dc3545;
        }
        .message-text {
            flex: 1;
            color: #333;
            font-size: 14px;
            word-break: break-word;
        }
        .summary {
            background-color: #f9f9f9;
            padding: 20px;
            border-top: 1px solid #eee;
            text-align: center;
        }
        .summary h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 16px;
        }
        .summary-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-bottom: 15px;
        }
        .stat {
            text-align: center;
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #800000;
            display: block;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .close-btn {
            background-color: #800000;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .close-btn:hover {
            background-color: #600000;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Horoscope Data Cronjob</h1>
            <p>Execution completed at <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
        
        <ul class="messages">
            <?php foreach ($messages as $msg): ?>
                <li class="message-item <?php echo $msg['type']; ?>">
                    <span class="message-icon"><?php echo ($msg['type'] === 'success') ? '✓' : '✗'; ?></span>
                    <span class="message-text"><?php echo htmlspecialchars($msg['text']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <div class="summary">
            <h3>Summary</h3>
            <div class="summary-stats">
                <div class="stat">
                    <span class="stat-value"><?php echo $successCount; ?></span>
                    <span class="stat-label">Successful</span>
                </div>
                <div class="stat">
                    <span class="stat-value"><?php echo $failCount; ?></span>
                    <span class="stat-label">Failed</span>
                </div>
                <div class="stat">
                    <span class="stat-value"><?php echo ($successCount + $failCount); ?></span>
                    <span class="stat-label">Total</span>
                </div>
            </div>
            <button class="close-btn" onclick="window.close()">Close Window</button>
        </div>
    </div>
</body>
</html>
    <?php
}
