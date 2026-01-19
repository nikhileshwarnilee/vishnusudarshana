<?php
/**
 * WhatsApp Business API Configuration
 * Centralized configuration for WhatsApp Cloud API
 * Used across admin panel and website
 */

// WhatsApp Cloud API Credentials
define('WHATSAPP_PHONE_NUMBER_ID', '872295572641175');
define('WHATSAPP_ACCESS_TOKEN', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6IjY5NjU3NzMxODFhMDg2MTIzZWUyMmI3MiIsIm5hbWUiOiJWaXNobnVzdWRhcnNoYW5hIERoYXJtaWsgU2Fuc2thciBLZW5kcmEiLCJhcHBOYW1lIjoiQWlTZW5zeSIsImNsaWVudElkIjoiNjk2NTc3MzE4MWEwODYxMjNlZTIyYjZkIiwiYWN0aXZlUGxhbiI6IlBST19NT05USExZIiwiaWF0IjoxNzY4ODIyODc2fQ.y826CXzzagFeITfVMpuIzph01rLZ2OmvwPaHT6ZpvUM');

// WhatsApp API Configuration
define('WHATSAPP_API_VERSION', 'v18.0');
define('WHATSAPP_API_BASE_URL', 'https://graph.facebook.com');

// Test Mode - Set to true to log messages instead of sending
define('WHATSAPP_TEST_MODE', false);

// Default Language
define('WHATSAPP_DEFAULT_LANGUAGE', 'en');

// Business Details
define('WHATSAPP_BUSINESS_NAME', 'Vishnu Sudarshana');
define('WHATSAPP_BUSINESS_PHONE', '919999999999'); // Your business WhatsApp number

/**
 * Template Names - All approved WhatsApp templates
 * Update these as you create new templates in Meta Business Manager
 */
define('WHATSAPP_TEMPLATES', [
    // Service Request Templates
    'SERVICE_RECEIVED' => 'service_received',
    'SERVICE_ACCEPTED' => 'service_accepted_notification',
    'SERVICE_IN_PROGRESS' => 'service_in_progress',
    'SERVICE_COMPLETED' => 'service_completed',
    'SERVICE_CANCELLED' => 'service_cancelled',
    'SERVICE_FILE_UPLOADED' => 'file_upload_notification',
    
    // Appointment Templates
    'APPOINTMENT_BOOKED_PAYMENT_SUCCESS' => 'appointment_booked_payment_successful',
    'APPOINTMENT_RECEIVED' => 'appointment_received',
    'APPOINTMENT_ACCEPTED' => 'appointment_accepted',
    'APPOINTMENT_REMINDER' => 'appointment_reminder',
    'APPOINTMENT_COMPLETED' => 'appointment_completed',
    'APPOINTMENT_CANCELLED' => 'appointment_cancelled',
    'APPOINTMENT_MISSED' => 'appointment_missed',
    
    // Payment Templates
    'PAYMENT_RECEIVED' => 'payment_received',
    'PAYMENT_PENDING' => 'payment_pending',
    'PAYMENT_CONFIRMED' => 'payment_confirmed',
    'INVOICE_GENERATED' => 'invoice_generated',
    'PAYMENT_REMINDER' => 'payment_reminder',
    
    // General Templates
    'WELCOME_MESSAGE' => 'welcome_message',
    'OTP_VERIFICATION' => 'otp_verification_code',
    'CUSTOM_MESSAGE' => 'custom_message'
]);

/**
 * Template Variable Configurations
 * Define required variables for each template
 */
define('WHATSAPP_TEMPLATE_VARIABLES', [
    'SERVICE_RECEIVED' => ['name', 'tracking_code', 'service_name', 'tracking_url'],
    'SERVICE_ACCEPTED_NOTIFICATION' => ['name', 'tracking_code'],
    'SERVICE_COMPLETED' => ['name', 'tracking_code', 'service_name'],
    'FILE_UPLOAD_NOTIFICATION' => ['name', 'tracking_code'],
    'APPOINTMENT_BOOKED_PAYMENT_SUCCESS' => ['name', 'tracking_code', 'service_name', 'tracking_url'],
    'APPOINTMENT_ACCEPTED' => ['name', 'tracking_code', 'appointment_date', 'appointment_time'],
    'APPOINTMENT_MISSED' => ['name', 'tracking_code'],
    'PAYMENT_RECEIVED' => ['name', 'amount', 'tracking_code'],
    'PAYMENT_CONFIRMED' => ['name', 'amount', 'payment_id'],
    'OTP_VERIFICATION' => ['otp_code', 'validity_minutes']
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
