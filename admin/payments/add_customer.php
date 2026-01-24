<?php
// add_customer.php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
$address = isset($_POST['address']) ? trim($_POST['address']) : '';

if ($name === '' || $mobile === '') {
    echo json_encode(['success' => false, 'error' => 'Name and Mobile are required.']);
    exit;
}

// Check for duplicate mobile
$stmt = $pdo->prepare('SELECT id FROM customers WHERE mobile = ? LIMIT 1');
$stmt->execute([$mobile]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Mobile number already exists.']);
    exit;
}

$createdAt = date('Y-m-d H:i:s');
$stmt = $pdo->prepare('INSERT INTO customers (name, mobile, address, created_at) VALUES (?, ?, ?, ?)');
try {
    if ($stmt->execute([$name, $mobile, $address, $createdAt])) {
        $id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'customer' => ['id' => $id, 'name' => $name, 'mobile' => $mobile, 'address' => $address]]);
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log('Add customer DB error: ' . print_r($errorInfo, true));
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $errorInfo[2]]);
    }
} catch (Throwable $e) {
    error_log('Add customer exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
}
