<?php
$dashboardPageTitle = isset($dashboardPageTitle) ? (string)$dashboardPageTitle : 'Dashboard';
$dashboardHeading = isset($dashboardHeading) ? (string)$dashboardHeading : 'Dashboard';
$dashboard = isset($dashboard) && is_array($dashboard) ? $dashboard : [];
$heroStats = isset($dashboard['hero_stats']) && is_array($dashboard['hero_stats']) ? $dashboard['hero_stats'] : [];
$quickLinks = isset($dashboard['quick_links']) && is_array($dashboard['quick_links']) ? $dashboard['quick_links'] : [];
$attentionCards = isset($dashboard['attention_cards']) && is_array($dashboard['attention_cards']) ? $dashboard['attention_cards'] : [];
$moduleBoards = isset($dashboard['module_boards']) && is_array($dashboard['module_boards']) ? $dashboard['module_boards'] : [];
$todayMetrics = isset($dashboard['today_metrics']) && is_array($dashboard['today_metrics']) ? $dashboard['today_metrics'] : [];
$focusNotes = isset($dashboard['focus_notes']) && is_array($dashboard['focus_notes']) ? $dashboard['focus_notes'] : [];
$timeline = isset($dashboard['timeline']) && is_array($dashboard['timeline']) ? $dashboard['timeline'] : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dashboardPageTitle); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --vs-bg: #f6f0e5;
            --vs-paper: #fffdf9;
            --vs-card: rgba(255, 251, 244, 0.9);
            --vs-ink: #2f2922;
            --vs-muted: #73685d;
            --vs-line: rgba(109, 30, 27, 0.12);
            --vs-maroon: #6d1e1b;
            --vs-saffron: #b96f1d;
            --vs-sky: #2b607d;
            --vs-sage: #5c7150;
            --vs-rose: #a64536;
            --vs-shadow: 0 22px 50px rgba(74, 41, 18, 0.08);
        }
        body.vs-dashboard-body,
        body.vs-dashboard-body * {
            font-family: "Trebuchet MS", "Segoe UI", sans-serif !important;
        }
        body.vs-dashboard-body {
            margin: 0;
            color: var(--vs-ink);
            background:
                radial-gradient(circle at top left, rgba(214, 170, 83, 0.24), transparent 32%),
                radial-gradient(circle at top right, rgba(109, 30, 27, 0.12), transparent 30%),
                linear-gradient(180deg, #f7f1e7 0%, #f3ede1 100%);
        }
        .vs-shell {
            max-width: 1480px;
            margin: 0 auto;
            padding: 24px 16px 48px;
        }
        .vs-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(300px, 0.9fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        .vs-hero-panel,
        .vs-panel,
        .vs-card {
            background: var(--vs-card);
            border: 1px solid var(--vs-line);
            border-radius: 24px;
            box-shadow: var(--vs-shadow);
            backdrop-filter: blur(14px);
        }
        .vs-hero-main {
            padding: 26px 28px 28px;
            background:
                linear-gradient(135deg, rgba(109, 30, 27, 0.96), rgba(185, 111, 29, 0.88)),
                var(--vs-card);
            color: #fff7ef;
            position: relative;
            overflow: hidden;
        }
        .vs-hero-main::after {
            content: "";
            position: absolute;
            inset: auto -60px -70px auto;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255, 240, 213, 0.12);
        }
        .vs-kicker {
            margin: 0 0 10px;
            font-size: 0.78rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            opacity: 0.82;
        }
        .vs-heading {
            margin: 0;
            font-family: "Palatino Linotype", "Book Antiqua", Georgia, serif !important;
            font-size: clamp(2rem, 4vw, 3rem);
            line-height: 1.02;
        }
        .vs-subtitle {
            max-width: 780px;
            margin: 14px 0 0;
            font-size: 1rem;
            line-height: 1.65;
            color: rgba(255, 247, 239, 0.92);
        }
        .vs-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 22px;
        }
        .vs-stat {
            padding: 14px 16px;
            border-radius: 18px;
            background: rgba(255, 251, 245, 0.12);
            border: 1px solid rgba(255, 247, 239, 0.16);
        }
        .vs-stat-value {
            display: block;
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1;
        }
        .vs-stat-label {
            display: block;
            margin-top: 6px;
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .vs-hero-side {
            padding: 22px 22px 20px;
        }
        .vs-side-block + .vs-side-block {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid var(--vs-line);
        }
        .vs-side-title {
            margin: 0 0 12px;
            color: var(--vs-maroon);
            font-size: 1.02rem;
        }
        .vs-chip-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .vs-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--vs-ink);
            background: #fff;
            border: 1px solid var(--vs-line);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .vs-chip:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(74, 41, 18, 0.12);
        }
        .vs-accent-maroon { color: var(--vs-maroon); }
        .vs-accent-saffron { color: var(--vs-saffron); }
        .vs-accent-sky { color: var(--vs-sky); }
        .vs-accent-sage { color: var(--vs-sage); }
        .vs-accent-rose { color: var(--vs-rose); }
        .vs-attention-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(235px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }
        .vs-card {
            padding: 18px 18px 16px;
            min-height: 172px;
            animation: vs-rise 0.45s ease both;
        }
        .vs-card-critical { border-color: rgba(166, 69, 54, 0.28); }
        .vs-card-warm { border-color: rgba(185, 111, 29, 0.26); }
        .vs-card-sky { border-color: rgba(43, 96, 125, 0.24); }
        .vs-card-sage { border-color: rgba(92, 113, 80, 0.24); }
        .vs-card-rose { border-color: rgba(166, 69, 54, 0.24); }
        .vs-card-calm { border-color: rgba(92, 113, 80, 0.18); }
        .vs-card-label {
            color: var(--vs-muted);
            font-size: 0.78rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .vs-card-count {
            margin: 12px 0 10px;
            font-size: 2.2rem;
            font-weight: 800;
            line-height: 1;
            color: var(--vs-maroon);
        }
        .vs-card-copy {
            margin: 0;
            min-height: 54px;
            color: var(--vs-muted);
            line-height: 1.55;
            font-size: 0.93rem;
        }
        .vs-card-link {
            display: inline-flex;
            margin-top: 14px;
            text-decoration: none;
            font-weight: 700;
            color: var(--vs-maroon);
        }
        .vs-main-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(290px, 0.82fr);
            gap: 18px;
        }
        .vs-panel {
            padding: 20px 20px 18px;
        }
        .vs-panel + .vs-panel {
            margin-top: 18px;
        }
        .vs-panel-title {
            margin: 0 0 14px;
            font-size: 1.15rem;
            color: var(--vs-maroon);
        }
        .vs-board-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 16px;
        }
        .vs-board {
            border-radius: 22px;
            padding: 18px;
            background: rgba(255, 252, 247, 0.92);
            border: 1px solid var(--vs-line);
        }
        .vs-board-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
        }
        .vs-board-head h3 {
            margin: 0;
            font-size: 1.12rem;
            color: var(--vs-maroon);
        }
        .vs-board-link {
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--vs-maroon);
        }
        .vs-board-desc {
            margin: 10px 0 0;
            color: var(--vs-muted);
            line-height: 1.55;
            font-size: 0.92rem;
        }
        .vs-board-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 16px;
        }
        .vs-mini {
            padding: 12px;
            border-radius: 16px;
            background: #fff;
            border: 1px solid var(--vs-line);
        }
        .vs-mini-value {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--vs-maroon);
        }
        .vs-mini-label {
            margin-top: 4px;
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--vs-muted);
        }
        .vs-mini-hint {
            margin-top: 5px;
            font-size: 0.83rem;
            color: var(--vs-muted);
        }
        .vs-list {
            margin: 16px 0 0;
        }
        .vs-list-item {
            display: flex;
            gap: 12px;
            justify-content: space-between;
            padding: 12px 0;
            text-decoration: none;
            border-top: 1px solid rgba(109, 30, 27, 0.08);
            color: inherit;
        }
        .vs-list-item:first-child {
            border-top: 0;
            padding-top: 0;
        }
        .vs-list-title {
            font-weight: 700;
        }
        .vs-list-meta,
        .vs-list-support,
        .vs-empty,
        .vs-footnote {
            color: var(--vs-muted);
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .vs-list-support { font-size: 0.85rem; }
        .vs-flag {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .vs-flag-critical { background: rgba(166, 69, 54, 0.12); color: var(--vs-rose); }
        .vs-flag-warm { background: rgba(185, 111, 29, 0.12); color: var(--vs-saffron); }
        .vs-flag-sky { background: rgba(43, 96, 125, 0.11); color: var(--vs-sky); }
        .vs-flag-sage { background: rgba(92, 113, 80, 0.11); color: var(--vs-sage); }
        .vs-board-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }
        .vs-note-item + .vs-note-item,
        .vs-timeline-item + .vs-timeline-item {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--vs-line);
        }
        .vs-note-link,
        .vs-timeline-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .vs-note-title,
        .vs-timeline-title {
            font-weight: 700;
            color: var(--vs-ink);
        }
        .vs-timeline-badge {
            display: inline-block;
            margin-bottom: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--vs-maroon);
            background: rgba(109, 30, 27, 0.08);
        }
        @keyframes vs-rise {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 1100px) {
            .vs-hero, .vs-main-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 700px) {
            .vs-shell { padding: 16px 12px 36px; }
            .vs-hero-main, .vs-hero-side, .vs-panel, .vs-card, .vs-board { padding: 18px; }
            .vs-board-metrics { grid-template-columns: 1fr; }
            .vs-card-count { font-size: 1.9rem; }
        }
    </style>
