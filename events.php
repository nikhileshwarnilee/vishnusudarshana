<?php
$pageTitle = 'Events | Vishnusudarshana';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/event_module.php';
require_once 'header.php';

vs_event_ensure_tables($pdo);

$sql = "SELECT
    e.*,
    COUNT(CASE WHEN p.status = 'Active' THEN p.id END) AS active_packages,
    MIN(CASE WHEN p.status = 'Active' THEN p.price END) AS min_price
FROM events e
LEFT JOIN event_packages p ON p.event_id = e.id
WHERE e.status = 'Active'
GROUP BY e.id
ORDER BY e.event_date ASC, e.id DESC";
$events = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$countdownScriptFile = __DIR__ . '/assets/js/event-registration-countdown.js';
$countdownScriptSrc = 'assets/js/event-registration-countdown.js' . (is_file($countdownScriptFile) ? ('?v=' . filemtime($countdownScriptFile)) : '');
?>
<main class="events-main" style="background-color:var(--cream-bg);">
    <section class="events-wrap">
        <h1 class="events-title">Spiritual Events</h1>
        <p class="events-subtitle">Explore upcoming poojas, yatras, pilgrimages, workshops and spiritual programs.</p>

        <div class="events-grid">
            <?php if (empty($events)): ?>
                <div class="event-empty">No active events available right now. Please check again soon.</div>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <?php
                    $isOpen = vs_event_is_registration_open($event);
                    $registrationWindowLabel = vs_event_format_registration_window($event);
                    $registrationDeadlineLabel = vs_event_format_registration_deadline($event);
                    $registrationDeadlineIso = vs_event_get_registration_deadline_iso($event);
                    ?>
                    <article class="event-card">
                        <a href="event-detail.php?slug=<?php echo urlencode((string)$event['slug']); ?>" class="event-card-link">
                            <div class="event-image-wrap">
                                <?php if (!empty($event['image'])): ?>
                                    <img src="<?php echo htmlspecialchars((string)$event['image']); ?>" alt="<?php echo htmlspecialchars((string)$event['title']); ?>" class="event-image">
                                <?php else: ?>
                                    <div class="event-image event-image-placeholder">Event</div>
                                <?php endif; ?>
                            </div>
                            <div class="event-content">
                                <h2><?php echo htmlspecialchars((string)$event['title']); ?></h2>
                                <div class="event-meta">
                                    <span><?php echo htmlspecialchars((string)$event['location']); ?></span>
                                </div>
                                <?php
                                $desc = trim(strip_tags((string)($event['short_description'] ?? '')));
                                if (strlen($desc) > 160) {
                                    $desc = substr($desc, 0, 157) . '...';
                                }
                                ?>
                                <p><?php echo htmlspecialchars($desc); ?></p>
                                <div
                                    class="registration-countdown compact<?php echo $isOpen ? '' : ' is-closed'; ?>"
                                    <?php if ($isOpen && $registrationDeadlineIso !== ''): ?>
                                        data-registration-countdown
                                        data-deadline="<?php echo htmlspecialchars($registrationDeadlineIso); ?>"
                                        data-live-note="<?php echo htmlspecialchars('Register before ' . $registrationDeadlineLabel); ?>"
                                    <?php else: ?>
                                        data-registration-countdown
                                        data-force-closed="1"
                                    <?php endif; ?>
                                    data-closed-note="<?php echo htmlspecialchars($registrationWindowLabel !== '' ? ('Closed on ' . $registrationDeadlineLabel) : 'Registration is currently closed for this event.'); ?>"
                                >
                                    <span class="countdown-copy">Time Left To Register</span>
                                    <span class="countdown-display">
                                        <span class="countdown-value" data-countdown-value><?php echo $isOpen ? '...' : 'Closed'; ?></span>
                                        <span class="countdown-unit" data-countdown-unit><?php echo $isOpen ? 'Loading' : 'Registration Ended'; ?></span>
                                    </span>
                                    <span class="countdown-note" data-countdown-note>
                                        <?php echo htmlspecialchars($isOpen && $registrationDeadlineLabel !== '' ? ('Register before ' . $registrationDeadlineLabel) : ($registrationDeadlineLabel !== '' ? ('Closed on ' . $registrationDeadlineLabel) : 'Registration is currently closed for this event.')); ?>
                                    </span>
                                </div>
                                <div class="event-footer">
                                    <span class="event-price">
                                        Packages available
                                    </span>
                                    <span class="event-status <?php echo $isOpen ? 'open' : 'closed'; ?>">
                                        <?php echo $isOpen ? 'Registration Open' : 'Registration Closed'; ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<style>
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html, body { font-family: 'Marcellus', serif !important; }
.events-main { min-height: 100vh; padding: 1.5rem 0 5rem; }
.events-wrap { max-width: 1120px; margin: 0 auto; padding: 0 14px; }
.events-title { text-align: center; color: #800000; margin: 0 0 8px; font-size: 2rem; }
.events-subtitle { text-align: center; color: #5b4b4b; margin: 0 0 18px; }
.events-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
.event-card { background: #fff; border: 1px solid #ecd3d3; border-radius: 18px; box-shadow: 0 10px 24px rgba(128, 0, 0, 0.1); overflow: hidden; transition: transform .25s ease, box-shadow .25s ease; }
.event-card:hover { transform: translateY(-4px); box-shadow: 0 18px 34px rgba(128, 0, 0, 0.16); }
.event-card-link { color: inherit; text-decoration: none; display: block; height: 100%; }
.event-image-wrap { background: #f9efef; height: 180px; overflow: hidden; }
.event-image { width: 100%; height: 100%; object-fit: cover; display: block; }
.event-image-placeholder { display:flex; align-items:center; justify-content:center; color:#800000; font-weight:700; }
.event-content { padding: 12px; }
.event-content h2 { margin: 0 0 6px; color: #800000; font-size: 1.18rem; }
.event-meta { display: flex; flex-direction: column; gap: 2px; color: #5f5f5f; font-size: 0.9rem; margin-bottom: 8px; }
.event-content p { margin: 0 0 10px; color: #444; line-height: 1.45; font-size: 0.94rem; }
.event-footer { display: flex; flex-direction: column; gap: 7px; }
.event-price { color: #1a8917; font-weight: 700; font-size: 0.93rem; }
.event-status { display: inline-block; width: fit-content; border-radius: 12px; padding: 4px 10px; font-weight: 700; font-size: 0.8rem; }
.event-status.open { background: #e5ffe5; color: #1a8917; }
.event-status.closed { background: #ffeaea; color: #b00020; }
.event-empty { background: #fff; border-radius: 12px; padding: 14px; text-align: center; color: #666; border: 1px dashed #e0bebe; }
.registration-countdown { position: relative; display: flex; flex-direction: column; gap: 6px; margin: 0 0 12px; padding: 14px 15px; border-radius: 18px; overflow: hidden; color: #fff9f1; background: linear-gradient(135deg, #7e0000 0%, #d44b1f 52%, #ffb347 100%); box-shadow: 0 16px 30px rgba(126, 0, 0, 0.2); }
.registration-countdown::before { content: ''; position: absolute; right: -28px; top: -34px; width: 110px; height: 110px; border-radius: 50%; background: rgba(255, 255, 255, 0.16); }
.registration-countdown::after { content: ''; position: absolute; left: -14px; bottom: -34px; width: 80px; height: 80px; border-radius: 50%; background: rgba(255, 236, 199, 0.18); }
.registration-countdown > * { position: relative; z-index: 1; }
.registration-countdown.compact { padding: 13px 14px; }
.countdown-copy { font-size: 0.72rem; letter-spacing: 0.14em; text-transform: uppercase; font-weight: 700; opacity: 0.92; }
.countdown-display { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
.countdown-value { font-size: clamp(1.7rem, 5vw, 2.35rem); line-height: 1; font-weight: 700; color: #fff; text-shadow: 0 10px 18px rgba(95, 15, 0, 0.28); }
.countdown-unit { font-size: 0.86rem; letter-spacing: 0.1em; text-transform: uppercase; font-weight: 700; padding-bottom: 4px; }
.countdown-note { font-size: 0.84rem; line-height: 1.4; color: rgba(255, 248, 238, 0.95); }
.registration-countdown.is-closed { background: linear-gradient(135deg, #5b6474 0%, #2c394d 100%); box-shadow: 0 14px 28px rgba(35, 48, 71, 0.18); }
@media (max-width: 680px) { .registration-countdown { border-radius: 16px; } .countdown-value { font-size: 1.95rem; } }
</style>

<script src="<?php echo htmlspecialchars($countdownScriptSrc); ?>"></script>
<?php require_once 'footer.php'; ?>
