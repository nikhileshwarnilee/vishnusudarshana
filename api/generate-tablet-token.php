<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function respond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function to12Hour(string $timeValue): string
{
    $timeValue = trim($timeValue);
    if ($timeValue === '') {
        return '';
    }

    $formats = ['H:i:s', 'H:i'];
    foreach ($formats as $format) {
        $dateTime = DateTime::createFromFormat($format, $timeValue);
        if ($dateTime instanceof DateTime) {
            return $dateTime->format('g:i A');
        }
    }

    $timestamp = strtotime($timeValue);
    if ($timestamp === false) {
        return $timeValue;
    }

    return date('g:i A', $timestamp);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$rawLocation = isset($_POST['location']) ? trim((string) $_POST['location']) : 'solapur';
$location = strtolower($rawLocation);
if ($location === '' || !preg_match('/^[a-z0-9 _-]+$/', $location)) {
    $location = 'solapur';
}

$phoneNumber = isset($_POST['phone_number']) ? trim((string) $_POST['phone_number']) : '';
if ($phoneNumber === '' && isset($_POST['mobile'])) {
    $phoneNumber = trim((string) $_POST['mobile']);
}
if (!preg_match('/^\d{10}$/', $phoneNumber)) {
    respond([
        'success' => false,
        'invalid_phone' => true,
        'message' => 'Please enter a valid 10 digit mobile number.'
    ], 422);
}

$cooldownSeconds = 30;
$lastIssuedAtMap = (isset($_SESSION['tablet_last_issued_at']) && is_array($_SESSION['tablet_last_issued_at']))
    ? $_SESSION['tablet_last_issued_at']
    : [];
$lastIssuedAt = isset($lastIssuedAtMap[$location]) ? (int) $lastIssuedAtMap[$location] : 0;
$elapsed = time() - $lastIssuedAt;
if ($lastIssuedAt > 0 && $elapsed < $cooldownSeconds) {
    respond([
        'success' => false,
        'cooldown' => true,
        'remaining_seconds' => $cooldownSeconds - $elapsed,
        'message' => 'Please wait 30 seconds before generating next token.'
    ], 429);
}

$tokenDate = date('Y-m-d');
$defaultName = 'Walk-in Visitor';
$mobileNumber = $phoneNumber;

try {
    $pdo->beginTransaction();

    $slotStmt = $pdo->prepare(
        'SELECT id, token_date, from_time, to_time, unbooked_tokens
         FROM token_management
         WHERE (
             DATE(token_date) = ?
             OR token_date = ?
             OR STR_TO_DATE(token_date, "%d-%m-%Y") = ?
         )
         AND LOWER(TRIM(location)) = LOWER(TRIM(?))
         LIMIT 1
         FOR UPDATE'
    );
    $slotStmt->execute([$tokenDate, $tokenDate, $tokenDate, $location]);
    $slot = $slotStmt->fetch(PDO::FETCH_ASSOC);

    if (!$slot || (int) $slot['unbooked_tokens'] <= 0) {
        $pdo->rollBack();
        respond([
            'success' => false,
            'full' => true,
            'message' => "Today's tokens are full. Please come tomorrow."
        ]);
    }

    $maxTokenStmt = $pdo->prepare(
        'SELECT token_no
         FROM token_bookings
         WHERE token_date = ?
         AND LOWER(TRIM(location)) = LOWER(TRIM(?))
         ORDER BY CAST(token_no AS UNSIGNED) DESC
         LIMIT 1
         FOR UPDATE'
    );
    $maxTokenStmt->execute([$tokenDate, $location]);
    $maxTokenNo = $maxTokenStmt->fetchColumn();
    $nextTokenNo = ($maxTokenNo !== null && (int) $maxTokenNo > 0) ? ((int) $maxTokenNo + 1) : 1;

    $fromTime = to12Hour((string) ($slot['from_time'] ?? ''));
    $toTime = to12Hour((string) ($slot['to_time'] ?? ''));
    $serviceTime = trim($fromTime . ($fromTime !== '' && $toTime !== '' ? ' to ' : '') . $toTime);

    $insertStmt = $pdo->prepare(
        'INSERT INTO token_bookings (location, name, mobile, token_date, service_time, token_no)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $insertStmt->execute([$location, $defaultName, $mobileNumber, $tokenDate, $serviceTime, $nextTokenNo]);

    $decrementStmt = $pdo->prepare(
        'UPDATE token_management
         SET unbooked_tokens = unbooked_tokens - 1
         WHERE id = ?
         AND unbooked_tokens > 0'
    );
    $decrementStmt->execute([(int) $slot['id']]);

    if ($decrementStmt->rowCount() === 0) {
        $pdo->rollBack();
        respond([
            'success' => false,
            'full' => true,
            'message' => "Today's tokens are full. Please come tomorrow."
        ]);
    }

    $pdo->commit();
    $lastIssuedAtMap[$location] = time();
    $_SESSION['tablet_last_issued_at'] = $lastIssuedAtMap;

    respond([
        'success' => true,
        'token' => $nextTokenNo,
        'token_no' => $nextTokenNo,
        'token_date' => $tokenDate,
        'location' => $location,
        'phone_number' => $mobileNumber,
        'cooldown_seconds' => $cooldownSeconds
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond([
        'success' => false,
        'message' => 'Unable to generate token right now. Please try again.'
    ], 500);
}
