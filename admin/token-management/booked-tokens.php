<?php
require_once __DIR__ . '/../../config/db.php';
include __DIR__ . '/../includes/top-menu.php';

// Utility function for time formatting
if (!function_exists('minsToTime')) {
    function minsToTime($mins) {
        $h = floor($mins / 60);
        $m = $mins % 60;
        $ampm = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h % 12;
        if ($h12 == 0) $h12 = 12;
        return sprintf('%02d:%02d %s', $h12, $m, $ampm);
    }
}

if (!function_exists('formatTime12')) {
    function formatTime12($time) {
        if ($time === null || $time === '') {
            return '';
        }
        $dt = DateTime::createFromFormat('H:i:s', $time);
        if (!$dt) {
            $dt = DateTime::createFromFormat('H:i', $time);
        }
        return $dt ? $dt->format('g:i A') : $time;
    }
}

// Fetch all booked tokens
$bookings = $pdo->query("SELECT * FROM token_bookings ORDER BY token_date DESC, token_no ASC")->fetchAll(PDO::FETCH_ASSOC);

// Show only today/future dates; past dates only if not completed
$today = date('Y-m-d');
$bookings = array_values(array_filter($bookings, function($b) use ($today) {
    $date = $b['token_date'] ?? '';
    if ($date >= $today) {
        return true;
    }
    $status = strtolower(trim((string)($b['status'] ?? '')));
    return $status !== 'completed';
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booked Tokens</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7f7fa;
            margin: 0;
            padding: 24px 0 32px 0;
            color: #2b2b2b;
        }
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 12px 24px 12px;
        }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px #e0bebe22;
            padding: 16px 18px;
            margin-bottom: 18px;
        }
        .page-header h2 {
            margin: 0;
            font-size: 1.4em;
            color: #800000;
            letter-spacing: 0.3px;
        }
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .action-link {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 8px;
            background: #800000;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95em;
        }
        .action-link:hover {
            background: #600000;
        }
        .filter-bar label {
            font-size: 1em;
            color: #444;
        }
        #cityFilter {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
            min-width: 180px;
            background: #fff;
            font-size: 1em;
        }
        .booking-table-group {
            margin-bottom: 18px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px #e0bebe22;
            padding: 14px;
        }
        .group-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        .group-title h3 {
            margin: 0;
            color: #800000;
            font-size: 1.1em;
        }
        .group-meta {
            color: #6b5b00;
            font-weight: 600;
            font-size: 0.95em;
        }
        .table-wrap {
            overflow-x: auto;
        }
        .bookedTokensTable {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            table-layout: auto;
            font-size: 0.9em;
        }
        .bookedTokensTable th, .bookedTokensTable td {
            padding: 8px 6px;
            border-bottom: 1px solid #f3caca;
            text-align: left;
            white-space: nowrap;
        }
        .bookedTokensTable th {
            background: #f9eaea;
            color: #800000;
            font-size: 0.92em;
            font-weight: 600;
        }
        .bookedTokensTable tbody tr:nth-child(even) td {
            background: #fcf7f7;
        }
        .bookedTokensTable tbody tr:hover td {
            background: #fff0d6;
        }
        .bookedTokensTable td.token-no {
            font-weight: 700;
            color: #800000;
        }
        .complete-btn, .revert-btn, .delete-btn {
            font-size: 0.95em;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-right: 6px;
        }
        .complete-btn { background: #1a8917; color: #fff; }
        .revert-btn { background: #888; color: #fff; }
        .delete-btn { background: #c00; color: #fff; }
        @media (max-width: 800px) {
            .bookedTokensTable { font-size: 0.88em; }
            .page-header { padding: 14px; }
        }
        @media (max-width: 600px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            #cityFilter { width: 100%; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/top-menu.php'; ?>
    
    <div class="admin-container">
        <div class="page-header">
            <h2>All Booked Token Details</h2>
            <div class="header-actions">
                <a class="action-link" href="completed-tokens.php">Completed Tokens</a>
                <div class="filter-bar">
                    <label for="cityFilter">Filter by City:</label>
                    <select id="cityFilter">
                        <option value="solapur" selected>Solapur</option>
                        <option value="">All</option>
                        <?php
                        // Collect unique cities from bookings
                        $cities = array_unique(array_map(function($b) { return strtolower(trim($b['location'])); }, $bookings));
                        foreach ($cities as $city) {
                            if ($city !== 'solapur') {
                                echo '<option value="' . htmlspecialchars($city) . '">' . htmlspecialchars(ucfirst($city)) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Group bookings by date
    $grouped = [];
    foreach ($bookings as $b) {
        $grouped[$b['token_date']][] = $b;
    }

    // Sort all dates ascending (oldest to newest)
    $finalDates = array_keys($grouped);
    sort($finalDates);

    // Fetch total tokens for each date/location
    $tokenTotals = [];
    $stmt = $pdo->prepare("SELECT token_date, location, total_tokens FROM token_management");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $tokenTotals[$row['token_date']][strtolower(trim($row['location']))] = $row['total_tokens'];
    }

    foreach ($finalDates as $date):
        $bookingsForDate = $grouped[$date];
        $dayName = date('l', strtotime($date));
        $marathiDays = [
            'Sunday' => 'रविवार',
            'Monday' => 'सोमवार',
            'Tuesday' => 'मंगळवार',
            'Wednesday' => 'बुधवार',
            'Thursday' => 'गुरुवार',
            'Friday' => 'शुक्रवार',
            'Saturday' => 'शनिवार'
        ];
        $marathiDay = $marathiDays[$dayName] ?? '';
        // Group by city for filter
        $cityGroups = [];
        foreach ($bookingsForDate as $b) {
            $cityGroups[strtolower(trim($b['location']))][] = $b;
        }
        foreach ($cityGroups as $city => $cityBookings):
            // Sort bookings by token_no ascending
            usort($cityBookings, function($a, $b) {
                return $a['token_no'] - $b['token_no'];
            });
    ?>
    <div class="booking-table-group" data-city="<?= htmlspecialchars($city) ?>">
        <div class="group-title">
            <h3>
                <?= htmlspecialchars($date) ?>
                <?php if ($marathiDay !== ''): ?>
                    (<?= htmlspecialchars($marathiDay) ?>)
                <?php endif; ?>
                - <?= htmlspecialchars(ucfirst($city)) ?>
            </h3>
            <div class="group-meta">Total Tokens: <?= isset($tokenTotals[$date][$city]) ? $tokenTotals[$date][$city] : '-' ?> | Booked Tokens: <?= count($cityBookings) ?></div>
        </div>
        <div class="table-wrap">
        <table class="bookedTokensTable">
            <thead>
                <tr>
                    <th>Token No</th>
                    <th>Location</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Service Time</th>
                    <th>Time Slot</th>
                    <th>Revised Time Slot</th>
                    <th>Late By</th>
                    <th>Action</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php $rowIndex = 0; foreach ($cityBookings as $b): ?>
                <tr>
                    <td class="token-no"><?= htmlspecialchars($b['token_no']) ?></td>
                    <td><?= htmlspecialchars($b['location']) ?></td>
                    <td><?= htmlspecialchars($b['name']) ?></td>
                    <td><?= htmlspecialchars($b['mobile']) ?></td>
                    <td><?= htmlspecialchars(formatTime12($b['service_time'])) ?></td>
                    <td>
                        <?php
                        // Calculate time slot window based on row index
                        $slot = null;
                        $row = $pdo->prepare("SELECT from_time, to_time, total_tokens FROM token_management WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) LIMIT 1");
                        $row->execute([$b['token_date'], $b['location']]);
                        $slot = $row->fetch(PDO::FETCH_ASSOC);
                        $slotText = '-';
                        $highlight = false;
                        $perMinsCalc = 0;
                        if ($slot && $slot['from_time'] && $slot['to_time'] && $slot['total_tokens'] > 0) {
                            $fromParts = explode(':', $slot['from_time']);
                            $toParts = explode(':', $slot['to_time']);
                            if (count($fromParts) >= 2 && count($toParts) >= 2) {
                                $fromMins = intval($fromParts[0]) * 60 + intval($fromParts[1]);
                                $toMins = intval($toParts[0]) * 60 + intval($toParts[1]);
                                $diffMins = $toMins - $fromMins;
                                if ($diffMins > 0) {
                                    $perMinsCalc = floor($diffMins / $slot['total_tokens']);
                                    $startMins = $fromMins + ($rowIndex) * $perMinsCalc;
                                    $endMins = $fromMins + ($rowIndex + 1) * $perMinsCalc;
                                    $slotText = minsToTime($startMins) . ' - ' . minsToTime($endMins);
                                    // Highlight if current time is within slot and date is today
                                    $today = date('Y-m-d');
                                    if ($b['token_date'] === $today) {
                                        $now = time();
                                        $slotStartTimestamp = strtotime($b['token_date'] . sprintf(' %02d:%02d:00', floor($startMins/60), $startMins%60));
                                        $slotEndTimestamp = strtotime($b['token_date'] . sprintf(' %02d:%02d:00', floor($endMins/60), $endMins%60));
                                        if ($now >= $slotStartTimestamp && $now < $slotEndTimestamp) {
                                            $highlight = true;
                                        }
                                    }
                                }
                            }
                        }
                        if ($highlight) {
                            echo '<span style="color:#fff;background:#1a8917;padding:2px 8px;border-radius:4px;">' . htmlspecialchars($slotText) . '</span>';
                        } else {
                            echo htmlspecialchars($slotText);
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        $revisedText = '-';
                        if ($perMinsCalc > 0) {
                            $nowMins = ((int)date('G')) * 60 + (int)date('i');
                            $revStart = $nowMins + ($rowIndex * $perMinsCalc);
                            $revEnd = $nowMins + (($rowIndex + 1) * $perMinsCalc);
                            $revisedText = minsToTime($revStart) . ' - ' . minsToTime($revEnd);
                        }
                        echo htmlspecialchars($revisedText);
                        ?>
                    </td>
                    <td>
                        <?php
                        // Calculate late by comparing current time to calculated time slot start time
                        $lateBy = '-';
                        if ($slot && $slot['from_time'] && $slot['to_time'] && $slot['total_tokens'] > 0) {
                            $fromParts = explode(':', $slot['from_time']);
                            $toParts = explode(':', $slot['to_time']);
                            if (count($fromParts) >= 2 && count($toParts) >= 2) {
                                $fromMins = intval($fromParts[0]) * 60 + intval($fromParts[1]);
                                $toMins = intval($toParts[0]) * 60 + intval($toParts[1]);
                                $diffMins = $toMins - $fromMins;
                                if ($diffMins > 0) {
                                    $perMins = floor($diffMins / $slot['total_tokens']);
                                    $startMins = $fromMins + ($rowIndex) * $perMins;
                                    $slotStartHour = floor($startMins / 60);
                                    $slotStartMin = $startMins % 60;
                                    $slotStartTimestamp = strtotime($b['token_date'] . sprintf(' %02d:%02d:00', $slotStartHour, $slotStartMin));
                                    $now = time();
                                    $diff = $now - $slotStartTimestamp;
                                    if ($diff > 60) {
                                        $hours = floor($diff / 3600);
                                        $mins = floor(($diff % 3600) / 60);
                                        $lateBy = ($hours ? $hours . 'h ' : '') . $mins . 'm late';
                                    } else if ($diff > 0) {
                                        $lateBy = $diff . 's late';
                                    } else if ($diff < -60) {
                                        $hours = floor(abs($diff) / 3600);
                                        $mins = floor((abs($diff) % 3600) / 60);
                                        $lateBy = 'Early by ' . ($hours ? $hours . 'h ' : '') . $mins . 'm';
                                    } else if ($diff < 0) {
                                        $lateBy = 'Early by ' . abs($diff) . 's';
                                    } else {
                                        $lateBy = 'On time';
                                    }
                                }
                            }
                        }
                        if (strpos($lateBy, 'late') !== false) {
                            echo '<span style="color:#c00;font-weight:bold;">' . htmlspecialchars($lateBy) . '</span>';
                        } else {
                            echo htmlspecialchars($lateBy);
                        }
                        ?>
                    </td>
                    <td>
                        <?php if (isset($b['status']) && $b['status'] === 'completed'): ?>
                            <button class="revert-btn" data-booking-id="<?= htmlspecialchars($b['id']) ?>" style="background:#888;color:#fff;padding:4px 10px;border:none;border-radius:4px;cursor:pointer;margin-right:6px;">Revert</button>
                        <?php else: ?>
                            <button class="complete-btn" style="background:#1a8917;color:#fff;padding:4px 10px;border:none;border-radius:4px;cursor:pointer;margin-right:6px;">Completed</button>
                        <?php endif; ?>
                        <button class="delete-btn" data-booking-id="<?= htmlspecialchars($b['id']) ?>" style="background:#c00;color:#fff;padding:4px 10px;border:none;border-radius:4px;cursor:pointer;">Delete</button>
                    </td>
                    <td><?= htmlspecialchars($b['created_at']) ?></td>
                </tr>
                <?php $rowIndex++; endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endforeach; endforeach; ?>
    </div>
    <script>
    // City filter logic with Solapur as default
    function filterByCity(city) {
        document.querySelectorAll('.booking-table-group').forEach(function(group) {
            if (!city || group.getAttribute('data-city') === city) {
                group.style.display = '';
            } else {
                group.style.display = 'none';
            }
        });
    }
    document.getElementById('cityFilter').addEventListener('change', function() {
        filterByCity(this.value);
    });
    // Initial filter
    filterByCity('solapur');
    </script>
    <script>
    // Delete booking functionality
    document.querySelectorAll('.delete-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var bookingId = this.getAttribute('data-booking-id');
            if (!bookingId) return;
            if (!confirm('Are you sure you want to delete this booking?')) return;
            fetch('delete-booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(bookingId)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to delete booking.');
                }
            })
            .catch(() => alert('Failed to delete booking.'));
        });
    });

    // Completed booking functionality
    document.querySelectorAll('.complete-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var bookingId = this.parentNode.querySelector('.delete-btn').getAttribute('data-booking-id');
            if (!bookingId) return;
            fetch('complete-booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(bookingId)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to mark booking as completed.');
                }
            })
            .catch(() => alert('Failed to mark booking as completed.'));
        });
    });

    // Revert booking functionality
    document.querySelectorAll('.revert-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var bookingId = this.getAttribute('data-booking-id');
            if (!bookingId) return;
            fetch('revert-booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(bookingId)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to revert booking status.');
                }
            })
            .catch(() => alert('Failed to revert booking status.'));
        });
    });
    </script>
</body>
</html>
