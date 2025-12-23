<?php
// helpers/send_whatsapp.php
// WhatsApp Cloud API reusable helper

// WhatsApp Cloud API credentials (for development/testing only)
define('WHATSAPP_TEST_MODE', true); // Set to false for production
define('WHATSAPP_PHONE_NUMBER_ID', '937629662767540'); // Placeholder, replace with real ID
define('WHATSAPP_ACCESS_TOKEN', 'EAAbx07ZA0plwBQBZBlqMlPatUTHy6q66RzX0n2MglWpzqI5ZCmKssAz2X62FXxbMmbz37yih3DbBQAu6K9GQZCYayK1raoCrc5nTTl7KXJ7hjz0TiRrDE3kvPr3q5CI353OBxpZA9GiUpZAFvLtmBXAZARV84DiRvyYHKFWSi7JZBBPcN8SwfbHDEVlQEPDSIEYjdU46ZBeJMRO2qyOlcnySr7EN2aHOd7ObkxZATkcyVLxPzTABbDjZBV9A5luUESjyAqF4PQTtLwulpk9M4DY4BLT'); // Placeholder, replace with real token

/**
 * Send WhatsApp message using WhatsApp Cloud API template
 * @param string $to Recipient phone number in international format (e.g., 919999999999)
 * @param string $templateName Name of the WhatsApp template
 * @param string $language Language code (e.g., 'en')
 * @param array $variables Associative array of template variables (e.g., ['name' => 'John', ...])
 * @return bool True on success, false on failure
 */
function sendWhatsAppMessage($to, $templateName, $language, $variables = []) {
    if (defined('WHATSAPP_TEST_MODE') && WHATSAPP_TEST_MODE) {
        error_log("WHATSAPP TEST MODE MESSAGE:");
        error_log("To: " . $to);
        error_log("Template: " . $templateName);
        error_log("Language: " . $language);
        error_log("Variables: " . json_encode($variables));
        return true;
    }

    if (!defined('WHATSAPP_PHONE_NUMBER_ID') || !defined('WHATSAPP_ACCESS_TOKEN')) {
        error_log('WhatsApp API constants not defined.');
        return false;
    }

    $url = 'https://graph.facebook.com/v18.0/' . WHATSAPP_PHONE_NUMBER_ID . '/messages';
    $headers = [
        'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN,
        'Content-Type: application/json'
    ];

    // Prepare template parameters (order matters as per template definition)
    $params = [];
    foreach (['name', 'tracking_id', 'category', 'tracking_link'] as $key) {
        $params[] = [ 'type' => 'text', 'text' => isset($variables[$key]) ? $variables[$key] : '' ];
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => 'template',
        'template' => [
            'name' => $templateName,
            'language' => [ 'code' => $language ],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => $params
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        error_log('WhatsApp API cURL error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    $respArr = json_decode($response, true);
    if ($httpCode === 200 && isset($respArr['messages'][0]['id'])) {
        return true;
    } else {
        error_log('WhatsApp API error: ' . $response);
        return false;
    }
}
