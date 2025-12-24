<style>
.pagination .page-link {
    display: inline-block;
    margin: 0 3px;
    padding: 6px 12px;
    border-radius: 6px;
    background: #f9eaea;
    color: #800000;
    text-decoration: none;
    font-weight: 600;
    border: 1px solid #f3caca;
    min-width: 32px;
}
.pagination .page-link.current {
    background: #800000;
    color: #fff;
    border: 1px solid #800000;
}
.pagination .page-link.disabled {
    background: #f7f7fa;
    color: #bbb;
    border: 1px solid #eee;
    cursor: not-allowed;
}

/* Payment clickable style */
.payment-clickable { text-decoration: underline; color: #c00 !important; }
</style>
<script>
// Debounce helper
function debounce(fn, delay) {
    let timer = null;
    return function(...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.filter-bar');
    const searchInput = form.querySelector('input[name="search"]');
    const tableBody = document.getElementById('serviceTableBody');
    const paginationContainer = document.getElementById('pagination');
    let paginationState = {
        totalPages: <?= json_encode($totalPages) ?>,
        currentPage: <?= json_encode($page) ?>
    };

    // AJAX load function
    function loadTable(params) {
        const url = 'ajax_list.php?' + new URLSearchParams(params).toString();
        fetch(url)
            .then(r => r.text())
            .then(html => {
                const { rowsHtml, pagination } = parsePagination(html);
                tableBody.innerHTML = rowsHtml;
                if (pagination) {
                    paginationState = pagination;
                }
                renderPagination();
            });
    }

    // Extract pagination object from AJAX response
    function parsePagination(html) {
        let pagination = null;
        const scriptMatch = html.match(/<script[^>]*>[\s\S]*?<\/script>/i);
        if (scriptMatch) {
            const objMatch = scriptMatch[0].match(/window\.ajaxPagination\s*=\s*({[\s\S]*?})/);
            if (objMatch && objMatch[1]) {
                try {
                    pagination = Function('return ' + objMatch[1])();
                } catch (e) {
                    // Ignore parse errors; fallback to previous pagination state
                }
            }
            html = html.replace(scriptMatch[0], '');
        }
        return { rowsHtml: html, pagination };
    }

    // Gather current filter/search/page params
    function getParams(pageOverride) {
        const fd = new FormData(form);
        const params = Object.fromEntries(fd.entries());
        if (pageOverride) params.page = pageOverride;
        return params;
    }

    // Debounced search resets to first page
    const debouncedSearch = debounce(function() {
        paginationState.currentPage = 1;
        loadTable(getParams(1));
    }, 300);

    searchInput.addEventListener('input', debouncedSearch);

    // Render pagination buttons and wire clicks
    function renderPagination() {
        const totalPages = Math.max(1, paginationState.totalPages || 1);
        const currentPage = Math.max(1, Math.min(paginationState.currentPage || 1, totalPages));

        if (totalPages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        let html = '';

        // Prev
        if (currentPage > 1) {
            html += '<a href="#" class="page-link" data-page="' + (currentPage - 1) + '">&laquo; Previous</a> ';
        } else {
            html += '<span class="page-link disabled">&laquo; Previous</span> ';
        }

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                html += '<span class="page-link current">' + i + '</span> ';
            } else {
                html += '<a href="#" class="page-link" data-page="' + i + '">' + i + '</a> ';
            }
        }

        // Next
        if (currentPage < totalPages) {
            html += '<a href="#" class="page-link" data-page="' + (currentPage + 1) + '">Next &raquo;</a>';
        } else {
            html += '<span class="page-link disabled">Next &raquo;</span>';
        }

        paginationContainer.innerHTML = html;

        paginationContainer.querySelectorAll('.page-link[data-page]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetPage = parseInt(link.getAttribute('data-page'), 10) || 1;
                paginationState.currentPage = targetPage;
                loadTable(getParams(targetPage));
            });
        });
    }

    // Initial render for first page and fetch fresh data
    renderPagination();
    loadTable(getParams(1));

    // AJAX for filter change (category/status)
    form.querySelectorAll('select').forEach(sel => {
        sel.addEventListener('change', function() {
            loadTable(getParams(1));
        });
    });

    // AJAX for form submit (button)
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        loadTable(getParams(1));
    });

    // Payment popup logic
    document.body.insertAdjacentHTML('beforeend', `
    <div id="paymentModal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.25);align-items:center;justify-content:center;">
      <div style="background:#fff;padding:24px 18px 18px 18px;border-radius:12px;max-width:400px;width:96vw;box-shadow:0 4px 24px #80000033;position:relative;">
        <button id="closePaymentModal" style="position:absolute;top:8px;right:12px;font-size:1.3em;background:none;border:none;color:#800000;cursor:pointer;">&times;</button>
        <h2 style="color:#800000;font-size:1.1em;margin-bottom:12px;">Add Payment</h2>
        <div id="paymentDetails"></div>
        <form id="addPaymentForm" style="margin-top:10px;display:none;">
          <div style="margin-bottom:8px;">Total: <span id="modalTotal"></span></div>
          <div style="margin-bottom:8px;">Paid: <span id="modalPaid"></span></div>
          <div style="margin-bottom:8px;">Balance: <span id="modalBalance"></span></div>
          <label>Paying Amount: <input type="number" id="payingAmount" name="paying_amount" min="1" step="0.01" style="width:100px;"></label>
          <input type="hidden" id="modalServiceId" name="service_request_id">
          <button type="submit" class="form-btn" style="margin-top:10px;">Submit Payment</button>
        </form>
        <div id="previousPayments" style="margin-top:18px;"></div>
      </div>
    </div>
    `);
    const modal = document.getElementById('paymentModal');
    const closeBtn = document.getElementById('closePaymentModal');
    closeBtn.onclick = () => { modal.style.display = 'none'; };
    document.body.addEventListener('click', function(e) {
        if (e.target.classList.contains('payment-clickable')) {
            const id = e.target.getAttribute('data-id');
            const total = parseFloat(e.target.getAttribute('data-total'));
            const paid = parseFloat(e.target.getAttribute('data-paid'));
            const balance = parseFloat(e.target.getAttribute('data-balance'));
            document.getElementById('modalTotal').textContent = '₹'+total.toFixed(2);
            document.getElementById('modalPaid').textContent = '₹'+paid.toFixed(2);
            document.getElementById('modalBalance').textContent = '₹'+balance.toFixed(2);
            document.getElementById('modalServiceId').value = id;
            document.getElementById('payingAmount').value = '';
            document.getElementById('addPaymentForm').style.display = balance > 0 ? '' : 'none';
            // Fetch previous payments
            fetch('ajax_get_payments.php?service_request_id='+id)
              .then(r=>r.json()).then(data=>{
                let html = '<b>Previous Payments:</b>';
                if(data.length===0) html += '<div style="color:#888;">No payments yet.</div>';
                else {
                  html += '<ul style="padding-left:18px;">';
                  data.forEach(p=>{
                    html += '<li>₹'+parseFloat(p.amount).toFixed(2)+' on '+p.created_at+'</li>';
                  });
                  html += '</ul>';
                }
                document.getElementById('previousPayments').innerHTML = html;
              });
            modal.style.display = 'flex';
        }
    });
    document.getElementById('addPaymentForm').onsubmit = function(e) {
        e.preventDefault();
        const id = document.getElementById('modalServiceId').value;
        const amt = parseFloat(document.getElementById('payingAmount').value);
        if(isNaN(amt)||amt<=0) { alert('Enter valid amount'); return; }
        fetch('ajax_add_payment.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'service_request_id='+encodeURIComponent(id)+'&amount='+encodeURIComponent(amt)
        }).then(r=>r.json()).then(res=>{
            if(res.success) {
                alert('Payment added!');
                modal.style.display = 'none';
                location.reload();
            } else {
                alert('Error: '+(res.error||'Unknown error'));
            }
        });
    };
});
</script>
<?php
require_once __DIR__ . '/../../config/db.php';

