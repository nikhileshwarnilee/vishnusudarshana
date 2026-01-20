<?php
// admin/customers/crm/index.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/top-menu.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/send_whatsapp.php';

// Handle AJAX send message (single/bulk)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    $data = $_POST;
    if (empty($data) && $body) {
        $json = json_decode($body, true);
        if (is_array($json)) $data = $json;
    }
    $action = $data['action'] ?? '';
    if (in_array($action, ['send_single', 'send_bulk'], true)) {
        $message = trim($data['message'] ?? '');
        $searchFilter = trim($data['search'] ?? '');
        $selectAll = !empty($data['select_all']);
        $recipients = [];

        if ($action === 'send_single') {
            $name = trim($data['name'] ?? '');
            $mobile = trim($data['mobile'] ?? '');
            if ($name && $mobile) {
                $recipients[] = ['name' => $name, 'mobile' => $mobile];
            }
        } else { // send_bulk
            if (!$selectAll && !empty($data['recipients']) && is_array($data['recipients'])) {
                $recipients = $data['recipients'];
            } else {
                // Fetch all customers (filtered by search if provided)
                $params = [];
                $where = '';
                if ($searchFilter !== '') {
                    $where = "WHERE name LIKE :q OR mobile LIKE :q OR address_city LIKE :q";
                    $params['q'] = "%$searchFilter%";
                }
                $sqlAll = "
                    SELECT name, mobile, address as address_city FROM cif_clients
                    UNION ALL
                    SELECT name, mobile, address as address_city FROM customers
                    UNION ALL
                    SELECT customer_name as name, mobile, city as address_city FROM service_requests
                ";
                if ($where !== '') {
                    $sqlAll = "SELECT * FROM (" . $sqlAll . ") AS all_customers $where";
                }
                $stmtAll = $pdo->prepare($sqlAll);
                $stmtAll->execute($params);
                $recipients = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        if (!$message || empty($recipients)) {
            echo json_encode(['success' => false, 'msg' => 'Message and recipients are required.']);
            exit;
        }

        $sent = 0; $failed = 0;
        foreach ($recipients as $r) {
            $mobile = trim($r['mobile'] ?? '');
            $name = trim($r['name'] ?? '');
            if (!$mobile || !$name) { $failed++; continue; }
            $res = sendWhatsAppMessage($mobile, 'APPOINTMENT_MESSAGE', [
                'name' => $name,
                'message' => $message
            ]);
            if (!empty($res['success'])) {
                $sent++;
            } else {
                $failed++;
            }
        }
        echo json_encode(['success' => true, 'sent' => $sent, 'failed' => $failed]);
        exit;
    }
}

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
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
        .admin-container { max-width: 1100px; margin: 0 auto; padding: 24px 12px; }
        h1 { color: #800000; margin-bottom: 18px; }
        .filter-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 18px; }
        .filter-bar input { min-width: 220px; padding: 7px 12px; border-radius: 6px; font-size: 1em; border: 1px solid #ddd; }
        .filter-bar .btn-main { padding: 8px 16px; background: #800000; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .filter-bar .btn-main:hover { background: #600000; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 8px #e0bebe22; border-radius: 12px; overflow: hidden; }
        table th, table td { padding: 12px 10px; text-align: left; }
        table thead { background: #f9eaea; color: #800000; }
        table tbody tr:hover { background: #f1f1f1; }
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
        <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>" placeholder="Search customers...">
        <button type="submit" class="btn-main">Search</button>
        <button type="button" class="btn-main" style="background:#007bff;" onclick="selectAllCustomersDb()">Select All Customers (<?= $total_customers ?>)</button>
        <button type="button" class="btn-main" style="background:#25D366;" onclick="openBulkMsgModal()">Send Bulk Msgs</button>
    </form>
    <table>
        <thead>
            <tr>
                <th style="width:40px;"><input type="checkbox" id="selectAll"></th>
                <th>Name</th>
                <th>Mobile</th>
                <th>Address/City</th>
                <th>Msg</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($customers)): ?>
            <tr><td colspan="5" style="text-align:center;color:#888;">No customers found.</td></tr>
        <?php else: foreach ($customers as $idx => $c): ?>
            <tr>
                <td><input type="checkbox" class="row-check" data-name="<?= htmlspecialchars($c['name']) ?>" data-mobile="<?= htmlspecialchars($c['mobile']) ?>"></td>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td><?= htmlspecialchars($c['mobile']) ?></td>
                <td><?= htmlspecialchars($c['address_city']) ?></td>
                <td><button type="button" style="background:#25D366;color:#fff;border:none;border-radius:6px;padding:6px 10px;font-weight:600;cursor:pointer;" onclick="openSingleMsgModal('<?= htmlspecialchars(addslashes($c['name'])) ?>','<?= htmlspecialchars($c['mobile']) ?>')">Send Msg</button></td>
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
<script>
// Select all checkboxes and DB-wide selection
let selectAllDb = false;
const selectAll = document.getElementById('selectAll');
const rowChecks = () => Array.from(document.querySelectorAll('.row-check'));
const updateBulkSelectedCount = () => {
    const note = document.getElementById('bulkSelectionNote');
    if (!note) return;
    const total = Number(note.dataset.total || 0);
    if (selectAllDb) {
        note.textContent = `Selected: ${total} customer${total === 1 ? '' : 's'} (all pages)`;
        note.style.color = '#007bff';
        return;
    }
    const count = rowChecks().filter(cb => cb.checked).length;
    if (count > 0) {
        note.textContent = `Selected: ${count} customer${count > 1 ? 's' : ''}`;
        note.style.color = '#007bff';
    } else {
        note.textContent = 'None selected - will send to all in this list.';
        note.style.color = '#444';
    }
};
const selectAllCustomersDb = () => {
    selectAllDb = true;
    rowChecks().forEach(cb => cb.checked = false);
    if (selectAll) selectAll.checked = false;
    updateBulkSelectedCount();
};
if (selectAll) {
    selectAll.addEventListener('change', () => {
        selectAllDb = false;
        rowChecks().forEach(cb => cb.checked = selectAll.checked);
        updateBulkSelectedCount();
    });
}
rowChecks().forEach(cb => cb.addEventListener('change', () => {
    selectAllDb = false;
    updateBulkSelectedCount();
}));
updateBulkSelectedCount();

function openSingleMsgModal(name, mobile) {
    if (!mobile) { alert('No mobile number found.'); return; }
    document.getElementById('msgNameInput').value = name;
    document.getElementById('msgMobileInput').value = mobile;
    document.getElementById('msgNameLabel').textContent = name;
    document.getElementById('msgMobileLabel').textContent = mobile;
    document.getElementById('msgText').value = '';
    document.getElementById('msgStatus').style.display = 'none';
    document.getElementById('msgModalBg').style.display = 'flex';
}
function closeMsgModal() {
    document.getElementById('msgModalBg').style.display = 'none';
}

function openBulkMsgModal() {
    document.getElementById('bulkText').value = '';
    document.getElementById('bulkStatus').style.display = 'none';
    updateBulkSelectedCount();
    document.getElementById('bulkModalBg').style.display = 'flex';
}
function closeBulkModal() {
    document.getElementById('bulkModalBg').style.display = 'none';
}

document.getElementById('msgForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const status = document.getElementById('msgStatus');
    status.style.display = 'none';
    const formData = new FormData(this);
    fetch(window.location.pathname, {
        method: 'POST',
        body: formData
    }).then(res => res.json()).then(data => {
        if (data.success) {
            status.style.color = '#28a745';
            status.textContent = 'Message sent!';
            status.style.display = 'block';
            setTimeout(() => { closeMsgModal(); }, 800);
        } else {
            status.style.color = '#c00';
            status.textContent = data.msg || 'Failed to send';
            status.style.display = 'block';
        }
    }).catch(() => {
        status.style.color = '#c00';
        status.textContent = 'Failed to send';
        status.style.display = 'block';
    });
});

document.getElementById('bulkForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const status = document.getElementById('bulkStatus');
    status.style.display = 'none';
    const selected = rowChecks()
        .filter(cb => cb.checked)
        .map(cb => ({name: cb.dataset.name, mobile: cb.dataset.mobile}));
    const payload = {
        action: 'send_bulk',
        message: document.getElementById('bulkText').value,
        recipients: selectAllDb ? [] : selected,
        select_all: selectAllDb ? 1 : 0,
        search: document.getElementById('searchInput') ? document.getElementById('searchInput').value : ''
    };
    fetch(window.location.pathname, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    }).then(res => res.json()).then(data => {
        if (data.success) {
            status.style.color = '#28a745';
            status.textContent = `Sent: ${data.sent || 0}${data.failed ? `, Failed: ${data.failed}` : ''}`;
            status.style.display = 'block';
            setTimeout(() => { closeBulkModal(); }, 900);
        } else {
            status.style.color = '#c00';
            status.textContent = data.msg || 'Failed to send';
            status.style.display = 'block';
        }
    }).catch(() => {
        status.style.color = '#c00';
        status.textContent = 'Failed to send';
        status.style.display = 'block';
    });
});
</script>

<!-- Custom Message Modal -->
<div id="msgModalBg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:1000; align-items:center; justify-content:center;">
    <div id="msgModal" style="background:#fff; border-radius:12px; box-shadow:0 2px 16px #80000033; padding:28px 24px 18px 24px; min-width:340px; max-width:95vw; width:420px; text-align:left; position:relative;">
        <div style="font-size:1.12em;color:#007bff;font-weight:700;margin-bottom:10px;">Send Message</div>
        <form id="msgForm" autocomplete="off">
            <input type="hidden" name="action" value="send_single">
            <input type="hidden" name="name" id="msgNameInput">
            <input type="hidden" name="mobile" id="msgMobileInput">
            <div style="margin-bottom:10px;color:#444;"><b>Customer:</b> <span id="msgNameLabel"></span></div>
            <div style="margin-bottom:10px;color:#444;"><b>Mobile:</b> <span id="msgMobileLabel"></span></div>
            <div style="margin-bottom:10px;">
                <label for="msgText" style="display:block; margin-bottom:6px;"><b>Message:</b></label>
                <textarea name="message" id="msgText" style="width:100%;height:110px;padding:8px;border-radius:6px;border:1px solid #ccc;font-family:Arial,sans-serif;resize:vertical;" placeholder="Enter your custom message..." required></textarea>
            </div>
            <div style="margin-top:14px;text-align:center;">
                <button type="submit" style="background:#25D366;color:#fff;padding:8px 22px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Send Message</button>
                &nbsp;
                <button type="button" onclick="closeMsgModal()" style="background:#800000;color:#fff;padding:8px 22px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
            </div>
            <div id="msgStatus" style="margin-top:10px; color:#c00; display:none;"></div>
        </form>
    </div>
</div>

<!-- Bulk Message Modal -->
<div id="bulkModalBg" style="display:none; position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:1000; align-items:center; justify-content:center;">
    <div id="bulkModal" style="background:#fff; border-radius:12px; box-shadow:0 2px 16px #80000033; padding:28px 24px 18px 24px; min-width:340px; max-width:95vw; width:420px; text-align:left; position:relative;">
        <div style="font-size:1.12em;color:#007bff;font-weight:700;margin-bottom:10px;">Send Bulk Messages</div>
        <form id="bulkForm" autocomplete="off">
            <input type="hidden" name="action" value="send_bulk">
            <div id="bulkSelectionNote" data-total="<?= $total_customers ?>" style="margin-bottom:10px;color:#444;">None selected - will send to all in this list.</div>
            <div style="margin-bottom:10px;">
                <label for="bulkText" style="display:block; margin-bottom:6px;"><b>Message:</b></label>
                <textarea name="message" id="bulkText" style="width:100%;height:110px;padding:8px;border-radius:6px;border:1px solid #ccc;font-family:Arial,sans-serif;resize:vertical;" placeholder="Enter your custom message..." required></textarea>
            </div>
            <div style="margin-top:14px;text-align:center;">
                <button type="submit" style="background:#25D366;color:#fff;padding:8px 22px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Send Bulk</button>
                &nbsp;
                <button type="button" onclick="closeBulkModal()" style="background:#800000;color:#fff;padding:8px 22px;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
            </div>
            <div id="bulkStatus" style="margin-top:10px; color:#c00; display:none;"></div>
        </form>
    </div>
</div>
</body>
</html>
