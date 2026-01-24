<?php
// AJAX: live search for client name
if (isset($_GET['ajax_search_client']) && isset($_GET['name'])) {
    require_once __DIR__ . '/../../config/db.php';
    $name = trim($_GET['name']);
    $out = [];
    if (strlen($name) >= 3) {
        $stmt = $pdo->prepare('SELECT id, name, mobile FROM cif_clients WHERE name LIKE ? ORDER BY name ASC LIMIT 10');
        $stmt->execute(['%' . $name . '%']);
        $out = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}
// admin/cif/index.php
// CIF main page styled like Appointments

require_once __DIR__ . '/../../config/db.php';


$selected_client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$show_add_client = isset($_GET['add_client']);
$msg = '';
$edit_enquiry_id = isset($_GET['edit_enquiry']) ? (int)$_GET['edit_enquiry'] : 0;

// Handle add client + enquiry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_client_and_enquiry'])) {
    $name = trim($_POST['name']);
    $mobile = trim($_POST['mobile']);
    $address = trim($_POST['address'] ?? '');
    $dob = $_POST['dob'] ?? null;
    $birth_time = $_POST['birth_time'] ?? null;
    $birth_place = trim($_POST['birth_place'] ?? '');
    $category_id = (int)$_POST['category_id'];
    $enquiry_date = $_POST['enquiry_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    if ($name !== '' && $category_id > 0 && $enquiry_date) {
        $stmt = $pdo->prepare('INSERT INTO cif_clients (name, mobile, address, dob, birth_time, birth_place) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $mobile, $address, $dob, $birth_time, $birth_place]); // mobile and address can be blank
        $client_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO cif_enquiries (client_id, category_id, enquiry_date, notes) VALUES (?, ?, ?, ?)');
        $stmt->execute([$client_id, $category_id, $enquiry_date, $notes]);
        $msg = 'Client and enquiry added!';
        header('Location: index.php?client_id=' . $client_id);
        exit;
    } else {
        $msg = 'Please fill all client and enquiry fields.';
        $show_add_client = true;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_enquiry_notes'])) {
    $enquiry_id = (int)$_POST['enquiry_id'];
    $notes = trim($_POST['notes'] ?? '');
    $client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
    $stmt = $pdo->prepare('UPDATE cif_enquiries SET notes = ? WHERE id = ?');
    $stmt->execute([$notes, $enquiry_id]);
    $msg = 'Notes updated!';
    $selected_client_id = $client_id;
    header('Location: index.php?client_id=' . $client_id);
    exit;
}

// Handle delete enquiry
if (isset($_GET['delete_enquiry']) && $selected_client_id > 0) {
    $del_id = (int)$_GET['delete_enquiry'];
    $stmt = $pdo->prepare('DELETE FROM cif_enquiries WHERE id = ? AND client_id = ?');
    $stmt->execute([$del_id, $selected_client_id]);
    $msg = 'Enquiry deleted!';
    header('Location: index.php?client_id=' . $selected_client_id);
    exit;
}

// Handle add enquiry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_enquiry'])) {
    $client_id = (int)$_POST['client_id'];
    $category_id = (int)$_POST['category_id'];
    $enquiry_date = $_POST['enquiry_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    if ($client_id > 0 && $category_id > 0 && $enquiry_date) {
        $stmt = $pdo->prepare('INSERT INTO cif_enquiries (client_id, category_id, enquiry_date, notes) VALUES (?, ?, ?, ?)');
        $stmt->execute([$client_id, $category_id, $enquiry_date, $notes]);
        $msg = 'Enquiry added!';
        $selected_client_id = $client_id;
        header('Location: index.php?client_id=' . $client_id);
        exit;
    } else {
        $msg = 'Please fill all enquiry fields.';
    }
}

$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_client = null;

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id = (int)$_POST['edit_id'];
    $name = trim($_POST['name']);
    $mobile = trim($_POST['mobile']);
    $address = trim($_POST['address'] ?? '');
    $dob = $_POST['dob'] ?? null;
    $birth_time = $_POST['birth_time'] ?? null;
    $birth_place = trim($_POST['birth_place'] ?? '');
    if ($name !== '' && $mobile !== '') {
        $stmt = $pdo->prepare('UPDATE cif_clients SET name=?, mobile=?, address=?, dob=?, birth_time=?, birth_place=? WHERE id=?');
        $stmt->execute([$name, $mobile, $address, $dob, $birth_time, $birth_place, $id]);
        $msg = 'Client updated!';
        $selected_client_id = $id;
        header('Location: index.php?client_id=' . $id);
        exit;
    } else {
        $msg = 'Name and mobile are required.';
    }
}

