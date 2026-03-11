<?php
/**
 * Payment link map helper.
 *
 * Purpose:
 * - Preserve original ORD-* payment links as stable references.
 * - Map ORD -> razorpay_order_id -> pay_* for post-payment recovery UX.
 */

if (!function_exists('vs_paymap_ensure_table')) {
    function vs_paymap_ensure_table(PDO $pdo): void
    {
        static $created = false;
        if ($created) {
            return;
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS payment_link_map (
                id INT AUTO_INCREMENT PRIMARY KEY,
                original_payment_id VARCHAR(120) NOT NULL,
                razorpay_order_id VARCHAR(100) NULL,
                razorpay_payment_id VARCHAR(100) NULL,
                source VARCHAR(50) NULL,
                category VARCHAR(80) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_original_payment_id (original_payment_id),
                INDEX idx_paymap_order_id (razorpay_order_id),
                INDEX idx_paymap_payment_id (razorpay_payment_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $created = true;
    }
}

if (!function_exists('vs_paymap_upsert')) {
    function vs_paymap_upsert(
        PDO $pdo,
        string $originalPaymentId,
        ?string $razorpayOrderId = null,
        ?string $razorpayPaymentId = null,
        ?string $source = null,
        ?string $category = null
    ): void {
        $originalPaymentId = trim($originalPaymentId);
        if ($originalPaymentId === '') {
            return;
        }

        vs_paymap_ensure_table($pdo);

        $sql = "
            INSERT INTO payment_link_map
                (original_payment_id, razorpay_order_id, razorpay_payment_id, source, category)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                razorpay_order_id = IF(VALUES(razorpay_order_id) IS NULL OR VALUES(razorpay_order_id) = '', razorpay_order_id, VALUES(razorpay_order_id)),
                razorpay_payment_id = IF(VALUES(razorpay_payment_id) IS NULL OR VALUES(razorpay_payment_id) = '', razorpay_payment_id, VALUES(razorpay_payment_id)),
                source = IF(VALUES(source) IS NULL OR VALUES(source) = '', source, VALUES(source)),
                category = IF(VALUES(category) IS NULL OR VALUES(category) = '', category, VALUES(category))
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $originalPaymentId,
            $razorpayOrderId !== null ? trim($razorpayOrderId) : null,
            $razorpayPaymentId !== null ? trim($razorpayPaymentId) : null,
            $source !== null ? trim($source) : null,
            $category !== null ? trim($category) : null
        ]);
    }
}

if (!function_exists('vs_paymap_update_by_order')) {
    function vs_paymap_update_by_order(PDO $pdo, string $razorpayOrderId, ?string $razorpayPaymentId = null): void
    {
        $razorpayOrderId = trim($razorpayOrderId);
        if ($razorpayOrderId === '') {
            return;
        }

        vs_paymap_ensure_table($pdo);

        $sql = "
            UPDATE payment_link_map
            SET
                razorpay_order_id = ?,
                razorpay_payment_id = IF(? IS NULL OR ? = '', razorpay_payment_id, ?)
            WHERE razorpay_order_id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $razorpayOrderId,
            $razorpayPaymentId,
            $razorpayPaymentId,
            $razorpayPaymentId,
            $razorpayOrderId
        ]);
    }
}

if (!function_exists('vs_paymap_find')) {
    function vs_paymap_find(PDO $pdo, string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        vs_paymap_ensure_table($pdo);

        $stmt = $pdo->prepare("
            SELECT *
            FROM payment_link_map
            WHERE original_payment_id = ?
               OR razorpay_payment_id = ?
               OR razorpay_order_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$token, $token, $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

