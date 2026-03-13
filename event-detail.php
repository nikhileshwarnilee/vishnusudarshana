<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/event_module.php';

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    header('Location: events.php');
    exit;
}

vs_event_ensure_tables($pdo);

$stmt = $pdo->prepare('SELECT * FROM events WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    $pageTitle = 'Event Not Found';
    require_once 'header.php';
    echo '<main class="main-content" style="background-color:var(--cream-bg);"><h2>Event not found.</h2><a href="events.php">&larr; Back to Events</a></main>';
    require_once 'footer.php';
    exit;
}

$eventType = vs_event_normalize_event_type((string)($event['event_type'] ?? 'single_day'));
$selectedDateLabel = vs_event_get_event_date_display($pdo, (int)$event['id'], (string)$event['event_date'], $eventType);
$packages = vs_event_fetch_packages_with_seats($pdo, (int)$event['id'], true, 0);
$registrationOpen = vs_event_is_registration_open($event);
$registrationWindowLabel = vs_event_format_registration_window($event);
$registrationDeadlineLabel = vs_event_format_registration_deadline($event);
$registrationDeadlineIso = vs_event_get_registration_deadline_iso($event);
$shortDescription = trim((string)($event['short_description'] ?? ''));
$longDescription = trim((string)($event['long_description'] ?? ''));
$youtubeEmbedUrl = vs_event_get_youtube_embed_url((string)($event['youtube_video_url'] ?? ''));
$countdownScriptFile = __DIR__ . '/assets/js/event-registration-countdown.js';
$countdownScriptSrc = 'assets/js/event-registration-countdown.js' . (is_file($countdownScriptFile) ? ('?v=' . filemtime($countdownScriptFile)) : '');

