<?php
// config/db.php
// Reusable PDO database connection for Vishnusudarshana
// Switches between local dev and production credentials automatically

// Set timezone to India Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');

// Detect Codespaces or localhost for development
$isLocal = false;
if (
    (getenv('CODESPACES') !== false) ||
    (isset($_SERVER['CODESPACES']) && $_SERVER['CODESPACES']) ||
    (isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1'))
) {
    $isLocal = true;
}

if ($isLocal) {
    // Local development credentials
    $DB_HOST = 'localhost';
    $DB_USER = 'root';
    $DB_PASS = '';
    $DB_NAME = 'vishnusudarshana';
} else {
    // Production/hosting credentials
    $DB_HOST = '162.214.80.18';
    $DB_USER = 'fkwxcbmy_vishnusudarshana';
    $DB_PASS = 'Admin@01234';
    $DB_NAME = 'fkwxcbmy_vishnusudarshana';
}

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    // Set MySQL timezone per session (safe for shared hosting)
    $pdo->exec("SET time_zone = '+05:30'");
} catch (PDOException $e) {
    die('Database connection failed. Please try again later.');
}

// Backward-compatible mysqli connection for legacy modules.
// Keep this non-fatal so PDO-based pages continue to work even if mysqli is unavailable.
$connection = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($connection) {
    mysqli_set_charset($connection, 'utf8mb4');
    mysqli_query($connection, "SET time_zone = '+05:30'");
}

// Usage: include this file and use $pdo (preferred) or $connection (legacy).
