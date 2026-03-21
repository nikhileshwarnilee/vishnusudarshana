<?php
$dashboardPageTitle = isset($dashboardPageTitle) ? (string)$dashboardPageTitle : 'Dashboard';
$dashboardHeading = isset($dashboardHeading) ? (string)$dashboardHeading : 'Dashboard';
$dashboard = isset($dashboard) && is_array($dashboard) ? $dashboard : [];
$heroStats = isset($dashboard['hero_stats']) && is_array($dashboard['hero_stats']) ? $dashboard['hero_stats'] : [];
$quickLinks = isset($dashboard['quick_links']) && is_array($dashboard['quick_links']) ? array_values($dashboard['quick_links']) : [];
$attentionCards = isset($dashboard['attention_cards']) && is_array($dashboard['attention_cards']) ? array_values($dashboard['attention_cards']) : [];
$moduleBoards = isset($dashboard['module_boards']) && is_array($dashboard['module_boards']) ? $dashboard['module_boards'] : [];
$todayMetrics = isset($dashboard['today_metrics']) && is_array($dashboard['today_metrics']) ? array_values($dashboard['today_metrics']) : [];
$focusNotes = isset($dashboard['focus_notes']) && is_array($dashboard['focus_notes']) ? array_values($dashboard['focus_notes']) : [];
$timeline = isset($dashboard['timeline']) && is_array($dashboard['timeline']) ? array_values($dashboard['timeline']) : [];
$userName = trim((string)($dashboard['user_name'] ?? 'Team'));
if ($userName === '') {
    $userName = 'Team';
}

$allowedTones = ['maroon', 'saffron', 'sky', 'sage', 'rose', 'warm', 'critical', 'calm'];
$toneClass = static function (string $value) use ($allowedTones): string {
    $value = trim(strtolower($value));
    return in_array($value, $allowedTones, true) ? $value : 'maroon';
};
$initials = static function (string $label): string {
    $parts = preg_split('/[^A-Za-z0-9]+/', strtoupper(trim($label)));
    $parts = is_array($parts) ? array_values(array_filter($parts)) : [];
    if (!$parts) {
        return 'GO';
    }
    if (count($parts) === 1) {
        return substr($parts[0], 0, 2);
    }
    return substr($parts[0], 0, 1) . substr($parts[1], 0, 1);
};

$glanceMetrics = array_slice($todayMetrics, 0, 5);
$priorityCards = array_slice($attentionCards, 0, 4);
$primaryShortcut = $quickLinks[0] ?? null;
$coverageStats = [
    ['label' => 'Modules', 'value' => vs_dashboard_format_count(count($moduleBoards))],
    ['label' => 'Shortcuts', 'value' => vs_dashboard_format_count(count($quickLinks))],
    ['label' => 'Upcoming', 'value' => vs_dashboard_format_count(count($timeline))],
];

$actionItems = [];
$seen = [];
$pushAction = static function (string $title, string $detail, string $href, string $cta = 'Open') use (&$actionItems, &$seen): void {
    $title = trim($title);
    if ($title === '') {
        return;
    }
    $key = strtolower($title . '|' . trim($href));
    if (isset($seen[$key])) {
        return;
    }
    $seen[$key] = true;
    $actionItems[] = [
        'title' => $title,
        'detail' => trim($detail),
        'href' => trim($href) !== '' ? trim($href) : '#',
        'cta' => trim($cta) !== '' ? trim($cta) : 'Open',
    ];
};

