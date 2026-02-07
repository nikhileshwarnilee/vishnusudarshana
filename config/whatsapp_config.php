<?php
/**
 * AiSensy WhatsApp API Configuration
 * Centralized configuration for AiSensy WhatsApp messaging
 * Used across admin panel and website
 */

// AiSensy API Credentials
define('AISENSY_API_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6IjY5NjU3NzMxODFhMDg2MTIzZWUyMmI3MiIsIm5hbWUiOiJWaXNobnVzdWRhcnNoYW5hIERoYXJtaWsgU2Fuc2thciBLZW5kcmEiLCJhcHBOYW1lIjoiQWlTZW5zeSIsImNsaWVudElkIjoiNjk2NTc3MzE4MWEwODYxMjNlZTIyYjZkIiwiYWN0aXZlUGxhbiI6IlBST19NT05USExZIiwiaWF0IjoxNzY4ODUwMTc2fQ.AOfSsmh8v310FZrHZwA369nSGR6RZHm2joCqwgkWFZc');
define('AISENSY_API_URL', 'https://backend.aisensy.com/campaign/t1/api/v2');

// Test Mode - Set to true to log messages instead of sending
define('WHATSAPP_TEST_MODE', false);

// Default Language
define('WHATSAPP_DEFAULT_LANGUAGE', 'en');

// Business Details
define('WHATSAPP_BUSINESS_NAME', 'Vishnusudarshana Dharmik Sanskar Kendra');
define('WHATSAPP_BUSINESS_PHONE', '918975224444'); // Your business WhatsApp number

/**
 * Template Names - Mapped to AiSensy Campaign Names
 * These map internal template references to actual AiSensy campaign names
 * Campaign names must match exactly as created in AiSensy dashboard
 */
define('WHATSAPP_TEMPLATES', [
    // Token Booking Confirmation - AiSensy campaign: Token Booked
    'token_booking_confirmation' => 'Token Booked',
    // Service Request Templates - All use "Website Service Request Confirmation" campaign
    'SERVICE_RECEIVED' => 'Website Service Request Confirmation',
    'SERVICE_ACCEPTED' => 'Website Service Request Confirmation',
    'SERVICE_IN_PROGRESS' => 'Website Service Request Confirmation',
    'SERVICE_COMPLETED' => 'Website Service Request Confirmation',
    'SERVICE_CANCELLED' => 'Website Service Request Confirmation',
    'SERVICE_FILE_UPLOADED' => 'Website Service Request Confirmation',
    
    // Appointment Templates - Also use "Website Service Request Confirmation" campaign (same template)
    'APPOINTMENT_BOOKED_PAYMENT_SUCCESS' => 'Website Service Request Confirmation',
    'APPOINTMENT_RECEIVED' => 'Website Service Request Confirmation',
    'APPOINTMENT_ACCEPTED' => 'Website Service Request Confirmation',
    'APPOINTMENT_REMINDER' => 'Website Service Request Confirmation',
    'APPOINTMENT_COMPLETED' => 'Website Service Request Confirmation',
    'APPOINTMENT_CANCELLED' => 'Website Service Request Confirmation',
    'APPOINTMENT_MISSED' => 'Appointment Missed',
    
    // Admin Appointment Scheduled - New template for admin-triggered acceptance
    'APPOINTMENT_SCHEDULED' => 'Appointment Scheduled',

    // Admin Appointment Completed
    'APPOINTMENT_COMPLETED' => 'Appointment Completed',
    
    // Admin Appointment Cancelled
    'APPOINTMENT_CANCELLED' => 'Appointment Cancelled',
    
    // Payment Templates - Use "Website Service Request Confirmation" campaign
    'PAYMENT_RECEIVED' => 'Website Service Request Confirmation',
    'PAYMENT_PENDING' => 'Website Service Request Confirmation',
    'PAYMENT_CONFIRMED' => 'Website Service Request Confirmation',
    'INVOICE_GENERATED' => 'Website Service Request Confirmation',
    'PAYMENT_REMINDER' => 'Website Service Request Confirmation',
    
    // General Templates
    'WELCOME_MESSAGE' => 'Website Service Request Confirmation',
    'OTP_VERIFICATION' => 'OTP for Download',
    'CUSTOM_MESSAGE' => 'Website Service Request Confirmation',
    'APPOINTMENT_MESSAGE' => 'Admin Notes',
    
    // Offline Service Request - Admin submitted
    'OFFLINE_SERVICE_REQUEST_RECEIVED' => 'Offline Service Request Received',

    // Payment - Offline service request collected
    'SERVICE_REQUEST_PAYMENT_RECEIVED' => 'Service Request Payment Received',
    
    // Service Completed - Admin marks service as completed
    'SERVICE_REQUEST_COMPLETED' => 'Service Request Completed',
    
    // File Upload - Admin uploads document for service request
    'SERVICE_REQUEST_FILE_UPLOADED' => 'Service Request File Uploaded',
    
    // Payment Dues Reminder - Admin sends payment due reminder
    'PAYMENT_DUES_REMINDER' => 'Payment Dues Reminder',

    // Schedule blocked notification (admin created blocks)
    'SCHEDULE_BLOCKED' => 'Schedule Manager Marathi'
    , 'ADMIN_SERVICES_ALERT' => 'Admin Service Alert'
    , 'token_update_marathi' => 'Token Update Marathi'
    , 'token_update_telugu' => 'Token Update Telugu'
]);

