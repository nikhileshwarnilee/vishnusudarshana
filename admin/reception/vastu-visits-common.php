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

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vastu_visit_notes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                visit_id INT NOT NULL,
                note_text TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_vastu_visit_notes_visit (visit_id),
                KEY idx_vastu_visit_notes_created (created_at),
                CONSTRAINT fk_vastu_visit_notes_visit FOREIGN KEY (visit_id) REFERENCES vastu_visits(id) ON DELETE CASCADE
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
        $notesByVisit = vs_reception_vastu_fetch_notes_for_visits($pdo, array_column($rows, 'id'));

        $grouped = [];
        foreach ($rows as $row) {
            $visitId = (int)($row['id'] ?? 0);
            $row['notes'] = $notesByVisit[$visitId] ?? [];
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

if (!function_exists('vs_reception_vastu_fetch_notes_for_visits')) {
    function vs_reception_vastu_fetch_notes_for_visits(PDO $pdo, array $visitIds): array
    {
        $visitIds = array_values(array_unique(array_filter(array_map('intval', $visitIds), static function ($visitId) {
            return $visitId > 0;
        })));

        if (empty($visitIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($visitIds), '?'));
        $stmt = $pdo->prepare("
            SELECT id, visit_id, note_text, created_at
            FROM vastu_visit_notes
            WHERE visit_id IN ($placeholders)
            ORDER BY created_at DESC, id DESC
        ");
        $stmt->execute($visitIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $notesByVisit = [];
        foreach ($rows as $row) {
            $visitId = (int)($row['visit_id'] ?? 0);
            if ($visitId <= 0) {
                continue;
            }
            if (!isset($notesByVisit[$visitId])) {
                $notesByVisit[$visitId] = [];
            }
            $notesByVisit[$visitId][] = $row;
        }

        return $notesByVisit;
    }
}

if (!function_exists('vs_reception_vastu_fetch_notes')) {
    function vs_reception_vastu_fetch_notes(PDO $pdo, int $visitId): array
    {
        if ($visitId <= 0) {
            return [];
        }

        $notesByVisit = vs_reception_vastu_fetch_notes_for_visits($pdo, [$visitId]);
        return $notesByVisit[$visitId] ?? [];
    }
}

if (!function_exists('vs_reception_vastu_add_note')) {
    function vs_reception_vastu_add_note(PDO $pdo, int $visitId, string $noteText): bool
    {
        $noteText = trim($noteText);
        if ($visitId <= 0 || $noteText === '') {
            return false;
        }

        $stmt = $pdo->prepare("INSERT INTO vastu_visit_notes (visit_id, note_text) VALUES (?, ?)");
        return $stmt->execute([$visitId, $noteText]);
    }
}

if (!function_exists('vs_reception_vastu_find_note')) {
    function vs_reception_vastu_find_note(PDO $pdo, int $noteId, int $visitId = 0): ?array
    {
        if ($noteId <= 0) {
            return null;
        }

        $sql = "SELECT id, visit_id, note_text, created_at FROM vastu_visit_notes WHERE id = ?";
        $params = [$noteId];
        if ($visitId > 0) {
            $sql .= " AND visit_id = ?";
            $params[] = $visitId;
        }
        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('vs_reception_vastu_update_note')) {
    function vs_reception_vastu_update_note(PDO $pdo, int $noteId, int $visitId, string $noteText): bool
    {
        $noteText = trim($noteText);
        if ($noteId <= 0 || $visitId <= 0 || $noteText === '') {
            return false;
        }

        $stmt = $pdo->prepare("
            UPDATE vastu_visit_notes
            SET note_text = ?
            WHERE id = ? AND visit_id = ?
            LIMIT 1
        ");
        $stmt->execute([$noteText, $noteId, $visitId]);
        return $stmt->rowCount() > 0;
    }
}

if (!function_exists('vs_reception_vastu_delete_note')) {
    function vs_reception_vastu_delete_note(PDO $pdo, int $noteId, int $visitId): bool
    {
        if ($noteId <= 0 || $visitId <= 0) {
            return false;
        }

        $stmt = $pdo->prepare("DELETE FROM vastu_visit_notes WHERE id = ? AND visit_id = ? LIMIT 1");
        $stmt->execute([$noteId, $visitId]);
        return $stmt->rowCount() > 0;
    }
}
