<?php
if (defined('VS_INTERNAL_CLIPBOARD_WIDGET_INCLUDE')) {
    return;
}
define('VS_INTERNAL_CLIPBOARD_WIDGET_INCLUDE', true);
?>
<style>
#vsInternalClipboardTrigger {
    position: fixed;
    z-index: 100090;
    width: 34px;
    height: 34px;
    border: none;
    border-radius: 50%;
    background: #0f6cbd;
    color: #fff;
    display: none;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.25);
    cursor: pointer;
    transition: transform 0.15s ease;
}

#vsInternalClipboardTrigger:hover {
    transform: scale(1.08);
}

#vsInternalClipboardOverlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.22);
    z-index: 100100;
    display: none;
}

#vsInternalClipboardPanel {
    position: fixed;
    right: 16px;
    bottom: 16px;
    width: 430px;
    max-width: calc(100vw - 24px);
    max-height: calc(100vh - 24px);
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
    z-index: 100101;
    display: none;
    overflow: hidden;
    border: 1px solid #e8e8e8;
}

#vsInternalClipboardPanelHeader {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    background: #f5f8fd;
    border-bottom: 1px solid #d7e4f5;
}

#vsInternalClipboardPanelHeader h3 {
    margin: 0;
    font-size: 15px;
    color: #123d67;
}

#vsInternalClipboardPanelBody {
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: calc(100vh - 90px);
    overflow: auto;
}

.vs-clip-row {
    border: 1px solid #e6e6e6;
    border-radius: 8px;
    background: #fcfcfc;
    padding: 8px;
}

.vs-clip-row-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 6px;
}

.vs-clip-header-main {
    flex: 1;
    min-width: 0;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.vs-clip-header-main:hover .vs-clip-title {
    text-decoration: underline;
}

.vs-clip-title {
    font-weight: 700;
    font-size: 13px;
    color: #1f1f1f;
    line-height: 1.25;
}

.vs-clip-meta {
    color: #6f6f6f;
    font-size: 11px;
}

.vs-clip-toggle-indicator {
    color: #56708a;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.vs-clip-row.is-collapsed .vs-clip-content {
    display: none;
}

.vs-clip-content {
    border: 1px solid #ededed;
    border-radius: 6px;
    background: #fff;
    padding: 8px;
    white-space: pre-wrap;
    word-break: break-word;
    font-size: 13px;
    color: #2a2a2a;
    cursor: pointer;
}

.vs-clip-content:hover {
    border-color: #c8d9ee;
    background: #f8fbff;
}

.vs-clip-actions {
    display: flex;
    justify-content: flex-end;
    gap: 6px;
    margin-top: 7px;
}

.vs-clip-btn {
    border: 1px solid #cfd8e3;
    background: #fff;
    border-radius: 6px;
    padding: 4px 9px;
    font-size: 12px;
    cursor: pointer;
}

.vs-clip-btn:hover {
    background: #f5f8fd;
}

.vs-clip-btn-danger {
    border-color: #f0c8c8;
    color: #8d1f1f;
    background: #fff7f7;
}

.vs-clip-btn-danger:hover {
    background: #ffecec;
}

#vsInternalClipboardStatus {
    font-size: 12px;
    min-height: 14px;
    color: #0e6d2a;
}

#vsInternalClipboardStatus.error {
    color: #b42318;
}

#vsInternalClipboardSearch {
    width: 100%;
    border: 1px solid #ccd9e7;
    border-radius: 8px;
    padding: 8px 10px;
    box-sizing: border-box;
    font-size: 13px;
    font-family: Arial, sans-serif;
    background: #fff;
}

#vsInternalClipboardSearch:focus {
    border-color: #0f6cbd;
    outline: none;
    box-shadow: 0 0 0 2px rgba(15, 108, 189, 0.12);
}

#vsInternalClipboardEditor {
    border: 1px solid #dde6f1;
    border-radius: 8px;
    background: #f8fbff;
    padding: 8px;
    display: none;
    flex-direction: column;
    gap: 6px;
}

#vsInternalClipboardEditor label {
    font-size: 12px;
    color: #34506b;
    font-weight: 600;
}

#vsInternalClipboardEditor input,
#vsInternalClipboardEditor textarea {
    width: 100%;
    border: 1px solid #ccd9e7;
    border-radius: 6px;
    padding: 7px 9px;
    box-sizing: border-box;
    font-size: 13px;
    font-family: Arial, sans-serif;
}

