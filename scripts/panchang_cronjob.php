<?php
// scripts/panchang_cronjob.php - To be run daily at 12:05 AM by cron
// Fetches Panchang details for default city and stores in DB for use by panchang.php

require_once __DIR__ . '/../config/db.php';

// Set default values (Solapur, India, Asia/Kolkata, current date, 05:30 timezone offset)
$city = 'Solapur, India';
$lat = 17.6599;
$lon = 75.9064;
$tz = '5.5'; // Asia/Kolkata timezone offset
$lang = 'en';
$date = date('d/m/Y');
$time = '05:30'; // Default time for Panchang

// Panchang API details
$api_key = '16b52b73-65fb-56af-b92d-7f35d3105d8f';
$api_url = "https://api.vedicastroapi.com/v3-json/panchang/panchang?api_key={$api_key}&date={$date}&tz={$tz}&lat={$lat}&lon={$lon}&time={$time}&lang={$lang}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode == 200 && $response) {
    try {
        $stmt = $pdo->prepare("INSERT INTO panchang (city, lat, lon, tz, lang, panchang_json) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $city,
            $lat,
            $lon,
            $tz,
            $lang,
            $response
        ]);
        echo "Panchang data stored successfully.";
    } catch (Exception $e) {
        echo "DB Error: " . $e->getMessage();
    }
} else {
    echo "Failed to fetch Panchang data from API.";
}
