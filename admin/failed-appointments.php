<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/top-menu.php';

// Fetch failed appointments from pending_payments where category = 'appointment'
$stmt = $pdo->prepare("SELECT * FROM pending_payments WHERE category = 'appointment' ORDER BY created_at DESC");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Failed Appointments</title>
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 12px; border: 1px solid #ccc; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
<div class="container">
    <h2>Failed Appointments</h2>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>City</th>
                    <th>Products</th>
                    <th>Notes</th>
                    <th>Total Amount</th>
                    <th>Date</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                    $customer = json_decode($row['customer_details'] ?? '{}', true);
                    $products = json_decode($row['selected_products'] ?? '[]', true);
                    $formData = json_decode($row['form_data'] ?? '{}', true);
                    // Name
                    $name = $customer['full_name'] ?? $customer['name'] ?? $formData['name'] ?? '';
                    // Mobile
                    $mobile = $customer['mobile'] ?? $formData['mobile'] ?? '';
                    // City
                    $city = $customer['city'] ?? $formData['city'] ?? '';
                    // Notes
                    $notes = $formData['notes'] ?? '';
                    // Total Amount
                    $amount = $row['total_amount'] ?? $row['amount'] ?? '';
                    // Date & Time
                    $date = '';
                    $time = '';
                    if (!empty($formData['date'])) {
                        $date = $formData['date'];
                    } elseif (!empty($row['created_at'])) {
                        $date = date('Y-m-d', strtotime($row['created_at']));
                    }
                    if (!empty($formData['time'])) {
                        $time = $formData['time'];
                    } elseif (!empty($row['created_at'])) {
                        $time = date('H:i', strtotime($row['created_at']));
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($name) ?></td>
                    <td><?= htmlspecialchars($mobile) ?></td>
                    <td><?= htmlspecialchars($city) ?></td>
                    <td>
                        <?php if (!empty($products) && is_array($products)): ?>
                            <ul style="margin:0; padding-left:18px;">
                            <?php foreach ($products as $p): ?>
                                <li><?= htmlspecialchars($p['name'] ?? $p['product_name'] ?? '') ?></li>
                            <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($notes) ?></td>
                    <td><?= htmlspecialchars($amount) ?></td>
                    <td><?= htmlspecialchars($date) ?></td>
                    <td><?= htmlspecialchars($time) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8">No failed appointments found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
