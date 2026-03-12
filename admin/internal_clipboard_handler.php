<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/internal_clipboard.php';

header('Content-Type: application/json; charset=UTF-8');

if (function_exists('vs_admin_start_session_if_needed')) {
    vs_admin_start_session_if_needed();
} elseif (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to continue.']);
    exit;
}

try {
    vs_ensure_internal_clipboard_schema($pdo);
} catch (Throwable $e) {
    error_log('Internal clipboard schema error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to initialize clipboard storage.']);
    exit;
}

$action = strtolower(trim((string)($_REQUEST['action'] ?? 'list')));
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$isMutation = in_array($action, ['add', 'edit', 'delete'], true);

if ($isMutation && $method !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    if ($action === 'list') {
        $stmt = $pdo->query("
            SELECT id, title, content_text, created_by_user_id, created_by_user_name, created_at, updated_at
            FROM admin_internal_clipboard
            ORDER BY updated_at DESC, id DESC
        ");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        echo json_encode(['success' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'add') {
        $title = trim((string)($_POST['title'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        if ($title === '' || $content === '') {
            echo json_encode(['success' => false, 'message' => 'Title and clipboard content are required.']);
            exit;
        }

        if (function_exists('mb_substr')) {
            $title = mb_substr($title, 0, 180);
        } else {
            $title = substr($title, 0, 180);
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            INSERT INTO admin_internal_clipboard
            (title, content_text, created_by_user_id, created_by_user_name, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $title,
            $content,
            (int)($_SESSION['user_id'] ?? 0),
            trim((string)($_SESSION['user_name'] ?? '')),
            $now,
            $now
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Clipboard entry added.',
            'id' => (int)$pdo->lastInsertId()
        ]);
        exit;
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        if ($id <= 0 || $title === '' || $content === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid clipboard entry details.']);
            exit;
        }

        if (function_exists('mb_substr')) {
            $title = mb_substr($title, 0, 180);
        } else {
            $title = substr($title, 0, 180);
        }

        $stmt = $pdo->prepare("
            UPDATE admin_internal_clipboard
            SET title = ?, content_text = ?, updated_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $content, date('Y-m-d H:i:s'), $id]);

        if ($stmt->rowCount() < 1) {
            $checkStmt = $pdo->prepare("SELECT id FROM admin_internal_clipboard WHERE id = ? LIMIT 1");
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'Clipboard entry not found.']);
                exit;
            }
            echo json_encode(['success' => true, 'message' => 'No changes detected.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Clipboard entry updated.']);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid clipboard entry ID.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM admin_internal_clipboard WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() < 1) {
            echo json_encode(['success' => false, 'message' => 'Clipboard entry not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Clipboard entry deleted.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
} catch (Throwable $e) {
    error_log('Internal clipboard handler error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}
