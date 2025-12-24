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

$menu = [
    'Dashboard' => [
        'url'  => $baseUrl . '/admin/index.php',
        'icon' => '🏠',
    ],

    'Appointments' => [
        'icon' => '📅',
        'submenu' => [
            'Pending Appointments'   => $baseUrl . '/admin/services/appointments.php',
            'Accepted Appointments'  => $baseUrl . '/admin/services/accepted-appointments.php',
            'Completed Appointments' => $baseUrl . '/admin/services/completed-appointments.php',
        ]
    ],

    'Services' => [
        'icon' => '🛠️',
        'submenu' => [
            'Service Requests' => $baseUrl . '/admin/services/index.php',
            'Add Product'      => $baseUrl . '/admin/products/add.php',
            'Categories'       => $baseUrl . '/admin/products/categories.php',
        ]
    ],

    // 'Reels' menu removed

    'Payments' => [
        'icon' => '💳',
        'submenu' => [
            'All Payments'    => $baseUrl . '/admin/payments/payments.php',
            // 'Failed Payments' removed
        ]
    ],


    'CIF' => [
        'icon' => '📄',
        'submenu' => [
            'CIF Home'   => $baseUrl . '/admin/cif/index.php',
            'Category'   => $baseUrl . '/admin/cif/category.php',
            'Clients'    => $baseUrl . '/admin/cif/clients.php',
        ]
    ],

    'Settings' => [
        'icon' => '⚙️',
        'submenu' => [
            // 'Profile' removed
            'Change Password' => $baseUrl . '/admin/settings/password.php',
            'Users'           => $baseUrl . '/admin/users.php',
        ]
    ],

    'Logout' => [
        'url'  => $baseUrl . '/admin/logout.php',
        'icon' => '🚪',
    ],
];

// Active state helper
function isActivePage($url, $currentPage) {
    return basename($url) === $currentPage;
}
?>

<!-- =======================
     ADMIN TOP MENU BAR
     ======================= -->
<nav class="admin-top-menu">
    <div class="admin-top-menu-inner">

        <!-- Logo -->
        <div class="admin-top-menu-logo">
            Vishnusudarshana Admin
        </div>

        <!-- Menu -->
        <ul class="admin-top-menu-list" id="adminTopMenuList">
            <?php foreach ($menu as $label => $item): ?>
                <?php
                $hasSubmenu = isset($item['submenu']);
                $isActive = false;

                if ($hasSubmenu) {
                    foreach ($item['submenu'] as $subUrl) {
                        if (isActivePage($subUrl, $currentPage)) {
                            $isActive = true;
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
                            <?= htmlspecialchars($label) ?>
                            <span class="dropdown-arrow">▼</span>
                        </a>

                        <ul class="admin-top-menu-dropdown">
                            <?php foreach ($item['submenu'] as $subLabel => $subUrl): ?>
                                <li class="<?= isActivePage($subUrl, $currentPage) ? 'active' : '' ?>">
                                    <a href="<?= htmlspecialchars($subUrl) ?>">
                                        <?= htmlspecialchars($subLabel) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                    <?php else: ?>
                        <a href="<?= htmlspecialchars($item['url']) ?>" class="admin-top-menu-link">
                            <span class="icon"><?= $item['icon'] ?></span>
                            <?= htmlspecialchars($label) ?>
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
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    z-index: 9999;
}

.admin-top-menu-inner {
    max-width: 1300px;
    margin: auto;
    display: flex;
    align-items: center;
    height: 60px;
    padding: 0 20px;
}

.admin-top-menu-logo {
    font-size: 1.25em;
    font-weight: 700;
    color: #800000;
    margin-right: 30px;
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
    padding: 0 16px;
    text-decoration: none;
    color: #800000;
    font-weight: 600;
}

.admin-top-menu-item.active > .admin-top-menu-link,
.admin-top-menu-link:hover {
    background: #f9eaea;
    color: #b30000;
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
