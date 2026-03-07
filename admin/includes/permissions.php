<?php
/**
 * Centralized admin permission enforcement.
 *
 * This guard maps admin routes to permission rows in user_permissions
 * (menu + submenu + action) and enforces view/edit/delete access.
 */
require_once __DIR__ . '/admin-auth.php';

// PHP 7 compatibility for shared hosts that don't provide PHP 8 string helpers.
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        if ($needle === '') {
            return true;
        }
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('vs_admin_permission_map')) {
    function vs_admin_permission_map(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [
            // Dashboard
            'index.php' => ['pairs' => [['Dashboard', 'main']]],
            'staff-dashboard.php' => ['pairs' => [['Dashboard', 'main']]],
            'ajax_dashboard_activity.php' => ['pairs' => [['Dashboard', 'main']], 'action' => 'view'],

            // Reception
            'token-management/booked-tokens.php' => ['pairs' => [['Reception', 'Booked Tokens']]],
            'token-management/completed-tokens.php' => ['pairs' => [['Reception', 'Booked Tokens']]],
            'token-management/book-token-offline.php' => ['pairs' => [['Reception', 'Book Token']]],
            'token-management/book-token-availability.php' => ['pairs' => [['Reception', 'Book Token']], 'action' => 'view'],
            'token-management/save-book-token.php' => ['pairs' => [['Reception', 'Book Token']], 'action' => 'edit'],
            'token-management/index.php' => ['pairs' => [['Reception', 'Token Management']]],
            'token-management/fetch-tokens.php' => ['pairs' => [['Reception', 'Token Management']], 'action' => 'view'],
            'token-management/save-token.php' => ['pairs' => [['Reception', 'Token Management']], 'action' => 'edit'],
            'token-management/edit-token.php' => ['pairs' => [['Reception', 'Token Management']], 'action' => 'edit'],
            'token-management/delete-token.php' => ['pairs' => [['Reception', 'Token Management']], 'action' => 'delete'],
            'token-management/delete-old-tokens.php' => ['pairs' => [['Reception', 'Token Management']], 'action' => 'delete'],
            'token-management/complete-booking.php' => ['pairs' => [['Reception', 'Booked Tokens']], 'action' => 'edit'],
            'token-management/delete-booking.php' => ['pairs' => [['Reception', 'Booked Tokens']], 'action' => 'delete'],
            'token-management/manage-booking.php' => ['pairs' => [['Reception', 'Booked Tokens']]],
            'token-management/revert-booking.php' => ['pairs' => [['Reception', 'Booked Tokens']], 'action' => 'edit'],
            'token-management/skip-booking.php' => ['pairs' => [['Reception', 'Booked Tokens']], 'action' => 'edit'],
            'token-management/send-token-start-reminder.php' => ['pairs' => [['Reception', 'Booked Tokens']], 'action' => 'edit'],

            'reception/visitors-log.php' => ['pairs' => [['Reception', 'Visitors Log']]],
            'reception/closed-visitors-log.php' => ['pairs' => [['Reception', 'Closed Visitors Log']]],
            'reception/view-visitor.php' => ['pairs' => [['Reception', 'Visitors Log'], ['Reception', 'Closed Visitors Log']], 'action' => 'view'],
            'reception/ajax_visitors_log.php' => ['pairs' => [['Reception', 'Visitors Log'], ['Reception', 'Closed Visitors Log']], 'action' => 'view'],
            'reception/ajax_get_notes.php' => ['pairs' => [['Reception', 'Visitors Log'], ['Reception', 'Closed Visitors Log']], 'action' => 'view'],
            'reception/ajax_add_note.php' => ['pairs' => [['Reception', 'Visitors Log'], ['Reception', 'Closed Visitors Log']], 'action' => 'edit'],

            // Appointments
            'services/appointments.php' => ['pairs' => [['Appointments', 'Pending Appointments']]],
            'services/accepted-appointments.php' => ['pairs' => [['Appointments', 'Accepted Appointments']]],
            'services/completed-appointments.php' => ['pairs' => [['Appointments', 'Completed Appointments']]],
            'services/failed-appointments.php' => ['pairs' => [['Appointments', 'Failed Appointments']]],
            'services/booking-slots.php' => ['pairs' => [['Appointments', 'Booking Slots']]],

            // Services
            'services/service-request-list.php' => ['pairs' => [['Services', 'Service Request List']]],
            'services/offlineservicerequest.php' => ['pairs' => [['Services', 'Offline Service Request']]],
            'services/servicepayments.php' => ['pairs' => [['Services', 'Service Payments']]],
            'services/category.php' => ['pairs' => [['Services', 'Service Categories']]],
            'services/saved-msgs.php' => ['pairs' => [['Services', 'Service Request List']]],
            'services/index.php' => ['pairs' => [['Services', 'Service Request List']]],
            'services/view.php' => ['pairs' => [
                ['Appointments', 'Pending Appointments'],
                ['Appointments', 'Accepted Appointments'],
                ['Appointments', 'Completed Appointments'],
                ['Appointments', 'Failed Appointments'],
                ['Services', 'Service Request List'],
                ['Services', 'Offline Service Request'],
            ], 'action' => 'view'],
            'services/ajax_list.php' => ['pairs' => [['Services', 'Service Request List']], 'action' => 'view'],
            'services/ajax_service_pagination.php' => ['pairs' => [['Services', 'Service Request List']], 'action' => 'view'],
            'services/ajax_get_products.php' => ['pairs' => [['Services', 'Service Request List'], ['Services', 'Offline Service Request']], 'action' => 'view'],
            'services/ajax_get_notes.php' => ['pairs' => [['Services', 'Service Request List'], ['Appointments', 'Pending Appointments']], 'action' => 'view'],
            'services/ajax_add_note.php' => ['pairs' => [['Services', 'Service Request List'], ['Appointments', 'Pending Appointments']], 'action' => 'edit'],
            'services/ajax_update_payment_status.php' => ['pairs' => [['Services', 'Service Payments']], 'action' => 'edit'],
            'services/collect-service-payment.php' => ['pairs' => [['Services', 'Service Payments']], 'action' => 'edit'],

            // Products (under Services menu)
            'products/index.php' => ['pairs' => [['Services', 'Products']]],
            'products/index_new.php' => ['pairs' => [['Services', 'Products']]],
            'products/ajax_list.php' => ['pairs' => [['Services', 'Products']], 'action' => 'view'],
            'products/add.php' => ['pairs' => [['Services', 'Products']]],
            'products/edit.php' => ['pairs' => [['Services', 'Products']]],
            'products/delete.php' => ['pairs' => [['Services', 'Products']], 'action' => 'delete'],
            'products/delete_product.php' => ['pairs' => [['Services', 'Products']], 'action' => 'delete'],
            'products/update_mandatory.php' => ['pairs' => [['Services', 'Products']], 'action' => 'edit'],
            'products/update_sequence.php' => ['pairs' => [['Services', 'Products']], 'action' => 'edit'],

            // Billing
            'payments/create-invoice.php' => ['pairs' => [['Billing', 'Create Invoice']]],
            'payments/save_invoice.php' => ['pairs' => [['Billing', 'Create Invoice']], 'action' => 'edit'],
            'payments/add_customer.php' => ['pairs' => [['Billing', 'Create Invoice']], 'action' => 'edit'],
            'payments/fetch_customers.php' => ['pairs' => [['Billing', 'Create Invoice']], 'action' => 'view'],
            'payments/products.php' => ['pairs' => [['Billing', 'Create Invoice']], 'action' => 'view'],

            'payments/invoice-list.php' => ['pairs' => [['Billing', 'Invoices List']]],
            'payments/edit-invoice.php' => ['pairs' => [['Billing', 'Invoices List']], 'action' => 'edit'],
            'payments/delete-invoice.php' => ['pairs' => [['Billing', 'Invoices List']], 'action' => 'delete'],
            'payments/view-invoice.php' => ['pairs' => [['Billing', 'Invoices List']], 'action' => 'view'],
            'payments/view-customer-invoices.php' => ['pairs' => [['Billing', 'Invoices List']], 'action' => 'view'],
            'payments/fetch_customer_invoices.php' => ['pairs' => [['Billing', 'Invoices List']], 'action' => 'view'],

            'payments/dues.php' => ['pairs' => [['Billing', 'Customer Dues']]],
            'payments/get_customer_dues.php' => ['pairs' => [['Billing', 'Customer Dues']], 'action' => 'view'],
            'payments/send_due_reminder.php' => ['pairs' => [['Billing', 'Customer Dues']], 'action' => 'edit'],
            'payments/collect-payment.php' => ['pairs' => [['Billing', 'Customer Dues']], 'action' => 'edit'],

            'payments/payments.php' => ['pairs' => [['Billing', 'All Payments']]],
            'payments/view-customer-payments.php' => ['pairs' => [['Billing', 'All Payments']], 'action' => 'view'],
            'payments/fetch_customer_payments.php' => ['pairs' => [['Billing', 'All Payments']], 'action' => 'view'],

            'payments/capture-payments.php' => ['pairs' => [['Billing', 'Capture Payments']]],

            // CIF
            'cif/index.php' => ['pairs' => [['CIF', 'CIF Home']]],
            'cif/category.php' => ['pairs' => [['CIF', 'Category']]],
            'cif/clients.php' => ['pairs' => [['CIF', 'Clients']]],
            'cif/ajax_search_clients.php' => ['pairs' => [['CIF', 'Clients']], 'action' => 'view'],

            // Schedule
            'schedule/manage-schedule.php' => ['pairs' => [['Schedule', 'Manage Schedule']]],
            'schedule/setup-schedule.php' => ['pairs' => [['Schedule', 'Setup Schedule']]],
            'schedule/send-schedule.php' => ['pairs' => [['Schedule', 'Send Schedule']]],
            'schedule/ajax_schedule.php' => ['pairs' => [['Schedule', 'Manage Schedule']]],

            // CRM
            'crm/customerdatabase.php' => ['pairs' => [['CRM', 'Customer Database']]],

            // Site Management
            'website/blogs-management.php' => ['pairs' => [['Site Mgt', 'Blogs Management']]],
            'website/blog-create.php' => ['pairs' => [['Site Mgt', 'Blogs Management']]],
            'website/blog-edit.php' => ['pairs' => [['Site Mgt', 'Blogs Management']]],
            'website/upload-blog-image.php' => ['pairs' => [['Site Mgt', 'Blogs Management']], 'action' => 'edit'],
            'website/upload-editor-image.php' => ['pairs' => [['Site Mgt', 'Blogs Management']], 'action' => 'edit'],
            'website/delete-blog-image.php' => ['pairs' => [['Site Mgt', 'Blogs Management']], 'action' => 'delete'],
            'website/update-site-data.php' => ['pairs' => [['Site Mgt', 'Update Site Data']]],

            // Settings
            'settings/password.php' => ['pairs' => [['Settings', 'Change Password']]],
            'users.php' => ['pairs' => [['Settings', 'Users']]],
            'assign-permissions.php' => ['pairs' => [['Settings', 'Users']], 'action' => 'edit'],

            // Popup title manager (used from admin data-entry popups)
            'popup_titles_handler.php' => ['pairs' => [['Billing', 'Create Invoice']]],
            'popup-floating.php' => ['pairs' => [['Billing', 'Create Invoice']]],
        ];

        return $map;
    }
}

