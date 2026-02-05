<?php
/**
 * AiSensy WhatsApp API Helper
 * Centralized WhatsApp messaging system using AiSensy platform
 * 
 * Usage:
 * - sendWhatsAppMessage($to, $templateName, $variables)
 * - logWhatsAppActivity($to, $templateName, $status, $response)
 */

// Load configuration
require_once __DIR__ . '/../config/whatsapp_config.php';

/**
 * Send WhatsApp message using AiSensy API
 * 
 * @param string $to Recipient phone number (with or without country code)
 * @param string $templateName Template identifier from WHATSAPP_TEMPLATES
 * @param array $variables Associative array of template variables
 * @param string $language Language code (not used in AiSensy, kept for compatibility)
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
    
    // Validate AiSensy credentials
    if (!defined('AISENSY_API_KEY') || !AISENSY_API_KEY || !defined('AISENSY_API_URL')) {
        return [
            'success' => false,
            'message' => 'AiSensy API credentials not configured',
            'data' => null
        ];
    }
    
    // Build AiSensy API request
    $url = AISENSY_API_URL;
    $headers = [
        'Content-Type: application/json'
    ];
    
    // Extract variables for AiSensy format using configured order
    $templateParams = buildAiSensyTemplateParams($templateName, $variables);
    $userName = isset($variables['name']) ? $variables['name'] : '';
    
    // Build AiSensy API payload
    $payload = [
        'apiKey' => AISENSY_API_KEY,
        'campaignName' => $templateActualName,
        'destination' => $to,
        'userName' => $userName,
        'templateParams' => $templateParams
    ];
    
    // Add button parameters if this template has buttons configured
    $buttons = buildAiSensyButtons($templateName, $variables);
    if (!empty($buttons)) {
        $payload['buttons'] = $buttons;
    }
    
    // Add media object if file_path is provided (for document sending)
    if (isset($variables['file_path']) && !empty($variables['file_path'])) {
        $filePath = $variables['file_path'];
        $fileName = basename($filePath);
        $publicUrl = 'https://vishnusudarshana.com/uploads/services/' . $filePath;
        $payload['media'] = [
            'url' => $publicUrl,
            'filename' => $fileName
        ];
    }
    
    // Add optional source if available
    if (isset($variables['source'])) {
        $payload['source'] = $variables['source'];
    }
    
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
    
    // Check success - AiSensy returns status 200 for success
    if ($httpCode === 200) {
        logWhatsAppActivity($to, $templateActualName, 'SUCCESS', $response);
        return [
            'success' => true,
            'message' => 'Message sent successfully via AiSensy',
            'data' => [
                'phone' => $to,
                'campaign' => $templateActualName,
                'response' => $responseData
            ]
        ];
    } else {
        $errorMsg = isset($responseData['error']['message']) 
            ? $responseData['error']['message'] 
            : (isset($responseData['message']) ? $responseData['message'] : 'Unknown error');
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
 * Build AiSensy template params based on configured variables
 *
 * @param string $templateIdentifier Internal template key (e.g., 'OTP_VERIFICATION')
 * @param array $variables Provided variables
 * @return array Ordered list of string params for AiSensy
 */
function buildAiSensyTemplateParams($templateIdentifier, $variables) {
    $params = [];
    
    // Determine expected variables for this template
    if (defined('WHATSAPP_TEMPLATE_VARIABLES') && isset(WHATSAPP_TEMPLATE_VARIABLES[$templateIdentifier])) {
        $expected = WHATSAPP_TEMPLATE_VARIABLES[$templateIdentifier];
    } else {
        // Fallback: use provided variables order
        $expected = array_keys($variables);
    }
    
    // Build params in expected order, ignoring extras
    foreach ($expected as $key) {
        $params[] = isset($variables[$key]) ? (string)$variables[$key] : '';
    }
    
    return $params;
}

/**
 * Build AiSensy buttons array for URL button parameters
 * 
 * @param string $templateIdentifier Internal template key
 * @param array $variables Provided variables
 * @return array Buttons array for AiSensy API
 */
