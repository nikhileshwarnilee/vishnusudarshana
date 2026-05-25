<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
require_once __DIR__ . '/../../config/db.php';

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
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        button.action-link {
            border: none;
            cursor: pointer;
            font-family: inherit;
        }
        .action-link:hover {
            background: #600000;
        }
        .action-link.danger-action {
            background: #c00;
        }
        .action-link.danger-action:hover {
            background: #990000;
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
        .start-btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 8px;
            background: #1a8917;
            color: #fff;
            font-weight: 600;
            font-size: 0.95em;
            border: none;
            cursor: pointer;
            transition: background 0.3s;
        }
        .start-btn:hover {
            background: #158910;
        }
        .start-btn:disabled {
            background: #888;
            cursor: not-allowed;
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
        .call-btn, .present-btn, .complete-btn, .revert-btn, .delete-btn {
            font-size: 0.95em;
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-right: 6px;
        }
        .call-btn { background: #d18b00; color: #fff; }
        .present-btn, .complete-btn { background: #1a8917; color: #fff; }
        .revert-btn { background: #888; color: #fff; }
        .delete-btn { background: #c00; color: #fff; }
        .called-row td {
            background: #fff4cf !important;
        }
        .called-badge {
            display: inline-block;
            margin-left: 6px;
            padding: 2px 7px;
            border-radius: 999px;
            background: #fff0bd;
            color: #7c4b00;
            font-size: 0.78em;
            font-weight: 700;
        }
        @media (max-width: 800px) {
            .bookedTokensTable { font-size: 0.88em; }
            .page-header { padding: 14px; }
        }
        @media (max-width: 600px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            #cityFilter { width: 100%; }
            body { padding: 12px 0 20px 0; }
            .admin-container { padding: 0 8px 18px 8px; }
            .page-header h2 { font-size: 1.1em; }
            .header-actions { width: 100%; }
            .action-link { width: 100%; text-align: center; }
            .filter-bar { width: 100%; flex-wrap: wrap; }
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
                <button type="button" class="action-link danger-action" id="deletePreviousBookingsBtn">Delete Previous Records</button>
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

    <div class="admin-container" id="bookedTokenGroups">
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
            $perTokenLabel = '-';
            $slotInfoStmt = $pdo->prepare("SELECT from_time, to_time, total_tokens FROM token_management WHERE token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) LIMIT 1");
            $slotInfoStmt->execute([$date, $city]);
            $slotInfo = $slotInfoStmt->fetch(PDO::FETCH_ASSOC);
            if ($slotInfo && $slotInfo['from_time'] && $slotInfo['to_time'] && (int)$slotInfo['total_tokens'] > 0) {
                $fromParts = explode(':', $slotInfo['from_time']);
                $toParts = explode(':', $slotInfo['to_time']);
                if (count($fromParts) >= 2 && count($toParts) >= 2) {
                    $fromMins = intval($fromParts[0]) * 60 + intval($fromParts[1]);
                    $toMins = intval($toParts[0]) * 60 + intval($toParts[1]);
                    $diffMins = $toMins - $fromMins;
                    if ($diffMins > 0) {
                        $perMins = floor($diffMins / (int)$slotInfo['total_tokens']);
                        if ($perMins > 0) {
                            $hours = floor($perMins / 60);
                            $mins = $perMins % 60;
                            $perTokenLabel = ($hours ? $hours . 'h ' : '') . $mins . 'm';
                        }
                    }
                }
            }
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
            <div class="group-meta">Total Tokens: <?= isset($tokenTotals[$date][$city]) ? $tokenTotals[$date][$city] : '-' ?> | Booked Tokens: <?= count($cityBookings) ?> | Per Token: <?= htmlspecialchars($perTokenLabel) ?></div>
            <?php if ($date === date('Y-m-d')): ?>
            <?php
                // Check if tokens 1-5 are all completed
                $allCompleted = true;
                $checkStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM token_bookings WHERE token_no IN (1,2,3,4,5) AND token_date = ? AND LOWER(TRIM(location)) = LOWER(TRIM(?)) AND (status IS NULL OR LOWER(TRIM(status)) != 'completed')");
                $checkStmt->execute([$date, $city]);
                $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if ($checkResult && (int)$checkResult['cnt'] > 0) {
                    $allCompleted = false;
                }
            ?>
            <button class="start-btn" data-date="<?= htmlspecialchars($date) ?>" data-city="<?= htmlspecialchars($city) ?>" <?php if ($allCompleted): ?>disabled<?php endif; ?>><?php if ($allCompleted): ?>All Completed<?php else: ?>Send Reminder<?php endif; ?></button>
            <?php endif; ?>
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
                    <th>Delayed By</th>
                    <th>Action</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php $rowIndex = 0; foreach ($cityBookings as $b): ?>
                <?php $bookingStatus = strtolower(trim((string)($b['status'] ?? ''))); ?>
                <tr class="<?= $bookingStatus === 'called' ? 'called-row' : '' ?>">
                    <td class="token-no"><?= htmlspecialchars($b['token_no']) ?><?php if ($bookingStatus === 'called'): ?><span class="called-badge">Called</span><?php endif; ?></td>
                    <td><?= htmlspecialchars($b['location']) ?></td>
                    <td>
                        <a href="../cif/index.php?prefill_name=<?= rawurlencode($b['name']) ?>" style="color:#0056b3;text-decoration:underline;">
                            <?= htmlspecialchars($b['name']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars(vs_format_mobile_for_display($b['mobile'] ?? '')) ?></td>
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
                        $slotStartMins = null;
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
                                    $slotStartMins = $startMins;
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
                        $revisedStartMins = null;
                        if ($perMinsCalc > 0) {
                            $nowMins = ((int)date('G')) * 60 + (int)date('i');
                            $revStart = $nowMins + ($rowIndex * $perMinsCalc);
                            $revEnd = $nowMins + (($rowIndex + 1) * $perMinsCalc);
                            $revisedStartMins = $revStart;
                            $revisedText = minsToTime($revStart) . ' - ' . minsToTime($revEnd);
                        }
                        echo htmlspecialchars($revisedText);
                        ?>
                    </td>
                    <td>
                        <?php
                        $delayText = '-';
                        if ($slotStartMins !== null && $revisedStartMins !== null) {
                            $delayMins = $revisedStartMins - $slotStartMins;
                            if ($delayMins > 0) {
                                $hours = floor($delayMins / 60);
                                $mins = $delayMins % 60;
                                $delayText = ($hours ? $hours . 'h ' : '') . $mins . 'm late';
                            } elseif ($delayMins < 0) {
                                $earlyMins = abs($delayMins);
                                $hours = floor($earlyMins / 60);
                                $mins = $earlyMins % 60;
                                $delayText = 'Early by ' . ($hours ? $hours . 'h ' : '') . $mins . 'm';
                            } else {
                                $delayText = 'On time';
                            }
                        }
                        if (strpos($delayText, 'late') !== false) {
                            echo '<span style="color:#c00;font-weight:bold;">' . htmlspecialchars($delayText) . '</span>';
                        } else {
                            echo htmlspecialchars($delayText);
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($bookingStatus === 'completed'): ?>
                            <button class="revert-btn" data-booking-id="<?= htmlspecialchars($b['id']) ?>" style="background:#888;color:#fff;padding:4px 10px;border:none;border-radius:4px;cursor:pointer;margin-right:6px;">Revert</button>
                        <?php elseif ($bookingStatus === 'skip'): ?>
                            <button class="unskip-btn" data-booking-id="<?= htmlspecialchars($b['id']) ?>" style="background:#888;color:#fff;padding:4px 10px;border:none;border-radius:4px;cursor:pointer;margin-right:6px;">Unskip</button>
                        <?php elseif ($bookingStatus === 'called'): ?>
                            <button class="present-btn" data-booking-id="<?= htmlspecialchars($b['id']) ?>" style="background:#1a8917;color:#fff;padding:4px 10px;border:none;border-radius:4px;cursor:pointer;margin-right:6px;">Present</button>
                            <button class="skip-btn" data-booking-id="<?= htmlspecialchars($b['id']) ?>" style="background:#ffc107;color:#333;padding:4px 10px;border:none;border-radius:4px;cursor:pointer;margin-right:6px;">Skip</button>
                        <?php else: ?>
                            <button class="call-btn" data-booking-id="<?= htmlspecialchars($b['id']) ?>" style="background:#d18b00;color:#fff;padding:4px 10px;border:none;border-radius:4px;cursor:pointer;margin-right:6px;">Call</button>
                            <button class="skip-btn" data-booking-id="<?= htmlspecialchars($b['id']) ?>" style="background:#ffc107;color:#333;padding:4px 10px;border:none;border-radius:4px;cursor:pointer;margin-right:6px;">Skip</button>
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
    (function() {
        var cityStorageKey = 'bookedTokensCityFilter';
        var cityFilterDropdown = document.getElementById('cityFilter');
        var tokenGroups = document.getElementById('bookedTokenGroups');
        var isRefreshing = false;

        function hasCityOption(city) {
            if (!cityFilterDropdown) return false;
            return Array.prototype.some.call(cityFilterDropdown.options, function(option) {
                return option.value === city;
            });
        }

        function resolveCity(preferredCity) {
            if (preferredCity && hasCityOption(preferredCity)) {
                return preferredCity;
            }
            var savedCity = localStorage.getItem(cityStorageKey) || '';
            if (savedCity && hasCityOption(savedCity)) {
                return savedCity;
            }
            return hasCityOption('solapur') ? 'solapur' : '';
        }

        function filterByCity(city) {
            document.querySelectorAll('#bookedTokenGroups .booking-table-group').forEach(function(group) {
                group.style.display = (!city || group.getAttribute('data-city') === city) ? '' : 'none';
            });
        }

        function applyCityFilter(preferredCity) {
            if (!cityFilterDropdown) return;
            var city = resolveCity(preferredCity);
            cityFilterDropdown.value = city;
            localStorage.setItem(cityStorageKey, city);
            filterByCity(city);
        }

        function refreshBookedTokens(preferredCity) {
            if (isRefreshing || !tokenGroups) {
                return Promise.resolve();
            }
            isRefreshing = true;
            var city = preferredCity;
            if (city === undefined && cityFilterDropdown) {
                city = cityFilterDropdown.value;
            }
            var scrollY = window.scrollY;

            return fetch(window.location.href, {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(function(res) { return res.text(); })
                .then(function(html) {
                    var parsed = new DOMParser().parseFromString(html, 'text/html');
                    var freshGroups = parsed.getElementById('bookedTokenGroups');
                    var freshCityFilter = parsed.getElementById('cityFilter');

                    if (!freshGroups) {
                        throw new Error('Unable to refresh token list.');
                    }

                    tokenGroups.innerHTML = freshGroups.innerHTML;
                    if (cityFilterDropdown && freshCityFilter) {
                        cityFilterDropdown.innerHTML = freshCityFilter.innerHTML;
                    }
                    applyCityFilter(city);
                    window.scrollTo({ top: scrollY, left: 0, behavior: 'auto' });
                })
                .finally(function() {
                    isRefreshing = false;
                });
        }

        function setButtonBusy(button, busy, busyText) {
            if (!button) return;
            if (busy) {
                button.dataset.originalText = button.textContent;
                button.disabled = true;
                if (busyText) {
                    button.textContent = busyText;
                }
            } else {
                button.disabled = false;
                if (button.dataset.originalText) {
                    button.textContent = button.dataset.originalText;
                    delete button.dataset.originalText;
                }
            }
        }

        function postAction(url, body, button, busyText, failureMessage, refreshCity, refreshAfter) {
            setButtonBusy(button, true, busyText);
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: body
            })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (!data.success) {
                        throw new Error(data.message || failureMessage);
                    }
                    if (refreshAfter === false) {
                        return data;
                    }
                    return refreshBookedTokens(refreshCity).then(function() {
                        return data;
                    });
                })
                .catch(function(err) {
                    alert(err.message || failureMessage);
                    setButtonBusy(button, false);
                });
        }

        applyCityFilter();

        if (cityFilterDropdown) {
            cityFilterDropdown.addEventListener('change', function() {
                localStorage.setItem(cityStorageKey, this.value);
                filterByCity(this.value);
            });
        }

        document.addEventListener('click', function(event) {
            var button = event.target.closest('button');
            if (!button) return;

            if (button.id === 'deletePreviousBookingsBtn') {
                if (!confirm('Delete all booked tokens from previous days? Today\'s tokens will remain. This cannot be undone.')) return;
                postAction('delete-old-bookings.php', '', button, 'Deleting...', 'Failed to delete previous booked tokens.')
                    .then(function(data) {
                        if (data && data.success) {
                            alert((data.deleted_count || 0) + ' previous booked token record(s) deleted.');
                        }
                    });
                return;
            }

            var bookingId = button.getAttribute('data-booking-id');
            if (!bookingId) return;

            if (button.classList.contains('delete-btn')) {
                if (!confirm('Are you sure you want to delete this booking?')) return;
                postAction('delete-booking.php', 'id=' + encodeURIComponent(bookingId), button, 'Deleting...', 'Failed to delete booking.');
                return;
            }

            if (button.classList.contains('call-btn')) {
                postAction('call-booking.php', 'id=' + encodeURIComponent(bookingId), button, 'Calling...', 'Failed to call booking.');
                return;
            }

            if (button.classList.contains('present-btn')) {
                postAction('complete-booking.php', 'id=' + encodeURIComponent(bookingId), button, 'Saving...', 'Failed to mark booking as present.');
                return;
            }

            if (button.classList.contains('revert-btn')) {
                postAction('revert-booking.php', 'id=' + encodeURIComponent(bookingId), button, 'Reverting...', 'Failed to revert booking status.');
                return;
            }

            if (button.classList.contains('skip-btn')) {
                postAction('skip-booking.php', 'id=' + encodeURIComponent(bookingId), button, 'Skipping...', 'Failed to skip booking.');
                return;
            }

            if (button.classList.contains('unskip-btn')) {
                postAction('skip-booking.php', 'id=' + encodeURIComponent(bookingId) + '&unskip=1', button, 'Saving...', 'Failed to unskip booking.');
                return;
            }

            if (button.classList.contains('start-btn')) {
                if (button.disabled) {
                    alert('All tokens 1-5 are completed.');
                    return;
                }

                var date = button.getAttribute('data-date');
                var city = button.getAttribute('data-city');
                if (!date || !city) return;
                if (!confirm('Send revised time reminders to tokens 1-5?')) return;

                postAction(
                    'send-token-start-reminder.php',
                    'date=' + encodeURIComponent(date) + '&location=' + encodeURIComponent(city),
                    button,
                    'Sending...',
                    'Error sending reminders',
                    cityFilterDropdown ? cityFilterDropdown.value : '',
                    false
                ).then(function(data) {
                    if (data && data.success) {
                        alert('Reminders sent to ' + data.sent_count + ' token(s)');
                        button.style.display = 'none';
                    }
                });
            }
        });
    })();
    </script>
</body>
</html>

