<?php
// admin/customers/crm/index.php

// Handle AJAX custom message FIRST (before any output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'custom_msg') {
    require_once __DIR__ . '/../../helpers/send_whatsapp.php';
    
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$name || !$mobile || !$message) {
        echo json_encode(['success' => false, 'msg' => 'All fields are required.']);
        exit;
    }
    try {
        $result = sendWhatsAppMessage(
            $mobile,
            'APPOINTMENT_MESSAGE',
            [
                'name' => $name,
                'message' => $message
            ]
        );
        
        error_log('WhatsApp result: ' . json_encode($result));
        
        if (isset($result['success']) && $result['success'] === true) {
            echo json_encode(['success' => true, 'msg' => 'Message sent successfully']);
        } else {
            echo json_encode(['success' => false, 'msg' => $result['message'] ?? 'Failed to send']);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'msg' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle AJAX bulk send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'bulk_send') {
    require_once __DIR__ . '/../../helpers/send_whatsapp.php';
    require_once __DIR__ . '/../../config/db.php';
    
    $message = trim($_POST['message'] ?? '');

    if (!$message) {
        echo json_encode(['success' => false, 'msg' => 'Message is required.']);
        exit;
    }
    
    try {
        // Get all customers from unified query
        $sql = "
            SELECT name, mobile FROM cif_clients
            UNION ALL
            SELECT name, mobile FROM customers
            UNION ALL
            SELECT customer_name as name, mobile FROM service_requests
        ";
        $stmt = $pdo->query($sql);
        $customers = $stmt->fetchAll();
        
        $sent = 0;
        $failed = 0;
        
        foreach ($customers as $customer) {
            if (empty($customer['mobile'])) {
                $failed++;
                continue;
            }
            
            $result = sendWhatsAppMessage(
                $customer['mobile'],
                'APPOINTMENT_MESSAGE',
                [
                    'name' => $customer['name'],
                    'message' => $message
                ]
            );
            
            if (isset($result['success']) && $result['success'] === true) {
                $sent++;
            } else {
                $failed++;
            }
        }
        
        echo json_encode([
            'success' => true, 
            'msg' => "Messages sent to $sent customers. Failed: $failed"
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'msg' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Now load includes that may output HTML
require_once __DIR__ . '/../../helpers/send_whatsapp.php';
require_once __DIR__ . '/../includes/top-menu.php';
require_once __DIR__ . '/../../config/db.php';

// Pagination and search
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$perPage = 10;
$params = [];
$where = '';

if ($search !== '') {
    $where = "WHERE name LIKE :q OR mobile LIKE :q OR address_city LIKE :q";
    $params['q'] = "%$search%";
}

// Build unified query using UNION ALL
$sql = "
    SELECT name, mobile, address as address_city FROM cif_clients
    UNION ALL
    SELECT name, mobile, address as address_city FROM customers
    UNION ALL
    SELECT customer_name as name, mobile, city as address_city FROM service_requests
";
if ($search !== '') {
    $sql = "
        SELECT * FROM (
            SELECT name, mobile, address as address_city FROM cif_clients
            UNION ALL
            SELECT name, mobile, address as address_city FROM customers
            UNION ALL
            SELECT customer_name as name, mobile, city as address_city FROM service_requests
        ) AS all_customers
        $where
    ";
}

// Get total count
$countSql = "SELECT COUNT(*) FROM (" . str_replace('SELECT * FROM', 'SELECT 1 FROM', $sql) . ") AS total";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total_customers = (int)$countStmt->fetchColumn();
$total_pages = max(1, ceil($total_customers / $perPage));
$offset = ($page - 1) * $perPage;

// Add LIMIT for pagination
$sql .= " LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Database</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
        .admin-container { max-width: 1400px; margin: 0 auto; padding: 24px 12px; }
        h1 { color: #800000; margin-bottom: 18px; }
        .filter-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 18px; flex-wrap: wrap; }
        .filter-bar input { min-width: 220px; padding: 7px 12px; border-radius: 6px; font-size: 1em; border: 1px solid #ddd; }
        .filter-bar .btn-main { padding: 8px 16px; background: #800000; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .filter-bar .btn-main:hover { background: #600000; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 12px #e0bebe22; border-radius: 12px; table-layout: auto; font-size: 0.85em; }
        table th, table td { padding: 8px 6px; text-align: left; border-bottom: 1px solid #f3caca; white-space: nowrap; }
        table thead { background: #f9eaea; color: #800000; font-size: 0.9em; font-weight: 600; }
        table td { font-size: 0.95em; }
        table tbody tr:hover { background: #f3f7fa; }
        .page-link { display: inline-block; padding: 8px 14px; margin: 0 2px; border-radius: 6px; background: #f9eaea; color: #800000; font-weight: 600; text-decoration: none; }
        .page-link:hover { background: #800000; color: #fff; }
        .page-link.active { background: #800000; color: #fff; }
        .summary-cards { display: flex; gap: 18px; margin-bottom: 24px; flex-wrap: wrap; }
        .summary-card { flex: 1 1 180px; background: #fffbe7; border-radius: 14px; padding: 16px; text-align: center; box-shadow: 0 2px 8px #e0bebe22; }
        .summary-count { font-size: 2.2em; font-weight: 700; color: #800000; }
        .summary-label { font-size: 1em; color: #444; }
    </style>
</head>
<body>
<?php /* ...existing code for top menu... */ ?>
<div class="admin-container">
    <h1>Customer Database</h1>
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-count"><?= $total_customers ?></div>
            <div class="summary-label">Total Customers</div>
        </div>
    </div>
    <form class="filter-bar" method="get" action="">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search customers...">
        <button type="submit" class="btn-main">Search</button>
        <button type="button" class="btn-main" onclick="openBulkMsgModal()" style="background:#25D366;">Send Message to All</button>
    </form>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Mobile</th>
                <th>Address/City</th>
                <th>Msg</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($customers)): ?>
            <tr><td colspan="4" style="text-align:center;color:#888;">No customers found.</td></tr>
        <?php else: foreach ($customers as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td><?= htmlspecialchars($c['mobile']) ?></td>
                <td><?= htmlspecialchars($c['address_city']) ?></td>
                <td>
                    <button class="action-btn" style="background:#25D366;color:#fff;padding:6px 12px;border:none;border-radius:4px;font-weight:600;cursor:pointer;font-size:0.9em;" onclick="openCustMsgModal('<?= htmlspecialchars(addslashes($c['name'])) ?>', '<?= htmlspecialchars($c['mobile']) ?>')">Send Msg</button>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <div style="margin-top:18px;text-align:center;">
        <?php
        // Improved pagination logic
        $maxPagesToShow = 5;
        $startPage = max(1, $page - 2);
        $endPage = min($total_pages, $page + 2);
        if ($endPage - $startPage < $maxPagesToShow - 1) {
            if ($startPage === 1) {
                $endPage = min($total_pages, $startPage + $maxPagesToShow - 1);
            } else {
                $startPage = max(1, $endPage - $maxPagesToShow + 1);
            }
        }
        if ($startPage > 1) {
            echo '<a class="page-link" href="?search=' . urlencode($search) . '&page=1">1</a>';
            if ($startPage > 2) echo '<span class="page-link" style="background:none;color:#888;cursor:default;">...</span>';
        }
        for ($i = $startPage; $i <= $endPage; $i++) {
            echo '<a class="page-link' . ($i === $page ? ' active' : '') . '" href="?search=' . urlencode($search) . '&page=' . $i . '">' . $i . '</a>';
        }
        if ($endPage < $total_pages) {
            if ($endPage < $total_pages - 1) echo '<span class="page-link" style="background:none;color:#888;cursor:default;">...</span>';
            echo '<a class="page-link" href="?search=' . urlencode($search) . '&page=' . $total_pages . '">' . $total_pages . '</a>';
        }
        ?>
    </div>
</div>

<!-- Bulk Message Modal -->
<div id="bulkMsgModalBg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:1000; align-items:center; justify-content:center;">
    <div id="bulkMsgModal" style="background:#fff; border-radius:12px; box-shadow:0 2px 16px #80000033; padding:28px 24px 18px 24px; min-width:340px; max-width:95vw; width:420px; text-align:left; position:relative;">
        <div style="font-size:1.12em;color:#25D366;font-weight:700;margin-bottom:10px;">Send Message to All Customers</div>
        <form id="bulkMsgForm" autocomplete="off">
            <input type="hidden" name="ajax" value="bulk_send">
            <div style="margin-bottom:10px;color:#444;"><b>Recipients:</b> All customers in database</div>
            <div style="margin-bottom:10px;">
                <label for="bulkMsgText" style="display:block; margin-bottom:6px;"><b>Message:</b></label>
                <textarea name="message" id="bulkMsgText" style="width:100%;height:110px;padding:8px;border-radius:6px;border:1px solid #ccc;font-family:Arial,sans-serif;resize:vertical;" placeholder="Enter your message to all customers..." required></textarea>
            </div>
            <div style="margin-top:14px;text-align:center;">
                <button type="submit" style="background:#25D366;color:#fff;padding:8px 22px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Send to All</button>
                &nbsp;
                <button type="button" onclick="closeBulkMsgModal()" style="background:#ccc;color:#333;padding:8px 22px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
            </div>
            <div id="bulkMsgStatus" style="margin-top:10px; color:#c00; display:none;"></div>
        </form>
    </div>
</div>

<!-- Custom Message Modal -->
<div id="custMsgModalBg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:1000; align-items:center; justify-content:center;">
    <div id="custMsgModal" style="background:#fff; border-radius:12px; box-shadow:0 2px 16px #80000033; padding:28px 24px 18px 24px; min-width:340px; max-width:95vw; width:420px; text-align:left; position:relative;">
        <div style="font-size:1.12em;color:#007bff;font-weight:700;margin-bottom:10px;">Send Custom Message</div>
        <form id="custMsgForm" autocomplete="off">
            <input type="hidden" name="ajax" value="custom_msg">
            <input type="hidden" name="name" id="custMsgNameInput">
            <input type="hidden" name="mobile" id="custMsgMobileInput">
            <div style="margin-bottom:10px;color:#444;"><b>Customer:</b> <span id="custMsgName"></span></div>
            <div style="margin-bottom:10px;color:#444;"><b>Mobile:</b> <span id="custMsgMobile"></span></div>
            <div style="margin-bottom:10px;">
                <label for="custMsgText" style="display:block; margin-bottom:6px;"><b>Message:</b></label>
                <textarea name="message" id="custMsgText" style="width:100%;height:110px;padding:8px;border-radius:6px;border:1px solid #ccc;font-family:Arial,sans-serif;resize:vertical;" placeholder="Enter your custom message..." required></textarea>
            </div>
            <div style="margin-top:14px;text-align:center;">
                <button type="submit" style="background:#25D366;color:#fff;padding:8px 22px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Send Message</button>
                &nbsp;
                <button type="button" onclick="closeCustMsgModal()" style="background:#800000;color:#fff;padding:8px 22px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
            </div>
            <div id="custMsgStatus" style="margin-top:10px; color:#c00; display:none;"></div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
function openCustMsgModal(name, mobile) {
    if (!mobile) {
        alert('No mobile number available for this customer.');
        return;
    }
    $('#custMsgName').text(name);
    $('#custMsgNameInput').val(name);
    $('#custMsgMobile').text(mobile);
    $('#custMsgMobileInput').val(mobile);
    $('#custMsgText').val('');
    $('#custMsgStatus').hide().text('');
    $('#custMsgModalBg').css('display','flex');
}
function closeCustMsgModal() {
    $('#custMsgModalBg').hide();
}

$('#custMsgForm').on('submit', function(e) {
    e.preventDefault();
    const $status = $('#custMsgStatus');
    const $btn = $(this).find('button[type="submit"]');
    $status.hide();
    $btn.prop('disabled', true).text('Sending...');
    $.post(window.location.pathname, $(this).serialize(), function(resp) {
        if (resp.success) {
            $status.css('color','#28a745').text('Message sent!').show();
            setTimeout(() => {
                closeCustMsgModal();
                $btn.prop('disabled', false).text('Send Message');
            }, 800);
        } else {
            $status.css('color','#c00').text(resp.msg || 'Failed to send').show();
            $btn.prop('disabled', false).text('Send Message');
        }
    }, 'json').fail(function() {
        $status.css('color','#c00').text('Failed to send').show();
        $btn.prop('disabled', false).text('Send Message');
    });
});

// Bulk message modal functions
function openBulkMsgModal() {
    $('#bulkMsgText').val('');
    $('#bulkMsgStatus').hide().text('');
    $('#bulkMsgModalBg').css('display', 'flex');
}
function closeBulkMsgModal() {
    $('#bulkMsgModalBg').hide();
}
$('#bulkMsgForm').on('submit', function(e) {
    e.preventDefault();
    const $status = $('#bulkMsgStatus');
    const $btn = $(this).find('button[type="submit"]');
    const message = $('#bulkMsgText').val().trim();
    
    if (!message) {
        $status.css('color','#c00').text('Please enter a message').show();
        return;
    }
    
    if (!confirm('Send this message to ALL customers in the database?')) {
        return;
    }
    
    $status.hide();
    $btn.prop('disabled', true).text('Sending to all...');
    
    $.post(window.location.pathname, $(this).serialize(), function(resp) {
        if (resp.success) {
            $status.css('color','#28a745').text(resp.msg).show();
            setTimeout(() => {
                closeBulkMsgModal();
                $btn.prop('disabled', false).text('Send to All');
            }, 2000);
        } else {
            $status.css('color','#c00').text(resp.msg || 'Failed to send').show();
            $btn.prop('disabled', false).text('Send to All');
        }
    }, 'json').fail(function() {
        $status.css('color','#c00').text('Failed to send').show();
        $btn.prop('disabled', false).text('Send to All');
    });
});
</script>
<script src="../includes/responsive-tables.js"></script>
</body>
</html>
