<?php
// scripts/horoscope_cronjob.php - To be run daily to fetch horoscope data
// Fetches daily horoscope details for all zodiac signs and stores in DB

require_once __DIR__ . '/../config/db.php';

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
$api_key = 'aa16b52b73-65fb-56af-b92d-7f35d3105d8f';

// API parameters:
// split: true/false - true returns detailed/split data format
// type: big/small - big returns comprehensive horoscope, small returns summary

$split = 'true';
$type = 'big';

$successCount = 0;
$failCount = 0;

// Delete all previous data before inserting new
try {
    $pdo->exec("TRUNCATE TABLE daily_horoscope");
    echo "Cleared previous horoscope data\n";
} catch (Exception $e) {
    echo "Error truncating daily_horoscope table: " . $e->getMessage() . "\n";
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
                echo "Successfully inserted horoscope data for zodiac: $zodiac ($zodiac_name), language: $lang\n";
            } catch (Exception $e) {
                echo "DB Error for zodiac $zodiac, lang $lang: " . $e->getMessage() . "\n";
                $failCount++;
            }
        } else {
            echo "Failed to fetch horoscope data from API for zodiac $zodiac, lang $lang. HTTP Code: $httpcode\n";
            $failCount++;
        }
    }
}

echo "\nSummary: Inserted $successCount horoscope records. $failCount failed.\n";