// Fetch all clients for dropdown
$clients = $pdo->query('SELECT * FROM cif_clients ORDER BY name ASC')->fetchAll();

// If editing, fetch the client
if ($edit_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM cif_clients WHERE id = ?');
    $stmt->execute([$edit_id]);
    $edit_client = $stmt->fetch();
    $selected_client_id = $edit_id;
}

// Fetch selected client details
$selected_client = null;
if ($selected_client_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM cif_clients WHERE id = ?');
    $stmt->execute([$selected_client_id]);
    $selected_client = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CIF Panel</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Admin panel styles (copied from appointments) -->
<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
@media (max-width: 700px) {
    .summary-cards { flex-direction: column; }
}
</style>

<!-- Add Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <h1>CIF Panel</h1>
    <!-- Stats removed as per request -->
    <div style="background:#fff;padding:24px;border-radius:12px;box-shadow:0 2px 8px #e0bebe22;">
        <div style="margin-bottom:18px;">
            <a href="index.php?add_client=1" style="padding:10px 22px;background:#28a745;color:#fff;border:none;border-radius:6px;font-weight:600;text-decoration:none;">Add Client</a>
        </div>
        <?php if ($show_add_client): ?>
            <form method="post" id="addClientForm" autocomplete="off" style="position:relative;display:flex;gap:18px;align-items:center;flex-wrap:wrap;margin-bottom:24px;background:#f9eaea;padding:18px;border-radius:10px;">
                <div style="position:relative;">
                    <input type="text" name="name" id="clientNameInput" placeholder="Name" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    <div id="clientSearchResults" style="display:none;position:absolute;top:110%;left:0;z-index:10;background:#fff;border:1px solid #ccc;border-radius:8px;box-shadow:0 2px 8px #e0bebe22;min-width:220px;max-height:220px;overflow-y:auto;"></div>
                </div>
                <input type="text" name="mobile" placeholder="Mobile No" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>">
                <input type="text" name="address" placeholder="Address" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;min-width:180px;" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                <input type="date" name="dob" placeholder="DOB" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
                <input type="time" name="birth_time" placeholder="Birth Time" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= htmlspecialchars($_POST['birth_time'] ?? '') ?>">
                <input type="text" name="birth_place" placeholder="Birth Place" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= htmlspecialchars($_POST['birth_place'] ?? '') ?>">
                <select name="category_id" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;">
                    <option value="">-- Select Category --</option>
                    <?php $categories = $pdo->query('SELECT * FROM cif_categories ORDER BY name ASC')->fetchAll();
                    foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="enquiry_date" value="<?= htmlspecialchars($_POST['enquiry_date'] ?? date('Y-m-d')) ?>" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;">
                <textarea name="notes" placeholder="Notes" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;min-width:360px;min-height:80px;"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                <input type="hidden" name="add_client_and_enquiry" value="1">
                <button type="submit" style="padding:10px 22px;background:#800000;color:#fff;border:none;border-radius:6px;font-weight:600;">Add Client & Enquiry</button>
                <a href="index.php" style="padding:10px 22px;background:#6c757d;color:#fff;border:none;border-radius:6px;font-weight:600;text-decoration:none;">Cancel</a>
            </form>
            <script>
            document.getElementById('clientNameInput').addEventListener('input', function() {
                var val = this.value.trim();
                var resultsBox = document.getElementById('clientSearchResults');
                if (val.length < 3) {
                    resultsBox.style.display = 'none';
                    resultsBox.innerHTML = '';
                    return;
                }
                var xhr = new XMLHttpRequest();
                xhr.open('GET', 'index.php?ajax_search_client=1&name=' + encodeURIComponent(val), true);
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        var data = JSON.parse(xhr.responseText);
                        if (data.length > 0) {
                            resultsBox.innerHTML = data.map(function(c) {
                                return '<div style="padding:8px 12px;cursor:pointer;" onclick="window.location=\'index.php?client_id=' + c.id + '\'">' +
                                    '<b>' + c.name + '</b> <span style=\'color:#888;\'>' + c.mobile + '</span>' +
                                    '</div>';
                            }).join('');
                            resultsBox.style.display = 'block';
                        } else {
                            resultsBox.innerHTML = '<div style="padding:8px 12px;color:#888;">No matches</div>';
                            resultsBox.style.display = 'block';
                        }
                    }
                };
                xhr.send();
            });
            document.addEventListener('click', function(e) {
                var box = document.getElementById('clientSearchResults');
                if (!box.contains(e.target) && e.target.id !== 'clientNameInput') {
                    box.style.display = 'none';
                }
            });
            </script>
        <?php endif; ?>
        <h2 style="color:#800000;">Select Client</h2>
        <form method="get" style="margin-bottom:24px;display:flex;gap:18px;align-items:center;">
            <select id="clientSelect" name="client_id" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;min-width:220px;width:320px;">
                <option value="">-- Select Client --</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $selected_client_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?><?= $c['mobile'] ? ' - ' . htmlspecialchars($c['mobile']) : '' ?></option>
                <?php endforeach; ?>
            </select>
        </form>
