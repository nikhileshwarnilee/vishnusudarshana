-- Add discount column to service_requests
ALTER TABLE service_requests ADD COLUMN discount DECIMAL(10,2) DEFAULT 0 AFTER total_amount;

-- Add discount column to service_payments
ALTER TABLE service_payments ADD COLUMN discount DECIMAL(10,2) DEFAULT 0 AFTER amount;