/* ==============================
   SUMMARY COUNTS
   VISIBILITY CONTROL: Exclude appointment records from dashboard stats
   Appointments are managed separately in appointmentmanagement.php
   Filter: category_slug != 'appointment' excludes all appointment data
============================== */
$todayCount = $pdo->query(
    "SELECT COUNT(*) FROM service_requests WHERE DATE(created_at) = CURDATE() AND category_slug != 'appointment'"
)->fetchColumn();

$receivedCount = $pdo->query(
    "SELECT COUNT(*) FROM service_requests WHERE service_status = 'Received' AND category_slug != 'appointment'"
)->fetchColumn();

$inProgressCount = $pdo->query(
    "SELECT COUNT(*) FROM service_requests WHERE service_status = 'In Progress' AND category_slug != 'appointment'"
)->fetchColumn();

$completedCount = $pdo->query(
    "SELECT COUNT(*) FROM service_requests WHERE service_status = 'Completed' AND category_slug != 'appointment'"
)->fetchColumn();

/* ==============================
   FILTERS
============================== */
$statusOptions = ['All', 'Received', 'In Progress', 'Completed'];
$categoryOptions = [
    'All' => 'All Categories',
    'birth-child' => 'Birth & Child Services',
    'marriage-matching' => 'Marriage & Matching',
    'astrology-consultation' => 'Astrology Consultation',
    'muhurat-event' => 'Muhurat & Event Guidance',
    'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
];


