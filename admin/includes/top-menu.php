<?php
// Handle AJAX for letterpad_titles (fetch/add)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'fetch_titles') {
    require_once __DIR__ . '/../../config/db.php';
    $stmt = $pdo->query('SELECT title FROM letterpad_titles ORDER BY id ASC');
    $dbTitles = array_column($stmt->fetchAll(), 'title');
    echo json_encode(['success'=>true, 'titles'=>$dbTitles]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'add_title') {
    require_once __DIR__ . '/../../config/db.php';
    $title = trim($_POST['title'] ?? '');
    if (!$title) {
        echo json_encode(['success'=>false, 'msg'=>'Title required']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM letterpad_titles WHERE title = ?');
    $stmt->execute([$title]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success'=>false, 'msg'=>'Title already exists']);
        exit;
    }
    $pdo->prepare('INSERT INTO letterpad_titles (title) VALUES (?)')->execute([$title]);
    echo json_encode(['success'=>true, 'msg'=>'Title added']);
    exit;
}
/**
 * admin/includes/top-menu.php
 * FULL admin top navigation bar
 * Works correctly with subfolders like /1/
 * No hardcoded paths
 */

/* --------------------------------------------------
   BASE URL DETECTION
   -------------------------------------------------- */

// Example SCRIPT_NAME:
// /vishnusudarshana/1/admin/services/completed-appointments.php
$scriptName = $_SERVER['SCRIPT_NAME'];

// Detect base directory (everything before /admin/)
$baseUrl = '';
if (strpos($scriptName, '/admin/') !== false) {
    $baseUrl = substr($scriptName, 0, strpos($scriptName, '/admin/'));
}

// If empty, site is at root
$baseUrl = rtrim($baseUrl, '/');

// Current page filename
$currentPage = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

/* --------------------------------------------------
   MENU CONFIGURATION (NO HARD PATHS)
   -------------------------------------------------- */

// Reception moved after Dashboard
$menu = [
    'Dashboard' => [
        'url'  => $baseUrl . '/admin/index.php',
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z" fill="#495057"/></svg>',
    ],

    'Reception' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z" fill="#495057"/></svg>',
        'submenu' => [
            'Visitors Log' => $baseUrl . '/admin/reception/visitors-log.php',
            'Closed Visitors Log' => $baseUrl . '/admin/reception/closed-visitors-log.php',
        ]
    ],

    'Appointments' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path d="M19 4h-1V3c0-.55-.45-1-1-1s-1 .45-1 1v1H8V3c0-.55-.45-1-1-1s-1 .45-1 1v1H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z" fill="#495057"/><circle cx="12" cy="15" r="1.5" fill="#495057"/></svg>',
        'submenu' => [
            'Pending Appointments'   => $baseUrl . '/admin/services/appointments.php',
            'Accepted Appointments'  => $baseUrl . '/admin/services/accepted-appointments.php',
            'Completed Appointments' => $baseUrl . '/admin/services/completed-appointments.php',
        ]
    ],

    'Services' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z" fill="#495057"/></svg>',
        'submenu' => [
            // 'Service Requests'   => $baseUrl . '/admin/services/index.php',
            'Service Request List' => $baseUrl . '/admin/services/service-request-list.php',
            'Offline Service Request' => $baseUrl . '/admin/services/offlineservicerequest.php',
            'Service Payments' => $baseUrl . '/admin/services/servicepayments.php',
            'Products'           => $baseUrl . '/admin/products/index.php',
            'Service Categories' => $baseUrl . '/admin/services/category.php',
        ]
    ],

    // 'Reels' menu removed

    'Payments' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z" fill="#495057"/></svg>',
        'submenu' => [
            'All Payments'    => $baseUrl . '/admin/payments/payments.php',
            'Create Invoice'  => $baseUrl . '/admin/payments/create-invoice.php',
            'Invoices List'   => $baseUrl . '/admin/payments/invoice-list.php',
            'Customer Dues'   => $baseUrl . '/admin/payments/dues.php',
            'Capture Payments' => $baseUrl . '/admin/payments/capture-payments.php',
        ]
    ],


    'CIF' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM8 20H4v-4h4v4zm0-6H4v-4h4v4zm0-6H4V4h4v4zm6 12h-4v-4h4v4zm0-6h-4v-4h4v4zm0-6h-4V4h4v4zm6 12h-4v-4h4v4zm0-6h-4v-4h4v4zm0-6h-4V4h4v4z" fill="#495057"/></svg>',
        'submenu' => [
            'CIF Home'   => $baseUrl . '/admin/cif/index.php',
            'Category'   => $baseUrl . '/admin/cif/category.php',
            'Clients'    => $baseUrl . '/admin/cif/clients.php',
        ]
    ],

    'Schedule' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z" fill="#495057"/></svg>',
        'submenu' => [
            'Manage Schedule' => $baseUrl . '/admin/schedule/manage-schedule.php',
        ]
    ],

    'CRM' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" fill="#495057"/></svg>',
        'submenu' => [
            'Customer Database' => $baseUrl . '/admin/crm/customerdatabase.php',
        ]
    ],

    'Site Mgt' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-5 14H4v-4h11v4zm0-5H4V9h11v4zm5 5h-4V9h4v9z" fill="#495057"/></svg>',
        'submenu' => [
            'Blogs Management' => $baseUrl . '/admin/website/blogs-management.php',
            'Update Site Data' => $baseUrl . '/admin/website/update-site-data.php',
        ]
    ],

    'Settings' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z" fill="#495057"/></svg>',
        'submenu' => [
            // 'Profile' removed
            'Change Password' => $baseUrl . '/admin/settings/password.php',
            'Users'           => $baseUrl . '/admin/users.php',
        ]
    ],

    // Reception menu is defined above with both submenus

    'Logout' => [
        'url'  => $baseUrl . '/admin/logout.php',
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z" fill="#495057"/></svg>',
    ],
];

