<?php
// Handle token management form submission
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['token_date'] ?? '';
    $from_time = $_POST['from_time'] ?? '';
    $to_time = $_POST['to_time'] ?? '';
    $total_tokens = intval($_POST['total_tokens'] ?? 0);
    $location = $_POST['location'] ?? 'solapur';
    $notes = $_POST['note'] ?? '';
    // Check for duplicate entry
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM token_management WHERE token_date = ? AND from_time = ? AND to_time = ? AND location = ?");
    $checkStmt->execute([$date, $from_time, $to_time, $location]);
    $exists = $checkStmt->fetchColumn();
    if ($exists > 0) {
        echo json_encode(['success' => false, 'error' => 'Duplicate token entry for this date, time, and location.']);
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO token_management (token_date, from_time, to_time, total_tokens, unbooked_tokens, location, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$date, $from_time, $to_time, $total_tokens, $total_tokens, $location, $notes]);
    echo json_encode(['success' => true]);
    exit;
}
echo json_encode(['success' => false]);
exit;