if (!function_exists('vs_admin_get_base_url')) {
    function vs_admin_get_base_url(): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $adminPos = strpos($scriptName, '/admin/');
        if ($adminPos === false) {
            return '';
        }

        return rtrim(substr($scriptName, 0, $adminPos), '/');
    }
}

if (!function_exists('vs_admin_current_route')) {
    function vs_admin_current_route(): string
    {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $adminPos = strpos($scriptName, '/admin/');
        if ($adminPos === false) {
            return ltrim(basename($scriptName), '/');
        }

        return ltrim(substr($scriptName, $adminPos + strlen('/admin/')), '/');
    }
}

if (!function_exists('vs_admin_is_json_request')) {
    function vs_admin_is_json_request(string $route = ''): bool
    {
        $route = $route ?: vs_admin_current_route();
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xhr = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $base = strtolower(basename($route));

        if (str_contains($accept, 'application/json') || $xhr === 'xmlhttprequest') {
            return true;
        }

        return (bool)preg_match('/^(ajax_|save-|edit-|delete-|collect-|send-|upload-|update_|manage-|revert-|skip-|complete-)/', $base);
    }
}

if (!function_exists('vs_admin_start_session_if_needed')) {
    function vs_admin_start_session_if_needed(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_start();
        }
    }
}

if (!function_exists('vs_admin_guess_action')) {
    function vs_admin_guess_action(string $route): string
    {
        $base = strtolower(basename($route));
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $requestAction = strtolower((string)($_POST['action'] ?? $_GET['action'] ?? ''));

        if (
            isset($_GET['delete']) ||
            isset($_POST['delete']) ||
            in_array($requestAction, ['delete', 'remove', 'purge'], true) ||
            str_starts_with($base, 'delete')
        ) {
            return 'delete';
        }

        if (
            $method !== 'GET' ||
            isset($_GET['edit']) ||
            in_array($requestAction, ['edit', 'update', 'save', 'complete', 'revert', 'skip', 'unskip', 'send', 'create', 'add'], true) ||
            preg_match('/^(save-|edit-|add_|add-|update_|update-|collect-|complete-|revert-|skip-|send-|upload-|manage-|set-)/', $base)
        ) {
            return 'edit';
        }

        return 'view';
    }
}

