<?php
if (!empty($GLOBALS['vs_floating_model_rendered'])) {
    return;
}
$GLOBALS['vs_floating_model_rendered'] = true;

$vsFloatingBaseUrl = function_exists('vs_admin_get_base_url') ? vs_admin_get_base_url() : '';
$vsFloatingAdminUrl = $vsFloatingBaseUrl . '/admin';
$vsFloatingUserId = (int)($_SESSION['user_id'] ?? 0);
$vsTokenWidgetCanView = $vsFloatingUserId > 0
    && function_exists('vs_admin_can_access_route')
    && vs_admin_can_access_route($vsFloatingUserId, 'token-management/floating-token-bookings.php', 'view');
$vsTokenWidgetCanEdit = $vsFloatingUserId > 0
    && function_exists('vs_admin_can_access_route')
    && vs_admin_can_access_route($vsFloatingUserId, 'token-management/call-booking.php', 'edit')
    && vs_admin_can_access_route($vsFloatingUserId, 'token-management/skip-booking.php', 'edit');
$vsTokenWidgetCanDelete = $vsFloatingUserId > 0
    && function_exists('vs_admin_can_access_route')
    && vs_admin_can_access_route($vsFloatingUserId, 'token-management/delete-booking.php', 'delete');
?>
<?php if ($vsTokenWidgetCanView && ($vsTokenWidgetCanEdit || $vsTokenWidgetCanDelete)): ?>
<style>
    .vs-token-widget {
        position: fixed;
        right: 28px;
        bottom: 112px;
        z-index: 99997;
        width: 360px;
        max-width: calc(100vw - 24px);
        max-height: min(520px, calc(100vh - 96px));
        background: #ffffff;
        border: 1px solid #ead2d2;
        border-radius: 10px;
        box-shadow: 0 12px 34px rgba(35, 20, 20, 0.22);
        color: #2b2b2b;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        overflow: hidden;
    }
    .vs-token-widget.is-hidden {
        display: none;
    }
    .vs-token-widget.collapsed {
        width: 58px;
        height: 58px;
        max-width: 58px;
        max-height: 58px;
        border-radius: 50%;
        border-color: #800000;
        background: #800000;
        box-shadow: 0 8px 22px rgba(128, 0, 0, 0.28);
    }
    .vs-token-widget.collapsed .vs-token-widget-body {
        display: none;
    }
    .vs-token-widget.collapsed .vs-token-widget-header {
        width: 58px;
        height: 58px;
        padding: 0;
        justify-content: center;
        border-radius: 50%;
        cursor: pointer;
    }
    .vs-token-widget.collapsed .vs-token-widget-title,
    .vs-token-widget.collapsed .vs-token-widget-count {
        display: none;
    }
    .vs-token-widget.collapsed .vs-token-widget-toggle {
        width: 58px;
        height: 58px;
        border: none;
        border-radius: 50%;
        background: transparent;
        font-size: 26px;
    }
    .vs-token-widget-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        background: #800000;
        color: #ffffff;
        cursor: move;
        user-select: none;
        touch-action: none;
    }
    .vs-token-widget-title {
        flex: 1;
        min-width: 0;
        font-size: 14px;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .vs-token-widget-count {
        min-width: 24px;
        height: 22px;
        padding: 0 7px;
        border-radius: 999px;
        background: #fff0d6;
        color: #800000;
        font-size: 12px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .vs-token-widget-toggle {
        width: 28px;
        height: 28px;
        border: 1px solid rgba(255, 255, 255, 0.35);
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.12);
        color: #ffffff;
        font-size: 18px;
        line-height: 1;
        cursor: pointer;
    }
    .vs-token-widget-body {
        display: flex;
        flex-direction: column;
        max-height: calc(min(520px, calc(100vh - 96px)) - 49px);
    }
    .vs-token-widget-list {
        overflow: auto;
        padding: 8px;
    }
    .vs-token-widget-state {
        padding: 16px 12px;
        color: #666;
        font-size: 13px;
        text-align: center;
    }
    .vs-token-widget-item {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 8px;
        align-items: center;
        padding: 9px 8px;
        border-bottom: 1px solid #f1dddd;
    }
    .vs-token-widget-item:last-child {
        border-bottom: none;
    }
    .vs-token-widget-token {
        color: #800000;
        font-size: 13px;
        font-weight: 800;
    }
    .vs-token-widget-name {
        margin-top: 2px;
        color: #252525;
        font-size: 13px;
        font-weight: 650;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .vs-token-widget-meta {
        margin-top: 2px;
        color: #777;
        font-size: 11px;
        text-transform: capitalize;
    }
    .vs-token-widget-actions {
        display: flex;
        gap: 5px;
        align-items: center;
    }
    .vs-token-widget-actions button {
        min-width: 42px;
        height: 30px;
        padding: 0 8px;
        border: none;
        border-radius: 6px;
        color: #ffffff;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }
    .vs-token-widget-actions button:disabled {
        opacity: 0.65;
        cursor: wait;
    }
    .vs-token-call-btn {
        background: #d18b00;
    }
    .vs-token-skip-btn {
        background: #856404;
    }
    .vs-token-delete-btn {
        background: #c00;
    }
    .vs-token-widget-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 8px 10px;
        border-top: 1px solid #f1dddd;
        background: #fffafa;
    }
    .vs-token-widget-refresh {
        border: 1px solid #d9bcbc;
        background: #ffffff;
        color: #800000;
        border-radius: 6px;
        padding: 6px 9px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }
    .vs-token-widget-link {
        color: #800000;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
    }
    .vs-token-widget-link:hover {
        text-decoration: underline;
    }
    @media (max-width: 600px) {
        .vs-token-widget {
            right: 12px;
            bottom: 92px;
            width: calc(100vw - 24px);
        }
        .vs-token-widget.collapsed {
            width: 58px;
            height: 58px;
        }
        .vs-token-widget-item {
            grid-template-columns: 1fr;
        }
        .vs-token-widget-actions {
            justify-content: flex-start;
        }
    }
</style>

<div id="vsTokenWidget" class="vs-token-widget" data-vs-route-permission-scope="independent">
    <div id="vsTokenWidgetHandle" class="vs-token-widget-header">
        <div class="vs-token-widget-title">Today Tokens</div>
        <span id="vsTokenWidgetCount" class="vs-token-widget-count">0</span>
        <button type="button" id="vsTokenWidgetToggle" class="vs-token-widget-toggle" title="Minimize">-</button>
    </div>
    <div class="vs-token-widget-body">
        <div id="vsTokenWidgetList" class="vs-token-widget-list">
            <div class="vs-token-widget-state">Loading tokens...</div>
        </div>
        <div class="vs-token-widget-footer">
            <button type="button" id="vsTokenWidgetRefresh" class="vs-token-widget-refresh">Refresh</button>
            <a class="vs-token-widget-link" href="<?= htmlspecialchars($vsFloatingAdminUrl . '/token-management/booked-tokens.php', ENT_QUOTES, 'UTF-8') ?>">View all</a>
        </div>
    </div>
</div>

<script>
(function () {
    const widget = document.getElementById('vsTokenWidget');
    if (!widget) return;

    const config = {
        endpoints: {
            list: <?= json_encode($vsFloatingAdminUrl . '/token-management/floating-token-bookings.php') ?>,
            call: <?= json_encode($vsFloatingAdminUrl . '/token-management/call-booking.php') ?>,
            skip: <?= json_encode($vsFloatingAdminUrl . '/token-management/skip-booking.php') ?>,
            delete: <?= json_encode($vsFloatingAdminUrl . '/token-management/delete-booking.php') ?>
        },
        canEdit: <?= $vsTokenWidgetCanEdit ? 'true' : 'false' ?>,
        canDelete: <?= $vsTokenWidgetCanDelete ? 'true' : 'false' ?>
    };

    const handle = document.getElementById('vsTokenWidgetHandle');
    const toggle = document.getElementById('vsTokenWidgetToggle');
    const list = document.getElementById('vsTokenWidgetList');
    const count = document.getElementById('vsTokenWidgetCount');
    const refresh = document.getElementById('vsTokenWidgetRefresh');
    const storageKey = 'vsTokenWidgetState';
    let dragState = null;

    function readState() {
        try {
            return JSON.parse(localStorage.getItem(storageKey) || '{}') || {};
        } catch (e) {
            return {};
        }
    }

    function saveState(extra) {
        const state = Object.assign(readState(), extra || {});
        localStorage.setItem(storageKey, JSON.stringify(state));
    }

    function clampPosition(left, top) {
        const rect = widget.getBoundingClientRect();
        const maxLeft = Math.max(12, window.innerWidth - rect.width - 12);
        const maxTop = Math.max(68, window.innerHeight - rect.height - 12);
        return {
            left: Math.min(Math.max(12, left), maxLeft),
            top: Math.min(Math.max(68, top), maxTop)
        };
    }

    function applySavedState() {
        const state = readState();
        if (typeof state.left === 'number' && typeof state.top === 'number') {
            const pos = clampPosition(state.left, state.top);
            widget.style.left = pos.left + 'px';
            widget.style.top = pos.top + 'px';
            widget.style.right = 'auto';
            widget.style.bottom = 'auto';
        }
        if (state.collapsed) {
            widget.classList.add('collapsed');
            toggle.textContent = '+';
            toggle.title = 'Expand';
        }
    }

    function setLoading() {
        list.innerHTML = '<div class="vs-token-widget-state">Loading tokens...</div>';
    }

    function escapeText(value) {
        return String(value === null || value === undefined ? '' : value);
    }

    function renderTokens(tokens) {
        widget.classList.remove('is-hidden');
        count.textContent = tokens.length;
        if (!tokens.length) {
            list.innerHTML = '<div class="vs-token-widget-state">No active tokens for today.</div>';
            return;
        }

        const fragment = document.createDocumentFragment();
        tokens.forEach(function (token) {
            const item = document.createElement('div');
            item.className = 'vs-token-widget-item';

            const details = document.createElement('div');
            const tokenNo = document.createElement('div');
            tokenNo.className = 'vs-token-widget-token';
            tokenNo.textContent = 'Token ' + escapeText(token.token_no || '-');
            const name = document.createElement('div');
            name.className = 'vs-token-widget-name';
            name.textContent = escapeText(token.name || 'No name');
            const meta = document.createElement('div');
            meta.className = 'vs-token-widget-meta';
            meta.textContent = escapeText(token.location || '');
            details.appendChild(tokenNo);
            details.appendChild(name);
            details.appendChild(meta);

            const actions = document.createElement('div');
            actions.className = 'vs-token-widget-actions';

            if (config.canEdit) {
                actions.appendChild(makeActionButton('Call', 'vs-token-call-btn', function () {
                    runAction(config.endpoints.call, token.id, 'call');
                }));
                actions.appendChild(makeActionButton('Skip', 'vs-token-skip-btn', function () {
                    runAction(config.endpoints.skip, token.id, 'skip');
                }));
            }
            if (config.canDelete) {
                actions.appendChild(makeActionButton('Delete', 'vs-token-delete-btn', function () {
                    if (confirm('Delete token ' + escapeText(token.token_no || '') + '?')) {
                        runAction(config.endpoints.delete, token.id, 'delete');
                    }
                }));
            }

            item.appendChild(details);
            item.appendChild(actions);
            fragment.appendChild(item);
        });

        list.innerHTML = '';
        list.appendChild(fragment);
    }

    function makeActionButton(label, className, handler) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.textContent = label;
        button.addEventListener('click', handler);
        return button;
    }

    function setButtonsBusy(busy) {
        widget.querySelectorAll('.vs-token-widget-actions button, #vsTokenWidgetRefresh').forEach(function (button) {
            button.disabled = busy;
        });
    }

    function loadTokens(showLoading) {
        if (showLoading) {
            widget.classList.remove('is-hidden');
            setLoading();
        }
        return fetch(config.endpoints.list, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) {
                    throw new Error(data.message || 'Unable to load tokens.');
                }
                const todayCount = parseInt(data.today_count || 0, 10) || 0;
                const tokens = Array.isArray(data.tokens) ? data.tokens : [];
                if (todayCount <= 0) {
                    widget.classList.add('is-hidden');
                    count.textContent = '0';
                    list.innerHTML = '';
                    return;
                }
                renderTokens(tokens);
            })
            .catch(function () {
                widget.classList.remove('is-hidden');
                count.textContent = '!';
                list.innerHTML = '<div class="vs-token-widget-state">Unable to load today tokens.</div>';
            });
    }

    function runAction(url, id, actionName) {
        if (!id) return;
        setButtonsBusy(true);
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: 'id=' + encodeURIComponent(id)
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) {
                    throw new Error(data.message || 'Unable to ' + actionName + ' token.');
                }
                return loadTokens(false);
            })
            .catch(function (err) {
                alert(err.message || 'Token action failed.');
                return loadTokens(false);
            })
            .finally(function () {
                setButtonsBusy(false);
            });
    }

    toggle.addEventListener('click', function (event) {
        event.stopPropagation();
        const collapsed = !widget.classList.contains('collapsed');
        widget.classList.toggle('collapsed', collapsed);
        toggle.textContent = collapsed ? '+' : '-';
        toggle.title = collapsed ? 'Expand' : 'Minimize';
        saveState({ collapsed: collapsed });
    });

    refresh.addEventListener('click', function () {
        loadTokens(true);
    });

    handle.addEventListener('pointerdown', function (event) {
        if (widget.classList.contains('collapsed') || event.target === toggle) return;
        const rect = widget.getBoundingClientRect();
        dragState = {
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            left: rect.left,
            top: rect.top
        };
        handle.setPointerCapture(event.pointerId);
    });

    handle.addEventListener('pointermove', function (event) {
        if (!dragState || dragState.pointerId !== event.pointerId) return;
        const pos = clampPosition(
            dragState.left + event.clientX - dragState.startX,
            dragState.top + event.clientY - dragState.startY
        );
        widget.style.left = pos.left + 'px';
        widget.style.top = pos.top + 'px';
        widget.style.right = 'auto';
        widget.style.bottom = 'auto';
    });

    handle.addEventListener('pointerup', function (event) {
        if (!dragState || dragState.pointerId !== event.pointerId) return;
        const rect = widget.getBoundingClientRect();
        saveState({ left: rect.left, top: rect.top });
        dragState = null;
    });

    window.addEventListener('resize', function () {
        const rect = widget.getBoundingClientRect();
        const pos = clampPosition(rect.left, rect.top);
        widget.style.left = pos.left + 'px';
        widget.style.top = pos.top + 'px';
        widget.style.right = 'auto';
        widget.style.bottom = 'auto';
        saveState({ left: pos.left, top: pos.top });
    });

    applySavedState();
    window.VSRefreshTokenWidget = function () {
        return loadTokens(false);
    };
    loadTokens(true);
    setInterval(function () {
        loadTokens(false);
    }, 20000);
})();
</script>
<?php endif; ?>

