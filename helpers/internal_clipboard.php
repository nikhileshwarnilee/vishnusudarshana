<?php
/**
 * Shared helpers for admin internal clipboard entries.
 */

if (!function_exists('vs_ensure_internal_clipboard_schema')) {
    function vs_ensure_internal_clipboard_schema(PDO $pdo): void
    {
        static $schemaEnsured = false;
        if ($schemaEnsured) {
            return;
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_internal_clipboard (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(180) NOT NULL,
                content_text MEDIUMTEXT NOT NULL,
                created_by_user_id INT DEFAULT NULL,
                created_by_user_name VARCHAR(191) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_admin_internal_clipboard_updated_at (updated_at),
                KEY idx_admin_internal_clipboard_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $schemaEnsured = true;
    }
}

