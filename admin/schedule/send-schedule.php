<?php
// send-schedule.php
session_start();
if (!isset($_SESSION['user_id'])) {
	header('Location: ../login.php');
	exit;
}
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/send_whatsapp.php';

$waMsgStatus = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['user_ids']) && !empty($_POST['wa_message'])) {
	$userIds = array_map('intval', $_POST['user_ids']);
	$waMsg = trim($_POST['wa_message']);
	if ($userIds && $waMsg) {
		// Fetch selected users' names and mobiles
		$in  = str_repeat('?,', count($userIds) - 1) . '?';
		$stmt = $pdo->prepare("SELECT name, mobile FROM users WHERE id IN ($in)");
		$stmt->execute($userIds);
		$users = $stmt->fetchAll();
		$successCount = 0;
		$failCount = 0;
		foreach ($users as $user) {
			$result = sendWhatsAppMessage(
				$user['mobile'],
				'APPOINTMENT_MESSAGE',
				[
					'name' => $user['name'],
					'message' => $waMsg
				]
			);
			if (!empty($result['success'])) {
				$successCount++;
			} else {
				$failCount++;
			}
		}
		$waMsgStatus = "<span style='color:green;'>Message sent to $successCount user(s).</span>";
		if ($failCount) {
			$waMsgStatus .= " <span style='color:red;'>Failed for $failCount user(s).</span>";
		}
	} else {
		$waMsgStatus = "<span style='color:red;'>Please select users and enter a message.</span>";
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Send Schedule</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="../../assets/css/style.css">
	<link rel="stylesheet" href="../includes/responsive-tables.css">
	<style>
	body { margin: 0; background: #f7f7fa; font-family: 'Segoe UI', Arial, sans-serif; }
	.schedule-container { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(128,0,0,0.07); padding:32px 24px; max-width:1000px; margin:32px auto; }
	.schedule-container h1 { color:#800000; font-size:1.5em; margin-bottom:18px; }
	</style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/top-menu.php'; ?>
<div class="schedule-container">
	<h1>Send Schedule</h1>
	<form method="post" id="sendScheduleForm">
		<div style="margin-bottom:18px; font-weight:600; color:#333;">Select Users:</div>
		<div style="display:flex; flex-wrap:wrap; gap:18px 24px; margin-bottom:24px;">
		<?php
		// Fetch all users
		$users = $pdo->query('SELECT id, name FROM users ORDER BY name ASC')->fetchAll();
		foreach ($users as $user): ?>
			<label style="display:flex;align-items:center;gap:6px;font-weight:500;">
				<input type="checkbox" name="user_ids[]" value="<?= $user['id'] ?>">
				<?= htmlspecialchars($user['name']) ?>
			</label>
		<?php endforeach; ?>
		</div>
		<div style="margin-bottom:18px; font-weight:600; color:#333;">Message to send (WhatsApp - Admin Notes):</div>
		<textarea name="wa_message" rows="3" style="width:100%;max-width:600px;padding:10px;border-radius:6px;border:1px solid #ccc;font-size:1em;resize:vertical;margin-bottom:24px;" placeholder="Enter your message here..."></textarea>
		<button type="submit" class="btn-main">Send via WhatsApp</button>
		<div id="waMsgStatus" style="margin-top:16px;font-weight:600;">
			<?php if (!empty($waMsgStatus)) echo $waMsgStatus; ?>
		</div>
	</form>
	<script>
	document.getElementById('sendScheduleForm').addEventListener('submit', function(e) {
		var checkboxes = document.querySelectorAll('input[name="user_ids[]"]:checked');
		var msg = document.querySelector('textarea[name="wa_message"]').value.trim();
		var statusDiv = document.getElementById('waMsgStatus');
		if (checkboxes.length === 0 || msg === '') {
			e.preventDefault();
			statusDiv.innerHTML = '<span style="color:red;">Please select at least one user and enter a message.</span>';
			return false;
		}
		statusDiv.innerHTML = '';
	});
	</script>
	</form>
</div>
</body>
</html>
