<?php
// scripts/panchang3rdparty.php
header('Content-Type: application/json');

// Get params from POST or GET
$date = isset($_REQUEST['date']) ? $_REQUEST['date'] : '';
$tz = isset($_REQUEST['tz']) ? $_REQUEST['tz'] : '';
$lat = isset($_REQUEST['lat']) ? $_REQUEST['lat'] : '';
$lon = isset($_REQUEST['lon']) ? $_REQUEST['lon'] : '';
$time = isset($_REQUEST['time']) ? $_REQUEST['time'] : '';
$lang = isset($_REQUEST['lang']) ? $_REQUEST['lang'] : 'en';

// Validate required params
if (!$date || !$tz || !$lat || !$lon || !$time) {
    echo json_encode(['error' => 'Missing required parameters.']);
    exit;
}

$api_key = ''; // Replace with your actual API key
$url = "https://api.vedicastroapi.com/v3-json/panchang/panchang?api_key={$api_key}&date={$date}&tz={$tz}&lat={$lat}&lon={$lon}&time={$time}&lang={$lang}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode == 200 && $response) {
    echo $response;
} else {
    echo json_encode(['error' => 'Failed to fetch Panchang data.']);
}