// Ensure session and DB connection for permission filtering
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($pdo)) {
    require_once __DIR__ . '/../../config/db.php';
}

// --- Permission filtering ---
// Only filter for non-admins; admin (user_id=1) always sees all menus/submenus
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != 1) {
    $user_id = $_SESSION['user_id'];
    $perms = $pdo->prepare('SELECT menu, submenu FROM user_permissions WHERE user_id=? AND action="view"');
    $perms->execute([$user_id]);
    $allowed = [];
    foreach ($perms->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $allowed[$p['menu']][$p['submenu']] = true;
    }
    // Filter $menu
    foreach ($menu as $menuLabel => &$menuItem) {
        if ($menuLabel === 'Logout') {
            // Always show Logout
            continue;
        }
        if ($menuLabel === 'Dashboard') {
            // Dashboard is a main menu, treat as 'main' permission
            $menu[$menuLabel]['url'] = $baseUrl . '/admin/staff-dashboard.php';
            if (empty($allowed['Dashboard']['main'])) {
                unset($menu[$menuLabel]);
                continue;
            }
        } else if (isset($menuItem['submenu'])) {
            foreach ($menuItem['submenu'] as $subLabel => $subUrl) {
                if (empty($allowed[$menuLabel][$subLabel])) {
                    unset($menuItem['submenu'][$subLabel]);
                }
            }
            if (empty($menuItem['submenu'])) {
                unset($menu[$menuLabel]);
            }
        } else {
            if (empty($allowed[$menuLabel]['main'])) {
                unset($menu[$menuLabel]);
            }
        }
    }
    unset($menuItem);
}

// Active state helper (prevent redeclaration)
if (!function_exists('isActivePage')) {
    function isActivePage($url, $currentPage) {
        // Match full path after base URL for uniqueness
        $urlPath = parse_url($url, PHP_URL_PATH);
        $currPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return rtrim($urlPath, '/') === rtrim($currPath, '/');
    }
}
?>
<!-- =======================
     ADMIN MENU CSS (LOADED FIRST TO PREVENT FOUC)
     ======================= -->
