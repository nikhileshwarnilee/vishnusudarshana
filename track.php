<style>
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');

html, body {
    font-family: 'Marcellus', serif !important;
}
:root {
    --cream-bg: #fffbe6;
    --gold-shadow: 0 4px 18px rgba(212,175,55,0.10);
    --gold-shadow-hover: 0 12px 36px rgba(212,175,55,0.18);
    --gold-border: #ffe9a7;
    --gold-border-hover: #FFD700;
}
.main-content {
    background: var(--cream-bg);
    min-height: 100vh;
}
.track-table {
    background: linear-gradient(135deg, #fffbe6 0%, #fff9e0 60%, #f7e9c7 100%);
    border-radius: 18px;
    box-shadow: var(--gold-shadow);
    border: 1.5px solid var(--gold-border);
    overflow: hidden;
    transition: box-shadow 0.3s, border-color 0.3s;
}
.track-table th, .track-table td {
    background: transparent;
    font-size: 0.92em;
    padding: 7px 8px;
    vertical-align: middle;
}
.track-table th {
    color: #fff;
    font-weight: 700;
    letter-spacing: 0.01em;
    background: var(--maroon);
    font-size: 1em;
    border-bottom: 2px solid #ffe9a7;
}
.track-table tr {
    transition: background 0.2s;
    border-bottom: 1px solid #f7e9c7;
}
.track-table tr:hover {
    background: #fffbe6;
}
.table-responsive {
    box-shadow: var(--gold-shadow);
    border-radius: 18px;
    background: transparent;
    padding: 0.5rem 0.5rem 0 0.5rem;
}
.status-badge {
    background: #fffbe6;
    color: var(--maroon);
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.98em;
    box-shadow: 0 1px 4px #ffe9a733;
    padding: 2px 12px;
    display: inline-block;
    border: 1px solid var(--gold-border);
    transition: background 0.2s, border-color 0.2s;
}
.status-badge.status-paid {
    background: #e5ffe5;
    color: #1b5e20;
    border-color: #b6e6b6;
}
.status-badge.status-received {
    background: #e5f0ff;
    color: #0056b3;
    border-color: #b6d6e6;
}
.status-badge.status-unknown {
    background: #f3e5ff;
    color: #6a1b9a;
    border-color: #d6b6e6;
}
.status-badge:hover {
    background: #fff9e0;
    border-color: var(--gold-border-hover);
}
.download-btn {
    background: linear-gradient(90deg, #FFD700 0%, #FFFACD 100%);
    color: var(--maroon);
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1.05em;
    padding: 10px 24px;
    box-shadow: 0 2px 8px rgba(212,175,55,0.10);
    margin-bottom: 4px;
    text-decoration: none !important;
    display: inline-block;
    min-width: 120px;
    text-align: center;
    transition: all 0.18s cubic-bezier(.4,1.3,.6,1);
    cursor: pointer;
}
.download-btn:active, .download-btn:hover {
    background: #FFD700;
    color: #fff;
    box-shadow: 0 6px 18px rgba(212,175,55,0.18);
}

/* OTP Modal Styles */
.otp-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.otp-modal-content {
    background: linear-gradient(135deg, #fffbe6 0%, #fff9e0 60%, #f7e9c7 100%);
    margin: 10% auto;
    padding: 32px 24px;
    border-radius: 18px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 12px 36px rgba(212,175,55,0.3);
    border: 1.5px solid var(--gold-border);
    animation: slideDown 0.3s;
}

@keyframes slideDown {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.otp-modal-header {
    color: var(--maroon);
    font-size: 1.4em;
    font-weight: 900;
    margin-bottom: 12px;
    text-align: center;
}

.otp-modal-subtitle {
    color: #666;
    font-size: 0.95em;
    text-align: center;
    margin-bottom: 20px;
    line-height: 1.4;
}

.otp-input-group {
    margin-bottom: 20px;
}

.otp-input {
    width: 100%;
    padding: 14px 12px;
    font-size: 1.1em;
    border: 2px solid var(--gold-border);
    border-radius: 10px;
    background: #fff;
    color: var(--maroon);
    font-weight: 600;
    text-align: center;
    letter-spacing: 2px;
    font-family: 'Courier New', monospace;
    transition: border-color 0.2s;
}

.otp-input:focus {
    outline: none;
    border-color: #FFD700;
    box-shadow: 0 0 8px rgba(212,175,55,0.3);
}

.otp-input::placeholder {
    color: #bbb;
    letter-spacing: 0;
}

.otp-error {
    color: #cf1322;
    font-size: 0.92em;
    margin-bottom: 12px;
    text-align: center;
    display: none;
}

.otp-error.show {
    display: block;
}

.otp-button-group {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

.otp-modal-btn {
    flex: 1;
    padding: 12px 18px;
    font-size: 1em;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    font-family: 'Marcellus', serif;
}

.otp-submit-btn {
    background: linear-gradient(90deg, #FFD700 0%, #FFFACD 100%);
    color: var(--maroon);
    box-shadow: 0 2px 8px rgba(212,175,55,0.1);
}

.otp-submit-btn:hover {
    background: #FFD700;
    color: #fff;
    box-shadow: 0 6px 18px rgba(212,175,55,0.18);
}

.otp-submit-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.otp-cancel-btn {
    background: #f0f0f0;
    color: #666;
    border: 1px solid #ddd;
}

.otp-cancel-btn:hover {
    background: #e0e0e0;
}

.otp-resend {
    text-align: center;
    margin-top: 16px;
    font-size: 0.92em;
}

.otp-resend-btn {
    color: #FFD700;
    text-decoration: none;
    cursor: pointer;
    font-weight: 600;
}

.otp-resend-btn:hover {
    color: var(--maroon);
}

.otp-resend-btn:disabled {
    color: #ccc;
    cursor: not-allowed;
}

.otp-timer {
    color: #666;
    font-size: 0.9em;
    margin-top: 12px;
    text-align: center;
}

.otp-success {
    color: #1b5e20;
    font-size: 0.92em;
    margin-bottom: 12px;
    text-align: center;
    display: none;
}

.otp-success.show {
    display: block;
}
.track-hero, .track-form-section {
    background: #fffbe6;
    border-radius: 18px;
    box-shadow: var(--gold-shadow);
    padding: 32px 18px 24px 18px;
    margin: 0 auto 32px auto;
    max-width: 600px;
    text-align: center;
}
.track-hero h2 {
    color: var(--maroon);
    font-size: 2rem;
    font-weight: 900;
    margin-bottom: 10px;
}
.track-hero p {
    color: #bfa100;
    font-size: 1.13rem;
    margin-bottom: 0;
}
.track-form input, .track-form .track-btn {
    font-family: 'Marcellus', serif;
}
.track-btn {
    background: linear-gradient(90deg, #FFD700 0%, #FFFACD 100%);
    color: var(--maroon);
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1.1rem;
    padding: 14px 36px;
    box-shadow: 0 2px 8px rgba(212,175,55,0.10);
    margin-bottom: 8px;
    text-decoration: none !important;
    display: inline-block;
    min-width: 160px;
    text-align: center;
    transition: all 0.18s cubic-bezier(.4,1.3,.6,1);
    cursor: pointer;
}
.track-btn:hover, .track-btn:focus {
    background: #FFD700;
    color: #fff;
    box-shadow: 0 6px 18px rgba(212,175,55,0.18);
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
    .track-btn {
        font-size: 1rem;
        padding: 13px 18px;
        min-width: 120px;
    }
}
@media (max-width: 700px) {
    .track-table th, .track-table td {
        padding: 10px 6px;
        font-size: 0.97em;
    }
    .track-table {
        min-width: 600px;
        border-radius: 10px;
    }
    .table-responsive {
        border-radius: 10px;
    }
    .track-hero, .track-form-section {
        border-radius: 10px;
        padding: 18px 6px 14px 6px;
    }
}
</style>


<?php
$pageTitle = 'Track Services';
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
            <div class="form-group track-form-card">
                <input type="text" id="track_input" name="track_input" maxlength="30" placeholder="Enter Mobile Number or Tracking ID" required value="<?php echo isset($_POST['track_input']) ? htmlspecialchars($_POST['track_input']) : ''; ?>">
            </div>
            <div class="track-btn-wrap">
                <button type="submit" class="track-btn redesigned-cta-btn">Track Service</button>
            </div>
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
                                                <button type="button" class="download-btn" onclick="requestDownloadOTP('<?php echo htmlspecialchars($row['tracking_id']); ?>', '<?php echo htmlspecialchars($row['mobile']); ?>', '<?php echo htmlspecialchars($file['file']); ?>')" style="margin-left:8px;">Download</button>
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


<!-- OTP Verification Modal -->
<div id="otpModal" class="otp-modal">
    <div class="otp-modal-content">
        <div class="otp-modal-header">Verify Download</div>
        <div class="otp-modal-subtitle">
            We've sent a 4-digit OTP to your registered mobile number. Please enter it below to download your file.
        </div>
        
        <div class="otp-error" id="otpError"></div>
        <div class="otp-success" id="otpSuccess"></div>
        
        <form id="otpForm" onsubmit="verifyOTP(event)">
            <input type="hidden" id="trackingId" value="">
            <input type="hidden" id="mobileNumber" value="">
            <input type="hidden" id="fileName" value="">
            <input type="hidden" id="downloadToken" value="">
            
            <div class="otp-input-group">
                <input 
                    type="text" 
                    id="otpCode" 
                    class="otp-input" 
                    placeholder="Enter 4-digit OTP" 
                    maxlength="4" 
                    inputmode="numeric"
                    autocomplete="off"
                    required
                >
            </div>
            
            <div class="otp-timer">
                <span id="timerText">OTP expires in: <strong id="timeLeft">10:00</strong></span>
            </div>
            
            <div class="otp-button-group">
                <button type="submit" class="otp-modal-btn otp-submit-btn" id="submitBtn">Verify & Download</button>
                <button type="button" class="otp-modal-btn otp-cancel-btn" onclick="closeOTPModal()">Cancel</button>
            </div>
            
            <div class="otp-resend">
                <span>Didn't receive OTP? </span>
                <button type="button" class="otp-resend-btn" id="resendBtn" onclick="resendOTP()">Resend</button>
            </div>
        </form>
    </div>
</div>

<script>
let otpTimer = null;
let otpExpiryTime = null;
let currentDownloadData = {};

function requestDownloadOTP(trackingId, mobileNumber, fileName) {
    currentDownloadData = {
        trackingId: trackingId,
        mobileNumber: mobileNumber,
        fileName: fileName
    };
    
    document.getElementById('trackingId').value = trackingId;
    document.getElementById('mobileNumber').value = mobileNumber;
    document.getElementById('fileName').value = fileName;
    
    // Request OTP
    const formData = new FormData();
    formData.append('action', 'send_otp');
    formData.append('tracking_id', trackingId);
    formData.append('mobile', mobileNumber);
    
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitBtn').textContent = 'Sending OTP...';
    
    fetch('api/verify_download_otp.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('submitBtn').disabled = false;
        document.getElementById('submitBtn').textContent = 'Verify & Download';
        
        if (data.success) {
            // Show modal
            document.getElementById('otpModal').style.display = 'block';
            document.getElementById('otpCode').focus();
            
            // Clear any previous errors
            clearOTPError();
            clearOTPSuccess();
            
            // Start timer
            otpExpiryTime = Date.now() + (10 * 60 * 1000); // 10 minutes
            startOTPTimer();
            
            // Disable resend for 30 seconds
            disableResendButton();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        document.getElementById('submitBtn').disabled = false;
        document.getElementById('submitBtn').textContent = 'Verify & Download';
        console.error('Error:', error);
        alert('Failed to send OTP. Please try again.');
    });
}

function verifyOTP(e) {
    e.preventDefault();
    
    const otp = document.getElementById('otpCode').value.trim();
    const trackingId = document.getElementById('trackingId').value;
    const mobile = document.getElementById('mobileNumber').value;
    const file = document.getElementById('fileName').value;
    
    if (!otp || otp.length !== 4) {
        showOTPError('Please enter a valid 4-digit OTP.');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'verify_otp');
    formData.append('tracking_id', trackingId);
    formData.append('mobile', mobile);
    formData.append('otp', otp);
    formData.append('file', file);
    
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitBtn').textContent = 'Verifying...';
    
    fetch('api/verify_download_otp.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showOTPSuccess('OTP verified! Starting download...');
            clearOTPError();
            
            // Download the file
            setTimeout(() => {
                window.location.href = 'download.php?tracking_id=' + encodeURIComponent(trackingId) + '&file=' + encodeURIComponent(file) + '&token=' + data.data.download_token;
                
                // Close modal after a brief delay
                setTimeout(() => {
                    closeOTPModal();
                    document.getElementById('submitBtn').disabled = false;
                    document.getElementById('submitBtn').textContent = 'Verify & Download';
                }, 1000);
            }, 1500);
        } else {
            document.getElementById('submitBtn').disabled = false;
            document.getElementById('submitBtn').textContent = 'Verify & Download';
            showOTPError(data.message);
        }
    })
    .catch(error => {
        document.getElementById('submitBtn').disabled = false;
        document.getElementById('submitBtn').textContent = 'Verify & Download';
        console.error('Error:', error);
        showOTPError('Error verifying OTP. Please try again.');
    });
}

function resendOTP() {
    const trackingId = document.getElementById('trackingId').value;
    const mobile = document.getElementById('mobileNumber').value;
    
    const formData = new FormData();
    formData.append('action', 'send_otp');
    formData.append('tracking_id', trackingId);
    formData.append('mobile', mobile);
    
    document.getElementById('resendBtn').disabled = true;
    
    fetch('api/verify_download_otp.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            clearOTPError();
            showOTPSuccess('OTP sent again! Check your phone.');
            document.getElementById('otpCode').value = '';
            
            // Reset timer
            otpExpiryTime = Date.now() + (10 * 60 * 1000);
            startOTPTimer();
            
            // Disable resend for 30 seconds
            disableResendButton();
        } else {
            showOTPError('Failed to resend OTP: ' + data.message);
            document.getElementById('resendBtn').disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showOTPError('Error resending OTP. Please try again.');
        document.getElementById('resendBtn').disabled = false;
    });
}

function startOTPTimer() {
    if (otpTimer) clearInterval(otpTimer);
    
    otpTimer = setInterval(() => {
        const now = Date.now();
        const remaining = otpExpiryTime - now;
        
        if (remaining <= 0) {
            clearInterval(otpTimer);
            showOTPError('OTP has expired. Please request a new OTP.');
            document.getElementById('submitBtn').disabled = true;
            return;
        }
        
        const minutes = Math.floor(remaining / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);
        const timeText = (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
        document.getElementById('timeLeft').textContent = timeText;
    }, 1000);
}

function disableResendButton() {
    const resendBtn = document.getElementById('resendBtn');
    resendBtn.disabled = true;
    
    let countdown = 30;
    const interval = setInterval(() => {
        if (countdown > 0) {
            resendBtn.textContent = 'Resend in ' + countdown + 's';
            countdown--;
        } else {
            clearInterval(interval);
            resendBtn.disabled = false;
            resendBtn.textContent = 'Resend';
        }
    }, 1000);
}

function showOTPError(message) {
    const errorEl = document.getElementById('otpError');
    errorEl.textContent = message;
    errorEl.classList.add('show');
}

function clearOTPError() {
    const errorEl = document.getElementById('otpError');
    errorEl.textContent = '';
    errorEl.classList.remove('show');
}

function showOTPSuccess(message) {
    const successEl = document.getElementById('otpSuccess');
    successEl.textContent = message;
    successEl.classList.add('show');
}

function clearOTPSuccess() {
    const successEl = document.getElementById('otpSuccess');
    successEl.textContent = '';
    successEl.classList.remove('show');
}

function closeOTPModal() {
    document.getElementById('otpModal').style.display = 'none';
    clearInterval(otpTimer);
    document.getElementById('otpCode').value = '';
    clearOTPError();
    clearOTPSuccess();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('otpModal');
    if (event.target === modal) {
        closeOTPModal();
    }
}
</script>

<?php include 'footer.php'; ?>