/**
 * Template Variable Configurations
 * Define required variables for each template
 */
define('WHATSAPP_TEMPLATE_VARIABLES', [
    // Service & Appointment Templates - All use same 4 parameters
    'SERVICE_RECEIVED' => ['name', 'category', 'products_list', 'tracking_url'],
    'SERVICE_ACCEPTED_NOTIFICATION' => ['name', 'category', 'products_list', 'tracking_url'],
    'SERVICE_COMPLETED' => ['name', 'category', 'products_list', 'tracking_url'],
    'SERVICE_IN_PROGRESS' => ['name', 'category', 'products_list', 'tracking_url'],
    'SERVICE_CANCELLED' => ['name', 'category', 'products_list', 'tracking_url'],
    'SERVICE_FILE_UPLOADED' => ['name', 'category', 'products_list', 'tracking_url'],
    'FILE_UPLOAD_NOTIFICATION' => ['name', 'category', 'products_list', 'tracking_url'],
    
    'APPOINTMENT_BOOKED_PAYMENT_SUCCESS' => ['name', 'category', 'products_list', 'tracking_url'],
    'APPOINTMENT_RECEIVED' => ['name', 'category', 'products_list', 'tracking_url'],
    'APPOINTMENT_ACCEPTED' => ['name', 'category', 'products_list', 'tracking_url'],
    'APPOINTMENT_MISSED' => ['name', 'tracking_id'],
    'APPOINTMENT_REMINDER' => ['name', 'category', 'products_list', 'tracking_url'],
    'APPOINTMENT_COMPLETED' => ['name', 'category', 'products_list', 'tracking_url'],
    
    // Admin Appointment Scheduled - 6 parameters: name, tracking_id, date, from_time, to_time, tracking_url
    'APPOINTMENT_SCHEDULED' => ['name', 'tracking_id', 'appointment_date', 'from_time', 'to_time', 'tracking_url'],

    // Admin Appointment Completed - 3 parameters: name, tracking_id, appointment_date
    'APPOINTMENT_COMPLETED' => ['name', 'tracking_id', 'appointment_date'],
    
    // Admin Appointment Cancelled - 2 parameters: name, tracking_id
    'APPOINTMENT_CANCELLED' => ['name', 'tracking_id'],
    
    // Payment Templates
    'PAYMENT_RECEIVED' => ['name', 'amount', 'tracking_code'],
    'PAYMENT_CONFIRMED' => ['name', 'amount', 'payment_id'],
    'PAYMENT_PENDING' => ['name', 'amount', 'tracking_code'],
    'PAYMENT_REMINDER' => ['name', 'amount', 'tracking_code'],
    'INVOICE_GENERATED' => ['name', 'amount', 'tracking_code'],
    
    // General Templates
    'WELCOME_MESSAGE' => ['name'],
    'CUSTOM_MESSAGE' => ['name'],
    'OTP_VERIFICATION' => ['otp_code'],  // {{1}} = otp_code for message body
    'APPOINTMENT_MESSAGE' => ['name', 'message'],  // {{1}} = Customer Name, {{2}} = Custom message text
    
    // Offline Service Request - 4 parameters: name, category, products_list, tracking_id
    'OFFLINE_SERVICE_REQUEST_RECEIVED' => ['name', 'category', 'products_list', 'tracking_id'],

    // Payment received for offline service request
    'SERVICE_REQUEST_PAYMENT_RECEIVED' => ['name', 'tracking_id', 'amount'],
    
    // Service completed notification - 5 params: name, tracking_id, category, products_list, tracking_id
    'SERVICE_REQUEST_COMPLETED' => ['name', 'tracking_id', 'category', 'products_list', 'tracking_id'],
    
    // File uploaded notification - 5 params: name, tracking_id, category, products_list, file_path
    'SERVICE_REQUEST_FILE_UPLOADED' => ['name', 'tracking_id', 'category', 'products_list', 'file_path'],
    
    // Payment dues reminder - 2 params: name, due_amount
    'PAYMENT_DUES_REMINDER' => ['name', 'due_amount'],

    // Schedule blocked notification - 6 params: name, date_range, title, time_range, status, description
    'SCHEDULE_BLOCKED' => ['name', 'date_range', 'title', 'time_range', 'status', 'description']
    , 'ADMIN_SERVICES_ALERT' => ['customer_name', 'customer_mobile', 'category', 'products_list', 'tracking_id']
    , 'token_update_marathi' => ['name', 'token_no', 'revised_slot', 'current_token']
    , 'token_update_telugu' => ['name', 'token_no', 'revised_slot', 'current_token']
]);

