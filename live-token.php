<?php
require_once __DIR__ . '/config/db.php';

// Get all bookings for today and future dates
$stmt = $pdo->prepare("SELECT * FROM token_bookings WHERE token_date >= ? ORDER BY token_date ASC, CAST(token_no AS UNSIGNED) ASC");
$stmt->execute([date('Y-m-d')]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get city filter from query
$filterCity = isset($_GET['city']) ? strtolower(trim($_GET['city'])) : 'solapur';

// Utility function for time formatting
function minsToTime($mins) {
    $h = floor($mins / 60);
    $m = $mins % 60;
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h % 12;
    if ($h12 == 0) $h12 = 12;
    return sprintf('%02d:%02d %s', $h12, $m, $ampm);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Token Tracking - Vishnusudarshana</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .header h1 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
            font-size: 2.5em;
        }
        .header-content {
            display: flex;
            gap: 20px;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }
        .current-time {
            font-size: 1.2em;
            color: #667eea;
            font-weight: bold;
        }
        .location-filter {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .location-filter label {
            font-weight: 600;
            color: #333;
        }
        .location-filter select {
            padding: 10px 15px;
            border: 2px solid #667eea;
            border-radius: 6px;
            font-size: 1em;
            cursor: pointer;
            background: white;
            color: #333;
        }
        .location-filter select:focus {
            outline: none;
            border-color: #764ba2;
        }

        .tabs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: all 0.3s;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }
        .tab-btn.active {
            background: #667eea;
            color: white;
        }
        .tab-btn:hover {
            background: #667eea;
            color: white;
        }

        .booking-group {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            margin-bottom: 25px;
            display: none;
        }
        .booking-group.active {
            display: block;
        }

        .group-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-bottom: 4px solid #764ba2;
        }
        .group-header h2 {
            font-size: 1.8em;
            margin-bottom: 10px;
        }
        .group-meta {
            font-size: 0.95em;
            opacity: 0.9;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .meta-item strong {
            opacity: 1;
        }

        .current-token-section {
            background: #fff3cd;
            padding: 20px;
            border-left: 5px solid #ffc107;
            margin: 20px;
            border-radius: 6px;
        }
        .current-token-section h3 {
            color: #856404;
            margin-bottom: 10px;
            font-size: 1.2em;
        }
        .current-token-info {
            color: #856404;
            font-size: 1.1em;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        thead {
            background: #f8f9fa;
            border-bottom: 3px solid #667eea;
        }
        th {
            padding: 15px;
            text-align: left;
            font-weight: 700;
            color: #333;
            text-transform: uppercase;
            font-size: 0.9em;
            letter-spacing: 0.5px;
        }
        td {
            padding: 14px 15px;
            border-bottom: 1px solid #eee;
            color: #555;
        }
        tbody tr:hover {
            background: #f5f5f5;
            transition: background 0.2s;
        }
        tbody tr.completed {
            background: #e8f5e9;
            opacity: 0.7;
        }
        tbody tr.completed td {
            text-decoration: line-through;
            color: #999;
        }

        .token-no {
            font-weight: bold;
            color: #667eea;
            font-size: 1.1em;
            min-width: 50px;
        }
        .token-name {
            font-weight: 600;
            color: #333;
        }
        .token-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending {
            background: #e3f2fd;
            color: #1976d2;
        }
        .status-completed {
            background: #c8e6c9;
            color: #388e3c;
        }

        .empty-message {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 1.1em;
        }

        .time-slot-display {
            background: #f0f4ff;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: 600;
            color: #667eea;
        }

        @media (max-width: 768px) {
            .header {
                padding: 20px;
            }
            .header h1 {
                font-size: 1.8em;
            }
            .header-content {
                flex-direction: column;
            }
            table {
                font-size: 0.9em;
            }
            th, td {
                padding: 10px 8px;
            }
            .group-header h2 {
                font-size: 1.3em;
            }
            .group-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <div class="container">
        <div class="header">
            <h1>🎫 Live Token Tracking</h1>
            <div class="header-content">
                <div class="current-time">
                    Current Time: <span id="liveTime">--:--</span>
                </div>
                <div class="location-filter">
                    <label for="locationSelect">Select Location:</label>
                    <select id="locationSelect" onchange="switchLocation(this.value)">
                        <option value="solapur">Solapur</option>
                        <option value="hyderabad">Hyderabad</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('today')">Today</button>
            <button class="tab-btn" onclick="switchTab('upcoming')">Upcoming</button>
        </div>

        <!-- Today's Tokens -->
        <div id="today" class="booking-group active">
            <?php
            $todayBookings = array_filter($bookings, function($b) {
                return $b['token_date'] === date('Y-m-d');
            });

            if (!empty($todayBookings)) {
                // Group by city
                $byCity = [];
                foreach ($todayBookings as $b) {
                    $city = strtolower(trim($b['location']));
                    if (!isset($byCity[$city])) $byCity[$city] = [];
                    $byCity[$city][] = $b;
                }

                foreach ($byCity as $city => $cityBookings) {
                    if ($city !== $filterCity) continue;

                    // Sort by token number
                    usort($cityBookings, function($a, $b) {
                        return (int)$a['token_no'] - (int)$b['token_no'];
                    });

                    // Get current serving token
                    $currentStmt = $pdo->prepare("SELECT MAX(CAST(token_no AS UNSIGNED)) as current FROM token_bookings WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) AND status = 'completed'");
                    $currentStmt->execute([date('Y-m-d'), $city]);
                    $currentResult = $currentStmt->fetch(PDO::FETCH_ASSOC);
                    $currentToken = $currentResult['current'] ?? 0;

                    // Get slot info
                    $slotStmt = $pdo->prepare("SELECT from_time, to_time, total_tokens FROM token_management WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?))");
                    $slotStmt->execute([date('Y-m-d'), $city]);
                    $slot = $slotStmt->fetch(PDO::FETCH_ASSOC);

                    $perTokenMins = 0;
                    if ($slot && $slot['from_time'] && $slot['to_time'] && (int)$slot['total_tokens'] > 0) {
                        $fromParts = explode(':', $slot['from_time']);
                        $toParts = explode(':', $slot['to_time']);
                        if (count($fromParts) >= 2 && count($toParts) >= 2) {
                            $fromMins = intval($fromParts[0]) * 60 + intval($fromParts[1]);
                            $toMins = intval($toParts[0]) * 60 + intval($toParts[1]);
                            $diffMins = $toMins - $fromMins;
                            if ($diffMins > 0) {
                                $perTokenMins = floor($diffMins / (int)$slot['total_tokens']);
                            }
                        }
                    }

                    ?>
                    <div class="group-header">
                        <h2><?= htmlspecialchars(ucfirst($city)) ?> - Today</h2>
                        <div class="group-meta">
                            <div class="meta-item">
                                <strong>Total Appointments:</strong> <?= count($cityBookings) ?>
                            </div>
                            <div class="meta-item">
                                <strong>Per Token:</strong> <?= $perTokenMins > 0 ? ($perTokenMins . ' mins') : '-' ?>
                            </div>
                            <div class="meta-item">
                                <strong>Current Serving:</strong> Token #<?= $currentToken ?: 'None yet' ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($currentToken > 0): ?>
                    <div class="current-token-section">
                        <h3>🎯 Now Serving</h3>
                        <div class="current-token-info">
                            Token #<?= $currentToken ?> has been completed. Next: Token #<?= ($currentToken + 1) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <table>
                        <thead>
                            <tr>
                                <th>Token #</th>
                                <th>Name</th>
                                <th>Mobile</th>
                                <th>Time Slot</th>
                                <th>Revised Time Slot</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $nowMins = ((int)date('G')) * 60 + (int)date('i');
                            foreach ($cityBookings as $booking):
                                $tokenNo = (int)$booking['token_no'];
                                $status = isset($booking['status']) ? $booking['status'] : 'pending';
                                
                                // Calculate revised time
                                $rowIndex = $tokenNo - 1;
                                $revStart = $nowMins + ($rowIndex * $perTokenMins);
                                $revEnd = $nowMins + (($rowIndex + 1) * $perTokenMins);
                                $revisedSlot = ($perTokenMins > 0) ? (minsToTime($revStart) . ' - ' . minsToTime($revEnd)) : '-';
                                
                                // Get scheduled slot
                                $scheduledSlot = '-';
                                if ($slot && $slot['from_time'] && $slot['to_time'] && $perTokenMins > 0) {
                                    $schedStart = (intval(explode(':', $slot['from_time'])[0]) * 60 + intval(explode(':', $slot['from_time'])[1])) + ($rowIndex * $perTokenMins);
                                    $schedEnd = $schedStart + $perTokenMins;
                                    $scheduledSlot = minsToTime($schedStart) . ' - ' . minsToTime($schedEnd);
                                }
                            ?>
                            <tr <?= $status === 'completed' ? 'class="completed"' : '' ?>>
                                <td class="token-no"><?= sprintf('%02d', $tokenNo) ?></td>
                                <td class="token-name"><?= htmlspecialchars($booking['name']) ?></td>
                                <td><?= htmlspecialchars($booking['mobile']) ?></td>
                                <td>
                                    <div class="time-slot-display"><?= htmlspecialchars($scheduledSlot) ?></div>
                                </td>
                                <td>
                                    <div class="time-slot-display"><?= htmlspecialchars($revisedSlot) ?></div>
                                </td>
                                <td>
                                    <span class="token-status <?= $status === 'completed' ? 'status-completed' : 'status-pending' ?>">
                                        <?= ucfirst($status) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                }
            } else {
                echo '<div class="empty-message">No appointments booked for today yet.</div>';
            }
            ?>
        </div>

        <!-- Upcoming Tokens -->
        <div id="upcoming" class="booking-group">
            <?php
            $upcomingBookings = array_filter($bookings, function($b) {
                return $b['token_date'] > date('Y-m-d');
            });

            if (!empty($upcomingBookings)) {
                // Group by date then city
                $byDate = [];
                foreach ($upcomingBookings as $b) {
                    $date = $b['token_date'];
                    if (!isset($byDate[$date])) $byDate[$date] = [];
                    $byDate[$date][] = $b;
                }
                ksort($byDate);

                foreach ($byDate as $date => $dateBookings) {
                    $byCity = [];
                    foreach ($dateBookings as $b) {
                        $city = strtolower(trim($b['location']));
                        if (!isset($byCity[$city])) $byCity[$city] = [];
                        $byCity[$city][] = $b;
                    }

                    foreach ($byCity as $city => $cityBookings) {
                        if ($city !== $filterCity) continue;

                        usort($cityBookings, function($a, $b) {
                            return (int)$a['token_no'] - (int)$b['token_no'];
                        });

                        $dayName = date('l', strtotime($date));
                        $dateFormatted = date('d-M-Y', strtotime($date));
                        ?>
                        <div class="group-header">
                            <h2><?= htmlspecialchars(ucfirst($city)) ?> - <?= $dateFormatted ?> (<?= $dayName ?>)</h2>
                            <div class="group-meta">
                                <div class="meta-item">
                                    <strong>Total Appointments:</strong> <?= count($cityBookings) ?>
                                </div>
                            </div>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Token #</th>
                                    <th>Name</th>
                                    <th>Mobile</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cityBookings as $booking): ?>
                                <tr>
                                    <td class="token-no"><?= sprintf('%02d', (int)$booking['token_no']) ?></td>
                                    <td class="token-name"><?= htmlspecialchars($booking['name']) ?></td>
                                    <td><?= htmlspecialchars($booking['mobile']) ?></td>
                                    <td>
                                        <span class="token-status status-pending">Booked</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php
                    }
                }
            } else {
                echo '<div class="empty-message">No upcoming appointments.</div>';
            }
            ?>
        </div>
    </div>

    <?php include __DIR__ . '/footer.php'; ?>

    <script>
        // Update live time
        function updateTime() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const mins = String(now.getMinutes()).padStart(2, '0');
            document.getElementById('liveTime').textContent = hours + ':' + mins;
        }
        updateTime();
        setInterval(updateTime, 1000);

        // Tab switching
        function switchTab(tab) {
            document.querySelectorAll('.booking-group').forEach(el => el.classList.remove('active'));
            document.getElementById(tab).classList.add('active');
            
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }

        // Location switching
        function switchLocation(location) {
            window.location.href = '?city=' + location;
        }

        // Set initial location from current filter
        const urlParams = new URLSearchParams(window.location.search);
        const city = urlParams.get('city') || 'solapur';
        document.getElementById('locationSelect').value = city;
    </script>
</body>
</html>
