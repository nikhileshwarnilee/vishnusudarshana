<?php
require_once __DIR__ . '/../../config/db.php';
session_start();
$user_id = $_SESSION['user_id'] ?? 0;
$isAdmin = ($user_id == 1);

$action = $_POST['action'] ?? '';

if ($action === 'fetch') {
    $assigned_user_id = (int)($_POST['assigned_user_id'] ?? 0);
    $start = $_POST['start'] ?? date('Y-m-d');
    $end = $_POST['end'] ?? date('Y-m-d');
    $where = 'assigned_user_id = ? AND schedule_date BETWEEN ? AND ?';
    $params = [$assigned_user_id, $start, $end];
    $stmt = $pdo->prepare("SELECT * FROM admin_schedule WHERE $where");
    $stmt->execute($params);
    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $color = '#FFD700'; // Tentative
        if ($row['status'] === 'confirmed') $color = '#1a8917';
        if ($row['status'] === 'blocked') $color = '#dc3545';
        $events[] = [
            'id' => $row['id'],
            'title' => $row['title'] . ' (' . ucfirst($row['status']) . ')',
            'start' => $row['schedule_date'] . 'T' . $row['start_time'],
            'end' => $row['schedule_date'] . 'T' . $row['end_time'],
            'color' => $color,
            'extendedProps' => [
                'description' => $row['description'],
                'status' => $row['status'],
                'assigned_user_id' => $row['assigned_user_id'],
            ]
        ];
    }
    echo json_encode($events);
    exit;
}

if ($action === 'create' && $isAdmin) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $schedule_date = $_POST['schedule_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $status = $_POST['status'] ?? 'tentative';
    $assigned_user_id = (int)($_POST['assigned_user_id'] ?? 0);
    if (!$title || !$schedule_date || !$start_time || !$end_time || !$assigned_user_id) {
        echo json_encode(['success' => false, 'msg' => 'All required fields must be filled.']);
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO admin_schedule (title, description, schedule_date, start_time, end_time, status, assigned_user_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $description, $schedule_date, $start_time, $end_time, $status, $assigned_user_id, $user_id]);
    echo json_encode(['success' => true, 'msg' => 'Schedule created successfully.']);
    exit;
}

echo json_encode(['success' => false, 'msg' => 'Invalid request.']);
exit;
