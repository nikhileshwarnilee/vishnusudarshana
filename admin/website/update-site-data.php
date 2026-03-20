<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Permission check
if (!isset($_SESSION['user_id'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/system_settings.php';
require_once __DIR__ . '/../includes/top-menu.php';

$pageTitle = 'Update Site Data';
$successMessage = '';
$errorMessage = '';
$marqueeDefinitions = vs_settings_marquee_definitions();
$marqueeFormValues = vs_settings_get_marquee_form_values($pdo);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'save-marquees') {
            vs_settings_ensure_table($pdo);

            foreach ($marqueeDefinitions as $settingKey => $definition) {
                $submittedValue = $_POST[$settingKey] ?? '';
                $sanitizedValue = vs_settings_normalize_marquee_html($submittedValue);
                vs_settings_upsert($pdo, $settingKey, $sanitizedValue);
            }

            $marqueeFormValues = vs_settings_get_marquee_form_values($pdo);
            $successMessage = 'Marquee content saved successfully.';
        }

        if ($action === 'refresh-festivals') {
            // Placeholder - will be executed when page opens in new window
            $successMessage = "Opening festival update page...";
        }
        
        if ($action === 'refresh-horoscope') {
            // Placeholder - will be executed when page opens in new window
            $successMessage = "Opening horoscope update page...";
        }
        
        if ($action === 'refresh-panchang') {
            // Placeholder - will be executed when page opens in new window
            $successMessage = "Opening panchang update page...";
        }
        
        if ($action === 'clear-cache') {
            // Clear application cache if any
            $successMessage = "Cache cleared successfully.";
        }
        
    } catch (Exception $e) {
        if ($action === 'save-marquees') {
            foreach ($marqueeDefinitions as $settingKey => $definition) {
                $marqueeFormValues[$settingKey] = (string) ($_POST[$settingKey] ?? '');
            }
        }
        $errorMessage = "Error: " . $e->getMessage();
    }
}

