<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/event_module.php';

vs_event_ensure_tables($pdo);

$errors = [];
$bookingReference = trim((string)($_GET['booking_reference'] ?? $_POST['booking_reference'] ?? ''));
$phoneInput = trim((string)($_GET['phone'] ?? $_POST['phone'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = preg_replace('/[^0-9]/', '', $phoneInput);
    if (strlen($phone) === 10) {
        $phone = '91' . $phone;
    }

    if ($bookingReference === '' || $phone === '') {
        $errors[] = 'Booking reference and phone are required.';
    } else {
        $stmt = $pdo->prepare("SELECT
                r.id,
                r.payment_status,
                COALESCE(ep.remaining_amount, 0) AS remaining_amount,
                COALESCE(ep.amount_paid, 0) AS amount_paid,
                COALESCE(NULLIF(pdp.price_total, 0), NULLIF(p.price_total, 0), p.price) AS package_price,
                r.persons
            FROM event_registrations r
            INNER JOIN event_packages p ON p.id = r.package_id
            LEFT JOIN event_package_date_prices pdp ON pdp.package_id = p.id AND pdp.event_date_id = r.event_date_id
            LEFT JOIN event_payments ep ON ep.registration_id = r.id
            WHERE r.booking_reference = ?
              AND r.phone = ?
            LIMIT 1");
        $stmt->execute([$bookingReference, $phone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $errors[] = 'Booking not found. Please verify reference and phone.';
        } else {
            $total = (float)$row['package_price'] * max((int)$row['persons'], 1);
            $remaining = (float)$row['remaining_amount'];
            if ($remaining <= 0) {
                $remaining = max($total - (float)$row['amount_paid'], 0);
            }

            if (strtolower((string)$row['payment_status']) === 'paid' || $remaining <= 0) {
                $errors[] = 'No remaining amount found for this booking.';
            } else {
                header('Location: event-payment.php?registration_id=' . (int)$row['id']);
                exit;
            }
        }
    }
}

$pageTitle = 'Pay Remaining Amount';
require_once 'header.php';
?>
<main class="event-payment-main" style="background-color:var(--cream-bg);">
    <section class="event-payment-wrap">
        <a href="events.php" class="back-link">&larr; Back to Events</a>
        <div class="card">
            <h1>Remaining Payment Lookup</h1>
            <p class="small">Enter booking reference and phone to continue remaining payment.</p>
            <?php if (!empty($errors)): ?><div class="notice err"><?php foreach ($errors as $err) { echo '<div>' . htmlspecialchars((string)$err) . '</div>'; } ?></div><?php endif; ?>
            <form method="post" autocomplete="off">
                <div class="form-group"><label>Booking Reference</label><input type="text" name="booking_reference" value="<?php echo htmlspecialchars($bookingReference); ?>" required></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?php echo htmlspecialchars($phoneInput); ?>" required></div>
                <button type="submit" class="btn-main">Continue</button>
            </form>
        </div>
    </section>
</main>
<style>
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html,body{font-family:'Marcellus',serif!important}.event-payment-main{min-height:100vh;padding:1.5rem 0 5rem}.event-payment-wrap{max-width:640px;margin:0 auto;padding:0 14px}.back-link{display:inline-block;color:#800000;text-decoration:none;font-weight:700;margin-bottom:10px}.card{background:#fff;border:1px solid #ecd3d3;border-radius:14px;box-shadow:0 4px 14px rgba(128,0,0,.08);padding:14px;margin-bottom:14px}h1{margin:0 0 10px;color:#800000}.form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:10px}label{color:#800000;font-weight:700}.btn-main{display:inline-block;border:none;border-radius:8px;background:#800000;color:#fff;font-weight:700;padding:9px 12px;cursor:pointer}.small{color:#666}.notice.err{background:#ffeaea;color:#b00020;padding:10px;border-radius:8px;margin-bottom:10px}
</style>
<?php require_once 'footer.php'; ?>
