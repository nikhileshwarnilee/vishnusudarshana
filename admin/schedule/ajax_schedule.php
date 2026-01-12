<?php
require_once __DIR__ . '/../../config/db.php';
session_start();
$user_id = $_SESSION['user_id'] ?? 0;
$isAdmin = ($user_id == 1);

$action = $_POST['action'] ?? '';

if ($action === 'fetch') {
    $all_users = isset($_POST['all_users']) && $_POST['all_users'] == '1';
    $start = $_POST['start'] ?? date('Y-m-d');
    $end = $_POST['end'] ?? date('Y-m-d');
    $events = [];
    if ($all_users) {
        $sql = "SELECT a.*, u.name as assigned_user_name FROM admin_schedule a JOIN users u ON a.assigned_user_id = u.id WHERE (a.schedule_date <= ? AND (a.end_date IS NULL OR a.end_date >= ?))";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$end, $start]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $color = '#FFD700';
            if ($row['status'] === 'confirmed') $color = '#1a8917';
            if ($row['status'] === 'blocked') $color = '#dc3545';
            $endDate = $row['end_date'] ?? $row['schedule_date'];
            // Check if it's a 24-hour block
            $is24Hour = ($row['start_time'] === '00:00:00' && $row['end_time'] === '23:59:59') || 
                        ($row['start_time'] === '00:00:00' && $row['end_time'] === '24:00:00');
            $event = [
                'id' => $row['id'],
                'title' => $row['assigned_user_name'] . ' – ' . $row['title'] . ' (' . ucfirst($row['status']) . ')',
                'start' => $row['schedule_date'] . 'T' . $row['start_time'],
                'end' => $endDate . 'T' . $row['end_time'],
                'color' => $color,
                'allDay' => $is24Hour,
                'extendedProps' => [
                    'description' => $row['description'],
                    'status' => $row['status'],
                    'assigned_user_id' => $row['assigned_user_id'],
                    'assigned_user_name' => $row['assigned_user_name'],
                ]
            ];
            $events[] = $event;
        }
    } else {
        $assigned_user_id = (int)($_POST['assigned_user_id'] ?? 0);
        $sql = "SELECT * FROM admin_schedule WHERE assigned_user_id = ? AND (schedule_date <= ? AND (end_date IS NULL OR end_date >= ?))";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$assigned_user_id, $end, $start]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $color = '#FFD700';
            if ($row['status'] === 'confirmed') $color = '#1a8917';
            if ($row['status'] === 'blocked') $color = '#dc3545';
            $endDate = $row['end_date'] ?? $row['schedule_date'];
            // Check if it's a 24-hour block
            $is24Hour = ($row['start_time'] === '00:00:00' && $row['end_time'] === '23:59:59') || 
                        ($row['start_time'] === '00:00:00' && $row['end_time'] === '24:00:00');
            $event = [
                'id' => $row['id'],
                'title' => $row['title'] . ' (' . ucfirst($row['status']) . ')',
                'start' => $row['schedule_date'] . 'T' . $row['start_time'],
                'end' => $endDate . 'T' . $row['end_time'],
                'color' => $color,
                'allDay' => $is24Hour,
                'extendedProps' => [
                    'description' => $row['description'],
                    'status' => $row['status'],
                    'assigned_user_id' => $row['assigned_user_id'],
                ]
            ];
            $events[] = $event;
        }
    }
    echo json_encode($events);
    exit;
}

if ($action === 'create' && ($isAdmin || $assigned_user_id === $user_id)) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_date = $_POST['start_date'] ?? ($_POST['schedule_date'] ?? '');
    $end_date = $_POST['end_date'] ?? $start_date;
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $status = $_POST['status'] ?? 'tentative';
    $assigned_user_id = (int)($_POST['assigned_user_id'] ?? 0);
    if (!$title || !$start_date || !$start_time || !$end_time || !$assigned_user_id) {
        echo json_encode(['success' => false, 'msg' => 'All required fields must be filled.']);
        exit;
    }
    // Create single entry for the range (not multiple per date)
    // Conflict detection: check if there's any overlap in the date range
    $conflictStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_schedule WHERE assigned_user_id = ? AND ((schedule_date <= ? AND (end_date IS NULL OR end_date >= ?)) OR (schedule_date >= ? AND schedule_date <= ?))");
    $conflictStmt->execute([$assigned_user_id, $end_date, $start_date, $start_date, $end_date]);
    $conflictCount = $conflictStmt->fetchColumn();
    if ($conflictCount > 0) {
        echo json_encode([
            'success' => false,
            'status' => 'conflict',
            'message' => 'Selected user already has a schedule during this date range.'
        ]);
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO admin_schedule (title, description, schedule_date, end_date, start_time, end_time, status, assigned_user_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $description, $start_date, $end_date, $start_time, $end_time, $status, $assigned_user_id, $user_id]);
    echo json_encode(['success' => true, 'msg' => 'Schedule created successfully.']);
    exit;
}

if ($action === 'update' && $isAdmin) {
    $event_id = (int)($_POST['event_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $schedule_date = $_POST['schedule_date'] ?? ($_POST['start_date'] ?? '');
    $end_date = $_POST['end_date'] ?? $schedule_date;
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $status = $_POST['status'] ?? 'tentative';
    $assigned_user_id = (int)($_POST['assigned_user_id'] ?? 0);
    
    if (!$event_id || !$title || !$schedule_date || !$start_time || !$end_time || !$assigned_user_id) {
        echo json_encode(['success' => false, 'msg' => 'All required fields must be filled.']);
        exit;
    }
    
    // Conflict detection (excluding current event)
    $conflictStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_schedule WHERE id != ? AND assigned_user_id = ? AND schedule_date = ? AND (? < end_time AND ? > start_time)");
    $conflictStmt->execute([$event_id, $assigned_user_id, $schedule_date, $start_time, $end_time]);
    $conflictCount = $conflictStmt->fetchColumn();
    if ($conflictCount > 0) {
        echo json_encode([
            'success' => false,
            'status' => 'conflict',
            'message' => 'Selected user already has a schedule during this time.'
        ]);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE admin_schedule SET title = ?, description = ?, schedule_date = ?, end_date = ?, start_time = ?, end_time = ?, status = ?, assigned_user_id = ? WHERE id = ?");
    $stmt->execute([$title, $description, $schedule_date, $end_date, $start_time, $end_time, $status, $assigned_user_id, $event_id]);
    echo json_encode(['success' => true, 'msg' => 'Schedule updated successfully.']);
    exit;
}

if ($action === 'delete' && $isAdmin) {
    $event_id = (int)($_POST['event_id'] ?? 0);
    if (!$event_id) {
        echo json_encode(['success' => false, 'msg' => 'Invalid event ID.']);
        exit;
    }
    
    $stmt = $pdo->prepare("DELETE FROM admin_schedule WHERE id = ?");
    $stmt->execute([$event_id]);
    echo json_encode(['success' => true, 'msg' => 'Schedule deleted successfully.']);
    exit;
}

echo json_encode(['success' => false, 'msg' => 'Invalid request.']);
exit;
