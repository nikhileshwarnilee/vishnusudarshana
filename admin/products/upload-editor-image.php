<?php
declare(strict_types=1);

require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=UTF-8');

function vs_products_upload_fail(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit;
}

function vs_products_base_prefix(): string
{
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $adminPos = strpos($scriptName, '/admin/');
    if ($adminPos === false) {
        return '';
    }
    return rtrim(substr($scriptName, 0, $adminPos), '/');
}

if (!isset($_SESSION['user_id'])) {
    vs_products_upload_fail('Unauthorized request.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['upload'])) {
    vs_products_upload_fail('No file uploaded.');
}

$file = $_FILES['upload'];
if (!isset($file['error']) || is_array($file['error'])) {
    vs_products_upload_fail('Invalid upload payload.');
}

if ((int)$file['error'] !== UPLOAD_ERR_OK) {
    vs_products_upload_fail('Upload failed. Please try again.');
}

$maxSizeBytes = 25 * 1024 * 1024;
$size = (int)($file['size'] ?? 0);
if ($size <= 0 || $size > $maxSizeBytes) {
    vs_products_upload_fail('File size must be under 25MB.');
}

$originalName = (string)($file['name'] ?? '');
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'ogg', 'mp3', 'wav'];
if (!in_array($extension, $allowedExtensions, true)) {
    vs_products_upload_fail('Unsupported file format.');
}

$tmpPath = (string)($file['tmp_name'] ?? '');
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    vs_products_upload_fail('Invalid upload source.');
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
    vs_products_upload_fail('Invalid file MIME type.');
}

$isImage = str_starts_with($mimeType, 'image/');
$subDir = $isImage ? 'images' : 'media';
$uploadDir = __DIR__ . '/../../uploads/products/editor/' . $subDir . '/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
    vs_products_upload_fail('Failed to prepare upload directory.', 500);
}

try {
    $fileName = 'product_editor_' . bin2hex(random_bytes(8)) . '.' . $extension;
} catch (Throwable $e) {
    $fileName = 'product_editor_' . uniqid('', true) . '.' . $extension;
}

$targetPath = $uploadDir . $fileName;
if (!move_uploaded_file($tmpPath, $targetPath)) {
    vs_products_upload_fail('Failed to store uploaded file.', 500);
}

$baseUrl = vs_products_base_prefix();
$publicUrl = ($baseUrl !== '' ? $baseUrl : '') . '/uploads/products/editor/' . $subDir . '/' . rawurlencode($fileName);

echo json_encode(['url' => $publicUrl]);
exit;
