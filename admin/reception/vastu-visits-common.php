<?php

if (!function_exists('vs_reception_vastu_bootstrap')) {
    function vs_reception_vastu_bootstrap(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vastu_visit_areas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                area_name VARCHAR(150) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_vastu_visit_area_name (area_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vastu_visits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_name VARCHAR(255) NOT NULL,
                contact_number VARCHAR(30) NOT NULL,
                details TEXT NULL,
                vastu_type VARCHAR(150) NOT NULL,
                area_id INT NOT NULL,
                address_line1 VARCHAR(255) NULL,
                address_line2 VARCHAR(255) NULL,
                landmark VARCHAR(255) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'open',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                closed_at DATETIME NULL DEFAULT NULL,
                KEY idx_vastu_visits_area (area_id),
                KEY idx_vastu_visits_status (status),
                KEY idx_vastu_visits_created (created_at),
                CONSTRAINT fk_vastu_visits_area FOREIGN KEY (area_id) REFERENCES vastu_visit_areas(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

if (!function_exists('vs_reception_vastu_allowed_return_page')) {
    function vs_reception_vastu_allowed_return_page(?string $page): string
    {
        $page = trim((string)$page);
        if ($page === 'closed-vastu-visits.php') {
            return $page;
        }

        return 'vastu-visits.php';
    }
}

if (!function_exists('vs_reception_vastu_all_areas')) {
    function vs_reception_vastu_all_areas(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT id, area_name, created_at, updated_at FROM vastu_visit_areas ORDER BY area_name ASC, id ASC");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}

if (!function_exists('vs_reception_vastu_area_exists')) {
    function vs_reception_vastu_area_exists(PDO $pdo, int $areaId): bool
    {
        if ($areaId <= 0) {
            return false;
        }

        $stmt = $pdo->prepare("SELECT id FROM vastu_visit_areas WHERE id = ? LIMIT 1");
        $stmt->execute([$areaId]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('vs_reception_vastu_find_area')) {
    function vs_reception_vastu_find_area(PDO $pdo, int $areaId): ?array
    {
        if ($areaId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare("SELECT id, area_name, created_at, updated_at FROM vastu_visit_areas WHERE id = ? LIMIT 1");
        $stmt->execute([$areaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('vs_reception_vastu_find_visit')) {
    function vs_reception_vastu_find_visit(PDO $pdo, int $visitId): ?array
    {
        if ($visitId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT
                v.*,
                a.area_name
            FROM vastu_visits v
            INNER JOIN vastu_visit_areas a ON a.id = v.area_id
            WHERE v.id = ?
            LIMIT 1
        ");
        $stmt->execute([$visitId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('vs_reception_vastu_fetch_grouped_visits')) {
    function vs_reception_vastu_fetch_grouped_visits(PDO $pdo, string $status): array
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['open', 'closed'], true)) {
            $status = 'open';
        }

        $stmt = $pdo->prepare("
            SELECT
                v.*,
                a.area_name
            FROM vastu_visits v
            INNER JOIN vastu_visit_areas a ON a.id = v.area_id
            WHERE v.status = ?
            ORDER BY a.area_name ASC, v.created_at DESC, v.id DESC
        ");
        $stmt->execute([$status]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $areaName = trim((string)($row['area_name'] ?? ''));
            if ($areaName === '') {
                $areaName = 'Unassigned Area';
            }
            if (!isset($grouped[$areaName])) {
                $grouped[$areaName] = [];
            }
            $grouped[$areaName][] = $row;
        }

        return $grouped;
    }
}
