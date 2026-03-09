<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/event_module.php';

$slug = trim((string)($_GET['slug'] ?? $_POST['slug'] ?? ''));
if ($slug === '') {
    header('Location: events.php');
    exit;
}

vs_event_ensure_tables($pdo);

$eventStmt = $pdo->prepare('SELECT * FROM events WHERE slug = ? LIMIT 1');
$eventStmt->execute([$slug]);
$event = $eventStmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    header('Location: events.php');
    exit;
}

$eventType = vs_event_normalize_event_type((string)($event['event_type'] ?? 'single_day'));
$eventDates = vs_event_get_event_dates_cached($pdo, (int)$event['id'], true);
$singleDefaultDateId = !empty($eventDates) ? (int)$eventDates[0]['id'] : 0;
$rangeDateLabel = vs_event_build_range_label($eventDates, (string)$event['event_date']);
$selectedDateId = (int)($_POST['event_date_id'] ?? 0);
if ($eventType === 'single_day') {
    $selectedDateId = $singleDefaultDateId;
} elseif ($eventType === 'date_range') {
    $selectedDateId = 0;
} elseif ($selectedDateId <= 0 && !empty($eventDates)) {
    $selectedDateId = (int)$eventDates[0]['id'];
} elseif ($eventType === 'multi_select_dates' && !empty($eventDates)) {
    $dateFound = false;
    foreach ($eventDates as $eventDateRow) {
        if ((int)$eventDateRow['id'] === $selectedDateId) {
            $dateFound = true;
            break;
        }
    }
    if (!$dateFound) {
        $selectedDateId = (int)$eventDates[0]['id'];
    }
}

$packageDateContextId = ($eventType === 'date_range') ? 0 : $selectedDateId;
$packages = vs_event_fetch_packages_with_seats($pdo, (int)$event['id'], true, $packageDateContextId);
$packageMap = [];
foreach ($packages as $package) {
    $packageMap[(int)$package['id']] = $package;
}

$selectedPackageId = (int)($_GET['package'] ?? $_POST['package_id'] ?? 0);
$errors = [];
$success = '';
$old = ['name' => '', 'phone' => '', 'persons' => '1', 'package_id' => $selectedPackageId > 0 ? (string)$selectedPackageId : '', 'event_date_id' => (string)$selectedDateId];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedPackageId = (int)($_POST['package_id'] ?? 0);
    $selectedDateId = (int)($_POST['event_date_id'] ?? 0);
    if ($eventType === 'single_day') {
        $selectedDateId = $singleDefaultDateId;
    } elseif ($eventType === 'date_range') {
        $selectedDateId = 0;
    }
    $packageDateContextId = ($eventType === 'date_range') ? 0 : $selectedDateId;
    $packages = vs_event_fetch_packages_with_seats($pdo, (int)$event['id'], true, $packageDateContextId);
    $packageMap = [];
    foreach ($packages as $package) {
        $packageMap[(int)$package['id']] = $package;
    }
    $name = trim((string)($_POST['name'] ?? ''));
    $phoneRaw = trim((string)($_POST['phone'] ?? ''));
    $phone = preg_replace('/[^0-9]/', '', $phoneRaw);
    if (strlen($phone) === 10) { $phone = '91' . $phone; }
    $persons = (int)($_POST['persons'] ?? 1);
    if ($persons <= 0) { $persons = 1; }

    $old['name'] = $name;
    $old['phone'] = $phoneRaw;
    $old['persons'] = (string)$persons;
    $old['package_id'] = (string)$selectedPackageId;
    $old['event_date_id'] = (string)$selectedDateId;

    if ($name === '') { $errors[] = 'Name is required.'; }
    if ($phone === '' || strlen($phone) < 10) { $errors[] = 'A valid phone number is required.'; }
    if (!isset($packageMap[$selectedPackageId])) { $errors[] = 'Please select a valid package.'; }
    if ($eventType === 'multi_select_dates' && !empty($eventDates)) {
        $validDate = false;
        foreach ($eventDates as $d) {
            if ((int)$d['id'] === $selectedDateId) { $validDate = true; break; }
        }
        if (!$validDate) { $errors[] = 'Please select a valid event date.'; }
    } elseif ($eventType === 'single_day' && $singleDefaultDateId > 0) {
        $selectedDateId = $singleDefaultDateId;
    }

    if (empty($errors)) {
        try {
            $insertStmt = $pdo->prepare("INSERT INTO event_waitlist (event_id, package_id, event_date_id, name, phone, persons) VALUES (?, ?, ?, ?, ?, ?)");
            $insertStmt->execute([(int)$event['id'], $selectedPackageId, $selectedDateId > 0 ? $selectedDateId : null, $name, $phone, $persons]);
            $pkg = $packageMap[$selectedPackageId];
            $success = 'Waitlist request submitted for ' . (string)$pkg['package_name'] . '. We will contact you when seats open.';
            $old = ['name' => '', 'phone' => '', 'persons' => '1', 'package_id' => (string)$selectedPackageId, 'event_date_id' => (string)$selectedDateId];
        } catch (Throwable $e) {
            $errors[] = (stripos((string)$e->getMessage(), 'uniq_event_waitlist_package_phone') !== false)
                ? 'This phone is already on waitlist for the selected package.'
                : 'Unable to join waitlist at the moment.';
            error_log('Event waitlist failed: ' . $e->getMessage());
        }
    }
}

