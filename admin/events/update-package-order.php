<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/event_module.php';

header('Content-Type: application/json; charset=UTF-8');

vs_event_ensure_tables($pdo);

$packageId = (int)($_POST['package_id'] ?? 0);
$eventId = (int)($_POST['event_id'] ?? 0);
$displayOrderRaw = trim((string)($_POST['display_order'] ?? ''));

if ($packageId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid package id.']);
    exit;
}
if ($displayOrderRaw === '' || !preg_match('/^-?\d+$/', $displayOrderRaw)) {
    echo json_encode(['success' => false, 'error' => 'Sort order must be a whole number.']);
    exit;
}

$displayOrder = (int)$displayOrderRaw;
if ($displayOrder < 0) {
    echo json_encode(['success' => false, 'error' => 'Sort order must be zero or more.']);
    exit;
}

try {
    if ($eventId > 0) {
        $updateStmt = $pdo->prepare('UPDATE event_packages SET display_order = ? WHERE id = ? AND event_id = ? LIMIT 1');
        $updateStmt->execute([$displayOrder, $packageId, $eventId]);

        if ($updateStmt->rowCount() === 0) {
            $existsStmt = $pdo->prepare('SELECT id FROM event_packages WHERE id = ? AND event_id = ? LIMIT 1');
            $existsStmt->execute([$packageId, $eventId]);
            if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'error' => 'Package not found.']);
                exit;
            }
        }
    } else {
        $updateStmt = $pdo->prepare('UPDATE event_packages SET display_order = ? WHERE id = ? LIMIT 1');
        $updateStmt->execute([$displayOrder, $packageId]);

        if ($updateStmt->rowCount() === 0) {
            $existsStmt = $pdo->prepare('SELECT id FROM event_packages WHERE id = ? LIMIT 1');
            $existsStmt->execute([$packageId]);
            if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'error' => 'Package not found.']);
                exit;
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Sort order updated.']);
} catch (Throwable $e) {
    error_log('Event package sort update failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unable to update sort order right now.']);
}

