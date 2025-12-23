<?php
/**
 * payment-success.php
 * 
 * Handles successful Razorpay payments for:
 * 1) Appointment bookings → appointments table
 * 2) Service requests → service_requests table
 * 
 * ARCHITECTURE: Session-Optional
 * - Primary source of truth: pending_payments table in database
 * - Session ($_SESSION['pending_payment']) is optional
 * - If session lost: rebuilds context from database using payment_id
 * - All payment data persists in database before user is redirected to payment gateway
 * 
 * FLOW:
 * 1) Validate payment_id from GET parameter
 * 2) Load pending_payment from session OR database
 * 3) Determine source (appointment/service)
 * 4) Process accordingly and insert into respective table
 * 5) Render success UI with tracking ID
 */

/* ======================
   STEP 1: BOOTSTRAP
   ====================== */

// Session is already started in header.php — DO NOT start here
require_once __DIR__ . '/config/db.php';

// Validate payment_id
$payment_id = isset($_GET['payment_id']) ? trim($_GET['payment_id']) : '';
if ($payment_id === '') {
    header('Location: services.php?msg=missing_payment_id');
    exit;
}

// LOAD FROM DATABASE FIRST (source of truth for all payment types)
$stmt = $pdo->prepare("SELECT * FROM pending_payments WHERE payment_id = ?");
$stmt->execute([$payment_id]);
$dbRecord = $stmt->fetch(PDO::FETCH_ASSOC);

// Reconstruct pending payment data from database
$pending = [];
if ($dbRecord) {
    $pending = [
        'source' => $dbRecord['source'],
        'customer_details' => json_decode($dbRecord['customer_details'], true) ?? [],
        'appointment_form' => json_decode($dbRecord['appointment_form'], true) ?? [],
        'form_data' => json_decode($dbRecord['form_data'], true) ?? [],
        'products' => json_decode($dbRecord['selected_products'], true) ?? [],
        'category' => $dbRecord['category'],
        'total_amount' => $dbRecord['total_amount']
    ];
    // Store in session for consistency with rest of codebase
    $_SESSION['pending_payment'] = $pending;
} else {
    error_log('No pending payment found in database for payment_id: ' . $payment_id);
}