$selectedDateText = (string)$event['event_date'];
if ($eventType === 'date_range') {
    $selectedDateText = $rangeDateLabel;
} elseif ($eventType === 'single_day' && !empty($eventDates)) {
    $selectedDateText = (string)$eventDates[0]['event_date'];
    $selectedDateId = (int)$eventDates[0]['id'];
} else {
    foreach ($eventDates as $d) {
        if ((int)$d['id'] === (int)$old['event_date_id']) {
            $selectedDateText = (string)$d['event_date'];
            break;
        }
    }
}

$pageTitle = $event['title'] . ' | Waitlist';
require_once 'header.php';
?>
<main class="event-waitlist-main" style="background-color:var(--cream-bg);">
    <section class="event-waitlist-wrap">
        <a href="event-detail.php?slug=<?php echo urlencode((string)$event['slug']); ?>" class="back-link">&larr; Back to Event</a>
        <div class="card">
            <h1>Join Waitlist</h1>
            <p class="small">Event: <?php echo htmlspecialchars((string)$event['title']); ?> | Date: <?php echo htmlspecialchars($selectedDateText); ?></p>
            <?php if ($success !== ''): ?><div class="notice ok"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
            <?php if (!empty($errors)): ?><div class="notice err"><?php foreach ($errors as $error) { echo '<div>' . htmlspecialchars((string)$error) . '</div>'; } ?></div><?php endif; ?>

            <form method="post" autocomplete="off">
                <input type="hidden" name="slug" value="<?php echo htmlspecialchars((string)$event['slug']); ?>">
                <div class="grid">
                    <?php if ($eventType === 'multi_select_dates' && !empty($eventDates)): ?>
                        <div class="form-group"><label>Event Date</label><select name="event_date_id" required><?php foreach ($eventDates as $d): ?><option value="<?php echo (int)$d['id']; ?>" <?php echo ((int)$old['event_date_id'] === (int)$d['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$d['event_date']); ?></option><?php endforeach; ?></select></div>
                    <?php elseif ($eventType === 'date_range'): ?>
                        <div class="form-group"><label>Event Date Range</label><select name="event_date_id"><option value="0" selected><?php echo htmlspecialchars($rangeDateLabel); ?></option></select></div>
                    <?php else: ?>
                        <div class="form-group"><label>Event Date</label><input type="text" value="<?php echo htmlspecialchars($selectedDateText); ?>" readonly><input type="hidden" name="event_date_id" value="<?php echo (int)$selectedDateId; ?>"></div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Package</label>
                        <select name="package_id" required>
                            <option value="">-- Select Package --</option>
                            <?php foreach ($packages as $pkg): ?>
                                <?php $value = (int)$pkg['id']; $isSelected = ((int)$old['package_id'] === $value); $label = (string)$pkg['package_name'] . ' - Rs. ' . number_format((float)($pkg['price_total'] ?? $pkg['price']), 0, '.', ''); ?>
                                <option value="<?php echo $value; ?>" <?php echo $isSelected ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Full Name</label><input type="text" name="name" value="<?php echo htmlspecialchars((string)$old['name']); ?>" required></div>
                    <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?php echo htmlspecialchars((string)$old['phone']); ?>" required></div>
                    <div class="form-group"><label>Persons</label><input type="number" name="persons" min="1" max="25" value="<?php echo htmlspecialchars((string)$old['persons']); ?>" required></div>
                </div>
                <button type="submit" class="submit-btn">Join Waitlist</button>
            </form>
        </div>
    </section>
</main>
<style>
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html,body{font-family:'Marcellus',serif!important}.event-waitlist-main{min-height:100vh;padding:1.5rem 0 5rem}.event-waitlist-wrap{max-width:860px;margin:0 auto;padding:0 14px}.back-link{display:inline-block;color:#800000;text-decoration:none;font-weight:700;margin-bottom:10px}.card{background:#fff;border:1px solid #ecd3d3;border-radius:14px;box-shadow:0 4px 14px rgba(128,0,0,.08);padding:14px}h1{margin:0 0 8px;color:#800000;font-size:1.6rem}.small{margin:4px 0;color:#555;font-size:.9rem}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:10px}.form-group{display:flex;flex-direction:column;gap:6px}label{color:#800000;font-weight:700;font-size:.92rem}input,select{width:100%;box-sizing:border-box;border:1px solid #e0bebe;border-radius:8px;padding:9px 10px;font-size:.94rem;background:#fff}.notice{margin:10px 0;padding:10px 12px;border-radius:8px;font-weight:600}.notice.ok{background:#e7f7ed;color:#1a8917}.notice.err{background:#ffeaea;color:#b00020}.submit-btn{margin-top:14px;width:100%;border:none;border-radius:8px;background:#0b7285;color:#fff;font-weight:700;font-size:1rem;padding:11px 12px;cursor:pointer}
</style>
<?php require_once 'footer.php'; ?>