<!-- Add Select2 JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function() {
    $('#clientSelect').select2({
        placeholder: '-- Select Client --',
        allowClear: true,
        ajax: {
            url: 'index.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    ajax_search_client: 1,
                    name: params.term || ''
                };
            },
            processResults: function(data) {
                return {
                    results: data.map(function(c) {
                        return {
                            id: c.id,
                            text: c.name + (c.mobile ? ' - ' + c.mobile : '')
                        };
                    })
                };
            },
            cache: true
        },
        minimumInputLength: 2
    });
    $('#clientSelect').on('change', function() {
        this.form.submit();
    });
});
</script>

        <?php if ($selected_client): ?>
            <h2 style="color:#800000;">Client Details</h2>
            <?php if ($edit_client): ?>
                <form method="post" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;margin-bottom:24px;">
                    <input type="text" name="name" placeholder="Name" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= htmlspecialchars($edit_client['name']) ?>">
                    <input type="text" name="mobile" placeholder="Mobile No" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= htmlspecialchars($edit_client['mobile']) ?>">
                    <input type="text" name="address" placeholder="Address" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;min-width:180px;" value="<?= htmlspecialchars($edit_client['address']) ?>">
                    <input type="date" name="dob" placeholder="DOB" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= $edit_client['dob'] ? htmlspecialchars($edit_client['dob']) : '' ?>">
                    <input type="time" name="birth_time" placeholder="Birth Time" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= $edit_client['birth_time'] ? htmlspecialchars($edit_client['birth_time']) : '' ?>">
                    <input type="text" name="birth_place" placeholder="Birth Place" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= htmlspecialchars($edit_client['birth_place']) ?>">
                    <input type="hidden" name="edit_id" value="<?= (int)$edit_client['id'] ?>">
                    <button type="submit" style="padding:10px 22px;background:#800000;color:#fff;border:none;border-radius:6px;font-weight:600;">Update Client</button>
                    <a href="index.php?client_id=<?= (int)$edit_client['id'] ?>" style="padding:10px 22px;background:#6c757d;color:#fff;border:none;border-radius:6px;font-weight:600;text-decoration:none;">Cancel</a>
                </form>
            <?php else: ?>
                <div style="display:flex;align-items:center;gap:18px;margin-bottom:18px;">
                    <form method="get" action="" style="margin:0;">
                        <input type="hidden" name="client_id" value="<?= (int)$selected_client['id'] ?>">
                        <button type="button" id="showEnquiryFormBtn" style="padding:10px 22px;background:#28a745;color:#fff;border:none;border-radius:6px;font-weight:600;">Add Enquiry</button>
                    </form>
                </div>
                
                <!-- Client Details View (read-only) -->
                <div id="clientDetailsView" style="margin-bottom:18px;">
                    <table style="width:100%;border-collapse:collapse;background:#fff;box-shadow:0 2px 8px #e0bebe22;border-radius:12px;overflow:hidden;">
                        <thead>
                            <tr style="background:#f9eaea;color:#800000;">
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
                            <tr>
                                <td style="padding:10px;"> <?= htmlspecialchars($selected_client['name']) ?> </td>
                                <td style="padding:10px;"> <?= htmlspecialchars($selected_client['mobile']) ?> </td>
                                <td style="padding:10px;"> <?= htmlspecialchars($selected_client['address']) ?> </td>
                                <td style="padding:10px;"> <?= htmlspecialchars($selected_client['dob']) ?> </td>
                                <td style="padding:10px;"> <?= htmlspecialchars($selected_client['birth_time']) ?> </td>
                                <td style="padding:10px;"> <?= htmlspecialchars($selected_client['birth_place']) ?> </td>
                                <td style="padding:10px;">
                                    <button type="button" id="editClientBtn" style="padding:6px 14px;background:#007bff;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Edit</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Client Details Edit Form (hidden by default) -->
                <div id="clientEditForm" style="display:none;margin-bottom:18px;">
                    <form method="post" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;background:#f9eaea;padding:18px;border-radius:10px;">
                        <input type="text" name="name" placeholder="Name" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= htmlspecialchars($selected_client['name']) ?>">
                        <input type="text" name="mobile" placeholder="Mobile No" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= htmlspecialchars($selected_client['mobile']) ?>">
                        <input type="text" name="address" placeholder="Address" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;min-width:180px;" value="<?= htmlspecialchars($selected_client['address']) ?>">
                        <input type="date" name="dob" placeholder="DOB" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= $selected_client['dob'] ? htmlspecialchars($selected_client['dob']) : '' ?>">
                        <input type="time" name="birth_time" placeholder="Birth Time" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= $selected_client['birth_time'] ? htmlspecialchars($selected_client['birth_time']) : '' ?>">
                        <input type="text" name="birth_place" placeholder="Birth Place" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;" value="<?= htmlspecialchars($selected_client['birth_place']) ?>">
                        <input type="hidden" name="edit_id" value="<?= (int)$selected_client['id'] ?>">
                        <button type="submit" style="padding:10px 22px;background:#800000;color:#fff;border:none;border-radius:6px;font-weight:600;">Update Client</button>
                        <button type="button" id="cancelEditClientBtn" style="padding:10px 22px;background:#6c757d;color:#fff;border:none;border-radius:6px;font-weight:600;">Cancel</button>
                    </form>
                </div>
                
                <script>
                document.getElementById('editClientBtn').onclick = function() {
                    document.getElementById('clientDetailsView').style.display = 'none';
                    document.getElementById('clientEditForm').style.display = 'block';
                };
                document.getElementById('cancelEditClientBtn').onclick = function() {
                    document.getElementById('clientDetailsView').style.display = 'block';
                    document.getElementById('clientEditForm').style.display = 'none';
                };
                </script>

                <!-- Enquiry Form (hidden by default) -->
                <div id="enquiryFormContainer" style="display:none;margin-bottom:24px;">
                    <form method="post" action="" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;">
                        <input type="hidden" name="add_enquiry" value="1">
                        <input type="hidden" name="client_id" value="<?= (int)$selected_client['id'] ?>">
                        <select name="category_id" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;">
                            <option value="">-- Select Category --</option>
                            <?php
                            $categories = $pdo->query('SELECT * FROM cif_categories ORDER BY name ASC')->fetchAll();
                            foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="enquiry_date" value="<?= date('Y-m-d') ?>" required style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;">
                        <textarea name="notes" placeholder="Notes" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;min-width:360px;min-height:80px;"></textarea>
                        <button type="submit" style="padding:10px 22px;background:#800000;color:#fff;border:none;border-radius:6px;font-weight:600;">Save Enquiry</button>
                        <button type="button" id="cancelEnquiryBtn" style="padding:10px 22px;background:#6c757d;color:#fff;border:none;border-radius:6px;font-weight:600;">Cancel</button>
                    </form>
                </div>
                <script>
                document.getElementById('showEnquiryFormBtn').onclick = function() {
                    document.getElementById('enquiryFormContainer').style.display = 'block';
                    this.style.display = 'none';
                };
                document.getElementById('cancelEnquiryBtn').onclick = function() {
                    document.getElementById('enquiryFormContainer').style.display = 'none';
                    document.getElementById('showEnquiryFormBtn').style.display = 'inline-block';
                };
                </script>
            <?php endif; ?>

            <!-- Show added enquiries for this client -->
            <?php
            $enquiries = [];
            if ($selected_client) {
                $stmt = $pdo->prepare('SELECT e.*, c.name as category_name, c.color as category_color FROM cif_enquiries e JOIN cif_categories c ON e.category_id = c.id WHERE e.client_id = ? ORDER BY e.enquiry_date DESC, e.id DESC');
                $stmt->execute([$selected_client['id']]);
                $enquiries = $stmt->fetchAll();
            }
            ?>
            <?php if (!empty($enquiries)): ?>
                <h2 style="color:#800000;">Enquiries</h2>
                <table style="width:100%;border-collapse:collapse;background:#fff;box-shadow:0 2px 8px #e0bebe22;border-radius:12px;overflow:hidden;margin-bottom:18px;">
                    <thead>
                        <tr style="background:#f9eaea;color:#800000;">
                            <th style="padding:12px 10px;">Category</th>
                            <th style="padding:12px 10px;">Date</th>
                            <th style="padding:12px 10px;">Notes</th>
                            <th style="padding:12px 10px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enquiries as $enq): ?>
                        <tr>
                            <td style="padding:10px;">
                                <span style="display:inline-block;width:32px;height:32px;border-radius:8px;background:<?= htmlspecialchars($enq['category_color']) ?>;vertical-align:middle;margin-right:8px;border:1px solid #ccc;"></span>
                                <span style="vertical-align:middle;font-weight:600;"> <?= htmlspecialchars($enq['category_name']) ?> </span>
                            </td>
                            <td style="padding:10px;"> <?= htmlspecialchars($enq['enquiry_date']) ?> </td>
                            <td style="padding:10px;white-space:pre-line;">
                                <?php if ($edit_enquiry_id === (int)$enq['id']): ?>
                                    <form method="post" style="display:flex;flex-direction:column;gap:8px;">
                                        <textarea name="notes" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;min-width:360px;min-height:80px;"><?= htmlspecialchars($enq['notes']) ?></textarea>
                                        <input type="hidden" name="update_enquiry_notes" value="1">
                                        <input type="hidden" name="enquiry_id" value="<?= (int)$enq['id'] ?>">
                                        <input type="hidden" name="client_id" value="<?= (int)$selected_client['id'] ?>">
                                        <div>
                                            <button type="submit" style="padding:6px 18px;background:#28a745;color:#fff;border:none;border-radius:6px;font-weight:600;">Save</button>
                                            <a href="index.php?client_id=<?= (int)$selected_client['id'] ?>" style="padding:6px 18px;background:#6c757d;color:#fff;border:none;border-radius:6px;font-weight:600;text-decoration:none;">Cancel</a>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <?= nl2br(htmlspecialchars($enq['notes'])) ?>
                                <?php endif; ?>
                            </td>
                            <td style="padding:10px;">
                                <?php if ($edit_enquiry_id === (int)$enq['id']): ?>
                                    <!-- No edit/delete while editing -->
                                <?php else: ?>
                                    <a href="index.php?client_id=<?= (int)$selected_client['id'] ?>&edit_enquiry=<?= (int)$enq['id'] ?>" style="padding:6px 14px;background:#007bff;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;margin-right:8px;">Edit</a>
                                    <a href="index.php?client_id=<?= (int)$selected_client['id'] ?>&delete_enquiry=<?= (int)$enq['id'] ?>" onclick="return confirm('Delete this enquiry?');" style="padding:6px 14px;background:#dc3545;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Example: include language.js if needed -->
<script src="/assets/js/language.js"></script>

</body>
</html>