$selectedStatus   = $_GET['status']   ?? 'All';
$selectedCategory = $_GET['category'] ?? 'All';
$search           = trim($_GET['search'] ?? '');


$where  = [];
$params = [];

// Pagination setup
$perPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;


if ($selectedStatus !== 'All') {
    $where[]  = 'service_status = ?';
    $params[] = $selectedStatus;
}
if ($selectedCategory !== 'All') {
    $where[]  = 'category_slug = ?';
    $params[] = $selectedCategory;
} else {
    // VISIBILITY CONTROL: When viewing "All Categories", explicitly exclude appointments
    // Appointments with category_slug = 'appointment' are now stored in service_requests table
    // but must be hidden from this generic service list and managed only in appointmentmanagement.php
    // This filter ensures they don't appear in the main services list or search results
    $where[] = "category_slug != 'appointment'";
}
if ($search !== '') {
    $where[] = '(tracking_id LIKE ? OR mobile LIKE ? OR customer_name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM service_requests $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $perPage));

// Main paginated query
$sql = "
    SELECT id, tracking_id, customer_name, mobile, category_slug,
           total_amount, payment_status, service_status, created_at, selected_products
    FROM service_requests
    $whereSql
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin – Service Requests</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body {
    font-family: Arial, sans-serif;
    background: #f7f7fa;
    margin: 0;
}
.admin-container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 24px 12px;
}
h1 {
    color: #800000;
    margin-bottom: 18px;
}

/* SUMMARY CARDS */
.summary-cards {
    display: flex;
    gap: 18px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.summary-card {
    flex: 1 1 180px;
    background: #fffbe7;
    border-radius: 14px;
    padding: 16px;
    text-align: center;
    box-shadow: 0 2px 8px #e0bebe22;
}
.summary-count {
    font-size: 2.2em;
    font-weight: 700;
    color: #800000;
}
.summary-label {
    font-size: 1em;
    color: #444;
}

/* FILTER BAR */
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
}
.filter-bar label {
    font-weight: 600;
}
.filter-bar select,
.filter-bar button {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 1em;
}
.filter-bar button {
    background: #800000;
    color: #fff;
    border: none;
    cursor: pointer;
}


