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
    // Service Request Templates - All use "Services Booking" campaign
    'SERVICE_RECEIVED' => 'Services Booking',
    'SERVICE_ACCEPTED' => 'Services Booking',
    'SERVICE_IN_PROGRESS' => 'Services Booking',
    'SERVICE_COMPLETED' => 'Services Booking',
    'SERVICE_CANCELLED' => 'Services Booking',
    'SERVICE_FILE_UPLOADED' => 'Services Booking',
    
    // Appointment Templates - All use "Appointment Booking" campaign
    'APPOINTMENT_BOOKED_PAYMENT_SUCCESS' => 'Appointment Booking',
    'APPOINTMENT_RECEIVED' => 'Appointment Booking',
    'APPOINTMENT_ACCEPTED' => 'Appointment Booking',
    'APPOINTMENT_REMINDER' => 'Appointment Booking',
    'APPOINTMENT_COMPLETED' => 'Appointment Booking',
    'APPOINTMENT_CANCELLED' => 'Appointment Booking',
    'APPOINTMENT_MISSED' => 'Appointment Booking',
    
    // Payment Templates - Use "Services Booking" campaign
    'PAYMENT_RECEIVED' => 'Services Booking',
    'PAYMENT_PENDING' => 'Services Booking',
    'PAYMENT_CONFIRMED' => 'Services Booking',
    'INVOICE_GENERATED' => 'Services Booking',
    'PAYMENT_REMINDER' => 'Services Booking',
    
    // General Templates
    'WELCOME_MESSAGE' => 'Services Booking',
    'OTP_VERIFICATION' => 'OTP for Download',
    'CUSTOM_MESSAGE' => 'Services Booking'
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
    'OTP_VERIFICATION' => ['name']  // {{1}} = name for message body
]);

/**
 * Template Button Configurations
 * Define button parameters for templates with URL buttons
 */
define('WHATSAPP_TEMPLATE_BUTTONS', [
    'OTP_VERIFICATION' => [
        ['type' => 'url', 'param' => 'otp_code']  // Button index 0 uses otp_code
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