<link rel="stylesheet" href="<?= $baseUrl ?>/admin/includes/responsive-tables.css">
<link rel="stylesheet" href="<?= $baseUrl ?>/admin/includes/responsive-cards.css">
<style>
html, body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
}

*, *::before, *::after {
    font-family: inherit;
}

body {
    padding-top: 64px;
    margin: 0;
}

.admin-top-menu {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-bottom: 1px solid #dee2e6;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    z-index: 9999;
    backdrop-filter: blur(10px);
}

.admin-top-menu-inner {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    height: 64px;
    padding: 0 20px;
    gap: 24px;
    width: 100%;
    box-sizing: border-box;
}

.admin-top-menu-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    padding: 8px 16px 8px 8px;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.admin-top-menu-logo:hover {
    background: rgba(128, 0, 0, 0.05);
}

.admin-logo-img {
    width: 40px;
    height: 40px;
    object-fit: contain;
    border-radius: 8px;
}

.admin-logo-text {
    font-size: 1.2em;
    font-weight: 700;
    color: #800000;
    letter-spacing: -0.3px;
}

.admin-top-menu-list {
    list-style: none;
    display: flex;
    gap: 2px;
    margin: 0;
    padding: 0;
    flex: 1;
    align-items: center;
}

.admin-top-menu-item {
    position: relative;
    display: flex;
    align-items: center;
}

.admin-top-menu-link {
    display: flex;
    align-items: center;
    gap: 6px;
    height: 40px;
    padding: 0 12px;
    text-decoration: none;
    color: #495057;
    font-weight: 500;
    font-size: 14px;
    border-radius: 8px;
    transition: all 0.2s ease;
    white-space: nowrap;
    position: relative;
}

.admin-top-menu-link .icon {
    display: flex;
    align-items: center;
    opacity: 0.85;
    transition: opacity 0.2s ease;
}

.admin-top-menu-link .dropdown-arrow {
    font-size: 10px;
    margin-left: 2px;
    opacity: 0.6;
    transition: transform 0.2s ease;
}

.admin-top-menu-item:hover > .admin-top-menu-link .dropdown-arrow {
    transform: translateY(2px);
}

.admin-top-menu-link:hover {
    background: #f8f9fa;
    color: #800000;
}

.admin-top-menu-link:hover .icon {
    opacity: 1;
}