#vsInternalClipboardEditor textarea {
    min-height: 88px;
    resize: vertical;
}

#vsInternalClipboardEmpty {
    font-size: 13px;
    color: #707070;
    background: #fbfbfb;
    border: 1px dashed #dcdcdc;
    border-radius: 8px;
    padding: 12px;
}

#vsInternalClipboardList {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

@media (max-width: 700px) {
    #vsInternalClipboardPanel {
        right: 8px;
        left: 8px;
        width: auto;
        bottom: 8px;
        max-width: none;
    }
}
</style>
<script>
(function () {
    if (window.__vsInternalClipboardBooted) {
        return;
    }
    window.__vsInternalClipboardBooted = true;

    const state = {
        activeTarget: null,
        textareaStart: null,
        textareaEnd: null,
        contentRange: null,
        panelOpen: false,
        mode: 'add',
        editId: null,
        items: [],
        collapsedById: {},
        searchQuery: '',
        hideTimer: null,
        dom: {}
    };

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    function getAdminRootPath() {
        const path = window.location.pathname || '';
        const marker = '/admin/';
        const idx = path.indexOf(marker);
        if (idx >= 0) {
            return path.slice(0, idx + '/admin'.length);
        }
        return '/admin';
    }

    function getEndpointUrl() {
        return window.location.origin + getAdminRootPath() + '/internal_clipboard_handler.php';
    }

    function isEditableTarget(el) {
        if (!el || el.nodeType !== 1) {
            return false;
        }
        if (typeof el.closest === 'function' && el.closest('#vsInternalClipboardPanel')) {
            return false;
        }
        if (el.tagName === 'TEXTAREA') {
            return !el.disabled && !el.readOnly;
        }
        return !!el.isContentEditable;
    }

    function findEditableTarget(node) {
        if (!node || node.nodeType !== 1) {
            return null;
        }
        if (isEditableTarget(node)) {
            return node;
        }
        if (typeof node.closest === 'function') {
            const match = node.closest('textarea,[contenteditable],[contenteditable="true"],[contenteditable=""]');
            if (match && isEditableTarget(match)) {
                return match;
            }
        }
        return null;
    }

    function isElementVisible(el) {
        if (!el || !document.contains(el)) {
            return false;
        }
        const style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
            return false;
        }
        const rect = el.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
    }

    function formatDateTime(raw) {
        if (!raw) {
            return '';
        }
        const val = String(raw).replace(' ', 'T');
        const dt = new Date(val);
        if (isNaN(dt.getTime())) {
            return String(raw);
        }
        return dt.toLocaleString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    }

    function setStatus(message, isError) {
        const statusEl = state.dom.status;
        if (!statusEl) {
            return;
        }
        statusEl.textContent = message || '';
        statusEl.classList.toggle('error', !!isError);
    }

    function normalizeSearchText(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .trim();
    }

    function positionTrigger() {
        const trigger = state.dom.trigger;
        if (!trigger) {
            return;
        }

        if (!state.activeTarget || !isElementVisible(state.activeTarget)) {
            if (!state.panelOpen) {
                trigger.style.display = 'none';
            }
            return;
        }

        const rect = state.activeTarget.getBoundingClientRect();
        const size = 34;
        const pad = 8;
        const top = Math.max(pad, Math.min(window.innerHeight - size - pad, rect.top + 8));
        const left = Math.max(pad, Math.min(window.innerWidth - size - pad, rect.right - size - 8));

        trigger.style.top = top + 'px';
        trigger.style.left = left + 'px';
        trigger.style.display = 'flex';
    }

    function captureSelectionState() {
        const el = state.activeTarget;
        if (!el || !document.contains(el)) {
            return;
        }

        if (el.tagName === 'TEXTAREA') {
            state.textareaStart = typeof el.selectionStart === 'number' ? el.selectionStart : 0;
            state.textareaEnd = typeof el.selectionEnd === 'number' ? el.selectionEnd : state.textareaStart;
            return;
        }

        if (el.isContentEditable) {
            const sel = window.getSelection();
            if (!sel || !sel.rangeCount) {
                return;
            }
            const range = sel.getRangeAt(0);
            if (el.contains(range.startContainer) && el.contains(range.endContainer)) {
                state.contentRange = range.cloneRange();
            }
        }
    }

    function setActiveTarget(el) {
        if (!isEditableTarget(el)) {
            return;
        }
        state.activeTarget = el;
        if (el.tagName !== 'TEXTAREA') {
            state.textareaStart = null;
            state.textareaEnd = null;
        }
        captureSelectionState();
        positionTrigger();
    }

    function restoreSelectionForTarget() {
        const el = state.activeTarget;
        if (!el || !document.contains(el)) {
            return false;
        }

        el.focus({ preventScroll: true });

        if (el.tagName === 'TEXTAREA') {
            const start = typeof state.textareaStart === 'number' ? state.textareaStart : (el.value || '').length;
            const end = typeof state.textareaEnd === 'number' ? state.textareaEnd : start;
            try {
                el.setSelectionRange(start, end);
            } catch (err) {
                // Ignore selection errors for unsupported environments.
            }
            return true;
        }

        if (el.isContentEditable) {
            const sel = window.getSelection();
            if (!sel) {
                return true;
            }
            sel.removeAllRanges();
            if (state.contentRange && document.contains(state.contentRange.startContainer)) {
                try {
                    sel.addRange(state.contentRange.cloneRange());
                    return true;
                } catch (err) {
                    // Fall through to collapsed-at-end range.
                }
            }
            const range = document.createRange();
            range.selectNodeContents(el);
            range.collapse(false);
            sel.addRange(range);
            state.contentRange = range.cloneRange();
            return true;
        }

        return true;
    }

    function insertTextIntoContentEditable(el, text) {
        const sel = window.getSelection();
        if (!sel) {
            return;
        }

        let range = null;
        if (state.contentRange && document.contains(state.contentRange.startContainer)) {
            range = state.contentRange.cloneRange();
        } else if (sel.rangeCount > 0) {
            const current = sel.getRangeAt(0);
            if (el.contains(current.startContainer)) {
                range = current.cloneRange();
            }
        }

        if (!range) {
            range = document.createRange();
            range.selectNodeContents(el);
            range.collapse(false);
        }

        sel.removeAllRanges();
        sel.addRange(range);

        range.deleteContents();

        const normalized = String(text || '').replace(/\r\n?/g, '\n');
        const lines = normalized.split('\n');
        const fragment = document.createDocumentFragment();
        for (let i = 0; i < lines.length; i += 1) {
            fragment.appendChild(document.createTextNode(lines[i]));
            if (i < lines.length - 1) {
                fragment.appendChild(document.createElement('br'));
            }
        }

        const caretNode = document.createElement('span');
        caretNode.style.display = 'inline';
        caretNode.textContent = '';
        fragment.appendChild(caretNode);
        range.insertNode(fragment);

        const newRange = document.createRange();
        newRange.setStartAfter(caretNode);
        newRange.collapse(true);
        if (caretNode.parentNode) {
            caretNode.parentNode.removeChild(caretNode);
        }
        sel.removeAllRanges();
        sel.addRange(newRange);
        state.contentRange = newRange.cloneRange();
    }

    function insertContentAtCursor(content) {
        if (!state.activeTarget || !document.contains(state.activeTarget)) {
            setStatus('Click inside a textarea/editor first.', true);
            return;
        }

        if (!restoreSelectionForTarget()) {
            setStatus('Unable to restore the target cursor.', true);
            return;
        }

        const text = String(content || '');
        const el = state.activeTarget;

        if (el.tagName === 'TEXTAREA') {
            const start = typeof state.textareaStart === 'number' ? state.textareaStart : el.selectionStart;
            const end = typeof state.textareaEnd === 'number' ? state.textareaEnd : el.selectionEnd;
            const safeStart = typeof start === 'number' ? start : 0;
            const safeEnd = typeof end === 'number' ? end : safeStart;
            const original = String(el.value || '');
            el.value = original.slice(0, safeStart) + text + original.slice(safeEnd);
            const caret = safeStart + text.length;
            try {
                el.setSelectionRange(caret, caret);
            } catch (err) {
                // Ignore unsupported selection behavior.
            }
            state.textareaStart = caret;
            state.textareaEnd = caret;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
            setStatus('Clipboard content pasted.', false);
            return;
        }

        if (el.isContentEditable) {
            insertTextIntoContentEditable(el, text);
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
            setStatus('Clipboard content pasted.', false);
        }
    }

    function clearEditor() {
        state.mode = 'add';
        state.editId = null;
        state.dom.editor.style.display = 'none';
        state.dom.titleInput.value = '';
        state.dom.contentInput.value = '';
        state.dom.saveBtn.textContent = 'Save';
        state.dom.cancelBtn.style.display = 'none';
    }

    function openEditor(mode, item) {
        state.mode = mode === 'edit' ? 'edit' : 'add';
        state.editId = state.mode === 'edit' && item ? Number(item.id) : null;
        state.dom.editor.style.display = 'flex';
        state.dom.saveBtn.textContent = state.mode === 'edit' ? 'Update' : 'Save';
        state.dom.cancelBtn.style.display = 'inline-block';
        if (state.mode === 'edit' && item) {
            state.dom.titleInput.value = item.title || '';
            state.dom.contentInput.value = item.content_text || '';
        } else {
            state.dom.titleInput.value = '';
            state.dom.contentInput.value = '';
        }
        state.dom.titleInput.focus();
    }

    async function requestList() {
        const url = getEndpointUrl() + '?action=list';
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        return response.json();
    }

    async function requestMutation(action, payload) {
        const formData = new FormData();
        formData.append('action', action);
        Object.keys(payload || {}).forEach(function (key) {
            formData.append(key, payload[key]);
        });
        const response = await fetch(getEndpointUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            body: formData
        });
        return response.json();
    }

    function createActionButton(label, className, onClick) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'vs-clip-btn' + (className ? ' ' + className : '');
        btn.textContent = label;
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            onClick();
        });
        return btn;
    }

    function renderList() {
        const listEl = state.dom.list;
        listEl.innerHTML = '';

        const query = normalizeSearchText(state.searchQuery);
        const queryParts = query ? query.split(' ').filter(Boolean) : [];
        const filteredItems = state.items.filter(function (item) {
            if (!queryParts.length) {
                return true;
            }
            const haystack = normalizeSearchText((item.title || '') + ' ' + (item.content_text || ''));
            for (let i = 0; i < queryParts.length; i += 1) {
                if (!haystack.includes(queryParts[i])) {
                    return false;
                }
            }
            return true;
        });

        if (!filteredItems.length) {
            state.dom.empty.style.display = 'block';
            state.dom.empty.textContent = queryParts.length
                ? 'No clipboard entries match your search.'
                : 'No clipboard entries yet. Use Add to create one.';
            return;
        }

        state.dom.empty.style.display = 'none';

        filteredItems.forEach(function (item) {
            const itemId = String(item.id || '');
            const isCollapsed = !!state.collapsedById[itemId];
            const row = document.createElement('div');
            row.className = 'vs-clip-row' + (isCollapsed ? ' is-collapsed' : '');

            const rowHeader = document.createElement('div');
            rowHeader.className = 'vs-clip-row-header';

            const titleWrap = document.createElement('div');
            titleWrap.className = 'vs-clip-header-main';
            const title = document.createElement('div');
            title.className = 'vs-clip-title';
            title.textContent = item.title || '(Untitled)';
            titleWrap.appendChild(title);

            const meta = document.createElement('div');
            meta.className = 'vs-clip-meta';
            const updatedText = formatDateTime(item.updated_at || '');
            const createdText = formatDateTime(item.created_at || '');
            if (updatedText) {
                meta.textContent = 'Updated: ' + updatedText;
            } else if (createdText) {
                meta.textContent = 'Created: ' + createdText;
            } else {
                meta.textContent = '';
            }
            titleWrap.appendChild(meta);
            titleWrap.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (!itemId) {
                    return;
                }
                state.collapsedById[itemId] = !state.collapsedById[itemId];
                renderList();
            });
            rowHeader.appendChild(titleWrap);

            const indicator = document.createElement('div');
            indicator.className = 'vs-clip-toggle-indicator';
            indicator.textContent = isCollapsed ? 'Show' : 'Hide';
            rowHeader.appendChild(indicator);
            row.appendChild(rowHeader);

            const contentEl = document.createElement('div');
            contentEl.className = 'vs-clip-content';
            contentEl.textContent = item.content_text || '';
            contentEl.title = 'Click this content to paste into current cursor field';
            contentEl.addEventListener('click', function () {
                insertContentAtCursor(item.content_text || '');
            });
            row.appendChild(contentEl);

            const actions = document.createElement('div');
            actions.className = 'vs-clip-actions';
            actions.appendChild(createActionButton('Paste', '', function () {
                insertContentAtCursor(item.content_text || '');
            }));
            actions.appendChild(createActionButton('Edit', '', function () {
                openEditor('edit', item);
            }));
            actions.appendChild(createActionButton('Delete', 'vs-clip-btn-danger', async function () {
                if (!confirm('Delete this clipboard entry?')) {
                    return;
                }
                setStatus('Deleting...', false);
                try {
                    const result = await requestMutation('delete', { id: item.id });
                    if (!result || !result.success) {
                        setStatus((result && result.message) || 'Failed to delete.', true);
                        return;
                    }
                    setStatus('Deleted.', false);
                    await loadItems();
                } catch (error) {
                    setStatus('Delete request failed.', true);
                }
            }));
            row.appendChild(actions);

            listEl.appendChild(row);
        });
    }

    async function loadItems() {
        setStatus('Loading clipboard entries...', false);
        try {
            const result = await requestList();
            if (!result || !result.success) {
                state.items = [];
                state.collapsedById = {};
                renderList();
                setStatus((result && result.message) || 'Failed to load clipboard entries.', true);
                return;
            }
            state.items = Array.isArray(result.items) ? result.items : [];
            const validIds = {};
            state.items.forEach(function (entry) {
                const id = String(entry.id || '');
                if (id) {
                    validIds[id] = true;
                    if (!(id in state.collapsedById)) {
                        state.collapsedById[id] = true;
                    }
                }
            });
            Object.keys(state.collapsedById).forEach(function (id) {
                if (!validIds[id]) {
                    delete state.collapsedById[id];
                }
            });
            renderList();
            setStatus('', false);
        } catch (error) {
            state.items = [];
            state.collapsedById = {};
            renderList();
            setStatus('Unable to connect to clipboard service.', true);
        }
    }

    function openPanel() {
        state.panelOpen = true;
        positionTrigger();
        state.dom.overlay.style.display = 'block';
        state.dom.panel.style.display = 'block';
        loadItems();
    }

    function closePanel() {
        state.panelOpen = false;
        state.dom.overlay.style.display = 'none';
        state.dom.panel.style.display = 'none';
        clearEditor();
        setStatus('', false);
        if (!document.activeElement || !isEditableTarget(document.activeElement)) {
            state.dom.trigger.style.display = 'none';
        } else {
            positionTrigger();
        }
    }

    function buildUi() {
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.id = 'vsInternalClipboardTrigger';
        trigger.title = 'Open internal clipboard';
        trigger.setAttribute('aria-label', 'Open internal clipboard');
        trigger.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 4h-2.2A2.8 2.8 0 0 0 11.2 2h-1.4A2.8 2.8 0 0 0 7.2 4H5a2 2 0 0 0-2 2v13a3 3 0 0 0 3 3h12a3 3 0 0 0 3-3V6a2 2 0 0 0-2-2Zm-8 0a1.2 1.2 0 0 1 1.2-1.2h1.4A1.2 1.2 0 0 1 11.8 4v.4H8V4Zm11 15a1.4 1.4 0 0 1-1.4 1.4H6.4A1.4 1.4 0 0 1 5 19V6h2.2v1.2h6.6V6H19v13Z" fill="currentColor"/></svg>';

        const overlay = document.createElement('div');
        overlay.id = 'vsInternalClipboardOverlay';

        const panel = document.createElement('div');
        panel.id = 'vsInternalClipboardPanel';
        panel.innerHTML = '' +
            '<div id="vsInternalClipboardPanelHeader">' +
                '<h3>Internal Clipboard</h3>' +
                '<div style="display:flex;gap:6px;">' +
                    '<button type="button" id="vsInternalClipboardAddBtn" class="vs-clip-btn">Add</button>' +
                    '<button type="button" id="vsInternalClipboardCloseBtn" class="vs-clip-btn">Close</button>' +
                '</div>' +
            '</div>' +
            '<div id="vsInternalClipboardPanelBody">' +
                '<div id="vsInternalClipboardStatus"></div>' +
                '<input id="vsInternalClipboardSearch" type="text" placeholder="Search title or content...">' +
                '<div id="vsInternalClipboardEditor">' +
                    '<label for="vsInternalClipboardTitle">Title</label>' +
                    '<input id="vsInternalClipboardTitle" type="text" maxlength="180" placeholder="Enter title">' +
                    '<label for="vsInternalClipboardContent">Clipboard Content</label>' +
                    '<textarea id="vsInternalClipboardContent" placeholder="Enter clipboard content"></textarea>' +
                    '<div style="display:flex;justify-content:flex-end;gap:6px;">' +
                        '<button type="button" id="vsInternalClipboardCancelBtn" class="vs-clip-btn">Cancel</button>' +
                        '<button type="button" id="vsInternalClipboardSaveBtn" class="vs-clip-btn">Save</button>' +
                    '</div>' +
                '</div>' +
                '<div id="vsInternalClipboardEmpty" style="display:none;">No clipboard entries yet. Use Add to create one.</div>' +
                '<div id="vsInternalClipboardList"></div>' +
            '</div>';

        document.body.appendChild(trigger);
        document.body.appendChild(overlay);
        document.body.appendChild(panel);

        state.dom = {
            trigger: trigger,
            overlay: overlay,
            panel: panel,
            list: panel.querySelector('#vsInternalClipboardList'),
            empty: panel.querySelector('#vsInternalClipboardEmpty'),
            status: panel.querySelector('#vsInternalClipboardStatus'),
            searchInput: panel.querySelector('#vsInternalClipboardSearch'),
            addBtn: panel.querySelector('#vsInternalClipboardAddBtn'),
            closeBtn: panel.querySelector('#vsInternalClipboardCloseBtn'),
            editor: panel.querySelector('#vsInternalClipboardEditor'),
            titleInput: panel.querySelector('#vsInternalClipboardTitle'),
            contentInput: panel.querySelector('#vsInternalClipboardContent'),
            saveBtn: panel.querySelector('#vsInternalClipboardSaveBtn'),
            cancelBtn: panel.querySelector('#vsInternalClipboardCancelBtn')
        };

        trigger.addEventListener('mousedown', function (event) {
            event.preventDefault();
            captureSelectionState();
        });
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            openPanel();
        });

        state.dom.addBtn.addEventListener('click', function () {
            openEditor('add');
        });

        state.dom.cancelBtn.addEventListener('click', function () {
            clearEditor();
        });

        state.dom.closeBtn.addEventListener('click', function () {
            closePanel();
        });

        state.dom.searchInput.addEventListener('input', function () {
            state.searchQuery = state.dom.searchInput.value || '';
            renderList();
        });

        overlay.addEventListener('click', function () {
            closePanel();
        });

        state.dom.saveBtn.addEventListener('click', async function () {
            const title = state.dom.titleInput.value.trim();
            const content = state.dom.contentInput.value.trim();

            if (!title || !content) {
                setStatus('Title and clipboard content are required.', true);
                return;
            }

            setStatus(state.mode === 'edit' ? 'Updating...' : 'Saving...', false);

            try {
                let result;
                if (state.mode === 'edit' && state.editId) {
                    result = await requestMutation('edit', {
                        id: state.editId,
                        title: title,
                        content: content
                    });
                } else {
                    result = await requestMutation('add', {
                        title: title,
                        content: content
                    });
                }

                if (!result || !result.success) {
                    setStatus((result && result.message) || 'Failed to save entry.', true);
                    return;
                }

                setStatus((result && result.message) || 'Saved.', false);
                clearEditor();
                await loadItems();
            } catch (error) {
                setStatus('Save request failed.', true);
            }
        });
    }

    function bindGlobalListeners() {
        document.addEventListener('focusin', function (event) {
            const target = findEditableTarget(event.target);
            if (!target || !isElementVisible(target)) {
                return;
            }
            setActiveTarget(target);
        });

        document.addEventListener('focusout', function (event) {
            const target = findEditableTarget(event.target);
            if (!target) {
                return;
            }
            if (state.hideTimer) {
                clearTimeout(state.hideTimer);
            }
            state.hideTimer = setTimeout(function () {
                const nextFocused = findEditableTarget(document.activeElement);
                if (!nextFocused && !state.panelOpen && state.dom.trigger) {
                    state.dom.trigger.style.display = 'none';
                }
            }, 160);
        });

        document.addEventListener('click', function (event) {
            const target = findEditableTarget(event.target);
            if (!target || !isElementVisible(target)) {
                return;
            }
            setActiveTarget(target);
            captureSelectionState();
        });

        document.addEventListener('keyup', function (event) {
            if (!state.activeTarget) {
                return;
            }
            if (event.target === state.activeTarget) {
                captureSelectionState();
                positionTrigger();
            }
        });

        document.addEventListener('mouseup', function (event) {
            const target = findEditableTarget(event.target);
            if (!target || target !== state.activeTarget) {
                return;
            }
            captureSelectionState();
        });

        document.addEventListener('selectionchange', function () {
            if (!state.activeTarget || !state.activeTarget.isContentEditable) {
                return;
            }
            captureSelectionState();
        });

        window.addEventListener('scroll', positionTrigger, true);
        window.addEventListener('resize', positionTrigger);
    }

    onReady(function () {
        buildUi();
        clearEditor();
        bindGlobalListeners();
    });
})();
</script>