<!-- Floating Modal Example -->
<div id="floatingModal" style="display:none; position:fixed; bottom:32px; right:32px; z-index:99999; background:#fff; box-shadow:0 4px 24px #80000033; border-radius:16px; min-width:320px; max-width:95vw; width:400px; padding:28px 24px 18px 24px;">
    <div style="font-size:1.15em; color:#800000; font-weight:700; margin-bottom:10px;">Floating Modal</div>
    <div id="floatingModalContent" style="color:#444; margin-bottom:18px;">This is a floating modal. You can customize its content.</div>
    <div style="text-align:right;">
        <button onclick="document.getElementById('floatingModal').style.display='none'" style="background:#800000; color:#fff; padding:8px 22px; border:none; border-radius:8px; font-weight:600; cursor:pointer;">Close</button>
    </div>
</div>
<!-- Floating Writing Icon Button -->
<button id="floatingWriteBtn" onclick="openFloatingPopup()" style="position:fixed; bottom:32px; right:32px; z-index:99998; background:#800000; border:none; box-shadow:0 2px 12px #80000033; border-radius:50%; width:64px; height:64px; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.2s;">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" style="display:block;" xmlns="http://www.w3.org/2000/svg">
        <path d="M20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25z" fill="#fff"/>
    </svg>
</button>
<script>
function showFloatingModal(content) {
    document.getElementById('floatingModalContent').innerHTML = content || 'This is a floating modal.';
    document.getElementById('floatingModal').style.display = 'block';
}
function getAdminBasePath() {
    const path = window.location.pathname || '';
    const adminMarker = '/admin/';
    const adminIndex = path.indexOf(adminMarker);
    if (adminIndex >= 0) {
        return path.slice(0, adminIndex + '/admin'.length);
    }
    return '/admin';
}
function openFloatingPopup() {
    const popupUrl = window.location.origin + getAdminBasePath() + '/popup-floating.php';
    window.open(popupUrl, 'FloatingPopup', 'width=600,height=600,scrollbars=yes,resizable=yes');
}
</script>
<!-- Usage: Call showFloatingModal('Your content here') from anywhere -->
