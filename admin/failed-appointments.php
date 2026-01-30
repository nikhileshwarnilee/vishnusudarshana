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
                    <th>ID</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Email</th>
                    <th>City</th>
                    <th>Products</th>
                    <th>Notes</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Preferred Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                    $customer = json_decode($row['customer_details'] ?? '{}', true);
                    $products = json_decode($row['selected_products'] ?? '[]', true);
                    $formData = json_decode($row['form_data'] ?? '{}', true);
                    $notes = $formData['notes'] ?? '';
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($customer['full_name'] ?? $customer['name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($customer['mobile'] ?? '') ?></td>
                    <td><?= htmlspecialchars($customer['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($customer['city'] ?? '') ?></td>
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
                    <td><?= htmlspecialchars($row['amount'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['status'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="10">No failed appointments found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
