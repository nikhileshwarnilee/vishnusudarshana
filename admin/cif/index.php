$msg = '';

// Handle delete enquiry
if (isset($_GET['delete_enquiry']) && $selected_client_id > 0) {
    $del_id = (int)$_GET['delete_enquiry'];
    $stmt = $pdo->prepare('DELETE FROM cif_enquiries WHERE id = ? AND client_id = ?');
    $stmt->execute([$del_id, $selected_client_id]);
    $msg = 'Enquiry deleted!';
    header('Location: index.php?client_id=' . $selected_client_id);
    exit;
}

<?php
// admin/cif/index.php
// CIF main page styled like Appointments

require_once __DIR__ . '/../../config/db.php';


$msg = '';
$edit_enquiry_id = isset($_GET['edit_enquiry']) ? (int)$_GET['edit_enquiry'] : 0;

// Handle update enquiry notes
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
$selected_client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
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
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <h1>CIF Panel</h1>
    <div class="summary-cards">
        <div class="summary-card">
            <div class="summary-count">0</div>
            <div class="summary-label">CIF Example</div>
        </div>
        <div class="summary-card">
            <div class="summary-count">0</div>
            <div class="summary-label">Another Stat</div>
        </div>
    </div>
    <div style="background:#fff;padding:24px;border-radius:12px;box-shadow:0 2px 8px #e0bebe22;">
        <h2 style="color:#800000;">Select Client</h2>
        <form method="get" style="margin-bottom:24px;display:flex;gap:18px;align-items:center;">
            <select name="client_id" onchange="this.form.submit()" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:1em;min-width:220px;">
                <option value="">-- Select Client --</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $selected_client_id == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['mobile']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </form>

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
                <table style="width:100%;border-collapse:collapse;background:#fff;box-shadow:0 2px 8px #e0bebe22;border-radius:12px;overflow:hidden;margin-bottom:18px;">
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
                                <a href="index.php?client_id=<?= (int)$selected_client['id'] ?>&edit=<?= (int)$selected_client['id'] ?>" style="padding:6px 14px;background:#007bff;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">Edit</a>
                            </td>
                        </tr>
                    </tbody>
                </table>

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
