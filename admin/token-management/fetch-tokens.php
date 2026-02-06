<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
$tokens = $pdo->query("SELECT * FROM token_management ORDER BY token_date ASC, from_time ASC")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['tokens' => $tokens]);