if (!function_exists('vs_admin_permissions_matrix')) {
    function vs_admin_clear_permissions_cache(?int $userId = null): void
    {
        if (!isset($GLOBALS['_vs_admin_permission_cache']) || !is_array($GLOBALS['_vs_admin_permission_cache'])) {
            $GLOBALS['_vs_admin_permission_cache'] = [];
            return;
        }

        if ($userId === null) {
            $GLOBALS['_vs_admin_permission_cache'] = [];
            return;
        }

        unset($GLOBALS['_vs_admin_permission_cache'][(int)$userId]);
    }

    function vs_admin_permissions_matrix(int $userId): array
    {
        if (!isset($GLOBALS['_vs_admin_permission_cache']) || !is_array($GLOBALS['_vs_admin_permission_cache'])) {
            $GLOBALS['_vs_admin_permission_cache'] = [];
        }

        if (isset($GLOBALS['_vs_admin_permission_cache'][$userId])) {
            return $GLOBALS['_vs_admin_permission_cache'][$userId];
        }

        if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
            require_once __DIR__ . '/../../config/db.php';
            if (isset($pdo) && $pdo instanceof PDO) {
                $GLOBALS['pdo'] = $pdo;
            }
        }

        $pdoRef = $GLOBALS['pdo'] ?? null;
        if (!($pdoRef instanceof PDO)) {
            return [];
        }

        $stmt = $pdoRef->prepare('SELECT menu, submenu, action FROM user_permissions WHERE user_id = ?');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $matrix = [];
        foreach ($rows as $row) {
            $menu = (string)$row['menu'];
            $submenu = (string)$row['submenu'];
            $action = strtolower((string)$row['action']);
            $matrix[$menu][$submenu][$action] = true;
        }

        $GLOBALS['_vs_admin_permission_cache'][$userId] = $matrix;
        return $GLOBALS['_vs_admin_permission_cache'][$userId];
    }
}

