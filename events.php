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
                    <?php $isOpen = vs_event_is_registration_open($event); ?>
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
                                if ($desc === '') {
                                    $desc = trim(strip_tags((string)$event['description']));
                                }
                                if (strlen($desc) > 160) {
                                    $desc = substr($desc, 0, 157) . '...';
                                }
                                ?>
                                <p><?php echo htmlspecialchars($desc); ?></p>
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
.event-card { background: #fff; border: 1px solid #ecd3d3; border-radius: 14px; box-shadow: 0 4px 14px rgba(128, 0, 0, 0.08); overflow: hidden; }
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
</style>

<?php require_once 'footer.php'; ?>
