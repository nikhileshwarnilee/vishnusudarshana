<?php
/**
 * WhatsApp Business API Helper
 * Centralized WhatsApp messaging system for admin panel and website
 * 
 * Usage:
 * - sendWhatsAppMessage($to, $templateName, $variables)
 * - logWhatsAppActivity($to, $templateName, $status, $response)
 */

// Load configuration
require_once __DIR__ . '/../config/whatsapp_config.php';

/**
 * Send WhatsApp message using Cloud API template
 * 
 * @param string $to Recipient phone number (with or without country code)
 * @param string $templateName Template identifier from WHATSAPP_TEMPLATES
 * @param array $variables Associative array of template variables
 * @param string $language Language code (default: 'en')
 * @return array ['success' => bool, 'message' => string, 'data' => array]
 */
function sendWhatsAppMessage($to, $templateName, $variables = [], $language = null) {
    $language = $language ?? WHATSAPP_DEFAULT_LANGUAGE;
    
    // Format phone number
    $to = formatWhatsAppPhone($to);
    if (!$to) {
        return [
            'success' => false,
            'message' => 'Invalid phone number format',
            'data' => null
        ];
    }
    
    // Validate template exists
    $templateActualName = getTemplateName($templateName);
    if (!$templateActualName) {
        return [
            'success' => false,
            'message' => "Template '$templateName' not found",
            'data' => null
        ];
    }
    
    // Test mode - Log instead of sending
    if (WHATSAPP_TEST_MODE) {
        $logData = [
            'to' => $to,
            'template' => $templateActualName,
            'language' => $language,
            'variables' => $variables,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        logWhatsAppActivity($to, $templateActualName, 'TEST_MODE', json_encode($logData));
        error_log("WHATSAPP TEST MODE: " . json_encode($logData));
        return [
            'success' => true,
            'message' => 'Test mode - message logged',
            'data' => $logData
        ];
    }
    
    // Validate credentials
    if (!WHATSAPP_PHONE_NUMBER_ID || !WHATSAPP_ACCESS_TOKEN) {
        return [
            'success' => false,
            'message' => 'WhatsApp API credentials not configured',
            'data' => null
        ];
    }
    
    // Build API request
    $url = WHATSAPP_API_BASE_URL . '/' . WHATSAPP_API_VERSION . '/' . WHATSAPP_PHONE_NUMBER_ID . '/messages';
    $headers = [
        'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN,
        'Content-Type: application/json'
    ];
    
    // Prepare template parameters
    $params = buildTemplateParameters($templateName, $variables);
    
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $to,
        'type' => 'template',
        'template' => [
            'name' => $templateActualName,
            'language' => ['code' => $language],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => $params
                ]
            ]
        ]
    ];
    
    // Send request
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Handle errors
    if ($curlError) {
        logWhatsAppActivity($to, $templateActualName, 'CURL_ERROR', $curlError);
        return [
            'success' => false,
            'message' => 'Network error: ' . $curlError,
            'data' => null
        ];
    }
    
    $responseData = json_decode($response, true);
    
    // Check success
    if ($httpCode === 200 && isset($responseData['messages'][0]['id'])) {
        logWhatsAppActivity($to, $templateActualName, 'SUCCESS', $response);
        return [
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => [
                'message_id' => $responseData['messages'][0]['id'],
                'phone' => $to
            ]
        ];
    } else {
        $errorMsg = isset($responseData['error']['message']) 
            ? $responseData['error']['message'] 
            : 'Unknown error';
        logWhatsAppActivity($to, $templateActualName, 'API_ERROR', $response);
        return [
            'success' => false,
            'message' => 'API error: ' . $errorMsg,
            'data' => $responseData
        ];
    }
}

/**
 * Format phone number for WhatsApp
 * 
 * @param string $phone Phone number
 * @return string|false Formatted phone or false if invalid
 */
function formatWhatsAppPhone($phone) {
    if (!$phone) return false;
    
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Remove leading zeros
    $phone = ltrim($phone, '0');
    
    // Add country code if missing
    if (strlen($phone) === 10) {
        $phone = WHATSAPP_COUNTRY_CODE . $phone;
    }
    
    // Validate length (should be 12 digits for India: 91 + 10 digits)
    if (WHATSAPP_PHONE_VALIDATION && strlen($phone) < 10) {
        return false;
    }
    
    return $phone;
}

/**
 * Get actual template name from identifier
 * 
 * @param string $identifier Template identifier
 * @return string|false Template name or false
 */
function getTemplateName($identifier) {
    // If it's a key in WHATSAPP_TEMPLATES
    if (defined('WHATSAPP_TEMPLATES')) {
        $templates = WHATSAPP_TEMPLATES;
        if (isset($templates[$identifier])) {
            return $templates[$identifier];
        }
    }
    
    // If it's already a template name
    if (in_array($identifier, WHATSAPP_TEMPLATES)) {
        return $identifier;
    }
    
    return false;
}

/**
 * Build template parameters array
 * 
 * @param string $templateName Template name
 * @param array $variables Variables array
 * @return array Parameters for API
 */
