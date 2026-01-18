<?php
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
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="2" fill="#800000"/><rect x="14" y="3" width="7" height="7" rx="2" fill="#800000"/><rect x="14" y="14" width="7" height="7" rx="2" fill="#800000"/><rect x="3" y="14" width="7" height="7" rx="2" fill="#800000"/></svg>',
    ],

    'Reception' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="7" r="3" fill="#800000"/><rect x="6" y="12" width="12" height="5" rx="2" fill="#800000" fill-opacity=".15"/><rect x="4" y="17" width="16" height="2" rx="1" fill="#800000" fill-opacity=".25"/></svg>',
        'submenu' => [
            'Visitors Log' => $baseUrl . '/admin/reception/visitors-log.php',
            'Closed Visitors Log' => $baseUrl . '/admin/reception/closed-visitors-log.php',
        ]
    ],

    'Appointments' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" fill="#800000" fill-opacity=".08" stroke="#800000" stroke-width="2"/><path d="M8 2v4M16 2v4" stroke="#800000" stroke-width="2"/><path d="M7 13l3 3 7-7" stroke="#800000" stroke-width="2" fill="none"/></svg>',
        'submenu' => [
            'Pending Appointments'   => $baseUrl . '/admin/services/appointments.php',
            'Accepted Appointments'  => $baseUrl . '/admin/services/accepted-appointments.php',
            'Completed Appointments' => $baseUrl . '/admin/services/completed-appointments.php',
        ]
    ],

    'Services' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 0 1 1.4 1.4l-8 8a1 1 0 0 1-1.4-1.4l8-8zM17.7 3.3a3 3 0 0 1 4.2 4.2l-2.1 2.1-4.2-4.2 2.1-2.1zM2.3 17.7a3 3 0 0 1 4.2 0l2.1-2.1-4.2-4.2-2.1 2.1a3 3 0 0 1 0 4.2z" fill="#800000"/></svg>',
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
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2" fill="#800000" fill-opacity=".08" stroke="#800000" stroke-width="2"/><rect x="6" y="10" width="6" height="4" rx="1" fill="#800000"/><circle cx="18" cy="14" r="2" fill="#800000"/></svg>',
        'submenu' => [
            'All Payments'    => $baseUrl . '/admin/payments/payments.php',
            'Create Invoice'  => $baseUrl . '/admin/payments/create-invoice.php',
            'Invoices List'   => $baseUrl . '/admin/payments/invoice-list.php',
            'Customer Dues'   => $baseUrl . '/admin/payments/dues.php',
            'Capture Payments' => $baseUrl . '/admin/payments/capture-payments.php',
        ]
    ],


    'CIF' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2" fill="#800000" fill-opacity=".08" stroke="#800000" stroke-width="2"/><path d="M8 8h8v8H8z" fill="#fff"/><path d="M8 2v4M16 2v4" stroke="#800000" stroke-width="2"/></svg>',
        'submenu' => [
            'CIF Home'   => $baseUrl . '/admin/cif/index.php',
            'Category'   => $baseUrl . '/admin/cif/category.php',
            'Clients'    => $baseUrl . '/admin/cif/clients.php',
        ]
    ],

    'Schedule' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="2" fill="#800000" fill-opacity=".08" stroke="#800000" stroke-width="2"/><path d="M8 2v4M16 2v4" stroke="#800000" stroke-width="2"/><path d="M7 13l3 3 7-7" stroke="#800000" stroke-width="2" fill="none"/></svg>',
        'submenu' => [
            'Manage Schedule' => $baseUrl . '/admin/schedule/manage-schedule.php',
        ]
    ],

    'CRM' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><circle cx="7" cy="8" r="3" fill="#800000"/><circle cx="17" cy="8" r="3" fill="#800000"/><ellipse cx="12" cy="17" rx="9" ry="5" fill="#800000" fill-opacity=".08"/></svg>',
        'submenu' => [
            'Customer Database' => $baseUrl . '/admin/crm/customerdatabase.php',
        ]
    ],

    'Site Mgt' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2" fill="#800000" fill-opacity=".08" stroke="#800000" stroke-width="2"/><path d="M2 8h20" stroke="#800000" stroke-width="2"/><circle cx="6" cy="6" r="1" fill="#800000"/><circle cx="9" cy="6" r="1" fill="#800000"/><circle cx="12" cy="6" r="1" fill="#800000"/><rect x="6" y="12" width="5" height="2" rx="1" fill="#800000"/><rect x="6" y="15" width="12" height="2" rx="1" fill="#800000" fill-opacity=".5"/></svg>',
        'submenu' => [
            'Blogs Management' => $baseUrl . '/admin/website/blogs-management.php',
            'Update Site Data' => $baseUrl . '/admin/website/update-site-data.php',
        ]
    ],

    'Settings' => [
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" stroke="#800000" stroke-width="2" fill="#800000" fill-opacity=".2"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.09A1.65 1.65 0 0 0 11 3.09V3a2 2 0 0 1 4 0v.09c.28.11.53.28.74.5.21.21.39.46.5.74H15a1.65 1.65 0 0 0 1.51 1c.2 0 .39-.07.56-.18l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.09c.11.28.28.53.5.74.21.21.46.39.74.5V9a2 2 0 0 1 0 4h-.09c-.28.11-.53.28-.74.5-.21.21-.39.46-.5.74v.09z" stroke="#800000" stroke-width="2" fill="none"/></svg>',
        'submenu' => [
            // 'Profile' removed
            'Change Password' => $baseUrl . '/admin/settings/password.php',
            'Users'           => $baseUrl . '/admin/users.php',
        ]
    ],

    // Reception menu is defined above with both submenus

    'Logout' => [
        'url'  => $baseUrl . '/admin/logout.php',
        'icon' => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke="#800000" stroke-width="2" fill="#800000" fill-opacity=".08"/><path d="M12 4v8" stroke="#800000" stroke-width="2" stroke-linecap="round"/><path d="M7 12a5 5 0 1 0 10 0" stroke="#800000" stroke-width="2" fill="none"/></svg>',
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
     ADMIN TOP MENU BAR
     ======================= -->
<nav class="admin-top-menu">
    <div class="admin-top-menu-inner">

        

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
     MENU CSS
     ======================= -->
<style>
body {
    padding-top: 60px;
    margin: 0;
}

.admin-top-menu {
    position: fixed;
    top: 0; left: 0; right: 0;
    background: #fffbe7;
    border-bottom: 2px solid #f3caca;
        background: #f8f6f2;
        border-bottom: 2px solid #e0bebe;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    z-index: 9999;
}

.admin-top-menu-inner {
    max-width: 1300px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    height: 60px;
    padding: 0 0px;
}

.admin-top-menu-logo {
    font-size: 1.25em;
    font-weight: 700;
    color: #800000;
    margin-right: 16px;
}

.admin-top-menu-list {
    list-style: none;
    display: flex;
    gap: 4px;
    margin: 0;
    padding: 0;
    flex: 1;
}

.admin-top-menu-item {
    position: relative;
}

.admin-top-menu-link {
    display: flex;
    align-items: center;
    height: 60px;
    padding: 0 10px;
    text-decoration: none;
    color: #800000;
    font-weight: 600;
}

.admin-top-menu-item.active > .admin-top-menu-link,
.admin-top-menu-link:hover {
    background: #f9eaea;
    color: #b30000;
    background: #f3e6e6;
    color: #a00000;
    font-weight: 700;
}

.admin-top-menu-item.has-sub:hover .admin-top-menu-dropdown {
    display: block;
}

.admin-top-menu-dropdown {
    display: none;
    position: absolute;
    top: 60px;
    left: 0;
    background: #fff;
    min-width: 230px;
    border: 1px solid #f3caca;
        border: 1px solid #e0bebe;
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
}

.admin-top-menu-dropdown li a {
    display: block;
    padding: 12px 16px;
    color: #800000;
    text-decoration: none;
}

.admin-top-menu-dropdown li.active a,
.admin-top-menu-dropdown li a:hover {
    background: #f9eaea;
    color: #b30000;
    font-weight: 600;
    background: #f3e6e6;
    color: #a00000;
    font-weight: 700;
}

.admin-top-menu-mobile-toggle {
    display: none;
    font-size: 26px;
    cursor: pointer;
    color: #800000;
}

/* MOBILE */
@media (max-width: 900px) {
    .admin-top-menu-inner {
        flex-wrap: wrap;
        height: auto;
    }

    .admin-top-menu-list {
        display: none;
        flex-direction: column;
        width: 100%;
        background: #fffbe7;
            background: #f8f6f2;
    }

    .admin-top-menu-list.show {
        display: flex;
    }

    .admin-top-menu-item.has-sub .admin-top-menu-dropdown {
        position: static;
        box-shadow: none;
        border: none;
    }

    .admin-top-menu-mobile-toggle {
        display: block;
    }
}
</style>

<!-- =======================
     MENU JS
     ======================= -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    var toggle = document.getElementById('adminTopMenuToggle');
    var menu   = document.getElementById('adminTopMenuList');

    if (toggle) {
        toggle.addEventListener('click', function () {
            menu.classList.toggle('show');
        });
    }

    // Mobile submenu toggle
    document.querySelectorAll('.admin-top-menu-item.has-sub > .admin-top-menu-link')
        .forEach(function (link) {
            link.addEventListener('click', function (e) {
                if (window.innerWidth <= 900) {
                    e.preventDefault();
                    var dropdown = this.nextElementSibling;
                    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
                }
            });
        });
});
</script>
