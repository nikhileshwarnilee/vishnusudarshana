<?php
// send_due_reminder.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/send_whatsapp.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_due_reminder') {
    $customerId = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $customerName = trim($_POST['customer_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $dueAmount = isset($_POST['due_amount']) ? (float)$_POST['due_amount'] : 0;
    
    if ($customerId <= 0 || !$customerName || !$mobile || $dueAmount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    try {
        // Format due amount
        $formattedAmount = number_format($dueAmount, 2, '.', '');
        
        // Send WhatsApp reminder
        $result = sendWhatsAppMessage(
            $mobile,
            'PAYMENT_DUES_REMINDER',
            [
                'name' => $customerName,
                'due_amount' => $formattedAmount
            ]
        );
        
        if ($result['success']) {
            error_log("Payment due reminder sent to $mobile for customer $customerName, amount: ₹$formattedAmount");
            echo json_encode(['success' => true]);
        } else {
            error_log("Payment due reminder failed for $mobile: " . ($result['message'] ?? 'Unknown error'));
            echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to send']);
        }
    } catch (Throwable $e) {
        error_log('Payment due reminder exception: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_to_all') {
    // Bulk send to all customers with dues
    try {
        // Get all customers with unpaid dues
        $sql = "SELECT c.id, c.name, c.mobile,
                COALESCE(SUM(i.total_amount),0) - COALESCE(SUM(i.paid_amount),0) as unpaid_dues
            FROM customers c
            LEFT JOIN invoices i ON i.customer_id = c.id
            GROUP BY c.id
            HAVING unpaid_dues > 0
            ORDER BY c.name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $customers = $stmt->fetchAll();
        
        $count = 0;
        $failed = 0;
        
        foreach ($customers as $customer) {
            if (empty($customer['mobile'])) {
                $failed++;
                continue;
            }
            
            $formattedAmount = number_format($customer['unpaid_dues'], 2, '.', '');
            
            $result = sendWhatsAppMessage(
                $customer['mobile'],
                'PAYMENT_DUES_REMINDER',
                [
                    'name' => $customer['name'],
                    'due_amount' => $formattedAmount
                ]
            );
            
            if ($result['success']) {
                $count++;
                error_log("Bulk: Payment due reminder sent to {$customer['mobile']} for {$customer['name']}, amount: ₹$formattedAmount");
            } else {
                $failed++;
                error_log("Bulk: Payment due reminder failed for {$customer['mobile']}: " . ($result['message'] ?? 'Unknown error'));
            }
        }
        
        echo json_encode([
            'success' => true,
            'count' => $count,
            'failed' => $failed,
            'message' => "Sent to $count customer(s)" . ($failed > 0 ? ", $failed failed" : "")
        ]);
    } catch (Throwable $e) {
        error_log('Bulk payment due reminder exception: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

