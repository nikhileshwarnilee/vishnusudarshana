<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
// AJAX endpoint for Visitors Log module

require_once '../../config/db.php';
require_once '../../helpers/mobile_display.php';


$action = $_POST['action'] ?? '';

if (!function_exists('vs_insert_visitor_ticket')) {
    function vs_insert_visitor_ticket(
        PDO $pdo,
        string $name,
        string $contact,
        string $address,
        string $purpose,
        string $visitType,
        string $priority,
        string $status
    ): array {
        $name = trim($name);
        $contact = trim($contact);
        $address = trim($address);
        $purpose = trim($purpose);
        $visitType = in_array($visitType, ['inoffice', 'call'], true) ? $visitType : 'inoffice';
        $priority = in_array($priority, ['normal', 'urgent'], true) ? $priority : 'normal';
        $status = in_array($status, ['open', 'closed'], true) ? $status : 'open';

        if ($name === '') {
            return ['success' => false, 'error' => 'Visitor name is required'];
        }

        $inTime = date('Y-m-d H:i:s');
        $outTime = $status === 'closed' ? $inTime : null;

        $stmt = $pdo->prepare("INSERT INTO visitor_tickets
            (visitor_name, contact_number, address, purpose, visit_type, priority, in_time, out_time, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ok = $stmt->execute([$name, $contact, $address, $purpose, $visitType, $priority, $inTime, $outTime, $status]);

        if (!$ok) {
            return ['success' => false, 'error' => 'DB error'];
        }

        return ['success' => true, 'id' => $pdo->lastInsertId()];
    }
}


switch ($action) {
    case 'list':
        $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
        $perPage = isset($_POST['perPage']) ? max(1, (int)$_POST['perPage']) : 10;
        $offset = ($page - 1) * $perPage;

        // Optional search (future-proof, not yet in UI)
        $search = trim($_POST['search'] ?? '');
        $status = isset($_POST['status']) ? trim($_POST['status']) : '';
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = "(visitor_name LIKE :q OR contact_number LIKE :q OR address LIKE :q OR purpose LIKE :q)";
            $params['q'] = "%$search%";
        }
        if ($status !== '') {
            $where[] = "status = :status";
            $params['status'] = $status;
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Get total count
        $countSql = "SELECT COUNT(*) FROM visitor_tickets $whereSql";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Get paginated data
        $sql = "SELECT * FROM visitor_tickets $whereSql ORDER BY in_time DESC LIMIT :perPage OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":$k", $v);
        }
        $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        if (is_array($rows)) {
            foreach ($rows as &$row) {
                $row['contact_number'] = vs_format_mobile_for_display($row['contact_number'] ?? '');
            }
            unset($row);
        }
        echo json_encode(['success' => true, 'data' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage]);
        break;
    case 'add':
        $name = trim($_POST['visitor_name'] ?? '');
        $contact = trim($_POST['contact_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');
        $visit_type = $_POST['visit_type'] ?? 'inoffice';
        $priority = $_POST['priority'] ?? 'normal';
        echo json_encode(vs_insert_visitor_ticket($pdo, $name, $contact, $address, $purpose, $visit_type, $priority, 'open'));
        break;
    case 'add_customer':
        $name = trim($_POST['visitor_name'] ?? '');
        $contact = trim($_POST['contact_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');
        $visit_type = $_POST['visit_type'] ?? 'inoffice';
        $priority = $_POST['priority'] ?? 'normal';
        echo json_encode(vs_insert_visitor_ticket($pdo, $name, $contact, $address, $purpose, $visit_type, $priority, 'closed'));
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
        if (!in_array($status, ['open','closed'])) {
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