/**
 * Template Button Configurations
 * Define button parameters for templates with URL buttons
 */
define('WHATSAPP_TEMPLATE_BUTTONS', [
    'OTP_VERIFICATION' => [
        ['type' => 'url', 'param' => 'otp_code']  // Button index 0 uses otp_code
    ],
    'SERVICE_RECEIVED' => [
        ['type' => 'url', 'param' => 'tracking_url']  // Track button uses tracking_url
    ],
    'APPOINTMENT_BOOKED_PAYMENT_SUCCESS' => [
        ['type' => 'url', 'param' => 'tracking_url']  // Track button uses tracking_url
    ],
    'APPOINTMENT_SCHEDULED' => [
        ['type' => 'url', 'param' => 'tracking_url']  // Track button uses tracking_url
    ],
    'OFFLINE_SERVICE_REQUEST_RECEIVED' => [
        ['type' => 'url', 'param' => 'tracking_id']  // Track Service button uses tracking_id
    ],
    'SERVICE_REQUEST_COMPLETED' => [
        ['type' => 'url', 'param' => 'tracking_id']  // Track Service button uses tracking_id (last param {{5}})
    ],
    'SERVICE_REQUEST_FILE_UPLOADED' => [
        ['type' => 'url', 'param' => 'file_path']  // Download button uses file_path (param {{5}})
    ]
]);

/**
 * Notification Settings
 * Control when automatic notifications are sent
 */
define('WHATSAPP_AUTO_NOTIFICATIONS', [
    'appointment_booked_payment_success' => true,
    'service_request_received' => true,
    'service_status_changed' => true,
    'appointment_accepted' => true,
    'appointment_reminder' => true,
    'file_uploaded' => true,
    'payment_received' => true,
    'payment_confirmed' => true
]);

/**
 * Logging Configuration
 */
define('WHATSAPP_LOG_ENABLED', true);
define('WHATSAPP_LOG_FILE', __DIR__ . '/../logs/whatsapp.log');

/**
 * Phone Number Formatting
 */
define('WHATSAPP_COUNTRY_CODE', '91'); // Default country code (India)
define('WHATSAPP_PHONE_VALIDATION', true); // Validate phone numbers before sending