.admin-top-menu-item.active > .admin-top-menu-link {
    background: linear-gradient(135deg, #800000 0%, #a00000 100%);
    color: #ffffff;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(128, 0, 0, 0.25);
}

.admin-top-menu-item.active > .admin-top-menu-link .icon svg {
    fill: #ffffff !important;
    stroke: #ffffff !important;
}

.admin-top-menu-item.active > .admin-top-menu-link .icon svg path,
.admin-top-menu-item.active > .admin-top-menu-link .icon svg rect,
.admin-top-menu-item.active > .admin-top-menu-link .icon svg circle,
.admin-top-menu-item.active > .admin-top-menu-link .icon svg ellipse {
    fill: #ffffff;
    stroke: #ffffff;
}

.admin-top-menu-item.has-sub:hover .admin-top-menu-dropdown {
    display: block;
    animation: slideDown 0.2s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.admin-top-menu-dropdown {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background: #ffffff;
    min-width: 260px;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1), 0 0 0 1px rgba(0, 0, 0, 0.02);
    overflow: hidden;
    z-index: 10000;
    padding: 6px 0;
    margin-top: 0;
    list-style: none;
}

.admin-top-menu-dropdown li {
    list-style: none;
    margin: 2px 0;
}

.admin-top-menu-dropdown li:last-child {
    margin-bottom: 0;
}

.admin-top-menu-dropdown li a {
    display: block;
    padding: 10px 16px;
    color: #333;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.15s ease;
    position: relative;
    border-left: 3px solid transparent;
}

.admin-top-menu-dropdown li a::before {
    display: none;
}

.admin-top-menu-dropdown li a:hover {
    background: #f5f5f5;
    color: #800000;
    border-left-color: #800000;
    padding-left: 18px;
}

.admin-top-menu-dropdown li.active a {
    background: linear-gradient(90deg, #fff0f0 0%, #ffffff 100%);
    color: #800000;
    font-weight: 600;
    border-left-color: #800000;
}

.admin-top-menu-mobile-toggle {
    display: none;
    font-size: 28px;
    cursor: pointer;
    color: #800000;
    padding: 8px;
    border-radius: 8px;
    transition: background 0.2s ease;
    margin-left: auto;
}

.admin-top-menu-mobile-toggle:hover {
    background: #f8f9fa;
}

/* Responsive styles */
@media (max-width: 1400px) {
    .admin-top-menu-inner {
        gap: 16px;
        padding: 0 16px;
    }
    .admin-top-menu-logo {
        padding: 6px 12px;
    }
    .admin-logo-img {
        width: 36px;
        height: 36px;
    }
    .admin-logo-text {
        font-size: 1.1em;
    }
    .admin-top-menu-link {
        height: 38px;
        padding: 0 10px;
        font-size: 13px;
        gap: 5px;
    }
    .admin-top-menu-dropdown {
        min-width: 220px;
    }
    body {
        padding-top: 60px;
    }
    .admin-top-menu {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        width: 100%;
        height: 60px;
        background: #ffffff;
        border-bottom: 2px solid #dee2e6;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    .admin-top-menu-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 60px;
        padding: 0 12px;
        max-width: 100%;
        gap: 12px;
        flex-wrap: nowrap;
    }
    .admin-top-menu-logo {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0;
        height: 60px;
        flex: 0 0 auto;
        border-radius: 0;
    }
    .admin-top-menu-logo:hover {
        background: transparent;
    }
    .admin-logo-img {
        width: 36px;
        height: 36px;
        border-radius: 6px;
    }
    .admin-logo-text {
        font-size: 1.1em;
        font-weight: 700;
        color: #800000;
    }
    .admin-top-menu-list {
        display: none;
        position: fixed;
        top: 60px;
        left: 0;
        right: 0;
        width: 100%;
        height: calc(100vh - 60px);
        background: #ffffff;
        margin: 0;
        padding: 0;
        list-style: none;
        overflow-y: auto;
        z-index: 9998;
        flex-direction: column;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    .admin-top-menu-list.show {
        display: block;
    }
    .admin-top-menu-item {
        display: block;
        width: 100%;
        border-bottom: 1px solid #e9ecef;
        position: relative;
    }
    .admin-top-menu-item:last-child {
        border-bottom: none;
    }
    .admin-top-menu-link {
        display: flex;
        align-items: center;
        width: 100%;
        height: 52px;
        padding: 0 20px;
        gap: 12px;
        background: #ffffff;
        color: #333333;
        font-size: 15px;
        font-weight: 500;
        border-radius: 0;
        border-left: 4px solid transparent;
        transition: all 0.25s ease;
        cursor: pointer;
    }
    .admin-top-menu-link .icon {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        opacity: 0.85;
    }
    .admin-top-menu-link .icon svg {
        width: 22px;
        height: 22px;
    }
    .admin-top-menu-link .dropdown-arrow {
        margin-left: auto;
        font-size: 11px;
        transform: rotate(0deg);
        transition: transform 0.25s ease;
        opacity: 0.6;
    }
    .admin-top-menu-link:active,
    .admin-top-menu-link:hover {
        background: #f8f9fa;
        border-left-color: #800000;
        color: #800000;
    }
    .admin-top-menu-link:hover .icon {
        opacity: 1;
    }
    .admin-top-menu-item.active > .admin-top-menu-link {
        background: #fff5f5;
        border-left-color: #800000;
        color: #800000;
        font-weight: 600;
    }
    .admin-top-menu-item.active > .admin-top-menu-link .icon svg path {
        fill: #800000;
    }
    .admin-top-menu-item.has-sub.expanded > .admin-top-menu-link .dropdown-arrow {
        transform: rotate(180deg);
    }
    .admin-top-menu-item.has-sub:hover .admin-top-menu-dropdown {
        display: none;
    }
    .admin-top-menu-item.has-sub .admin-top-menu-dropdown {
        position: static;
        display: none;
        width: 100%;
        background: #f5f5f5;
        border: none;
        border-radius: 0;
        box-shadow: none;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .admin-top-menu-item.has-sub.expanded .admin-top-menu-dropdown {
        display: block;
    }
    .admin-top-menu-dropdown li {
        display: block;
        margin: 0;
        border-bottom: 1px solid #e0e0e0;
        list-style: none;
    }
    .admin-top-menu-dropdown li:last-child {
        border-bottom: none;
    }
    .admin-top-menu-dropdown li a {
        display: block;
        padding: 14px 20px 14px 56px;
        background: #f5f5f5;
        color: #555555;
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        border-left: 4px solid transparent;
        transition: all 0.2s ease;
    }
    .admin-top-menu-dropdown li a:active,
    .admin-top-menu-dropdown li a:hover {
        background: #ececec;
        color: #800000;
        border-left-color: #800000;
        padding-left: 60px;
    }
    .admin-top-menu-dropdown li.active a {
        background: #e8e8e8;
        color: #800000;
        font-weight: 600;
        border-left-color: #800000;
    }
    .admin-top-menu-mobile-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        font-size: 26px;
        color: #800000;
        background: transparent;
        border: 2px solid #e9ecef;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.25s ease;
        flex: 0 0 auto;
        margin-left: auto;
    }
    .admin-top-menu-mobile-toggle:hover {
        background: #f8f9fa;
        border-color: #800000;
    }
    .admin-top-menu-mobile-toggle.active {
        background: #800000;
        color: #ffffff;
        border-color: #800000;
    }
}

@media (max-width: 600px) {
    body {
        padding-top: 56px;
    }
    .admin-top-menu {
        height: 56px;
    }
    .admin-top-menu-inner {
        height: 56px;
        padding: 0 10px;
    }
    .admin-top-menu-logo {
        height: 56px;
        gap: 6px;
    }
    .admin-logo-img {
        width: 32px;
        height: 32px;
    }
    .admin-logo-text {
        font-size: 1em;
    }
    .admin-top-menu-list {
        top: 56px;
        height: calc(100vh - 56px);
    }
    .admin-top-menu-link {
        height: 48px;
        padding: 0 16px;
        font-size: 14px;
    }
    .admin-top-menu-dropdown li a {
        padding: 12px 16px 12px 48px;
        font-size: 13px;
    }
    .admin-top-menu-dropdown li a:hover {
        padding-left: 52px;
    }
    .admin-top-menu-mobile-toggle {
        width: 40px;
        height: 40px;
        font-size: 24px;
    }
}

@media (max-width: 400px) {
    .admin-logo-text {
        font-size: 0.95em;
    }
    .admin-top-menu-link {
        padding: 0 12px;
        font-size: 13px;
        gap: 10px;
    }
    .admin-top-menu-link .icon svg {
        width: 20px;
        height: 20px;
    }
    .admin-top-menu-dropdown li a {
        padding: 11px 12px 11px 44px;
        font-size: 12px;
    }
}
</style>

<!-- =======================
     ADMIN TOP MENU BAR
     ======================= -->
<nav class="admin-top-menu">
    <div class="admin-top-menu-inner">

        <!-- Logo -->
        <a href="<?= $baseUrl ?>/admin/index.php" class="admin-top-menu-logo">
            <img src="<?= $baseUrl ?>/assets/images/logo/logo-iconpwa192.png" alt="Logo" class="admin-logo-img">
            <span class="admin-logo-text">Admin Panel</span>
        </a>

        <!-- Menu -->
        <ul class="admin-top-menu-list" id="adminTopMenuList">
            <?php foreach ($menu as $label => $item): ?>
                <?php
                $hasSubmenu = isset($item['submenu']);
                $isActive = false;
                $activeSub = null;
                if ($hasSubmenu) {
                    foreach ($item['submenu'] as $subLabel => $subUrl) {
                        if (isActivePage($subUrl, $currentPage)) {
                            $isActive = true;
                            $activeSub = $subLabel;
                            break;
                        }
                    }
                } else {
                    if (isset($item['url']) && isActivePage($item['url'], $currentPage)) {
                        $isActive = true;
                    }
                }
                ?>

                <li class="admin-top-menu-item <?= $hasSubmenu ? 'has-sub' : '' ?> <?= $isActive ? 'active' : '' ?>">

                    <?php if ($hasSubmenu): ?>
                        <a href="javascript:void(0)" class="admin-top-menu-link">
                            <span class="icon"><?= $item['icon'] ?></span>
                            <?php if ($label !== 'Settings') echo htmlspecialchars($label); ?>
                            <span class="dropdown-arrow">▼</span>
                        </a>

                        <ul class="admin-top-menu-dropdown">
                            <?php foreach ($item['submenu'] as $subLabel => $subUrl): ?>
                                <li class="<?= ($activeSub === $subLabel) ? 'active' : '' ?>">
                                    <a href="<?= htmlspecialchars($subUrl) ?>">
                                        <?= htmlspecialchars($subLabel) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                    <?php else: ?>
                        <a href="<?= htmlspecialchars($item['url']) ?>" class="admin-top-menu-link">
                            <span class="icon"><?= $item['icon'] ?></span>
                            <?php if ($label !== 'Logout') echo htmlspecialchars($label); ?>
                        </a>
                    <?php endif; ?>

                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Mobile toggle -->
        <div class="admin-top-menu-mobile-toggle" id="adminTopMenuToggle">☰</div>

    </div>
</nav>

<!-- =======================
     MENU JS
     ======================= -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('adminTopMenuToggle');
    const menu = document.getElementById('adminTopMenuList');
    const body = document.body;

    // Toggle main menu on mobile
    if (toggle && menu) {
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = menu.classList.contains('show');
            
            if (isOpen) {
                closeMainMenu();
            } else {
                openMainMenu();
            }
        });
    }

    // Open main menu
    function openMainMenu() {
        menu.classList.add('show');
        toggle.classList.add('active');
        body.style.overflow = 'hidden'; // Prevent body scroll when menu is open
    }

    // Close main menu
    function closeMainMenu() {
        menu.classList.remove('show');
        toggle.classList.remove('active');
        body.style.overflow = '';
        // Close all expanded submenus
        document.querySelectorAll('.admin-top-menu-item.has-sub.expanded').forEach(function(item) {
            item.classList.remove('expanded');
        });
    }

    // Handle submenu toggle on mobile
    document.querySelectorAll('.admin-top-menu-item.has-sub > .admin-top-menu-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            if (window.innerWidth <= 1400) {
                e.preventDefault();
                e.stopPropagation();
                
                const parentItem = this.parentElement;
                const isExpanded = parentItem.classList.contains('expanded');
                
                // Close all other expanded menus
                document.querySelectorAll('.admin-top-menu-item.has-sub.expanded').forEach(function(item) {
                    if (item !== parentItem) {
                        item.classList.remove('expanded');
                    }
                });
                
                // Toggle current submenu
                if (isExpanded) {
                    parentItem.classList.remove('expanded');
                } else {
                    parentItem.classList.add('expanded');
                }
            }
        });
    });

    // Close menu when clicking a non-submenu link
    document.querySelectorAll('.admin-top-menu-item:not(.has-sub) .admin-top-menu-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 1400) {
                closeMainMenu();
            }
        });
    });

    // Close menu when clicking a submenu item
    document.querySelectorAll('.admin-top-menu-dropdown a').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 1400) {
                closeMainMenu();
            }
        });
    });

    // Close menu when clicking outside
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1400) {
            const isClickInsideMenu = menu && menu.contains(e.target);
            const isClickOnToggle = toggle && toggle.contains(e.target);
            
            if (!isClickInsideMenu && !isClickOnToggle && menu && menu.classList.contains('show')) {
                closeMainMenu();
            }
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1200) {
            if (menu) {
                menu.classList.remove('show');
            }
            if (toggle) {
                toggle.classList.remove('active');
            }
            body.style.overflow = '';
            // Remove expanded class from all items
            document.querySelectorAll('.admin-top-menu-item.has-sub.expanded').forEach(function(item) {
                item.classList.remove('expanded');
            });
        }
    });

    // Prevent menu from being accidentally opened on desktop
    if (window.innerWidth > 1200 && menu) {
        menu.classList.remove('show');
        if (toggle) {
            toggle.classList.remove('active');
        }
    }

    // Desktop hover behavior for dropdowns
    if (window.innerWidth > 1400) {
        document.querySelectorAll('.admin-top-menu-item.has-sub').forEach(function(item) {
            let hoverTimeout;
            item.addEventListener('mouseenter', function() {
                clearTimeout(hoverTimeout);
                const dropdown = this.querySelector('.admin-top-menu-dropdown');
                if (dropdown) {
                    dropdown.style.display = 'block';
                }
            });
            item.addEventListener('mouseleave', function() {
                hoverTimeout = setTimeout(() => {
                    const dropdown = this.querySelector('.admin-top-menu-dropdown');
                    if (dropdown) {
                        dropdown.style.display = 'none';
                    }
                }, 150);
            });
        });
    }
});
</script>


