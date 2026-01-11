<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo json_encode([]);
    exit;
}
$stmt = $pdo->prepare('SELECT id, name, mobile FROM cif_clients WHERE name LIKE ? OR mobile LIKE ? ORDER BY name ASC LIMIT 10');
$stmt->execute(["%$q%", "%$q%"]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results);
