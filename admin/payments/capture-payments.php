<?php
// capture-payments.php
session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: ../login.php');
	exit;
}

require_once __DIR__ . '/../includes/top-menu.php';
require_once __DIR__ . '/../../config/db.php';

// Razorpay manual loader (expects src/ files in vendor/razorpay/razorpay/)
if (file_exists(__DIR__ . '/../../vendor/razorpay/razorpay/Razorpay.php')) {
	require_once __DIR__ . '/../../vendor/razorpay/razorpay/Razorpay.php';
	// Use: use Razorpay\Api\Api;
}

// Handle AJAX capture request
if (isset($_POST['action']) && $_POST['action'] === 'capture_payment' && isset($_POST['payment_id']) && isset($_POST['amount'])) {
	$response = ['success' => false, 'message' => 'Unknown error'];
	$paymentId = $_POST['payment_id'];
	$amount = (int)$_POST['amount'];
	// TODO: Set your Razorpay API key/secret here
	$api_key = 'YOUR_KEY_HERE';
	$api_secret = 'YOUR_SECRET_HERE';
	try {
		if (!class_exists('Razorpay\\Api\\Api')) throw new Exception('Razorpay SDK not found');
		$api = new Razorpay\Api\Api($api_key, $api_secret);
		$payment = $api->payment->fetch($paymentId);
		$capture = $payment->capture(['amount' => $amount, 'currency' => 'INR']);
		// Update DB status
		$stmt = $pdo->prepare("UPDATE service_requests SET payment_status = 'Captured' WHERE payment_id = ?");
		$stmt->execute([$paymentId]);
		$response = ['success' => true, 'message' => 'Payment captured successfully'];
	} catch (Exception $e) {
		$response = ['success' => false, 'message' => $e->getMessage()];
	}
	header('Content-Type: application/json');
	echo json_encode($response);
	exit;
}

// Fetch all online payments from service_requests table
$sql = "SELECT id, payment_id, customer_name, selected_products, total_amount, payment_status, created_at, payment_date FROM service_requests WHERE payment_id IS NOT NULL AND payment_id != '' AND payment_status = 'Paid' ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Capture Payments</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="../../assets/css/style.css">
	<style>
		.capture-container { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(128,0,0,0.07); padding:32px 24px; max-width:1100px; margin:32px auto; }
		table { width:100%; border-collapse:collapse; margin-top:10px; }
		th, td { padding:12px 10px; border-bottom:1px solid #eee; text-align:left; }
		th { background:#faf6f6; color:#800000; font-weight:700; }
		tr:last-child td { border-bottom:none; }
		.capture-btn { padding:7px 16px; border:none; border-radius:4px; font-weight:600; cursor:pointer; background:#007bff; color:#fff; }
	</style>
</head>
<body>
<div class="capture-container">
	<h1 style="margin-bottom:18px; color:#800000; font-size:1.5em;">Collected Online Payments</h1>
	<table>
		<tr>
			<th>Payment ID</th>
			<th>Customer Name</th>
			<th>Product/Service(s)</th>
			<th>Amount</th>
			<th>Status</th>
			<th>Date</th>
			<th>Action</th>
		</tr>
		<?php if (empty($payments)): ?>
			<tr><td colspan="7" style="text-align:center; color:#888;">No online payments found.</td></tr>
		<?php else: foreach ($payments as $row): ?>
			<tr>
				<td><?= htmlspecialchars($row['payment_id']) ?></td>
				<td><?= htmlspecialchars($row['customer_name']) ?></td>
				<td>
					<?php 
					// selected_products is a JSON array of objects with 'name'
					$products = [];
					if (!empty($row['selected_products'])) {
						$items = json_decode($row['selected_products'], true);
						if (is_array($items)) {
							foreach ($items as $item) {
								if (isset($item['name'])) $products[] = $item['name'];
							}
						}
					}
					echo htmlspecialchars(implode(', ', $products) ?: '-');
					?>
				</td>
				<td>₹<?= number_format($row['total_amount'],2) ?></td>
				<td><?= htmlspecialchars($row['payment_status']) ?></td>
				<td><?= htmlspecialchars($row['payment_date'] ?? $row['created_at']) ?></td>
				<td>
					<button class="capture-btn" data-payment-id="<?= htmlspecialchars($row['payment_id']) ?>" data-amount="<?= (int)($row['total_amount']*100) ?>" <?= ($row['payment_status']==='Captured'?'disabled':'') ?>>
						<?= ($row['payment_status']==='Captured'?'Captured':'Capture Payment') ?>
					</button>
				</td>
			</tr>
		<?php endforeach; endif; ?>
	</table>
</div>

<script>
// AJAX capture payment
document.querySelectorAll('.capture-btn').forEach(function(btn) {
	btn.addEventListener('click', function() {
		if (btn.disabled || btn.textContent.trim() === 'Captured') return;
		if (!confirm('Are you sure you want to capture this payment?')) return;
		btn.disabled = true;
		btn.textContent = 'Capturing...';
		fetch('', {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded'},
			body: 'action=capture_payment&payment_id=' + encodeURIComponent(btn.dataset.paymentId) + '&amount=' + encodeURIComponent(btn.dataset.amount)
		})
		.then(r => r.json())
		.then(data => {
			if (data.success) {
				btn.textContent = 'Captured';
				btn.style.background = '#28a745';
			} else {
				btn.textContent = 'Capture Payment';
				btn.disabled = false;
				alert('Error: ' + data.message);
			}
		})
		.catch(() => {
			btn.textContent = 'Capture Payment';
			btn.disabled = false;
			alert('Network error.');
		});
	});
});
</script>
</body>
</html>
