<?php
/**
 * Firebase Cloud Messaging Helper
 * Provides functions to send notifications via FCM
 */

class FirebaseMessaging {
    private $projectId;
    private $serviceAccountPath;
    private $dbConnection;
    private $accessToken;
    private $accessTokenExpiry;

    /**
     * Constructor
     * @param string $projectId Firebase Project ID
     * @param string|null $serviceAccountPath Path to Firebase service account JSON
     * @param mysqli $dbConnection Database connection
     */
    public function __construct($projectId, $serviceAccountPath = null, $dbConnection = null) {
        $this->projectId = $projectId;
        $this->serviceAccountPath = $serviceAccountPath;
        $this->dbConnection = $dbConnection;
        $this->accessToken = null;
        $this->accessTokenExpiry = 0;
    }

    /**
     * Set service account JSON path
     * @param string $serviceAccountPath
     */
    public function setServiceAccountPath($serviceAccountPath) {
        $this->serviceAccountPath = $serviceAccountPath;
        $this->accessToken = null;
        $this->accessTokenExpiry = 0;
    }

    /**
     * Send notification to a single device
     * @param string $deviceToken FCM device token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data to send
     * @param array $options Additional options (requireInteraction, icon, etc)
     * @return array Response with success status and message
     */
    public function sendToDevice($deviceToken, $title, $body, $data = [], $options = []) {
        return $this->sendNotification(
            $deviceToken,
            $title,
            $body,
            $data,
            $options
        );
    }

    /**
     * Send notification to a topic
     * @param string $topic Topic name
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data to send
     * @param array $options Additional options
     * @return array Response with success status and message
     */
    public function sendToTopic($topic, $title, $body, $data = [], $options = []) {
        return $this->sendNotification(
            "/topics/$topic",
            $title,
            $body,
            $data,
            $options
        );
    }

    /**
     * Send notification to multiple devices
     * @param array $deviceTokens Array of FCM device tokens
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data
     * @param array $options Additional options
     * @return array Response with success status and results for each token
     */
    public function sendToMultipleDevices($deviceTokens, $title, $body, $data = [], $options = []) {
        $results = [];
        
        foreach ($deviceTokens as $token) {
            $results[$token] = $this->sendNotification(
                $token,
                $title,
                $body,
                $data,
                $options
            );
        }

        return [
            'success' => true,
            'message' => 'Messages sent to multiple devices',
            'total' => count($deviceTokens),
            'results' => $results
        ];
    }

