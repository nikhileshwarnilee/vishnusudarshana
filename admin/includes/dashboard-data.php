<?php

if (!function_exists('vs_dashboard_table_exists')) {
    function vs_dashboard_table_exists(PDO $pdo, string $tableName): bool
    {
        static $tables = null;

        if ($tables === null) {
            $tables = [];
            try {
                $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()");
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
                    $tables[(string)$name] = true;
                }
            } catch (Throwable $e) {
                $tables = [];
            }
        }

        return isset($tables[$tableName]);
    }
}

if (!function_exists('vs_dashboard_column_exists')) {
    function vs_dashboard_column_exists(PDO $pdo, string $tableName, string $columnName): bool
    {
        static $columns = [];

        if (!isset($columns[$tableName])) {
            $columns[$tableName] = [];
            try {
                $stmt = $pdo->prepare("
                    SELECT column_name
                    FROM information_schema.columns
                    WHERE table_schema = DATABASE()
                      AND table_name = ?
                ");
                $stmt->execute([$tableName]);
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
                    $columns[$tableName][(string)$name] = true;
                }
            } catch (Throwable $e) {
                $columns[$tableName] = [];
            }
        }

        return isset($columns[$tableName][$columnName]);
    }
}

if (!function_exists('vs_dashboard_fetch_int')) {
    function vs_dashboard_fetch_int(PDO $pdo, string $sql, array $params = []): int
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $value = $stmt->fetchColumn();
            return $value !== false ? (int)$value : 0;
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('vs_dashboard_fetch_rows')) {
    function vs_dashboard_fetch_rows(PDO $pdo, string $sql, array $params = []): array
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('vs_dashboard_get_permissions')) {
    function vs_dashboard_get_permissions(PDO $pdo, int $userId, bool $isSuperAdmin): array
    {
        if ($isSuperAdmin) {
            return ['__all' => true];
        }

        $allowed = [];
        try {
            $stmt = $pdo->prepare('SELECT menu, submenu FROM user_permissions WHERE user_id = ? AND action = "view"');
            $stmt->execute([$userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $menu = trim((string)($row['menu'] ?? ''));
                $submenu = trim((string)($row['submenu'] ?? ''));
                if ($menu !== '' && $submenu !== '') {
                    $allowed[$menu][$submenu] = true;
                }
            }
        } catch (Throwable $e) {
            $allowed = [];
        }

        return $allowed;
    }
}

if (!function_exists('vs_dashboard_can_view')) {
    function vs_dashboard_can_view(array $permissions, string $menu, string $submenu = 'main'): bool
    {
        if (!empty($permissions['__all'])) {
            return true;
        }

        return !empty($permissions[$menu][$submenu]);
    }
}

if (!function_exists('vs_dashboard_route')) {
    function vs_dashboard_route(string $baseUrl, string $route): string
    {
        return rtrim($baseUrl, '/') . '/admin/' . ltrim($route, '/');
    }
}

if (!function_exists('vs_dashboard_format_count')) {
    function vs_dashboard_format_count($value): string
    {
        return number_format((float)$value, 0, '.', ',');
    }
}

if (!function_exists('vs_dashboard_format_date')) {
    function vs_dashboard_format_date(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Date not set';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date('d M Y', $timestamp);
    }
}

if (!function_exists('vs_dashboard_format_time')) {
    function vs_dashboard_format_time(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $formats = ['H:i:s', 'H:i', 'g:i A', 'h:i A'];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $value);
            if ($dt instanceof DateTime) {
                return $dt->format('g:i A');
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date('g:i A', $timestamp);
    }
}

if (!function_exists('vs_dashboard_since_label')) {
    function vs_dashboard_since_label(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Time not available';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $mins = (int)floor($diff / 60);
            return $mins . ' min ago';
        }
        if ($diff < 86400) {
            $hours = (int)floor($diff / 3600);
            return $hours . ' hr ago';
        }
        if ($diff < 604800) {
            $days = (int)floor($diff / 86400);
            return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
        }

        return date('d M, g:i A', $timestamp);
    }
}

if (!function_exists('vs_dashboard_date_badge')) {
    function vs_dashboard_date_badge(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Upcoming';
        }

        $target = strtotime($value);
        if ($target === false) {
            return 'Upcoming';
        }

        $today = strtotime(date('Y-m-d'));
        $diffDays = (int)floor(($target - $today) / 86400);
        if ($diffDays <= 0) {
            return 'Today';
        }
        if ($diffDays === 1) {
            return 'Tomorrow';
        }

        return 'Upcoming';
    }
}

if (!function_exists('vs_dashboard_service_category_label')) {
    function vs_dashboard_service_category_label(string $slug): string
    {
        $map = [
            'appointment' => 'Appointment',
            'birth-child' => 'Birth & Child',
            'marriage-matching' => 'Marriage Matching',
            'astrology-consultation' => 'Astrology',
            'muhurat-event' => 'Muhurat & Event',
            'pooja-vastu-enquiry' => 'Pooja & Vastu',
        ];

        $slug = trim($slug);
        if ($slug === '') {
            return 'Service';
        }

        return $map[$slug] ?? ucwords(str_replace(['-', '_'], ' ', $slug));
    }
}

if (!function_exists('vs_dashboard_trim_text')) {
    function vs_dashboard_trim_text(string $value, int $maxLength = 88): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value));
        if ($value === '' || strlen($value) <= $maxLength) {
            return $value;
        }

        return rtrim(substr($value, 0, $maxLength - 1)) . '...';
    }
}

if (!function_exists('vs_dashboard_metric')) {
    function vs_dashboard_metric(string $label, $value, string $hint = ''): array
    {
        return [
            'label' => $label,
            'value' => is_numeric($value) ? vs_dashboard_format_count($value) : (string)$value,
            'hint' => $hint,
        ];
    }
}

if (!function_exists('vs_dashboard_item')) {
    function vs_dashboard_item(string $title, string $meta, string $support, string $flag, string $flagTone, string $href): array
    {
        return [
            'title' => $title,
            'meta' => $meta,
            'support' => $support,
            'flag' => $flag,
            'flag_tone' => $flagTone,
            'href' => $href,
        ];
    }
}

