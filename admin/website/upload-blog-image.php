<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['cover_image_file'])) {
    $imgTmp = $_FILES['cover_image_file']['tmp_name'];
    $imgName = basename($_FILES['cover_image_file']['name']);
    $imgExt = strtolower(pathinfo($imgName, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (in_array($imgExt, $allowed)) {
        $newName = uniqid('blog_', true) . '.' . $imgExt;
        $uploadDir = __DIR__ . '/../../uploads/blogs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $uploadPath = $uploadDir . $newName;
        if (move_uploaded_file($imgTmp, $uploadPath)) {
            $cover_image = 'uploads/blogs/' . $newName;
            $_SESSION['cover_image'] = $cover_image;
            echo json_encode(['success' => true, 'path' => $cover_image]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => 'Invalid image or upload failed.']);
    exit;
}
echo json_encode(['success' => false, 'error' => 'No image uploaded.']);
exit;
