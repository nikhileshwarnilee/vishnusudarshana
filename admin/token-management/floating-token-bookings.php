<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('view');
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare(
        "SELECT id, token_no, name, location, status
         FROM token_bookings
         WHERE token_date = ?
           AND (
                status IS NULL
                OR TRIM(status) = ''
                OR LOWER(TRIM(status)) NOT IN ('completed', 'skip')
           )
         ORDER BY LOWER(TRIM(location)) ASC, CAST(token_no AS UNSIGNED) ASC, token_no ASC
         LIMIT 100"
    );
    $stmt->execute([$today]);

    echo json_encode([
        'success' => true,
        'date' => $today,
        'tokens' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load today tokens.',
        'tokens' => [],
    ]);
}

