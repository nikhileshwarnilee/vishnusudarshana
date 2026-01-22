<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

if ($action === 'list') {
    try {
        $stmt = $pdo->query('SELECT id, title FROM letterpad_titles ORDER BY title ASC');
        $titles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'titles' => $titles]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'add') {
    try {
        $title = trim($_POST['title'] ?? '');
        if (!$title) {
            echo json_encode(['success' => false, 'message' => 'Title cannot be empty']);
            exit;
        }

        // Check if title already exists
        $checkStmt = $pdo->prepare('SELECT id FROM letterpad_titles WHERE title = ?');
        $checkStmt->execute([$title]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'This title already exists']);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO letterpad_titles (title) VALUES (?)');
        $stmt->execute([$title]);

        // Get updated options HTML
        $optionsStmt = $pdo->query('SELECT id, title FROM letterpad_titles ORDER BY title ASC');
        $optionsHtml = '<option value=""></option>';
        foreach ($optionsStmt as $row) {
            $id = htmlspecialchars($row['id']);
            $t = htmlspecialchars($row['title']);
            $optionsHtml .= "<option value=\"$id\">$t</option>";
        }

        echo json_encode(['success' => true, 'message' => 'Title added', 'optionsHtml' => $optionsHtml]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'edit') {
    try {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');

        if (!$id || !$title) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }

        // Check if new title already exists (excluding current)
        $checkStmt = $pdo->prepare('SELECT id FROM letterpad_titles WHERE title = ? AND id != ?');
        $checkStmt->execute([$title, $id]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'This title already exists']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE letterpad_titles SET title = ? WHERE id = ?');
        $stmt->execute([$title, $id]);

        // Get updated options HTML
        $optionsStmt = $pdo->query('SELECT id, title FROM letterpad_titles ORDER BY title ASC');
        $optionsHtml = '<option value=""></option>';
        foreach ($optionsStmt as $row) {
            $rid = htmlspecialchars($row['id']);
            $t = htmlspecialchars($row['title']);
            $optionsHtml .= "<option value=\"$rid\">$t</option>";
        }

        echo json_encode(['success' => true, 'message' => 'Title updated', 'optionsHtml' => $optionsHtml]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete') {
    try {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }

        $stmt = $pdo->prepare('DELETE FROM letterpad_titles WHERE id = ?');
        $stmt->execute([$id]);

        // Get updated options HTML
        $optionsStmt = $pdo->query('SELECT id, title FROM letterpad_titles ORDER BY title ASC');
        $optionsHtml = '<option value=""></option>';
        foreach ($optionsStmt as $row) {
            $rid = htmlspecialchars($row['id']);
            $t = htmlspecialchars($row['title']);
            $optionsHtml .= "<option value=\"$rid\">$t</option>";
        }

        echo json_encode(['success' => true, 'message' => 'Title deleted', 'optionsHtml' => $optionsHtml]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
