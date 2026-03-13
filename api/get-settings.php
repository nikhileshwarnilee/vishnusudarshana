<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

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

function getSettingValue(PDO $pdo, string $key): ?string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (string) $value;
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

try {
    ensureSettingsTable($pdo);

    $languageRaw = getSettingValue($pdo, 'announcement_language');
    $tabletWhatsAppRaw = getSettingValue($pdo, 'tablet_token_whatsapp_enabled');
    $liveTokenAnnouncementRaw = getSettingValue($pdo, 'live_token_announcement_enabled');
    $sameDayCutoffRaw = getSettingValue($pdo, 'same_day_online_booking_cutoff_time');
    $tokenBookingCommonNoteRaw = getSettingValue($pdo, 'token_booking_common_note');
    $tokenBookingCommonNoteEnabledRaw = getSettingValue($pdo, 'token_booking_common_note_enabled');

    $language = normalizeAnnouncementLanguage($languageRaw);
    $tabletWhatsAppEnabled = normalizeTabletWhatsAppEnabled($tabletWhatsAppRaw ?? '1');
    $liveTokenAnnouncementEnabled = normalizeLiveTokenAnnouncementEnabled($liveTokenAnnouncementRaw ?? '1');
    $sameDayCutoffTime = normalizeSameDayOnlineBookingCutoffTime($sameDayCutoffRaw);
    $tokenBookingCommonNote = normalizeTokenBookingCommonNoteText($tokenBookingCommonNoteRaw);
    $tokenBookingCommonNoteEnabled = normalizeTokenBookingCommonNoteEnabled($tokenBookingCommonNoteEnabledRaw ?? '1');

    // Ensure persistent defaults exist.
    if ($languageRaw === null) {
        upsertSetting($pdo, 'announcement_language', $language);
    }
    if ($tabletWhatsAppRaw === null) {
        upsertSetting($pdo, 'tablet_token_whatsapp_enabled', (string) $tabletWhatsAppEnabled);
    }
    if ($liveTokenAnnouncementRaw === null) {
        upsertSetting($pdo, 'live_token_announcement_enabled', (string) $liveTokenAnnouncementEnabled);
    }
    if ($sameDayCutoffRaw === null) {
        upsertSetting($pdo, 'same_day_online_booking_cutoff_time', $sameDayCutoffTime);
    }
    if ($tokenBookingCommonNoteRaw === null) {
        upsertSetting($pdo, 'token_booking_common_note', $tokenBookingCommonNote);
    }
    if ($tokenBookingCommonNoteEnabledRaw === null) {
        upsertSetting($pdo, 'token_booking_common_note_enabled', (string) $tokenBookingCommonNoteEnabled);
    }

    respond([
        'success' => true,
        'announcement_language' => $language,
        'tablet_token_whatsapp_enabled' => $tabletWhatsAppEnabled === 1,
        'live_token_announcement_enabled' => $liveTokenAnnouncementEnabled === 1,
        'same_day_online_booking_cutoff_time' => $sameDayCutoffTime,
        'token_booking_common_note' => $tokenBookingCommonNote,
        'token_booking_common_note_enabled' => $tokenBookingCommonNoteEnabled === 1,
    ]);
} catch (Throwable $e) {
    respond([
        'success' => false,
        'announcement_language' => 'marathi',
        'tablet_token_whatsapp_enabled' => true,
        'live_token_announcement_enabled' => true,
        'same_day_online_booking_cutoff_time' => '09:00',
        'token_booking_common_note' => '',
        'token_booking_common_note_enabled' => true,
        'message' => 'Unable to load settings right now.'
    ], 500);
}
