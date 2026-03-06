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

try {
    ensureSettingsTable($pdo);

    $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute(['announcement_language']);
    $language = normalizeAnnouncementLanguage($stmt->fetchColumn() ?: null);

    // Ensure a persistent default exists for first-time installs.
    if ($stmt->rowCount() === 0) {
        $insertStmt = $pdo->prepare(
            'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)'
        );
        $insertStmt->execute(['announcement_language', $language]);
    }

    respond([
        'success' => true,
        'announcement_language' => $language,
    ]);
} catch (Throwable $e) {
    respond([
        'success' => false,
        'announcement_language' => 'marathi',
        'message' => 'Unable to load settings right now.'
    ], 500);
}