if (!function_exists('vs_dashboard_build')) {
    function vs_dashboard_build(PDO $pdo, array $options = []): array
    {
        date_default_timezone_set('Asia/Kolkata');

        $baseUrl = function_exists('vs_admin_get_base_url') ? vs_admin_get_base_url() : '';
        $userId = (int)($options['user_id'] ?? ($_SESSION['user_id'] ?? 0));
        $userName = trim((string)($options['user_name'] ?? ($_SESSION['user_name'] ?? 'Admin')));
        if ($userName === '') {
            $userName = 'Admin';
        }
        $isSuperAdmin = !empty($options['is_super_admin']);
        $permissions = vs_dashboard_get_permissions($pdo, $userId, $isSuperAdmin);

        $canAppointmentsPending = vs_dashboard_can_view($permissions, 'Appointments', 'Pending Appointments');
        $canAppointmentsAccepted = vs_dashboard_can_view($permissions, 'Appointments', 'Accepted Appointments');
        $canAppointmentsCompleted = vs_dashboard_can_view($permissions, 'Appointments', 'Completed Appointments');
        $canAppointmentsFailed = vs_dashboard_can_view($permissions, 'Appointments', 'Failed Appointments');
        $canServicesList = vs_dashboard_can_view($permissions, 'Services', 'Service Request List');
        $canServicesFailed = vs_dashboard_can_view($permissions, 'Services', 'Failed Service Requests');
        $canServicesPayments = vs_dashboard_can_view($permissions, 'Services', 'Service Payments');
        $canReceptionVisitors = vs_dashboard_can_view($permissions, 'Reception', 'Visitors Log');
        $canReceptionTokens = vs_dashboard_can_view($permissions, 'Reception', 'Booked Tokens');
        $canReceptionTokenSetup = vs_dashboard_can_view($permissions, 'Reception', 'Token Management');
        $canEventsDashboard = vs_dashboard_can_view($permissions, 'Events', 'Dashboard');
        $canEventsVerifications = vs_dashboard_can_view($permissions, 'Events', 'Verifications') || vs_dashboard_can_view($permissions, 'Events', 'Payment Verification');
        $canEventsPendingPayments = vs_dashboard_can_view($permissions, 'Events', 'Pending Payments');
        $canEventsWaitlist = vs_dashboard_can_view($permissions, 'Events', 'Waitlist');
        $canEventsCheckin = vs_dashboard_can_view($permissions, 'Events', 'Check-In');
        $canCrmDb = vs_dashboard_can_view($permissions, 'CRM', 'Customer Database');
        $canCifHome = vs_dashboard_can_view($permissions, 'CIF', 'CIF Home');
        $canCifClients = vs_dashboard_can_view($permissions, 'CIF', 'Clients');
        $canSchedule = vs_dashboard_can_view($permissions, 'Schedule', 'Manage Schedule');
        $canSiteBlogs = vs_dashboard_can_view($permissions, 'Site Mgt', 'Blogs Management');
        $canBillingDues = vs_dashboard_can_view($permissions, 'Billing', 'Customer Dues');

        $today = date('Y-m-d');
        $assignedDateExpr = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(form_data, '$.assigned_date')), '')";
        $assignedFromExpr = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(form_data, '$.assigned_from_time')), '')";

        $attentionCards = [];
        $focusNotes = [];
        $moduleBoards = [];
        $timeline = [];
        $quickLinks = [];
        $todayMetrics = [];

        $serviceRequestsToday = 0;
        $eventRegistrationsToday = 0;
        $visitorsToday = 0;
        $openVisitors = 0;
        $todayTokensPending = 0;
        $acceptedTodayAppointments = 0;
        $eventTodayArrivals = 0;
        $visitorItems = [];

        $addQuickLink = static function (array &$links, string $label, string $href, string $accent, int $priority): void {
            $links[] = [
                'label' => $label,
                'href' => $href,
                'accent' => $accent,
                'priority' => $priority,
            ];
        };

        $addAttentionCard = static function (array &$cards, int $count, string $label, string $detail, string $href, string $cta, string $tone, int $priority): void {
            if ($count <= 0) {
                return;
            }

            $cards[] = [
                'count' => $count,
                'label' => $label,
                'detail' => $detail,
                'href' => $href,
                'cta' => $cta,
                'tone' => $tone,
                'priority' => $priority,
            ];
        };

        $addFocusNote = static function (array &$notes, string $title, string $detail, string $href, int $priority): void {
            $notes[] = [
                'title' => $title,
                'detail' => $detail,
                'href' => $href,
                'priority' => $priority,
            ];
        };

        $addTimelineItem = static function (array &$items, string $type, string $dateKey, string $title, string $meta, string $href): void {
            $timestamp = strtotime($dateKey);
            if ($timestamp === false) {
                return;
            }

            $items[] = [
                'type' => $type,
                'date_key' => date('Y-m-d H:i:s', $timestamp),
                'badge' => vs_dashboard_date_badge(date('Y-m-d', $timestamp)),
                'date_label' => date('d M Y', $timestamp),
                'title' => $title,
                'meta' => $meta,
                'href' => $href,
            ];
        };

        if (vs_dashboard_table_exists($pdo, 'service_requests')) {
            $serviceRequestsToday = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*) FROM service_requests WHERE DATE(created_at) = CURDATE()"
            );
        }

        /* APPOINTMENTS */

        if (($canAppointmentsPending || $canAppointmentsAccepted || $canAppointmentsCompleted || $canAppointmentsFailed) && vs_dashboard_table_exists($pdo, 'service_requests')) {
            $pendingAppointments = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*) FROM service_requests
                 WHERE category_slug = 'appointment'
                   AND payment_status IN ('Paid', 'Free')
                   AND service_status IN ('Received', 'Pending')"
            );
            $acceptedTodayAppointments = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*) FROM service_requests
                 WHERE category_slug = 'appointment'
                   AND payment_status IN ('Paid', 'Free')
                   AND service_status = 'Accepted'
                   AND {$assignedDateExpr} = CURDATE()"
            );
            $acceptedNextSeven = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*) FROM service_requests
                 WHERE category_slug = 'appointment'
                   AND payment_status IN ('Paid', 'Free')
                   AND service_status = 'Accepted'
                   AND {$assignedDateExpr} BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
            );
            $overdueAcceptedAppointments = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*) FROM service_requests
                 WHERE category_slug = 'appointment'
                   AND payment_status IN ('Paid', 'Free')
                   AND service_status = 'Accepted'
                   AND {$assignedDateExpr} <> ''
                   AND {$assignedDateExpr} < CURDATE()"
            );
            $completedTodayAppointments = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*) FROM service_requests
                 WHERE category_slug = 'appointment'
                   AND payment_status IN ('Paid', 'Free')
                   AND service_status = 'Completed'
                   AND DATE(updated_at) = CURDATE()"
            );
            $appointmentRequestsToday = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*) FROM service_requests
                 WHERE category_slug = 'appointment'
                   AND payment_status IN ('Paid', 'Free')
                   AND DATE(created_at) = CURDATE()"
            );

            $failedAppointments = 0;
            if (vs_dashboard_table_exists($pdo, 'pending_payments')) {
                $failedAppointments = vs_dashboard_fetch_int(
                    $pdo,
                    "SELECT COUNT(*)
                     FROM pending_payments p
                     LEFT JOIN service_requests s ON s.razorpay_order_id = p.razorpay_order_id
                     WHERE p.category = 'appointment'
                       AND s.id IS NULL"
                );
            }

            $appointmentItems = [];
            $appointmentRows = vs_dashboard_fetch_rows(
                $pdo,
                "SELECT id, customer_name, mobile, created_at, service_status, form_data
                 FROM service_requests
                 WHERE category_slug = 'appointment'
                   AND payment_status IN ('Paid', 'Free')
                   AND (
                       service_status IN ('Received', 'Pending')
                       OR (
                           service_status = 'Accepted'
                           AND {$assignedDateExpr} >= CURDATE()
                       )
                   )
                 ORDER BY
                   CASE WHEN service_status IN ('Received', 'Pending') THEN 0 ELSE 1 END ASC,
                   CASE WHEN {$assignedDateExpr} = CURDATE() THEN 0 ELSE 1 END ASC,
                   {$assignedDateExpr} ASC,
                   {$assignedFromExpr} ASC,
                   created_at DESC
                 LIMIT 3"
            );

            foreach ($appointmentRows as $row) {
                $formData = json_decode((string)($row['form_data'] ?? ''), true);
                if (!is_array($formData)) {
                    $formData = [];
                }

                $status = trim((string)($row['service_status'] ?? 'Pending'));
                $preferredDate = trim((string)($formData['preferred_date'] ?? ''));
                $assignedDate = trim((string)($formData['assigned_date'] ?? ''));
                $assignedFrom = trim((string)($formData['assigned_from_time'] ?? ''));
                $support = trim((string)($row['mobile'] ?? ''));

                if ($status === 'Accepted' && $assignedDate !== '') {
                    $meta = vs_dashboard_format_date($assignedDate);
                    if ($assignedFrom !== '') {
                        $meta .= ' • ' . vs_dashboard_format_time($assignedFrom);
                    }
                    $meta .= ' • ' . vs_dashboard_since_label((string)($row['created_at'] ?? ''));
                    $flag = $assignedDate === $today ? 'Due Today' : 'Scheduled';
                    $flagTone = $assignedDate === $today ? 'critical' : 'sky';
                } else {
                    $meta = ($preferredDate !== '' ? ('Preferred ' . vs_dashboard_format_date($preferredDate)) : 'Awaiting date allocation');
                    $meta .= ' • ' . vs_dashboard_since_label((string)($row['created_at'] ?? ''));
                    $flag = 'Pending';
                    $flagTone = 'warm';
                }

                $appointmentItems[] = vs_dashboard_item(
                    trim((string)($row['customer_name'] ?? 'Appointment request')),
                    $meta,
                    $support,
                    $flag,
                    $flagTone,
                    vs_dashboard_route($baseUrl, 'services/view.php?id=' . (int)$row['id'])
                );
            }

            $moduleBoards[] = [
                'title' => 'Appointments',
                'description' => 'Assign dates, keep today’s consultations on time, and catch failed payment follow-ups before seekers slip.',
                'tone' => 'maroon',
                'primary_href' => vs_dashboard_route($baseUrl, $canAppointmentsPending ? 'services/appointments.php' : 'services/accepted-appointments.php'),
                'primary_label' => $canAppointmentsPending ? 'Open Appointment Queue' : 'Open Accepted Appointments',
                'metrics' => [
                    vs_dashboard_metric('Pending', $pendingAppointments, 'Awaiting acceptance'),
                    vs_dashboard_metric('Due Today', $acceptedTodayAppointments, 'Accepted for today'),
                    vs_dashboard_metric('Next 7 Days', $acceptedNextSeven, 'Planned consultations'),
                    vs_dashboard_metric('Failed Follow-up', $failedAppointments, 'Needs payment recovery'),
                ],
                'items' => $appointmentItems,
                'empty_text' => 'No appointment queues are active right now.',
                'actions' => array_values(array_filter([
                    $canAppointmentsPending ? ['label' => 'Pending Queue', 'href' => vs_dashboard_route($baseUrl, 'services/appointments.php')] : null,
                    $canAppointmentsAccepted ? ['label' => 'Accepted List', 'href' => vs_dashboard_route($baseUrl, 'services/accepted-appointments.php')] : null,
                    $canAppointmentsCompleted ? ['label' => 'Completed Today', 'href' => vs_dashboard_route($baseUrl, 'services/completed-appointments.php')] : null,
                    $canAppointmentsFailed ? ['label' => 'Failed Payments', 'href' => vs_dashboard_route($baseUrl, 'services/failed-appointments.php')] : null,
                ])),
            ];

            if ($canAppointmentsPending) {
                $addQuickLink($quickLinks, 'Pending Appointments', vs_dashboard_route($baseUrl, 'services/appointments.php'), 'maroon', $pendingAppointments > 0 ? 120 : 70);
            }
            if ($canAppointmentsAccepted) {
                $addQuickLink($quickLinks, 'Accepted Appointments', vs_dashboard_route($baseUrl, 'services/accepted-appointments.php'), 'sky', $acceptedTodayAppointments > 0 ? 112 : 66);
            }

            $addAttentionCard(
                $attentionCards,
                $overdueAcceptedAppointments,
                'Overdue Accepted Appointments',
                $overdueAcceptedAppointments . ' accepted appointment(s) are still sitting before today. Review them before new scheduling.',
                vs_dashboard_route($baseUrl, 'services/accepted-appointments.php'),
                'Review Schedule',
                'critical',
                180
            );
            $addAttentionCard(
                $attentionCards,
                $acceptedTodayAppointments,
                'Appointments Due Today',
                $acceptedTodayAppointments . ' accepted appointment(s) are already due today and should stay visible for the admin team.',
                vs_dashboard_route($baseUrl, 'services/accepted-appointments.php'),
                'Open Day Plan',
                'critical',
                165
            );
            $addAttentionCard(
                $attentionCards,
                $pendingAppointments,
                'Pending Appointments',
                $pendingAppointments . ' paid appointment request(s) still need an assigned date and time slot.',
                vs_dashboard_route($baseUrl, 'services/appointments.php'),
                'Assign Now',
                'warm',
                150
            );
            $addAttentionCard(
                $attentionCards,
                $failedAppointments,
                'Failed Appointment Payments',
                $failedAppointments . ' appointment payment record(s) need manual follow-up or cleanup.',
                vs_dashboard_route($baseUrl, 'services/failed-appointments.php'),
                'Resolve Failures',
                'rose',
                128
            );

            if ($pendingAppointments > 0) {
                $addFocusNote(
                    $focusNotes,
                    'Assign appointment windows',
                    $pendingAppointments . ' appointment request(s) are still waiting for acceptance and date allocation.',
                    vs_dashboard_route($baseUrl, 'services/appointments.php'),
                    145
                );
            }
            if ($acceptedTodayAppointments > 0) {
                $addFocusNote(
                    $focusNotes,
                    'Keep today’s consultations moving',
                    $acceptedTodayAppointments . ' accepted appointment(s) are already scheduled for ' . vs_dashboard_format_date($today) . '.',
                    vs_dashboard_route($baseUrl, 'services/accepted-appointments.php'),
                    142
                );
            }

            $todayMetrics[] = vs_dashboard_metric('Appointments Due', $acceptedTodayAppointments, 'Accepted for today');
            $todayMetrics[] = vs_dashboard_metric('New Appointment Requests', $appointmentRequestsToday, 'Created today');
            $todayMetrics[] = vs_dashboard_metric('Completed Today', $completedTodayAppointments, 'Appointments closed today');

            if ($canAppointmentsAccepted) {
                $upcomingAppointments = vs_dashboard_fetch_rows(
                    $pdo,
                    "SELECT id, customer_name, form_data
                     FROM service_requests
                     WHERE category_slug = 'appointment'
                       AND payment_status IN ('Paid', 'Free')
                       AND service_status = 'Accepted'
                       AND {$assignedDateExpr} >= CURDATE()
                     ORDER BY {$assignedDateExpr} ASC, {$assignedFromExpr} ASC, id ASC
                     LIMIT 4"
                );
                foreach ($upcomingAppointments as $row) {
                    $formData = json_decode((string)($row['form_data'] ?? ''), true);
                    if (!is_array($formData)) {
                        $formData = [];
                    }
                    $assignedDate = trim((string)($formData['assigned_date'] ?? ''));
                    $assignedFrom = trim((string)($formData['assigned_from_time'] ?? ''));
                    if ($assignedDate === '') {
                        continue;
                    }

                    $meta = 'Appointment';
                    if ($assignedFrom !== '') {
                        $meta .= ' • ' . vs_dashboard_format_time($assignedFrom);
                    }
                    $addTimelineItem(
                        $timeline,
                        'Appointment',
                        $assignedDate . ' ' . ($assignedFrom !== '' ? $assignedFrom : '09:00'),
                        trim((string)($row['customer_name'] ?? 'Appointment')),
                        $meta,
                        vs_dashboard_route($baseUrl, 'services/accepted-appointments.php')
                    );
                }
            }
        }

        /* SERVICES */

        if (($canServicesList || $canServicesFailed || $canServicesPayments) && vs_dashboard_table_exists($pdo, 'service_requests')) {
            $pendingServices = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*) FROM service_requests
                 WHERE category_slug != 'appointment'
                   AND service_status = 'Received'"
            );
            $inProgressServices = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*) FROM service_requests
                 WHERE category_slug != 'appointment'
                   AND service_status = 'In Progress'"
            );
            $completedServicesToday = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*) FROM service_requests
                 WHERE category_slug != 'appointment'
                   AND service_status = 'Completed'
                   AND DATE(updated_at) = CURDATE()"
            );
            $serviceRequestsTodayOnly = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*) FROM service_requests
                 WHERE category_slug != 'appointment'
                   AND DATE(created_at) = CURDATE()"
            );

            $failedServiceRequests = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*)
                 FROM service_requests
                 WHERE category_slug != 'appointment'
                   AND LOWER(TRIM(COALESCE(payment_status, ''))) = 'failed'"
            );
            if (vs_dashboard_table_exists($pdo, 'pending_payments')) {
                $failedServiceRequests += vs_dashboard_fetch_int(
                    $pdo,
                    "SELECT COUNT(*)
                     FROM pending_payments p
                     LEFT JOIN service_requests s ON s.razorpay_order_id = p.razorpay_order_id
                     WHERE p.category != 'appointment'
                       AND s.id IS NULL"
                );
            }

            $serviceItems = [];
            $serviceRows = vs_dashboard_fetch_rows(
                $pdo,
                "SELECT id, customer_name, mobile, category_slug, service_status, created_at
                 FROM service_requests
                 WHERE category_slug != 'appointment'
                   AND service_status IN ('Received', 'In Progress')
                 ORDER BY
                   CASE WHEN service_status = 'Received' THEN 0 ELSE 1 END ASC,
                   created_at DESC
                 LIMIT 3"
            );
            foreach ($serviceRows as $row) {
                $status = trim((string)($row['service_status'] ?? 'Received'));
                $flagTone = $status === 'Received' ? 'warm' : 'sage';
                $serviceItems[] = vs_dashboard_item(
                    trim((string)($row['customer_name'] ?? 'Service request')),
                    vs_dashboard_service_category_label((string)($row['category_slug'] ?? '')) . ' • ' . vs_dashboard_since_label((string)($row['created_at'] ?? '')),
                    trim((string)($row['mobile'] ?? '')),
                    $status,
                    $flagTone,
                    vs_dashboard_route($baseUrl, 'services/view.php?id=' . (int)$row['id'])
                );
            }

            $moduleBoards[] = [
                'title' => 'Services',
                'description' => 'Keep non-appointment requests moving from receipt to completion, with failures surfaced early for follow-up.',
                'tone' => 'saffron',
                'primary_href' => vs_dashboard_route($baseUrl, 'services/service-request-list.php'),
                'primary_label' => 'Open Service Queue',
                'metrics' => [
                    vs_dashboard_metric('Pending', $pendingServices, 'Received status'),
                    vs_dashboard_metric('In Progress', $inProgressServices, 'Currently being handled'),
                    vs_dashboard_metric('Today', $serviceRequestsTodayOnly, 'New service requests'),
                    vs_dashboard_metric('Failed Follow-up', $failedServiceRequests, 'Payment or redirect issues'),
                ],
                'items' => $serviceItems,
                'empty_text' => 'No service requests are waiting right now.',
                'actions' => array_values(array_filter([
                    $canServicesList ? ['label' => 'Service Requests', 'href' => vs_dashboard_route($baseUrl, 'services/service-request-list.php')] : null,
                    $canServicesFailed ? ['label' => 'Failed Requests', 'href' => vs_dashboard_route($baseUrl, 'services/failed-service-requests.php')] : null,
                    $canServicesPayments ? ['label' => 'Service Payments', 'href' => vs_dashboard_route($baseUrl, 'services/servicepayments.php')] : null,
                ])),
            ];

            if ($canServicesList) {
                $addQuickLink($quickLinks, 'Service Requests', vs_dashboard_route($baseUrl, 'services/service-request-list.php'), 'saffron', $pendingServices > 0 ? 116 : 68);
            }

            $addAttentionCard(
                $attentionCards,
                $pendingServices,
                'Pending Service Requests',
                $pendingServices . ' service request(s) are still in Received status and waiting for first action.',
                vs_dashboard_route($baseUrl, 'services/service-request-list.php'),
                'Start Queue',
                'warm',
                138
            );
            $addAttentionCard(
                $attentionCards,
                $failedServiceRequests,
                'Service Follow-up Failures',
                $failedServiceRequests . ' service payment or redirect issue(s) need manual recovery.',
                vs_dashboard_route($baseUrl, 'services/failed-service-requests.php'),
                'Review Failures',
                'rose',
                122
            );

            if ($pendingServices > 0) {
                $addFocusNote(
                    $focusNotes,
                    'Clear the service intake queue',
                    $pendingServices . ' service request(s) are still marked as received and have not moved into action.',
                    vs_dashboard_route($baseUrl, 'services/service-request-list.php'),
                    132
                );
            }

            $todayMetrics[] = vs_dashboard_metric('Service Requests Today', $serviceRequestsTodayOnly, 'Non-appointment requests');
            $todayMetrics[] = vs_dashboard_metric('Services Closed Today', $completedServicesToday, 'Completed today');
        }

        /* RECEPTION */

        if ($canReceptionVisitors && vs_dashboard_table_exists($pdo, 'visitor_tickets')) {
            $openVisitors = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*) FROM visitor_tickets WHERE status = 'open'"
            );
            $urgentVisitors = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*) FROM visitor_tickets WHERE status = 'open' AND priority = 'urgent'"
            );
            $visitorsToday = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*) FROM visitor_tickets WHERE DATE(in_time) = CURDATE()"
            );

            $visitorRows = vs_dashboard_fetch_rows(
                $pdo,
                "SELECT id, visitor_name, contact_number, purpose, priority, in_time
                 FROM visitor_tickets
                 WHERE status = 'open'
                 ORDER BY
                   CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END ASC,
                   in_time ASC
                 LIMIT 3"
            );
            foreach ($visitorRows as $row) {
                $priority = strtolower(trim((string)($row['priority'] ?? 'normal'))) === 'urgent' ? 'Urgent' : 'Open';
                $visitorItems[] = vs_dashboard_item(
                    trim((string)($row['visitor_name'] ?? 'Visitor')),
                    vs_dashboard_trim_text(trim((string)($row['purpose'] ?? 'Reception visitor'))) . ' • ' . vs_dashboard_since_label((string)($row['in_time'] ?? '')),
                    trim((string)($row['contact_number'] ?? '')),
                    $priority,
                    $priority === 'Urgent' ? 'critical' : 'sky',
                    vs_dashboard_route($baseUrl, 'reception/view-visitor.php?id=' . (int)$row['id'])
                );
            }

            $addQuickLink($quickLinks, 'Visitors Log', vs_dashboard_route($baseUrl, 'reception/visitors-log.php'), 'sky', ($urgentVisitors + $openVisitors) > 0 ? 125 : 64);

            $addAttentionCard(
                $attentionCards,
                $urgentVisitors,
                'Urgent Visitors Waiting',
                $urgentVisitors . ' visitor ticket(s) are marked urgent and should stay visible at the top of the desk.',
                vs_dashboard_route($baseUrl, 'reception/visitors-log.php'),
                'Open Reception',
                'critical',
                190
            );
            $addAttentionCard(
                $attentionCards,
                $openVisitors,
                'Open Reception Tickets',
                $openVisitors . ' visitor ticket(s) are still open and waiting to be received or closed.',
                vs_dashboard_route($baseUrl, 'reception/visitors-log.php'),
                'View Visitors',
                'sky',
                136
            );

            if ($urgentVisitors > 0 || $openVisitors > 0) {
                $addFocusNote(
                    $focusNotes,
                    'Start at reception',
                    ($urgentVisitors > 0 ? ($urgentVisitors . ' urgent') : 'No urgent') . ' and ' . $openVisitors . ' open visitor ticket(s) are still active.',
                    vs_dashboard_route($baseUrl, 'reception/visitors-log.php'),
                    160
                );
            }
        } else {
            $visitorItems = [];
            $urgentVisitors = 0;
        }

        if ($canReceptionTokens && vs_dashboard_table_exists($pdo, 'token_bookings')) {
            $todayTokensPending = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*)
                 FROM token_bookings
                 WHERE token_date = CURDATE()
                   AND LOWER(TRIM(COALESCE(status, ''))) != 'completed'"
            );
            $overdueTokens = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*)
                 FROM token_bookings
                 WHERE token_date < CURDATE()
                   AND LOWER(TRIM(COALESCE(status, ''))) != 'completed'"
            );
            $tokenBookingsToday = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COUNT(*) FROM token_bookings WHERE token_date = CURDATE()"
            );
            $currentServedToken = vs_dashboard_fetch_int(
                $pdo,
                "SELECT COALESCE(MAX(token_no), 0)
                 FROM token_bookings
                 WHERE token_date = CURDATE()
                   AND LOWER(TRIM(COALESCE(status, ''))) = 'completed'"
            );

            $tokenRows = vs_dashboard_fetch_rows(
                $pdo,
                "SELECT id, token_no, name, mobile, location, token_date
                 FROM token_bookings
                 WHERE LOWER(TRIM(COALESCE(status, ''))) != 'completed'
                   AND token_date >= CURDATE()
                 ORDER BY token_date ASC, token_no ASC
                 LIMIT 3"
            );

            foreach ($tokenRows as $row) {
                $visitorItems[] = vs_dashboard_item(
                    'Token ' . (int)($row['token_no'] ?? 0) . ' • ' . trim((string)($row['name'] ?? 'Booking')),
                    vs_dashboard_format_date((string)($row['token_date'] ?? '')) . ' • ' . ucwords(trim((string)($row['location'] ?? 'Desk'))),
                    trim((string)($row['mobile'] ?? '')),
                    ((string)($row['token_date'] ?? '') === $today) ? 'Today' : 'Upcoming',
                    ((string)($row['token_date'] ?? '') === $today) ? 'warm' : 'sage',
                    vs_dashboard_route($baseUrl, 'token-management/booked-tokens.php')
                );
            }

            $visitorItems = array_slice($visitorItems, 0, 3);

            $moduleBoards[] = [
                'title' => 'Reception',
                'description' => 'Watch the live front-desk load: waiting visitors, active token flow, and any carry-over queue from previous dates.',
                'tone' => 'sky',
                'primary_href' => vs_dashboard_route($baseUrl, $canReceptionVisitors ? 'reception/visitors-log.php' : 'token-management/booked-tokens.php'),
                'primary_label' => $canReceptionVisitors ? 'Open Reception Desk' : 'Open Token Queue',
                'metrics' => [
                    vs_dashboard_metric('Open Visitors', $openVisitors, 'Still active at desk'),
                    vs_dashboard_metric('Urgent Visitors', $urgentVisitors, 'High priority tickets'),
                    vs_dashboard_metric('Tokens Today', $todayTokensPending, 'Still pending today'),
                    vs_dashboard_metric('Carry-Over Tokens', $overdueTokens, 'Older incomplete bookings'),
                ],
                'items' => $visitorItems,
                'empty_text' => 'Reception is calm right now.',
                'actions' => array_values(array_filter([
                    $canReceptionVisitors ? ['label' => 'Visitors Log', 'href' => vs_dashboard_route($baseUrl, 'reception/visitors-log.php')] : null,
                    $canReceptionTokens ? ['label' => 'Booked Tokens', 'href' => vs_dashboard_route($baseUrl, 'token-management/booked-tokens.php')] : null,
                    $canReceptionTokenSetup ? ['label' => 'Token Setup', 'href' => vs_dashboard_route($baseUrl, 'token-management/index.php')] : null,
                ])),
            ];

            $addQuickLink($quickLinks, 'Booked Tokens', vs_dashboard_route($baseUrl, 'token-management/booked-tokens.php'), 'sage', ($todayTokensPending + $overdueTokens) > 0 ? 118 : 58);

            $addAttentionCard(
                $attentionCards,
                $overdueTokens,
                'Carry-Over Token Queue',
                $overdueTokens . ' token booking(s) are still incomplete from past dates and need manual review.',
                vs_dashboard_route($baseUrl, 'token-management/booked-tokens.php'),
                'Inspect Tokens',
                'critical',
                172
            );
            $addAttentionCard(
                $attentionCards,
                $todayTokensPending,
                'Today’s Token Queue',
                $todayTokensPending . ' token booking(s) are still open for today’s live flow.',
                vs_dashboard_route($baseUrl, 'token-management/booked-tokens.php'),
                'Serve Queue',
                'sage',
                134
            );

            if ($todayTokensPending > 0 || $overdueTokens > 0) {
                $addFocusNote(
                    $focusNotes,
                    'Watch the token desk',
                    $todayTokensPending . ' live token booking(s) are pending today, with ' . $overdueTokens . ' carry-over item(s).',
                    vs_dashboard_route($baseUrl, 'token-management/booked-tokens.php'),
                    134
                );
            }

            $todayMetrics[] = vs_dashboard_metric('Tokens Live Today', $todayTokensPending, $currentServedToken > 0 ? ('Currently served up to ' . $currentServedToken) : 'No completed token yet');
            $todayMetrics[] = vs_dashboard_metric('Bookings Today', $tokenBookingsToday, 'All token bookings today');

            $upcomingTokens = vs_dashboard_fetch_rows(
                $pdo,
                "SELECT token_no, name, token_date, location
                 FROM token_bookings
                 WHERE LOWER(TRIM(COALESCE(status, ''))) != 'completed'
                   AND token_date >= CURDATE()
                 ORDER BY token_date ASC, token_no ASC
                 LIMIT 4"
            );
            foreach ($upcomingTokens as $row) {
                $tokenDate = trim((string)($row['token_date'] ?? ''));
                if ($tokenDate === '') {
                    continue;
                }
                $addTimelineItem(
                    $timeline,
                    'Token',
                    $tokenDate . ' 08:00:00',
                    'Token ' . (int)($row['token_no'] ?? 0) . ' • ' . trim((string)($row['name'] ?? 'Booking')),
                    ucwords(trim((string)($row['location'] ?? 'Desk'))),
                    vs_dashboard_route($baseUrl, 'token-management/booked-tokens.php')
                );
            }
        } elseif (!empty($visitorItems)) {
            $moduleBoards[] = [
                'title' => 'Reception',
                'description' => 'Watch the live front-desk load and ensure visitors are not left waiting.',
                'tone' => 'sky',
                'primary_href' => vs_dashboard_route($baseUrl, 'reception/visitors-log.php'),
                'primary_label' => 'Open Reception Desk',
                'metrics' => [
                    vs_dashboard_metric('Open Visitors', $openVisitors, 'Still active at desk'),
                    vs_dashboard_metric('Urgent Visitors', $urgentVisitors, 'High priority tickets'),
                ],
                'items' => array_slice($visitorItems, 0, 3),
                'empty_text' => 'Reception is calm right now.',
                'actions' => [
                    ['label' => 'Visitors Log', 'href' => vs_dashboard_route($baseUrl, 'reception/visitors-log.php')],
                ],
            ];
        }

        /* EVENTS */

        if (($canEventsDashboard || $canEventsVerifications || $canEventsPendingPayments || $canEventsWaitlist || $canEventsCheckin) && (vs_dashboard_table_exists($pdo, 'events') || vs_dashboard_table_exists($pdo, 'event_registrations') || vs_dashboard_table_exists($pdo, 'event_payments'))) {
            if (is_file(__DIR__ . '/../../helpers/event_module.php')) {
                require_once __DIR__ . '/../../helpers/event_module.php';
                if (function_exists('vs_event_ensure_tables')) {
                    try {
                        vs_event_ensure_tables($pdo);
                    } catch (Throwable $e) {
                        // Keep the dashboard usable even if event table sync fails.
                    }
                }
            }

            $pendingEventVerifications = 0;
            $eventPendingCollections = 0;
            $eventWaitlistCount = 0;
            $upcomingEventsCount = 0;

            if (vs_dashboard_table_exists($pdo, 'event_payments') && vs_dashboard_table_exists($pdo, 'event_registrations')) {
                $pendingEventVerifications = vs_dashboard_fetch_int(
                    $pdo,
                    "SELECT COUNT(*)
                     FROM event_payments ep
                     INNER JOIN event_registrations r ON r.id = ep.registration_id
                     WHERE ep.payment_method IN ('Manual UPI', 'Cash')
                       AND ep.status IN ('Pending', 'Pending Verification')
                       AND r.payment_status NOT IN ('Cancelled')"
                );
            }

            if (vs_dashboard_table_exists($pdo, 'event_registrations') && vs_dashboard_table_exists($pdo, 'event_packages')) {
                $eventPendingCollections = vs_dashboard_fetch_int(
                    $pdo,
                    "SELECT COUNT(*)
                     FROM event_registrations r
                     INNER JOIN event_packages p ON p.id = r.package_id
                     WHERE p.is_paid = 1
                       AND r.payment_status IN ('Unpaid', 'Partial Paid', 'Pending')"
                );
            }

            if (vs_dashboard_table_exists($pdo, 'event_waitlist')) {
                $eventWaitlistCount = vs_dashboard_fetch_int($pdo, "SELECT COUNT(*) FROM event_waitlist");
            } elseif (vs_dashboard_table_exists($pdo, 'event_registrations')) {
                $eventWaitlistCount = vs_dashboard_fetch_int(
                    $pdo,
                    "SELECT COUNT(*)
                     FROM event_registrations
                     WHERE payment_status = 'Waitlisted'
                        OR verification_status = 'Waitlisted'"
                );
            }

            if (vs_dashboard_table_exists($pdo, 'events')) {
                $upcomingEventsCount = vs_dashboard_fetch_int(
                    $pdo,
                    "SELECT COUNT(*) FROM events WHERE status = 'Active' AND event_date >= CURDATE()"
                );
            }

            if (vs_dashboard_table_exists($pdo, 'event_registrations')) {
                $eventRegistrationsToday = vs_dashboard_fetch_int(
                    $pdo,
                    "SELECT COUNT(*) FROM event_registrations WHERE DATE(created_at) = CURDATE()"
                );
            }

            if ($canEventsCheckin && vs_dashboard_table_exists($pdo, 'event_registrations') && vs_dashboard_table_exists($pdo, 'events')) {
                $dateJoin = vs_dashboard_table_exists($pdo, 'event_dates')
                    ? "LEFT JOIN event_dates d ON d.id = r.event_date_id"
                    : '';
                $dateExpr = vs_dashboard_table_exists($pdo, 'event_dates')
                    ? "COALESCE(d.event_date, e.event_date)"
                    : "e.event_date";
                $eventTodayArrivals = vs_dashboard_fetch_int(
                    $pdo,
                    "SELECT COUNT(*)
                     FROM event_registrations r
                     INNER JOIN events e ON e.id = r.event_id
                     {$dateJoin}
                     WHERE {$dateExpr} = CURDATE()
                       AND r.payment_status = 'Paid'
                       AND COALESCE(r.checkin_status, 0) = 0"
                );
            }

            $eventItems = [];
            if ($pendingEventVerifications > 0 && vs_dashboard_table_exists($pdo, 'event_payments') && vs_dashboard_table_exists($pdo, 'event_registrations') && vs_dashboard_table_exists($pdo, 'events')) {
                $eventRows = vs_dashboard_fetch_rows(
                    $pdo,
                    "SELECT r.name, r.phone, e.title AS event_title, COALESCE(ep.updated_at, ep.created_at) AS activity_at
                     FROM event_payments ep
                     INNER JOIN event_registrations r ON r.id = ep.registration_id
                     INNER JOIN events e ON e.id = r.event_id
                     WHERE ep.payment_method IN ('Manual UPI', 'Cash')
                       AND ep.status IN ('Pending', 'Pending Verification')
                     ORDER BY COALESCE(ep.updated_at, ep.created_at) DESC, ep.id DESC
                     LIMIT 3"
                );
                foreach ($eventRows as $row) {
                    $eventItems[] = vs_dashboard_item(
                        trim((string)($row['name'] ?? 'Registration')),
                        vs_dashboard_trim_text(trim((string)($row['event_title'] ?? 'Event payment'))) . ' • ' . vs_dashboard_since_label((string)($row['activity_at'] ?? '')),
                        trim((string)($row['phone'] ?? '')),
                        'Verify',
                        'critical',
                        vs_dashboard_route($baseUrl, 'events/verifications.php')
                    );
                }
            } elseif ($eventPendingCollections > 0 && vs_dashboard_table_exists($pdo, 'event_registrations') && vs_dashboard_table_exists($pdo, 'event_packages') && vs_dashboard_table_exists($pdo, 'events')) {
                $dateJoin = vs_dashboard_table_exists($pdo, 'event_dates')
                    ? "LEFT JOIN event_dates d ON d.id = r.event_date_id"
                    : '';
                $dateExpr = vs_dashboard_table_exists($pdo, 'event_dates')
                    ? "COALESCE(d.event_date, e.event_date)"
                    : "e.event_date";
                $eventRows = vs_dashboard_fetch_rows(
                    $pdo,
                    "SELECT r.name, r.phone, r.payment_status, e.title AS event_title, {$dateExpr} AS selected_event_date
                     FROM event_registrations r
                     INNER JOIN event_packages p ON p.id = r.package_id
                     INNER JOIN events e ON e.id = r.event_id
                     {$dateJoin}
                     WHERE p.is_paid = 1
                       AND r.payment_status IN ('Unpaid', 'Partial Paid', 'Pending')
                     ORDER BY {$dateExpr} ASC, r.id DESC
                     LIMIT 3"
                );
                foreach ($eventRows as $row) {
                    $eventItems[] = vs_dashboard_item(
                        trim((string)($row['name'] ?? 'Registration')),
                        vs_dashboard_trim_text(trim((string)($row['event_title'] ?? 'Pending event payment'))) . ' • ' . vs_dashboard_format_date((string)($row['selected_event_date'] ?? '')),
                        trim((string)($row['phone'] ?? '')),
                        trim((string)($row['payment_status'] ?? 'Pending')),
                        'warm',
                        vs_dashboard_route($baseUrl, 'events/pending-payments.php')
                    );
                }
            }

            if (empty($eventItems) && $upcomingEventsCount > 0 && vs_dashboard_table_exists($pdo, 'events')) {
                $upcomingRows = vs_dashboard_fetch_rows(
                    $pdo,
                    "SELECT title, event_date, location
                     FROM events
                     WHERE status = 'Active'
                       AND event_date >= CURDATE()
                     ORDER BY event_date ASC, id ASC
                     LIMIT 3"
                );
                foreach ($upcomingRows as $row) {
                    $eventItems[] = vs_dashboard_item(
                        trim((string)($row['title'] ?? 'Upcoming event')),
                        vs_dashboard_format_date((string)($row['event_date'] ?? '')) . ' • ' . trim((string)($row['location'] ?? 'Location not set')),
                        '',
                        'Upcoming',
                        'sage',
                        vs_dashboard_route($baseUrl, 'events/dashboard.php')
                    );
                }
            }

            $moduleBoards[] = [
                'title' => 'Events',
                'description' => 'Separate manual verification, collection backlog, waitlist pressure, and today’s arrival flow instead of burying them under totals.',
                'tone' => 'rose',
                'primary_href' => vs_dashboard_route($baseUrl, $canEventsVerifications ? 'events/verifications.php' : ($canEventsPendingPayments ? 'events/pending-payments.php' : 'events/dashboard.php')),
                'primary_label' => $canEventsVerifications ? 'Open Verification Desk' : ($canEventsPendingPayments ? 'Open Payment Queue' : 'Open Events'),
                'metrics' => [
                    vs_dashboard_metric('Verify Now', $pendingEventVerifications, 'Manual payment checks'),
                    vs_dashboard_metric('Pending Collection', $eventPendingCollections, 'Unpaid or partial bookings'),
                    vs_dashboard_metric('Waitlist', $eventWaitlistCount, 'Seat review needed'),
                    vs_dashboard_metric('Today Arrival Gap', $eventTodayArrivals, 'Paid attendees not checked in'),
                ],
                'items' => $eventItems,
                'empty_text' => 'No event queues are waiting right now.',
                'actions' => array_values(array_filter([
                    $canEventsDashboard ? ['label' => 'Events Dashboard', 'href' => vs_dashboard_route($baseUrl, 'events/dashboard.php')] : null,
                    $canEventsVerifications ? ['label' => 'Verifications', 'href' => vs_dashboard_route($baseUrl, 'events/verifications.php')] : null,
                    $canEventsPendingPayments ? ['label' => 'Pending Payments', 'href' => vs_dashboard_route($baseUrl, 'events/pending-payments.php')] : null,
                    $canEventsWaitlist ? ['label' => 'Waitlist', 'href' => vs_dashboard_route($baseUrl, 'events/waitlist.php')] : null,
                    $canEventsCheckin ? ['label' => 'Check-In', 'href' => vs_dashboard_route($baseUrl, 'events/checkin.php')] : null,
                ])),
            ];

            if ($canEventsVerifications) {
                $addQuickLink($quickLinks, 'Event Verifications', vs_dashboard_route($baseUrl, 'events/verifications.php'), 'rose', $pendingEventVerifications > 0 ? 124 : 67);
            }
            if ($canEventsPendingPayments) {
                $addQuickLink($quickLinks, 'Event Pending Payments', vs_dashboard_route($baseUrl, 'events/pending-payments.php'), 'saffron', $eventPendingCollections > 0 ? 114 : 59);
            }
            if ($canEventsWaitlist) {
                $addQuickLink($quickLinks, 'Event Waitlist', vs_dashboard_route($baseUrl, 'events/waitlist.php'), 'maroon', $eventWaitlistCount > 0 ? 108 : 54);
            }

            $addAttentionCard(
                $attentionCards,
                $pendingEventVerifications,
                'Event Payment Verifications',
                $pendingEventVerifications . ' manual event payment submission(s) still need approval or rejection.',
                vs_dashboard_route($baseUrl, 'events/verifications.php'),
                'Verify Payments',
                'critical',
                168
            );
            $addAttentionCard(
                $attentionCards,
                $eventTodayArrivals,
                'Event Check-In Gap',
                $eventTodayArrivals . ' paid attendee(s) are due today and still not checked in.',
                vs_dashboard_route($baseUrl, 'events/checkin.php'),
                'Open Check-In',
                'warm',
                140
            );
            $addAttentionCard(
                $attentionCards,
                $eventPendingCollections,
                'Event Balance Collection',
                $eventPendingCollections . ' paid-package registration(s) still have an unpaid or partial balance.',
                vs_dashboard_route($baseUrl, 'events/pending-payments.php'),
                'Collect Balance',
                'saffron',
                126
            );
            $addAttentionCard(
                $attentionCards,
                $eventWaitlistCount,
                'Event Waitlist Pressure',
                $eventWaitlistCount . ' waitlist entry or waitlisted booking(s) need seat review.',
                vs_dashboard_route($baseUrl, 'events/waitlist.php'),
                'Review Waitlist',
                'rose',
                118
            );

            if ($pendingEventVerifications > 0) {
                $addFocusNote(
                    $focusNotes,
                    'Verify event submissions before follow-up',
                    $pendingEventVerifications . ' event payment submission(s) are waiting for manual approval.',
                    vs_dashboard_route($baseUrl, 'events/verifications.php'),
                    138
                );
            }
            if ($eventTodayArrivals > 0) {
                $addFocusNote(
                    $focusNotes,
                    'Watch today’s event arrivals',
                    $eventTodayArrivals . ' paid attendee(s) for today are still not checked in.',
                    vs_dashboard_route($baseUrl, 'events/checkin.php'),
                    136
                );
            }

            $todayMetrics[] = vs_dashboard_metric('Event Arrivals Pending', $eventTodayArrivals, 'Paid but not checked in');
            $todayMetrics[] = vs_dashboard_metric('Event Registrations Today', $eventRegistrationsToday, 'Registered today');

            if ($upcomingEventsCount > 0 && vs_dashboard_table_exists($pdo, 'events')) {
                $upcomingEvents = vs_dashboard_fetch_rows(
                    $pdo,
                    "SELECT id, title, event_date, location, event_type
                     FROM events
                     WHERE status = 'Active'
                       AND event_date >= CURDATE()
                     ORDER BY event_date ASC, id ASC
                     LIMIT 4"
                );
                foreach ($upcomingEvents as $row) {
                    $dateLabel = (string)($row['event_date'] ?? '');
                    if (function_exists('vs_event_get_event_date_display')) {
                        try {
                            $dateLabel = (string)vs_event_get_event_date_display(
                                $pdo,
                                (int)($row['id'] ?? 0),
                                (string)($row['event_date'] ?? ''),
                                (string)($row['event_type'] ?? 'single_day')
                            );
                        } catch (Throwable $e) {
                            $dateLabel = (string)($row['event_date'] ?? '');
                        }
                    }
                    $eventDate = trim((string)($row['event_date'] ?? ''));
                    if ($eventDate === '') {
                        continue;
                    }
                    $addTimelineItem(
                        $timeline,
                        'Event',
                        $eventDate . ' 07:00:00',
                        trim((string)($row['title'] ?? 'Upcoming event')),
                        $dateLabel . ' • ' . trim((string)($row['location'] ?? 'Location not set')),
                        vs_dashboard_route($baseUrl, 'events/dashboard.php')
                    );
                }
            }
        }

        /* CRM */

        if (($canCrmDb || $canCifHome || $canCifClients) && (vs_dashboard_table_exists($pdo, 'cif_clients') || vs_dashboard_table_exists($pdo, 'customers') || vs_dashboard_table_exists($pdo, 'service_requests'))) {
            $cifClientsCount = vs_dashboard_table_exists($pdo, 'cif_clients')
                ? vs_dashboard_fetch_int($pdo, "SELECT COUNT(*) FROM cif_clients")
                : 0;
            $customerRecordsCount = vs_dashboard_table_exists($pdo, 'customers')
                ? vs_dashboard_fetch_int($pdo, "SELECT COUNT(*) FROM customers")
                : 0;
            $cifEnquiriesWeek = vs_dashboard_table_exists($pdo, 'cif_enquiries')
                ? vs_dashboard_fetch_int(
                    $pdo,
                    "SELECT COUNT(*)
                     FROM cif_enquiries
                     WHERE enquiry_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
                )
                : 0;
            $serviceSeekersWeek = vs_dashboard_table_exists($pdo, 'service_requests')
                ? vs_dashboard_fetch_int(
                    $pdo,
                    "SELECT COUNT(*)
                     FROM service_requests
                     WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
                )
                : 0;

            $crmItems = [];
            if (vs_dashboard_table_exists($pdo, 'cif_enquiries') && vs_dashboard_table_exists($pdo, 'cif_clients')) {
                $categoryJoin = vs_dashboard_table_exists($pdo, 'cif_categories')
                    ? "LEFT JOIN cif_categories cat ON cat.id = e.category_id"
                    : '';
                $categoryExpr = vs_dashboard_table_exists($pdo, 'cif_categories')
                    ? "COALESCE(cat.name, 'Enquiry')"
                    : "'Enquiry'";
                $crmRows = vs_dashboard_fetch_rows(
                    $pdo,
                    "SELECT e.client_id, c.name, e.enquiry_date, {$categoryExpr} AS category_name
                     FROM cif_enquiries e
                     INNER JOIN cif_clients c ON c.id = e.client_id
                     {$categoryJoin}
                     ORDER BY e.enquiry_date DESC, e.id DESC
                     LIMIT 3"
                );
                foreach ($crmRows as $row) {
                    $crmItems[] = vs_dashboard_item(
                        trim((string)($row['name'] ?? 'Client')),
                        trim((string)($row['category_name'] ?? 'Enquiry')) . ' • ' . vs_dashboard_format_date((string)($row['enquiry_date'] ?? '')),
                        '',
                        'Record',
                        'sky',
                        vs_dashboard_route($baseUrl, 'cif/index.php?client_id=' . (int)($row['client_id'] ?? 0))
                    );
                }
            }

            if (empty($crmItems) && vs_dashboard_table_exists($pdo, 'service_requests')) {
                $crmRows = vs_dashboard_fetch_rows(
                    $pdo,
                    "SELECT id, customer_name, mobile, category_slug, created_at
                     FROM service_requests
                     ORDER BY created_at DESC
                     LIMIT 3"
                );
                foreach ($crmRows as $row) {
                    $crmItems[] = vs_dashboard_item(
                        trim((string)($row['customer_name'] ?? 'Recent seeker')),
                        vs_dashboard_service_category_label((string)($row['category_slug'] ?? '')) . ' • ' . vs_dashboard_since_label((string)($row['created_at'] ?? '')),
                        trim((string)($row['mobile'] ?? '')),
                        'Recent',
                        'sage',
                        vs_dashboard_route($baseUrl, 'services/view.php?id=' . (int)$row['id'])
                    );
                }
            }

            $moduleBoards[] = [
                'title' => 'CRM & Records',
                'description' => 'Use the record layer to preserve context, not just counts: who came recently, which enquiries were logged, and where follow-up can start.',
                'tone' => 'sage',
                'primary_href' => vs_dashboard_route($baseUrl, $canCifHome ? 'cif/index.php' : 'crm/customerdatabase.php'),
                'primary_label' => $canCifHome ? 'Open CIF Panel' : 'Open Customer Database',
                'metrics' => [
                    vs_dashboard_metric('CIF Clients', $cifClientsCount, 'Stored client records'),
                    vs_dashboard_metric('Enquiries 7d', $cifEnquiriesWeek, 'Recently logged in CIF'),
                    vs_dashboard_metric('Seekers 7d', $serviceSeekersWeek, 'Recent service activity'),
                    vs_dashboard_metric('Customer Records', $customerRecordsCount, 'Billing-side records'),
                ],
                'items' => $crmItems,
                'empty_text' => 'No recent CRM activity found.',
                'actions' => array_values(array_filter([
                    $canCifHome ? ['label' => 'CIF Panel', 'href' => vs_dashboard_route($baseUrl, 'cif/index.php')] : null,
                    $canCifClients ? ['label' => 'Clients', 'href' => vs_dashboard_route($baseUrl, 'cif/clients.php')] : null,
                    $canCrmDb ? ['label' => 'Customer Database', 'href' => vs_dashboard_route($baseUrl, 'crm/customerdatabase.php')] : null,
                ])),
            ];

            if ($canCifHome) {
                $addQuickLink($quickLinks, 'CIF Panel', vs_dashboard_route($baseUrl, 'cif/index.php'), 'sage', $cifEnquiriesWeek > 0 ? 96 : 52);
            }
            if ($canCrmDb) {
                $addQuickLink($quickLinks, 'Customer Database', vs_dashboard_route($baseUrl, 'crm/customerdatabase.php'), 'sky', $serviceSeekersWeek > 0 ? 92 : 50);
            }
        }

        /* OPTIONAL LINKS */

        if ($canSchedule) {
            $addQuickLink($quickLinks, 'Manage Schedule', vs_dashboard_route($baseUrl, 'schedule/manage-schedule.php'), 'sage', 34);
        }
        if ($canSiteBlogs) {
            $addQuickLink($quickLinks, 'Blogs Management', vs_dashboard_route($baseUrl, 'website/blogs-management.php'), 'maroon', 20);
        }
        if ($canBillingDues) {
            $dueReminderCount = 0;
            if (vs_dashboard_table_exists($pdo, 'customers') && vs_dashboard_table_exists($pdo, 'invoices') && vs_dashboard_table_exists($pdo, 'payments')) {
                $dueReminderCount = vs_dashboard_fetch_int(
                    $pdo,
                    "SELECT COUNT(*) FROM (
                        SELECT c.id
                        FROM customers c
                        LEFT JOIN invoices i ON i.customer_id = c.id
                        LEFT JOIN (
                            SELECT customer_id, COALESCE(SUM(paid_amount), 0) AS total_paid
                            FROM payments
                            GROUP BY customer_id
                        ) p ON p.customer_id = c.id
                        GROUP BY c.id
                        HAVING COALESCE(SUM(i.total_amount), 0) - COALESCE(MAX(p.total_paid), 0) > 0
                    ) due_customers"
                );
            }
            if ($dueReminderCount > 0) {
                $addAttentionCard(
                    $attentionCards,
                    $dueReminderCount,
                    'Due Reminder Queue',
                    $dueReminderCount . ' customer record(s) still have unpaid dues if you need to handle reminders after operational queues.',
                    vs_dashboard_route($baseUrl, 'payments/dues.php'),
                    'Open Dues',
                    'sage',
                    48
                );
            }
            $addQuickLink($quickLinks, 'Customer Dues', vs_dashboard_route($baseUrl, 'payments/dues.php'), 'saffron', $dueReminderCount > 0 ? 40 : 18);
        }

        usort($attentionCards, static function (array $a, array $b): int {
            if ($a['priority'] === $b['priority']) {
                return $b['count'] <=> $a['count'];
            }
            return $b['priority'] <=> $a['priority'];
        });
        usort($focusNotes, static function (array $a, array $b): int {
            return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
        });
        usort($quickLinks, static function (array $a, array $b): int {
            return ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0);
        });
        usort($timeline, static function (array $a, array $b): int {
            return strcmp((string)($a['date_key'] ?? ''), (string)($b['date_key'] ?? ''));
        });

        $quickLinks = array_slice($quickLinks, 0, 8);
        $focusNotes = array_slice($focusNotes, 0, 4);
        $timeline = array_slice($timeline, 0, 8);

        if (empty($attentionCards)) {
            $attentionCards[] = [
                'count' => 0,
                'label' => 'Urgent Queues Are Clear',
                'detail' => 'Visible operational queues are calm right now. Use the upcoming commitments panel to stay ahead of the next service window.',
                'href' => !empty($quickLinks[0]['href']) ? $quickLinks[0]['href'] : vs_dashboard_route($baseUrl, 'index.php'),
                'cta' => !empty($quickLinks[0]['label']) ? ('Open ' . $quickLinks[0]['label']) : 'Open Dashboard',
                'tone' => 'calm',
                'priority' => 0,
            ];
        }

        if (empty($focusNotes)) {
            $focusNotes[] = [
                'title' => 'Queues are stable',
                'detail' => 'Nothing urgent is currently visible from your accessible modules. Review the next commitments and keep the dashboard as the handover view.',
                'href' => !empty($quickLinks[0]['href']) ? $quickLinks[0]['href'] : vs_dashboard_route($baseUrl, 'index.php'),
                'priority' => 0,
            ];
        }

        $heroPendingActions = 0;
        foreach ($attentionCards as $card) {
            $heroPendingActions += max(0, (int)($card['count'] ?? 0));
        }
        $heroOpenQueues = 0;
        foreach ($attentionCards as $card) {
            if ((int)($card['count'] ?? 0) > 0) {
                $heroOpenQueues++;
            }
        }
        $heroDueToday = $acceptedTodayAppointments + $openVisitors + $todayTokensPending + $eventTodayArrivals;
        $heroRecentSeekers = $serviceRequestsToday + $eventRegistrationsToday + $visitorsToday;

        if (count($todayMetrics) < 4) {
            $todayMetrics[] = vs_dashboard_metric('Open Queues', $heroOpenQueues, 'Action groups visible now');
        }
        $todayMetrics = array_slice($todayMetrics, 0, 5);

        $visibleModuleCount = count($moduleBoards);
        $introText = $heroOpenQueues > 0
            ? $heroPendingActions . ' open queue item(s) are visible across ' . max($visibleModuleCount, 1) . ' module(s). Clear unfinished work here before it falls into the day’s background.'
            : 'Visible queues are stable. Use this page as the day board for upcoming service commitments, handovers, and CRM context.';

        $heroStats = [
            ['label' => 'Open Queues', 'value' => vs_dashboard_format_count($heroOpenQueues)],
            ['label' => 'Pending Actions', 'value' => vs_dashboard_format_count($heroPendingActions)],
            ['label' => 'Due Today', 'value' => vs_dashboard_format_count($heroDueToday)],
            ['label' => 'New Today', 'value' => vs_dashboard_format_count($heroRecentSeekers)],
        ];

        $visibleModuleCount = count($moduleBoards);
        $introText = $heroOpenQueues > 0
            ? $heroPendingActions . ' open queue item(s) are visible across ' . max($visibleModuleCount, 1) . ' module(s). Clear unfinished work here before it falls into the day’s background.'
            : 'Visible queues are stable. Use this page as the day board for upcoming service commitments, handovers, and CRM context.';
        /* duplicate intro assignment from incremental patch cleanup */
        $introText = $heroOpenQueues > 0
            ? $heroPendingActions . ' open queue item(s) are visible across ' . max($visibleModuleCount, 1) . ' module(s). Clear unfinished work here before it falls into the day\'s background.'
            : 'Visible queues are stable. Use this page as the day board for upcoming service commitments, handovers, and CRM context.';

        return [
            'base_url' => $baseUrl,
            'today_label' => date('l, d M Y'),
            'time_label' => date('g:i A'),
            'user_name' => $userName,
            'is_super_admin' => $isSuperAdmin,
            'hero_stats' => $heroStats,
            'intro_text' => $introText,
            'attention_cards' => $attentionCards,
            'focus_notes' => $focusNotes,
            'today_metrics' => $todayMetrics,
            'module_boards' => $moduleBoards,
            'timeline' => $timeline,
            'quick_links' => $quickLinks,
            'footer_note' => $isSuperAdmin
                ? 'Super admin view includes all operational modules.'
                : 'Only modules with view permission for your account are shown on this dashboard.',
        ];
    }
}