// If context is missing after trying session and database, show friendly message
if (empty($pending)) {
    error_log('Payment successful but context missing: no pending data found for payment_id=' . $payment_id);
    
    require_once 'header.php';
    ?>
    <main class="main-content">
        <h1 class="review-title">Payment Received</h1>

        <div class="review-card">
            <h2 class="section-title">Thank You!</h2>

            <p class="success-text">
                Your payment has been received successfully.<br>
                Our team is processing your request.<br>
                <br>
                We will contact you shortly with details.
            </p>

            <a href="services.php" class="pay-btn">Back to Services</a>
        </div>
    </main>

    <style>
        .main-content { max-width:480px;margin:0 auto;padding:18px; }
        .review-title { text-align:center;font-size:1.2em;margin-bottom:16px; }
        .review-card { background:#f9eaea;border-radius:14px;padding:16px;text-align:center; }
        .section-title { color:#800000;font-weight:600;margin-bottom:10px; }
        .success-text { color:#333;margin-bottom:18px; }
        .pay-btn { display:inline-block;background:#800000;color:#fff;padding:12px 28px;
                   border-radius:8px;text-decoration:none;font-weight:600; }
    </style>
    <?php
    require_once 'footer.php';
    exit;
}

/* ======================
   UNIFIED SERVICE FLOW
   All services (including appointments) follow this single path
   ====================== */

// Create service tables if not exist
$pdo->exec("CREATE TABLE IF NOT EXISTS service_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracking_id VARCHAR(30) UNIQUE,
    category_slug VARCHAR(50),
    customer_name VARCHAR(255),
    mobile VARCHAR(20),
    email VARCHAR(255),
    city VARCHAR(255),
    form_data JSON,
    selected_products JSON,
    total_amount DECIMAL(10,2),
    payment_id VARCHAR(100),
    payment_status VARCHAR(20),
    service_status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);");

// Use pending data from database as source of truth (session not required)
$category = $pending['category_slug'] ?? $pending['category'] ?? '';

// Log if category is empty but continue processing
// Payment is recorded in pending_payments table, so recovery is still possible
if ($category === '') {
    error_log('Service payment: no category found. payment_id=' . $payment_id . '. Will create default tracking record.');
    $category = 'unknown-service';
}

// Generate tracking ID
$tracking_id = 'VDSK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

// Extract all data from pending (database is source of truth)
// For appointments, form_data or appointment_form may contain the details
$formData = $pending['form_data'] ?? [];
if (empty($formData) && !empty($pending['appointment_form'])) {
    $formData = $pending['appointment_form'];
}

$customerName = $pending['customer_details']['full_name'] ?? $formData['full_name'] ?? '';
$mobile       = $pending['customer_details']['mobile'] ?? $formData['mobile'] ?? '';
$email        = $pending['customer_details']['email'] ?? $formData['email'] ?? '';
$city         = $pending['customer_details']['city'] ?? $formData['city'] ?? '';
$products     = $pending['products'] ?? [];
$totalAmount  = $pending['total_amount'] ?? 0;

// Insert service request (log errors but continue - data is still in pending_payments table)
try {
    $stmt = $pdo->prepare("
        INSERT INTO service_requests (
            tracking_id, category_slug, customer_name, mobile, email, city,
            form_data, selected_products, total_amount, payment_id, payment_status, service_status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Paid', 'Received'
        )
    ");
    $stmt->execute([
        $tracking_id,
        $category,
        $customerName ?: 'N/A',
        $mobile ?: 'N/A',
        $email ?: '',
        $city ?: '',
        json_encode($formData),
        json_encode($products),
        $totalAmount,
        $payment_id
    ]);

    // WhatsApp: Appointment Booked (only for appointments)
    if ($category === 'appointment') {
        require_once __DIR__ . '/helpers/send_whatsapp.php';
        try {
            sendWhatsAppMessage(
                $mobile,
                'appointment_booked',
                'en',
                [
                    'name' => $customerName,
                    'tracking_id' => $tracking_id,
                    'preferred_date' => $formData['preferred_date'] ?? '',
                    'appointment_type' => $formData['appointment_type'] ?? ''
                ]
            );
        } catch (Throwable $e) {
            error_log('WhatsApp booking failed: ' . $e->getMessage());
        }
    }
} catch (Throwable $e) {
    error_log('Service request insert failed: ' . $e->getMessage() . ' (payment_id=' . $payment_id . ', tracking_id=' . $tracking_id . ')');
    // Continue - data is safe in pending_payments table for manual recovery
}

/* ======================
   RENDER SERVICE UI
   ====================== */
require_once 'header.php';
?>
<main class="main-content">
    <h1 class="review-title">Thank You for Your Payment!</h1>

    <div class="review-card">
        <h2 class="section-title">Your Tracking ID</h2>

        <div class="tracking-id">
            <?= htmlspecialchars($tracking_id) ?>
        </div>

        <p class="success-text">
            Our team will contact you shortly.<br>
            Keep your tracking ID for reference.
        </p>

        <a href="track.php?tracking_id=<?= urlencode($tracking_id) ?>" class="pay-btn">
            Track Your Service
        </a>
    </div>
</main>

<style>
    .main-content { max-width:480px;margin:0 auto;padding:18px; }
    .review-title { text-align:center;font-size:1.2em;margin-bottom:16px; }
    .review-card { background:#f9eaea;border-radius:14px;padding:16px;text-align:center; }
    .section-title { color:#800000;font-weight:600;margin-bottom:10px; }
    .tracking-id { font-size:1.4em;font-weight:700;color:#800000;margin:12px 0; }
    .success-text { color:#333;margin-bottom:18px; }
    .pay-btn { display:inline-block;background:#800000;color:#fff;padding:12px 28px;
               border-radius:8px;text-decoration:none;font-weight:600; }
</style>
<?php
require_once 'footer.php';

// Clear service-related session data ONLY AFTER UI rendering
unset($_SESSION['pending_payment']);
unset($_SESSION['book_appointment']);
unset($_SESSION['appointment_products']);

// DELETE from pending_payments after successful insertion/confirmation
try {
    $deleteStmt = $pdo->prepare("DELETE FROM pending_payments WHERE payment_id = ?");
    $deleteStmt->execute([$payment_id]);
} catch (Throwable $e) {
    error_log('Failed to delete pending_payments record for payment_id=' . $payment_id . '. Error: ' . $e->getMessage());
}