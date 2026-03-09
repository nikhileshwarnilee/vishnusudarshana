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
$shortDescription = trim((string)($event['short_description'] ?? ''));
if ($shortDescription === '') {
    $shortDescription = trim(strip_tags((string)($event['description'] ?? '')));
}
$longDescription = trim((string)($event['long_description'] ?? ''));
if ($longDescription === '') {
    $longDescription = (string)($event['description'] ?? '');
}
$youtubeEmbedUrl = vs_event_get_youtube_embed_url((string)($event['youtube_video_url'] ?? ''));

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
                    <span><strong>Registration:</strong> <?php echo htmlspecialchars((string)$event['registration_start']); ?> to <?php echo htmlspecialchars((string)$event['registration_end']); ?></span>
                </div>
                <div class="hero-status <?php echo $registrationOpen ? 'open' : 'closed'; ?>"><?php echo $registrationOpen ? 'Registration Open' : 'Registration Closed'; ?></div>
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

        <div class="packages-card">
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
                            <?php if ($canRegister): ?>
                                <a class="register-btn" href="<?php echo htmlspecialchars($registerUrl); ?>">Register</a>
                            <?php elseif ($isFull): ?>
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
html,body{font-family:'Marcellus',serif!important}.event-detail-main{padding:1.4rem 0 5rem;min-height:100vh}.event-detail-wrap{max-width:1100px;margin:0 auto;padding:0 14px}.back-link{display:inline-block;margin-bottom:12px;color:#800000;text-decoration:none;font-weight:700}.event-hero{display:grid;grid-template-columns:1fr 1.3fr;gap:16px;background:#fff;border:1px solid #ecd3d3;border-radius:14px;overflow:hidden;box-shadow:0 4px 14px rgba(128,0,0,.08)}.hero-image-wrap{min-height:260px;background:#f9efef}.hero-image{width:100%;height:100%;object-fit:cover;display:block}.hero-placeholder{display:flex;align-items:center;justify-content:center;color:#800000;font-weight:700}.hero-content{padding:14px}.hero-content h1{margin:0 0 8px;color:#800000}.hero-meta{display:flex;flex-direction:column;gap:5px;color:#4d4d4d;font-size:.93rem;margin-bottom:8px}.hero-status{display:inline-block;padding:4px 10px;border-radius:12px;font-size:.82rem;font-weight:700;margin-bottom:10px}.hero-status.open{background:#e5ffe5;color:#1a8917}.hero-status.closed{background:#ffeaea;color:#b00020}.hero-description{margin:0;color:#333;line-height:1.5}.date-picker-form{display:flex;flex-direction:column;gap:5px;margin-bottom:10px}.date-picker-form select{border:1px solid #e0bebe;border-radius:8px;padding:7px 9px}.packages-card{margin-top:16px;background:#fff;border:1px solid #ecd3d3;border-radius:14px;padding:14px;box-shadow:0 4px 14px rgba(128,0,0,.08)}.packages-card h2{margin:0 0 10px;color:#800000}.video-wrap{position:relative;padding-top:56.25%;border-radius:10px;overflow:hidden}.video-wrap iframe{position:absolute;inset:0;width:100%;height:100%}.long-description{line-height:1.6;color:#333}.packages-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}.package-item{border:1px solid #f1d6d6;border-radius:12px;padding:12px;background:#fffaf8}.package-item h3{margin:0 0 5px;color:#800000}.pkg-price{margin:0 0 6px;color:#1a8917;font-weight:700}.pkg-desc{margin:0 0 6px;color:#4a4a4a;min-height:52px}.pkg-seats,.pkg-paymode{margin:0 0 10px;color:#5b4b4b;font-size:.9rem;font-weight:600}.register-btn{display:inline-block;text-decoration:none;background:#800000;color:#fff;border-radius:8px;padding:8px 12px;font-weight:700}.waitlist-btn{background:#0b7285}.register-btn.disabled{background:#999;cursor:not-allowed}@media (max-width:900px){.event-hero{grid-template-columns:1fr}.hero-image-wrap{min-height:210px}}
</style>
<?php require_once 'footer.php'; ?>