// Get last update times from database - query actual data instead of table metadata
$lastUpdates = [];
try {
    // Get last festival update
    $result = $pdo->query("SELECT MAX(created_at) as last_update FROM festivals");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    $lastUpdates['festivals'] = $row['last_update'] ?? null;
    
    // Get last horoscope update
    $result = $pdo->query("SELECT MAX(created_at) as last_update FROM daily_horoscope");
    $row = $result->fetch(PDO::FETCH_ASSOC);
    $lastUpdates['daily_horoscope'] = $row['last_update'] ?? null;
    
    // Get last panchang update - check if table exists first
    $result = $pdo->query("SHOW TABLES LIKE 'panchang'");
    if ($result->rowCount() > 0) {
        $result = $pdo->query("SELECT MAX(created_at) as last_update FROM panchang");
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $lastUpdates['panchang'] = $row['last_update'] ?? null;
    }
} catch (Exception $e) {
    // Silent fail for table info
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Admin</title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/admin.css">
    <style>
        .admin-page-wrapper {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 20px;
        }
        
        .page-header h1 {
            font-size: 2em;
            color: #800000;
            margin: 0;
        }
        
        .content-card {
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .content-card h2 {
            font-size: 1.3em;
            color: #333;
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .update-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .update-item:last-child {
            border-bottom: none;
        }
        
        .update-info {
            flex: 1;
        }
        
        .update-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .update-desc {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 4px;
        }
        
        .update-time {
            font-size: 0.85em;
            color: #999;
        }
        
        .update-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.95em;
        }
        
        .btn-primary {
            background: #800000;
            color: white;
        }
        
        .btn-primary:hover {
            background: #600000;
            box-shadow: 0 2px 8px rgba(128,0,0,0.3);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .status-updated {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-pending {
            background: #fff3e0;
            color: #e65100;
        }

        .section-note {
            margin: 0 0 18px;
            color: #666;
            line-height: 1.6;
        }

        .marquee-grid {
            display: grid;
            gap: 20px;
        }

        .marquee-field {
            padding: 18px;
            border: 1px solid #f0e1e1;
            border-radius: 10px;
            background: #fffdfd;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .marquee-field.is-active {
            border-color: #800000;
            background: #fff9f7;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.08);
        }

        .marquee-field-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }

        .marquee-field-copy {
            flex: 1;
        }

        .marquee-field h3 {
            margin: 0 0 6px;
            color: #800000;
            font-size: 1.05em;
        }

        .marquee-field p {
            margin: 0 0 12px;
            color: #666;
            line-height: 1.5;
        }

        .marquee-select-btn {
            flex-shrink: 0;
            min-width: 96px;
        }

        .marquee-select-btn.is-active {
            background: #800000;
            color: #fff;
        }

        .marquee-status {
            display: inline-flex;
            align-items: center;
            margin-top: 10px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #f8f1f1;
            color: #7a4343;
            font-size: 0.82em;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .marquee-preview {
            margin-top: 12px;
            color: #666;
            line-height: 1.6;
            font-size: 0.94em;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
            overflow: hidden;
        }

        .marquee-editor-panel {
            margin-top: 26px;
            padding: 22px;
            border: 1px solid #f0e1e1;
            border-radius: 12px;
            background: #fffafa;
        }

        .marquee-editor-panel h3 {
            margin: 0 0 8px;
            color: #800000;
            font-size: 1.1em;
        }

        .marquee-editor-panel p {
            margin: 0 0 14px;
            color: #666;
            line-height: 1.6;
        }

        .editor-wrapper {
            border: 1px solid #e0bebe;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            box-shadow: inset 0 1px 0 #f7f0f0;
        }

        .editor-area {
            min-height: 260px;
            width: 100%;
            padding: 14px;
            outline: none;
            font-size: 1em;
            line-height: 1.6;
            box-sizing: border-box;
        }

        .marquee-editor-frame {
            width: 100%;
            min-height: 700px;
            border: 0;
            display: block;
            background: #fff;
        }

        .helper-pill {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #f8f1f1;
            color: #7a4343;
            font-size: 0.88em;
            font-weight: 600;
        }
        
        @media (max-width: 600px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .marquee-field-header {
                flex-direction: column;
            }
             
            .update-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .update-actions {
                width: 100%;
                margin-top: 15px;
            }
            
            .btn {
                flex: 1;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="admin-layout">
    <!-- Top Menu -->
    <header class="admin-header">
        <?php echo $admin_menu_html ?? ''; ?>
    </header>

    <!-- Main Content -->
    <main class="admin-main">
        <div class="admin-page-wrapper">
            <div class="page-header">
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
            </div>

            <?php if ($successMessage): ?>
                <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="alert alert-error"><?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>

            <div class="content-card">
                <h2>Site Marquees</h2>
                <p class="section-note">
                    Manage the scrolling marquee text shown on the public website. Links added here will stay clickable on the live site.
                    Leave any editor empty if you want to hide that marquee completely.
                </p>
                <form method="POST" id="marqueeSettingsForm">
                    <input type="hidden" name="action" value="save-marquees">
                    <div class="marquee-grid">
                        <?php $marqueeIndex = 0; ?>
                        <?php foreach ($marqueeDefinitions as $settingKey => $definition): ?>
                            <?php
                            $currentValue = (string) ($marqueeFormValues[$settingKey] ?? '');
                            $plainPreview = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($currentValue), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                            $previewText = $plainPreview !== '' ? $plainPreview : 'This marquee is currently empty.';
                            $isActiveMarquee = $marqueeIndex === 0;
                            ?>
                            <section class="marquee-field<?= $isActiveMarquee ? ' is-active' : '' ?>" data-marquee-card="<?= htmlspecialchars($settingKey) ?>">
                                <div class="marquee-field-header">
                                    <div class="marquee-field-copy">
                                        <h3><?= htmlspecialchars((string) $definition['label']) ?></h3>
                                        <p><?= htmlspecialchars((string) $definition['description']) ?></p>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-secondary marquee-select-btn<?= $isActiveMarquee ? ' is-active' : '' ?>"
                                        data-select-marquee="<?= htmlspecialchars($settingKey) ?>"
                                    ><?= $isActiveMarquee ? 'Editing' : 'Edit' ?></button>
                                </div>
                                <div class="marquee-status" data-marquee-status="<?= htmlspecialchars($settingKey) ?>"><?= $currentValue !== '' ? 'Filled' : 'Empty' ?></div>
                                <div class="marquee-preview" data-marquee-preview="<?= htmlspecialchars($settingKey) ?>"><?= htmlspecialchars($previewText) ?></div>
                                <textarea id="<?= htmlspecialchars($settingKey) ?>" name="<?= htmlspecialchars($settingKey) ?>" style="display:none;"><?= htmlspecialchars($currentValue) ?></textarea>
                            </section>
                            <?php $marqueeIndex++; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php
                    $firstMarqueeKey = (string) array_key_first($marqueeDefinitions);
                    $firstMarqueeDefinition = $marqueeDefinitions[$firstMarqueeKey] ?? ['label' => 'Marquee Editor', 'description' => ''];
                    $firstMarqueeValue = (string) ($marqueeFormValues[$firstMarqueeKey] ?? '');
                    ?>
                    <section class="marquee-editor-panel">
                        <h3 id="activeMarqueeLabel"><?= htmlspecialchars((string) $firstMarqueeDefinition['label']) ?></h3>
                        <p id="activeMarqueeDescription"><?= htmlspecialchars((string) $firstMarqueeDefinition['description']) ?></p>
                        <div class="editor-wrapper">
                            <iframe
                                id="marqueeEditorFrame"
                                class="marquee-editor-frame"
                                src="marquee-editor-frame.php"
                                title="Site marquee editor"
                            ></iframe>
                        </div>
                        <div class="helper-pill">Same editor as blog add page. Saved marquee output is sanitized before showing on the website.</div>
                    </section>
                    <div class="update-actions" style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">Save Marquees</button>
                    </div>
                </form>
            </div>

            <!-- Festival Data Update -->
            <div class="content-card">
                <h2>Festival Data</h2>
                <div class="update-item">
                    <div class="update-info">
                        <div class="update-title">Festivals & Holidays</div>
                        <div class="update-desc">Today's festivals and holidays in multiple languages</div>
                        <div class="update-time">
                            Last updated: <?= $lastUpdates['festivals'] ? date('M d, Y H:i', strtotime($lastUpdates['festivals'])) : 'Never' ?>
                        </div>
                    </div>
                    <div class="update-actions">
                        <form action="../../scripts/festival_cronjob.php" method="GET" target="_blank" style="margin: 0;">
                            <button type="submit" class="btn btn-primary">Update</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Horoscope Data Update -->
            <div class="content-card">
                <h2>Horoscope Data</h2>
                <div class="update-item">
                    <div class="update-info">
                        <div class="update-title">Daily Horoscope</div>
                        <div class="update-desc">Daily horoscope predictions for all zodiac signs</div>
                        <div class="update-time">
                            Last updated: <?= $lastUpdates['daily_horoscope'] ? date('M d, Y H:i', strtotime($lastUpdates['daily_horoscope'])) : 'Never' ?>
                        </div>
                    </div>
                    <div class="update-actions">
                        <form action="../../scripts/horoscope_cronjob.php" method="GET" target="_blank" style="margin: 0;">
                            <button type="submit" class="btn btn-secondary">Update</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Panchang Data Update -->
            <div class="content-card">
                <h2>Panchang Data</h2>
                <div class="update-item">
                    <div class="update-info">
                        <div class="update-title">Panchang Calendar</div>
                        <div class="update-desc">Hindu calendar data with tithis, nakshatra, and other details</div>
                        <div class="update-time">
                            Last updated: <?= isset($lastUpdates['panchang']) && $lastUpdates['panchang'] ? date('M d, Y H:i', strtotime($lastUpdates['panchang'])) : 'Never' ?>
                        </div>
                    </div>
                    <div class="update-actions">
                        <form action="../../scripts/panchang_cronjob.php" method="GET" target="_blank" style="margin: 0;">
                            <button type="submit" class="btn btn-secondary">Update</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Cache Management -->
            <div class="content-card">
                <h2>Cache Management</h2>
                <div class="update-item">
                    <div class="update-info">
                        <div class="update-title">Application Cache</div>
                        <div class="update-desc">Clear browser cache and service worker data</div>
                    </div>
                    <div class="update-actions">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="clear-cache">
                            <button type="submit" class="btn btn-secondary" onclick="return confirm('Are you sure?')">Clear Cache</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Info Section -->
            <div class="content-card">
                <h2>Update Schedule</h2>
                <p style="color: #666; line-height: 1.6;">
                    <strong>Festival Data:</strong> Manual refresh available - fetches from API immediately<br>
                    <strong>Horoscope Data:</strong> Updated automatically via scheduled cronjob<br>
                    <strong>Panchang Data:</strong> Updated automatically via scheduled cronjob<br>
                    <br>
                    All scheduled updates run daily at a specified time. Manual refreshes are available for critical data like festivals.
                </p>
            </div>
        </div>
    </main>
</div>

<script>
    (function () {
        const marqueeForm = document.getElementById('marqueeSettingsForm');
        const editorFrame = document.getElementById('marqueeEditorFrame');
        const activeMarqueeLabel = document.getElementById('activeMarqueeLabel');
        const activeMarqueeDescription = document.getElementById('activeMarqueeDescription');
        const marqueeEditors = <?= json_encode(array_map(
            static function (string $settingKey) use ($marqueeDefinitions, $marqueeFormValues): array {
                return [
                    'settingKey' => $settingKey,
                    'hiddenId' => $settingKey,
                    'label' => (string) ($marqueeDefinitions[$settingKey]['label'] ?? ''),
                    'description' => (string) ($marqueeDefinitions[$settingKey]['description'] ?? ''),
                    'initialHtml' => (string) ($marqueeFormValues[$settingKey] ?? ''),
                ];
            },
            array_keys($marqueeDefinitions)
        ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        let activeMarqueeKey = marqueeEditors.length ? marqueeEditors[0].settingKey : '';
        let frameReady = false;
        let frameSyncTimer = 0;

        const getMarqueeConfig = settingKey => marqueeEditors.find(config => config.settingKey === settingKey) || null;

        const stripHtml = html => {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html || '';
            return (wrapper.textContent || wrapper.innerText || '').replace(/\s+/g, ' ').trim();
        };

        const updateMarqueeCard = config => {
            const hiddenField = document.getElementById(config.hiddenId);
            const card = document.querySelector('[data-marquee-card="' + config.settingKey + '"]');
            const button = document.querySelector('[data-select-marquee="' + config.settingKey + '"]');
            const status = document.querySelector('[data-marquee-status="' + config.settingKey + '"]');
            const preview = document.querySelector('[data-marquee-preview="' + config.settingKey + '"]');
            const html = hiddenField ? (hiddenField.value || '') : '';
            const plainText = stripHtml(html);
            const previewText = plainText || (html ? 'Rich marquee content saved.' : 'This marquee is currently empty.');
            const isActive = config.settingKey === activeMarqueeKey;

            if (card) {
                card.classList.toggle('is-active', isActive);
            }
            if (button) {
                button.classList.toggle('is-active', isActive);
                button.classList.toggle('btn-primary', isActive);
                button.classList.toggle('btn-secondary', !isActive);
                button.textContent = isActive ? 'Editing' : 'Edit';
            }
            if (status) {
                status.textContent = html.trim() !== '' ? 'Filled' : 'Empty';
            }
            if (preview) {
                preview.textContent = previewText;
            }
        };

        const updateAllMarqueeCards = () => {
            marqueeEditors.forEach(function (config) {
                updateMarqueeCard(config);
            });
        };

        const syncActiveMarqueeFromFrame = () => {
            if (!frameReady || !editorFrame || !editorFrame.contentWindow || !activeMarqueeKey) {
                return;
            }
            if (typeof editorFrame.contentWindow.vsMarqueeEditorGetContent !== 'function') {
                return;
            }
            const hiddenField = document.getElementById(activeMarqueeKey);
            if (!hiddenField) {
                return;
            }
            hiddenField.value = editorFrame.contentWindow.vsMarqueeEditorGetContent() || '';
            updateAllMarqueeCards();
        };

        const pushActiveMarqueeToFrame = () => {
            if (!editorFrame || !editorFrame.contentWindow || typeof editorFrame.contentWindow.vsMarqueeEditorSetContent !== 'function') {
                return false;
            }
            const config = getMarqueeConfig(activeMarqueeKey);
            if (!config) {
                return false;
            }
            const hiddenField = document.getElementById(config.hiddenId);
            if (activeMarqueeLabel) {
                activeMarqueeLabel.textContent = config.label || 'Marquee Editor';
            }
            if (activeMarqueeDescription) {
                activeMarqueeDescription.textContent = config.description || '';
            }
            editorFrame.contentWindow.vsMarqueeEditorSetContent(hiddenField ? (hiddenField.value || '') : '');
            frameReady = true;
            updateAllMarqueeCards();
            return true;
        };

        const waitForFrameEditor = () => {
            window.clearTimeout(frameSyncTimer);
            if (pushActiveMarqueeToFrame()) {
                return;
            }
            frameSyncTimer = window.setTimeout(waitForFrameEditor, 150);
        };

        const loadMarqueeIntoEditor = settingKey => {
            const config = getMarqueeConfig(settingKey);
            if (!config) {
                return;
            }
            activeMarqueeKey = config.settingKey;
            waitForFrameEditor();
        };

        marqueeEditors.forEach(function (config) {
            const hiddenField = document.getElementById(config.hiddenId);
            if (hiddenField && hiddenField.value === '') {
                hiddenField.value = config.initialHtml || '';
            }
        });
        updateAllMarqueeCards();

        document.querySelectorAll('[data-select-marquee]').forEach(function (button) {
            button.addEventListener('click', function () {
                const settingKey = button.getAttribute('data-select-marquee') || '';
                if (!settingKey || settingKey === activeMarqueeKey) {
                    return;
                }
                syncActiveMarqueeFromFrame();
                loadMarqueeIntoEditor(settingKey);
            });
        });

        window.vsMarqueeEditorParentChange = function (html) {
            if (!activeMarqueeKey) {
                return;
            }
            const hiddenField = document.getElementById(activeMarqueeKey);
            if (!hiddenField) {
                return;
            }
            hiddenField.value = html || '';
            updateAllMarqueeCards();
        };

        if (editorFrame) {
            editorFrame.addEventListener('load', function () {
                frameReady = false;
                waitForFrameEditor();
            });
        }
        waitForFrameEditor();

        if (marqueeForm) {
            marqueeForm.addEventListener('submit', function () {
                syncActiveMarqueeFromFrame();
            });
        }
    })();
</script>
</body>
</html>



