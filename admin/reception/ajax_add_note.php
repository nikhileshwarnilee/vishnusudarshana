<?php
require_once '../../config/db.php';

$visitor_id = isset($_POST['ref_id']) ? (int)$_POST['ref_id'] : 0;
$note = trim($_POST['note'] ?? '');
if ($visitor_id <= 0 || $note === '') {
    echo json_encode(['success' => false]);
    exit;
}
$createdAt = date('Y-m-d H:i:s');
$stmt = $pdo->prepare("INSERT INTO visitors_notes (visitor_id, note_text, created_at) VALUES (?, ?, ?)");
$ok = $stmt->execute([$visitor_id, $note, $createdAt]);
echo json_encode(['success' => $ok]);