/* TABLE */
.service-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    box-shadow: 0 2px 12px #e0bebe22;
    border-radius: 12px;
    overflow: hidden;
}
.service-table th,
.service-table td {
    padding: 12px 10px;
    border-bottom: 1px solid #f3caca;
    text-align: left;
}
.service-table th {
    background: #f9eaea;
    color: #800000;
}
.service-table tbody tr:hover {
    background: #f3f7fa;
    cursor: pointer;
}
.status-badge {
    padding: 4px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9em;
    display: inline-block;
    min-width: 80px;
    text-align: center;
}
/* Service Status Colors */
.status-received { background: #e5f0ff; color: #0056b3; }
.status-in-progress { background: #fffbe5; color: #b36b00; }
.status-completed { background: #e5ffe5; color: #1a8917; }
.status-cancelled { background: #ffeaea; color: #c00; }
/* Payment Status Colors */
.payment-paid { background: #e5ffe5; color: #1a8917; }
.payment-pending { background: #f7f7f7; color: #b36b00; }
.payment-failed { background: #ffeaea; color: #c00; }

.view-btn {
    background: #800000;
    color: #fff;
    padding: 6px 14px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
}

.no-data {
    text-align: center;
    color: #777;
    padding: 24px;
}

/* PAGINATION */
.pagination {
    display: flex;
    gap: 8px;
    justify-content: center;
    margin: 18px 0;
    flex-wrap: wrap;
}

.pagination a,
.pagination span {
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
    background: #fff;
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    color: #333;
}

.pagination .page-link.current {
    background: #800000;
    color: #fff;
    border-color: #800000;
}

.pagination .page-link.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

@media (max-width: 700px) {
    .summary-cards {
        flex-direction: column;
    }
}
</style>
</head>

<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">

<h1>Service Requests</h1>

<!-- SUMMARY CARDS -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-count"><?= $todayCount ?></div>
        <div class="summary-label">Today’s Requests</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?= $receivedCount ?></div>
        <div class="summary-label">Pending</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?= $inProgressCount ?></div>
        <div class="summary-label">In Progress</div>
    </div>
    <div class="summary-card">
        <div class="summary-count"><?= $completedCount ?></div>
        <div class="summary-label">Completed</div>
    </div>
</div>

<!-- FILTERS -->
<form class="filter-bar" method="get">
    <label>Category</label>
    <select name="category" onchange="this.form.submit()">
        <?php foreach ($categoryOptions as $k => $v): ?>
            <option value="<?= $k ?>" <?= $selectedCategory === $k ? 'selected' : '' ?>>
                <?= $v ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Status</label>
    <select name="status" onchange="this.form.submit()">
        <?php foreach ($statusOptions as $s): ?>
            <option value="<?= $s ?>" <?= $selectedStatus === $s ? 'selected' : '' ?>>
                <?= $s ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by Tracking ID, Mobile, or Customer Name" style="min-width:200px;" />
    <button type="submit">Apply</button>
</form>


<!-- LEGEND -->


<!-- TABLE -->

<table class="service-table">
<thead>
<tr>
    <th>Tracking ID</th>
    <th>Customer</th>
    <th>Mobile</th>
    <th>Product(s)</th>
    <th>Category</th>
    <th>Amount</th>
    <th>Payment</th>
    <th>Status</th>
    <th>Date</th>
    <th>Action</th>
</tr>
</thead>
<tbody id="serviceTableBody">
<?php if (!$requests): ?>
<tr>
    <td colspan="10" class="no-data">No service requests found.</td>
</tr>
<?php else: ?>
<?php foreach ($requests as $row): ?>
<tr>
    <td><?= htmlspecialchars($row['tracking_id']) ?></td>
    <td><?= htmlspecialchars($row['customer_name']) ?></td>
    <td><?= htmlspecialchars($row['mobile']) ?></td>
    <td>
        <?php
        $products = '-';
        $decoded = json_decode($row['selected_products'], true);
        if (is_array($decoded) && count($decoded)) {
            $names = [];
            foreach ($decoded as $prod) {
                if (isset($prod['id'])) {
                    // Fetch product name from DB (cache for performance)
                    static $productNameCache = [];
                    $pid = (int)$prod['id'];
                    if (!isset($productNameCache[$pid])) {
                        $pstmt = $pdo->prepare('SELECT product_name FROM products WHERE id = ?');
                        $pstmt->execute([$pid]);
                        $prow = $pstmt->fetch();
                        $productNameCache[$pid] = $prow ? $prow['product_name'] : 'Product#'.$pid;
                    }
                    $names[] = htmlspecialchars($productNameCache[$pid]);
                }
            }
            if ($names) {
                $products = implode(', ', $names);
            }
        }
        echo $products;
        ?>
    </td>
    <td>
        <?php
        $catMap = [
            'birth-child' => 'Birth & Child Services',
            'marriage-matching' => 'Marriage & Matching',
            'astrology-consultation' => 'Astrology Consultation',
            'muhurat-event' => 'Muhurat & Event Guidance',
            'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
        ];
        $catSlug = $row['category_slug'];
        echo isset($catMap[$catSlug]) ? htmlspecialchars($catMap[$catSlug]) : htmlspecialchars($catSlug);
        ?>
    </td>
    <td>₹<?= number_format($row['total_amount'], 2) ?></td>
    <td>
        <?php
        $payClass = 'payment-' . strtolower(str_replace(' ', '-', $row['payment_status']));
        $isOffline = !empty($row['selected_products']);
        $offlinePaymentHtml = '';
        if ($isOffline) {
            // Calculate paid amount
            $paid = 0;
            $payments = $pdo->prepare('SELECT amount FROM service_payments WHERE service_request_id = ?');
            $payments->execute([$row['id']]);
            $allPayments = $payments->fetchAll(PDO::FETCH_ASSOC);
            foreach ($allPayments as $p) $paid += (float)$p['amount'];
            $total = (float)$row['total_amount'];
            $balance = $total - $paid;
            $statusText = 'Unpaid';
            $payClass = 'payment-failed';
            if ($paid > 0 && $balance > 0) {
                $statusText = 'Partial Paid';
                $payClass = 'payment-pending';
            } elseif ($paid >= $total && $total > 0) {
                $statusText = 'Paid';
                $payClass = 'payment-paid';
            }
            $offlinePaymentHtml = '<span class="status-badge '.$payClass.' payment-clickable" style="cursor:pointer;" data-id="'.$row['id'].'" data-total="'.$total.'" data-paid="'.$paid.'" data-balance="'.$balance.'">'.$statusText.'</span>';
            echo $offlinePaymentHtml;
        } else {
        ?>
        <span class="status-badge <?= $payClass ?>">
            <?= htmlspecialchars($row['payment_status']) ?>
        </span>
        <?php }
</td>
    <td>
        <?php
        $statusClass = 'status-' . strtolower(str_replace(' ', '-', $row['service_status']));
        ?>
        <span class="status-badge <?= $statusClass ?>">
            <?= htmlspecialchars($row['service_status']) ?>
        </span>
    </td>
    <td><?= date('d-m-Y', strtotime($row['created_at'])) ?></td>
    <td><a class="view-btn" href="view.php?id=<?= $row['id'] ?>">View</a></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>

<!-- PAGINATION CONTROLS -->
<div id="pagination" class="pagination"></div>

</div>
</body>
</html>
