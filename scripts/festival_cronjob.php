<?php
// scripts/festival_cronjob.php - To be run daily to fetch festival data
// Fetches festival/holiday details and stores in DB

require_once __DIR__ . '/../config/db.php';

// Set default values
$city = 'Solapur, India';
$lat = 17.6599;
$lon = 75.9064;
$tz = '5.5'; // Asia/Kolkata timezone offset

// Languages to fetch
$languages = ['en', 'hi', 'mr', 'gu', 'ka', 'te'];
$date = date('d/m/Y');
$api_key = '16b52b73-65fb-56af-b92d-7f35d3105d8f';

$successCount = 0;
$failCount = 0;

// Delete all existing data before inserting new rows
try {
    $pdo->exec("TRUNCATE TABLE festivals");
} catch (Exception $e) {
    echo "Error truncating festivals table: " . $e->getMessage() . "\n";
}

foreach ($languages as $lang) {
    // API endpoint for festival/holiday data
    $api_url = "https://api.vedicastroapi.com/v3-json/panchang/festivals?api_key={$api_key}&date={$date}&tz={$tz}&lat={$lat}&lon={$lon}&lang={$lang}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpcode == 200 && $response) {
        try {
            // Check if festivals table exists, create if not
            $pdo->exec("CREATE TABLE IF NOT EXISTS festivals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                city VARCHAR(255),
                lat DECIMAL(10,8),
                lon DECIMAL(11,8),
                tz VARCHAR(50),
                lang VARCHAR(10),
                festival_json TEXT,
                request_date DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_lang (lang),
                INDEX idx_request_date (request_date)
            )");
            
            // Insert festival data
            $stmt = $pdo->prepare("INSERT INTO festivals (city, lat, lon, tz, lang, festival_json, request_date) 
                                   VALUES (?, ?, ?, ?, ?, ?, CURDATE())
                                   ON DUPLICATE KEY UPDATE festival_json = VALUES(festival_json), request_date = CURDATE()");
            $stmt->execute([
                $city,
                $lat,
                $lon,
                $tz,
                $lang,
                $response
            ]);
            $successCount++;
            echo "Successfully inserted festival data for language: $lang\n";
        } catch (Exception $e) {
            echo "DB Error for lang $lang: " . $e->getMessage() . "\n";
            $failCount++;
        }
    } else {
        echo "Failed to fetch festival data from API for lang $lang. HTTP Code: $httpcode\n";
        $failCount++;
    }
}

echo "\nSummary: Inserted $successCount festival records. $failCount failed.\n";