if (!function_exists('vs_admin_user_has_permission')) {
    function vs_admin_user_has_permission(int $userId, string $menu, string $submenu, string $action): bool
    {
        if (
            $userId === 1 &&
            function_exists('vs_admin_use_id1_fallback') &&
            vs_admin_use_id1_fallback()
        ) {
            return true;
        }

        if (
            session_status() === PHP_SESSION_ACTIVE &&
            (int)($_SESSION['user_id'] ?? 0) === $userId &&
            function_exists('vs_admin_is_super_admin') &&
            vs_admin_is_super_admin()
        ) {
            return true;
        }

        $action = strtolower($action);
        $matrix = vs_admin_permissions_matrix($userId);
        return !empty($matrix[$menu][$submenu][$action]);
    }
}

if (!function_exists('vs_admin_permission_denied')) {
    function vs_admin_permission_denied(string $message, int $statusCode = 403, string $route = ''): void
    {
        http_response_code($statusCode);
        if (vs_admin_is_json_request($route)) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Content-Type: text/html; charset=UTF-8');
        $loginUrl = vs_admin_get_base_url() . '/admin/login.php';
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Permission Denied</title></head><body style="font-family:Segoe UI,Arial,sans-serif;background:#f7f7fa;padding:24px;">';
        echo '<div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #eee;border-radius:10px;padding:20px;">';
        echo '<h2 style="margin:0 0 12px;color:#800000;">Permission Denied</h2>';
        echo '<p style="margin:0 0 16px;color:#444;">' . htmlspecialchars($message) . '</p>';
        echo '<a href="' . htmlspecialchars($loginUrl) . '" style="display:inline-block;background:#800000;color:#fff;text-decoration:none;padding:8px 12px;border-radius:6px;">Go to Login</a>';
        echo '</div></body></html>';
        exit;
    }
}

