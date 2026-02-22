-- Firebase Cloud Messaging setup
-- Run this once on the target database before enabling notification sends.

CREATE TABLE IF NOT EXISTS fcm_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token VARCHAR(255) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    device_id VARCHAR(64) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fcm_tokens_token (token),
    KEY idx_fcm_tokens_user_id (user_id),
    KEY idx_fcm_tokens_device_id (device_id),
    KEY idx_fcm_tokens_active_updated (is_active, last_updated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fcm_topic_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_id BIGINT UNSIGNED NOT NULL,
    token VARCHAR(255) NOT NULL,
    topic VARCHAR(100) NOT NULL,
    subscribed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fcm_topic_token_topic (token, topic),
    KEY idx_fcm_topic_topic (topic),
    KEY idx_fcm_topic_token_id (token_id),
    CONSTRAINT fk_fcm_topic_token_id
        FOREIGN KEY (token_id) REFERENCES fcm_tokens(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fcm_notification_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient TEXT NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    type ENUM('device', 'topic', 'broadcast') NOT NULL DEFAULT 'device',
    status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    response MEDIUMTEXT NULL,
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fcm_logs_sent_at (sent_at),
    KEY idx_fcm_logs_type_status (type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
