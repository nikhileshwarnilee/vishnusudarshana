<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../helpers/blog-media.php';

function failUpload(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    failUpload('Unauthorized request.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['upload'])) {
    failUpload('No file uploaded.');
}

$file = $_FILES['upload'];
if (!isset($file['error']) || is_array($file['error'])) {
    failUpload('Invalid upload payload.');
}

if ((int)$file['error'] !== UPLOAD_ERR_OK) {
    failUpload('Upload failed. Please try again.');
}

$maxSizeBytes = 25 * 1024 * 1024; // 25MB (supports media uploads)
$size = (int)($file['size'] ?? 0);
if ($size <= 0 || $size > $maxSizeBytes) {
    failUpload('File size must be under 25MB.');
}

$originalName = (string)($file['name'] ?? '');
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'ogg', 'mp3', 'wav'];
if (!in_array($extension, $allowedExtensions, true)) {
    failUpload('Unsupported file format.');
}

$tmpPath = (string)($file['tmp_name'] ?? '');
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    failUpload('Invalid upload source.');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = $finfo ? (string)finfo_file($finfo, $tmpPath) : '';
if ($finfo) {
    finfo_close($finfo);
}
$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'video/mp4',
    'video/webm',
    'video/ogg',
    'audio/mpeg',
    'audio/wav',
    'audio/x-wav',
    'audio/ogg',
];
if (!in_array($mimeType, $allowedMimeTypes, true)) {
    failUpload('Invalid file MIME type.');
}

$isImage = str_starts_with($mimeType, 'image/');
$subDir = $isImage ? 'images' : 'media';
$uploadDir = __DIR__ . '/../../uploads/blogs/editor/' . $subDir . '/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
    failUpload('Failed to prepare upload directory.', 500);
}

try {
    $fileName = 'blog_editor_' . bin2hex(random_bytes(8)) . '.' . $extension;
} catch (Throwable $e) {
    $fileName = 'blog_editor_' . uniqid('', true) . '.' . $extension;
}

$targetPath = $uploadDir . $fileName;
if (!move_uploaded_file($tmpPath, $targetPath)) {
    failUpload('Failed to store uploaded file.', 500);
}

$baseUrl = vs_blog_base_prefix();

$publicUrl = ($baseUrl !== '' ? $baseUrl : '') . '/uploads/blogs/editor/' . $subDir . '/' . rawurlencode($fileName);
echo json_encode(['url' => $publicUrl]);
exit;
