<?php
// Secure file download handler for service files
require_once __DIR__ . '/config/db.php';

$trackingId = $_GET['tracking_id'] ?? '';
$fileName = $_GET['file'] ?? '';

function errorMsg($msg) {
    echo '<!DOCTYPE html><html><head><title>Download Error</title></head><body style="font-family:Arial,sans-serif;background:#f7f7fa;padding:32px;"><div style="max-width:400px;margin:0 auto;background:#fff1f0;color:#cf1322;padding:18px 14px;border-radius:10px;text-align:center;font-weight:600;">' . htmlspecialchars($msg) . '</div></body></html>';
    exit;
}

if (!$trackingId || !$fileName) {
    errorMsg('Invalid download request.');
}

// Fetch uploaded_files for tracking_id
$stmt = $pdo->prepare('SELECT uploaded_files FROM service_requests WHERE tracking_id = ?');
$stmt->execute([$trackingId]);
$uf = $stmt->fetchColumn();
if (!$uf) {
    errorMsg('No files found for this service.');
}
$files = json_decode($uf, true) ?: [];
$found = false;
foreach ($files as $file) {
    if ($file['file'] === $fileName) {
        $found = $file;
        break;
    }
}
if (!$found) {
    errorMsg('Requested file not found for this service.');
}
// Build file path
$baseDir = __DIR__ . '/uploads/services/' . $trackingId . '/';
$fullPath = $baseDir . $fileName;
if (!file_exists($fullPath)) {
    errorMsg('File does not exist on server.');
}
// Serve file
$mime = mime_content_type($fullPath);
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($found['name']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
