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

// Fetch all booked tokens
$bookings = $pdo->query("SELECT * FROM token_bookings ORDER BY token_date DESC, token_no ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booked Tokens</title>
    <style>
        body {
            padding-top: 40px;
            padding-bottom: 40px;
        }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 24px;
            margin-bottom: 24px;
        }
        .page-header h2 {
            margin: 0;
            font-size: 1.6em;
        }
        .page-header label, .page-header select {
            font-size: 1em;
        }
        .bookedTokensTable {
            border-collapse: collapse;
            width: auto;
            table-layout: auto;
            margin: 0 auto 32px auto;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #f5f5f5;
        }
        .booking-table-group {
            margin-bottom: 40px;
        }
        .booking-table-group h3 {
            text-align: center;
            margin-bottom: 8px;
        }
        .booking-table-group p {
            text-align: center;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/top-menu.php'; ?>
    
    <div class="page-header">
        <h2>All Booked Token Details</h2>
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

    <?php
    // Group bookings by date
    $grouped = [];
    foreach ($bookings as $b) {
        $grouped[$b['token_date']][] = $b;
    }

    // Sort dates: current date first, then tomorrow, then future, then past
    $dates = array_keys($grouped);
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $future = [];
    $past = [];
    foreach ($dates as $date) {
        if ($date == $today) {
            $sortedDates['today'] = $date;
        } elseif ($date == $tomorrow) {
            $sortedDates['tomorrow'] = $date;
        } elseif ($date > $tomorrow) {
            $future[$date] = $date;
        } else {
            $past[$date] = $date;
        }
    }
    // Merge sorted dates
    $finalDates = [];
    if (isset($sortedDates['today'])) $finalDates[] = $sortedDates['today'];
    if (isset($sortedDates['tomorrow'])) $finalDates[] = $sortedDates['tomorrow'];
    if (!empty($future)) {
        ksort($future);
        foreach ($future as $d) $finalDates[] = $d;
    }
    if (!empty($past)) {
        krsort($past);
        foreach ($past as $d) $finalDates[] = $d;
    }

    // Fetch total tokens for each date/location
    $tokenTotals = [];
    $stmt = $pdo->prepare("SELECT token_date, location, total_tokens FROM token_management");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $tokenTotals[$row['token_date']][strtolower(trim($row['location']))] = $row['total_tokens'];
    }

    foreach ($finalDates as $date):
        $bookingsForDate = $grouped[$date];
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
        <h3><?= htmlspecialchars($date) ?> - <?= htmlspecialchars(ucfirst($city)) ?></h3>
        <p>Total Tokens: <?= isset($tokenTotals[$date][$city]) ? $tokenTotals[$date][$city] : '-' ?> | Booked Tokens: <?= count($cityBookings) ?></p>
        <table class="bookedTokensTable">
            <thead>
                <tr>
                    <th>Token No</th>
                    <th>Location</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Service Time</th>
                    <th>Time Slot</th>
                    <th>Late By</th>
                    <th>Action</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php $rowIndex = 0; foreach ($cityBookings as $b): ?>
                <tr>
                    <td><?= htmlspecialchars($b['token_no']) ?></td>
                    <td><?= htmlspecialchars($b['location']) ?></td>
                    <td><?= htmlspecialchars($b['name']) ?></td>
                    <td><?= htmlspecialchars($b['mobile']) ?></td>
                    <td><?= htmlspecialchars($b['service_time']) ?></td>
                    <td>
                        <?php
                        // Calculate time slot window based on row index
                        $slot = null;
                        $row = $pdo->prepare("SELECT from_time, to_time, total_tokens FROM token_management WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) LIMIT 1");
                        $row->execute([$b['token_date'], $b['location']]);
                        $slot = $row->fetch(PDO::FETCH_ASSOC);
                        $slotText = '-';
                        $highlight = false;
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
                                    $endMins = $fromMins + ($rowIndex + 1) * $perMins;
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
    <?php endforeach; endforeach; ?>
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
