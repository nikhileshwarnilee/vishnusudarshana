-- Safe maintenance for razorpay_webhook_logs.
-- Review the preview queries first, then run the DELETE sections when ready.

-- Current size snapshot
SELECT COUNT(*) AS total_rows, MIN(created_at) AS oldest_row, MAX(created_at) AS newest_row
FROM razorpay_webhook_logs;

SELECT event_type, COUNT(*) AS row_count
FROM razorpay_webhook_logs
GROUP BY event_type
ORDER BY row_count DESC, event_type ASC;

-- Trim rows older than the app retention window (code defaults to 45 days).
DELETE FROM razorpay_webhook_logs
WHERE created_at < (NOW() - INTERVAL 45 DAY);

-- Collapse duplicate reconcile.error rows, keeping the newest row for each order + payload pair.
CREATE TEMPORARY TABLE tmp_rzp_reconcile_error_keep AS
SELECT MAX(id) AS keep_id
FROM razorpay_webhook_logs
WHERE event_type = 'reconcile.error'
GROUP BY COALESCE(razorpay_order_id, ''), COALESCE(payload, '');

DELETE l
FROM razorpay_webhook_logs l
LEFT JOIN tmp_rzp_reconcile_error_keep k ON k.keep_id = l.id
WHERE l.event_type = 'reconcile.error'
  AND k.keep_id IS NULL;

DROP TEMPORARY TABLE tmp_rzp_reconcile_error_keep;

-- Collapse duplicate terminal invalid-order markers, keeping the newest marker per order.
CREATE TEMPORARY TABLE tmp_rzp_terminal_invalid_keep AS
SELECT MAX(id) AS keep_id
FROM razorpay_webhook_logs
WHERE event_type = 'reconcile.terminal.invalid_order'
GROUP BY COALESCE(razorpay_order_id, '');

DELETE l
FROM razorpay_webhook_logs l
LEFT JOIN tmp_rzp_terminal_invalid_keep k ON k.keep_id = l.id
WHERE l.event_type = 'reconcile.terminal.invalid_order'
  AND k.keep_id IS NULL;

DROP TEMPORARY TABLE tmp_rzp_terminal_invalid_keep;

-- Post-cleanup snapshot
SELECT COUNT(*) AS total_rows_after, MIN(created_at) AS oldest_row_after, MAX(created_at) AS newest_row_after
FROM razorpay_webhook_logs;

SELECT event_type, COUNT(*) AS row_count_after
FROM razorpay_webhook_logs
GROUP BY event_type
ORDER BY row_count_after DESC, event_type ASC;
