<?php
/**
 * Shared admin auth helpers.
 */

if (!function_exists('vs_admin_super_admin_emails')) {
    function vs_admin_super_admin_emails(): array
    {
        $emails = ['vishnusudarshana@gmail.com'];
        $fromEnv = getenv('VS_SUPER_ADMIN_EMAILS');
        if ($fromEnv !== false && trim((string)$fromEnv) !== '') {
            foreach (explode(',', (string)$fromEnv) as $email) {
                $email = strtolower(trim($email));
                if ($email !== '') {
                    $emails[] = $email;
                }
            }
        }

        return array_values(array_unique(array_map('strtolower', $emails)));
    }
}

if (!function_exists('vs_admin_use_id1_fallback')) {
    function vs_admin_use_id1_fallback(): bool
    {
        $flag = strtolower(trim((string)(getenv('VS_SUPER_ADMIN_ID1_FALLBACK') ?: '')));
        return in_array($flag, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('vs_admin_is_super_admin_by_user')) {
    function vs_admin_is_super_admin_by_user(array $user): bool
    {
        $email = strtolower(trim((string)($user['email'] ?? '')));
        $id = (int)($user['id'] ?? 0);
        if (in_array($email, vs_admin_super_admin_emails(), true)) {
            return true;
        }

        return vs_admin_use_id1_fallback() && $id === 1;
    }
}

if (!function_exists('vs_admin_mark_session_user')) {
    function vs_admin_mark_session_user(array $user): void
    {
        $_SESSION['user_id'] = (int)($user['id'] ?? 0);
        $_SESSION['user_name'] = (string)($user['name'] ?? '');
        $_SESSION['user_email'] = (string)($user['email'] ?? '');
        $_SESSION['is_super_admin'] = vs_admin_is_super_admin_by_user($user);
    }
}

if (!function_exists('vs_admin_is_super_admin')) {
    function vs_admin_is_super_admin(): bool
    {
        if (!empty($_SESSION['is_super_admin'])) {
            return true;
        }

        $email = strtolower(trim((string)($_SESSION['user_email'] ?? '')));
        $userId = (int)($_SESSION['user_id'] ?? 0);

        // Backfill email for older sessions so permission checks stay correct.
        if ($email === '' && $userId > 0) {
            if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
                require_once __DIR__ . '/../../config/db.php';
                if (isset($pdo) && $pdo instanceof PDO) {
                    $GLOBALS['pdo'] = $pdo;
                }
            }
            $pdoRef = $GLOBALS['pdo'] ?? null;
            if ($pdoRef instanceof PDO) {
                $stmt = $pdoRef->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$userId]);
                $dbEmail = strtolower(trim((string)$stmt->fetchColumn()));
                if ($dbEmail !== '') {
                    $_SESSION['user_email'] = $dbEmail;
                    $email = $dbEmail;
                }
            }
        }

        if ($email !== '' && in_array($email, vs_admin_super_admin_emails(), true)) {
            $_SESSION['is_super_admin'] = true;
            return true;
        }

        // Optional legacy fallback for old setups.
        return vs_admin_use_id1_fallback() && $userId === 1;
    }
}
