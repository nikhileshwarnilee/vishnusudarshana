<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
require_once __DIR__ . '/includes/admin-auth.php';

date_default_timezone_set('Asia/Kolkata');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!vs_admin_is_super_admin()) {
    header('Location: staff-dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/dashboard-data.php';

$dashboardPageTitle = 'Admin Dashboard';
$dashboardHeading = 'Service Command Dashboard';
$dashboard = vs_dashboard_build($pdo, [
    'user_id' => (int)($_SESSION['user_id'] ?? 0),
    'user_name' => (string)($_SESSION['user_name'] ?? 'Admin'),
    'is_super_admin' => true,
]);

require __DIR__ . '/includes/dashboard-template.php';
