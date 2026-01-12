

<style>

.status-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    font-size: 1.04em;
}
@media (max-width: 600px) {
    .status-card {
        padding: 12px 4px;
        max-width: 98vw;
        font-size: 0.98em;
    }
    .status-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 2px;
        font-size: 0.98em;
    }
    .status-label {
        min-width: unset;
        font-size: 0.98em;
    }
    .download-btn {
        font-size: 0.97em;
        padding: 10px 0;
    }
}

.table-responsive {
    width: 100%;
    overflow-x: auto;
    margin-bottom: 18px;
}
.track-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
    background: #fff;
    box-shadow: 0 2px 12px #e0bebe22;
    border-radius: 12px;
    overflow: hidden;
}
.track-table th, .track-table td {
    padding: 12px 10px;
    text-align: left;
    border-bottom: 1px solid #f3caca;
    font-size: 1.04em;
}
.track-table th {
    background: #f9eaea;
    color: var(--maroon);
    font-weight: 700;
    letter-spacing: 0.01em;
}
.track-table tr:last-child td {
    border-bottom: none;
}
.status-badge {
    padding: 2px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.98em;
    background: var(--button-hover);
    color: var(--maroon);
    display: inline-block;
}
.status-badge.status-paid { background: var(--success-bg); color: var(--success-text); }
.status-badge.status-received { background: var(--info-bg); color: var(--info-text); }
.status-badge.status-unknown { background: var(--muted-bg); color: var(--muted-text); }
.download-btn {
    background: var(--maroon);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 0.98em;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(139, 21, 56, 0.15);
    transition: background 0.15s;
    display: inline-block;
}
.download-btn:active { background: var(--dark-maroon); }
@media (max-width: 700px) {
    .track-table th, .track-table td {
        padding: 10px 6px;
        font-size: 0.97em;
    }
    .track-table {
        min-width: 600px;
    }
}
</style>


<?php
require_once 'header.php';
require_once __DIR__ . '/config/db.php';

