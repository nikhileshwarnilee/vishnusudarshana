CREATE TABLE IF NOT EXISTS razorpay_webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    razorpay_order_id VARCHAR(100) NULL,
    razorpay_payment_id VARCHAR(100) NULL,
    payload LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rzp_order_id (razorpay_order_id),
    INDEX idx_rzp_payment_id (razorpay_payment_id),
    INDEX idx_rzp_event_type (event_type),
    INDEX idx_rzp_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