$waitlistCounts = [];
try {
    $waitlistCountStmt = $pdo->prepare("SELECT package_id, COUNT(*) AS cnt
        FROM (
            SELECT package_id
            FROM event_registrations
            WHERE event_id = ?
              AND (payment_status = 'Waitlisted' OR verification_status = 'Waitlisted')
            UNION ALL
            SELECT package_id
            FROM event_waitlist
            WHERE event_id = ?
        ) w
        GROUP BY package_id");
    $waitlistCountStmt->execute([(int)$event['id'], (int)$event['id']]);
    foreach ($waitlistCountStmt->fetchAll(PDO::FETCH_ASSOC) as $waitRow) {
        $pkgId = (int)($waitRow['package_id'] ?? 0);
        if ($pkgId > 0) {
            $waitlistCounts[$pkgId] = (int)($waitRow['cnt'] ?? 0);
        }
    }
} catch (Throwable $e) {
    $waitlistCounts = [];
}

$pageTitle = $event['title'] . ' | Event Details';
require_once 'header.php';
?>
<main class="event-detail-main" style="background-color:var(--cream-bg);">
    <section class="event-detail-wrap">
        <a href="events.php" class="back-link">&larr; Back to Events</a>
        <div class="event-hero">
            <div class="hero-image-wrap">
                <?php if (!empty($event['image'])): ?>
                    <img src="<?php echo htmlspecialchars((string)$event['image']); ?>" class="hero-image" alt="<?php echo htmlspecialchars((string)$event['title']); ?>">
                <?php else: ?>
                    <div class="hero-image hero-placeholder">Event</div>
                <?php endif; ?>
            </div>
            <div class="hero-content">
                <h1><?php echo htmlspecialchars((string)$event['title']); ?></h1>
                <div class="hero-meta">
                    <span><strong>Date:</strong> <?php echo htmlspecialchars($selectedDateLabel); ?></span>
                    <span><strong>Location:</strong> <?php echo htmlspecialchars((string)$event['location']); ?></span>
                    <span><strong>Registration:</strong> <?php echo htmlspecialchars($registrationWindowLabel !== '' ? $registrationWindowLabel : ((string)$event['registration_start'] . ' to ' . (string)$event['registration_end'])); ?></span>
                </div>
                <?php if ($registrationOpen): ?>
                    <a class="hero-status open hero-status-link" href="#available-packages">Click to Register</a>
                <?php else: ?>
                    <div class="hero-status closed">Registration Closed</div>
                <?php endif; ?>
                <div
                    class="registration-countdown featured<?php echo $registrationOpen ? '' : ' is-closed'; ?>"
                    <?php if ($registrationOpen && $registrationDeadlineIso !== ''): ?>
                        data-registration-countdown
                        data-deadline="<?php echo htmlspecialchars($registrationDeadlineIso); ?>"
                        data-live-note="<?php echo htmlspecialchars($registrationDeadlineLabel !== '' ? ('Register before ' . $registrationDeadlineLabel) : 'Registration is live right now.'); ?>"
                    <?php else: ?>
                        data-registration-countdown
                        data-force-closed="1"
                    <?php endif; ?>
                    data-closed-note="<?php echo htmlspecialchars($registrationDeadlineLabel !== '' ? ('Closed on ' . $registrationDeadlineLabel) : 'Registration is currently closed for this event.'); ?>"
                >
                    <span class="countdown-copy">Time Left To Register</span>
                    <span class="countdown-display">
                        <span class="countdown-value" data-countdown-value><?php echo $registrationOpen ? '...' : 'Closed'; ?></span>
                        <span class="countdown-unit" data-countdown-unit><?php echo $registrationOpen ? 'Loading' : 'Registration Ended'; ?></span>
                    </span>
                    <span class="countdown-note" data-countdown-note>
                        <?php echo htmlspecialchars($registrationOpen && $registrationDeadlineLabel !== '' ? ('Register before ' . $registrationDeadlineLabel) : ($registrationDeadlineLabel !== '' ? ('Closed on ' . $registrationDeadlineLabel) : 'Registration is currently closed for this event.')); ?>
                    </span>
                </div>
                <?php if ($shortDescription !== ''): ?><p class="hero-description"><?php echo nl2br(htmlspecialchars($shortDescription)); ?></p><?php endif; ?>
            </div>
        </div>

        <?php if ($youtubeEmbedUrl !== ''): ?>
            <div class="packages-card">
                <h2>Event Video</h2>
                <div class="video-wrap"><iframe src="<?php echo htmlspecialchars($youtubeEmbedUrl); ?>" title="Event Video" frameborder="0" allowfullscreen></iframe></div>
            </div>
        <?php endif; ?>

        <?php if ($longDescription !== ''): ?>
            <div class="packages-card">
                <h2>About This Event</h2>
                <div class="long-description"><?php echo $longDescription; ?></div>
            </div>
        <?php endif; ?>

        <div class="packages-card" id="available-packages">
            <h2>Available Packages</h2>
            <?php if (empty($packages)): ?>
                <p>No active packages available for this event.</p>
            <?php else: ?>
                <div class="packages-grid">
                    <?php foreach ($packages as $pkg): ?>
                        <?php
                        $seatsLeft = $pkg['seats_left'];
                        $isFull = (bool)$pkg['is_full'];
                        $canRegister = $registrationOpen && !$isFull;
                        $packageId = (int)($pkg['id'] ?? 0);
                        $hasWaitlist = !empty($pkg['has_waitlist']);
                        if (!$hasWaitlist && $packageId > 0 && !empty($waitlistCounts)) {
                            $hasWaitlist = !empty($waitlistCounts[$packageId]);
                        }
                        $priceTotal = (float)($pkg['price_total'] ?? $pkg['price'] ?? 0);
                        $isPaidPackage = vs_event_is_package_paid($pkg);
                        $registerUrl = 'event-register.php?slug=' . urlencode((string)$event['slug']) . '&package=' . (int)$pkg['id'];
                        $waitlistUrl = 'event-waitlist.php?slug=' . urlencode((string)$event['slug']) . '&package=' . (int)$pkg['id'];
                        ?>
                        <article class="package-item">
                            <h3><?php echo htmlspecialchars((string)$pkg['package_name']); ?></h3>
                            <p class="pkg-price"><?php echo $isPaidPackage ? ('Rs. ' . number_format($priceTotal, 0, '.', '')) : 'Free'; ?></p>
                            <p class="pkg-desc"><?php echo nl2br(htmlspecialchars((string)$pkg['description'])); ?></p>
                            <p class="pkg-seats"><?php echo ($seatsLeft === null) ? 'Seats: Unlimited' : ('Seats Available: ' . (int)$seatsLeft); ?></p>
                            <?php if ($canRegister && !$hasWaitlist): ?>
                                <a class="register-btn" href="<?php echo htmlspecialchars($registerUrl); ?>">Register</a>
                            <?php elseif ($isFull || $hasWaitlist): ?>
                                <a class="register-btn waitlist-btn" href="<?php echo htmlspecialchars($waitlistUrl); ?>">Join Waitlist</a>
                            <?php else: ?>
                                <span class="register-btn disabled">Registration Closed</span>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<style>
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html,body{font-family:'Marcellus',serif!important;scroll-behavior:smooth}.event-detail-main{padding:1.4rem 0 5rem;min-height:100vh}.event-detail-wrap{max-width:1100px;margin:0 auto;padding:0 14px}.back-link{display:inline-block;margin-bottom:12px;color:#800000;text-decoration:none;font-weight:700}.event-hero{display:grid;grid-template-columns:1fr 1.3fr;gap:16px;background:#fff;border:1px solid #ecd3d3;border-radius:18px;overflow:hidden;box-shadow:0 10px 26px rgba(128,0,0,.1)}.hero-image-wrap{min-height:260px;background:#f9efef}.hero-image{width:100%;height:100%;object-fit:cover;display:block}.hero-placeholder{display:flex;align-items:center;justify-content:center;color:#800000;font-weight:700}.hero-content{padding:18px}.hero-content h1{margin:0 0 8px;color:#800000}.hero-meta{display:flex;flex-direction:column;gap:5px;color:#4d4d4d;font-size:.93rem;margin-bottom:8px}.hero-status{display:inline-block;padding:4px 10px;border-radius:12px;font-size:.82rem;font-weight:700;margin-bottom:10px}.hero-status.open{background:#e5ffe5;color:#1a8917}.hero-status.closed{background:#ffeaea;color:#b00020}.hero-status-link{text-decoration:none;cursor:pointer}.hero-description{margin:0;color:#333;line-height:1.65}.date-picker-form{display:flex;flex-direction:column;gap:5px;margin-bottom:10px}.date-picker-form select{border:1px solid #e0bebe;border-radius:8px;padding:7px 9px}.packages-card{margin-top:16px;background:#fff;border:1px solid #ecd3d3;border-radius:16px;padding:16px;box-shadow:0 8px 20px rgba(128,0,0,.08)}.packages-card h2{margin:0 0 10px;color:#800000}.video-wrap{position:relative;padding-top:56.25%;border-radius:10px;overflow:hidden}.video-wrap iframe{position:absolute;inset:0;width:100%;height:100%}.long-description{line-height:1.6;color:#333}.packages-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}.package-item{border:1px solid #f1d6d6;border-radius:12px;padding:12px;background:#fffaf8}.package-item h3{margin:0 0 5px;color:#800000}.pkg-price{margin:0 0 6px;color:#1a8917;font-weight:700}.pkg-desc{margin:0 0 6px;color:#4a4a4a;min-height:52px}.pkg-seats,.pkg-paymode{margin:0 0 10px;color:#5b4b4b;font-size:.9rem;font-weight:600}.register-btn{display:inline-block;text-decoration:none;background:#800000;color:#fff;border-radius:8px;padding:8px 12px;font-weight:700}.waitlist-btn{background:#0b7285}.register-btn.disabled{background:#999;cursor:not-allowed}#available-packages{scroll-margin-top:90px}.registration-countdown{position:relative;display:flex;flex-direction:column;gap:7px;margin:6px 0 14px;padding:18px 18px 16px;border-radius:22px;overflow:hidden;color:#fffaf1;background:linear-gradient(135deg,#7e0000 0%,#cf441a 48%,#ffb347 100%);box-shadow:0 18px 36px rgba(126,0,0,.22)}.registration-countdown::before{content:'';position:absolute;right:-32px;top:-40px;width:150px;height:150px;border-radius:50%;background:rgba(255,255,255,.16)}.registration-countdown::after{content:'';position:absolute;left:-20px;bottom:-40px;width:110px;height:110px;border-radius:50%;background:rgba(255,236,199,.18)}.registration-countdown>*{position:relative;z-index:1}.registration-countdown.featured .countdown-copy{font-size:.8rem;letter-spacing:.16em;text-transform:uppercase;font-weight:700;opacity:.92}.registration-countdown.featured .countdown-display{display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap}.registration-countdown.featured .countdown-value{font-size:clamp(2.2rem,5vw,4rem);line-height:1;font-weight:700;color:#fff;text-shadow:0 12px 22px rgba(95,15,0,.28)}.registration-countdown.featured .countdown-unit{font-size:1rem;letter-spacing:.12em;text-transform:uppercase;font-weight:700;padding-bottom:8px}.registration-countdown.featured .countdown-note{font-size:.92rem;line-height:1.45;color:rgba(255,248,238,.96)}.registration-countdown.is-closed{background:linear-gradient(135deg,#5b6474 0%,#2c394d 100%);box-shadow:0 14px 28px rgba(35,48,71,.18)}@media (max-width:900px){.event-hero{grid-template-columns:1fr}.hero-image-wrap{min-height:210px}.registration-countdown.featured{border-radius:18px;padding:16px 15px}.registration-countdown.featured .countdown-value{font-size:2.4rem}.registration-countdown.featured .countdown-unit{padding-bottom:4px}}
</style>
<script src="<?php echo htmlspecialchars($countdownScriptSrc); ?>"></script>
<?php require_once 'footer.php'; ?>
