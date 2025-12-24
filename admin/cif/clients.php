<?php
// admin/cif/clients.php
require_once __DIR__ . '/../../config/db.php';

$msg = '';
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_client = null;

// Handle add or update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['mobile'])) {
    $name = trim($_POST['name']);
    $mobile = trim($_POST['mobile']);
    $address = trim($_POST['address'] ?? '');
    $dob = $_POST['dob'] ?? null;
    $birth_time = $_POST['birth_time'] ?? null;
    $birth_place = trim($_POST['birth_place'] ?? '');
    $id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    if ($name !== '' && $mobile !== '') {
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE cif_clients SET name=?, mobile=?, address=?, dob=?, birth_time=?, birth_place=? WHERE id=?');
            $stmt->execute([$name, $mobile, $address, $dob, $birth_time, $birth_place, $id]);
            $msg = 'Client updated!';
            $edit_id = 0;
            header('Location: clients.php');
            exit;
        } else {
            $stmt = $pdo->prepare('INSERT INTO cif_clients (name, mobile, address, dob, birth_time, birth_place) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $mobile, $address, $dob, $birth_time, $birth_place]);
            $msg = 'Client added!';
        }
    } else {
        $msg = 'Name and mobile are required.';
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare('DELETE FROM cif_clients WHERE id = ?')->execute([$id]);
    header('Location: clients.php');
    exit;
}

// If editing, fetch the client
if ($edit_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM cif_clients WHERE id = ?');
    $stmt->execute([$edit_id]);
    $edit_client = $stmt->fetch();
}

