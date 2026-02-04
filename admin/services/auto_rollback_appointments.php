<?php
// admin/services/auto_rollback_appointments.php
// Auto-rollback accepted appointments not completed on assigned date
// Safe to run multiple times, no UI output, logs via error_log()

require_once __DIR__ . '/../../config/db.php';


try {
    $pdo->beginTransaction();
    // Find all appointments to be rolled back first
    $find = $pdo->prepare("SELECT id, customer_name, mobile, tracking_id FROM service_requests WHERE category_slug = 'appointment' AND payment_status IN ('Paid', 'Free') AND service_status = 'Accepted' AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(form_data,'$.assigned_date')), '') <> '' AND JSON_UNQUOTE(JSON_EXTRACT(form_data,'$.assigned_date')) < CURDATE() AND service_status != 'Completed'");
    $find->execute();
    $toRollback = $find->fetchAll(PDO::FETCH_ASSOC);

    $updatedAt = date('Y-m-d H:i:s');
    if (!empty($toRollback)) {
        $ids = array_column($toRollback, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE service_requests SET service_status = 'Received', form_data = JSON_SET(form_data, '$.assigned_date', NULL, '$.assigned_from_time', NULL, '$.assigned_to_time', NULL), updated_at = ? WHERE id IN ($placeholders)";
        $params = array_merge([$updatedAt], $ids);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->rowCount();

        require_once __DIR__ . '/../../helpers/send_whatsapp.php';
        foreach ($toRollback as $row) {
            try {
                sendWhatsAppNotification('appointment_missed', [
                    'mobile' => $row['mobile'],
                    'customer_name' => $row['customer_name'],
                    'category' => 'Appointment',
                    'products_list' => '',
                    'tracking_id' => $row['tracking_id']
                ]);
            } catch (Throwable $e) {
                error_log('WhatsApp missed failed: ' . $e->getMessage());
            }
        }
        $msg = "Auto-rollback executed: $count records reverted.";
    } else {
        $count = 0;
        $msg = "Auto-rollback executed: 0 records reverted.";
    }
    $pdo->commit();
    echo $msg;
} catch (Throwable $e) {
    $pdo->rollBack();
    $msg = 'Auto-rollback failed: ' . $e->getMessage();
    echo $msg;
}
