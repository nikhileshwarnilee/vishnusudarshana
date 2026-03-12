<?php
/**
 * Shared helpers for product variants and variant values.
 */

if (!function_exists('vs_variant_normalize_id_list')) {
    function vs_variant_normalize_id_list(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }
        return array_values($normalized);
    }
}

if (!function_exists('vs_parse_variant_values_text')) {
    function vs_parse_variant_values_text(string $rawValues): array
    {
        $parts = preg_split('/[\r\n,]+/', $rawValues);
        if (!is_array($parts)) {
            return [];
        }

        $values = [];
        foreach ($parts as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $key = strtolower($value);
                $values[$key] = $value;
            }
        }
        return array_values($values);
    }
}

if (!function_exists('vs_ensure_product_variant_schema')) {
    function vs_ensure_product_variant_schema(PDO $pdo): void
    {
        static $schemaEnsured = false;
        if ($schemaEnsured) {
            return;
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS product_variants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                variant_name VARCHAR(120) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_product_variant_name (variant_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS product_variant_values (
                id INT AUTO_INCREMENT PRIMARY KEY,
                variant_id INT NOT NULL,
                value_name VARCHAR(120) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NULL,
                UNIQUE KEY uniq_product_variant_value (variant_id, value_name),
                KEY idx_product_variant_values_variant (variant_id),
                CONSTRAINT fk_product_variant_values_variant
                    FOREIGN KEY (variant_id) REFERENCES product_variants(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $columnStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'products'
              AND COLUMN_NAME = 'variant_id'
        ");
        $columnStmt->execute();
        $hasVariantColumn = (int)$columnStmt->fetchColumn() > 0;

        if (!$hasVariantColumn) {
            $pdo->exec("ALTER TABLE products ADD COLUMN variant_id INT NULL AFTER price");
        }

        $longDescStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'products'
              AND COLUMN_NAME = 'long_description'
        ");
        $longDescStmt->execute();
        $hasLongDescriptionColumn = (int)$longDescStmt->fetchColumn() > 0;

        if (!$hasLongDescriptionColumn) {
            $pdo->exec("ALTER TABLE products ADD COLUMN long_description MEDIUMTEXT NULL AFTER short_description");
        }

        $indexStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'products'
              AND INDEX_NAME = 'idx_products_variant_id'
        ");
        $indexStmt->execute();
        $hasVariantIndex = (int)$indexStmt->fetchColumn() > 0;

        if (!$hasVariantIndex) {
            $pdo->exec("ALTER TABLE products ADD INDEX idx_products_variant_id (variant_id)");
        }

        $schemaEnsured = true;
    }
}

if (!function_exists('vs_get_product_variants')) {
    function vs_get_product_variants(PDO $pdo, bool $activeOnly = true): array
    {
        $sql = "SELECT id, variant_name, is_active FROM product_variants";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY variant_name ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('vs_get_product_variant_lookup')) {
    function vs_get_product_variant_lookup(PDO $pdo, array $variantIds, bool $activeOnly = false): array
    {
        $variantIds = vs_variant_normalize_id_list($variantIds);
        if (empty($variantIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $sql = "SELECT id, variant_name, is_active FROM product_variants WHERE id IN ($placeholders)";
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($variantIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $lookup = [];
        foreach ($rows as $row) {
            $lookup[(int)$row['id']] = $row;
        }
        return $lookup;
    }
}

if (!function_exists('vs_get_product_variant_values_grouped')) {
    function vs_get_product_variant_values_grouped(PDO $pdo, array $variantIds, bool $activeOnly = true): array
    {
        $variantIds = vs_variant_normalize_id_list($variantIds);
        if (empty($variantIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $sql = "SELECT id, variant_id, value_name, is_active
                FROM product_variant_values
                WHERE variant_id IN ($placeholders)";
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY value_name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($variantIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $grouped = [];
        foreach ($rows as $row) {
            $variantId = (int)$row['variant_id'];
            if (!isset($grouped[$variantId])) {
                $grouped[$variantId] = [];
            }
            $grouped[$variantId][] = $row;
        }
        return $grouped;
    }
}

if (!function_exists('vs_get_product_variant_value_lookup')) {
    function vs_get_product_variant_value_lookup(PDO $pdo, array $valueIds, bool $activeOnly = false): array
    {
        $valueIds = vs_variant_normalize_id_list($valueIds);
        if (empty($valueIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($valueIds), '?'));
        $sql = "SELECT vv.id, vv.variant_id, vv.value_name, vv.is_active, v.variant_name, v.is_active AS variant_is_active
                FROM product_variant_values vv
                INNER JOIN product_variants v ON v.id = vv.variant_id
                WHERE vv.id IN ($placeholders)";
        if ($activeOnly) {
            $sql .= " AND vv.is_active = 1 AND v.is_active = 1";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($valueIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $lookup = [];
        foreach ($rows as $row) {
            $lookup[(int)$row['id']] = $row;
        }
        return $lookup;
    }
}