    /**
     * Send notification to all active devices
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data
     * @param array $options Additional options
     * @return array Response with success status
     */
    public function sendToAllDevices($title, $body, $data = [], $options = []) {
        if (!$this->dbConnection) {
            return [
                'success' => false,
                'message' => 'Database connection not available'
            ];
        }

        // Get all active FCM tokens
        $query = "SELECT token FROM fcm_tokens WHERE is_active = TRUE ORDER BY last_updated DESC LIMIT 1000";
        $result = mysqli_query($this->dbConnection, $query);

        if (!$result) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve tokens: ' . mysqli_error($this->dbConnection)
            ];
        }

        $tokens = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $tokens[] = $row['token'];
        }

        if (empty($tokens)) {
            return [
                'success' => false,
                'message' => 'No active device tokens found'
            ];
        }

        return $this->sendToMultipleDevices($tokens, $title, $body, $data, $options);
    }

    /**
     * Get subscribed devices for a topic
     * @param string $topic Topic name
     * @return array Array of device tokens
     */
    public function getTopicSubscribers($topic) {
        if (!$this->dbConnection) {
            return [];
        }

        $safeTopicName = mysqli_real_escape_string($this->dbConnection, $topic);
        $query = "SELECT DISTINCT token FROM fcm_topic_subscriptions 
                  WHERE topic = '$safeTopicName' AND token IN 
                  (SELECT token FROM fcm_tokens WHERE is_active = TRUE)";
        
        $result = mysqli_query($this->dbConnection, $query);
        $tokens = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $tokens[] = $row['token'];
        }

        return $tokens;
    }

    /**
     * Log notification in database
     * @param string $recipient Recipient identifier (token, user_id, or topic)
     * @param string $title Notification title
     * @param string $body Notification body
     * @param string $type Notification type (device, topic, broadcast)
     * @param string $status Send status (pending, sent, failed)
     * @param string $response API response
     */
    public function logNotification($recipient, $title, $body, $type = 'device', $status = 'sent', $response = '') {
        if (!$this->dbConnection) {
            return false;
        }


        $title = mysqli_real_escape_string($this->dbConnection, $title);
        $body = mysqli_real_escape_string($this->dbConnection, $body);
        $recipient = mysqli_real_escape_string($this->dbConnection, $recipient);
        $response = mysqli_real_escape_string($this->dbConnection, $response);

        $insertSQL = "INSERT INTO fcm_notification_logs 
                      (recipient, title, body, type, status, response) 
                      VALUES ('$recipient', '$title', '$body', '$type', '$status', '$response')";

        return mysqli_query($this->dbConnection, $insertSQL);
    }

    /**
     * Internal method to send notification
     * @private
     */
    private function sendNotification($recipient, $title, $body, $data = [], $options = []) {
        try {
            $recipientType = strpos($recipient, '/topics/') === 0 ? 'topic' : 'device';
            $recipientName = str_replace('/topics/', '', $recipient);

            // Build FCM v1 message
            $message = [
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'data' => $this->stringifyData(array_merge([
                    'timestamp' => (string) time(),
                    'url' => $options['clickAction'] ?? '/'
                ], $data)),
                'webpush' => [
                    'notification' => [
                        'icon' => $options['icon'] ?? '/assets/images/logo/icon-iconpwa192.png',
                        'badge' => $options['badge'] ?? '/assets/images/logo/icon-iconpwa192.png',
                        'requireInteraction' => $options['requireInteraction'] ?? false
                    ],
                    'fcm_options' => [
                        'link' => $options['clickAction'] ?? '/'
                    ]
                ]
            ];

            if ($recipientType === 'topic') {
                $message['topic'] = $recipientName;
            } else {
                $message['token'] = $recipient;
            }

            $payload = [
                'message' => $message
            ];

            $recipientType = strpos($recipient, '/topics/') === 0 ? 'topic' : 'device';
            $recipientName = str_replace('/topics/', '', $recipient);

            // If service account is not configured, just log
            if (empty($this->serviceAccountPath)) {
                if ($this->dbConnection) {
                    $this->logNotification(
                        $recipientName,
                        $title,
                        $body,
                        $recipientType,
                        'sent',
                        json_encode($payload)
                    );
                }

                error_log("FCM Notification queued (no send) for $recipientType: $recipientName");

                return [
                    'success' => true,
                    'message' => "Notification queued (no send) for $recipientType",
                    'recipient' => $recipientName,
                    'type' => $recipientType
                ];
            }

            $response = $this->sendHttpV1Request($payload);
            $status = $response['success'] ? 'sent' : 'failed';

            if ($this->dbConnection) {
                $this->logNotification(
                    $recipientName,
                    $title,
                    $body,
                    $recipientType,
                    $status,
                    json_encode($response)
                );
            }

            return $response + [
                'recipient' => $recipientName,
                'type' => $recipientType
            ];

        } catch (Exception $e) {
            error_log("FCM Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to send notification: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get notification history
     * @param int $limit Number of records to retrieve
     * @param string $filterType Filter by type (device, topic, broadcast)
     * @param string $filterStatus Filter by status (pending, sent, failed)
     * @return array Notification history
     */
    public function getNotificationHistory($limit = 50, $filterType = null, $filterStatus = null) {
        if (!$this->dbConnection) {
            return [];
        }

        $whereConditions = [];
        
        if ($filterType) {
            $filterType = mysqli_real_escape_string($this->dbConnection, $filterType);
            $whereConditions[] = "type = '$filterType'";
        }
        
        if ($filterStatus) {
            $filterStatus = mysqli_real_escape_string($this->dbConnection, $filterStatus);
            $whereConditions[] = "status = '$filterStatus'";
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "SELECT * FROM fcm_notification_logs 
                  $whereClause 
                  ORDER BY sent_at DESC 
                  LIMIT $limit";
        
        $result = mysqli_query($this->dbConnection, $query);
        $history = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $history[] = $row;
        }

        return $history;
    }

    /**
     * Ensure data values are strings for FCM
     */
    private function stringifyData($data) {
        $stringified = [];
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $stringified[$key] = $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $stringified[$key] = '';
            } elseif (is_scalar($value)) {
                $stringified[$key] = (string) $value;
            } else {
                $stringified[$key] = json_encode($value);
            }
        }
        return $stringified;
    }

    /**
     * Send message using FCM HTTP v1 API
     */
    private function sendHttpV1Request($payload) {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'message' => 'Failed to generate access token'
            ];
        }

        $url = 'https://fcm.googleapis.com/v1/projects/' . $this->projectId . '/messages:send';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'message' => 'cURL error: ' . $curlError
            ];
        }

        $decoded = json_decode($responseBody, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'message' => 'Notification sent',
                'response' => $decoded
            ];
        }

        return [
            'success' => false,
            'message' => 'FCM error',
            'http_code' => $httpCode,
            'response' => $decoded ?? $responseBody
        ];
    }

    /**
     * Generate OAuth2 access token from service account
     */
    private function getAccessToken() {
        if ($this->accessToken && $this->accessTokenExpiry > time() + 60) {
            return $this->accessToken;
        }

        $serviceAccount = $this->loadServiceAccount();
        if (!$serviceAccount) {
            return null;
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT'
        ]));

        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $serviceAccount['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600
        ]));

        $signatureInput = $header . '.' . $claims;
        $signature = '';
        $privateKey = $serviceAccount['private_key'];
        $signOk = openssl_sign($signatureInput, $signature, $privateKey, 'sha256');

        if (!$signOk) {
            error_log('FCM Error: Failed to sign JWT');
            return null;
        }

        $jwt = $signatureInput . '.' . $this->base64UrlEncode($signature);

        $tokenResponse = $this->requestAccessToken($serviceAccount['token_uri'], $jwt);
        if (empty($tokenResponse['access_token'])) {
            error_log('FCM Error: Failed to get access token');
            return null;
        }

        $this->accessToken = $tokenResponse['access_token'];
        $this->accessTokenExpiry = $now + (int) ($tokenResponse['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    /**
     * Load service account JSON
     */
    private function loadServiceAccount() {
        if (empty($this->serviceAccountPath)) {
            return null;
        }

        if (!file_exists($this->serviceAccountPath)) {
            error_log('FCM Error: Service account file not found at ' . $this->serviceAccountPath);
            return null;
        }

        $json = file_get_contents($this->serviceAccountPath);
        $data = json_decode($json, true);

        if (empty($data['client_email']) || empty($data['private_key']) || empty($data['token_uri'])) {
            error_log('FCM Error: Invalid service account JSON');
            return null;
        }

        return $data;
    }

    /**
     * Request OAuth token using JWT
     */
    private function requestAccessToken($tokenUri, $jwt) {
        $postFields = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]);

        $ch = curl_init($tokenUri);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('FCM Error: OAuth cURL error ' . $curlError);
            return [];
        }

        return json_decode($responseBody, true) ?? [];
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

?>