if (!function_exists('admin_enforce_mapped_permission')) {
    function vs_admin_can_access_route(int $userId, string $route, string $requestedAction = 'auto'): bool
    {
        if (
            $userId === 1 &&
            function_exists('vs_admin_use_id1_fallback') &&
            vs_admin_use_id1_fallback()
        ) {
            return true;
        }

        if (
            session_status() === PHP_SESSION_ACTIVE &&
            (int)($_SESSION['user_id'] ?? 0) === $userId &&
            function_exists('vs_admin_is_super_admin') &&
            vs_admin_is_super_admin()
        ) {
            return true;
        }

        $map = vs_admin_permission_map();
        $rule = $map[$route] ?? null;
        if (!$rule || empty($rule['pairs'])) {
            return true;
        }

        $action = $requestedAction !== 'auto'
            ? strtolower($requestedAction)
            : strtolower((string)($rule['action'] ?? 'auto'));

        if ($action === 'auto') {
            $action = vs_admin_guess_action($route);
        }

        foreach ($rule['pairs'] as $pair) {
            $menu = (string)($pair[0] ?? '');
            $submenu = (string)($pair[1] ?? '');
            if ($menu !== '' && $submenu !== '' && vs_admin_user_has_permission($userId, $menu, $submenu, $action)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('admin_enforce_mapped_permission')) {
    function admin_enforce_mapped_permission(string $requestedAction = 'auto'): bool
    {
        $route = vs_admin_current_route();
        $map = vs_admin_permission_map();
        $rule = $map[$route] ?? null;

        // Not a managed permission route; keep legacy behavior untouched.
        if (!$rule || empty($rule['pairs'])) {
            return true;
        }

        vs_admin_start_session_if_needed();

        if (!isset($_SESSION['user_id'])) {
            $message = 'Please login to continue.';
            if (vs_admin_is_json_request($route)) {
                vs_admin_permission_denied($message, 401, $route);
            }
            header('Location: ' . vs_admin_get_base_url() . '/admin/login.php');
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        if (vs_admin_is_super_admin()) {
            return true;
        }

        $action = $requestedAction !== 'auto'
            ? strtolower($requestedAction)
            : strtolower((string)($rule['action'] ?? 'auto'));

        if ($action === 'auto') {
            $action = vs_admin_guess_action($route);
        }

        if (vs_admin_can_access_route($userId, $route, $action)) {
            return true;
        }

        vs_admin_permission_denied('You do not have permission to perform this action.', 403, $route);
        return false;
    }
}