foreach ($focusNotes as $note) {
    $pushAction((string)($note['title'] ?? ''), (string)($note['detail'] ?? ''), (string)($note['href'] ?? ''), 'Open');
}
foreach ($priorityCards as $card) {
    if (count($actionItems) >= 5) {
        break;
    }
    $pushAction((string)($card['label'] ?? ''), (string)($card['detail'] ?? ''), (string)($card['href'] ?? ''), (string)($card['cta'] ?? 'Review'));
}
if (!$actionItems && $primaryShortcut) {
    $pushAction('Start from quick access', 'Use the first shortcut to jump into the main work queue.', (string)($primaryShortcut['href'] ?? '#'), 'Open');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dashboardPageTitle); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root{--bg1:#fff7ec;--bg2:#f2eadf;--paper:rgba(255,255,255,.84);--paper-strong:rgba(255,255,255,.93);--ink:#2f241d;--muted:#6d6258;--line:rgba(102,65,33,.11);--maroon:#8f3340;--saffron:#d18128;--sky:#337fa6;--sage:#628569;--rose:#d85b5d;--shadow:0 24px 56px rgba(74,42,17,.08)}
        body.vs-dashboard-body,body.vs-dashboard-body *{font-family:"Aptos","Trebuchet MS","Segoe UI",sans-serif!important}
        body.vs-dashboard-body{margin:0;color:var(--ink);background:radial-gradient(circle at top left,rgba(255,201,112,.34),transparent 26%),radial-gradient(circle at top right,rgba(143,51,64,.16),transparent 22%),linear-gradient(180deg,var(--bg1),var(--bg2))}
        .vs-shell{max-width:1480px;margin:0 auto;padding:24px 16px 48px}
        .vs-hero,.vs-row,.vs-main{display:grid;gap:18px}
        .vs-hero{grid-template-columns:minmax(0,1.55fr) minmax(320px,.95fr)}
        .vs-row{grid-template-columns:minmax(0,1fr) minmax(0,1fr);margin-top:18px}
        .vs-main{grid-template-columns:minmax(0,1fr) minmax(300px,.82fr);margin-top:18px}
        .vs-panel,.vs-hero-main,.vs-hero-side,.vs-card,.vs-action,.vs-board{background:var(--paper);border:1px solid var(--line);border-radius:26px;box-shadow:var(--shadow);backdrop-filter:blur(14px);animation:vs-up .45s ease both}
        .vs-hero-main{padding:30px;position:relative;overflow:hidden;background:linear-gradient(145deg,rgba(123,42,54,.98),rgba(209,129,40,.92) 70%,rgba(255,214,128,.92));color:#fff9f2}
        .vs-hero-main::after{content:"";position:absolute;right:-60px;bottom:-90px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.12)}
        .vs-hero-side,.vs-panel{padding:22px}
        .vs-kicker,.vs-label{margin:0;font-size:.76rem;letter-spacing:.18em;text-transform:uppercase}
        .vs-kicker{color:rgba(255,249,242,.82)}
        .vs-heading,.vs-title{font-family:"Palatino Linotype","Book Antiqua",Georgia,serif!important}
        .vs-heading{margin:10px 0 0;font-size:clamp(2.15rem,4vw,3.2rem);line-height:1.02}
        .vs-copy{margin:14px 0 0;max-width:760px;font-size:1rem;line-height:1.7;color:rgba(255,249,242,.92)}
        .vs-badges{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
        .vs-badge{display:inline-flex;align-items:center;padding:10px 14px;border-radius:999px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.14);font-size:.9rem;font-weight:700}
        .vs-stats,.vs-summary,.vs-grid,.vs-board-grid{display:grid;gap:12px}
        .vs-stats{grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-top:24px}
        .vs-stat{padding:16px;border-radius:18px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.16)}
        .vs-stat strong{display:block;font-size:1.9rem;line-height:1}
        .vs-stat span{display:block;margin-top:8px;font-size:.76rem;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,249,242,.84)}
        .vs-title{margin:6px 0 0;font-size:1.35rem;line-height:1.12;color:var(--maroon)}
        .vs-note,.vs-empty,.vs-meta,.vs-footnote{color:var(--muted);font-size:.93rem;line-height:1.6}
        .vs-note{margin:8px 0 0}
        .vs-grid{margin-top:14px}
        .vs-glance{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;padding:14px 16px;border-radius:18px;background:var(--paper-strong);border:1px solid rgba(102,65,33,.08)}
        .vs-glance strong{display:block;color:var(--ink)}
        .vs-glance small{display:block;margin-top:4px;color:var(--muted);font-size:.86rem;line-height:1.45}
        .vs-glance-value{flex-shrink:0;min-width:64px;padding:8px 12px;border-radius:14px;background:rgba(143,51,64,.08);color:var(--maroon);font-weight:800;text-align:center}
        .vs-summary{grid-template-columns:repeat(3,minmax(0,1fr))}
        .vs-summary-card{padding:14px;border-radius:18px;background:rgba(255,255,255,.72);border:1px solid rgba(102,65,33,.08)}
        .vs-summary-card strong{display:block;font-size:1.35rem;line-height:1;color:var(--maroon)}
        .vs-summary-card span{display:block;margin-top:6px;font-size:.74rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
        .vs-primary{display:block;margin-top:14px;padding:18px 20px;border-radius:22px;text-decoration:none;color:var(--ink);background:linear-gradient(135deg,rgba(143,51,64,.1),rgba(209,129,40,.18));border:1px solid rgba(143,51,64,.12)}
        .vs-primary strong{display:block;margin-top:8px;font-size:1.05rem;color:var(--maroon)}
        .vs-primary span{display:block;font-size:.75rem;letter-spacing:.14em;text-transform:uppercase;color:var(--muted)}
        .vs-primary small{display:block;margin-top:6px;font-size:.9rem;line-height:1.5;color:var(--muted)}
        .vs-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px}
        .vs-card,.vs-action{position:relative;overflow:hidden}
        .vs-card{padding:18px;text-decoration:none;color:inherit;transition:transform .18s ease,box-shadow .18s ease}
        .vs-card::before,.vs-priority::before{content:"";position:absolute;left:0;top:0;width:100%;height:4px;background:var(--tone)}
        .vs-card-mark{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:16px;background:var(--tone-soft);color:var(--tone);font-weight:800;letter-spacing:.08em}
        .vs-card-title{display:block;margin-top:12px;font-weight:800;color:var(--ink);line-height:1.35}
        .vs-card-sub{display:block;margin-top:4px;color:var(--muted);font-size:.86rem}
        .vs-priority-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
        .vs-priority{padding:18px;border-radius:22px;background:var(--paper);border:1px solid var(--line);box-shadow:var(--shadow);position:relative;overflow:hidden}
        .vs-priority-top{display:flex;justify-content:space-between;gap:12px;align-items:center}
        .vs-tag{display:inline-flex;align-items:center;padding:7px 10px;border-radius:999px;background:var(--tone-soft);color:var(--tone);font-size:.74rem;letter-spacing:.08em;text-transform:uppercase;font-weight:800}
        .vs-count{font-size:2rem;line-height:1;color:var(--tone);font-weight:800}
        .vs-priority h3{margin:14px 0 0;font-size:1.05rem;line-height:1.35;color:var(--ink)}
        .vs-priority p{margin:8px 0 0;color:var(--muted);font-size:.91rem;line-height:1.55}
        .vs-link{display:inline-flex;margin-top:14px;text-decoration:none;font-weight:800;color:var(--tone)}
        .vs-actions{display:grid;gap:12px}
        .vs-action{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:16px;align-items:center;padding:18px;transition:transform .18s ease,box-shadow .18s ease}
        .vs-step{width:50px;height:50px;border-radius:18px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,rgba(143,51,64,.12),rgba(209,129,40,.18));color:var(--maroon);font-weight:800}
        .vs-action h3{margin:0;font-size:1rem;color:var(--ink)}
        .vs-action p{margin:5px 0 0;color:var(--muted);font-size:.9rem;line-height:1.55}
        .vs-action-btn,.vs-board-open,.vs-chip{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:999px;text-decoration:none;font-weight:800;font-size:.86rem}
        .vs-action-btn{min-width:104px;background:rgba(143,51,64,.08);color:var(--maroon);border:1px solid rgba(143,51,64,.12)}
        .vs-board-grid{grid-template-columns:repeat(auto-fit,minmax(300px,1fr))}
        .vs-board{display:flex;flex-direction:column;gap:16px;padding:20px}
        .vs-board:nth-child(4n+1){--board:#8f3340;--board-soft:rgba(143,51,64,.08)}
        .vs-board:nth-child(4n+2){--board:#d18128;--board-soft:rgba(209,129,40,.1)}
        .vs-board:nth-child(4n+3){--board:#337fa6;--board-soft:rgba(51,127,166,.1)}
        .vs-board:nth-child(4n+4){--board:#628569;--board-soft:rgba(98,133,105,.1)}
        .vs-board-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}
        .vs-board-head h3{margin:0;font-size:1.12rem;color:var(--ink)}
        .vs-board-open{background:var(--board-soft);color:var(--board)}
        .vs-board p{margin:0;color:var(--muted);font-size:.92rem;line-height:1.6}
        .vs-board-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
        .vs-mini{padding:14px;border-radius:18px;background:rgba(255,255,255,.84);border:1px solid rgba(102,65,33,.08)}
        .vs-mini strong{display:block;font-size:1.32rem;line-height:1;color:var(--board)}
        .vs-mini span{display:block;margin-top:6px;font-size:.76rem;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
        .vs-mini small{display:block;margin-top:6px;font-size:.84rem;line-height:1.45;color:var(--muted)}
        .vs-board-list{display:grid;gap:10px}
        .vs-item{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;padding:14px 16px;border-radius:18px;text-decoration:none;color:inherit;background:rgba(255,255,255,.7);border:1px solid rgba(102,65,33,.08);transition:transform .18s ease,box-shadow .18s ease}
        .vs-item h4{margin:0;font-size:.98rem;line-height:1.4;color:var(--ink)}
        .vs-item p,.vs-item small{display:block;margin:4px 0 0;color:var(--muted);font-size:.87rem;line-height:1.5}
        .vs-flag{flex-shrink:0;display:inline-flex;align-items:center;padding:7px 10px;border-radius:999px;font-size:.74rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
        .vs-flag-critical,.tone-critical,.tone-rose{--tone:var(--rose);--tone-soft:rgba(216,91,93,.1)}
        .vs-flag-warm,.tone-warm,.tone-saffron{--tone:var(--saffron);--tone-soft:rgba(209,129,40,.1)}
        .vs-flag-sky,.tone-sky{--tone:var(--sky);--tone-soft:rgba(51,127,166,.1)}
        .vs-flag-sage,.tone-sage,.tone-calm{--tone:var(--sage);--tone-soft:rgba(98,133,105,.1)}
        .tone-maroon{--tone:var(--maroon);--tone-soft:rgba(143,51,64,.1)}
        .vs-flag-critical,.vs-flag-rose{background:rgba(216,91,93,.12);color:var(--rose)}
        .vs-flag-warm{background:rgba(209,129,40,.12);color:var(--saffron)}
        .vs-flag-sky{background:rgba(51,127,166,.11);color:var(--sky)}
        .vs-flag-sage{background:rgba(98,133,105,.11);color:var(--sage)}
        .vs-timeline{display:grid;gap:12px}
        .vs-time{padding-top:12px;border-top:1px solid rgba(102,65,33,.09)}
        .vs-time:first-child{padding-top:0;border-top:0}
        .vs-time a{display:block;text-decoration:none;color:inherit}
        .vs-time em{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:rgba(143,51,64,.08);color:var(--maroon);font-size:.74rem;font-style:normal;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
        .vs-time h3{margin:10px 0 0;font-size:1rem;line-height:1.45;color:var(--ink)}
        .vs-time p{margin:4px 0 0;color:var(--muted);font-size:.89rem;line-height:1.5}
        .vs-chip{background:rgba(255,255,255,.82);color:var(--ink);border:1px solid rgba(102,65,33,.1)}
        .vs-card:hover,.vs-action:hover,.vs-item:hover{transform:translateY(-2px);box-shadow:0 24px 50px rgba(74,42,17,.11)}
        @keyframes vs-up{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
        @media (max-width:1180px){.vs-hero,.vs-row,.vs-main{grid-template-columns:1fr}}
        @media (max-width:760px){.vs-shell{padding:16px 12px 40px}.vs-panel,.vs-hero-side,.vs-board,.vs-priority,.vs-card,.vs-action{padding:18px;border-radius:24px}.vs-summary,.vs-board-metrics,.vs-action{grid-template-columns:1fr}.vs-action-btn{width:100%}.vs-board-head,.vs-item,.vs-glance{flex-direction:column}.vs-heading{font-size:2rem}}
    </style>
</head>
<body class="vs-dashboard-body">
<?php include __DIR__ . '/top-menu.php'; ?>
<div class="vs-shell">
    <section class="vs-hero">
        <div class="vs-hero-main">
            <p class="vs-kicker"><?php echo htmlspecialchars((string)($dashboard['today_label'] ?? '')); ?> &middot; <?php echo htmlspecialchars((string)($dashboard['time_label'] ?? '')); ?></p>
            <h1 class="vs-heading"><?php echo htmlspecialchars($dashboardHeading); ?></h1>
            <p class="vs-copy"><?php echo htmlspecialchars((string)($dashboard['intro_text'] ?? '')); ?></p>
            <div class="vs-badges">
                <span class="vs-badge"><?php echo htmlspecialchars($userName); ?> on duty</span>
                <span class="vs-badge"><?php echo !empty($dashboard['is_super_admin']) ? 'Full admin coverage' : 'Permission based coverage'; ?></span>
            </div>
            <?php if ($heroStats): ?>
                <div class="vs-stats">
                    <?php foreach ($heroStats as $stat): ?>
                        <div class="vs-stat">
                            <strong><?php echo htmlspecialchars((string)($stat['value'] ?? '0')); ?></strong>
                            <span><?php echo htmlspecialchars((string)($stat['label'] ?? '')); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <aside class="vs-hero-side">
            <div>
                <p class="vs-label">Today's Glance</p>
                <h2 class="vs-title">Fast and easy to scan</h2>
                <p class="vs-note">This is the shortest path to understanding the day without digging through the full dashboard.</p>
            </div>
            <div class="vs-grid">
                <?php if ($glanceMetrics): ?>
                    <?php foreach ($glanceMetrics as $metric): ?>
                        <div class="vs-glance">
                            <div>
                                <strong><?php echo htmlspecialchars((string)($metric['label'] ?? '')); ?></strong>
                                <?php if (!empty($metric['hint'])): ?><small><?php echo htmlspecialchars((string)$metric['hint']); ?></small><?php endif; ?>
                            </div>
                            <div class="vs-glance-value"><?php echo htmlspecialchars((string)($metric['value'] ?? '0')); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="vs-empty">No glance metrics are available for this account yet.</div>
                <?php endif; ?>
            </div>
            <div class="vs-summary">
                <?php foreach ($coverageStats as $stat): ?>
                    <div class="vs-summary-card">
                        <strong><?php echo htmlspecialchars((string)$stat['value']); ?></strong>
                        <span><?php echo htmlspecialchars((string)$stat['label']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($primaryShortcut): ?>
                <a class="vs-primary" href="<?php echo htmlspecialchars((string)($primaryShortcut['href'] ?? '#')); ?>">
                    <span>Best place to start</span>
                    <strong><?php echo htmlspecialchars((string)($primaryShortcut['label'] ?? 'Open Dashboard')); ?></strong>
                    <small>Jump into the highest-priority shortcut from this dashboard.</small>
                </a>
            <?php endif; ?>
            <p class="vs-footnote"><?php echo htmlspecialchars((string)($dashboard['footer_note'] ?? '')); ?></p>
        </aside>
    </section>

    <section class="vs-row">
        <div class="vs-panel">
            <p class="vs-label">Quick Access</p>
            <h2 class="vs-title">Common tools in one tap</h2>
            <p class="vs-note">The most useful links are grouped here so the main menu does not slow you down.</p>
            <?php if ($quickLinks): ?>
                <div class="vs-cards" style="margin-top:16px;">
                    <?php foreach ($quickLinks as $link): ?>
                        <?php $tone = $toneClass((string)($link['accent'] ?? 'maroon')); ?>
                        <a class="vs-card tone-<?php echo htmlspecialchars($tone); ?>" href="<?php echo htmlspecialchars((string)($link['href'] ?? '#')); ?>">
                            <span class="vs-card-mark"><?php echo htmlspecialchars($initials((string)($link['label'] ?? 'Go'))); ?></span>
                            <span class="vs-card-title"><?php echo htmlspecialchars((string)($link['label'] ?? 'Open')); ?></span>
                            <span class="vs-card-sub">Open now</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="vs-empty">Quick access links will appear here when modules are available to this account.</div>
            <?php endif; ?>
        </div>

        <div class="vs-panel">
            <p class="vs-label">Actions To Take</p>
            <h2 class="vs-title">Work through these in order</h2>
            <p class="vs-note">This list turns the dashboard into a clear next-step board instead of a wall of information.</p>
            <?php if ($actionItems): ?>
                <div class="vs-actions" style="margin-top:16px;">
                    <?php foreach ($actionItems as $index => $item): ?>
                        <article class="vs-action">
                            <div class="vs-step"><?php echo htmlspecialchars(str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT)); ?></div>
                            <div>
                                <h3><?php echo htmlspecialchars((string)($item['title'] ?? 'Next step')); ?></h3>
                                <?php if (!empty($item['detail'])): ?><p><?php echo htmlspecialchars((string)$item['detail']); ?></p><?php endif; ?>
                            </div>
                            <a class="vs-action-btn" href="<?php echo htmlspecialchars((string)($item['href'] ?? '#')); ?>"><?php echo htmlspecialchars((string)($item['cta'] ?? 'Open')); ?></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="vs-empty">No action items are visible yet. Use quick access to jump into your main work area.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="vs-main">
        <div class="vs-panel">
            <p class="vs-label">Needs Attention</p>
            <h2 class="vs-title">What matters today</h2>
            <p class="vs-note">Priority cards keep urgent queues visible without flooding the whole page.</p>
            <?php if ($priorityCards): ?>
                <div class="vs-priority-grid" style="margin-top:16px;">
                    <?php foreach ($priorityCards as $card): ?>
                        <?php $tone = $toneClass((string)($card['tone'] ?? 'maroon')); ?>
                        <article class="vs-priority tone-<?php echo htmlspecialchars($tone); ?>">
                            <div class="vs-priority-top">
                                <span class="vs-tag"><?php echo htmlspecialchars((string)($card['label'] ?? 'Priority')); ?></span>
                                <span class="vs-count"><?php echo htmlspecialchars((string)vs_dashboard_format_count((int)($card['count'] ?? 0))); ?></span>
                            </div>
                            <h3><?php echo htmlspecialchars((string)($card['cta'] ?? 'Review now')); ?></h3>
                            <p><?php echo htmlspecialchars((string)($card['detail'] ?? '')); ?></p>
                            <a class="vs-link" href="<?php echo htmlspecialchars((string)($card['href'] ?? '#')); ?>"><?php echo htmlspecialchars((string)($card['cta'] ?? 'Open')); ?></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="vs-empty">Urgent queues are clear right now. Use quick access or upcoming responsibilities to stay ahead.</div>
            <?php endif; ?>
        </div>

        <aside class="vs-panel">
            <p class="vs-label">Upcoming Responsibilities</p>
            <h2 class="vs-title">What is coming next</h2>
            <?php if ($timeline): ?>
                <div class="vs-timeline" style="margin-top:16px;">
                    <?php foreach ($timeline as $item): ?>
                        <div class="vs-time">
                            <a href="<?php echo htmlspecialchars((string)($item['href'] ?? '#')); ?>">
                                <em><?php echo htmlspecialchars((string)($item['badge'] ?? 'Upcoming')); ?></em>
                                <h3><?php echo htmlspecialchars((string)($item['title'] ?? 'Item')); ?></h3>
                                <p><?php echo htmlspecialchars((string)($item['type'] ?? 'Task')); ?> &middot; <?php echo htmlspecialchars((string)($item['date_label'] ?? '')); ?></p>
                                <?php if (!empty($item['meta'])): ?><p><?php echo htmlspecialchars((string)$item['meta']); ?></p><?php endif; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="vs-empty">No upcoming responsibilities are visible from your current access.</div>
            <?php endif; ?>
        </aside>
    </section>

    <section class="vs-panel" style="margin-top:18px;">
        <p class="vs-label">Workboards</p>
        <h2 class="vs-title">Module by module clarity</h2>
        <p class="vs-note">Each board keeps totals, live items, and follow-up links together so you can understand a module at a glance.</p>
        <?php if ($moduleBoards): ?>
            <div class="vs-board-grid" style="margin-top:16px;">
                <?php foreach ($moduleBoards as $board): ?>
                    <article class="vs-board">
                        <div class="vs-board-head">
                            <div>
                                <h3><?php echo htmlspecialchars((string)($board['title'] ?? 'Board')); ?></h3>
                                <?php if (!empty($board['description'])): ?><p><?php echo htmlspecialchars((string)$board['description']); ?></p><?php endif; ?>
                            </div>
                            <a class="vs-board-open" href="<?php echo htmlspecialchars((string)($board['primary_href'] ?? '#')); ?>"><?php echo htmlspecialchars((string)($board['primary_label'] ?? 'Open')); ?></a>
                        </div>
                        <?php if (!empty($board['metrics'])): ?>
                            <div class="vs-board-metrics">
                                <?php foreach ($board['metrics'] as $metric): ?>
                                    <div class="vs-mini">
                                        <strong><?php echo htmlspecialchars((string)($metric['value'] ?? '0')); ?></strong>
                                        <span><?php echo htmlspecialchars((string)($metric['label'] ?? '')); ?></span>
                                        <?php if (!empty($metric['hint'])): ?><small><?php echo htmlspecialchars((string)$metric['hint']); ?></small><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="vs-board-list">
                            <?php if (!empty($board['items'])): ?>
                                <?php foreach ($board['items'] as $item): ?>
                                    <a class="vs-item" href="<?php echo htmlspecialchars((string)($item['href'] ?? '#')); ?>">
                                        <div>
                                            <h4><?php echo htmlspecialchars((string)($item['title'] ?? 'Item')); ?></h4>
                                            <?php if (!empty($item['meta'])): ?><p><?php echo htmlspecialchars((string)$item['meta']); ?></p><?php endif; ?>
                                            <?php if (!empty($item['support'])): ?><small><?php echo htmlspecialchars((string)$item['support']); ?></small><?php endif; ?>
                                        </div>
                                        <span class="vs-flag vs-flag-<?php echo htmlspecialchars($toneClass((string)($item['flag_tone'] ?? 'sage'))); ?>"><?php echo htmlspecialchars((string)($item['flag'] ?? 'Open')); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="vs-empty"><?php echo htmlspecialchars((string)($board['empty_text'] ?? 'No items visible.')); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($board['actions'])): ?>
                            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                <?php foreach ($board['actions'] as $action): ?>
                                    <a class="vs-chip" href="<?php echo htmlspecialchars((string)($action['href'] ?? '#')); ?>"><?php echo htmlspecialchars((string)($action['label'] ?? 'Open')); ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="vs-empty">No module boards are visible for this account yet.</div>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