function buildAiSensyButtons($templateIdentifier, $variables) {
    // Check if this template has button configuration
    if (!defined('WHATSAPP_TEMPLATE_BUTTONS') || !isset(WHATSAPP_TEMPLATE_BUTTONS[$templateIdentifier])) {
        return [];
    }
    
    $buttonConfig = WHATSAPP_TEMPLATE_BUTTONS[$templateIdentifier];
    $buttons = [];
    
    foreach ($buttonConfig as $index => $button) {
        $buttonParam = isset($variables[$button['param']]) ? (string)$variables[$button['param']] : '';
        
        // Use fallback if parameter is empty (button is mandatory in template)
        if (empty($buttonParam)) {
            $buttonParam = 'N/A';
        }
        
        $buttons[] = [
            'type' => 'button',
            'sub_type' => $button['type'] ?? 'url',
            'index' => $index,
            'parameters' => [
                [
                    'type' => 'text',
                    'text' => $buttonParam
                ]
            ]
        ];
    }
    
    return $buttons;
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
        case 'admin_services_alert':
            return sendWhatsAppMessage(
                $data['admin_mobile'],
                'ADMIN_SERVICES_ALERT',
                [
                    'customer_name' => $data['customer_name'] ?? $data['name'] ?? '',
                    'customer_mobile' => $data['customer_mobile'] ?? '',
                    'category' => $data['category'] ?? '',
                    'products_list' => $data['products_list'] ?? '',
                    'tracking_id' => $data['tracking_id'] ?? $data['tracking_url'] ?? ''
                ]
            );
        case 'appointment_booked_payment_success':
            return sendWhatsAppMessage(
                $data['mobile'],
                'APPOINTMENT_BOOKED_PAYMENT_SUCCESS',
                [
                    'name' => $data['name'] ?? $data['customer_name'] ?? '',
                    'category' => $data['category'] ?? 'Appointment',
                    'products_list' => $data['products_list'] ?? '',
                    'tracking_url' => $data['tracking_url'] ?? $data['tracking_id'] ?? ''
                ]
            );
            
        case 'service_received':
            return sendWhatsAppMessage(
                $data['mobile'],
                'SERVICE_RECEIVED',
                [
                    'name' => $data['name'] ?? $data['customer_name'] ?? '',
                    'category' => $data['category'] ?? 'Service',
                    'products_list' => $data['products_list'] ?? '',
                    'tracking_url' => $data['tracking_url'] ?? $data['tracking_id'] ?? ''
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
                    'tracking_id' => $data['tracking_id']
                ]
            );
            case 'appointment_completed_admin':
                return sendWhatsAppMessage(
                    $data['mobile'],
                    'APPOINTMENT_COMPLETED',
                    [
                        'name' => $data['name'] ?? $data['customer_name'] ?? '',
                        'tracking_id' => $data['tracking_id'] ?? '',
                        'appointment_date' => $data['appointment_date'] ?? ''
                    ]
                );
            
        case 'appointment_missed':
            return sendWhatsAppMessage(
                $data['mobile'],
                'APPOINTMENT_MISSED',
                [
                    'name' => $data['name'] ?? $data['customer_name'] ?? '',
                    'tracking_id' => $data['tracking_id'] ?? ''
                ]
            );

        case 'appointment_cancelled_admin':
            return sendWhatsAppMessage(
                $data['mobile'],
                'APPOINTMENT_CANCELLED',
                [
                    'name' => $data['name'] ?? $data['customer_name'] ?? '',
                    'tracking_id' => $data['tracking_id'] ?? ''
                ]
            );  
            
        case 'admin_appointment_scheduled':
            return sendWhatsAppMessage(
                $data['mobile'],
                'APPOINTMENT_SCHEDULED',
                [
                    'name' => $data['name'] ?? $data['customer_name'] ?? '',
                    'tracking_id' => $data['tracking_id'] ?? '',
                    'appointment_date' => $data['appointment_date'] ?? '',
                    'from_time' => $data['from_time'] ?? '',
                    'to_time' => $data['to_time'] ?? '',
                    'tracking_url' => $data['tracking_id'] ?? ''
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
        
        case 'admin_custom_message':
            return sendWhatsAppMessage(
                $data['mobile'],
                'APPOINTMENT_MESSAGE',
                [
                    'name' => $data['name'] ?? $data['customer_name'] ?? 'Customer',
                    'message' => $data['message'] ?? ''
                ]
            );
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
