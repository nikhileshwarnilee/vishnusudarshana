<?php
session_start();
// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!empty($data['path'])) {
        $filePath = __DIR__ . '/../../' . $data['path'];
        if (file_exists($filePath)) {
            unlink($filePath);
            if (isset($_SESSION['cover_image']) && $_SESSION['cover_image'] === $data['path']) {
                unset($_SESSION['cover_image']);
            }
            echo json_encode(['success' => true]);
            exit;
        }
    }
}
echo json_encode(['success' => false]);
exit;
