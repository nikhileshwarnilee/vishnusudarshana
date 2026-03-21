<?php
/**
 * Remedy management helpers for CIF-linked clients.
 */

if (!function_exists('vs_remedy_column_exists')) {
    function vs_remedy_column_exists(PDO $pdo, string $tableName, string $columnName): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?"
        );
        $stmt->execute([$tableName, $columnName]);

        return (int) $stmt->fetchColumn() > 0;
    }
}

if (!function_exists('vs_remedy_ensure_column')) {
    function vs_remedy_ensure_column(PDO $pdo, string $tableName, string $columnName, string $definition): void
    {
        if (!vs_remedy_column_exists($pdo, $tableName, $columnName)) {
            $pdo->exec("ALTER TABLE {$tableName} ADD COLUMN {$columnName} {$definition}");
        }
    }
}

if (!function_exists('vs_remedy_ensure_tables')) {
    function vs_remedy_ensure_tables(PDO $pdo): void
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }

        $queries = [
            "CREATE TABLE IF NOT EXISTS remedies (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                client_id INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                start_date DATE NOT NULL,
                repeat_type ENUM('monthly', 'yearly', 'custom_interval') NOT NULL DEFAULT 'custom_interval',
                custom_interval_days INT UNSIGNED DEFAULT NULL,
                last_completed_date DATE DEFAULT NULL,
                next_due_date DATE NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_remedies_client_due (client_id, next_due_date),
                KEY idx_remedies_client_created (client_id, created_at),
                KEY idx_remedies_repeat_type (repeat_type, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS remedy_logs (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                remedy_id INT UNSIGNED NOT NULL,
                client_id INT UNSIGNED NOT NULL,
                completed_date DATE NOT NULL,
                completion_notes TEXT NULL,
                created_by_user_id INT DEFAULT NULL,
                created_by_user_name VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_remedy_logs_remedy_date (remedy_id, completed_date),
                KEY idx_remedy_logs_client_date (client_id, completed_date),
                KEY idx_remedy_logs_created_by (created_by_user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS remedy_schedule_rows (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                remedy_id INT UNSIGNED NOT NULL,
                client_id INT UNSIGNED NOT NULL,
                sequence_no INT UNSIGNED NOT NULL,
                date_type ENUM('fixed_date', 'in_between') NOT NULL DEFAULT 'fixed_date',
                anchor_date DATE NOT NULL,
                fixed_date DATE DEFAULT NULL,
                from_date DATE DEFAULT NULL,
                to_date DATE DEFAULT NULL,
                instructions TEXT NULL,
                is_completed TINYINT(1) NOT NULL DEFAULT 0,
                completed_date DATE DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_remedy_schedule_rows_remedy_order (remedy_id, sequence_no),
                KEY idx_remedy_schedule_rows_client_anchor (client_id, anchor_date),
                KEY idx_remedy_schedule_rows_pending (remedy_id, is_completed, anchor_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($queries as $query) {
            $pdo->exec($query);
        }

        vs_remedy_ensure_column($pdo, 'remedy_logs', 'schedule_row_id', 'INT UNSIGNED DEFAULT NULL AFTER remedy_id');
        vs_remedy_ensure_column($pdo, 'remedy_logs', 'schedule_label', 'VARCHAR(255) DEFAULT NULL AFTER schedule_row_id');
        vs_remedy_ensure_column($pdo, 'remedy_schedule_rows', 'date_type', "ENUM('fixed_date', 'in_between') NOT NULL DEFAULT 'fixed_date' AFTER client_id");
        vs_remedy_ensure_column($pdo, 'remedy_schedule_rows', 'anchor_date', "DATE NOT NULL DEFAULT '2000-01-01' AFTER date_type");
        vs_remedy_ensure_column($pdo, 'remedy_schedule_rows', 'fixed_date', 'DATE DEFAULT NULL AFTER anchor_date');
        vs_remedy_ensure_column($pdo, 'remedy_schedule_rows', 'from_date', 'DATE DEFAULT NULL AFTER fixed_date');
        vs_remedy_ensure_column($pdo, 'remedy_schedule_rows', 'to_date', 'DATE DEFAULT NULL AFTER from_date');
        vs_remedy_ensure_column($pdo, 'remedy_schedule_rows', 'instructions', 'TEXT NULL AFTER to_date');
        vs_remedy_ensure_column($pdo, 'remedy_schedule_rows', 'is_completed', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER instructions');
        vs_remedy_ensure_column($pdo, 'remedy_schedule_rows', 'completed_date', 'DATE DEFAULT NULL AFTER is_completed');
        vs_remedy_ensure_column($pdo, 'remedy_schedule_rows', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER completed_date');
        vs_remedy_ensure_column($pdo, 'remedy_schedule_rows', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');

        if (!vs_remedy_column_exists($pdo, 'remedy_schedule_rows', 'sequence_no')) {
            $pdo->exec('ALTER TABLE remedy_schedule_rows ADD COLUMN sequence_no INT UNSIGNED DEFAULT NULL AFTER client_id');
            if (vs_remedy_column_exists($pdo, 'remedy_schedule_rows', 'row_number')) {
                $pdo->exec('UPDATE remedy_schedule_rows SET sequence_no = COALESCE(row_number, id) WHERE sequence_no IS NULL');
            } else {
                $pdo->exec('UPDATE remedy_schedule_rows SET sequence_no = id WHERE sequence_no IS NULL');
            }
            $pdo->exec('ALTER TABLE remedy_schedule_rows MODIFY COLUMN sequence_no INT UNSIGNED NOT NULL');
        }

        $initialized = true;
    }
}

if (!function_exists('vs_remedy_normalize_date')) {
    function vs_remedy_normalize_date($value, ?string $fallback = null): string
    {
        $raw = trim((string) $value);
        if ($raw !== '') {
            $date = date_create($raw);
            if ($date instanceof DateTimeInterface) {
                return $date->format('Y-m-d');
            }
        }

        if ($fallback !== null && trim($fallback) !== '') {
            return vs_remedy_normalize_date($fallback, null);
        }

        return date('Y-m-d');
    }
}

if (!function_exists('vs_remedy_fetch_client')) {
    function vs_remedy_fetch_client(PDO $pdo, int $clientId): ?array
    {
        if ($clientId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM cif_clients WHERE id = ? LIMIT 1');
        $stmt->execute([$clientId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        return $client ?: null;
    }
}

if (!function_exists('vs_remedy_fetch_remedy')) {
    function vs_remedy_fetch_remedy(PDO $pdo, int $remedyId, int $clientId): ?array
    {
        if ($remedyId <= 0 || $clientId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT * FROM remedies WHERE id = ? AND client_id = ? LIMIT 1');
        $stmt->execute([$remedyId, $clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('vs_remedy_fetch_row')) {
    function vs_remedy_fetch_row(PDO $pdo, int $rowId, int $clientId): ?array
    {
        if ($rowId <= 0 || $clientId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT
                rsr.*,
                r.title AS remedy_title
             FROM remedy_schedule_rows rsr
             INNER JOIN remedies r ON r.id = rsr.remedy_id
             WHERE rsr.id = ? AND rsr.client_id = ?
             LIMIT 1"
        );
        $stmt->execute([$rowId, $clientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('vs_remedy_validate_parent_payload')) {
    function vs_remedy_validate_parent_payload(array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $repeatQty = max(1, (int) ($payload['repeat_qty'] ?? 1));

        if ($title === '') {
            throw new InvalidArgumentException('Please enter a remedy name.');
        }

        return [
            'title' => $title,
            'description' => $description,
            'repeat_qty' => $repeatQty,
        ];
    }
}

if (!function_exists('vs_remedy_validate_schedule_rows')) {
    function vs_remedy_validate_schedule_rows(array $rows): array
    {
        $validatedRows = [];

        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $sequenceNo = $index + 1;
            $instructions = trim((string) ($row['instructions'] ?? $row['note'] ?? ''));
            $isCompleted = isset($row['is_completed']) && (string) $row['is_completed'] === '1';
            $completedDateRaw = trim((string) ($row['completed_date'] ?? $row['performed_date'] ?? ''));
            $completedDate = $isCompleted
                ? vs_remedy_normalize_date($completedDateRaw, date('Y-m-d'))
                : null;
            $anchorDate = $completedDate !== null ? $completedDate : date('Y-m-d');

            $validatedRows[] = [
                'sequence_no' => $sequenceNo,
                'date_type' => 'fixed_date',
                'anchor_date' => $anchorDate,
                'fixed_date' => $anchorDate,
                'from_date' => null,
                'to_date' => null,
                'instructions' => $instructions,
                'is_completed' => $isCompleted ? 1 : 0,
                'completed_date' => $completedDate,
            ];
        }

        if (empty($validatedRows)) {
            throw new InvalidArgumentException('Please add at least one perform row.');
        }

        return $validatedRows;
    }
}

if (!function_exists('vs_remedy_row_schedule_label')) {
    function vs_remedy_row_schedule_label(array $row): string
    {
        return 'Perform ' . (int) ($row['sequence_no'] ?? 0);
    }
}

if (!function_exists('vs_remedy_sync_row_log')) {
    function vs_remedy_sync_row_log(PDO $pdo, array $row, ?int $createdByUserId = null, string $createdByUserName = ''): void
    {
        $rowId = (int) ($row['id'] ?? 0);
        $clientId = (int) ($row['client_id'] ?? 0);
        $remedyId = (int) ($row['remedy_id'] ?? 0);
        $isCompleted = (int) ($row['is_completed'] ?? 0) === 1;
        $completedDate = trim((string) ($row['completed_date'] ?? ''));

        if ($rowId <= 0 || $clientId <= 0 || $remedyId <= 0) {
            return;
        }

        if (!$isCompleted || $completedDate === '') {
            $deleteStmt = $pdo->prepare('DELETE FROM remedy_logs WHERE schedule_row_id = ? AND client_id = ?');
            $deleteStmt->execute([$rowId, $clientId]);
            return;
        }

        $scheduleLabel = vs_remedy_row_schedule_label($row);
        $existingStmt = $pdo->prepare('SELECT id FROM remedy_logs WHERE schedule_row_id = ? AND client_id = ? LIMIT 1');
        $existingStmt->execute([$rowId, $clientId]);
        $existingLogId = (int) $existingStmt->fetchColumn();

        if ($existingLogId > 0) {
            $updateStmt = $pdo->prepare(
                'UPDATE remedy_logs
                 SET remedy_id = ?, schedule_label = ?, completed_date = ?, created_by_user_id = ?, created_by_user_name = ?
                 WHERE id = ?'
            );
            $updateStmt->execute([
                $remedyId,
                $scheduleLabel,
                $completedDate,
                $createdByUserId,
                trim($createdByUserName) !== '' ? trim($createdByUserName) : null,
                $existingLogId,
            ]);
            return;
        }

        $insertStmt = $pdo->prepare(
            'INSERT INTO remedy_logs (
                remedy_id,
                schedule_row_id,
                schedule_label,
                client_id,
                completed_date,
                completion_notes,
                created_by_user_id,
                created_by_user_name
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insertStmt->execute([
            $remedyId,
            $rowId,
            $scheduleLabel,
            $clientId,
            $completedDate,
            null,
            $createdByUserId,
            trim($createdByUserName) !== '' ? trim($createdByUserName) : null,
        ]);
    }
}

if (!function_exists('vs_remedy_resequence_rows')) {
    function vs_remedy_resequence_rows(PDO $pdo, int $remedyId): void
    {
        $stmt = $pdo->prepare('SELECT id FROM remedy_schedule_rows WHERE remedy_id = ? ORDER BY sequence_no ASC, id ASC');
        $stmt->execute([$remedyId]);
        $rowIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $updateStmt = $pdo->prepare('UPDATE remedy_schedule_rows SET sequence_no = ? WHERE id = ?');
        $sequenceNo = 1;
        foreach ($rowIds as $rowId) {
            $updateStmt->execute([$sequenceNo, (int) $rowId]);
            $sequenceNo++;
        }
    }
}

if (!function_exists('vs_remedy_refresh_parent_summary')) {
    function vs_remedy_refresh_parent_summary(PDO $pdo, int $remedyId): void
    {
        $summaryStmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS total_rows,
                SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) AS completed_rows,
                MAX(completed_date) AS last_completed_date
             FROM remedy_schedule_rows
             WHERE remedy_id = ?"
        );
        $summaryStmt->execute([$remedyId]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalRows = (int) ($summary['total_rows'] ?? 0);
        $completedRows = (int) ($summary['completed_rows'] ?? 0);
        $lastCompletedDate = trim((string) ($summary['last_completed_date'] ?? ''));
        $hasPendingRows = $totalRows > $completedRows;
        $nextDueDate = $hasPendingRows
            ? date('Y-m-d')
            : ($lastCompletedDate !== '' ? $lastCompletedDate : date('Y-m-d'));
        $isActive = $hasPendingRows ? 1 : 0;

        $updateStmt = $pdo->prepare(
            'UPDATE remedies
             SET last_completed_date = ?, next_due_date = ?, is_active = ?
             WHERE id = ?'
        );
        $updateStmt->execute([
            $lastCompletedDate !== '' ? $lastCompletedDate : null,
            $nextDueDate,
            $isActive,
            $remedyId,
        ]);
    }
}

if (!function_exists('vs_remedy_create')) {
    function vs_remedy_create(PDO $pdo, int $clientId, array $payload, array $rows): int
    {
        if ($clientId <= 0) {
            throw new InvalidArgumentException('Invalid client selected.');
        }

        $data = vs_remedy_validate_parent_payload($payload);
        $validatedRows = vs_remedy_validate_schedule_rows($rows);

        if (count($validatedRows) !== (int) $data['repeat_qty']) {
            throw new InvalidArgumentException('Please generate and fill all perform rows before saving.');
        }

        $today = date('Y-m-d');

        $pdo->beginTransaction();
        try {
            $parentStmt = $pdo->prepare(
                'INSERT INTO remedies (
                    client_id,
                    title,
                    description,
                    start_date,
                    repeat_type,
                    custom_interval_days,
                    next_due_date,
                    is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
            );
            $parentStmt->execute([
                $clientId,
                $data['title'],
                $data['description'] !== '' ? $data['description'] : null,
                $today,
                'custom_interval',
                null,
                $today,
            ]);

            $remedyId = (int) $pdo->lastInsertId();

            $rowStmt = $pdo->prepare(
                'INSERT INTO remedy_schedule_rows (
                    remedy_id,
                    client_id,
                    sequence_no,
                    date_type,
                    anchor_date,
                    fixed_date,
                    from_date,
                    to_date,
                    instructions,
                    is_completed,
                    completed_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($validatedRows as $row) {
                $rowStmt->execute([
                    $remedyId,
                    $clientId,
                    $row['sequence_no'],
                    $row['date_type'],
                    $row['anchor_date'],
                    $row['fixed_date'],
                    $row['from_date'],
                    $row['to_date'],
                    $row['instructions'] !== '' ? $row['instructions'] : null,
                    $row['is_completed'],
                    $row['completed_date'],
                ]);

                if ((int) $row['is_completed'] === 1) {
                    $row['id'] = (int) $pdo->lastInsertId();
                    $row['remedy_id'] = $remedyId;
                    $row['client_id'] = $clientId;
                    vs_remedy_sync_row_log(
                        $pdo,
                        $row,
                        isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
                        trim((string) ($_SESSION['user_name'] ?? ''))
                    );
                }
            }

            vs_remedy_refresh_parent_summary($pdo, $remedyId);
            $pdo->commit();
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $throwable;
        }

        return $remedyId;
    }
}

if (!function_exists('vs_remedy_add')) {
    function vs_remedy_add(PDO $pdo, int $clientId, array $payload): int
    {
        return vs_remedy_create($pdo, $clientId, [
            'title' => $payload['title'] ?? '',
            'description' => $payload['description'] ?? '',
            'repeat_qty' => 1,
        ], [[
            'instructions' => $payload['description'] ?? '',
        ]]);
    }
}

if (!function_exists('vs_remedy_update')) {
    function vs_remedy_update(PDO $pdo, int $remedyId, int $clientId, array $payload): void
    {
        $remedy = vs_remedy_fetch_remedy($pdo, $remedyId, $clientId);
        if (!$remedy) {
            throw new RuntimeException('Remedy not found for this client.');
        }

        $title = trim((string) ($payload['title'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));

        if ($title === '') {
            throw new InvalidArgumentException('Please enter a remedy name.');
        }

        $stmt = $pdo->prepare('UPDATE remedies SET title = ?, description = ? WHERE id = ? AND client_id = ?');
        $stmt->execute([
            $title,
            $description !== '' ? $description : null,
            $remedyId,
            $clientId,
        ]);
    }
}

if (!function_exists('vs_remedy_next_sequence_no')) {
    function vs_remedy_next_sequence_no(PDO $pdo, int $remedyId): int
    {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sequence_no), 0) FROM remedy_schedule_rows WHERE remedy_id = ?');
        $stmt->execute([$remedyId]);

        return ((int) $stmt->fetchColumn()) + 1;
    }
}

if (!function_exists('vs_remedy_add_row')) {
    function vs_remedy_add_row(PDO $pdo, int $remedyId, int $clientId, array $payload = []): int
    {
        $remedy = vs_remedy_fetch_remedy($pdo, $remedyId, $clientId);
        if (!$remedy) {
            throw new RuntimeException('Remedy not found for this client.');
        }

        $instructions = trim((string) ($payload['instructions'] ?? ''));
        $isCompleted = isset($payload['is_completed']) && (string) $payload['is_completed'] === '1';
        $completedDate = $isCompleted
            ? vs_remedy_normalize_date($payload['completed_date'] ?? date('Y-m-d'), date('Y-m-d'))
            : null;
        $anchorDate = $completedDate !== null ? $completedDate : date('Y-m-d');
        $sequenceNo = vs_remedy_next_sequence_no($pdo, $remedyId);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO remedy_schedule_rows (
                    remedy_id,
                    client_id,
                    sequence_no,
                    date_type,
                    anchor_date,
                    fixed_date,
                    from_date,
                    to_date,
                    instructions,
                    is_completed,
                    completed_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $remedyId,
                $clientId,
                $sequenceNo,
                'fixed_date',
                $anchorDate,
                $anchorDate,
                null,
                null,
                $instructions !== '' ? $instructions : null,
                $isCompleted ? 1 : 0,
                $completedDate,
            ]);

            $rowId = (int) $pdo->lastInsertId();

            if ($isCompleted) {
                vs_remedy_sync_row_log($pdo, [
                    'id' => $rowId,
                    'remedy_id' => $remedyId,
                    'client_id' => $clientId,
                    'sequence_no' => $sequenceNo,
                    'is_completed' => 1,
                    'completed_date' => $completedDate,
                ], isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null, trim((string) ($_SESSION['user_name'] ?? '')));
            }

            vs_remedy_refresh_parent_summary($pdo, $remedyId);
            $pdo->commit();
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $throwable;
        }

        return $rowId;
    }
}

if (!function_exists('vs_remedy_update_row')) {
    function vs_remedy_update_row(
        PDO $pdo,
        int $rowId,
        int $clientId,
        array $payload,
        ?int $createdByUserId = null,
        string $createdByUserName = ''
    ): array {
        $row = vs_remedy_fetch_row($pdo, $rowId, $clientId);
        if (!$row) {
            throw new RuntimeException('Remedy row not found for this client.');
        }

        $instructions = trim((string) ($payload['instructions'] ?? ($row['instructions'] ?? '')));
        $isCompleted = isset($payload['is_completed'])
            ? ((string) $payload['is_completed'] === '1')
            : ((int) ($row['is_completed'] ?? 0) === 1);
        $completedDateRaw = trim((string) ($payload['completed_date'] ?? ($row['completed_date'] ?? '')));
        $completedDate = $isCompleted
            ? vs_remedy_normalize_date($completedDateRaw, $row['completed_date'] ?? date('Y-m-d'))
            : null;
        $anchorDate = trim((string) ($row['anchor_date'] ?? '')) !== ''
            ? (string) $row['anchor_date']
            : date('Y-m-d');

        $pdo->beginTransaction();
        try {
            $updateStmt = $pdo->prepare(
                'UPDATE remedy_schedule_rows
                 SET instructions = ?, is_completed = ?, completed_date = ?, fixed_date = ?
                 WHERE id = ? AND client_id = ?'
            );
            $updateStmt->execute([
                $instructions !== '' ? $instructions : null,
                $isCompleted ? 1 : 0,
                $completedDate,
                $completedDate !== null ? $completedDate : $anchorDate,
                $rowId,
                $clientId,
            ]);

            $refetchedRow = vs_remedy_fetch_row($pdo, $rowId, $clientId);
            if (!$refetchedRow) {
                throw new RuntimeException('Unable to refresh remedy row after update.');
            }

            vs_remedy_sync_row_log($pdo, $refetchedRow, $createdByUserId, $createdByUserName);
            vs_remedy_refresh_parent_summary($pdo, (int) $refetchedRow['remedy_id']);
            $pdo->commit();
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $throwable;
        }

        return $refetchedRow;
    }
}

if (!function_exists('vs_remedy_delete_row')) {
    function vs_remedy_delete_row(PDO $pdo, int $rowId, int $clientId): array
    {
        $row = vs_remedy_fetch_row($pdo, $rowId, $clientId);
        if (!$row) {
            throw new RuntimeException('Remedy row not found for this client.');
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM remedy_schedule_rows WHERE remedy_id = ?');
        $countStmt->execute([(int) $row['remedy_id']]);
        $rowCount = (int) $countStmt->fetchColumn();

        if ($rowCount <= 1) {
            throw new RuntimeException('Use delete remedy to remove the last perform row.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM remedy_logs WHERE schedule_row_id = ? AND client_id = ?')->execute([$rowId, $clientId]);
            $pdo->prepare('DELETE FROM remedy_schedule_rows WHERE id = ? AND client_id = ?')->execute([$rowId, $clientId]);

            vs_remedy_resequence_rows($pdo, (int) $row['remedy_id']);
            vs_remedy_refresh_parent_summary($pdo, (int) $row['remedy_id']);
            $pdo->commit();
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $throwable;
        }

        return $row;
    }
}

if (!function_exists('vs_remedy_delete')) {
    function vs_remedy_delete(PDO $pdo, int $remedyId, int $clientId): array
    {
        $remedy = vs_remedy_fetch_remedy($pdo, $remedyId, $clientId);
        if (!$remedy) {
            throw new RuntimeException('Remedy not found for this client.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM remedy_logs WHERE remedy_id = ? AND client_id = ?')->execute([$remedyId, $clientId]);
            $pdo->prepare('DELETE FROM remedy_schedule_rows WHERE remedy_id = ? AND client_id = ?')->execute([$remedyId, $clientId]);
            $pdo->prepare('DELETE FROM remedies WHERE id = ? AND client_id = ?')->execute([$remedyId, $clientId]);
            $pdo->commit();
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $throwable;
        }

        return $remedy;
    }
}

if (!function_exists('vs_remedy_fetch_for_client')) {
    function vs_remedy_fetch_for_client(PDO $pdo, int $clientId): array
    {
        if ($clientId <= 0) {
            return [];
        }

        $stmt = $pdo->prepare(
            "SELECT
                r.*,
                COALESCE(row_summary.total_rows, 0) AS total_rows,
                COALESCE(row_summary.completed_rows, 0) AS completed_rows
             FROM remedies r
             LEFT JOIN (
                SELECT
                    remedy_id,
                    COUNT(*) AS total_rows,
                    SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) AS completed_rows
                FROM remedy_schedule_rows
                GROUP BY remedy_id
             ) AS row_summary ON row_summary.remedy_id = r.id
             WHERE r.client_id = ?
             ORDER BY r.created_at DESC, r.id DESC"
        );
        $stmt->execute([$clientId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('vs_remedy_fetch_rows_for_client')) {
    function vs_remedy_fetch_rows_for_client(PDO $pdo, int $clientId): array
    {
        if ($clientId <= 0) {
            return [];
        }

        $stmt = $pdo->prepare(
            "SELECT *
             FROM remedy_schedule_rows
             WHERE client_id = ?
             ORDER BY remedy_id DESC, sequence_no ASC, id ASC"
        );
        $stmt->execute([$clientId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $groupedRows = [];
        foreach ($rows as $row) {
            $remedyId = (int) ($row['remedy_id'] ?? 0);
            if (!isset($groupedRows[$remedyId])) {
                $groupedRows[$remedyId] = [];
            }
            $groupedRows[$remedyId][] = $row;
        }

        return $groupedRows;
    }
}

if (!function_exists('vs_remedy_fetch_logs_for_client')) {
    function vs_remedy_fetch_logs_for_client(PDO $pdo, int $clientId, int $limit = 25): array
    {
        if ($clientId <= 0) {
            return [];
        }

        $limit = max(1, $limit);
        $stmt = $pdo->prepare(
            "SELECT
                rl.*,
                r.title AS remedy_title
             FROM remedy_logs rl
             INNER JOIN remedies r ON r.id = rl.remedy_id
             WHERE rl.client_id = ?
             ORDER BY rl.completed_date DESC, rl.id DESC
             LIMIT " . (int) $limit
        );
        $stmt->execute([$clientId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('vs_remedy_mark_row_completed')) {
    function vs_remedy_mark_row_completed(
        PDO $pdo,
        int $rowId,
        int $clientId,
        string $completedDate,
        string $completionNotes = '',
        ?int $createdByUserId = null,
        string $createdByUserName = ''
    ): array {
        unset($completionNotes);
        return vs_remedy_update_row($pdo, $rowId, $clientId, [
            'is_completed' => '1',
            'completed_date' => $completedDate,
        ], $createdByUserId, $createdByUserName);
    }
}

if (!function_exists('vs_remedy_mark_row_pending')) {
    function vs_remedy_mark_row_pending(PDO $pdo, int $rowId, int $clientId): array
    {
        return vs_remedy_update_row($pdo, $rowId, $clientId, [
            'is_completed' => '0',
            'completed_date' => '',
        ]);
    }
}

if (!function_exists('vs_remedy_mark_completed')) {
    function vs_remedy_mark_completed(
        PDO $pdo,
        int $remedyId,
        int $clientId,
        string $completedDate,
        string $completionNotes = '',
        ?int $createdByUserId = null,
        string $createdByUserName = ''
    ): array {
        unset($completionNotes);
        $stmt = $pdo->prepare(
            "SELECT id
             FROM remedy_schedule_rows
             WHERE remedy_id = ? AND client_id = ? AND is_completed = 0
             ORDER BY sequence_no ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$remedyId, $clientId]);
        $rowId = (int) $stmt->fetchColumn();

        if ($rowId <= 0) {
            throw new RuntimeException('No pending remedy row found.');
        }

        return vs_remedy_mark_row_completed(
            $pdo,
            $rowId,
            $clientId,
            $completedDate,
            '',
            $createdByUserId,
            $createdByUserName
        );
    }
}

if (!function_exists('vs_remedy_row_date_label')) {
    function vs_remedy_row_date_label(array $row): string
    {
        $completedDate = trim((string) ($row['completed_date'] ?? ''));
        if ($completedDate !== '') {
            return $completedDate;
        }

        return vs_remedy_row_schedule_label($row);
    }
}

if (!function_exists('vs_remedy_row_type_label')) {
    function vs_remedy_row_type_label(array $row): string
    {
        unset($row);
        return 'Perform Entry';
    }
}

if (!function_exists('vs_remedy_row_anchor_date')) {
    function vs_remedy_row_anchor_date(array $row): string
    {
        $completedDate = trim((string) ($row['completed_date'] ?? ''));
        if ($completedDate !== '') {
            return $completedDate;
        }

        return vs_remedy_normalize_date($row['anchor_date'] ?? date('Y-m-d'), date('Y-m-d'));
    }
}

if (!function_exists('vs_remedy_row_due_status')) {
    function vs_remedy_row_due_status(array $row): array
    {
        if ((int) ($row['is_completed'] ?? 0) === 1) {
            return ['label' => 'Done', 'class' => 'completed'];
        }

        return ['label' => 'Not Yet Done', 'class' => 'pending'];
    }
}

if (!function_exists('vs_remedy_due_status')) {
    function vs_remedy_due_status(?string $nextDueDate, bool $isCompleted = false): array
    {
        unset($nextDueDate);
        if ($isCompleted) {
            return ['label' => 'Done', 'class' => 'completed'];
        }

        return ['label' => 'Not Yet Done', 'class' => 'pending'];
    }
}