function buildTemplateParameters($templateName, $variables) {
    $params = [];
    
    // Get expected variables for this template
    $expectedVars = defined('WHATSAPP_TEMPLATE_VARIABLES') && isset(WHATSAPP_TEMPLATE_VARIABLES[$templateName])
        ? WHATSAPP_TEMPLATE_VARIABLES[$templateName]
        : array_keys($variables);
    
    // Build parameters in order
    foreach ($expectedVars as $key) {
        $value = isset($variables[$key]) ? $variables[$key] : '';
        $params[] = [
            'type' => 'text',
            'text' => (string)$value
        ];
    }
    
    return $params;
}

/**
 * Log WhatsApp activity
 * 
 * @param string $to Recipient
 * @param string $template Template name
 * @param string $status Status (SUCCESS, ERROR, TEST_MODE)
 * @param string $details Additional details
 */
function logWhatsAppActivity($to, $template, $status, $details = '') {
    if (!WHATSAPP_LOG_ENABLED) return;
    
    $logEntry = sprintf(
        "[%s] TO: %s | TEMPLATE: %s | STATUS: %s | DETAILS: %s\n",
        date('Y-m-d H:i:s'),
        $to,
        $template,
        $status,
        $details
    );
    
    // Create logs directory if it doesn't exist
    $logDir = dirname(WHATSAPP_LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    error_log($logEntry, 3, WHATSAPP_LOG_FILE);
}

/**
 * Send notification based on event type
 * Automatically determines which template to use
 * 
 * @param string $eventType Event identifier (e.g., 'service_status_changed')
 * @param array $data Event data
 * @return array Result of sendWhatsAppMessage
 */
function sendWhatsAppNotification($eventType, $data) {
    // Check if auto notification is enabled for this event
    if (defined('WHATSAPP_AUTO_NOTIFICATIONS') && 
        isset(WHATSAPP_AUTO_NOTIFICATIONS[$eventType]) && 
        !WHATSAPP_AUTO_NOTIFICATIONS[$eventType]) {
        return [
            'success' => false,
            'message' => 'Auto notification disabled for this event',
            'data' => null
        ];
    }
    
    // Map event types to templates and extract variables
    switch ($eventType) {
        case 'appointment_booked_payment_success':
            return sendWhatsAppMessage(
                $data['mobile'],
                'APPOINTMENT_BOOKED_PAYMENT_SUCCESS',
                [
                    'name' => $data['customer_name'],
                    'tracking_code' => $data['tracking_id'],
                    'service_name' => $data['service_name'] ?? 'Appointment',
                    'tracking_url' => $data['tracking_url'] ?? 'track.php'
                ]
            );
            
        case 'service_received':
            return sendWhatsAppMessage(
                $data['mobile'],
                'SERVICE_RECEIVED',
                [
                    'name' => $data['customer_name'],
                    'tracking_code' => $data['tracking_id'],
                    'service_name' => $data['service_name'] ?? 'Service',
                    'tracking_url' => $data['tracking_url'] ?? 'track.php'
                ]
            );
            
        case 'service_accepted':
            return sendWhatsAppMessage(
                $data['mobile'],
                'SERVICE_ACCEPTED',
                [
                    'name' => $data['customer_name'],
                    'tracking_code' => $data['tracking_id']
                ]
            );
            
        case 'service_completed':
            return sendWhatsAppMessage(
                $data['mobile'],
                'SERVICE_COMPLETED',
                [
                    'name' => $data['customer_name'],
                    'tracking_code' => $data['tracking_id'],
                    'service_name' => $data['service_name'] ?? 'Service'
                ]
            );
            
        case 'file_uploaded':
            return sendWhatsAppMessage(
                $data['mobile'],
                'SERVICE_FILE_UPLOADED',
                [
                    'name' => $data['customer_name'],
                    'tracking_code' => $data['tracking_id']
                ]
            );
            
        case 'appointment_accepted':
            return sendWhatsAppMessage(
                $data['mobile'],
                'APPOINTMENT_ACCEPTED',
                [
                    'name' => $data['customer_name'],
                    'tracking_code' => $data['tracking_id'],
                    'appointment_date' => $data['appointment_date'],
                    'appointment_time' => $data['appointment_time']
                ]
            );
            
        case 'appointment_missed':
            return sendWhatsAppMessage(
                $data['mobile'],
                'APPOINTMENT_MISSED',
                [
                    'name' => $data['customer_name'],
                    'tracking_code' => $data['tracking_id']
                ]
            );
            
        case 'payment_received':
            return sendWhatsAppMessage(
                $data['mobile'],
                'PAYMENT_RECEIVED',
                [
                    'name' => $data['customer_name'],
                    'amount' => $data['amount'],
                    'tracking_code' => $data['tracking_id']
                ]
            );
            
        default:
            return [
                'success' => false,
                'message' => "Unknown event type: $eventType",
                'data' => null
            ];
    }
}

/**
 * Get WhatsApp notification history from database
 * (Optional - requires database table)
 * 
 * @param string $trackingId Tracking ID
 * @return array Notification history
 */
function getWhatsAppHistory($trackingId) {
    // This would query a whatsapp_logs table if you create one
    // For now, return empty array
    return [];
}