// Pagination and search logic
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$perPage = 10;
$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE name LIKE :q OR mobile LIKE :q OR address LIKE :q OR birth_place LIKE :q";
    $params['q'] = "%$search%";
}
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM cif_clients $where");
$countStmt->execute($params);
$total_clients = (int)$countStmt->fetchColumn();
$total_pages = max(1, ceil($total_clients / $perPage));
$offset = ($page - 1) * $perPage;
$listStmt = $pdo->prepare("SELECT * FROM cif_clients $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$listStmt->execute($params);
$clients = $listStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CIF Clients</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: Arial, sans-serif; background: #f7f7fa; margin: 0; }
.admin-container { max-width: 1100px; margin: 0 auto; padding: 24px 12px; }
h1 { color: #800000; margin-bottom: 18px; }
.summary-cards { display: flex; gap: 18px; margin-bottom: 24px; flex-wrap: wrap; }
.summary-card { flex: 1 1 180px; background: #fffbe7; border-radius: 14px; padding: 16px; text-align: center; box-shadow: 0 2px 8px #e0bebe22; }
.summary-count { font-size: 2.2em; font-weight: 700; color: #800000; }
.summary-label { font-size: 1em; color: #444; }
@media (max-width: 700px) { .summary-cards { flex-direction: column; } }
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
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>CIF Clients</h1>
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-count"><?= $total_clients ?></div>
            <div class="summary-label">Clients</div>
        </div>
    </div>
    <div style="background:#fff;padding:24px;border-radius:12px;box-shadow:0 2px 8px #e0bebe22;">
        <h2 style="color:#800000;"> <?= $edit_client ? 'Edit Client' : 'Add Client' ?> </h2>
        <?php if ($msg): ?>
            <div style="color: #800000; font-weight: 600; margin-bottom: 12px;"> <?= htmlspecialchars($msg) ?> </div>
        <?php endif; ?>
        <form method="post" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;margin-bottom:24px;">
            <input type="text" name="name" placeholder="Name" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= $edit_client ? htmlspecialchars($edit_client['name']) : '' ?>">
            <input type="text" name="mobile" placeholder="Mobile No" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= $edit_client ? htmlspecialchars($edit_client['mobile']) : '' ?>">
            <input type="text" name="address" placeholder="Address" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;min-width:180px;" value="<?= $edit_client ? htmlspecialchars($edit_client['address']) : '' ?>">
            <input type="date" name="dob" placeholder="DOB" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= $edit_client && $edit_client['dob'] ? htmlspecialchars($edit_client['dob']) : '' ?>">
            <input type="time" name="birth_time" placeholder="Birth Time" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= $edit_client && $edit_client['birth_time'] ? htmlspecialchars($edit_client['birth_time']) : '' ?>">
            <input type="text" name="birth_place" placeholder="Birth Place" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= $edit_client ? htmlspecialchars($edit_client['birth_place']) : '' ?>">
            <?php if ($edit_client): ?>
                <input type="hidden" name="edit_id" value="<?= (int)$edit_client['id'] ?>">
            <?php endif; ?>
            <button type="submit" style="padding:10px 22px;background:#800000;color:#fff;border:none;border-radius:6px;font-weight:600;">
                <?= $edit_client ? 'Update Client' : 'Add Client' ?>
            </button>
            <?php if ($edit_client): ?>
                <a href="clients.php" style="padding:10px 22px;background:#6c757d;color:#fff;border:none;border-radius:6px;font-weight:600;text-decoration:none;">Cancel</a>
            <?php endif; ?>
        </form>
        <h2 style="color:#800000;">Clients List</h2>
        <form id="clientsSearchForm" class="filter-bar" onsubmit="return false;" style="margin-bottom:18px;gap:12px;align-items:center;">
            <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>" placeholder="Search clients..." style="min-width:220px;padding:7px 12px;border-radius:6px;font-size:1em;border:1px solid #ddd;">
            <button type="submit" class="btn-main">Search</button>
        </form>
        <div id="clientsAjaxResult">
        <table style="width:100%;border-collapse:collapse;background:#fff;box-shadow:0 2px 8px #e0bebe22;border-radius:12px;overflow:hidden;">
            <thead>
                <tr style="background:#f9eaea;color:#800000;">
                    <th style="padding:12px 10px;">#</th>
                    <th style="padding:12px 10px;">Name</th>
                    <th style="padding:12px 10px;">Mobile</th>
                    <th style="padding:12px 10px;">Address</th>
                    <th style="padding:12px 10px;">DOB</th>
                    <th style="padding:12px 10px;">Birth Time</th>
                    <th style="padding:12px 10px;">Birth Place</th>
                    <th style="padding:12px 10px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clients)): ?>
                    <tr><td colspan="8" style="text-align:center;color:#777;padding:24px;">No clients found.</td></tr>
                <?php else: foreach ($clients as $c): ?>
                    <tr>
                        <td style="padding:10px;"> <?= (int)$c['id'] ?> </td>
                        <td style="padding:10px;"> <?= htmlspecialchars($c['name']) ?> </td>
                        <td style="padding:10px;"> <?= htmlspecialchars($c['mobile']) ?> </td>
                        <td style="padding:10px;"> <?= htmlspecialchars($c['address']) ?> </td>
                        <td style="padding:10px;"> <?= htmlspecialchars($c['dob']) ?> </td>
                        <td style="padding:10px;"> <?= htmlspecialchars($c['birth_time']) ?> </td>
                        <td style="padding:10px;"> <?= htmlspecialchars($c['birth_place']) ?> </td>
                        <td style="padding:10px;">
                            <a href="clients.php?edit=<?= (int)$c['id'] ?>" style="padding:6px 14px;background:#007bff;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;margin-right:8px;">Edit</a>
                            <a href="clients.php?delete=<?= (int)$c['id'] ?>" onclick="return confirm('Delete this client?');" style="padding:6px 14px;background:#dc3545;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <div style="margin-top:18px;text-align:center;">
            <?php
            $maxPagesToShow = 7;
            $startPage = max(1, $page - 2);
            $endPage = min($total_pages, $page + 2);
            if ($startPage > 1) {
                echo '<a href="#" class="page-link" data-page="1" style="display:inline-block;padding:8px 14px;margin:0 2px;border-radius:6px;background:' . (1 == $page ? '#800000' : '#f9eaea') . ';color:' . (1 == $page ? '#fff' : '#800000') . ';font-weight:600;text-decoration:none;">1</a>';
                if ($startPage > 2) echo '<span style="padding:8px 6px;">...</span>';
            }
            for ($i = $startPage; $i <= $endPage; $i++) {
                echo '<a href="#" class="page-link" data-page="' . $i . '" style="display:inline-block;padding:8px 14px;margin:0 2px;border-radius:6px;background:' . ($i == $page ? '#800000' : '#f9eaea') . ';color:' . ($i == $page ? '#fff' : '#800000') . ';font-weight:600;text-decoration:none;">' . $i . '</a>';
            }
            if ($endPage < $total_pages) {
                if ($endPage < $total_pages - 1) echo '<span style="padding:8px 6px;">...</span>';
                echo '<a href="#" class="page-link" data-page="' . $total_pages . '" style="display:inline-block;padding:8px 14px;margin:0 2px;border-radius:6px;background:' . ($total_pages == $page ? '#800000' : '#f9eaea') . ';color:' . ($total_pages == $page ? '#fff' : '#800000') . ';font-weight:600;text-decoration:none;">' . $total_pages . '</a>';
            }
            ?>
        </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
function loadClientsAjax(page) {
    var data = $('#clientsSearchForm').serialize();
    if (page) data += '&page=' + page;
    $.get(window.location.pathname, data + '&ajax=1', function(res) {
        var html = $(res).find('#clientsAjaxResult').html();
        $('#clientsAjaxResult').html(html);
    });
}
$('#searchInput').on('input', function() {
    loadClientsAjax(1);
});
$('#clientsSearchForm').on('submit', function() {
    loadClientsAjax(1);
    return false;
});
$(document).on('click', '.page-link', function(e) {
    e.preventDefault();
    var page = $(this).data('page');
    loadClientsAjax(page);
});
</script>
</body>
</html>
