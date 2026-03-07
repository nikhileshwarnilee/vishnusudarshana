<?php
// Token Management Page
// Add your token management logic here

include '../includes/top-menu.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Token Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/responsive-tables.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
        .token-mgmt-responsive { max-width: 1400px; margin: 0 auto; padding: 24px 12px; }
        h1 { color: #800000; margin-bottom: 18px; }
        .token-form-card { background: #fffbe6; border: 1px solid #f2d98c; border-radius: 18px; padding: 22px 12px; max-width: 680px; margin: 0 auto 18px auto; box-shadow: 0 4px 18px rgba(212,175,55,0.13); }
        .token-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 16px; }
        .form-label { display: block; margin-bottom: 0; font-weight: 600; color: #6b0000; }
        .form-actions { grid-column: 1 / -1; }
        .form-input, .form-select { width: 100%; margin-top: 6px; padding: 10px 12px; border-radius: 10px; border: 1px solid #f0d27a; font-size: 1em; background: #fff; }
        .form-btn { width: 100%; margin-top: 8px; font-size: 1.1em; border-radius: 10px; background: #800000; color: #fff; border: none; padding: 10px 12px; cursor: pointer; }
        .form-btn:hover { background: #600000; }
        .settings-card { background: #fffef8; border: 1px solid #edd9a2; border-radius: 14px; padding: 16px 14px; max-width: 680px; margin: 0 auto 12px auto; box-shadow: 0 4px 14px rgba(128, 0, 0, 0.08); }
        .settings-title { margin: 0 0 10px; color: #6b0000; font-size: 1.06em; font-weight: 700; }
        .language-options { display: flex; flex-wrap: wrap; gap: 12px 22px; margin-bottom: 12px; }
        .language-option { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: #5d2500; cursor: pointer; }
        .language-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .language-save-btn { padding: 8px 14px; border-radius: 9px; border: 0; background: #800000; color: #fff; font-weight: 700; cursor: pointer; }
        .language-save-btn:hover { background: #640000; }
        .language-msg { min-height: 1.3em; font-weight: 700; color: #6b0000; }
        table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 2px 12px #e0bebe22; border-radius: 12px; table-layout: auto; font-size: 0.85em; }
        table th, table td { padding: 8px 6px; text-align: left; border-bottom: 1px solid #f3caca; white-space: nowrap; }
        table thead { background: #f9eaea; color: #800000; font-size: 0.9em; font-weight: 600; }
        table tbody tr:hover { background: #f3f7fa; }
        .table-responsive-wrapper { display: block; width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px; }
        @media (max-width: 700px) {
            .token-mgmt-responsive { padding: 12px 8px; }
            .token-form-card { padding: 16px 10px; }
            .token-form-grid { grid-template-columns: 1fr; }
            table { font-size: 0.82em; }
            table th, table td { padding: 6px 4px; }
        }
        @media (max-width: 480px) {
            h1 { font-size: 1.2em; }
            table { font-size: 0.8em; }
            table th, table td { padding: 5px 4px; }
        }
    </style>
</head>
<body>
<div class="admin-container token-mgmt-responsive">
    <h1 style="text-align:center;">Token Management</h1>
    <form id="tokenForm" method="post" class="token-form-card token-form-grid">
        <label class="form-label">Date:
            <input type="date" name="token_date" class="form-input" required>
        </label>
        <label class="form-label">From Time:
            <input type="time" name="from_time" class="form-input" required>
        </label>
        <label class="form-label">To Time:
            <input type="time" name="to_time" class="form-input" required>
        </label>
        <label class="form-label">Total Number of Tokens:
            <input type="number" name="total_tokens" class="form-input" min="0" required>
        </label>
        <label class="form-label">Location:
            <select name="location" class="form-select" required>
                <option value="solapur" selected>Solapur</option>
                <option value="hyderabad">Hyderabad</option>
            </select>
        </label>
        <label class="form-label">Note:
            <textarea name="note" class="form-input" placeholder="Enter note (optional)" rows="3" style="resize:vertical;"></textarea>
        </label>
        <button type="submit" class="form-btn form-actions">Save</button>
    </form>
    <div id="saveMsg" style="margin-top:16px;font-weight:600;text-align:center;"></div>
    <section class="settings-card" aria-label="Announcement Settings">
        <h2 class="settings-title">Announcement Language</h2>
        <div class="language-options">
            <label class="language-option">
                <input type="radio" name="announcementLanguage" value="marathi" checked>
                Marathi
            </label>
            <label class="language-option">
                <input type="radio" name="announcementLanguage" value="english">
                English
            </label>
        </div>
        <div class="language-actions">
            <button type="button" id="saveLanguageBtn" class="language-save-btn">Save Language</button>
            <span id="languageSaveMsg" class="language-msg"></span>
        </div>
    </section>
    <section class="settings-card" aria-label="Tablet WhatsApp Settings">
        <h2 class="settings-title">Tablet Token WhatsApp</h2>
        <div class="language-options">
            <label class="language-option">
                <input type="radio" name="tabletTokenWhatsapp" value="1" checked>
                ON
            </label>
            <label class="language-option">
                <input type="radio" name="tabletTokenWhatsapp" value="0">
                OFF
            </label>
        </div>
        <div class="language-actions">
            <button type="button" id="saveTabletWhatsAppBtn" class="language-save-btn">Save Tablet WhatsApp Setting</button>
            <span id="tabletWhatsAppSaveMsg" class="language-msg"></span>
        </div>
    </section>
    <div style="max-width:900px;margin:18px auto 0 auto;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <label for="cityTableFilter" style="font-weight:600;color:#6b0000;">Filter by City:</label>
        <select id="cityTableFilter" class="form-select" style="min-width:180px;">
            <option value="">All Cities</option>
            <option value="solapur">Solapur</option>
            <option value="hyderabad">Hyderabad</option>
        </select>
    </div>
    <div id="tokensTableContainer" style="margin-top:18px;max-width:900px;margin-left:auto;margin-right:auto;"></div>
    <script>
    function getSelectedAnnouncementLanguage() {
        const selected = document.querySelector('input[name="announcementLanguage"]:checked');
        return selected ? selected.value : 'marathi';
    }

    function setSelectedAnnouncementLanguage(language) {
        const safeLanguage = language === 'english' ? 'english' : 'marathi';
        const radio = document.querySelector('input[name="announcementLanguage"][value="' + safeLanguage + '"]');
        if (radio) {
            radio.checked = true;
        }
    }

    function getSelectedTabletTokenWhatsappEnabled() {
        const selected = document.querySelector('input[name="tabletTokenWhatsapp"]:checked');
        return selected ? selected.value : '1';
    }

    function setSelectedTabletTokenWhatsappEnabled(enabled) {
        const safeValue = String(enabled) === '0' ? '0' : '1';
        const radio = document.querySelector('input[name="tabletTokenWhatsapp"][value="' + safeValue + '"]');
        if (radio) {
            radio.checked = true;
        }
    }

    function setLanguageMessage(message, isError) {
        const msg = document.getElementById('languageSaveMsg');
        if (!msg) return;
        msg.textContent = message || '';
        msg.style.color = isError ? '#a0001b' : '#176b1a';
    }

    function setTabletWhatsAppMessage(message, isError) {
        const msg = document.getElementById('tabletWhatsAppSaveMsg');
        if (!msg) return;
        msg.textContent = message || '';
        msg.style.color = isError ? '#a0001b' : '#176b1a';
    }

    function loadAnnouncementLanguage() {
        fetch('../../api/get-settings.php', { cache: 'no-store' })
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    setSelectedAnnouncementLanguage(data.announcement_language || 'marathi');
                    setSelectedTabletTokenWhatsappEnabled(data.tablet_token_whatsapp_enabled ? '1' : '0');
                } else {
                    setSelectedAnnouncementLanguage('marathi');
                    setSelectedTabletTokenWhatsappEnabled('1');
                }
            })
            .catch(() => {
                setSelectedAnnouncementLanguage('marathi');
                setSelectedTabletTokenWhatsappEnabled('1');
            });
    }

    function saveAnnouncementLanguage() {
        const language = getSelectedAnnouncementLanguage();
        const body = new URLSearchParams();
        body.set('announcement_language', language);

        setLanguageMessage('Saving...', false);
        fetch('../../api/save-settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                setLanguageMessage('Announcement language saved.', false);
            } else {
                setLanguageMessage('Failed to save language.', true);
            }
        })
        .catch(() => {
            setLanguageMessage('Error while saving language.', true);
        });
    }

    function saveTabletTokenWhatsAppSetting() {
        const enabledValue = getSelectedTabletTokenWhatsappEnabled();
        const body = new URLSearchParams();
        body.set('tablet_token_whatsapp_enabled', enabledValue);

        setTabletWhatsAppMessage('Saving...', false);
        fetch('../../api/save-settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: body.toString()
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                setSelectedTabletTokenWhatsappEnabled(data.tablet_token_whatsapp_enabled ? '1' : '0');
                setTabletWhatsAppMessage('Tablet token WhatsApp setting saved.', false);
            } else {
                setTabletWhatsAppMessage('Failed to save tablet WhatsApp setting.', true);
            }
        })
        .catch(() => {
            setTabletWhatsAppMessage('Error while saving tablet WhatsApp setting.', true);
        });
    }

    function fetchTokens() {
        fetch('fetch-tokens.php')
        .then(res => res.json())
        .then(data => {
            const tokens = data.tokens || [];
            const selectedCity = (document.getElementById('cityTableFilter')?.value || '').toLowerCase();
            const filteredTokens = selectedCity ? tokens.filter(t => String(t.location || '').toLowerCase() === selectedCity) : tokens;
            let html = '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">';
            html += '<h2 style="margin-bottom:0;">Saved Token Details</h2>';
            html += '<button id="deleteOldTokensBtn" style="padding:8px 18px;background:#c00;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:1em;">Delete Old Tokens</button>';
            html += '</div>';
            if (filteredTokens.length === 0) {
                html += '<div style="color:#c00;">No token details found.</div>';
            } else {
                html += '<div class="table-responsive-wrapper">';
                html += '<table>';
                html += '<thead><tr>';
                html += '<th>Date</th>';
                html += '<th>From Time</th>';
                html += '<th>To Time</th>';
                html += '<th>Tokens</th>';
                html += '<th>Remaining Tokens</th>';
                html += '<th>Appointment Time</th>';
                html += '<th>Location</th>';
                html += '<th>Note</th>';
                html += '<th>Created At</th>';
                html += '<th>Actions</th>';
                html += '</tr></thead><tbody>';
                filteredTokens.forEach(function(t) {
                    html += '<tr>';
                    html += '<td>'+t.token_date+'</td>';
                    html += '<td>'+t.from_time+'</td>';
                    html += '<td>'+t.to_time+'</td>';
                    html += '<td>'+t.total_tokens+'</td>';
                    html += '<td>'+t.unbooked_tokens+'</td>';
                    // Appointment Time column (calculated per token)
                    // Parse times as HH:MM:SS or HH:MM
                    function parseTimeToMinutes(str) {
                        if (!str) return null;
                        var parts = str.split(":");
                        if (parts.length < 2) return null;
                        var h = parseInt(parts[0], 10), m = parseInt(parts[1], 10);
                        if (isNaN(h) || isNaN(m)) return null;
                        return h * 60 + m;
                    }
                    var fromMins = parseTimeToMinutes(t.from_time);
                    var toMins = parseTimeToMinutes(t.to_time);
                    if (fromMins !== null && toMins !== null && t.total_tokens > 0 && toMins > fromMins) {
                        var diffMins = toMins - fromMins;
                        var perMins = Math.floor(diffMins / t.total_tokens);
                        var perHrs = Math.floor(perMins / 60);
                        var perRemMins = perMins % 60;
                        var perStr = (perHrs ? perHrs + 'h ' : '') + perRemMins + 'm';
                        html += '<td>' + perStr + '</td>';
                    } else {
                        html += '<td>-</td>';
                    }
                    html += '<td>'+t.location+'</td>';
                    html += '<td>'+(t.notes ? t.notes : '-')+'</td>';
                    html += '<td>'+t.created_at+'</td>';
                    html += '<td>';
                    html += '<button class="edit-btn" data-id="'+t.id+'" style="margin-right:6px;padding:6px 12px;background:#1a8917;color:#fff;border:none;border-radius:6px;cursor:pointer;">Edit</button>';
                    html += '<button class="delete-btn" data-id="'+t.id+'" style="padding:6px 12px;background:#c00;color:#fff;border:none;border-radius:6px;cursor:pointer;">Delete</button>';
                    html += '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
            }
            document.getElementById('tokensTableContainer').innerHTML = html;
            // Delete old tokens handler
            const deleteBtn = document.getElementById('deleteOldTokensBtn');
            if (deleteBtn) {
                deleteBtn.onclick = function() {
                    if (!confirm('Delete all tokens older than today? This cannot be undone.')) return;
                    fetch('delete-old-tokens.php', { method: 'POST' })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                fetchTokens();
                                document.getElementById('saveMsg').textContent = 'Old tokens deleted.';
                            } else {
                                document.getElementById('saveMsg').textContent = 'Failed to delete old tokens.';
                            }
                        })
                        .catch(() => {
                            document.getElementById('saveMsg').textContent = 'Error deleting old tokens.';
                        });
                };
            }
            // Disable already used dates in the date picker
            const dateInput = document.querySelector('input[name="token_date"]');
            const locationSelect = document.querySelector('select[name="location"]');
            function validateDateLocation(editId = null) {
                if (!dateInput || !locationSelect) return;
                const selectedDate = dateInput.value;
                const selectedLocation = locationSelect.value;
                const exists = tokens.some(t => t.token_date === selectedDate && t.location === selectedLocation && (!editId || t.id != editId));
                if (exists) {
                    dateInput.setCustomValidity('This date already has tokens for this location. Please select another date or location.');
                } else {
                    dateInput.setCustomValidity('');
                }
            }
            if (dateInput && locationSelect) {
                dateInput.addEventListener('input', function() { validateDateLocation(window.editTokenId || null); });
                locationSelect.addEventListener('change', function() { validateDateLocation(window.editTokenId || null); });
            }
            // Edit functionality
            document.querySelectorAll('.edit-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const token = tokens.find(t => t.id == id);
                    if (!token) return;
                    window.editTokenId = id;
                    dateInput.value = token.token_date;
                    document.querySelector('input[name="from_time"]').value = token.from_time;
                    document.querySelector('input[name="to_time"]').value = token.to_time;
                    document.querySelector('input[name="total_tokens"]').value = token.total_tokens;
                    locationSelect.value = token.location;
                    // Prefill notes field and make editable
                    document.querySelector('textarea[name="note"]').value = token.notes || '';
                    document.querySelector('textarea[name="note"]').readOnly = false;
                    // Only in edit mode: make date/location readonly/disabled
                    dateInput.readOnly = true;
                    locationSelect.disabled = true;
                    validateDateLocation(id);
                    document.querySelector('button[type="submit"]').textContent = 'Update';
                });
            });
            // Restore date/location to editable on new entry
            document.getElementById('tokenForm').addEventListener('reset', function() {
                dateInput.readOnly = false;
                locationSelect.disabled = false;
                document.querySelector('textarea[name="note"]').readOnly = false;
                document.querySelector('textarea[name="note"]').value = '';
                document.querySelector('button[type="submit"]').textContent = 'Save';
            });
            // Delete functionality
            document.querySelectorAll('.delete-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    if (!confirm('Are you sure you want to delete this token entry?')) return;
                    fetch('delete-token.php', {
                        method: 'POST',
                        body: new URLSearchParams({id})
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            fetchTokens();
                            document.getElementById('saveMsg').textContent = 'Token entry deleted.';
                        } else {
                            document.getElementById('saveMsg').textContent = 'Failed to delete token entry.';
                        }
                    });
                });
            });
        });
    }
    document.getElementById('tokenForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = e.target;
        var formData = new FormData(form);
        var isEdit = !!window.editTokenId;
        if (isEdit) {
            formData.append('id', window.editTokenId);
            fetch('edit-token.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('saveMsg').textContent = 'Token details updated successfully!';
                    form.reset();
                    window.editTokenId = null;
                    document.querySelector('button[type="submit"]').textContent = 'Save';
                    fetchTokens();
                } else {
                    document.getElementById('saveMsg').textContent = 'Failed to update token details.';
                }
            })
            .catch(() => {
                document.getElementById('saveMsg').textContent = 'Error updating token details.';
            });
        } else {
            fetch('save-token.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('saveMsg').textContent = 'Token details saved successfully!';
                    form.reset();
                    fetchTokens();
                } else {
                    if (data.error) {
                        document.getElementById('saveMsg').textContent = data.error;
                    } else {
                        document.getElementById('saveMsg').textContent = 'Failed to save token details.';
                    }
                }
            })
            .catch(() => {
                document.getElementById('saveMsg').textContent = 'Error saving token details.';
            });
        }
    });
    // Booked Tokens field is not shown in add or edit mode
    window.addEventListener('DOMContentLoaded', function() {
        loadAnnouncementLanguage();
        const saveLanguageBtn = document.getElementById('saveLanguageBtn');
        if (saveLanguageBtn) {
            saveLanguageBtn.addEventListener('click', saveAnnouncementLanguage);
        }
        const saveTabletWhatsAppBtn = document.getElementById('saveTabletWhatsAppBtn');
        if (saveTabletWhatsAppBtn) {
            saveTabletWhatsAppBtn.addEventListener('click', saveTabletTokenWhatsAppSetting);
        }
        fetchTokens();
        const cityFilter = document.getElementById('cityTableFilter');
        if (cityFilter) {
            cityFilter.addEventListener('change', fetchTokens);
        }
    });
    </script>
</div>
</body>
</html>
