<?php
include '../includes/top-menu.php';
require_once __DIR__ . '/../../config/db.php';
// Fetch and group bookings by date
// Fetch all bookings, group by date, and sort each group by token_no ascending
$bookings = $pdo->query("SELECT * FROM token_bookings ORDER BY token_date DESC, token_no ASC")->fetchAll(PDO::FETCH_ASSOC);
$grouped = [];
foreach ($bookings as $b) {
    $grouped[$b['token_date']][] = $b;
}
// Sort dates ascending (oldest first)
$dates = array_keys($grouped);
usort($dates, function($a, $b) { return strcmp($a, $b); });
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booked Tokens</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
        .admin-container { max-width: 1400px; margin: 0 auto; padding: 24px 12px; }
        h1 { color: #800000; margin-bottom: 18px; }
        .filter-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 18px; flex-wrap: wrap; }
        .filter-bar label { font-weight: 600; }
        .filter-bar select { min-width: 180px; padding: 7px 12px; border-radius: 6px; font-size: 1em; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 12px #e0bebe22; border-radius: 12px; table-layout: auto; font-size: 0.85em; }
        table th, table td { padding: 8px 6px; text-align: left; border-bottom: 1px solid #f3caca; white-space: nowrap; }
        table thead { background: #f9eaea; color: #800000; font-size: 0.9em; font-weight: 600; }
        table tbody tr:hover { background: #f3f7fa; }
        .booking-table-group h2 { margin-top: 28px; }
        .complete-btn, .delete-btn { font-size: 0.95em; }
        .complete-btn { background: #1a8917; color: #fff; padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; margin-right: 6px; }
        .delete-btn { background: #c00; color: #fff; padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; }
        .complete-btn:hover { background: #157113; }
        .delete-btn:hover { background: #a80000; }
        @media (max-width: 900px) {
            .admin-container { max-width: 100%; padding: 16px 10px; }
            table { font-size: 0.83em; }
        }
        @media (max-width: 700px) {
            .admin-container { padding: 12px 8px; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-bar label { margin-bottom: 6px; }
            .filter-bar select { width: 100%; }
            table th, table td { padding: 6px 4px; }
            .complete-btn, .delete-btn { width: 100%; margin: 4px 0 0 0; }
        }
        @media (max-width: 480px) {
            h1 { font-size: 1.2em; }
            h2 { font-size: 1.05em; }
            table { font-size: 0.8em; }
            table th, table td { padding: 5px 4px; }
        }
    </style>
</head>
<body>
<div class="admin-container">
    <h1>Booked Tokens</h1>
    <?php
    // Get unique cities (locations) from bookings
    $cities = [];
    foreach ($bookings as $b) {
        $loc = trim($b['location']);
        if ($loc && !in_array($loc, $cities)) {
            $cities[] = $loc;
        }
    }
    sort($cities);
    ?>
    <div class="filter-bar">
        <label for="cityFilter">Filter by City:</label>
        <select id="cityFilter">
            <option value="solapur" selected>Solapur</option>
            <option value="hyderabad">Hyderabad</option>
            <option value="">All Cities</option>
        </select>
    </div>
    <div id="bookingsTables">
    <?php foreach ($dates as $date): ?>
        <div class="booking-table-group" data-date="<?= htmlspecialchars($date) ?>">
        <?php
        // Calculate totals for this date
        $bookedCount = count($grouped[$date]);
        $totalTokens = 0;
        // Find total tokens from token_management
        $stmt = $pdo->prepare("SELECT total_tokens FROM token_management WHERE token_date = ? LIMIT 1");
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $totalTokens = (int)$row['total_tokens'];
        ?>
        <h2 style="margin-top:32px;color:#800000;">Date: <?= htmlspecialchars($date) ?>
            <span style="font-size:0.98em;color:#333;margin-left:18px;">Booked: <?= $bookedCount ?> / Total: <?= $totalTokens ?></span>
        </h2>
        <div class="table-responsive-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Token No</th>
                    <th>Location</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Service Time</th>
                    <th>Booked At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($grouped[$date] as $b): ?>
                <tr data-city="<?= htmlspecialchars($b['location']) ?>" data-id="<?= (int)$b['id'] ?>">
                    <td><?= htmlspecialchars($b['token_no']) ?></td>
                    <td><?= htmlspecialchars($b['location']) ?></td>
                    <td><?= htmlspecialchars($b['name']) ?></td>
                    <td><?= htmlspecialchars($b['mobile']) ?></td>
                    <td><?= htmlspecialchars($b['service_time']) ?></td>
                    <td>
                        <?php
                        $dt = strtotime($b['created_at']);
                        echo $dt ? date('d-m-Y h:i A', $dt) : htmlspecialchars($b['created_at']);
                        ?>
                    </td>
                    <td>
                        <button class="complete-btn" style="background:#1a8917;color:#fff;padding:4px 10px;border:none;border-radius:4px;cursor:pointer;margin-right:6px;">Completed</button>
                        <button class="delete-btn" style="background:#c00;color:#fff;padding:4px 10px;border:none;border-radius:4px;cursor:pointer;">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<script>
// City filter logic with Solapur as default
function filterByCity(city) {
    document.querySelectorAll('#bookingsTables tr[data-city]').forEach(function(row) {
        if (!city || row.getAttribute('data-city') === city) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    // Hide date groups if all rows inside are hidden
    document.querySelectorAll('.booking-table-group').forEach(function(group) {
        var visibleRows = group.querySelectorAll('tr[data-city]:not([style*="display: none"])');
        group.style.display = visibleRows.length ? '' : 'none';
    });
}
var cityFilter = document.getElementById('cityFilter');
cityFilter.addEventListener('change', function() {
    filterByCity(this.value);
});
// Initial filter to Solapur
filterByCity('solapur');
// Handle Completed and Delete actions
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('complete-btn') || e.target.classList.contains('delete-btn')) {
        var row = e.target.closest('tr[data-id]');
        var id = row ? row.getAttribute('data-id') : null;
        if (!id) return;
        var action = e.target.classList.contains('complete-btn') ? 'complete' : 'delete';
        if (action === 'delete' && !confirm('Are you sure you want to delete this booking?')) return;
        if (action === 'complete' && !confirm('Mark this booking as completed? This will remove it from the list.')) return;
        fetch('manage-booking.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id) + '&action=' + encodeURIComponent(action)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                row.parentNode.removeChild(row);
                // Optionally, re-filter to hide empty date groups
                filterByCity(cityFilter.value);
            } else {
                alert('Operation failed.');
            }
        })
        .catch(() => alert('Server error.'));
    }
});
</script>
</body>
</html>