<!-- GLOBAL FLOATING BUTTON -->
<style>
    .admin-floating-btn {
        position: fixed;
        right: 32px;
        bottom: 32px;
        z-index: 9999;
        background: #800000;
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 62px;
        height: 62px;
        box-shadow: 0 4px 16px #80000033;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.2s;
    }
    .admin-floating-btn:hover {
        background: #a83232;
    }
    .admin-floating-btn svg {
        width: 32px;
        height: 32px;
        display: block;
    }
</style>
<button class="admin-floating-btn" title="Quick Action">
    <!-- Writing (edit) icon SVG -->
    <svg viewBox="0 0 24 24" fill="none"><path d="M3 17.25V21h3.75l11.06-11.06-3.75-3.75L3 17.25zM20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z" fill="#fff"/></svg>
</button>

<!-- Floating Modal Window -->
<div id="adminFloatingModalBg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:10000; align-items:center; justify-content:center;">
    <div id="adminFloatingModal" style="background:#fff; border-radius:14px; box-shadow:0 4px 32px #80000033; padding:32px 28px 22px 28px; min-width:320px; max-width:95vw; width:400px; text-align:left; position:relative;">
        <button onclick="closeAdminFloatingModal()" style="position:absolute;top:12px;right:12px;background:none;border:none;font-size:1.6em;color:#800000;cursor:pointer;">&times;</button>
        <div style="font-size:1.18em;color:#800000;font-weight:700;margin-bottom:12px;">Quick Action</div>
        <div style="margin-bottom:16px;">
            <label for="adminFloatingTitle" style="font-weight:600;color:#333;">Select Title:</label><br>
            <div style="display:flex;align-items:center;gap:8px;">
                <select id="adminFloatingTitle" style="flex:1 1 0;width:100%;padding:8px 10px;border-radius:6px;border:1px solid #bbb;font-size:1em;margin-top:6px;">
                </select>
            </div>
            <div style="margin-top:16px;">
                <label for="adminFloatingRichText" style="font-weight:600;color:#333;">Message:</label><br>
                <div id="adminFloatingToolbar" style="margin-bottom:6px;display:flex;gap:6px;flex-wrap:wrap;">
                    <button type="button" title="Bold" onclick="execRichCmd('bold')" style="font-weight:bold;">B</button>
                    <button type="button" title="Italic" onclick="execRichCmd('italic')" style="font-style:italic;">I</button>
                    <button type="button" title="Underline" onclick="execRichCmd('underline')" style="text-decoration:underline;">U</button>
                    <button type="button" title="Strikethrough" onclick="execRichCmd('strikeThrough')" style="text-decoration:line-through;">S</button>
                    <button type="button" title="Ordered List" onclick="execRichCmd('insertOrderedList')">OL</button>
                    <button type="button" title="Unordered List" onclick="execRichCmd('insertUnorderedList')">UL</button>
                    <button type="button" title="Left Align" onclick="execRichCmd('justifyLeft')">&#8676;</button>
                    <button type="button" title="Center Align" onclick="execRichCmd('justifyCenter')">&#8596;</button>
                    <button type="button" title="Right Align" onclick="execRichCmd('justifyRight')">&#8677;</button>
                    <button type="button" title="Link" onclick="addRichLink()">&#128279;</button>
                    <button type="button" title="Remove Format" onclick="execRichCmd('removeFormat')">Tx</button>
                </div>
                <div id="adminFloatingRichText" contenteditable="true" style="min-height:120px;width:100%;border:1px solid #bbb;border-radius:6px;padding:10px;font-size:1em;background:#fff;outline:none;"></div>
            </div>
            <div id="addedTitlesList" style="margin-top:12px;font-size:0.98em;color:#444;"></div>
        </div>
        <div style="color:#444;">This is a floating window. You can put any content or form here.</div>
    </div>
