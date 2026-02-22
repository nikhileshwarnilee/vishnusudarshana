<?php
session_start();
// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!empty($data['path'])) {
        $rawPath = str_replace('\\', '/', trim((string)$data['path']));
        $cleanPath = ltrim($rawPath, '/');
        $fileName = basename($cleanPath);

        $candidates = [];
        if (str_starts_with($cleanPath, 'uploads/blogs/')) {
            $candidates[] = __DIR__ . '/../../' . $cleanPath;
        }
        $candidates[] = __DIR__ . '/../../uploads/blogs/' . $fileName;

        foreach ($candidates as $filePath) {
            if (file_exists($filePath) && is_file($filePath)) {
                unlink($filePath);
                if (isset($_SESSION['cover_image']) && $_SESSION['cover_image'] === $data['path']) {
                    unset($_SESSION['cover_image']);
                }
                echo json_encode(['success' => true]);
                exit;
            }
        }
    }
}
echo json_encode(['success' => false]);
exit;
