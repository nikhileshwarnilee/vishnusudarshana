<?php
// AJAX endpoint for Visitors Log module

require_once '../../config/db.php';


$action = $_POST['action'] ?? '';


switch ($action) {
    case 'list':
        $stmt = $pdo->query("SELECT * FROM visitor_tickets ORDER BY in_time DESC LIMIT 100");
        $rows = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $rows]);
        break;
    case 'add':
        $name = trim($_POST['visitor_name'] ?? '');
        $contact = trim($_POST['contact_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');
        $visit_type = $_POST['visit_type'] ?? 'inoffice';
        $priority = $_POST['priority'] ?? 'normal';
        $in_time = date('Y-m-d H:i:s');
        if ($name === '') {
            echo json_encode(['success' => false, 'error' => 'Visitor name is required']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO visitor_tickets (visitor_name, contact_number, address, purpose, visit_type, priority, in_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'open')");
        $ok = $stmt->execute([$name, $contact, $address, $purpose, $visit_type, $priority, $in_time]);
        if ($ok) {
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'error' => 'DB error']);
        }
        break;
    case 'close':
        $id = intval($_POST['id'] ?? 0);
        $out_time = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("UPDATE visitor_tickets SET status='closed', out_time=? WHERE id=?");
        $ok = $stmt->execute([$out_time, $id]);
        if ($ok) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'DB error']);
        }
        break;
    case 'update_status':
        $id = intval($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['open','closed','cancelled'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid status']);
            exit;
        }
        $fields = "status=?";
        $params = [$status];
        if ($status === 'closed') {
            $fields .= ", out_time=?";
            $params[] = date('Y-m-d H:i:s');
        }
        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE visitor_tickets SET $fields WHERE id=?");
        $ok = $stmt->execute($params);
        echo json_encode(['success' => $ok]);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
