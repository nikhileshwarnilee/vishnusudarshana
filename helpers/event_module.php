<?php
/**
 * Event module shared helpers.
 */

if (!function_exists('vs_event_ensure_tables')) {
    function vs_event_ensure_tables(PDO $pdo): void
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }

        $queries = [
            "CREATE TABLE IF NOT EXISTS events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                event_type ENUM('single_day', 'multi_select_dates', 'date_range') NOT NULL DEFAULT 'single_day',
                short_description TEXT NULL,
                long_description LONGTEXT NULL,
                youtube_video_url VARCHAR(255) DEFAULT NULL,
                description LONGTEXT NULL,
                image VARCHAR(255) DEFAULT NULL,
                location VARCHAR(255) DEFAULT NULL,
                event_date DATE NOT NULL,
                registration_start DATE NOT NULL,
                registration_end DATE NOT NULL,
                status ENUM('Active', 'Closed') NOT NULL DEFAULT 'Active',
                send_whatsapp_notifications TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_event_slug (slug),
                KEY idx_events_status_date (status, event_date),
                KEY idx_events_registration_window (registration_start, registration_end)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS event_packages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                package_name VARCHAR(255) NOT NULL,
                display_order INT NOT NULL DEFAULT 0,
                is_paid TINYINT(1) NOT NULL DEFAULT 1,
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                price_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                advance_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                payment_mode ENUM('full', 'advance', 'optional') NOT NULL DEFAULT 'full',
                payment_methods VARCHAR(100) DEFAULT 'razorpay,upi',
                upi_id VARCHAR(120) DEFAULT NULL,
                upi_qr_image VARCHAR(255) DEFAULT NULL,
                cancellation_allowed TINYINT(1) NOT NULL DEFAULT 1,
                refund_allowed TINYINT(1) NOT NULL DEFAULT 1,
                allow_checkin_without_payment TINYINT(1) NOT NULL DEFAULT 0,
                waitlist_confirmation_mode ENUM('auto', 'manual') NOT NULL DEFAULT 'auto',
                seat_limit INT DEFAULT NULL,
                description TEXT NULL,
                status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_event_packages_event_id (event_id),
                KEY idx_event_packages_display_order (event_id, display_order, id),
                KEY idx_event_packages_status (status),
                CONSTRAINT fk_event_packages_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS event_package_date_prices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                package_id INT NOT NULL,
                event_date_id INT NOT NULL,
                price_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_event_package_date_price (package_id, event_date_id),
                KEY idx_event_package_date_prices_date (event_date_id),
                CONSTRAINT fk_event_package_date_prices_package FOREIGN KEY (package_id) REFERENCES event_packages(id) ON DELETE CASCADE,
                CONSTRAINT fk_event_package_date_prices_date FOREIGN KEY (event_date_id) REFERENCES event_dates(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS event_dates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                event_date DATE NOT NULL,
                seat_limit INT DEFAULT NULL,
                status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_event_dates_event_id (event_id),
                KEY idx_event_dates_status_date (status, event_date),
                UNIQUE KEY uniq_event_dates_event_date (event_id, event_date),
                CONSTRAINT fk_event_dates_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS event_form_fields (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                field_name VARCHAR(120) NOT NULL,
                field_type VARCHAR(50) NOT NULL,
                field_options TEXT NULL,
                field_placeholder VARCHAR(255) NULL,
                required TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_event_form_fields_event_id (event_id),
                CONSTRAINT fk_event_form_fields_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS event_registrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                booking_reference VARCHAR(30) DEFAULT NULL,
                event_id INT NOT NULL,
                package_id INT NOT NULL,
                event_date_id INT DEFAULT NULL,
                package_upi_id_snapshot VARCHAR(120) DEFAULT NULL,
                package_upi_qr_snapshot VARCHAR(255) DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                phone VARCHAR(30) NOT NULL,
                persons INT NOT NULL DEFAULT 1,
                payment_status VARCHAR(50) NOT NULL DEFAULT 'Unpaid',
                verification_status VARCHAR(50) NOT NULL DEFAULT 'Pending',
                qr_code_path VARCHAR(255) DEFAULT NULL,
                checkin_status TINYINT(1) NOT NULL DEFAULT 0,
                checkin_time DATETIME DEFAULT NULL,
                checkin_by_user_id INT DEFAULT NULL,
                checkin_by_user_name VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_event_booking_reference (booking_reference),
                KEY idx_event_registrations_package_phone (package_id, phone),
                KEY idx_event_registrations_event_id (event_id),
                KEY idx_event_registrations_package_id (package_id),
                KEY idx_event_registrations_event_date_id (event_date_id),
                KEY idx_event_registrations_status (payment_status, verification_status),
                KEY idx_event_registrations_checkin (checkin_status, event_id),
                KEY idx_event_registrations_checkin_user_date (checkin_by_user_id, checkin_time),
                CONSTRAINT fk_event_registrations_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                CONSTRAINT fk_event_registrations_package FOREIGN KEY (package_id) REFERENCES event_packages(id) ON DELETE CASCADE,
                CONSTRAINT fk_event_registrations_event_date FOREIGN KEY (event_date_id) REFERENCES event_dates(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS event_registration_data (
                id INT AUTO_INCREMENT PRIMARY KEY,
                registration_id INT NOT NULL,
                field_name VARCHAR(120) NOT NULL,
                value LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_event_registration_data_registration_id (registration_id),
                CONSTRAINT fk_event_registration_data_registration FOREIGN KEY (registration_id) REFERENCES event_registrations(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS event_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                registration_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                payment_type ENUM('advance', 'remaining', 'full') NOT NULL DEFAULT 'full',
                amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                remaining_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                payment_method VARCHAR(50) NOT NULL,
                upi_id_used VARCHAR(120) DEFAULT NULL,
                upi_qr_used VARCHAR(255) DEFAULT NULL,
                transaction_id VARCHAR(255) DEFAULT NULL,
                screenshot VARCHAR(255) DEFAULT NULL,
                remarks TEXT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'Pending',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_event_payments_registration_id (registration_id),
                KEY idx_event_payments_status_method (status, payment_method),
                CONSTRAINT fk_event_payments_registration FOREIGN KEY (registration_id) REFERENCES event_registrations(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS event_waitlist (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                package_id INT NOT NULL,
                event_date_id INT DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                phone VARCHAR(30) NOT NULL,
                persons INT NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_event_waitlist_package_phone (package_id, phone),
                KEY idx_event_waitlist_event_id (event_id),
                KEY idx_event_waitlist_package_id (package_id),
                KEY idx_event_waitlist_event_date_id (event_date_id),
                CONSTRAINT fk_event_waitlist_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                CONSTRAINT fk_event_waitlist_package FOREIGN KEY (package_id) REFERENCES event_packages(id) ON DELETE CASCADE,
                CONSTRAINT fk_event_waitlist_event_date FOREIGN KEY (event_date_id) REFERENCES event_dates(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS event_cancellations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                registration_id INT NOT NULL,
                cancelled_persons INT NOT NULL DEFAULT 1,
                cancellation_type ENUM('full', 'partial') NOT NULL DEFAULT 'full',
                cancel_reason TEXT NULL,
                refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                refund_status ENUM('pending', 'processed', 'rejected') NOT NULL DEFAULT 'pending',
                cancelled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_event_cancellations_registration_id (registration_id),
                KEY idx_event_cancellations_refund_status (refund_status),
                CONSTRAINT fk_event_cancellations_registration FOREIGN KEY (registration_id) REFERENCES event_registrations(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS event_cancellation_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                registration_id INT NOT NULL,
                requested_persons INT NOT NULL DEFAULT 1,
                request_type ENUM('full', 'partial') NOT NULL DEFAULT 'full',
                cancel_reason TEXT NULL,
                request_source ENUM('online', 'admin') NOT NULL DEFAULT 'online',
                request_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                decision_note TEXT NULL,
                requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                decided_at DATETIME DEFAULT NULL,
                decided_by_user_id INT DEFAULT NULL,
                decided_by_user_name VARCHAR(255) DEFAULT NULL,
                processed_cancellation_id INT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_event_cancellation_requests_registration (registration_id),
                KEY idx_event_cancellation_requests_status (request_status, requested_at),
                CONSTRAINT fk_event_cancellation_requests_registration FOREIGN KEY (registration_id) REFERENCES event_registrations(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS event_registration_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_event_registration_attempts_ip_created (ip_address, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($queries as $sql) {
            $pdo->exec($sql);
        }

        $hasColumn = static function (string $table, string $column) use ($pdo): bool {
            $stmt = $pdo->prepare("SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND column_name = ?");
            $stmt->execute([$table, $column]);
            return ((int)$stmt->fetchColumn()) > 0;
        };

        $hasIndex = static function (string $table, string $index) use ($pdo): bool {
            $stmt = $pdo->prepare("SELECT COUNT(*)
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND index_name = ?");
            $stmt->execute([$table, $index]);
            return ((int)$stmt->fetchColumn()) > 0;
        };

        // New schema columns for multi-date events, rich content and staged payments.
        if (!$hasColumn('events', 'event_type')) {
            $pdo->exec("ALTER TABLE events ADD COLUMN event_type ENUM('single_day', 'multi_select_dates', 'date_range') NOT NULL DEFAULT 'single_day' AFTER slug");
        }
        if (!$hasColumn('events', 'short_description')) {
            $pdo->exec("ALTER TABLE events ADD COLUMN short_description TEXT NULL AFTER event_type");
        }
        if (!$hasColumn('events', 'long_description')) {
            $pdo->exec("ALTER TABLE events ADD COLUMN long_description LONGTEXT NULL AFTER short_description");
        }
        if (!$hasColumn('events', 'youtube_video_url')) {
            $pdo->exec("ALTER TABLE events ADD COLUMN youtube_video_url VARCHAR(255) DEFAULT NULL AFTER long_description");
        }
        if (!$hasColumn('events', 'send_whatsapp_notifications')) {
            $pdo->exec("ALTER TABLE events ADD COLUMN send_whatsapp_notifications TINYINT(1) NOT NULL DEFAULT 1 AFTER status");
        }
        if (!$hasColumn('event_form_fields', 'field_placeholder')) {
            $pdo->exec("ALTER TABLE event_form_fields ADD COLUMN field_placeholder VARCHAR(255) NULL AFTER field_options");
        }
        $pdo->exec("UPDATE events
            SET short_description = CASE
                    WHEN COALESCE(short_description, '') = '' THEN LEFT(COALESCE(description, ''), 500)
                    ELSE short_description
                END,
                long_description = CASE
                    WHEN COALESCE(long_description, '') = '' THEN COALESCE(description, '')
                    ELSE long_description
                END
            WHERE 1=1");

        // Keep event_dates in sync for existing single-day events.
        $pdo->exec("INSERT IGNORE INTO event_dates (event_id, event_date, seat_limit, status)
            SELECT e.id, e.event_date, NULL, 'Active'
            FROM events e
            WHERE e.event_date IS NOT NULL");

        if (!$hasColumn('event_packages', 'price_total')) {
            $pdo->exec("ALTER TABLE event_packages ADD COLUMN price_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price");
        }
        if (!$hasColumn('event_packages', 'advance_amount')) {
            $pdo->exec("ALTER TABLE event_packages ADD COLUMN advance_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price_total");
        }
        if (!$hasColumn('event_packages', 'payment_mode')) {
            $pdo->exec("ALTER TABLE event_packages ADD COLUMN payment_mode ENUM('full', 'advance', 'optional') NOT NULL DEFAULT 'full' AFTER advance_amount");
        }
        if (!$hasColumn('event_packages', 'payment_methods')) {
            $pdo->exec("ALTER TABLE event_packages ADD COLUMN payment_methods VARCHAR(100) DEFAULT 'razorpay,upi' AFTER payment_mode");
        }
        if (!$hasColumn('event_packages', 'upi_id')) {
            $pdo->exec("ALTER TABLE event_packages ADD COLUMN upi_id VARCHAR(120) DEFAULT NULL AFTER payment_methods");
        }
        if (!$hasColumn('event_packages', 'upi_qr_image')) {
            $pdo->exec("ALTER TABLE event_packages ADD COLUMN upi_qr_image VARCHAR(255) DEFAULT NULL AFTER upi_id");
        }
        if (!$hasColumn('event_packages', 'cancellation_allowed')) {
            $pdo->exec("ALTER TABLE event_packages ADD COLUMN cancellation_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER payment_methods");
        }
        if (!$hasColumn('event_packages', 'refund_allowed')) {
            $pdo->exec("ALTER TABLE event_packages ADD COLUMN refund_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER cancellation_allowed");
        }
        if (!$hasColumn('event_packages', 'allow_checkin_without_payment')) {
            $pdo->exec("ALTER TABLE event_packages ADD COLUMN allow_checkin_without_payment TINYINT(1) NOT NULL DEFAULT 0 AFTER refund_allowed");
        }
        if (!$hasColumn('event_packages', 'waitlist_confirmation_mode')) {
            $pdo->exec("ALTER TABLE event_packages ADD COLUMN waitlist_confirmation_mode ENUM('auto', 'manual') NOT NULL DEFAULT 'auto' AFTER allow_checkin_without_payment");
        }
        if (!$hasColumn('event_packages', 'display_order')) {
            $pdo->exec("ALTER TABLE event_packages ADD COLUMN display_order INT NOT NULL DEFAULT 0 AFTER package_name");
        }
        if (!$hasIndex('event_packages', 'idx_event_packages_display_order')) {
            $pdo->exec("ALTER TABLE event_packages ADD KEY idx_event_packages_display_order (event_id, display_order, id)");
        }
        $didAddIsPaidColumn = false;
        if (!$hasColumn('event_packages', 'is_paid')) {
            $pdo->exec("ALTER TABLE event_packages ADD COLUMN is_paid TINYINT(1) NOT NULL DEFAULT 1 AFTER package_name");
            $didAddIsPaidColumn = true;
        }
        // Backfill new package pricing columns from legacy price column.
        $pdo->exec("UPDATE event_packages
            SET price_total = CASE WHEN price_total <= 0 THEN price ELSE price_total END,
                advance_amount = CASE WHEN advance_amount < 0 THEN 0 ELSE advance_amount END");
        // Backfill payment type/method config for existing package rows.
        if ($didAddIsPaidColumn) {
            $pdo->exec("UPDATE event_packages
                SET is_paid = CASE
                    WHEN COALESCE(price_total, 0) > 0 OR COALESCE(price, 0) > 0 OR COALESCE(advance_amount, 0) > 0 THEN 1
                    ELSE 0
                END");
        }
        $pdo->exec("UPDATE event_packages
            SET payment_methods = CASE
                WHEN is_paid = 1 AND COALESCE(TRIM(payment_methods), '') = '' THEN 'razorpay,upi'
                ELSE payment_methods
            END");
        $pdo->exec("UPDATE event_packages
            SET payment_methods = ''
            WHERE is_paid = 0
              AND COALESCE(TRIM(payment_methods), '') <> ''");
        $pdo->exec("UPDATE event_packages
            SET upi_id = NULLIF(TRIM(COALESCE(upi_id, '')), ''),
                upi_qr_image = NULLIF(TRIM(COALESCE(upi_qr_image, '')), '')");

        if (!$hasColumn('event_registrations', 'event_date_id')) {
            $pdo->exec("ALTER TABLE event_registrations ADD COLUMN event_date_id INT DEFAULT NULL AFTER package_id");
        }
        if (!$hasColumn('event_registrations', 'package_upi_id_snapshot')) {
            $pdo->exec("ALTER TABLE event_registrations ADD COLUMN package_upi_id_snapshot VARCHAR(120) DEFAULT NULL AFTER event_date_id");
        }
        if (!$hasColumn('event_registrations', 'package_upi_qr_snapshot')) {
            $pdo->exec("ALTER TABLE event_registrations ADD COLUMN package_upi_qr_snapshot VARCHAR(255) DEFAULT NULL AFTER package_upi_id_snapshot");
        }
        if (!$hasIndex('event_registrations', 'idx_event_registrations_event_date_id')) {
            $pdo->exec("ALTER TABLE event_registrations ADD KEY idx_event_registrations_event_date_id (event_date_id)");
        }
        $pdo->exec("UPDATE event_registrations r
            INNER JOIN event_packages p ON p.id = r.package_id
            SET r.package_upi_id_snapshot = CASE
                    WHEN COALESCE(TRIM(r.package_upi_id_snapshot), '') = '' THEN p.upi_id
                    ELSE r.package_upi_id_snapshot
                END,
                r.package_upi_qr_snapshot = CASE
                    WHEN COALESCE(TRIM(r.package_upi_qr_snapshot), '') = '' THEN p.upi_qr_image
                    ELSE r.package_upi_qr_snapshot
                END");
        $pdo->exec("UPDATE event_registrations
            SET package_upi_id_snapshot = NULLIF(TRIM(COALESCE(package_upi_id_snapshot, '')), ''),
                package_upi_qr_snapshot = NULLIF(TRIM(COALESCE(package_upi_qr_snapshot, '')), '')");

        if (!$hasColumn('event_payments', 'payment_type')) {
            $pdo->exec("ALTER TABLE event_payments ADD COLUMN payment_type ENUM('advance', 'remaining', 'full') NOT NULL DEFAULT 'full' AFTER amount");
        }
        if (!$hasColumn('event_payments', 'amount_paid')) {
            $pdo->exec("ALTER TABLE event_payments ADD COLUMN amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER payment_type");
        }
        if (!$hasColumn('event_payments', 'remaining_amount')) {
            $pdo->exec("ALTER TABLE event_payments ADD COLUMN remaining_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER amount_paid");
        }
        if (!$hasColumn('event_payments', 'remarks')) {
            $pdo->exec("ALTER TABLE event_payments ADD COLUMN remarks TEXT NULL AFTER screenshot");
        }
        if (!$hasColumn('event_payments', 'upi_id_used')) {
            $pdo->exec("ALTER TABLE event_payments ADD COLUMN upi_id_used VARCHAR(120) DEFAULT NULL AFTER payment_method");
        }
        if (!$hasColumn('event_payments', 'upi_qr_used')) {
            $pdo->exec("ALTER TABLE event_payments ADD COLUMN upi_qr_used VARCHAR(255) DEFAULT NULL AFTER upi_id_used");
        }
        $pdo->exec("UPDATE event_payments ep
            INNER JOIN event_registrations r ON r.id = ep.registration_id
            SET ep.upi_id_used = CASE
                    WHEN LOWER(COALESCE(ep.payment_method, '')) = 'manual upi'
                         AND COALESCE(TRIM(ep.upi_id_used), '') = '' THEN r.package_upi_id_snapshot
                    ELSE ep.upi_id_used
                END,
                ep.upi_qr_used = CASE
                    WHEN LOWER(COALESCE(ep.payment_method, '')) = 'manual upi'
                         AND COALESCE(TRIM(ep.upi_qr_used), '') = '' THEN r.package_upi_qr_snapshot
                    ELSE ep.upi_qr_used
                END");
        $pdo->exec("UPDATE event_payments
            SET upi_id_used = NULLIF(TRIM(COALESCE(upi_id_used, '')), ''),
                upi_qr_used = NULLIF(TRIM(COALESCE(upi_qr_used, '')), '')");
        $pdo->exec("UPDATE event_payments
            SET amount_paid = CASE
                WHEN amount_paid <= 0 AND status IN ('Paid', 'Approved') THEN amount
                ELSE amount_paid
            END");

        if (!$hasColumn('event_waitlist', 'event_date_id')) {
            $pdo->exec("ALTER TABLE event_waitlist ADD COLUMN event_date_id INT DEFAULT NULL AFTER package_id");
        }
        if (!$hasIndex('event_waitlist', 'idx_event_waitlist_event_date_id')) {
            $pdo->exec("ALTER TABLE event_waitlist ADD KEY idx_event_waitlist_event_date_id (event_date_id)");
        }

        if (!$hasColumn('event_cancellations', 'cancelled_persons')) {
            $pdo->exec("ALTER TABLE event_cancellations ADD COLUMN cancelled_persons INT NOT NULL DEFAULT 1 AFTER registration_id");
        }
        if (!$hasColumn('event_cancellations', 'cancellation_type')) {
            $pdo->exec("ALTER TABLE event_cancellations ADD COLUMN cancellation_type ENUM('full', 'partial') NOT NULL DEFAULT 'full' AFTER cancelled_persons");
        }

        // Backward-compatible schema patch for existing installs.
        $columnCheckStmt = $pdo->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'event_registrations'
              AND column_name = 'booking_reference'");
        $columnCheckStmt->execute();
        $hasBookingReferenceColumn = ((int)$columnCheckStmt->fetchColumn()) > 0;
        if (!$hasBookingReferenceColumn) {
            $pdo->exec("ALTER TABLE event_registrations ADD COLUMN booking_reference VARCHAR(30) DEFAULT NULL AFTER id");
        }

        $indexCheckStmt = $pdo->prepare("SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'event_registrations'
              AND index_name = 'uniq_event_booking_reference'");
        $indexCheckStmt->execute();
        $hasBookingReferenceIndex = ((int)$indexCheckStmt->fetchColumn()) > 0;
        if (!$hasBookingReferenceIndex) {
            $pdo->exec("ALTER TABLE event_registrations ADD UNIQUE KEY uniq_event_booking_reference (booking_reference)");
        }

        if ($hasIndex('event_registrations', 'uniq_event_package_phone')) {
            $pdo->exec("ALTER TABLE event_registrations DROP INDEX uniq_event_package_phone");
        }
        if (!$hasIndex('event_registrations', 'idx_event_registrations_package_phone')) {
            $pdo->exec("ALTER TABLE event_registrations ADD KEY idx_event_registrations_package_phone (package_id, phone)");
        }

        $qrColumnCheckStmt = $pdo->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'event_registrations'
              AND column_name = 'qr_code_path'");
        $qrColumnCheckStmt->execute();
        if (((int)$qrColumnCheckStmt->fetchColumn()) === 0) {
            $pdo->exec("ALTER TABLE event_registrations ADD COLUMN qr_code_path VARCHAR(255) DEFAULT NULL AFTER verification_status");
        }

        $checkinStatusColumnStmt = $pdo->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'event_registrations'
              AND column_name = 'checkin_status'");
        $checkinStatusColumnStmt->execute();
        if (((int)$checkinStatusColumnStmt->fetchColumn()) === 0) {
            $pdo->exec("ALTER TABLE event_registrations ADD COLUMN checkin_status TINYINT(1) NOT NULL DEFAULT 0 AFTER qr_code_path");
        }

        $checkinTimeColumnStmt = $pdo->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'event_registrations'
              AND column_name = 'checkin_time'");
        $checkinTimeColumnStmt->execute();
        if (((int)$checkinTimeColumnStmt->fetchColumn()) === 0) {
            $pdo->exec("ALTER TABLE event_registrations ADD COLUMN checkin_time DATETIME DEFAULT NULL AFTER checkin_status");
        }

        $checkinByUserIdColumnStmt = $pdo->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'event_registrations'
              AND column_name = 'checkin_by_user_id'");
        $checkinByUserIdColumnStmt->execute();
        if (((int)$checkinByUserIdColumnStmt->fetchColumn()) === 0) {
            $pdo->exec("ALTER TABLE event_registrations ADD COLUMN checkin_by_user_id INT DEFAULT NULL AFTER checkin_time");
        }

        $checkinByUserNameColumnStmt = $pdo->prepare("SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'event_registrations'
              AND column_name = 'checkin_by_user_name'");
        $checkinByUserNameColumnStmt->execute();
        if (((int)$checkinByUserNameColumnStmt->fetchColumn()) === 0) {
            $pdo->exec("ALTER TABLE event_registrations ADD COLUMN checkin_by_user_name VARCHAR(255) DEFAULT NULL AFTER checkin_by_user_id");
        }

        $checkinIndexStmt = $pdo->prepare("SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'event_registrations'
              AND index_name = 'idx_event_registrations_checkin'");
        $checkinIndexStmt->execute();
        if (((int)$checkinIndexStmt->fetchColumn()) === 0) {
            $pdo->exec("ALTER TABLE event_registrations ADD KEY idx_event_registrations_checkin (checkin_status, event_id)");
        }

        $checkinUserDateIndexStmt = $pdo->prepare("SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'event_registrations'
              AND index_name = 'idx_event_registrations_checkin_user_date'");
        $checkinUserDateIndexStmt->execute();
        if (((int)$checkinUserDateIndexStmt->fetchColumn()) === 0) {
            $pdo->exec("ALTER TABLE event_registrations ADD KEY idx_event_registrations_checkin_user_date (checkin_by_user_id, checkin_time)");
        }

        // Backfill booking references for already-created rows.
        $missingRows = $pdo->query("SELECT id, created_at, payment_status, verification_status
            FROM event_registrations
            WHERE COALESCE(booking_reference, '') = ''
            ORDER BY id ASC")
            ->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($missingRows)) {
            $backfillStmt = $pdo->prepare("UPDATE event_registrations SET booking_reference = ? WHERE id = ?");
            foreach ($missingRows as $missingRow) {
                $registrationId = (int)($missingRow['id'] ?? 0);
                if ($registrationId <= 0) {
                    continue;
                }
                $paymentStatus = strtolower(trim((string)($missingRow['payment_status'] ?? '')));
                $verificationStatus = strtolower(trim((string)($missingRow['verification_status'] ?? '')));
                $isWaitlisted = ($paymentStatus === 'waitlisted' || $verificationStatus === 'waitlisted');
                $reference = $isWaitlisted
                    ? vs_event_format_waitlist_booking_reference($registrationId, (string)($missingRow['created_at'] ?? ''))
                    : vs_event_format_booking_reference($registrationId, (string)($missingRow['created_at'] ?? ''));
                $backfillStmt->execute([$reference, $registrationId]);
            }
        }

        $initialized = true;
    }
}

if (!function_exists('vs_event_slugify')) {
    function vs_event_slugify(string $text): string
    {
        $value = strtolower(trim($text));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim((string)$value, '-');

        if ($value === '') {
            return 'event-' . date('YmdHis');
        }

        return $value;
    }
}

if (!function_exists('vs_event_format_booking_reference')) {
    function vs_event_format_booking_reference(int $registrationId, ?string $createdAt = null): string
    {
        $year = date('Y');
        if ($createdAt !== null && $createdAt !== '') {
            $ts = strtotime($createdAt);
            if ($ts !== false) {
                $year = date('Y', $ts);
            }
        }

        return sprintf('VS-EVT-%s-%04d', $year, $registrationId);
    }
}

if (!function_exists('vs_event_format_waitlist_booking_reference')) {
    function vs_event_format_waitlist_booking_reference(int $registrationId, ?string $createdAt = null): string
    {
        $year = date('Y');
        if ($createdAt !== null && $createdAt !== '') {
            $ts = strtotime($createdAt);
            if ($ts !== false) {
                $year = date('Y', $ts);
            }
        }

        return sprintf('VS-WL-%s-%04d', $year, $registrationId);
    }
}

if (!function_exists('vs_event_is_waitlisted_registration')) {
    function vs_event_is_waitlisted_registration(array $row): bool
    {
        $paymentStatus = strtolower(trim((string)($row['payment_status'] ?? '')));
        $verificationStatus = strtolower(trim((string)($row['verification_status'] ?? '')));
        return ($paymentStatus === 'waitlisted' || $verificationStatus === 'waitlisted');
    }
}

if (!function_exists('vs_event_assign_booking_reference')) {
    function vs_event_assign_booking_reference(PDO $pdo, int $registrationId): string
    {
        $stmt = $pdo->prepare("SELECT booking_reference, created_at FROM event_registrations WHERE id = ? LIMIT 1");
        $stmt->execute([$registrationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return '';
        }

        $existingReference = trim((string)($row['booking_reference'] ?? ''));
        if ($existingReference !== '') {
            return $existingReference;
        }

        $reference = vs_event_format_booking_reference($registrationId, (string)($row['created_at'] ?? ''));
        $updateStmt = $pdo->prepare("UPDATE event_registrations SET booking_reference = ? WHERE id = ? AND COALESCE(booking_reference, '') = ''");
        $updateStmt->execute([$reference, $registrationId]);

        return $reference;
    }
}

if (!function_exists('vs_event_assign_waitlist_booking_reference')) {
    function vs_event_assign_waitlist_booking_reference(PDO $pdo, int $registrationId): string
    {
        $stmt = $pdo->prepare("SELECT booking_reference, created_at FROM event_registrations WHERE id = ? LIMIT 1");
        $stmt->execute([$registrationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return '';
        }

        $existingReference = trim((string)($row['booking_reference'] ?? ''));
        if ($existingReference !== '') {
            return $existingReference;
        }

        $reference = vs_event_format_waitlist_booking_reference($registrationId, (string)($row['created_at'] ?? ''));
        $updateStmt = $pdo->prepare("UPDATE event_registrations SET booking_reference = ? WHERE id = ? AND COALESCE(booking_reference, '') = ''");
        $updateStmt->execute([$reference, $registrationId]);

        return $reference;
    }
}

if (!function_exists('vs_event_get_waitlist_position')) {
    function vs_event_get_waitlist_position(PDO $pdo, int $registrationId): int
    {
        $registrationId = max($registrationId, 0);
        if ($registrationId <= 0) {
            return 0;
        }

        $rowStmt = $pdo->prepare("SELECT id, package_id, COALESCE(event_date_id, 0) AS event_date_id, created_at, payment_status, verification_status
            FROM event_registrations
            WHERE id = ?
            LIMIT 1");
        $rowStmt->execute([$registrationId]);
        $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !vs_event_is_waitlisted_registration($row)) {
            return 0;
        }

        $packageId = (int)($row['package_id'] ?? 0);
        $eventDateId = (int)($row['event_date_id'] ?? 0);
        if ($packageId <= 0) {
            return 0;
        }

        $createdAt = trim((string)($row['created_at'] ?? ''));
        $countStmt = $pdo->prepare("SELECT COUNT(*)
            FROM event_registrations
            WHERE package_id = ?
              AND COALESCE(event_date_id, 0) = ?
              AND (payment_status = 'Waitlisted' OR verification_status = 'Waitlisted')
              AND (
                    created_at < ?
                    OR (created_at = ? AND id <= ?)
              )");
        $countStmt->execute([$packageId, $eventDateId, $createdAt, $createdAt, $registrationId]);

        return (int)$countStmt->fetchColumn();
    }
}

if (!function_exists('vs_event_is_registration_open')) {
    function vs_event_is_registration_open(array $event, ?string $onDate = null): bool
    {
        $today = $onDate ?: date('Y-m-d');
        if (($event['status'] ?? '') !== 'Active') {
            return false;
        }

        $start = (string)($event['registration_start'] ?? '');
        $end = (string)($event['registration_end'] ?? '');
        if ($start === '' || $end === '') {
            return false;
        }

        return ($today >= $start && $today <= $end);
    }
}

if (!function_exists('vs_event_count_booked_seats_for_event_date')) {
    function vs_event_count_booked_seats_for_event_date(PDO $pdo, int $eventId, int $eventDateId): int
    {
        $eventId = max($eventId, 0);
        $eventDateId = max($eventDateId, 0);
        if ($eventId <= 0 || $eventDateId <= 0) {
            return 0;
        }

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(persons), 0)
            FROM event_registrations
            WHERE event_id = ?
              AND event_date_id = ?
              AND verification_status IN ('Pending', 'Approved', 'Auto Verified')
              AND payment_status NOT IN ('Failed', 'Cancelled')");
        $stmt->execute([$eventId, $eventDateId]);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('vs_event_count_booked_seats_for_package')) {
    function vs_event_count_booked_seats_for_package(PDO $pdo, int $packageId): int
    {
        $packageId = max($packageId, 0);
        if ($packageId <= 0) {
            return 0;
        }

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(persons), 0)
            FROM event_registrations
            WHERE package_id = ?
              AND verification_status IN ('Pending', 'Approved', 'Auto Verified')
              AND payment_status NOT IN ('Failed', 'Cancelled')");
        $stmt->execute([$packageId]);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('vs_event_fetch_event_dates_with_booked_seats')) {
    function vs_event_fetch_event_dates_with_booked_seats(PDO $pdo, int $eventId): array
    {
        $eventId = max($eventId, 0);
        if ($eventId <= 0) {
            return [];
        }

        $stmt = $pdo->prepare("SELECT
                d.id,
                d.event_id,
                d.event_date,
                d.seat_limit,
                d.status,
                COALESCE(SUM(r.persons), 0) AS booked_seats
            FROM event_dates d
            LEFT JOIN event_registrations r
                ON r.event_date_id = d.id
               AND r.verification_status IN ('Pending', 'Approved', 'Auto Verified')
               AND r.payment_status NOT IN ('Failed', 'Cancelled')
            WHERE d.event_id = ?
            GROUP BY d.id, d.event_id, d.event_date, d.seat_limit, d.status
            ORDER BY d.event_date ASC, d.id ASC");
        $stmt->execute([$eventId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('vs_event_fetch_packages_with_seats')) {
    function vs_event_fetch_packages_with_seats(PDO $pdo, int $eventId, bool $onlyActive = true, int $eventDateId = 0): array
    {
        $eventId = max($eventId, 0);
        $eventDateId = max($eventDateId, 0);
        if ($eventId <= 0) {
            return [];
        }

        $dateFilterSql = '';
        $dateFilterParams = [];
        if ($eventDateId > 0) {
            $dateFilterSql = ' AND r.event_date_id = ?';
            $dateFilterParams[] = $eventDateId;
        }

        $selectedPriceJoinSql = '';
        $selectedPriceParams = [];
        $effectivePriceSelect = '(CASE WHEN p.price_total > 0 THEN p.price_total ELSE p.price END) AS effective_price_total';
        if ($eventDateId > 0) {
            $selectedPriceJoinSql = ' LEFT JOIN event_package_date_prices pdpsel ON pdpsel.package_id = p.id AND pdpsel.event_date_id = ?';
            $selectedPriceParams[] = $eventDateId;
            $effectivePriceSelect = 'COALESCE(pdpsel.price_total, (CASE WHEN p.price_total > 0 THEN p.price_total ELSE p.price END)) AS effective_price_total';
        }

        $sql = "
            SELECT
                p.*,
                {$effectivePriceSelect},
                COALESCE(SUM(CASE
                    WHEN r.verification_status IN ('Pending', 'Approved', 'Auto Verified')
                         AND r.payment_status NOT IN ('Failed', 'Cancelled')
                    THEN r.persons
                    ELSE 0
                END), 0) AS seats_booked,
                COALESCE(SUM(CASE
                    WHEN r.payment_status IN ('Paid', 'Partial Paid')
                    THEN COALESCE(ep.amount_paid, ep.amount, (COALESCE(pdp.price_total, (CASE WHEN p.price_total > 0 THEN p.price_total ELSE p.price END)) * r.persons))
                    ELSE 0
                END), 0) AS revenue_generated
            FROM event_packages p
            LEFT JOIN event_registrations r ON r.package_id = p.id{$dateFilterSql}
            LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
            {$selectedPriceJoinSql}
            LEFT JOIN event_payments ep ON ep.registration_id = r.id
            WHERE p.event_id = ?
        ";

        $params = array_merge($dateFilterParams, $selectedPriceParams, [$eventId]);
        if ($onlyActive) {
            $sql .= " AND p.status = 'Active'";
        }

        $sql .= " GROUP BY p.id ORDER BY p.display_order ASC, p.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dateSeatLimit = null;
        $dateBooked = 0;
        if ($eventDateId > 0) {
            $dateStmt = $pdo->prepare("SELECT seat_limit
                FROM event_dates
                WHERE id = ?
                  AND event_id = ?
                  AND status = 'Active'
                LIMIT 1");
            $dateStmt->execute([$eventDateId, $eventId]);
            $dateRow = $dateStmt->fetch(PDO::FETCH_ASSOC);
            if ($dateRow) {
                $dateSeatLimitRaw = (int)($dateRow['seat_limit'] ?? 0);
                if ($dateSeatLimitRaw > 0) {
                    $dateSeatLimit = $dateSeatLimitRaw;
                }

                $dateBookedStmt = $pdo->prepare("SELECT COALESCE(SUM(persons), 0)
                    FROM event_registrations
                    WHERE event_id = ?
                      AND event_date_id = ?
                      AND verification_status IN ('Pending', 'Approved', 'Auto Verified')
                      AND payment_status NOT IN ('Failed', 'Cancelled')");
                $dateBookedStmt->execute([$eventId, $eventDateId]);
                $dateBooked = (int)$dateBookedStmt->fetchColumn();
            }
        }

        foreach ($rows as &$row) {
            $row['effective_price_total'] = isset($row['effective_price_total']) ? (float)$row['effective_price_total'] : (float)($row['price_total'] ?? $row['price'] ?? 0);
            if ($eventDateId > 0) {
                $row['price_total'] = $row['effective_price_total'];
                $row['price'] = $row['effective_price_total'];
            }

            $packageSeatLimit = isset($row['seat_limit']) ? (int)$row['seat_limit'] : 0;
            $booked = isset($row['seats_booked']) ? (int)$row['seats_booked'] : 0;

            $packageSeatsLeft = null;
            if ($packageSeatLimit > 0) {
                $packageSeatsLeft = max($packageSeatLimit - $booked, 0);
            }

            $dateSeatsLeft = null;
            if ($dateSeatLimit !== null) {
                $dateSeatsLeft = max($dateSeatLimit - $dateBooked, 0);
            }

            $row['date_seat_limit'] = $dateSeatLimit;
            $row['date_seats_booked'] = $dateBooked;
            $row['date_seats_left'] = $dateSeatsLeft;

            if ($packageSeatsLeft === null && $dateSeatsLeft === null) {
                $row['total_seats'] = null;
                $row['seats_left'] = null;
                $row['is_full'] = false;
            } elseif ($packageSeatsLeft !== null && $dateSeatsLeft !== null) {
                $row['total_seats'] = min($packageSeatLimit, $dateSeatLimit);
                $row['seats_left'] = min($packageSeatsLeft, $dateSeatsLeft);
                $row['is_full'] = $row['seats_left'] <= 0;
            } elseif ($packageSeatsLeft !== null) {
                $row['total_seats'] = $packageSeatLimit;
                $row['seats_left'] = $packageSeatsLeft;
                $row['is_full'] = $row['seats_left'] <= 0;
            } else {
                $row['total_seats'] = $dateSeatLimit;
                $row['seats_left'] = $dateSeatsLeft;
                $row['is_full'] = $row['seats_left'] <= 0;
            }

            $row['revenue_generated'] = isset($row['revenue_generated']) ? (float)$row['revenue_generated'] : 0.0;
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('vs_event_fetch_event_dates')) {
    function vs_event_fetch_event_dates(PDO $pdo, int $eventId, bool $onlyActive = true): array
    {
        $eventId = max($eventId, 0);
        if ($eventId <= 0) {
            return [];
        }

        $sql = "SELECT id, event_id, event_date, seat_limit, status
            FROM event_dates
            WHERE event_id = ?";
        $params = [$eventId];
        if ($onlyActive) {
            $sql .= " AND status = 'Active'";
        }
        $sql .= " ORDER BY event_date ASC, id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('vs_event_fetch_package_date_price_map')) {
    function vs_event_fetch_package_date_price_map(PDO $pdo, int $packageId): array
    {
        $packageId = max($packageId, 0);
        if ($packageId <= 0) {
            return [];
        }

        $stmt = $pdo->prepare("SELECT event_date_id, price_total
            FROM event_package_date_prices
            WHERE package_id = ?");
        $stmt->execute([$packageId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $priceMap = [];
        foreach ($rows as $row) {
            $dateId = (int)($row['event_date_id'] ?? 0);
            if ($dateId <= 0) {
                continue;
            }
            $priceMap[$dateId] = (float)($row['price_total'] ?? 0);
        }

        return $priceMap;
    }
}

if (!function_exists('vs_event_replace_package_date_prices')) {
    function vs_event_replace_package_date_prices(PDO $pdo, int $packageId, array $priceMap): void
    {
        $packageId = max($packageId, 0);
        if ($packageId <= 0) {
            return;
        }

        $pdo->prepare("DELETE FROM event_package_date_prices WHERE package_id = ?")->execute([$packageId]);
        if (empty($priceMap)) {
            return;
        }

        $insertStmt = $pdo->prepare("INSERT INTO event_package_date_prices (package_id, event_date_id, price_total) VALUES (?, ?, ?)");
        foreach ($priceMap as $dateId => $priceTotal) {
            $dateId = (int)$dateId;
            if ($dateId <= 0) {
                continue;
            }
            $priceTotal = (float)$priceTotal;
            if ($priceTotal < 0) {
                $priceTotal = 0;
            }
            $insertStmt->execute([$packageId, $dateId, $priceTotal]);
        }
    }
}

if (!function_exists('vs_event_normalize_dates_from_range')) {
    function vs_event_normalize_dates_from_range(string $startDate, string $endDate, ?int $seatLimit = null, string $status = 'Active'): array
    {
        $startDate = trim($startDate);
        $endDate = trim($endDate);
        if ($startDate === '' || $endDate === '') {
            return [];
        }

        $startTs = strtotime($startDate);
        $endTs = strtotime($endDate);
        if ($startTs === false || $endTs === false || $endTs < $startTs) {
            return [];
        }

        $rows = [];
        for ($ts = $startTs; $ts <= $endTs; $ts = strtotime('+1 day', $ts)) {
            $rows[] = [
                'event_date' => date('Y-m-d', $ts),
                'seat_limit' => $seatLimit,
                'status' => $status,
            ];
        }

        return $rows;
    }
}

if (!function_exists('vs_event_replace_event_dates')) {
    function vs_event_replace_event_dates(PDO $pdo, int $eventId, array $dateRows, array $migrationByOldDate = []): array
    {
        $eventId = max($eventId, 0);
        if ($eventId <= 0) {
            return [];
        }

        $normalized = [];
        foreach ($dateRows as $row) {
            $eventDate = trim((string)($row['event_date'] ?? ''));
            if ($eventDate === '') {
                continue;
            }
            $ts = strtotime($eventDate);
            if ($ts === false) {
                continue;
            }
            $eventDate = date('Y-m-d', $ts);
            $seatLimitRaw = (string)($row['seat_limit'] ?? '');
            $seatLimit = null;
            if ($seatLimitRaw !== '') {
                $limit = (int)$seatLimitRaw;
                if ($limit > 0) {
                    $seatLimit = $limit;
                }
            }
            $status = trim((string)($row['status'] ?? 'Active'));
            if (!in_array($status, ['Active', 'Inactive'], true)) {
                $status = 'Active';
            }
            $normalized[$eventDate] = [
                'event_date' => $eventDate,
                'seat_limit' => $seatLimit,
                'status' => $status,
            ];
        }

        ksort($normalized);
        $rows = array_values($normalized);

        $normalizedMigrationMap = [];
        foreach ($migrationByOldDate as $oldDateRaw => $targetDateRaw) {
            $oldDate = trim((string)$oldDateRaw);
            $targetDate = trim((string)$targetDateRaw);
            if ($oldDate === '' || $targetDate === '') {
                continue;
            }
            $oldTs = strtotime($oldDate);
            $targetTs = strtotime($targetDate);
            if ($oldTs === false || $targetTs === false) {
                continue;
            }
            $oldDate = date('Y-m-d', $oldTs);
            $targetDate = date('Y-m-d', $targetTs);
            $normalizedMigrationMap[$oldDate] = $targetDate;
        }

        $oldDateStmt = $pdo->prepare("SELECT id, event_date FROM event_dates WHERE event_id = ?");
        $oldDateStmt->execute([$eventId]);
        $oldDates = $oldDateStmt->fetchAll(PDO::FETCH_ASSOC);
        $oldDateMap = [];
        foreach ($oldDates as $oldDateRow) {
            $oldDateMap[(int)$oldDateRow['id']] = (string)$oldDateRow['event_date'];
        }

        $pdo->prepare("DELETE FROM event_dates WHERE event_id = ?")->execute([$eventId]);
        if (!empty($rows)) {
            $insertStmt = $pdo->prepare("INSERT INTO event_dates (event_id, event_date, seat_limit, status) VALUES (?, ?, ?, ?)");
            foreach ($rows as $row) {
                $insertStmt->execute([$eventId, $row['event_date'], $row['seat_limit'], $row['status']]);
            }
        }

        if (!empty($oldDateMap)) {
            $newDateStmt = $pdo->prepare("SELECT id, event_date FROM event_dates WHERE event_id = ?");
            $newDateStmt->execute([$eventId]);
            $newDates = $newDateStmt->fetchAll(PDO::FETCH_ASSOC);
            $newDateMap = [];
            foreach ($newDates as $newDateRow) {
                $newDateMap[(string)$newDateRow['event_date']] = (int)$newDateRow['id'];
            }

            $updateRegStmt = $pdo->prepare("UPDATE event_registrations
                SET event_date_id = ?
                WHERE event_id = ?
                  AND event_date_id = ?");
            $updateWaitStmt = $pdo->prepare("UPDATE event_waitlist
                SET event_date_id = ?
                WHERE event_id = ?
                  AND event_date_id = ?");
            foreach ($oldDateMap as $oldId => $oldDate) {
                $replacementId = $newDateMap[$oldDate] ?? null;
                if ($replacementId === null && isset($normalizedMigrationMap[$oldDate])) {
                    $targetDate = $normalizedMigrationMap[$oldDate];
                    $replacementId = $newDateMap[$targetDate] ?? null;
                    if ($replacementId === null) {
                        throw new RuntimeException('Migration target date not found for old date ' . $oldDate . '.');
                    }
                }
                $updateRegStmt->execute([$replacementId, $eventId, $oldId]);
                $updateWaitStmt->execute([$replacementId, $eventId, $oldId]);
            }
        }

        return $rows;
    }
}

if (!function_exists('vs_event_sync_selected_event_date_values')) {
    function vs_event_sync_selected_event_date_values(PDO $pdo, int $eventId, string $eventType = ''): void
    {
        $eventId = max($eventId, 0);
        if ($eventId <= 0) {
            return;
        }

        $resolvedEventType = $eventType !== ''
            ? vs_event_normalize_event_type($eventType)
            : vs_event_get_event_type($pdo, $eventId, 'single_day');

        if ($resolvedEventType === 'date_range') {
            $rangeLabel = vs_event_get_event_date_display($pdo, $eventId, '', 'date_range');
            $updateStmt = $pdo->prepare("UPDATE event_registration_data rd
                INNER JOIN event_registrations r ON r.id = rd.registration_id
                SET rd.value = ?
                WHERE r.event_id = ?
                  AND rd.field_name = 'Selected Event Date'");
            $updateStmt->execute([$rangeLabel, $eventId]);
            return;
        }

        $updateStmt = $pdo->prepare("UPDATE event_registration_data rd
            INNER JOIN event_registrations r ON r.id = rd.registration_id
            INNER JOIN event_dates d ON d.id = r.event_date_id
            SET rd.value = d.event_date
            WHERE r.event_id = ?
              AND rd.field_name = 'Selected Event Date'");
        $updateStmt->execute([$eventId]);
    }
}

if (!function_exists('vs_event_get_primary_event_date')) {
    function vs_event_get_primary_event_date(PDO $pdo, int $eventId, string $fallback = ''): string
    {
        $stmt = $pdo->prepare("SELECT event_date
            FROM event_dates
            WHERE event_id = ?
            ORDER BY CASE WHEN status = 'Active' THEN 0 ELSE 1 END, event_date ASC, id ASC
            LIMIT 1");
        $stmt->execute([max($eventId, 0)]);
        $primaryDate = (string)$stmt->fetchColumn();
        if ($primaryDate !== '') {
            return $primaryDate;
        }
        return $fallback;
    }
}

if (!function_exists('vs_event_normalize_event_type')) {
    function vs_event_normalize_event_type(string $eventType): string
    {
        $eventType = trim($eventType);
        if (!in_array($eventType, ['single_day', 'multi_select_dates', 'date_range'], true)) {
            return 'single_day';
        }
        return $eventType;
    }
}

if (!function_exists('vs_event_is_package_paid')) {
    function vs_event_is_package_paid(array $package): bool
    {
        if (array_key_exists('is_paid', $package)) {
            return ((int)$package['is_paid']) === 1;
        }

        $priceTotal = (float)($package['price_total'] ?? $package['price'] ?? 0);
        $advanceAmount = (float)($package['advance_amount'] ?? 0);
        return ($priceTotal > 0 || $advanceAmount > 0);
    }
}

if (!function_exists('vs_event_normalize_payment_methods')) {
    function vs_event_normalize_payment_methods($methods, bool $isPaid = true): array
    {
        if (!$isPaid) {
            return [];
        }

        $allowed = ['razorpay', 'upi', 'cash'];
        $raw = [];
        if (is_array($methods)) {
            $raw = $methods;
        } else {
            $raw = preg_split('/[\s,]+/', strtolower(trim((string)$methods))) ?: [];
        }

        $normalized = [];
        foreach ($raw as $method) {
            $method = strtolower(trim((string)$method));
            if ($method === '' || !in_array($method, $allowed, true)) {
                continue;
            }
            if (!in_array($method, $normalized, true)) {
                $normalized[] = $method;
            }
        }

        if (empty($normalized)) {
            return ['razorpay', 'upi'];
        }

        return $normalized;
    }
}

if (!function_exists('vs_event_payment_methods_to_csv')) {
    function vs_event_payment_methods_to_csv(array $methods, bool $isPaid = true): string
    {
        $normalized = vs_event_normalize_payment_methods($methods, $isPaid);
        if (empty($normalized)) {
            return '';
        }
        return implode(',', $normalized);
    }
}

if (!function_exists('vs_event_payment_methods_from_csv')) {
    function vs_event_payment_methods_from_csv(?string $methodsCsv, bool $isPaid = true): array
    {
        $methodsCsv = (string)($methodsCsv ?? '');
        return vs_event_normalize_payment_methods($methodsCsv, $isPaid);
    }
}

if (!function_exists('vs_event_get_event_type')) {
    function vs_event_get_event_type(PDO $pdo, int $eventId, string $fallback = 'single_day'): string
    {
        static $cache = [];

        $eventId = max($eventId, 0);
        if ($eventId <= 0) {
            return vs_event_normalize_event_type($fallback);
        }

        if (isset($cache[$eventId])) {
            return $cache[$eventId];
        }

        $stmt = $pdo->prepare("SELECT event_type FROM events WHERE id = ? LIMIT 1");
        $stmt->execute([$eventId]);
        $eventType = vs_event_normalize_event_type((string)$stmt->fetchColumn());
        $cache[$eventId] = $eventType;

        return $eventType;
    }
}

if (!function_exists('vs_event_get_event_dates_cached')) {
    function vs_event_get_event_dates_cached(PDO $pdo, int $eventId, bool $onlyActive = true): array
    {
        static $cache = [];

        $eventId = max($eventId, 0);
        if ($eventId <= 0) {
            return [];
        }

        $cacheKey = $eventId . ':' . ($onlyActive ? '1' : '0');
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $cache[$cacheKey] = vs_event_fetch_event_dates($pdo, $eventId, $onlyActive);
        return $cache[$cacheKey];
    }
}

if (!function_exists('vs_event_build_range_label')) {
    function vs_event_build_range_label(array $dateRows, string $fallback = ''): string
    {
        if (empty($dateRows)) {
            return $fallback;
        }

        $rangeStart = (string)($dateRows[0]['event_date'] ?? '');
        $rangeEnd = (string)($dateRows[count($dateRows) - 1]['event_date'] ?? '');
        if ($rangeStart === '' && $rangeEnd === '') {
            return $fallback;
        }
        if ($rangeStart === '') {
            return $rangeEnd;
        }
        if ($rangeEnd === '') {
            return $rangeStart;
        }

        return ($rangeStart === $rangeEnd) ? $rangeStart : ($rangeStart . ' to ' . $rangeEnd);
    }
}

if (!function_exists('vs_event_get_event_date_display')) {
    function vs_event_get_event_date_display(PDO $pdo, int $eventId, string $fallback = '', ?string $eventType = null): string
    {
        $eventId = max($eventId, 0);
        if ($eventId <= 0) {
            return $fallback;
        }

        $resolvedType = vs_event_normalize_event_type($eventType ?? vs_event_get_event_type($pdo, $eventId, 'single_day'));
        $dateRows = vs_event_get_event_dates_cached($pdo, $eventId, true);

        if ($resolvedType === 'date_range') {
            $label = vs_event_build_range_label($dateRows, $fallback);
            return $label !== '' ? $label : $fallback;
        }

        if ($resolvedType === 'multi_select_dates') {
            $labels = [];
            foreach ($dateRows as $dateRow) {
                $dateVal = trim((string)($dateRow['event_date'] ?? ''));
                if ($dateVal !== '') {
                    $labels[] = $dateVal;
                }
            }
            if (!empty($labels)) {
                return implode(', ', $labels);
            }
            return $fallback;
        }

        if (!empty($dateRows)) {
            return (string)$dateRows[0]['event_date'];
        }

        return $fallback;
    }
}

if (!function_exists('vs_event_get_registration_date_display')) {
    function vs_event_get_registration_date_display(PDO $pdo, array $row, string $fallback = ''): string
    {
        $eventId = max((int)($row['event_id'] ?? 0), 0);
        if ($eventId <= 0) {
            return $fallback;
        }

        $eventType = vs_event_normalize_event_type((string)($row['event_type'] ?? vs_event_get_event_type($pdo, $eventId, 'single_day')));
        if ($eventType === 'date_range') {
            return vs_event_get_event_date_display($pdo, $eventId, $fallback, 'date_range');
        }

        if ($eventType === 'single_day') {
            return vs_event_get_event_date_display($pdo, $eventId, $fallback, 'single_day');
        }

        $selectedDate = trim((string)($row['selected_event_date'] ?? $row['event_date'] ?? ''));
        if ($selectedDate !== '') {
            return $selectedDate;
        }

        $eventDateId = max((int)($row['event_date_id'] ?? 0), 0);
        if ($eventDateId > 0) {
            $dateRows = vs_event_get_event_dates_cached($pdo, $eventId, false);
            foreach ($dateRows as $dateRow) {
                if ((int)($dateRow['id'] ?? 0) === $eventDateId) {
                    return (string)$dateRow['event_date'];
                }
            }
        }

        return vs_event_get_event_date_display($pdo, $eventId, $fallback, 'multi_select_dates');
    }
}

if (!function_exists('vs_event_calculate_payment_plan')) {
    function vs_event_calculate_payment_plan(array $package, int $persons, string $choice = ''): array
    {
        $persons = max($persons, 1);
        $paymentMode = trim((string)($package['payment_mode'] ?? 'full'));
        if (!in_array($paymentMode, ['full', 'advance', 'optional'], true)) {
            $paymentMode = 'full';
        }

        $priceTotal = (float)($package['price_total'] ?? 0);
        if ($priceTotal <= 0) {
            $priceTotal = (float)($package['price'] ?? 0);
        }
        if ($priceTotal < 0) {
            $priceTotal = 0;
        }

        $advanceAmount = (float)($package['advance_amount'] ?? 0);
        if ($advanceAmount < 0) {
            $advanceAmount = 0;
        }

        $totalAmount = round($priceTotal * $persons, 2);
        $advanceTotal = round($advanceAmount * $persons, 2);
        if ($advanceTotal > $totalAmount) {
            $advanceTotal = $totalAmount;
        }

        $effectiveChoice = $choice;
        if ($paymentMode === 'full') {
            $effectiveChoice = 'full';
        } elseif ($paymentMode === 'advance') {
            $effectiveChoice = 'advance';
        } elseif ($effectiveChoice !== 'advance') {
            $effectiveChoice = 'full';
        }

        $dueNow = $totalAmount;
        $paymentType = 'full';
        if ($effectiveChoice === 'advance' && $advanceTotal > 0 && $advanceTotal < $totalAmount) {
            $dueNow = $advanceTotal;
            $paymentType = 'advance';
        }

        $remainingAfterDue = round(max($totalAmount - $dueNow, 0), 2);

        return [
            'payment_mode' => $paymentMode,
            'payment_choice' => $effectiveChoice,
            'payment_type' => $paymentType,
            'price_total' => $priceTotal,
            'advance_amount' => $advanceAmount,
            'total_amount' => $totalAmount,
            'due_now' => $dueNow,
            'remaining_after_due' => $remainingAfterDue,
            'advance_total' => $advanceTotal,
        ];
    }
}

if (!function_exists('vs_event_build_registration_payment_context')) {
    function vs_event_build_registration_payment_context(array $registration, array $package, ?array $paymentRow = null, string $choice = ''): array
    {
        $persons = max((int)($registration['persons'] ?? 1), 1);
        $basePlan = vs_event_calculate_payment_plan($package, $persons, $choice);
        $totalAmount = (float)$basePlan['total_amount'];

        $paymentStatus = strtolower((string)($registration['payment_status'] ?? ''));
        $recordedPaid = $paymentRow ? (float)($paymentRow['amount_paid'] ?? 0) : 0.0;
        if ($paymentStatus === 'paid') {
            $recordedPaid = max($recordedPaid, $totalAmount);
        }

        $recordedRemaining = $paymentRow ? (float)($paymentRow['remaining_amount'] ?? 0) : 0.0;
        $remainingBefore = $recordedRemaining > 0 ? $recordedRemaining : max($totalAmount - $recordedPaid, 0);
        $remainingBefore = round($remainingBefore, 2);
        $recordedPaid = round($recordedPaid, 2);

        $dueNow = (float)$basePlan['due_now'];
        $paymentType = (string)$basePlan['payment_type'];
        if ($paymentStatus === 'partial paid' && $remainingBefore > 0) {
            $dueNow = $remainingBefore;
            $paymentType = 'remaining';
        } elseif ($paymentStatus === 'paid') {
            $dueNow = 0.0;
            $paymentType = 'full';
        }

        if ($dueNow > $remainingBefore && $remainingBefore > 0) {
            $dueNow = $remainingBefore;
        }
        $dueNow = round(max($dueNow, 0), 2);

        return [
            'payment_mode' => $basePlan['payment_mode'],
            'payment_choice' => $basePlan['payment_choice'],
            'payment_type' => $paymentType,
            'price_total' => $basePlan['price_total'],
            'advance_amount' => $basePlan['advance_amount'],
            'total_amount' => $totalAmount,
            'amount_paid' => $recordedPaid,
            'remaining_before_payment' => $remainingBefore,
            'due_now' => $dueNow,
        ];
    }
}

if (!function_exists('vs_event_normalize_phone')) {
    function vs_event_normalize_phone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        return $digits;
    }
}

if (!function_exists('vs_event_phone_last10')) {
    function vs_event_phone_last10(string $phone): string
    {
        $normalized = vs_event_normalize_phone($phone);
        if ($normalized === '') {
            return '';
        }

        return substr($normalized, -10);
    }
}

if (!function_exists('vs_event_resolve_refund_amount')) {
    function vs_event_resolve_refund_amount(array $row): float
    {
        $refundAmount = round(max((float)($row['refund_amount'] ?? 0), 0), 2);

        $paymentStatus = strtolower(trim((string)($row['payment_status'] ?? '')));
        $verificationStatus = strtolower(trim((string)($row['verification_status'] ?? '')));
        $isCancelledBooking = ($paymentStatus === 'cancelled' || $verificationStatus === 'cancelled');
        if (!$isCancelledBooking) {
            return $refundAmount;
        }

        $paidAmount = (float)($row['paid_amount'] ?? ($row['amount_paid'] ?? 0));
        if ($paidAmount <= 0) {
            $paidAmount = (float)($row['payment_amount'] ?? 0);
        }
        if ($paidAmount < 0) {
            $paidAmount = 0;
        }

        return round($paidAmount, 2);
    }
}

if (!function_exists('vs_event_create_waitlisted_registration')) {
    function vs_event_create_waitlisted_registration(
        PDO $pdo,
        int $eventId,
        int $packageId,
        int $eventDateId,
        string $name,
        string $phone,
        int $persons = 1,
        array $dynamicValues = []
    ): array
    {
        $eventId = max($eventId, 0);
        $packageId = max($packageId, 0);
        $eventDateId = max($eventDateId, 0);
        $persons = max($persons, 1);
        $name = trim($name);
        $phone = trim($phone);

        if ($eventId <= 0 || $packageId <= 0) {
            throw new RuntimeException('Invalid event/package selected for waitlist.');
        }
        if ($name === '' || $phone === '') {
            throw new RuntimeException('Name and phone are required for waitlist.');
        }

        try {
            $pdo->beginTransaction();

            $packageStmt = $pdo->prepare("SELECT
                    p.*,
                    e.title AS event_title,
                    e.event_type,
                    e.event_date,
                    e.status AS event_status
                FROM event_packages p
                INNER JOIN events e ON e.id = p.event_id
                WHERE p.id = ?
                  AND p.event_id = ?
                LIMIT 1
                FOR UPDATE");
            $packageStmt->execute([$packageId, $eventId]);
            $packageRow = $packageStmt->fetch(PDO::FETCH_ASSOC);
            if (!$packageRow || (string)($packageRow['status'] ?? '') !== 'Active') {
                throw new RuntimeException('Selected package is not active.');
            }
            if ((string)($packageRow['event_status'] ?? '') !== 'Active') {
                throw new RuntimeException('Event is currently closed.');
            }

            $eventType = vs_event_normalize_event_type((string)($packageRow['event_type'] ?? 'single_day'));
            $dateRow = null;
            if ($eventType === 'single_day') {
                $singleDateStmt = $pdo->prepare("SELECT id, event_date, seat_limit, status
                    FROM event_dates
                    WHERE event_id = ?
                      AND status = 'Active'
                    ORDER BY event_date ASC, id ASC
                    LIMIT 1
                    FOR UPDATE");
                $singleDateStmt->execute([$eventId]);
                $dateRow = $singleDateStmt->fetch(PDO::FETCH_ASSOC);
                if (!$dateRow) {
                    throw new RuntimeException('Configured event date is not available.');
                }
                $eventDateId = (int)($dateRow['id'] ?? 0);
            } elseif ($eventType === 'multi_select_dates') {
                if ($eventDateId <= 0) {
                    throw new RuntimeException('Please select a valid event date.');
                }
                $dateStmt = $pdo->prepare("SELECT id, event_date, seat_limit, status
                    FROM event_dates
                    WHERE id = ?
                      AND event_id = ?
                    LIMIT 1
                    FOR UPDATE");
                $dateStmt->execute([$eventDateId, $eventId]);
                $dateRow = $dateStmt->fetch(PDO::FETCH_ASSOC);
                if (!$dateRow || (string)($dateRow['status'] ?? '') !== 'Active') {
                    throw new RuntimeException('Selected event date is not active.');
                }
            } else {
                $eventDateId = 0;
            }

            $duplicateStmt = $pdo->prepare("SELECT id
                FROM event_registrations
                WHERE package_id = ?
                  AND phone = ?
                  AND (? = 0 OR COALESCE(event_date_id, 0) = ?)
                  AND payment_status NOT IN ('Failed', 'Cancelled')
                LIMIT 1
                FOR UPDATE");
            $duplicateStmt->execute([$packageId, $phone, $eventDateId, $eventDateId]);
            if ($duplicateStmt->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('This phone already has an active booking/waitlist for selected package.');
            }

            $packageSeatLimit = (int)($packageRow['seat_limit'] ?? 0);
            $packageAvailable = null;
            if ($packageSeatLimit > 0) {
                $pkgUsedStmt = $pdo->prepare("SELECT COALESCE(SUM(persons), 0)
                    FROM event_registrations
                    WHERE package_id = ?
                      AND (? = 0 OR event_date_id = ?)
                      AND verification_status IN ('Pending', 'Approved', 'Auto Verified')
                      AND payment_status NOT IN ('Failed', 'Cancelled')
                    FOR UPDATE");
                $pkgUsedStmt->execute([$packageId, $eventDateId, $eventDateId]);
                $packageUsed = (int)$pkgUsedStmt->fetchColumn();
                $packageAvailable = max($packageSeatLimit - $packageUsed, 0);
            }

            $dateAvailable = null;
            if ($dateRow && (int)($dateRow['seat_limit'] ?? 0) > 0) {
                $dateSeatLimit = (int)$dateRow['seat_limit'];
                $dateUsedStmt = $pdo->prepare("SELECT COALESCE(SUM(persons), 0)
                    FROM event_registrations
                    WHERE event_id = ?
                      AND event_date_id = ?
                      AND verification_status IN ('Pending', 'Approved', 'Auto Verified')
                      AND payment_status NOT IN ('Failed', 'Cancelled')
                    FOR UPDATE");
                $dateUsedStmt->execute([$eventId, $eventDateId]);
                $dateUsed = (int)$dateUsedStmt->fetchColumn();
                $dateAvailable = max($dateSeatLimit - $dateUsed, 0);
            }

            $availableSeats = null;
            if ($packageAvailable !== null && $dateAvailable !== null) {
                $availableSeats = min($packageAvailable, $dateAvailable);
            } elseif ($packageAvailable !== null) {
                $availableSeats = $packageAvailable;
            } elseif ($dateAvailable !== null) {
                $availableSeats = $dateAvailable;
            }

            if ($availableSeats === null) {
                throw new RuntimeException('Seats are available. Please proceed with registration.');
            }
            if ($availableSeats >= $persons) {
                throw new RuntimeException('Seats are available for this booking. Please proceed to payment.');
            }

            $isPaidPackage = ((int)($packageRow['is_paid'] ?? 1) === 1);
            $allowedMethods = vs_event_payment_methods_from_csv((string)($packageRow['payment_methods'] ?? ''), $isPaidPackage);
            $snapshotUpiId = null;
            $snapshotUpiQr = null;
            if ($isPaidPackage && in_array('upi', $allowedMethods, true)) {
                $tmpUpiId = trim((string)($packageRow['upi_id'] ?? ''));
                $tmpUpiQr = trim((string)($packageRow['upi_qr_image'] ?? ''));
                $snapshotUpiId = $tmpUpiId !== '' ? $tmpUpiId : null;
                $snapshotUpiQr = $tmpUpiQr !== '' ? $tmpUpiQr : null;
            }

            $insertStmt = $pdo->prepare("INSERT INTO event_registrations
                (event_id, package_id, event_date_id, package_upi_id_snapshot, package_upi_qr_snapshot, name, phone, persons, payment_status, verification_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Waitlisted', 'Waitlisted')");
            $insertStmt->execute([
                $eventId,
                $packageId,
                $eventDateId > 0 ? $eventDateId : null,
                $snapshotUpiId,
                $snapshotUpiQr,
                $name,
                $phone,
                $persons,
            ]);
            $registrationId = (int)$pdo->lastInsertId();
            $bookingReference = vs_event_assign_waitlist_booking_reference($pdo, $registrationId);

            $insertDataStmt = $pdo->prepare("INSERT INTO event_registration_data (registration_id, field_name, value) VALUES (?, ?, ?)");
            $insertDataStmt->execute([$registrationId, 'Name', $name]);
            $insertDataStmt->execute([$registrationId, 'Phone', $phone]);
            $insertDataStmt->execute([$registrationId, 'Persons', (string)$persons]);
            $insertDataStmt->execute([$registrationId, 'Booking Reference', $bookingReference]);
            $insertDataStmt->execute([$registrationId, 'Source', 'Waitlist']);
            if ($dateRow) {
                $insertDataStmt->execute([$registrationId, 'Selected Event Date', (string)($dateRow['event_date'] ?? '')]);
            } elseif ($eventType === 'date_range') {
                $rangeLabel = vs_event_get_event_date_display($pdo, $eventId, (string)($packageRow['event_date'] ?? ''), 'date_range');
                $insertDataStmt->execute([$registrationId, 'Selected Event Date', $rangeLabel]);
            }
            foreach ($dynamicValues as $dynamicValue) {
                $fieldName = trim((string)($dynamicValue['field_name'] ?? ''));
                if ($fieldName === '') {
                    continue;
                }
                $insertDataStmt->execute([$registrationId, $fieldName, (string)($dynamicValue['value'] ?? '')]);
            }

            $pdo->commit();

            return [
                'registration_id' => $registrationId,
                'booking_reference' => $bookingReference,
                'waitlist_position' => vs_event_get_waitlist_position($pdo, $registrationId),
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('Unable to join waitlist right now.');
        }
    }
}

if (!function_exists('vs_event_confirm_waitlisted_registration')) {
    function vs_event_confirm_waitlisted_registration(
        PDO $pdo,
        int $registrationId,
        bool $useExistingTransaction = false
    ): array
    {
        $registrationId = max($registrationId, 0);
        if ($registrationId <= 0) {
            throw new RuntimeException('Invalid waitlisted booking selected.');
        }

        try {
            if (!$useExistingTransaction) {
                $pdo->beginTransaction();
            }

            $stmt = $pdo->prepare("SELECT
                    r.id,
                    r.event_id,
                    r.event_date_id,
                    r.package_id,
                    r.booking_reference,
                    r.name,
                    r.phone,
                    r.persons,
                    r.payment_status,
                    r.verification_status,
                    r.package_upi_id_snapshot,
                    r.package_upi_qr_snapshot,
                    p.id AS package_row_id,
                    p.status AS package_status,
                    p.is_paid,
                    p.payment_methods,
                    p.upi_id,
                    p.upi_qr_image,
                    p.seat_limit AS package_seat_limit,
                    p.waitlist_confirmation_mode,
                    e.status AS event_status,
                    e.event_type
                FROM event_registrations r
                INNER JOIN event_packages p ON p.id = r.package_id
                INNER JOIN events e ON e.id = r.event_id
                WHERE r.id = ?
                LIMIT 1
                FOR UPDATE");
            $stmt->execute([$registrationId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('Waitlisted booking not found.');
            }
            if (!vs_event_is_waitlisted_registration($row)) {
                throw new RuntimeException('This booking is not in waitlist state.');
            }
            if ((string)($row['package_status'] ?? '') !== 'Active') {
                throw new RuntimeException('Package is not active for waitlist confirmation.');
            }
            if ((string)($row['event_status'] ?? '') !== 'Active') {
                throw new RuntimeException('Event is closed for waitlist confirmation.');
            }

            $eventId = (int)($row['event_id'] ?? 0);
            $packageId = (int)($row['package_id'] ?? 0);
            $eventDateId = (int)($row['event_date_id'] ?? 0);
            $persons = max((int)($row['persons'] ?? 1), 1);

            $dateRow = null;
            if ($eventDateId > 0) {
                $dateStmt = $pdo->prepare("SELECT id, seat_limit, status
                    FROM event_dates
                    WHERE id = ?
                      AND event_id = ?
                    LIMIT 1
                    FOR UPDATE");
                $dateStmt->execute([$eventDateId, $eventId]);
                $dateRow = $dateStmt->fetch(PDO::FETCH_ASSOC);
                if (!$dateRow || (string)($dateRow['status'] ?? '') !== 'Active') {
                    throw new RuntimeException('Selected waitlist date is not active.');
                }
            }

            $packageSeatLimit = (int)($row['package_seat_limit'] ?? 0);
            $packageAvailable = null;
            if ($packageSeatLimit > 0) {
                $pkgUsedStmt = $pdo->prepare("SELECT COALESCE(SUM(persons), 0)
                    FROM event_registrations
                    WHERE package_id = ?
                      AND (? = 0 OR event_date_id = ?)
                      AND id <> ?
                      AND verification_status IN ('Pending', 'Approved', 'Auto Verified')
                      AND payment_status NOT IN ('Failed', 'Cancelled')
                    FOR UPDATE");
                $pkgUsedStmt->execute([$packageId, $eventDateId, $eventDateId, $registrationId]);
                $packageUsed = (int)$pkgUsedStmt->fetchColumn();
                $packageAvailable = max($packageSeatLimit - $packageUsed, 0);
            }

            $dateAvailable = null;
            if ($dateRow && (int)($dateRow['seat_limit'] ?? 0) > 0) {
                $dateSeatLimit = (int)$dateRow['seat_limit'];
                $dateUsedStmt = $pdo->prepare("SELECT COALESCE(SUM(persons), 0)
                    FROM event_registrations
                    WHERE event_id = ?
                      AND event_date_id = ?
                      AND id <> ?
                      AND verification_status IN ('Pending', 'Approved', 'Auto Verified')
                      AND payment_status NOT IN ('Failed', 'Cancelled')
                    FOR UPDATE");
                $dateUsedStmt->execute([$eventId, $eventDateId, $registrationId]);
                $dateUsed = (int)$dateUsedStmt->fetchColumn();
                $dateAvailable = max($dateSeatLimit - $dateUsed, 0);
            }

            $availableSeats = null;
            if ($packageAvailable !== null && $dateAvailable !== null) {
                $availableSeats = min($packageAvailable, $dateAvailable);
            } elseif ($packageAvailable !== null) {
                $availableSeats = $packageAvailable;
            } elseif ($dateAvailable !== null) {
                $availableSeats = $dateAvailable;
            }
            if ($availableSeats !== null && $availableSeats < $persons) {
                throw new RuntimeException('Not enough seats available to confirm this waitlisted booking.');
            }

            $isPaidPackage = ((int)($row['is_paid'] ?? 1) === 1);
            $paymentStatus = $isPaidPackage ? 'Unpaid' : 'Paid';
            $verificationStatus = $isPaidPackage ? 'Pending' : 'Auto Verified';

            $allowedMethods = vs_event_payment_methods_from_csv((string)($row['payment_methods'] ?? ''), $isPaidPackage);
            $snapshotUpiId = trim((string)($row['package_upi_id_snapshot'] ?? ''));
            $snapshotUpiQr = trim((string)($row['package_upi_qr_snapshot'] ?? ''));
            if ($isPaidPackage && in_array('upi', $allowedMethods, true)) {
                if ($snapshotUpiId === '') {
                    $snapshotUpiId = trim((string)($row['upi_id'] ?? ''));
                }
                if ($snapshotUpiQr === '') {
                    $snapshotUpiQr = trim((string)($row['upi_qr_image'] ?? ''));
                }
            } else {
                $snapshotUpiId = '';
                $snapshotUpiQr = '';
            }

            $updateStmt = $pdo->prepare("UPDATE event_registrations
                SET payment_status = ?, verification_status = ?, package_upi_id_snapshot = ?, package_upi_qr_snapshot = ?
                WHERE id = ?");
            $updateStmt->execute([
                $paymentStatus,
                $verificationStatus,
                $snapshotUpiId !== '' ? $snapshotUpiId : null,
                $snapshotUpiQr !== '' ? $snapshotUpiQr : null,
                $registrationId,
            ]);

            $sourceUpdateStmt = $pdo->prepare("UPDATE event_registration_data
                SET value = 'Waitlist Confirmed'
                WHERE registration_id = ?
                  AND field_name = 'Source'");
            $sourceUpdateStmt->execute([$registrationId]);
            if ($sourceUpdateStmt->rowCount() <= 0) {
                $insertSourceStmt = $pdo->prepare("INSERT INTO event_registration_data (registration_id, field_name, value) VALUES (?, 'Source', 'Waitlist Confirmed')");
                $insertSourceStmt->execute([$registrationId]);
            }

            if (!$isPaidPackage) {
                $freePaymentStmt = $pdo->prepare("INSERT INTO event_payments
                    (registration_id, amount, payment_type, amount_paid, remaining_amount, payment_method, transaction_id, screenshot, status)
                    VALUES (?, 0, 'full', 0, 0, 'Free', 'FREE', '', 'Paid')
                    ON DUPLICATE KEY UPDATE
                        amount = VALUES(amount),
                        payment_type = VALUES(payment_type),
                        amount_paid = VALUES(amount_paid),
                        remaining_amount = VALUES(remaining_amount),
                        payment_method = VALUES(payment_method),
                        transaction_id = VALUES(transaction_id),
                        screenshot = VALUES(screenshot),
                        status = VALUES(status)");
                $freePaymentStmt->execute([$registrationId]);
            } else {
                $pdo->prepare("DELETE FROM event_payments WHERE registration_id = ? AND status = 'Created'")->execute([$registrationId]);
            }

            if (!$useExistingTransaction) {
                $pdo->commit();
            }

            return [
                'registration_id' => $registrationId,
                'booking_reference' => (string)($row['booking_reference'] ?? ''),
                'payment_status' => $paymentStatus,
                'verification_status' => $verificationStatus,
            ];
        } catch (Throwable $e) {
            if (!$useExistingTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('Unable to confirm waitlisted booking right now.');
        }
    }
}

if (!function_exists('vs_event_promote_waitlisted_registrations')) {
    function vs_event_promote_waitlisted_registrations(
        PDO $pdo,
        int $eventId,
        int $packageId,
        int $eventDateId = 0,
        int $availableSeats = 0,
        bool $useExistingTransaction = false
    ): array
    {
        $eventId = max($eventId, 0);
        $packageId = max($packageId, 0);
        $eventDateId = max($eventDateId, 0);
        $availableSeats = max($availableSeats, 0);

        if ($eventId <= 0 || $packageId <= 0 || $availableSeats <= 0) {
            return ['mode' => 'manual', 'promoted_count' => 0, 'promoted_registration_ids' => []];
        }

        try {
            if (!$useExistingTransaction) {
                $pdo->beginTransaction();
            }

            $packageStmt = $pdo->prepare("SELECT waitlist_confirmation_mode
                FROM event_packages
                WHERE id = ?
                  AND event_id = ?
                LIMIT 1
                FOR UPDATE");
            $packageStmt->execute([$packageId, $eventId]);
            $packageRow = $packageStmt->fetch(PDO::FETCH_ASSOC);
            if (!$packageRow) {
                if (!$useExistingTransaction) {
                    $pdo->commit();
                }
                return ['mode' => 'manual', 'promoted_count' => 0, 'promoted_registration_ids' => []];
            }

            $mode = strtolower(trim((string)($packageRow['waitlist_confirmation_mode'] ?? 'auto')));
            if (!in_array($mode, ['auto', 'manual'], true)) {
                $mode = 'auto';
            }
            if ($mode !== 'auto') {
                if (!$useExistingTransaction) {
                    $pdo->commit();
                }
                return ['mode' => 'manual', 'promoted_count' => 0, 'promoted_registration_ids' => []];
            }

            $waitStmt = $pdo->prepare("SELECT id, persons
                FROM event_registrations
                WHERE event_id = ?
                  AND package_id = ?
                  AND (? = 0 OR COALESCE(event_date_id, 0) = ?)
                  AND (payment_status = 'Waitlisted' OR verification_status = 'Waitlisted')
                ORDER BY created_at ASC, id ASC
                FOR UPDATE");
            $waitStmt->execute([$eventId, $packageId, $eventDateId, $eventDateId]);
            $waitRows = $waitStmt->fetchAll(PDO::FETCH_ASSOC);

            $promotedIds = [];
            foreach ($waitRows as $waitRow) {
                $waitRegistrationId = (int)($waitRow['id'] ?? 0);
                $waitPersons = max((int)($waitRow['persons'] ?? 1), 1);
                if ($waitRegistrationId <= 0) {
                    continue;
                }
                if ($availableSeats < $waitPersons) {
                    break;
                }

                vs_event_confirm_waitlisted_registration($pdo, $waitRegistrationId, true);
                $availableSeats -= $waitPersons;
                $promotedIds[] = $waitRegistrationId;
            }

            if (!$useExistingTransaction) {
                $pdo->commit();
            }

            return [
                'mode' => 'auto',
                'promoted_count' => count($promotedIds),
                'promoted_registration_ids' => $promotedIds,
            ];
        } catch (Throwable $e) {
            if (!$useExistingTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('Unable to promote waitlisted bookings right now.');
        }
    }
}

if (!function_exists('vs_event_cancel_registration')) {
    function vs_event_cancel_registration(
        PDO $pdo,
        int $registrationId,
        int $cancelPersons = 0,
        string $cancelReason = '',
        bool $adminOverride = false,
        string $forcedRefundStatus = '',
        bool $useExistingTransaction = false
    ): array
    {
        $registrationId = max($registrationId, 0);
        if ($registrationId <= 0) {
            throw new RuntimeException('Invalid registration selected.');
        }

        $cancelReason = trim($cancelReason);
        if ($cancelReason === '') {
            $cancelReason = 'Cancelled by user';
        }

        try {
            if (!$useExistingTransaction) {
                $pdo->beginTransaction();
            }

            $stmt = $pdo->prepare("SELECT
                    r.id,
                    r.event_id,
                    r.event_date_id,
                    r.package_id,
                    r.booking_reference,
                    r.name,
                    r.phone,
                    r.persons,
                    r.payment_status,
                    r.verification_status,
                    r.checkin_status,
                    p.package_name,
                    p.cancellation_allowed,
                    p.refund_allowed,
                    COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS package_price_total,
                    ep.id AS payment_row_id,
                    COALESCE(ep.amount, 0) AS payment_amount,
                    COALESCE(ep.amount_paid, 0) AS amount_paid,
                    COALESCE(ep.remaining_amount, 0) AS remaining_amount,
                    ep.status AS payment_record_status
                FROM event_registrations r
                INNER JOIN event_packages p ON p.id = r.package_id
                LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
                LEFT JOIN event_payments ep ON ep.registration_id = r.id
                WHERE r.id = ?
                LIMIT 1
                FOR UPDATE");
            $stmt->execute([$registrationId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('Booking not found.');
            }

            $bookingReference = trim((string)($row['booking_reference'] ?? ''));
            if ($bookingReference === '') {
                $bookingReference = vs_event_assign_booking_reference($pdo, $registrationId);
                $row['booking_reference'] = $bookingReference;
            }

            if (!$adminOverride && (int)($row['cancellation_allowed'] ?? 1) !== 1) {
                throw new RuntimeException('Cancellation is not allowed for this package.');
            }
            if ((int)($row['checkin_status'] ?? 0) === 1) {
                throw new RuntimeException('Checked-in booking cannot be cancelled.');
            }

            $paymentStatus = strtolower(trim((string)($row['payment_status'] ?? '')));
            if ($paymentStatus === 'cancelled') {
                throw new RuntimeException('Booking is already cancelled.');
            }

            $currentPersons = max((int)($row['persons'] ?? 1), 1);
            if ($cancelPersons <= 0) {
                $cancelPersons = $currentPersons;
            }
            if ($cancelPersons > $currentPersons) {
                throw new RuntimeException('Cancellation quantity cannot be greater than booked persons.');
            }

            $isFullCancellation = ($cancelPersons >= $currentPersons);
            $cancellationType = $isFullCancellation ? 'full' : 'partial';
            $remainingPersons = $isFullCancellation ? 0 : ($currentPersons - $cancelPersons);

            $pricePerPerson = (float)($row['package_price_total'] ?? 0);
            if ($pricePerPerson < 0) {
                $pricePerPerson = 0;
            }
            $totalBefore = round($pricePerPerson * $currentPersons, 2);
            $amountPaid = (float)($row['amount_paid'] ?? 0);
            if ($amountPaid <= 0 && $paymentStatus === 'paid') {
                $amountPaid = $totalBefore;
            }
            if ($amountPaid <= 0) {
                $amountPaid = (float)($row['payment_amount'] ?? 0);
            }
            if ($amountPaid < 0) {
                $amountPaid = 0;
            }

            $perPersonPaid = $currentPersons > 0 ? ($amountPaid / $currentPersons) : 0.0;
            $forcedRefundStatus = strtolower(trim($forcedRefundStatus));
            if (!in_array($forcedRefundStatus, ['', 'pending', 'processed', 'rejected'], true)) {
                $forcedRefundStatus = '';
            }
            if ($adminOverride && $forcedRefundStatus === '') {
                $forcedRefundStatus = 'processed';
            }

            $refundAllowed = ((int)($row['refund_allowed'] ?? 1) === 1) || $adminOverride;
            if ($isFullCancellation) {
                $refundAmount = round(max($amountPaid, 0), 2);
            } else {
                $refundAmount = $refundAllowed ? round(min($amountPaid, $perPersonPaid * $cancelPersons), 2) : 0.0;
            }
            $refundStatus = ($refundAllowed && $refundAmount > 0) ? 'pending' : 'rejected';
            if ($forcedRefundStatus !== '') {
                $refundStatus = $forcedRefundStatus;
            }

            if ($isFullCancellation) {
                $pdo->prepare("UPDATE event_registrations
                    SET payment_status = 'Cancelled', verification_status = 'Cancelled'
                    WHERE id = ?")
                    ->execute([$registrationId]);

                $paymentRowId = (int)($row['payment_row_id'] ?? 0);
                if ($paymentRowId > 0) {
                    $pdo->prepare("UPDATE event_payments
                        SET remaining_amount = 0, status = 'Cancelled'
                        WHERE registration_id = ?")
                        ->execute([$registrationId]);
                }
            } else {
                $newPersons = $remainingPersons;
                $newTotal = round($pricePerPerson * $newPersons, 2);
                $newAmountPaid = round(max($amountPaid - $refundAmount, 0), 2);
                if ($newAmountPaid > $newTotal) {
                    $newAmountPaid = $newTotal;
                }
                $newRemaining = round(max($newTotal - $newAmountPaid, 0), 2);
                $oldPaymentStatus = strtolower(trim((string)($row['payment_status'] ?? '')));

                $newPaymentStatus = 'Unpaid';
                if ($newAmountPaid > 0 && $newRemaining > 0) {
                    $newPaymentStatus = 'Partial Paid';
                } elseif ($newAmountPaid > 0 && $newRemaining <= 0) {
                    $newPaymentStatus = 'Paid';
                }
                if (in_array($oldPaymentStatus, ['pending', 'pending verification'], true)) {
                    $newPaymentStatus = 'Pending Verification';
                }

                $newVerificationStatus = (string)($row['verification_status'] ?? 'Pending');
                if ($newPaymentStatus === 'Unpaid') {
                    $newVerificationStatus = 'Pending';
                } elseif ($newPaymentStatus === 'Pending Verification') {
                    $newVerificationStatus = 'Pending';
                } elseif ($newPaymentStatus === 'Paid' && in_array(strtolower($newVerificationStatus), ['pending', 'pending verification'], true)) {
                    $newVerificationStatus = 'Approved';
                }

                $pdo->prepare("UPDATE event_registrations
                    SET persons = ?, payment_status = ?, verification_status = ?
                    WHERE id = ?")
                    ->execute([$newPersons, $newPaymentStatus, $newVerificationStatus, $registrationId]);

                $paymentRowId = (int)($row['payment_row_id'] ?? 0);
                if ($paymentRowId > 0) {
                    $paymentRecordStatus = (string)($row['payment_record_status'] ?? 'Pending');
                    if ($newPaymentStatus === 'Unpaid') {
                        $paymentRecordStatus = 'Pending';
                    } elseif ($newPaymentStatus === 'Pending Verification') {
                        $paymentRecordStatus = 'Pending Verification';
                    } elseif ($newPaymentStatus === 'Paid' && in_array(strtolower($paymentRecordStatus), ['pending', 'pending verification'], true)) {
                        $paymentRecordStatus = 'Approved';
                    }

                    $pdo->prepare("UPDATE event_payments
                        SET amount_paid = ?, remaining_amount = ?, status = ?
                        WHERE registration_id = ?")
                        ->execute([$newAmountPaid, $newRemaining, $paymentRecordStatus, $registrationId]);
                }
            }

            $insertCancel = $pdo->prepare("INSERT INTO event_cancellations
                (registration_id, cancelled_persons, cancellation_type, cancel_reason, refund_amount, refund_status, cancelled_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $insertCancel->execute([
                $registrationId,
                $cancelPersons,
                $cancellationType,
                $cancelReason,
                $refundAmount,
                $refundStatus,
            ]);
            $cancellationId = (int)$pdo->lastInsertId();

            $waitlistPromotion = vs_event_promote_waitlisted_registrations(
                $pdo,
                (int)($row['event_id'] ?? 0),
                (int)($row['package_id'] ?? 0),
                (int)($row['event_date_id'] ?? 0),
                $cancelPersons,
                true
            );

            if (!$useExistingTransaction) {
                $pdo->commit();
            }

            return [
                'cancellation_id' => $cancellationId,
                'registration_id' => $registrationId,
                'booking_reference' => (string)$bookingReference,
                'cancellation_type' => $cancellationType,
                'cancelled_persons' => $cancelPersons,
                'remaining_persons' => $remainingPersons,
                'refund_amount' => $refundAmount,
                'refund_status' => $refundStatus,
                'waitlist_promotion' => $waitlistPromotion,
            ];
        } catch (Throwable $e) {
            if (!$useExistingTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('Unable to cancel booking right now.');
        }
    }
}

if (!function_exists('vs_event_submit_cancellation_request')) {
    function vs_event_submit_cancellation_request(
        PDO $pdo,
        int $registrationId,
        int $cancelPersons = 0,
        string $cancelReason = '',
        string $requestSource = 'online'
    ): array
    {
        $registrationId = max($registrationId, 0);
        if ($registrationId <= 0) {
            throw new RuntimeException('Invalid registration selected.');
        }

        $requestSource = strtolower(trim($requestSource));
        if (!in_array($requestSource, ['online', 'admin'], true)) {
            $requestSource = 'online';
        }

        $cancelReason = trim($cancelReason);
        if ($cancelReason === '') {
            $cancelReason = ($requestSource === 'online')
                ? 'Cancellation requested by customer'
                : 'Cancellation requested by admin';
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT
                    r.id,
                    r.persons,
                    r.booking_reference,
                    r.payment_status,
                    r.verification_status,
                    r.checkin_status,
                    p.cancellation_allowed
                FROM event_registrations r
                INNER JOIN event_packages p ON p.id = r.package_id
                WHERE r.id = ?
                LIMIT 1
                FOR UPDATE");
            $stmt->execute([$registrationId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('Booking not found.');
            }

            $paymentStatus = strtolower(trim((string)($row['payment_status'] ?? '')));
            $verificationStatus = strtolower(trim((string)($row['verification_status'] ?? '')));
            if ($paymentStatus === 'cancelled' || $verificationStatus === 'cancelled') {
                throw new RuntimeException('Booking is already cancelled.');
            }
            if ((int)($row['checkin_status'] ?? 0) === 1) {
                throw new RuntimeException('Checked-in booking cannot be cancelled.');
            }
            if ((int)($row['cancellation_allowed'] ?? 1) !== 1) {
                throw new RuntimeException('Cancellation is not allowed for this package.');
            }

            $currentPersons = max((int)($row['persons'] ?? 1), 1);
            if ($cancelPersons <= 0) {
                $cancelPersons = $currentPersons;
            }
            if ($cancelPersons > $currentPersons) {
                throw new RuntimeException('Cancellation quantity cannot be greater than booked persons.');
            }
            $requestType = ($cancelPersons >= $currentPersons) ? 'full' : 'partial';

            $pendingStmt = $pdo->prepare("SELECT id
                FROM event_cancellation_requests
                WHERE registration_id = ?
                  AND request_status = 'pending'
                ORDER BY id DESC
                LIMIT 1
                FOR UPDATE");
            $pendingStmt->execute([$registrationId]);
            $pendingId = (int)$pendingStmt->fetchColumn();
            if ($pendingId > 0) {
                throw new RuntimeException('Cancellation request is already pending approval.');
            }

            $insertStmt = $pdo->prepare("INSERT INTO event_cancellation_requests
                (registration_id, requested_persons, request_type, cancel_reason, request_source, request_status, requested_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            $insertStmt->execute([
                $registrationId,
                $cancelPersons,
                $requestType,
                $cancelReason,
                $requestSource,
            ]);
            $requestId = (int)$pdo->lastInsertId();

            $pdo->commit();

            return [
                'request_id' => $requestId,
                'registration_id' => $registrationId,
                'requested_persons' => $cancelPersons,
                'request_type' => $requestType,
                'request_source' => $requestSource,
                'request_status' => 'pending',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('Unable to submit cancellation request right now.');
        }
    }
}

if (!function_exists('vs_event_review_cancellation_request')) {
    function vs_event_review_cancellation_request(
        PDO $pdo,
        int $requestId,
        string $action,
        ?int $decidedByUserId = null,
        string $decidedByUserName = '',
        string $decisionNote = ''
    ): array
    {
        $requestId = max($requestId, 0);
        if ($requestId <= 0) {
            throw new RuntimeException('Invalid cancellation request selected.');
        }

        $action = strtolower(trim($action));
        if (!in_array($action, ['approve', 'reject'], true)) {
            throw new RuntimeException('Invalid cancellation request action.');
        }

        $decidedByUserName = trim($decidedByUserName);
        if ($decidedByUserName === '') {
            $decidedByUserName = 'Admin User';
        }
        if ($decidedByUserId !== null && $decidedByUserId <= 0) {
            $decidedByUserId = null;
        }
        $decisionNote = trim($decisionNote);

        try {
            $pdo->beginTransaction();

            $requestStmt = $pdo->prepare("SELECT
                    cr.*,
                    r.booking_reference
                FROM event_cancellation_requests cr
                INNER JOIN event_registrations r ON r.id = cr.registration_id
                WHERE cr.id = ?
                LIMIT 1
                FOR UPDATE");
            $requestStmt->execute([$requestId]);
            $requestRow = $requestStmt->fetch(PDO::FETCH_ASSOC);
            if (!$requestRow) {
                throw new RuntimeException('Cancellation request not found.');
            }

            $currentStatus = strtolower(trim((string)($requestRow['request_status'] ?? '')));
            if ($currentStatus !== 'pending') {
                throw new RuntimeException('Cancellation request is already reviewed.');
            }

            $registrationId = (int)($requestRow['registration_id'] ?? 0);
            if ($registrationId <= 0) {
                throw new RuntimeException('Invalid registration linked to this request.');
            }

            if ($action === 'approve') {
                $cancelReason = trim((string)($requestRow['cancel_reason'] ?? ''));
                if ($cancelReason === '') {
                    $cancelReason = 'Cancellation request approved by admin';
                }

                $cancelResult = vs_event_cancel_registration(
                    $pdo,
                    $registrationId,
                    (int)($requestRow['requested_persons'] ?? 0),
                    $cancelReason,
                    true,
                    'processed',
                    true
                );

                $finalDecisionNote = $decisionNote !== '' ? $decisionNote : 'Approved by admin. Booking cancelled and refund marked processed.';
                $updateStmt = $pdo->prepare("UPDATE event_cancellation_requests
                    SET request_status = 'approved',
                        decision_note = ?,
                        decided_at = NOW(),
                        decided_by_user_id = ?,
                        decided_by_user_name = ?,
                        processed_cancellation_id = ?
                    WHERE id = ?
                      AND request_status = 'pending'");
                $updateStmt->execute([
                    $finalDecisionNote,
                    $decidedByUserId,
                    $decidedByUserName,
                    (int)($cancelResult['cancellation_id'] ?? 0),
                    $requestId,
                ]);
                if ($updateStmt->rowCount() <= 0) {
                    throw new RuntimeException('Unable to update cancellation request status.');
                }

                $pdo->commit();
                return [
                    'request_id' => $requestId,
                    'registration_id' => $registrationId,
                    'request_status' => 'approved',
                    'cancellation' => $cancelResult,
                ];
            }

            $finalDecisionNote = $decisionNote !== '' ? $decisionNote : 'Rejected by admin.';
            $rejectStmt = $pdo->prepare("UPDATE event_cancellation_requests
                SET request_status = 'rejected',
                    decision_note = ?,
                    decided_at = NOW(),
                    decided_by_user_id = ?,
                    decided_by_user_name = ?
                WHERE id = ?
                  AND request_status = 'pending'");
            $rejectStmt->execute([
                $finalDecisionNote,
                $decidedByUserId,
                $decidedByUserName,
                $requestId,
            ]);
            if ($rejectStmt->rowCount() <= 0) {
                throw new RuntimeException('Unable to update cancellation request status.');
            }

            $pdo->commit();
            return [
                'request_id' => $requestId,
                'registration_id' => $registrationId,
                'request_status' => 'rejected',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('Unable to process cancellation request right now.');
        }
    }
}

if (!function_exists('vs_event_evaluate_registration_delete_eligibility')) {
    function vs_event_evaluate_registration_delete_eligibility(array $row): array
    {
        $persons = max((int)($row['persons'] ?? 1), 1);
        $paymentStatus = strtolower(trim((string)($row['payment_status'] ?? '')));
        $verificationStatus = strtolower(trim((string)($row['verification_status'] ?? '')));
        $paymentRecordStatus = strtolower(trim((string)($row['payment_record_status'] ?? '')));
        $isCheckedIn = ((int)($row['checkin_status'] ?? 0) === 1);
        $isCancelled = ($paymentStatus === 'cancelled' || $verificationStatus === 'cancelled');

        $amountPaid = round(max((float)($row['amount_paid'] ?? 0), 0), 2);
        $processedRefundAmount = round(max((float)($row['processed_refund_amount_total'] ?? 0), 0), 2);
        $latestRefundStatus = strtolower(trim((string)($row['latest_refund_status'] ?? '')));
        if ($processedRefundAmount <= 0 && $latestRefundStatus === 'processed') {
            $processedRefundAmount = round(max((float)($row['latest_refund_amount'] ?? 0), 0), 2);
        }
        $netPaidAmount = round(max($amountPaid - $processedRefundAmount, 0), 2);
        $hasPaidStatus = in_array($paymentStatus, ['paid', 'partial paid'], true);
        $hasPaidAmount = ($netPaidAmount > 0.0) || ($hasPaidStatus && !$isCancelled && $processedRefundAmount <= 0.0);

        $cancelledPersonsTotal = (int)($row['cancelled_persons_total'] ?? 0);
        if ($cancelledPersonsTotal <= 0) {
            $cancelledPersonsTotal = (int)($row['latest_cancelled_persons'] ?? 0);
        }
        $isFullyCancelled = ($isCancelled && $cancelledPersonsTotal > 0 && $cancelledPersonsTotal >= $persons);

        $isVerificationPending = in_array($verificationStatus, ['pending', 'pending verification'], true)
            || in_array($paymentRecordStatus, ['pending', 'pending verification'], true);

        $hasVerificationHistory = ((int)($row['payment_id'] ?? 0) > 0)
            || in_array($paymentStatus, ['pending verification', 'failed', 'rejected', 'paid', 'partial paid'], true)
            || in_array($paymentRecordStatus, ['pending', 'pending verification', 'approved', 'rejected', 'failed', 'paid', 'cancelled'], true);

        $verificationHistoryValid = true;
        if ($hasVerificationHistory) {
            $verificationHistoryValid = in_array($verificationStatus, ['rejected', 'cancelled'], true)
                || in_array($paymentRecordStatus, ['rejected', 'failed', 'cancelled'], true);
        }

        $eligible = false;
        $reason = '';
        if (!$isFullyCancelled) {
            $reason = 'Delete is allowed only after full cancellation for all booked persons.';
        } elseif ($hasPaidAmount) {
            $reason = 'Delete is blocked because paid amount is not zero after processed refunds.';
        } elseif ($isCheckedIn) {
            $reason = 'Checked-in registration cannot be deleted.';
        } elseif ($isVerificationPending) {
            $reason = 'Delete is blocked while verification is pending.';
        } elseif (!$verificationHistoryValid) {
            $reason = 'If payment verification was submitted earlier, it must be rejected before delete.';
        } else {
            $eligible = true;
        }

        return [
            'eligible' => $eligible,
            'reason' => $reason,
            'persons' => $persons,
            'cancelled_persons_total' => $cancelledPersonsTotal,
            'amount_paid' => $amountPaid,
            'processed_refund_amount_total' => $processedRefundAmount,
            'net_paid_amount' => $netPaidAmount,
            'has_paid_amount' => $hasPaidAmount,
            'is_checked_in' => $isCheckedIn,
            'is_fully_cancelled' => $isFullyCancelled,
            'is_verification_pending' => $isVerificationPending,
            'verification_history_valid' => $verificationHistoryValid,
        ];
    }
}

if (!function_exists('vs_event_get_registration_delete_eligibility')) {
    function vs_event_get_registration_delete_eligibility(PDO $pdo, int $registrationId, bool $forUpdate = false): array
    {
        $registrationId = max($registrationId, 0);
        if ($registrationId <= 0) {
            return [
                'exists' => false,
                'eligible' => false,
                'reason' => 'Invalid registration selected.',
                'row' => null,
            ];
        }

        $sql = "SELECT
                r.id,
                r.event_id,
                r.persons,
                r.payment_status,
                r.verification_status,
                r.checkin_status,
                COALESCE(ep.id, 0) AS payment_id,
                COALESCE(ep.amount_paid, 0) AS amount_paid,
                COALESCE(ep.status, '') AS payment_record_status,
                COALESCE(ep.transaction_id, '') AS transaction_id,
                COALESCE(c_tot.cancelled_persons_total, 0) AS cancelled_persons_total,
                COALESCE(c_tot.processed_refund_amount_total, 0) AS processed_refund_amount_total,
                COALESCE(c_last.cancelled_persons, 0) AS latest_cancelled_persons,
                COALESCE(c_last.refund_amount, 0) AS latest_refund_amount,
                COALESCE(c_last.refund_status, '') AS latest_refund_status
            FROM event_registrations r
            LEFT JOIN event_payments ep ON ep.registration_id = r.id
            LEFT JOIN (
                SELECT
                    registration_id,
                    SUM(cancelled_persons) AS cancelled_persons_total,
                    SUM(CASE WHEN refund_status = 'processed' THEN refund_amount ELSE 0 END) AS processed_refund_amount_total
                FROM event_cancellations
                GROUP BY registration_id
            ) c_tot ON c_tot.registration_id = r.id
            LEFT JOIN (
                SELECT c1.registration_id, c1.cancelled_persons, c1.refund_amount, c1.refund_status
                FROM event_cancellations c1
                INNER JOIN (
                    SELECT registration_id, MAX(id) AS latest_id
                    FROM event_cancellations
                    GROUP BY registration_id
                ) c2 ON c2.latest_id = c1.id
            ) c_last ON c_last.registration_id = r.id
            WHERE r.id = ?
            LIMIT 1";
        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$registrationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [
                'exists' => false,
                'eligible' => false,
                'reason' => 'Registration not found.',
                'row' => null,
            ];
        }

        $evaluation = vs_event_evaluate_registration_delete_eligibility($row);

        return array_merge(
            [
                'exists' => true,
                'row' => $row,
            ],
            $evaluation
        );
    }
}

if (!function_exists('vs_event_delete_registration_if_eligible')) {
    function vs_event_delete_registration_if_eligible(PDO $pdo, int $registrationId): array
    {
        $registrationId = max($registrationId, 0);
        if ($registrationId <= 0) {
            throw new RuntimeException('Invalid registration selected.');
        }

        $startedTx = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTx = true;
        }

        try {
            $eligibility = vs_event_get_registration_delete_eligibility($pdo, $registrationId, true);
            if (!$eligibility['exists']) {
                throw new RuntimeException('Registration not found.');
            }
            if (empty($eligibility['eligible'])) {
                throw new RuntimeException((string)($eligibility['reason'] ?? 'Registration is not eligible for delete.'));
            }

            $deleteStmt = $pdo->prepare("DELETE FROM event_registrations WHERE id = ? LIMIT 1");
            $deleteStmt->execute([$registrationId]);
            if ($deleteStmt->rowCount() <= 0) {
                throw new RuntimeException('Unable to delete registration right now.');
            }

            if ($startedTx && $pdo->inTransaction()) {
                $pdo->commit();
            }

            return [
                'registration_id' => $registrationId,
                'event_id' => (int)(($eligibility['row']['event_id'] ?? 0)),
                'deleted' => true,
            ];
        } catch (Throwable $e) {
            if ($startedTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('Unable to delete registration right now.');
        }
    }
}

if (!function_exists('vs_event_get_youtube_embed_url')) {
    function vs_event_get_youtube_embed_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = @parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        $host = strtolower((string)($parts['host'] ?? ''));
        $path = (string)($parts['path'] ?? '');
        $videoId = '';
        if ($host === 'youtu.be') {
            $videoId = trim($path, '/');
        } elseif (strpos($host, 'youtube.com') !== false) {
            if ($path === '/watch') {
                parse_str((string)($parts['query'] ?? ''), $query);
                $videoId = trim((string)($query['v'] ?? ''));
            } elseif (strpos($path, '/embed/') === 0) {
                $videoId = trim(substr($path, strlen('/embed/')));
            } elseif (strpos($path, '/shorts/') === 0) {
                $videoId = trim(substr($path, strlen('/shorts/')));
            }
        }

        $videoId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$videoId);
        if ($videoId === '') {
            return '';
        }

        return 'https://www.youtube.com/embed/' . $videoId;
    }
}

if (!function_exists('vs_event_store_upload')) {
    function vs_event_store_upload(array $file, string $bucket, array $allowedExt): ?string
    {
        if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $name = (string)($file['name'] ?? '');
        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($name === '' || $tmpName === '' || !is_uploaded_file($tmpName)) {
            return null;
        }

        $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowedExt, true)) {
            return null;
        }

        $safeBucket = trim(str_replace('..', '', $bucket), '/\\');
        $targetDir = __DIR__ . '/../uploads/events/' . $safeBucket;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return null;
        }

        $fileName = uniqid('event_', true) . '.' . $ext;
        $dest = $targetDir . '/' . $fileName;
        if (!move_uploaded_file($tmpName, $dest)) {
            return null;
        }

        return 'uploads/events/' . $safeBucket . '/' . $fileName;
    }
}

if (!function_exists('vs_event_get_client_ip')) {
    function vs_event_get_client_ip(): string
    {
        $sources = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
            $_SERVER['HTTP_X_REAL_IP'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
        ];

        foreach ($sources as $source) {
            $source = trim((string)$source);
            if ($source === '') {
                continue;
            }
            $parts = array_filter(array_map('trim', explode(',', $source)));
            foreach ($parts as $part) {
                if (filter_var($part, FILTER_VALIDATE_IP)) {
                    return $part;
                }
            }
        }

        return '0.0.0.0';
    }
}

if (!function_exists('vs_event_record_registration_attempt')) {
    function vs_event_record_registration_attempt(PDO $pdo, string $ipAddress): void
    {
        $ipAddress = trim($ipAddress);
        if ($ipAddress === '') {
            $ipAddress = '0.0.0.0';
        }

        $stmt = $pdo->prepare("INSERT INTO event_registration_attempts (ip_address) VALUES (?)");
        $stmt->execute([$ipAddress]);
    }
}

if (!function_exists('vs_event_count_recent_attempts')) {
    function vs_event_count_recent_attempts(PDO $pdo, string $ipAddress, int $windowMinutes = 60): int
    {
        $ipAddress = trim($ipAddress);
        if ($ipAddress === '') {
            return 0;
        }

        if ($windowMinutes <= 0) {
            $windowMinutes = 60;
        }

        $cutoff = date('Y-m-d H:i:s', time() - ($windowMinutes * 60));
        $stmt = $pdo->prepare("SELECT COUNT(*)
            FROM event_registration_attempts
            WHERE ip_address = ?
              AND created_at >= ?");
        $stmt->execute([$ipAddress, $cutoff]);

        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('vs_event_generate_qr')) {
    function vs_event_generate_qr(string $bookingReference, int $eventId = 0, int $packageId = 0): ?string
    {
        $bookingReference = trim($bookingReference);
        if ($bookingReference === '') {
            return null;
        }

        $eventId = max($eventId, 0);
        $packageId = max($packageId, 0);
        $payload = $bookingReference . '|' . $eventId . '|' . $packageId;

        $secret = (string)(getenv('EVENT_QR_SECRET') ?: getenv('APP_KEY') ?: 'vs_event_qr_secret');
        $fileHash = substr(sha1($payload . '|' . $secret), 0, 24);
        $fileName = 'qr_' . $fileHash . '.png';
        $relativeDir = 'uploads/events/qrcodes';
        $relativePath = $relativeDir . '/' . $fileName;
        $targetDir = __DIR__ . '/../' . $relativeDir;
        $targetPath = $targetDir . '/' . $fileName;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return null;
        }

        if (is_file($targetPath)) {
            return $relativePath;
        }

        $qrApiUrls = [
            'https://chart.googleapis.com/chart?cht=qr&chs=360x360&choe=UTF-8&chl=' . rawurlencode($payload),
            'https://api.qrserver.com/v1/create-qr-code/?size=360x360&data=' . rawurlencode($payload),
        ];
        $binary = '';

        foreach ($qrApiUrls as $qrApiUrl) {
            if (function_exists('curl_init')) {
                $ch = curl_init($qrApiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                $resp = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($httpCode === 200 && is_string($resp) && $resp !== '') {
                    $binary = $resp;
                    break;
                }
            } else {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 15,
                    ],
                ]);
                $resp = @file_get_contents($qrApiUrl, false, $context);
                if (is_string($resp) && $resp !== '') {
                    $binary = $resp;
                    break;
                }
            }
        }

        if ($binary === '') {
            return null;
        }

        $writeOk = @file_put_contents($targetPath, $binary);
        if ($writeOk === false || !is_file($targetPath)) {
            return null;
        }

        return $relativePath;
    }
}

if (!function_exists('vs_event_ensure_registration_qr')) {
    function vs_event_ensure_registration_qr(PDO $pdo, int $registrationId): string
    {
        if ($registrationId <= 0) {
            return '';
        }

        $stmt = $pdo->prepare("SELECT
            id,
            booking_reference,
            event_id,
            package_id,
            payment_status,
            qr_code_path
        FROM event_registrations
        WHERE id = ?
        LIMIT 1");
        $stmt->execute([$registrationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return '';
        }

        if (strtolower((string)$row['payment_status']) !== 'paid') {
            return (string)($row['qr_code_path'] ?? '');
        }

        $existingPath = trim((string)($row['qr_code_path'] ?? ''));
        if ($existingPath !== '') {
            $existingAbs = __DIR__ . '/../' . ltrim($existingPath, '/');
            if (is_file($existingAbs)) {
                return $existingPath;
            }
        }

        $bookingReference = trim((string)($row['booking_reference'] ?? ''));
        if ($bookingReference === '') {
            $bookingReference = vs_event_assign_booking_reference($pdo, $registrationId);
        }
        if ($bookingReference === '') {
            return '';
        }

        $qrPath = vs_event_generate_qr($bookingReference, (int)$row['event_id'], (int)$row['package_id']);
        if ($qrPath === null || $qrPath === '') {
            return '';
        }

        $updateStmt = $pdo->prepare("UPDATE event_registrations SET qr_code_path = ? WHERE id = ?");
        $updateStmt->execute([$qrPath, $registrationId]);

        return $qrPath;
    }
}

if (!function_exists('vs_event_extract_booking_reference')) {
    function vs_event_extract_booking_reference(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return '';
        }

        if (strpos($input, '|') !== false) {
            $parts = explode('|', $input);
            return trim((string)($parts[0] ?? ''));
        }

        return $input;
    }
}

if (!function_exists('vs_event_load_razorpay_keys')) {
    function vs_event_load_razorpay_keys(): array
    {
        $keyId = (string)(getenv('RAZORPAY_KEY_ID') ?: '');
        $keySecret = (string)(getenv('RAZORPAY_KEY_SECRET') ?: '');

        if ($keyId !== '' && $keySecret !== '') {
            return ['key_id' => $keyId, 'key_secret' => $keySecret];
        }

        $envPath = __DIR__ . '/../.env';
        if (is_file($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim((string)$line);
                if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                    continue;
                }
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                if ($k === 'RAZORPAY_KEY_ID' && $keyId === '') {
                    $keyId = $v;
                }
                if ($k === 'RAZORPAY_KEY_SECRET' && $keySecret === '') {
                    $keySecret = $v;
                }
            }
        }

        return ['key_id' => $keyId, 'key_secret' => $keySecret];
    }
}

if (!function_exists('vs_event_is_whatsapp_enabled')) {
    function vs_event_is_whatsapp_enabled(PDO $pdo, int $eventId): bool
    {
        $eventId = max($eventId, 0);
        if ($eventId <= 0) {
            return true;
        }

        static $cache = [];
        if (array_key_exists($eventId, $cache)) {
            return (bool)$cache[$eventId];
        }

        $stmt = $pdo->prepare("SELECT send_whatsapp_notifications FROM events WHERE id = ? LIMIT 1");
        $stmt->execute([$eventId]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            $cache[$eventId] = true;
            return true;
        }

        $enabled = ((int)$value === 1);
        $cache[$eventId] = $enabled;
        return $enabled;
    }
}

if (!function_exists('vs_event_send_whatsapp_notice')) {
    function vs_event_send_whatsapp_notice(string $trigger, string $phone, array $context = []): array
    {
        $phone = trim($phone);
        if ($phone === '') {
            return ['success' => false, 'message' => 'Phone missing', 'data' => null];
        }

        require_once __DIR__ . '/send_whatsapp.php';

        $name = (string)($context['name'] ?? 'Devotee');
        $eventName = (string)($context['event_name'] ?? 'Event');
        $packageName = (string)($context['package_name'] ?? 'Package');
        $eventDate = (string)($context['event_date'] ?? $context['date'] ?? '');
        $amount = isset($context['amount']) ? (string)$context['amount'] : '';
        $bookingReference = (string)($context['booking_reference'] ?? '');
        $registrationId = (int)($context['registration_id'] ?? 0);
        $qrCodePath = trim((string)($context['qr_code_path'] ?? ''));
        if ($bookingReference === '') {
            $bookingReference = 'N/A';
        }

        $templates = [
            'registration_received' => 'Registration received for %s (%s). Event date: %s. Amount: Rs %s. Ref: %s.',
            'payment_successful' => 'Payment successful for %s (%s). Event date: %s. Amount paid: Rs %s. Ref: %s.',
            'payment_pending_verification' => 'Payment submitted and pending verification for %s (%s). Event date: %s. Amount: Rs %s. Ref: %s.',
            'payment_approved' => 'Payment approved for %s (%s). Event date: %s. Amount: Rs %s. Ref: %s.',
            'event_reminder' => 'Reminder: %s (%s) is scheduled on %s. Amount: Rs %s. Ref: %s.',
            'ticket_delivery' => "Namaste %s\n\nYour booking for %s is confirmed.\n\nBooking ID: %s\n\nYour entry QR ticket is attached.\n\nEvent Date: %s\n\nThank you\nVishnusudarshana",
        ];

        if ($trigger === 'ticket_delivery') {
            $message = sprintf($templates['ticket_delivery'], $name, $eventName, $bookingReference, $eventDate);
        } else {
            $messagePattern = $templates[$trigger] ?? '%s (%s) update. Date: %s. Amount: Rs %s. Ref: %s.';
            $message = sprintf($messagePattern, $eventName, $packageName, $eventDate, $amount, $bookingReference);
        }

        $payload = [
            'name' => $name,
            'message' => $message,
        ];

        if ($trigger === 'ticket_delivery' && $qrCodePath !== '') {
            $mediaPath = $qrCodePath;
            if (strpos($mediaPath, 'uploads/events/') === 0) {
                $mediaPath = '../events/' . ltrim(substr($mediaPath, strlen('uploads/events/')), '/');
            }
            if ($registrationId > 0 && $bookingReference !== '' && $bookingReference !== 'N/A') {
                $payload['booking_reference'] = $bookingReference;
            }
            $payload['file_path'] = $mediaPath;
        }

        return sendWhatsAppMessage(
            $phone,
            'APPOINTMENT_MESSAGE',
            $payload
        );
    }
}

if (!function_exists('vs_event_send_event_reminders')) {
    function vs_event_send_event_reminders(PDO $pdo, int $eventId, bool $force = false): array
    {
        $eventStmt = $pdo->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
        $eventStmt->execute([$eventId]);
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            return ['sent' => 0, 'skipped' => 0, 'event_found' => false];
        }

        $marker = '__event_reminder_' . date('Ymd');

        $regStmt = $pdo->prepare("SELECT
                r.id,
                r.event_id,
                r.event_date_id,
                r.name,
                r.phone,
                r.booking_reference,
                p.package_name,
                e.event_type,
                COALESCE(ed.event_date, e.event_date) AS registration_event_date
            FROM event_registrations r
            INNER JOIN event_packages p ON p.id = r.package_id
            INNER JOIN events e ON e.id = r.event_id
            LEFT JOIN event_dates ed ON ed.id = r.event_date_id
            WHERE r.event_id = ?
              AND r.payment_status = 'Paid'
              AND r.verification_status IN ('Approved', 'Auto Verified')
            ORDER BY r.id ASC");
        $regStmt->execute([$eventId]);
        $registrations = $regStmt->fetchAll(PDO::FETCH_ASSOC);

        $checkMarkerStmt = $pdo->prepare('SELECT id FROM event_registration_data WHERE registration_id = ? AND field_name = ? LIMIT 1');
        $insertMarkerStmt = $pdo->prepare('INSERT INTO event_registration_data (registration_id, field_name, value) VALUES (?, ?, ?)');

        $sent = 0;
        $skipped = 0;

        foreach ($registrations as $row) {
            if (!$force) {
                $checkMarkerStmt->execute([(int)$row['id'], $marker]);
                if ($checkMarkerStmt->fetch()) {
                    $skipped++;
                    continue;
                }
            }

            $eventDateLabel = vs_event_get_registration_date_display(
                $pdo,
                $row,
                (string)($row['registration_event_date'] ?? $event['event_date'])
            );
            if (!vs_event_is_whatsapp_enabled($pdo, (int)$row['event_id'])) {
                $skipped++;
                continue;
            }
            $result = vs_event_send_whatsapp_notice('event_reminder', (string)$row['phone'], [
                'name' => (string)$row['name'],
                'event_name' => (string)$event['title'],
                'package_name' => (string)$row['package_name'],
                'event_date' => $eventDateLabel,
                'amount' => '',
                'booking_reference' => (string)($row['booking_reference'] ?? ''),
                'event_id' => (int)$row['event_id'],
            ]);

            if (!empty($result['success'])) {
                $insertMarkerStmt->execute([(int)$row['id'], $marker, date('Y-m-d H:i:s')]);
                $sent++;
            } else {
                $skipped++;
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'event_found' => true];
    }
}