</head>
<body class="vs-dashboard-body">
<?php include __DIR__ . '/top-menu.php'; ?>
<div class="vs-shell">
    <section class="vs-hero">
        <div class="vs-hero-panel vs-hero-main">
            <p class="vs-kicker"><?php echo htmlspecialchars(($dashboard['today_label'] ?? '') . ' • ' . ($dashboard['time_label'] ?? '')); ?></p>
            <h1 class="vs-heading"><?php echo htmlspecialchars($dashboardHeading); ?></h1>
            <p class="vs-subtitle"><?php echo htmlspecialchars((string)($dashboard['intro_text'] ?? '')); ?></p>
            <?php if (!empty($heroStats)): ?>
                <div class="vs-stats">
                    <?php foreach ($heroStats as $stat): ?>
                        <div class="vs-stat">
                            <span class="vs-stat-value"><?php echo htmlspecialchars((string)($stat['value'] ?? '0')); ?></span>
                            <span class="vs-stat-label"><?php echo htmlspecialchars((string)($stat['label'] ?? '')); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <aside class="vs-hero-panel vs-hero-side">
            <div class="vs-side-block">
                <h2 class="vs-side-title">Quick Actions</h2>
                <div class="vs-chip-grid">
                    <?php foreach ($quickLinks as $link): ?>
                        <a class="vs-chip vs-accent-<?php echo htmlspecialchars((string)($link['accent'] ?? 'maroon')); ?>" href="<?php echo htmlspecialchars((string)($link['href'] ?? '#')); ?>">
                            <?php echo htmlspecialchars((string)($link['label'] ?? 'Open')); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="vs-side-block">
                <h2 class="vs-side-title">Access Scope</h2>
                <p class="vs-footnote"><?php echo htmlspecialchars((string)($dashboard['footer_note'] ?? '')); ?></p>
            </div>
        </aside>
    </section>

    <section class="vs-attention-grid">
        <?php foreach ($attentionCards as $card): ?>
            <article class="vs-card vs-card-<?php echo htmlspecialchars((string)($card['tone'] ?? 'warm')); ?>">
                <div class="vs-card-label"><?php echo htmlspecialchars((string)($card['label'] ?? '')); ?></div>
                <div class="vs-card-count"><?php echo htmlspecialchars((string)vs_dashboard_format_count((int)($card['count'] ?? 0))); ?></div>
                <p class="vs-card-copy"><?php echo htmlspecialchars((string)($card['detail'] ?? '')); ?></p>
                <a class="vs-card-link" href="<?php echo htmlspecialchars((string)($card['href'] ?? '#')); ?>">
                    <?php echo htmlspecialchars((string)($card['cta'] ?? 'Open')); ?>
                </a>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="vs-main-grid">
        <div>
            <div class="vs-panel">
                <h2 class="vs-panel-title">Operational Watchboards</h2>
                <div class="vs-board-grid">
                    <?php foreach ($moduleBoards as $board): ?>
                        <article class="vs-board">
                            <div class="vs-board-head">
                                <div>
                                    <h3><?php echo htmlspecialchars((string)($board['title'] ?? 'Board')); ?></h3>
                                </div>
                                <a class="vs-board-link" href="<?php echo htmlspecialchars((string)($board['primary_href'] ?? '#')); ?>">
                                    <?php echo htmlspecialchars((string)($board['primary_label'] ?? 'Open')); ?>
                                </a>
                            </div>
                            <p class="vs-board-desc"><?php echo htmlspecialchars((string)($board['description'] ?? '')); ?></p>

                            <?php if (!empty($board['metrics'])): ?>
                                <div class="vs-board-metrics">
                                    <?php foreach ($board['metrics'] as $metric): ?>
                                        <div class="vs-mini">
                                            <div class="vs-mini-value"><?php echo htmlspecialchars((string)($metric['value'] ?? '0')); ?></div>
                                            <div class="vs-mini-label"><?php echo htmlspecialchars((string)($metric['label'] ?? '')); ?></div>
                                            <?php if (!empty($metric['hint'])): ?><div class="vs-mini-hint"><?php echo htmlspecialchars((string)$metric['hint']); ?></div><?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="vs-list">
                                <?php if (!empty($board['items'])): ?>
                                    <?php foreach ($board['items'] as $item): ?>
                                        <a class="vs-list-item" href="<?php echo htmlspecialchars((string)($item['href'] ?? '#')); ?>">
                                            <div>
                                                <div class="vs-list-title"><?php echo htmlspecialchars((string)($item['title'] ?? 'Item')); ?></div>
                                                <div class="vs-list-meta"><?php echo htmlspecialchars((string)($item['meta'] ?? '')); ?></div>
                                                <?php if (!empty($item['support'])): ?><div class="vs-list-support"><?php echo htmlspecialchars((string)$item['support']); ?></div><?php endif; ?>
                                            </div>
                                            <span class="vs-flag vs-flag-<?php echo htmlspecialchars((string)($item['flag_tone'] ?? 'sage')); ?>">
                                                <?php echo htmlspecialchars((string)($item['flag'] ?? 'Open')); ?>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="vs-empty"><?php echo htmlspecialchars((string)($board['empty_text'] ?? 'No items.')); ?></div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($board['actions'])): ?>
                                <div class="vs-board-actions">
                                    <?php foreach ($board['actions'] as $action): ?>
                                        <a class="vs-chip" href="<?php echo htmlspecialchars((string)($action['href'] ?? '#')); ?>">
                                            <?php echo htmlspecialchars((string)($action['label'] ?? 'Open')); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <aside>
            <div class="vs-panel">
                <h2 class="vs-panel-title">Today's Pulse</h2>
                <?php foreach ($todayMetrics as $metric): ?>
                    <div class="vs-note-item">
                        <div class="vs-note-title"><?php echo htmlspecialchars((string)($metric['label'] ?? '')); ?>: <?php echo htmlspecialchars((string)($metric['value'] ?? '0')); ?></div>
                        <?php if (!empty($metric['hint'])): ?><div class="vs-list-meta"><?php echo htmlspecialchars((string)$metric['hint']); ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="vs-panel">
                <h2 class="vs-panel-title">Suggested Flow</h2>
                <?php foreach ($focusNotes as $note): ?>
                    <div class="vs-note-item">
                        <a class="vs-note-link" href="<?php echo htmlspecialchars((string)($note['href'] ?? '#')); ?>">
                            <div class="vs-note-title"><?php echo htmlspecialchars((string)($note['title'] ?? 'Next step')); ?></div>
                            <div class="vs-list-meta"><?php echo htmlspecialchars((string)($note['detail'] ?? '')); ?></div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="vs-panel">
                <h2 class="vs-panel-title">Upcoming Responsibilities</h2>
                <?php if (!empty($timeline)): ?>
                    <?php foreach ($timeline as $item): ?>
                        <div class="vs-timeline-item">
                            <a class="vs-timeline-link" href="<?php echo htmlspecialchars((string)($item['href'] ?? '#')); ?>">
                                <span class="vs-timeline-badge"><?php echo htmlspecialchars((string)($item['badge'] ?? 'Upcoming')); ?></span>
                                <div class="vs-timeline-title"><?php echo htmlspecialchars((string)($item['title'] ?? 'Item')); ?></div>
                                <div class="vs-list-meta"><?php echo htmlspecialchars((string)($item['type'] ?? 'Task')); ?> • <?php echo htmlspecialchars((string)($item['date_label'] ?? '')); ?></div>
                                <div class="vs-list-meta"><?php echo htmlspecialchars((string)($item['meta'] ?? '')); ?></div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="vs-empty">No upcoming responsibilities are visible from your current module access.</div>
                <?php endif; ?>
            </div>
        </aside>
    </section>
</div>
</body>
</html>
