<?php
// Firebase Cloud Messaging (FCM) v1 API Configuration
// No server key needed! Uses OAuth 2.0 with service account instead.

// Your Firebase Project ID (found in Firebase Console > Project Settings)
define('FCM_PROJECT_ID', 'vishnusudarshana-cfcf7');

// Path to your Firebase service account JSON file
// Download from: Firebase Console > Project Settings > Service Accounts > Generate New Private Key
define('FCM_SERVICE_ACCOUNT_PATH', __DIR__ . '/firebase-service-account.json');

// FCM v1 API endpoint
define('FCM_API_URL', 'https://fcm.googleapis.com/v1/projects/' . FCM_PROJECT_ID . '/messages:send');

// OAuth scope for FCM
define('FCM_SCOPE', 'https://www.googleapis.com/auth/firebase.messaging');
