<?php
// fetch_customers.php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo json_encode([]);
    exit;
}
$stmt = $pdo->prepare("SELECT id, name, mobile FROM customers WHERE name LIKE :q OR mobile LIKE :q LIMIT 15");
$stmt->execute(['q' => "%$q%"]);
$results = $stmt->fetchAll();
echo json_encode($results);
