<?php
/* ============================================
   SHOW ERRORS
============================================ */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kolkata');


/* ============================================
   LOAD .env FILE
============================================ */
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false) {
            putenv(trim($line));
        }
    }
}

/* ============================================
   OPENAI API KEY
============================================ */
$OPENAI_API_KEY = getenv('OPENAI_API_KEY');
if (!$OPENAI_API_KEY) {
    die("OPENAI_API_KEY NOT SET");
}

/* ============================================
   PANCHANG PROMPT (STRICT JSON)
============================================ */
$todayDate = date('Y-m-d');

$weekday = date('l');
$aiPrompt = "
Today's date: {$todayDate}
Weekday: {$weekday}
Location: Maharashtra (Pune / Solapur region)
Timezone: Indian Standard Time (IST)

IMPORTANT RULES:
- You must reply ONLY in valid JSON
- Do NOT include any text outside JSON
- Do NOT add or remove JSON keys
- All content must be in clear, simple English
- Do NOT use Marathi or Hindi anywhere
- Do NOT provide exact minute or second timings
- Times should be approximate or descriptive
- This is for general guidance only, not final ritual advice

SOURCE INSTRUCTION:
- Panchang data must be based on Drik Panchang (https://www.drikpanchang.com/)
- Use traditional Indian Panchang terminology in English

JSON STRUCTURE (MUST MATCH EXACTLY):

{
    \"date\": \"\",
    \"weekday\": \"\",
    \"shaka\": \"\",
    \"samvatsar\": \"\",
    \"paksha\": \"\",
    \"tithi\": \"\",
    \"nakshatra\": \"\",
    \"ayan\": \"\",
    \"rutu\": \"\",
    \"maas\": \"\",
    \"yog\": \"\",
    \"karan\": \"\",
    \"sunrise_time\": \"Approximate sunrise time for Pune/Solapur region\",
    \"sunset_time\": \"Approximate sunset time for Pune/Solapur region\",
    \"rahu_kalam\": \"Approximate Rahu Kalam period\",
    \"day_summary\": \"Brief summary of whether the day is generally auspicious or inauspicious\",
    \"day_significance\": \"10–20 lines explaining the religious and cultural significance of the day\",
    \"marriage_muhurat\": \"General guidance for marriage muhurat availability today\",
    \"house_warming_muhurat\": \"General guidance for house warming muhurat availability today\",
    \"vehicle_purchase_muhurat\": \"General guidance for vehicle purchase muhurat availability today\",
    \"business_start_muhurat\": \"General guidance for business start muhurat availability today\"
}

Return ONLY valid JSON.
";

/* ============================================
   OPENAI API CALL (CHAT COMPLETIONS)
============================================ */
$url = "https://api.openai.com/v1/chat/completions";

$data = [
    "model" => "gpt-4o-mini",
    "temperature" => 0.2,
    "messages" => [
        [
            "role" => "system",
            "content" => "You must return ONLY valid JSON. No explanation text."
        ],
        [
            "role" => "user",
            "content" => $aiPrompt
        ]
    ]
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer " . $OPENAI_API_KEY
    ],
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_TIMEOUT => 60
]);

$response = curl_exec($ch);
if ($response === false) {
    die("CURL ERROR: " . curl_error($ch));
}
curl_close($ch);

/* ============================================
   PARSE OPENAI RESPONSE
============================================ */
$responseData = json_decode($response, true);

if (!isset($responseData['choices'][0]['message']['content'])) {
    echo "<pre>";
    print_r($responseData);
    echo "</pre>";
    die("OPENAI RESPONSE FORMAT ERROR");
}

$responseText = $responseData['choices'][0]['message']['content'];

/* ============================================
   PARSE JSON FROM AI
============================================ */

$aiData = json_decode($responseText, true);
// FORCE CORRECT DATE FROM SERVER (English keys only)
if (is_array($aiData)) {
    $aiData['date'] = date('Y-m-d');
    $aiData['weekday'] = date('l');
}

if (!$aiData) {
    echo "<pre>";
    echo $responseText;
    echo "</pre>";
    die("INVALID OPENAI JSON");
}

/* ============================================
   SAVE JSON FILE
============================================ */
$aiData['generated_at'] = date('Y-m-d H:i:s');

$today = date('Y-m-d');
$outputFile = __DIR__ . '/../data/panchang-' . $today . '.json';

file_put_contents(
    $outputFile,
    json_encode($aiData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

/* ============================================
   SUCCESS
============================================ */
echo "OPENAI PANCHANG GENERATED AND SAVED SUCCESSFULLY";
exit;
?>