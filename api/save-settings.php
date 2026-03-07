<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function respond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function ensureSettingsTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS system_settings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(120) NOT NULL,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function normalizeAnnouncementLanguage(?string $value): string
{
    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['marathi', 'english'], true) ? $normalized : 'marathi';
}

function normalizeTabletWhatsAppEnabled($value): int
{
    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on', 'enabled'], true) ? 1 : 0;
}

function normalizeSameDayOnlineBookingCutoffTime($value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '09:00';
    }

    if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $raw, $matches)) {
        return $matches[1] . ':' . $matches[2];
    }

    $timestamp = strtotime($raw);
    if ($timestamp !== false) {
        return date('H:i', $timestamp);
    }

    return '09:00';
}

function upsertSetting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO system_settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function getSettingValue(PDO $pdo, string $key): ?string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (string) $value;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$hasLanguage = array_key_exists('announcement_language', $_POST);
$hasTabletWhatsApp = array_key_exists('tablet_token_whatsapp_enabled', $_POST);
$hasSameDayCutoff = array_key_exists('same_day_online_booking_cutoff_time', $_POST);

if (!$hasLanguage && !$hasTabletWhatsApp && !$hasSameDayCutoff) {
    respond([
        'success' => false,
        'message' => 'No supported setting provided.'
    ], 422);
}

try {
    ensureSettingsTable($pdo);

    if ($hasLanguage) {
        $language = normalizeAnnouncementLanguage($_POST['announcement_language'] ?? null);
        upsertSetting($pdo, 'announcement_language', $language);
    }
    if ($hasTabletWhatsApp) {
        $tabletWhatsAppEnabled = normalizeTabletWhatsAppEnabled($_POST['tablet_token_whatsapp_enabled'] ?? null);
        upsertSetting($pdo, 'tablet_token_whatsapp_enabled', (string) $tabletWhatsAppEnabled);
    }
    if ($hasSameDayCutoff) {
        $sameDayCutoffTime = normalizeSameDayOnlineBookingCutoffTime($_POST['same_day_online_booking_cutoff_time'] ?? null);
        upsertSetting($pdo, 'same_day_online_booking_cutoff_time', $sameDayCutoffTime);
    }

    $savedLanguage = normalizeAnnouncementLanguage(getSettingValue($pdo, 'announcement_language'));
    $savedTabletWhatsAppEnabled = normalizeTabletWhatsAppEnabled(getSettingValue($pdo, 'tablet_token_whatsapp_enabled') ?? '1');
    $savedSameDayCutoffTime = normalizeSameDayOnlineBookingCutoffTime(getSettingValue($pdo, 'same_day_online_booking_cutoff_time'));

    respond([
        'success' => true,
        'announcement_language' => $savedLanguage,
        'tablet_token_whatsapp_enabled' => $savedTabletWhatsAppEnabled === 1,
        'same_day_online_booking_cutoff_time' => $savedSameDayCutoffTime,
        'message' => 'Settings saved.'
    ]);
} catch (Throwable $e) {
    respond([
        'success' => false,
        'message' => 'Unable to save settings right now.'
    ], 500);
}
