<?php
require_once '../../config/db.php';

$visitor_id = isset($_POST['ref_id']) ? (int)$_POST['ref_id'] : 0;
$note = trim($_POST['note'] ?? '');
if ($visitor_id <= 0 || $note === '') {
    echo json_encode(['success' => false]);
    exit;
}
$stmt = $pdo->prepare("INSERT INTO visitors_notes (visitor_id, note_text, created_at) VALUES (?, ?, NOW())");
$ok = $stmt->execute([$visitor_id, $note]);
echo json_encode(['success' => $ok]);
