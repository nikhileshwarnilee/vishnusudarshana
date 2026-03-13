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

function normalizeLiveTokenAnnouncementEnabled($value): int
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

function normalizeTokenBookingCommonNoteText($value): string
{
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, 3000);
    }
    return substr($text, 0, 3000);
}

function normalizeTokenBookingCommonNoteEnabled($value): int
{
    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'on', 'enabled'], true) ? 1 : 0;
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
$hasLiveTokenAnnouncementEnabled = array_key_exists('live_token_announcement_enabled', $_POST);
$hasSameDayCutoff = array_key_exists('same_day_online_booking_cutoff_time', $_POST);
$hasTokenBookingCommonNote = array_key_exists('token_booking_common_note', $_POST);
$hasTokenBookingCommonNoteEnabled = array_key_exists('token_booking_common_note_enabled', $_POST);

if (!$hasLanguage && !$hasTabletWhatsApp && !$hasLiveTokenAnnouncementEnabled && !$hasSameDayCutoff && !$hasTokenBookingCommonNote && !$hasTokenBookingCommonNoteEnabled) {
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
    if ($hasLiveTokenAnnouncementEnabled) {
        $liveTokenAnnouncementEnabled = normalizeLiveTokenAnnouncementEnabled($_POST['live_token_announcement_enabled'] ?? null);
        upsertSetting($pdo, 'live_token_announcement_enabled', (string) $liveTokenAnnouncementEnabled);
    }
    if ($hasSameDayCutoff) {
        $sameDayCutoffTime = normalizeSameDayOnlineBookingCutoffTime($_POST['same_day_online_booking_cutoff_time'] ?? null);
        upsertSetting($pdo, 'same_day_online_booking_cutoff_time', $sameDayCutoffTime);
    }
    if ($hasTokenBookingCommonNote) {
        $tokenBookingCommonNote = normalizeTokenBookingCommonNoteText($_POST['token_booking_common_note'] ?? null);
        upsertSetting($pdo, 'token_booking_common_note', $tokenBookingCommonNote);
    }
    if ($hasTokenBookingCommonNoteEnabled) {
        $tokenBookingCommonNoteEnabled = normalizeTokenBookingCommonNoteEnabled($_POST['token_booking_common_note_enabled'] ?? null);
        upsertSetting($pdo, 'token_booking_common_note_enabled', (string) $tokenBookingCommonNoteEnabled);
    }

    $savedLanguage = normalizeAnnouncementLanguage(getSettingValue($pdo, 'announcement_language'));
    $savedTabletWhatsAppEnabled = normalizeTabletWhatsAppEnabled(getSettingValue($pdo, 'tablet_token_whatsapp_enabled') ?? '1');
    $savedLiveTokenAnnouncementEnabled = normalizeLiveTokenAnnouncementEnabled(getSettingValue($pdo, 'live_token_announcement_enabled') ?? '1');
    $savedSameDayCutoffTime = normalizeSameDayOnlineBookingCutoffTime(getSettingValue($pdo, 'same_day_online_booking_cutoff_time'));
    $savedTokenBookingCommonNote = normalizeTokenBookingCommonNoteText(getSettingValue($pdo, 'token_booking_common_note'));
    $savedTokenBookingCommonNoteEnabled = normalizeTokenBookingCommonNoteEnabled(getSettingValue($pdo, 'token_booking_common_note_enabled') ?? '1');

    respond([
        'success' => true,
        'announcement_language' => $savedLanguage,
        'tablet_token_whatsapp_enabled' => $savedTabletWhatsAppEnabled === 1,
        'live_token_announcement_enabled' => $savedLiveTokenAnnouncementEnabled === 1,
        'same_day_online_booking_cutoff_time' => $savedSameDayCutoffTime,
        'token_booking_common_note' => $savedTokenBookingCommonNote,
        'token_booking_common_note_enabled' => $savedTokenBookingCommonNoteEnabled === 1,
        'message' => 'Settings saved.'
    ]);
} catch (Throwable $e) {
    respond([
        'success' => false,
        'message' => 'Unable to save settings right now.'
    ], 500);
}