$results = [];
$errorMsg = '';
$searched = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $searched = true;
    $input = trim($_POST['track_input'] ?? '');
    if ($input === '') {
        $errorMsg = 'Please enter your mobile number or tracking ID.';
    } else {
        if (preg_match('/^[0-9]{10,15}$/', $input)) {
            // Numeric: treat as mobile
            $stmt = $pdo->prepare('SELECT * FROM service_requests WHERE mobile = ? ORDER BY created_at DESC');
            $stmt->execute([$input]);
        } else {
            // Otherwise: treat as tracking ID
            $stmt = $pdo->prepare('SELECT * FROM service_requests WHERE tracking_id = ? ORDER BY created_at DESC');
            $stmt->execute([$input]);
        }
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<main class="main-content" style="background-color:var(--cream-bg);">
    <section class="track-hero">
        <h2>Track Your Service</h2>
        <p>Enter your mobile number or tracking ID to check your service status.</p>
    </section>

    <section class="track-form-section">
        <form class="track-form" method="post" autocomplete="off">
            <div class="form-group">
                <input type="text" id="track_input" name="track_input" maxlength="30" placeholder="Enter Mobile Number or Tracking ID" required value="<?php echo isset($_POST['track_input']) ? htmlspecialchars($_POST['track_input']) : ''; ?>" style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid #e0bebe;font-size:1.08em;">
            </div>
            <button type="submit" class="track-btn" style="width:100%;background:var(--maroon);color:#fff;border:none;border-radius:8px;padding:12px 0;font-size:1.08em;font-weight:600;margin-top:10px;cursor:pointer;">Track Service</button>
        </form>
    </section>

    <section class="track-status-section">
        <?php if ($searched): ?>
            <?php if ($errorMsg): ?>
                <div class="alert-box" style="background:#fff1f0;color:#cf1322;padding:14px 10px;text-align:center;font-weight:600;">
                    <?php echo $errorMsg; ?>
                </div>
            <?php elseif (empty($results)): ?>
                <div class="alert-box" style="background:var(--light-bg);color:#555;padding:14px 10px;text-align:center;font-weight:500;">
                    No service found for the given details.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                <table class="track-table">
                    <thead>
                        <tr>
                            <th>Tracking ID</th>
                            <th>Service Category</th>
                            <th>Products Chosen</th>
                            <th>Date</th>
                            <?php
                            // Check if any result is an appointment to show the column
                            $showScheduleCol = false;
                            foreach ($results as $row) {
                                if (
                                    (isset($row['category_slug']) && $row['category_slug'] === 'appointment') ||
                                    (isset($row['tracking_id']) && strpos($row['tracking_id'], 'APT-') === 0)
                                ) {
                                    $showScheduleCol = true;
                                    break;
                                }
                            }
                            if ($showScheduleCol) {
                                echo '<th>Scheduled Date & Time</th>';
                            }
                            ?>
                            <th>Total Amount</th>
                            <th>Payment Status</th>
                            <th>Service Status</th>
                            <th>Download</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['tracking_id']); ?></td>
                            <td><?php
                                $categoryTitles = [
                                    'birth-child' => 'Birth & Child Services',
                                    'marriage-matching' => 'Marriage & Matching',
                                    'astrology-consultation' => 'Astrology Consultation',
                                    'muhurat-event' => 'Muhurat & Event Guidance',
                                    'pooja-vastu-enquiry' => 'Pooja, Ritual & Vastu Enquiry',
                                    'appointment' => 'Appointment'
                                ];
                                $cat = $row['category_slug'];
                                echo isset($categoryTitles[$cat]) ? $categoryTitles[$cat] : htmlspecialchars($cat);
                            ?></td>
                            <td>
                                <?php
                                $products = '-';
                                $decoded = [];
                                if (isset($row['selected_products'])) {
                                    $decoded = json_decode($row['selected_products'], true);
                                }
                                if ($row['category_slug'] === 'appointment') {
                                    $products = 'Appointment';
                                } elseif (is_array($decoded) && count($decoded)) {
                                    $names = [];
                                    // Use DB connection to fetch product names
                                    foreach ($decoded as $prod) {
                                        if (isset($prod['id'])) {
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
                            <td><?php echo date('d-m-Y', strtotime($row['created_at'])); ?></td>
                            <?php if ($showScheduleCol): ?>
                                <td>
                                <?php
                                // Only show for appointments
                                if (
                                    (isset($row['category_slug']) && $row['category_slug'] === 'appointment') ||
                                    (isset($row['tracking_id']) && strpos($row['tracking_id'], 'APT-') === 0)
                                ) {
                                    // Parse form_data for scheduling info
                                    $fd = [];
                                    if (!empty($row['form_data'])) {
                                        $fd = json_decode($row['form_data'], true) ?? [];
                                    }
                                    // Always show assigned_date if present
                                    $assignedDate = $fd['assigned_date'] ?? '';
                                    $fromTime = $fd['assigned_from_time'] ?? ($fd['time_from'] ?? '');
                                    $toTime = $fd['assigned_to_time'] ?? ($fd['time_to'] ?? '');
                                    if ($assignedDate && $fromTime && $toTime) {
                                        $dateFmt = DateTime::createFromFormat('Y-m-d', $assignedDate);
                                        $dateDisp = $dateFmt ? $dateFmt->format('d-M-Y') : htmlspecialchars($assignedDate);
                                        $fromFmt = date('h:i A', strtotime($fromTime));
                                        $toFmt = date('h:i A', strtotime($toTime));
                                        echo htmlspecialchars($dateDisp . ' | ' . $fromFmt . ' – ' . $toFmt);
                                    } elseif ($assignedDate) {
                                        $dateFmt = DateTime::createFromFormat('Y-m-d', $assignedDate);
                                        $dateDisp = $dateFmt ? $dateFmt->format('d-M-Y') : htmlspecialchars($assignedDate);
                                        echo 'Assigned on ' . htmlspecialchars($dateDisp) . ' (Time pending)';
                                    } else {
                                        echo 'Time not set';
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                                </td>
                            <?php endif; ?>
                            <td>₹<?php echo number_format($row['total_amount'], 2); ?></td>
                            <td>
                                <?php
                                $payStatus = strtolower($row['payment_status'] ?? '');
                                if ($payStatus === 'paid') {
                                    echo '<span class="status-badge status-paid">Paid</span>';
                                } elseif ($payStatus === 'unpaid' || $payStatus === 'pending' || $payStatus === '') {
                                    echo '<span class="status-badge" style="background:var(--dark-maroon);color:#fff;">Unpaid</span>';
                                } else {
                                    echo '<span class="status-badge status-unknown">' . htmlspecialchars(ucfirst($row['payment_status'])) . '</span>';
                                }
                                ?>
                            </td>
                            <td><span class="status-badge status-<?php echo strtolower($row['service_status']); ?>"><?php echo htmlspecialchars($row['service_status']); ?></span></td>
                            <td>
                                <div style="min-width:180px;">
                                    <div style="font-weight:600;color:var(--maroon);margin-bottom:4px;">Service Files / Reports</div>
                                    <?php
                                    // Fetch uploaded_files
                                    $files = [];
                                    if (!empty($row['tracking_id'])) {
                                        $stmtFiles = $pdo->prepare('SELECT uploaded_files FROM service_requests WHERE tracking_id = ?');
                                        $stmtFiles->execute([$row['tracking_id']]);
                                        $uf = $stmtFiles->fetchColumn();
                                        if ($uf) {
                                            $files = json_decode($uf, true) ?: [];
                                        }
                                    }
                                    if ($files && count($files) > 0): ?>
                                        <ul style="list-style:none;padding:0;margin:0;">
                                        <?php foreach ($files as $file): ?>
                                            <li style="margin-bottom:6px;">
                                                <span style="font-size:0.98em;"><?php echo htmlspecialchars($file['name']); ?></span>
                                                <a href="download.php?tracking_id=<?php echo urlencode($row['tracking_id']); ?>&file=<?php echo urlencode($file['file']); ?>" class="download-btn" style="margin-left:8px;">Download</a>
                                            </li>
                                        <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <span style="color:#888;font-size:0.97em;">Files will be available once the service is completed.</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<?php include 'footer.php'; ?>
