<?php
require_once '../../config/db.php';
$visitor_id = isset($_POST['ref_id']) ? (int)$_POST['ref_id'] : 0;
if ($visitor_id <= 0) {
    echo json_encode(['success' => false, 'notes' => []]);
    exit;
}
$stmt = $pdo->prepare("SELECT id, note_text, created_at FROM visitors_notes WHERE visitor_id = ? ORDER BY created_at DESC");
$stmt->execute([$visitor_id]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($notes as &$n) {
    $n['note'] = htmlspecialchars($n['note_text']);
}
echo json_encode(['success' => true, 'notes' => $notes]);