</div>

<script>
// --- Floating Modal Logic ---
function openAdminFloatingModal() {
    document.getElementById('adminFloatingModalBg').style.display = 'flex';
    renderTitleDropdown();
    renderAddedTitlesList();
}
function closeAdminFloatingModal() {
    document.getElementById('adminFloatingModalBg').style.display = 'none';
}
document.querySelector('.admin-floating-btn').addEventListener('click', openAdminFloatingModal);
document.getElementById('adminFloatingModalBg').addEventListener('click', function(e) {
    if (e.target === this) closeAdminFloatingModal();
});

// --- Title Dropdown and Add Logic ---
function renderTitleDropdown() {
    const select = document.getElementById('adminFloatingTitle');
    select.innerHTML = '<option value="">-- Select a title --</option>';
    fetchTitles(function(titles) {
        titles.forEach(title => {
            const opt = document.createElement('option');
            opt.value = title;
            opt.textContent = title;
            select.appendChild(opt);
        });
    });
}
function renderAddedTitlesList() {
    document.getElementById('addedTitlesList').innerHTML = '';
}
function fetchTitles(cb) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.pathname, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        try {
            var resp = JSON.parse(xhr.responseText);
            if (resp.success) {
                cb(resp.titles, resp.added);
            } else {
                cb([]);
            }
        } catch { cb([]); }
    };
    xhr.send('ajax=fetch_titles');
}
document.getElementById('addTitleBtn').onclick = function() {
    document.getElementById('addTitleBox').style.display = 'block';
    document.getElementById('newTitleInput').focus();
};
document.getElementById('saveTitleBtn').onclick = function() {
    const input = document.getElementById('newTitleInput');
    let val = input.value.trim();
    if (!val) return;
    // AJAX add title
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.location.pathname, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        try {
            var resp = JSON.parse(xhr.responseText);
            if (resp.success) {
                input.value = '';
                document.getElementById('addTitleBox').style.display = 'none';
                renderTitleDropdown();
                renderAddedTitlesList();
            } else {
                alert(resp.msg || 'Failed to add title');
            }
        } catch { alert('Failed to add title'); }
    };
    xhr.send('ajax=add_title&title=' + encodeURIComponent(val));
};
document.getElementById('adminFloatingModalBg').addEventListener('click', function(e) {
    if (e.target === this) {
        document.getElementById('addTitleBox').style.display = 'none';
    }
});
function execRichCmd(cmd) {
    document.execCommand(cmd, false, null);
}
function addRichLink() {
    var url = prompt('Enter URL:');
    if (url) document.execCommand('createLink', false, url);
}
</script>

<!-- RESPONSIVE TABLES AUTO-WRAPPER SCRIPT -->
<script src="<?= $baseUrl ?>/admin/includes/responsive-tables.js"></script>
