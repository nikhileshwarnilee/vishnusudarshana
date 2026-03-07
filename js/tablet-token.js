(function () {
    'use strict';

    var MAX_PHONE_LENGTH = 10;
    var COOLDOWN_SECONDS = 30;
    var EMPTY_PHONE_MASK = '__________';
    var BUTTON_PRESS_MS = 150;
    var PRINT_ANIMATION_MS = 3400;
    var TTS_CHUNK_LIMIT = 180;
    var AVAILABILITY_REFRESH_ACTIVE_MS = 10000;
    var AVAILABILITY_REFRESH_COOLDOWN_MS = 4000;
    var AVAILABILITY_REFRESH_HIDDEN_MS = 20000;
    var RAWBT_IMAGE_SCHEME_PREFIX = 'rawbt:data:image/jpeg;base64,';
    var RAWBT_TEXT_SCHEME_PREFIX = 'rawbt:';
    var RAWBT_RECEIPT_IMAGE_WIDTH_PX = 384;
    var RAWBT_RECEIPT_IMAGE_PADDING_PX = 18;
    var THERMAL_PRINT_IFRAME_ID = 'tablet-thermal-print-frame';
    var THERMAL_RECEIPT_WIDTH_MM = 58;

    var LOCATION_LABELS_MR = {
        solapur: '\u0938\u094b\u0932\u093e\u092a\u0942\u0930'
    };

    var app = document.querySelector('.tablet-app');
    if (!app) {
        return;
    }

    var location = (app.getAttribute('data-location') || 'solapur').trim().toLowerCase();
    var interactionShell = document.querySelector('.interaction-shell');
    var entrySection = document.getElementById('entrySection');
    var interactionTitle = document.querySelector('.interaction-title');
    var keypad = document.getElementById('keypad');
    var phoneDisplay = document.getElementById('phoneDisplay');
    var getTokenButton = document.getElementById('getTokenButton');
    var printerStage = document.getElementById('printerStage');
    var printingTicket = document.getElementById('printingTicket');
    var ticketProgressBlock = document.getElementById('ticketProgressBlock');
    var ticketDetailsBlock = document.getElementById('ticketDetailsBlock');
    var receiptTokenNumber = document.getElementById('receiptTokenNumber');
    var receiptPhoneNumber = document.getElementById('receiptPhoneNumber');
    var receiptLocationName = document.getElementById('receiptLocationName');
    var receiptDateValue = document.getElementById('receiptDateValue');
    var receiptTimeValue = document.getElementById('receiptTimeValue');
    var cooldownBlock = document.getElementById('cooldownBlock');
    var cooldownSeconds = document.getElementById('cooldownSeconds');
    var printingStatus = document.getElementById('printingStatus');
    var statusMessage = document.getElementById('statusMessage');
    var tokensFullNotice = document.getElementById('tokensFullNotice');
    var remainingTokensCard = document.getElementById('remainingTokensCard');
    var remainingTokensCount = document.getElementById('remainingTokensCount');
    var remainingTokensMeta = document.getElementById('remainingTokensMeta');

    if (!entrySection || !keypad || !phoneDisplay || !getTokenButton || !printerStage || !printingTicket) {
        return;
    }

    var defaultLocationLabel = toLocationLabel(location || (receiptLocationName ? receiptLocationName.textContent.trim() : ''));
    var phoneDigits = '';
    var requestInFlight = false;
    var tokensFullForToday = false;
    var cooldownEndsAt = 0;
    var cooldownIntervalId = null;
    var cooldownStorageKey = 'tablet_token_cooldown_until_' + location.replace(/\s+/g, '_');
    var receiptStorageKey = 'tablet_token_receipt_data_' + location.replace(/\s+/g, '_');
    var availabilityRefreshIntervalId = null;
    var availabilityRefreshInFlight = false;
    var announcementLanguage = 'marathi';
    var settingsRefreshIntervalId = null;

    var audioContext = null;
    var printerFallbackIntervalId = null;
    var currentTtsAudio = null;
    var tapAudio = createAudio(resolveAssetUrl('assets/sounds/tap.mp3'), 0.9);
    var printerAudio = createAudio(resolveAssetUrl('assets/sounds/printer.mp3'), 0.9);
    var beepAudio = createAudio(resolveAssetUrl('assets/sounds/beep.mp3'), 0.9);

    if (receiptLocationName) {
        receiptLocationName.textContent = defaultLocationLabel;
    }
    if (printerAudio) {
        printerAudio.loop = true;
    }

    function resolveAssetUrl(relativePath) {
        try {
            return new URL(relativePath, window.location.href).toString();
        } catch (error) {
            return relativePath;
        }
    }

    function createAudio(src, volume) {
        try {
            var audio = new Audio(src);
            audio.preload = 'auto';
            if (typeof volume === 'number') {
                audio.volume = volume;
            }
            audio.load();
            return audio;
        } catch (error) {
            return null;
        }
    }

    function ensureAudioContext() {
        if (!window.AudioContext && !window.webkitAudioContext) {
            return null;
        }
        if (!audioContext) {
            var AudioContextClass = window.AudioContext || window.webkitAudioContext;
            audioContext = new AudioContextClass();
        }
        return audioContext;
    }

    function unlockAudioPlayback() {
        var context = ensureAudioContext();
        if (!context || context.state !== 'suspended') {
            return;
        }
        try {
            context.resume();
        } catch (error) {
            // Ignore resume errors
        }
    }

    function playFallbackTone(options) {
        var context = ensureAudioContext();
        if (!context) {
            return;
        }

        var tone = options || {};
        var frequency = Number(tone.frequency) || 920;
        var durationMs = Number(tone.duration) || 140;
        var waveType = tone.type || 'sine';
        var volume = Number(tone.volume);
        if (!Number.isFinite(volume) || volume <= 0) {
            volume = 0.06;
        }

        try {
            var oscillator = context.createOscillator();
            var gain = context.createGain();
            var now = context.currentTime;
            var stopAt = now + (durationMs / 1000);

            oscillator.type = waveType;
            oscillator.frequency.setValueAtTime(frequency, now);
            gain.gain.setValueAtTime(volume, now);
            gain.gain.exponentialRampToValueAtTime(0.0001, stopAt);

            oscillator.connect(gain);
            gain.connect(context.destination);
            oscillator.start(now);
            oscillator.stop(stopAt);
        } catch (error) {
            // Ignore fallback tone errors
        }
    }

    function playSound(audio, fallbackTone) {
        unlockAudioPlayback();
        if (!audio) {
            playFallbackTone(fallbackTone);
            return;
        }
        try {
            audio.currentTime = 0;
            var playPromise = audio.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(function () {
                    playFallbackTone(fallbackTone);
                });
            }
        } catch (error) {
            playFallbackTone(fallbackTone);
        }
    }

    function startFallbackPrinterTone() {
        stopFallbackPrinterTone();
        printerFallbackIntervalId = window.setInterval(function () {
            playFallbackTone({
                frequency: 210,
                duration: 70,
                type: 'square',
                volume: 0.03
            });
        }, 120);
    }

    function stopFallbackPrinterTone() {
        if (printerFallbackIntervalId) {
            window.clearInterval(printerFallbackIntervalId);
            printerFallbackIntervalId = null;
        }
    }

    function startPrinterSound() {
        unlockAudioPlayback();
        if (!printerAudio) {
            startFallbackPrinterTone();
            return;
        }
        try {
            printerAudio.currentTime = 0;
            var playPromise = printerAudio.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(function () {
                    startFallbackPrinterTone();
                });
            }
        } catch (error) {
            startFallbackPrinterTone();
        }
    }

    function stopPrinterSound() {
        stopFallbackPrinterTone();
        if (!printerAudio) {
            return;
        }
        try {
            printerAudio.pause();
            printerAudio.currentTime = 0;
        } catch (error) {
            // Ignore stop errors
        }
    }

    function wait(ms) {
        return new Promise(function (resolve) {
            window.setTimeout(resolve, ms);
        });
    }

    function setStatus(message, type) {
        statusMessage.textContent = message || '';
        statusMessage.classList.remove('status-info', 'status-error', 'status-success');
        if (type) {
            statusMessage.classList.add('status-' + type);
        }
    }

    function setInteractionTitleVisible(isVisible) {
        if (!interactionTitle) {
            return;
        }
        interactionTitle.classList.toggle('hidden', !isVisible);
    }

    function setPhoneDisplayVisible(isVisible) {
        if (!phoneDisplay) {
            return;
        }
        phoneDisplay.classList.toggle('hidden', !isVisible);
    }

    function setPrintLayoutActive(isActive) {
        if (!interactionShell) {
            return;
        }
        interactionShell.classList.toggle('print-active', Boolean(isActive));
    }

    function setEntrySectionVisible(isVisible) {
        if (!entrySection) {
            return;
        }
        entrySection.classList.toggle('hidden', !isVisible);
    }

    function setTokensFullNoticeVisible(isVisible) {
        if (!tokensFullNotice) {
            return;
        }
        tokensFullNotice.classList.toggle('hidden', !isVisible);
    }

    function updateRemainingTokensDisplay(remaining, total, metaMessage) {
        if (!remainingTokensCard || !remainingTokensCount || !remainingTokensMeta) {
            return;
        }

        var parsedRemaining = Number(remaining);
        var hasRemaining = Number.isFinite(parsedRemaining) && parsedRemaining >= 0;
        var parsedTotal = Number(total);
        var hasTotal = Number.isFinite(parsedTotal) && parsedTotal >= 0;

        remainingTokensCard.classList.remove('remaining-low', 'remaining-full');

        if (hasRemaining) {
            remainingTokensCount.textContent = toMarathiDigits(parsedRemaining);
            if (parsedRemaining <= 0) {
                remainingTokensCard.classList.add('remaining-full');
            } else if (parsedRemaining <= 10) {
                remainingTokensCard.classList.add('remaining-low');
            }
        } else {
            remainingTokensCount.textContent = '--';
        }

        if (metaMessage) {
            remainingTokensMeta.textContent = metaMessage;
            return;
        }

        if (hasRemaining && hasTotal) {
            remainingTokensMeta.textContent = '\u090f\u0915\u0942\u0923 ' + toMarathiDigits(parsedTotal) + ' \u092a\u0948\u0915\u0940 ' + toMarathiDigits(parsedRemaining) + ' \u0909\u092a\u0932\u092c\u094d\u0927';
            return;
        }

        if (hasRemaining) {
            remainingTokensMeta.textContent = '\u0909\u092a\u0932\u092c\u094d\u0927 \u091f\u094b\u0915\u0928: ' + toMarathiDigits(parsedRemaining);
            return;
        }

        remainingTokensMeta.textContent = '\u0938\u094d\u0925\u093f\u0924\u0940 \u0905\u092a\u0921\u0947\u091f \u0939\u094b\u0924 \u0906\u0939\u0947...';
    }

    function getTodayDateIsoString() {
        var now = new Date();
        var year = String(now.getFullYear());
        var month = String(now.getMonth() + 1).padStart(2, '0');
        var day = String(now.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    async function fetchTodayAvailability() {
        var date = getTodayDateIsoString();
        var url = 'book-token-availability.php?date=' + encodeURIComponent(date) + '&location=' + encodeURIComponent(location);

        try {
            var response = await fetch(url, { cache: 'no-store' });
            var data = await response.json();
            if (!data || !data.success || !data.data) {
                updateRemainingTokensDisplay(null, null, '\u0906\u091c\u091a\u094d\u092f\u093e \u0926\u093f\u0935\u0938\u093e\u0938\u093e\u0920\u0940 \u0938\u094d\u0932\u0949\u091f \u092e\u093e\u0939\u093f\u0924\u0940 \u0909\u092a\u0932\u092c\u094d\u0927 \u0928\u093e\u0939\u0940.');
                return null;
            }

            var remaining = Number(data.data.unbooked_tokens);
            var total = Number(data.data.total_tokens);
            if (!Number.isFinite(remaining)) {
                updateRemainingTokensDisplay(null, null, '\u0906\u091c\u091a\u094d\u092f\u093e \u0926\u093f\u0935\u0938\u093e\u091a\u0940 \u092e\u093e\u0939\u093f\u0924\u0940 \u0905\u092a\u0921\u0947\u091f \u0939\u094b\u0924 \u0906\u0939\u0947.');
                return null;
            }

            updateRemainingTokensDisplay(remaining, total);
            tokensFullForToday = remaining <= 0;
            return {
                remaining: remaining,
                total: Number.isFinite(total) ? total : null
            };
        } catch (error) {
            updateRemainingTokensDisplay(null, null, '\u091f\u094b\u0915\u0928 \u0938\u094d\u0925\u093f\u0924\u0940 \u092e\u093f\u0933\u0924 \u0928\u093e\u0939\u0940. \u0915\u0943\u092a\u092f\u093e \u0925\u094b\u0921\u094d\u092f\u093e \u0935\u0947\u0933\u093e\u0928\u0947 \u092a\u093e\u0939\u093e.');
            return null;
        }
    }

    async function loadAnnouncementLanguage() {
        try {
            var response = await fetch('api/get-settings.php', { cache: 'no-store' });
            var data = await response.json();
            if (!data || !data.success) {
                return;
            }
            var language = String(data.announcement_language || 'marathi').toLowerCase();
            announcementLanguage = language === 'english' ? 'english' : 'marathi';
        } catch (error) {
            // Keep default language when settings are unavailable.
        }
    }

    function startSettingsRefreshLoop() {
        if (settingsRefreshIntervalId) {
            window.clearInterval(settingsRefreshIntervalId);
        }
        settingsRefreshIntervalId = window.setInterval(loadAnnouncementLanguage, 30000);
    }

    function applyTokensFullState() {
        tokensFullForToday = true;
        setPrintLayoutActive(false);
        showPrinterStage(false);
        hideKeypadAndActionButtons();
        setEntrySectionVisible(false);
        setPhoneDisplayVisible(false);
        setInteractionTitleVisible(false);
        setTokensFullNoticeVisible(true);
        setStatus('', null);
    }

    function clearTokensFullStateForInput() {
        tokensFullForToday = false;
        setTokensFullNoticeVisible(false);
        setEntrySectionVisible(true);
        setPhoneDisplayVisible(true);
        setInteractionTitleVisible(true);
        keypad.classList.remove('hidden');
        updateGetTokenButton();
    }

    function startAvailabilityRefreshLoop() {
        function applyAvailabilityState(availability) {
            if (!availability) {
                return;
            }
            if (availability.remaining <= 0 && !isCooldownActive() && !requestInFlight) {
                applyTokensFullState();
                return;
            }
            if (availability.remaining > 0 && !isCooldownActive() && !requestInFlight && tokensFullForToday) {
                clearTokensFullStateForInput();
            }
        }

        function getAvailabilityRefreshDelay() {
            if (document.hidden) {
                return AVAILABILITY_REFRESH_HIDDEN_MS;
            }
            if (isCooldownActive() || requestInFlight) {
                return AVAILABILITY_REFRESH_COOLDOWN_MS;
            }
            return AVAILABILITY_REFRESH_ACTIVE_MS;
        }

        function scheduleNextAvailabilityRefresh() {
            if (availabilityRefreshIntervalId) {
                window.clearTimeout(availabilityRefreshIntervalId);
            }
            availabilityRefreshIntervalId = window.setTimeout(runAvailabilityRefresh, getAvailabilityRefreshDelay());
        }

        function refreshAvailabilityNow() {
            if (availabilityRefreshInFlight) {
                return Promise.resolve(null);
            }
            availabilityRefreshInFlight = true;
            return fetchTodayAvailability().then(function (availability) {
                applyAvailabilityState(availability);
                return availability;
            }).finally(function () {
                availabilityRefreshInFlight = false;
            });
        }

        function runAvailabilityRefresh() {
            refreshAvailabilityNow().finally(scheduleNextAvailabilityRefresh);
        }

        window.refreshTabletAvailabilityNow = function () {
            return refreshAvailabilityNow();
        };
        window.rescheduleTabletAvailabilityRefresh = function () {
            scheduleNextAvailabilityRefresh();
        };

        runAvailabilityRefresh();
    }

    function toMarathiDigits(value) {
        return String(value).replace(/[0-9]/g, function (digit) {
            return String.fromCharCode(0x0966 + Number(digit));
        });
    }

    function toAsciiDigits(value) {
        return String(value || '').replace(/[\u0966-\u096f]/g, function (digit) {
            return String(digit.charCodeAt(0) - 0x0966);
        });
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function isAndroidDevice() {
        return /android/i.test(String(window.navigator.userAgent || ''));
    }

    function buildUnifiedReceiptModel(details) {
        var serviceTimeText = String(details.serviceTime || '').trim();
        var rows = [
            { label: '\u092e\u094b\u092c\u093e\u0908\u0932', value: toAsciiDigits(details.phone) },
            { label: '\u0915\u0947\u0902\u0926\u094d\u0930', value: String(details.location || defaultLocationLabel || 'Solapur') },
            { label: '\u0926\u093f\u0928\u093e\u0902\u0915', value: toAsciiDigits(details.date) },
            { label: '\u0935\u0947\u0933', value: toAsciiDigits(details.time) }
        ];

        if (serviceTimeText) {
            rows.push({ label: '\u0938\u0947\u0935\u093e \u0935\u0947\u0933', value: serviceTimeText });
        }

        return {
            brand: 'VISHNUSUDARSHANA.COM',
            title: '\u092a\u094d\u0930\u0924\u094d\u092f\u0915\u094d\u0937 \u0915\u093e\u0930\u094d\u092f\u093e\u0932\u092f \u092d\u0947\u091f \u091f\u094b\u0915\u0928 \u092a\u093e\u0935\u0924\u0940',
            tokenLabel: '\u0906\u092a\u0932\u0947 \u091f\u094b\u0915\u0928',
            tokenText: '#' + toAsciiDigits(details.token),
            rows: rows,
            notes: [
                '\u0915\u0943\u092a\u092f\u093e \u0939\u0940 \u092a\u093e\u0935\u0924\u0940 \u091c\u092a\u0942\u0928 \u0920\u0947\u0935\u093e.',
                '\u0915\u0943\u092a\u092f\u093e live \u0938\u094d\u0915\u094d\u0930\u0940\u0928\u0935\u0930 \u091a\u093e\u0932\u0942 \u091f\u094b\u0915\u0928 \u092a\u093e\u0939\u0924 \u0930\u0939\u093e.'
            ]
        };
    }

    function buildRawBtReceiptText(details) {
        var receipt = buildUnifiedReceiptModel(details);
        var lines = [receipt.brand, receipt.title, '----------------------', receipt.tokenLabel + ' : ' + receipt.tokenText, '----------------------'];

        receipt.rows.forEach(function (row) {
            lines.push(row.label + ' : ' + row.value);
        });

        lines.push('----------------------');
        receipt.notes.forEach(function (note) {
            lines.push(note);
        });
        lines.push('');
        lines.push('');
        lines.push('');

        return lines.join('\n');
    }

    function getRawBtCanvasHeight(receipt) {
        var baseHeight = 560;
        var rowsHeight = receipt.rows.length * 36;
        var notesHeight = receipt.notes.length * 30;
        return Math.max(640, baseHeight + rowsHeight + notesHeight);
    }

    function loadImageForRawBt(src) {
        return new Promise(function (resolve, reject) {
            var image = new Image();
            image.crossOrigin = 'anonymous';
            image.onload = function () {
                resolve(image);
            };
            image.onerror = function () {
                reject(new Error('Unable to load logo image for RawBT receipt'));
            };
            image.src = src;
        });
    }

    function renderRawBtReceiptImage(details) {
        return new Promise(function (resolve, reject) {
            var receipt = buildUnifiedReceiptModel(details);
            var canvas = document.createElement('canvas');
            var width = RAWBT_RECEIPT_IMAGE_WIDTH_PX;
            var height = getRawBtCanvasHeight(receipt);
            var padding = RAWBT_RECEIPT_IMAGE_PADDING_PX;
            var contentWidth = width - (padding * 2);
            var y = 0;

            canvas.width = width;
            canvas.height = height;

            var ctx = canvas.getContext('2d');
            if (!ctx) {
                reject(new Error('RawBT canvas context is unavailable'));
                return;
            }

            var logoUrl = resolveAssetUrl('assets/images/logo/logomain.png');

            function drawSeparator() {
                ctx.strokeStyle = '#000000';
                ctx.lineWidth = 1;
                ctx.setLineDash([5, 3]);
                ctx.beginPath();
                ctx.moveTo(padding, y);
                ctx.lineTo(width - padding, y);
                ctx.stroke();
                ctx.setLineDash([]);
                y += 18;
            }

            function drawLabelValue(label, value) {
                ctx.font = '700 20px "Noto Sans Devanagari", sans-serif';
                ctx.fillStyle = '#000000';
                ctx.textAlign = 'left';
                ctx.fillText(label, padding, y);
                ctx.textAlign = 'right';
                ctx.font = String(value).length > 18
                    ? '500 16px "Noto Sans Devanagari", sans-serif'
                    : '500 20px "Noto Sans Devanagari", sans-serif';
                ctx.fillText(value, width - padding, y);
                y += 34;
            }

            function finalize() {
                var dataUrl = canvas.toDataURL('image/jpeg', 0.9);
                var base64Index = dataUrl.indexOf('base64,');
                if (base64Index === -1) {
                    reject(new Error('Unexpected data URL format for RawBT receipt'));
                    return;
                }
                resolve(dataUrl.slice(base64Index + 7));
            }

            function drawBody(logoImage) {
                y = padding;
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, width, height);

                if (logoImage) {
                    var logoRatio = logoImage.naturalWidth > 0 ? (logoImage.naturalHeight / logoImage.naturalWidth) : 0.3;
                    var logoWidth = contentWidth;
                    var logoHeight = Math.max(42, Math.round(logoWidth * logoRatio));
                    ctx.drawImage(logoImage, padding, y, logoWidth, logoHeight);
                    y += logoHeight + 10;
                } else {
                    ctx.fillStyle = '#000000';
                    ctx.textAlign = 'center';
                    ctx.font = '700 24px monospace';
                    ctx.fillText(receipt.brand, width / 2, y + 24);
                    y += 44;
                }

                ctx.textAlign = 'center';
                ctx.fillStyle = '#000000';
                ctx.font = '700 21px "Noto Sans Devanagari", sans-serif';
                ctx.fillText(receipt.title, width / 2, y + 20);
                y += 36;

                drawSeparator();

                ctx.font = '700 20px "Noto Sans Devanagari", sans-serif';
                ctx.fillText(receipt.tokenLabel, width / 2, y + 20);
                y += 28;

                ctx.font = '700 66px monospace';
                ctx.fillText(receipt.tokenText, width / 2, y + 66);
                y += 84;

                drawSeparator();
                receipt.rows.forEach(function (row) {
                    drawLabelValue(row.label, row.value);
                });

                drawSeparator();
                ctx.textAlign = 'center';
                ctx.font = '500 19px "Noto Sans Devanagari", sans-serif';
                receipt.notes.forEach(function (note) {
                    ctx.fillText(note, width / 2, y + 20);
                    y += 30;
                });

                finalize();
            }

            loadImageForRawBt(logoUrl).then(function (logoImage) {
                drawBody(logoImage);
            }).catch(function () {
                drawBody(null);
            });
        });
    }

    function tryNavigateToRawBt(rawBtUrl) {
        try {
            window.location.href = rawBtUrl;
            return true;
        } catch (error) {
            return false;
        }
    }

    function printViaRawBt(details) {
        return renderRawBtReceiptImage(details).then(function (imageBase64) {
            var imageUrl = RAWBT_IMAGE_SCHEME_PREFIX + imageBase64;
            if (tryNavigateToRawBt(imageUrl)) {
                return true;
            }

            var receiptText = buildRawBtReceiptText(details);
            var textUrl = RAWBT_TEXT_SCHEME_PREFIX + encodeURIComponent(receiptText);
            return tryNavigateToRawBt(textUrl);
        }).catch(function () {
            var fallbackText = buildRawBtReceiptText(details);
            var fallbackUrl = RAWBT_TEXT_SCHEME_PREFIX + encodeURIComponent(fallbackText);
            return tryNavigateToRawBt(fallbackUrl);
        });
    }

    function getThermalPrintFrame() {
        var frame = document.getElementById(THERMAL_PRINT_IFRAME_ID);
        if (frame) {
            return frame;
        }

        frame = document.createElement('iframe');
        frame.id = THERMAL_PRINT_IFRAME_ID;
        frame.setAttribute('aria-hidden', 'true');
        frame.style.position = 'fixed';
        frame.style.width = '0';
        frame.style.height = '0';
        frame.style.border = '0';
        frame.style.opacity = '0';
        frame.style.pointerEvents = 'none';
        frame.style.right = '0';
        frame.style.bottom = '0';
        document.body.appendChild(frame);

        return frame;
    }

    function buildThermalReceiptHtml(details) {
        var receipt = buildUnifiedReceiptModel(details);
        var logoUrl = resolveAssetUrl('assets/images/logo/logomain.png');
        var rowsHtml = receipt.rows.map(function (row) {
            return '<div class="row"><span class="label">' + escapeHtml(row.label) + '</span><span>' + escapeHtml(row.value) + '</span></div>';
        }).join('');
        var notesHtml = receipt.notes.map(function (note) {
            return '<div class="note center">' + escapeHtml(note) + '</div>';
        }).join('');

        return '<!DOCTYPE html>' +
            '<html><head><meta charset="UTF-8">' +
            '<meta name="viewport" content="width=device-width, initial-scale=1">' +
            '<title>Token Receipt</title>' +
            '<style>' +
            '@page{size:' + THERMAL_RECEIPT_WIDTH_MM + 'mm auto;margin:0;}' +
            'html,body{margin:0;padding:0;width:' + THERMAL_RECEIPT_WIDTH_MM + 'mm;background:#fff;color:#000;}' +
            'body{font-family:"Noto Sans Devanagari",sans-serif;font-size:12px;line-height:1.35;padding:4mm 3mm;box-sizing:border-box;}' +
            '.center{text-align:center;}' +
            '.logo{display:block;width:100%;height:auto;margin:0 auto 1.5mm auto;}' +
            '.brand{font-weight:700;font-size:13px;letter-spacing:0.2px;}' +
            '.title{font-weight:700;margin-top:2mm;font-size:12px;}' +
            '.line{border-top:1px dashed #000;margin:2mm 0;}' +
            '.token{font-size:26px;font-weight:700;line-height:1.05;margin:1mm 0 2mm 0;}' +
            '.row{display:flex;justify-content:space-between;gap:4px;white-space:nowrap;}' +
            '.row .label{font-weight:700;}' +
            '.note{margin-top:2mm;font-size:11px;}' +
            '</style></head><body>' +
            '<img class="logo" src="' + escapeHtml(logoUrl) + '" alt="Vishnusudarshana Logo">' +
            '<div class="center brand">' + escapeHtml(receipt.brand) + '</div>' +
            '<div class="center title">' + escapeHtml(receipt.title) + '</div>' +
            '<div class="line"></div>' +
            '<div class="center">' + escapeHtml(receipt.tokenLabel) + '</div>' +
            '<div class="center token">' + escapeHtml(receipt.tokenText) + '</div>' +
            '<div class="line"></div>' +
            rowsHtml +
            '<div class="line"></div>' +
            notesHtml +
            '</body></html>';
    }

    function printThermalReceipt(details) {
        if (isAndroidDevice()) {
            printViaRawBt(details);
            return;
        }

        try {
            var frame = getThermalPrintFrame();
            if (!frame || !frame.contentWindow || !frame.contentWindow.document) {
                return;
            }

            var frameDoc = frame.contentWindow.document;
            frameDoc.open();
            frameDoc.write(buildThermalReceiptHtml(details));
            frameDoc.close();

            window.setTimeout(function () {
                try {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                } catch (error) {
                    // Ignore print errors; on-screen receipt remains visible.
                }
            }, 120);
        } catch (error) {
            // Ignore print setup errors; on-screen receipt remains visible.
        }
    }

    function buildTtsChunks(text) {
        var normalized = String(text || '').replace(/\s+/g, ' ').trim();
        if (!normalized) {
            return [];
        }
        if (normalized.length <= TTS_CHUNK_LIMIT) {
            return [normalized];
        }

        var parts = normalized.split(/([.!?\u0964])/).map(function (part) {
            return part.trim();
        }).filter(Boolean);

        var chunks = [];
        var buffer = '';

        function flushBuffer() {
            if (buffer) {
                chunks.push(buffer.trim());
                buffer = '';
            }
        }

        parts.forEach(function (part) {
            var candidate = buffer ? (buffer + ' ' + part).trim() : part;
            if (candidate.length <= TTS_CHUNK_LIMIT) {
                buffer = candidate;
                return;
            }
            flushBuffer();
            if (part.length <= TTS_CHUNK_LIMIT) {
                buffer = part;
                return;
            }

            var words = part.split(/\s+/).filter(Boolean);
            words.forEach(function (word) {
                var next = buffer ? (buffer + ' ' + word).trim() : word;
                if (next.length <= TTS_CHUNK_LIMIT) {
                    buffer = next;
                } else {
                    flushBuffer();
                    buffer = word;
                }
            });
        });

        flushBuffer();
        return chunks;
    }

    function stopExternalTts() {
        if (!currentTtsAudio) {
            return;
        }
        try {
            currentTtsAudio.pause();
            currentTtsAudio.currentTime = 0;
            currentTtsAudio.src = '';
        } catch (error) {
            // Ignore audio stop errors
        }
        currentTtsAudio = null;
    }

    function playExternalTts(text, langCode) {
        var chunks = buildTtsChunks(text);
        if (!chunks.length) {
            return Promise.resolve();
        }

        stopExternalTts();
        unlockAudioPlayback();
        var language = String(langCode || 'en').toLowerCase();

        return new Promise(function (resolve, reject) {
            var index = 0;

            function playNextChunk() {
                if (index >= chunks.length) {
                    currentTtsAudio = null;
                    resolve();
                    return;
                }

                var chunk = chunks[index];
                var ttsUrl = 'https://translate.google.com/translate_tts?ie=UTF-8&tl=' + encodeURIComponent(language) + '&client=tw-ob&q=' + encodeURIComponent(chunk);
                var audio = new Audio(ttsUrl);
                audio.preload = 'auto';
                currentTtsAudio = audio;

                audio.onended = function () {
                    index += 1;
                    playNextChunk();
                };
                audio.onerror = function () {
                    currentTtsAudio = null;
                    reject(new Error('External TTS failed'));
                };

                var playPromise;
                try {
                    playPromise = audio.play();
                } catch (playError) {
                    currentTtsAudio = null;
                    reject(playError);
                    return;
                }

                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(function (error) {
                        currentTtsAudio = null;
                        reject(error);
                    });
                }
            }

            playNextChunk();
        });
    }

    function getPreferredVoice(voices, preferredLang, preferFemale) {
        if (!voices || !voices.length) {
            return null;
        }

        var femaleHints = ['female', 'woman', 'girl', 'sangeeta', 'kavya', 'swara', 'raveena', 'lekha'];
        var targetLang = String(preferredLang || 'en').toLowerCase();

        var languageVoices = voices.filter(function (voice) {
            var lang = String(voice.lang || '').toLowerCase();
            return lang === targetLang || lang.indexOf(targetLang) === 0;
        });

        if (preferFemale) {
            var femaleVoice = languageVoices.find(function (voice) {
                var name = String(voice.name || '').toLowerCase();
                return femaleHints.some(function (hint) {
                    return name.indexOf(hint) !== -1;
                });
            });
            if (femaleVoice) {
                return femaleVoice;
            }
        }
        if (languageVoices.length) {
            return languageVoices[0];
        }

        if (preferFemale) {
            var anyFemale = voices.find(function (voice) {
                var name = String(voice.name || '').toLowerCase();
                return femaleHints.some(function (hint) {
                    return name.indexOf(hint) !== -1;
                });
            });
            if (anyFemale) {
                return anyFemale;
            }
        }

        return voices[0];
    }

    function speakTextUsingBrowser(text, options) {
        if (!('speechSynthesis' in window)) {
            return;
        }
        var opts = options || {};
        var language = opts.lang || 'en-IN';
        var shouldCancelQueue = Boolean(opts.cancel);
        var preferFemale = opts.preferFemale !== false;

        try {
            var synthesis = window.speechSynthesis;
            if (shouldCancelQueue) {
                synthesis.cancel();
            }

            var speech = new SpeechSynthesisUtterance(text);
            speech.lang = language;
            speech.rate = 0.9;
            speech.pitch = 1.08;

            var voices = synthesis.getVoices();
            var preferredVoice = getPreferredVoice(voices, language.toLowerCase(), preferFemale);
            if (preferredVoice) {
                speech.voice = preferredVoice;
            }

            if (voices.length === 0) {
                window.setTimeout(function () {
                    try {
                        synthesis.speak(speech);
                    } catch (speakError) {
                        // Ignore delayed speech errors
                    }
                }, 120);
                return;
            }

            synthesis.speak(speech);
        } catch (error) {
            // Ignore speech errors
        }
    }

    function speakText(text, options) {
        var opts = options || {};
        var language = String(opts.lang || 'en').toLowerCase();
        var shouldCancelQueue = Boolean(opts.cancel);
        var preferFemale = opts.preferFemale !== false;

        if (shouldCancelQueue) {
            stopExternalTts();
            if ('speechSynthesis' in window) {
                try {
                    window.speechSynthesis.cancel();
                } catch (error) {
                    // Ignore queue reset errors
                }
            }
        }

        playExternalTts(text, language).catch(function () {
            speakTextUsingBrowser(text, {
                lang: language === 'en' ? 'en-IN' : language,
                cancel: false,
                preferFemale: preferFemale
            });
        });
    }

    function announcePrintingStarted() {
        if (window.TokenAnnouncement && typeof window.TokenAnnouncement.announcePrintingStart === 'function') {
            window.TokenAnnouncement.announcePrintingStart(announcementLanguage, {
                cancelQueue: true
            });
            return;
        }

        // Fallback if helper script is unavailable.
        var fallbackMessage = announcementLanguage === 'english'
            ? 'Please wait. Your token is being generated.'
            : '\u0915\u0943\u092a\u092f\u093e \u0925\u093e\u0902\u092c\u093e. \u0906\u092a\u0932\u0947 \u091f\u094b\u0915\u0928 \u0924\u092f\u093e\u0930 \u0939\u094b\u0924 \u0906\u0939\u0947.';
        speakTextUsingBrowser(fallbackMessage, {
            lang: announcementLanguage === 'english' ? 'en-IN' : 'mr-IN',
            cancel: true,
            preferFemale: true
        });
    }

    function announceToken(token) {
        if (window.TokenAnnouncement && typeof window.TokenAnnouncement.announceToken === 'function') {
            window.TokenAnnouncement.announceToken(token, announcementLanguage);
            return;
        }

        // Fallback if helper script is unavailable.
        var fallbackMessage = announcementLanguage === 'english'
            ? ('Thank you. Your token number is ' + String(token) + '. Please watch the screen for your turn.')
            : ('\u0927\u0928\u094d\u092f\u0935\u093e\u0926. \u0906\u092a\u0932\u0947 \u091f\u094b\u0915\u0928 \u0915\u094d\u0930\u092e\u093e\u0902\u0915 ' + String(token) + ' \u0906\u0939\u0947. \u0915\u0943\u092a\u092f\u093e \u0938\u094d\u0915\u094d\u0930\u0940\u0928\u0935\u0930 \u091a\u093e\u0932\u0942 \u091f\u094b\u0915\u0928 \u092a\u093e\u0939\u0924 \u0930\u0939\u093e.');
        speakTextUsingBrowser(fallbackMessage, {
            lang: announcementLanguage === 'english' ? 'en-IN' : 'mr-IN',
            cancel: true,
            preferFemale: true
        });
    }

    function normalizeLocationKey(value) {
        return String(value || '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    function toLocationLabel(rawValue) {
        var text = String(rawValue || '').trim();
        var normalizedKey = normalizeLocationKey(text);
        if (normalizedKey && Object.prototype.hasOwnProperty.call(LOCATION_LABELS_MR, normalizedKey)) {
            return LOCATION_LABELS_MR[normalizedKey];
        }
        if (!text) {
            return defaultLocationLabel || LOCATION_LABELS_MR.solapur || 'Solapur';
        }
        return text
            .split(/[-_\s]+/)
            .filter(Boolean)
            .map(function (part) {
                return part.charAt(0).toUpperCase() + part.slice(1).toLowerCase();
            })
            .join(' ');
    }

    function getFormattedDate(now) {
        var day = String(now.getDate()).padStart(2, '0');
        var month = String(now.getMonth() + 1).padStart(2, '0');
        var year = String(now.getFullYear());
        return toMarathiDigits(day + '-' + month + '-' + year);
    }

    function getFormattedTime(now) {
        var hours = now.getHours();
        var minutes = String(now.getMinutes()).padStart(2, '0');
        var suffix = hours >= 12 ? 'PM' : 'AM';
        var hour12 = hours % 12;
        if (hour12 === 0) {
            hour12 = 12;
        }
        return toMarathiDigits(String(hour12).padStart(2, '0') + ':' + minutes) + ' ' + suffix;
    }

    function isValidPhone(phone) {
        return /^\d{10}$/.test(phone);
    }

    function isCooldownActive() {
        return cooldownEndsAt > Date.now();
    }

    function clearCooldownInterval() {
        if (cooldownIntervalId) {
            window.clearInterval(cooldownIntervalId);
            cooldownIntervalId = null;
        }
    }

    function hideKeypadAndActionButtons() {
        keypad.classList.add('hidden');
        getTokenButton.classList.add('hidden');
        getTokenButton.disabled = true;
    }

    function showPrinterStage(isVisible) {
        printerStage.classList.toggle('hidden', !isVisible);
    }

    function resetPrintedTicketContent() {
        printingTicket.classList.remove('is-printing', 'is-printed');
        if (ticketProgressBlock) {
            ticketProgressBlock.classList.remove('hidden');
        }
        if (ticketDetailsBlock) {
            ticketDetailsBlock.classList.add('hidden');
        }
        if (receiptTokenNumber) {
            receiptTokenNumber.textContent = '#--';
        }
        if (receiptPhoneNumber) {
            receiptPhoneNumber.textContent = '--';
        }
        if (receiptLocationName) {
            receiptLocationName.textContent = defaultLocationLabel || '--';
        }
        if (receiptDateValue) {
            receiptDateValue.textContent = '--';
        }
        if (receiptTimeValue) {
            receiptTimeValue.textContent = '--';
        }
        if (printingStatus) {
            printingStatus.textContent = '\u0906\u092a\u0932\u0947 \u091f\u094b\u0915\u0928 \u091b\u093e\u092a\u0932\u0947 \u091c\u093e\u0924 \u0906\u0939\u0947...';
        }
        if (cooldownBlock) {
            cooldownBlock.classList.add('hidden');
        }
        if (cooldownSeconds) {
            cooldownSeconds.textContent = toMarathiDigits(COOLDOWN_SECONDS);
        }
    }

    function applyReceiptDetails(details) {
        if (ticketProgressBlock) {
            ticketProgressBlock.classList.add('hidden');
        }
        if (ticketDetailsBlock) {
            ticketDetailsBlock.classList.remove('hidden');
        }
        printingTicket.classList.add('is-printed');

        if (receiptTokenNumber) {
            receiptTokenNumber.textContent = '#' + toMarathiDigits(details.token);
        }
        if (receiptPhoneNumber) {
            receiptPhoneNumber.textContent = toMarathiDigits(details.phone);
        }
        if (receiptLocationName) {
            receiptLocationName.textContent = details.location;
        }
        if (receiptDateValue) {
            receiptDateValue.textContent = details.date;
        }
        if (receiptTimeValue) {
            receiptTimeValue.textContent = details.time;
        }
        if (printingStatus) {
            printingStatus.textContent = '\u092a\u093e\u0935\u0924\u0940 \u0924\u092f\u093e\u0930 \u0906\u0939\u0947. \u0915\u0943\u092a\u092f\u093e \u091f\u094b\u0915\u0928 \u0915\u094d\u0930\u092e\u093e\u0902\u0915 \u0935 \u092a\u093e\u0935\u0924\u0940 \u091c\u092a\u0942\u0928 \u0920\u0947\u0935\u093e.';
        }
    }

    async function animateButtonPress() {
        getTokenButton.classList.add('is-pressed');
        await wait(BUTTON_PRESS_MS);
        getTokenButton.classList.remove('is-pressed');
    }

    async function playPrinterAnimation() {
        showPrinterStage(true);
        printingTicket.classList.remove('is-printing', 'is-printed');
        void printingTicket.offsetWidth;
        printingTicket.classList.add('is-printing');
        startPrinterSound();
        announcePrintingStarted();
        await wait(PRINT_ANIMATION_MS);
        stopPrinterSound();
    }

    function buildMaskedDisplayValue() {
        if (phoneDigits.length === 0) {
            return EMPTY_PHONE_MASK;
        }
        return phoneDigits + EMPTY_PHONE_MASK.slice(phoneDigits.length);
    }

    function updatePhoneDisplay() {
        phoneDisplay.value = buildMaskedDisplayValue();
        phoneDisplay.classList.toggle('phone-complete', phoneDigits.length === MAX_PHONE_LENGTH);
    }

    function updateGetTokenButton() {
        var tokenButtonLabel = '\u091f\u094b\u0915\u0928 \u0918\u094d\u092f\u093e';
        if (tokensFullForToday || isCooldownActive() || requestInFlight) {
            getTokenButton.classList.add('hidden');
            getTokenButton.disabled = true;
            getTokenButton.textContent = tokenButtonLabel;
            return;
        }
        getTokenButton.classList.remove('hidden');
        getTokenButton.textContent = tokenButtonLabel;
        getTokenButton.disabled = !isValidPhone(phoneDigits);
    }

    function persistReceipt(details) {
        try {
            window.localStorage.setItem(receiptStorageKey, JSON.stringify(details));
        } catch (error) {
            // Ignore storage errors
        }
    }

    function readStoredReceipt() {
        try {
            var raw = window.localStorage.getItem(receiptStorageKey);
            if (!raw) {
                return null;
            }
            var parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') {
                return null;
            }
            return parsed;
        } catch (error) {
            return null;
        }
    }

    function clearStoredReceipt() {
        try {
            window.localStorage.removeItem(receiptStorageKey);
        } catch (error) {
            // Ignore storage errors
        }
    }

    function resetForNextVisitor() {
        phoneDigits = '';
        requestInFlight = false;
        stopExternalTts();
        if ('speechSynthesis' in window) {
            try {
                window.speechSynthesis.cancel();
            } catch (error) {
                // Ignore speech reset errors
            }
        }
        setPrintLayoutActive(false);
        updatePhoneDisplay();
        resetPrintedTicketContent();
        showPrinterStage(false);
        stopPrinterSound();

        if (tokensFullForToday) {
            applyTokensFullState();
            return;
        }

        clearTokensFullStateForInput();
        setStatus('', null);
    }

    function finishCooldown() {
        clearCooldownInterval();
        cooldownEndsAt = 0;
        try {
            window.localStorage.removeItem(cooldownStorageKey);
        } catch (error) {
            // Ignore storage errors
        }
        clearStoredReceipt();
        resetForNextVisitor();
        if (typeof window.refreshTabletAvailabilityNow === 'function') {
            window.refreshTabletAvailabilityNow();
        } else {
            fetchTodayAvailability();
        }
        if (typeof window.rescheduleTabletAvailabilityRefresh === 'function') {
            window.rescheduleTabletAvailabilityRefresh();
        }
    }

    function updateReceiptCooldown(remainingSeconds) {
        if (cooldownSeconds) {
            cooldownSeconds.textContent = toMarathiDigits(remainingSeconds);
        }
    }

    function tickCooldown() {
        var remaining = Math.max(0, Math.ceil((cooldownEndsAt - Date.now()) / 1000));
        updateReceiptCooldown(remaining);
        if (remaining <= 0) {
            finishCooldown();
        }
    }

    function startCooldown(seconds, persistToStorage) {
        var safeSeconds = Number(seconds);
        if (!Number.isFinite(safeSeconds) || safeSeconds <= 0) {
            safeSeconds = COOLDOWN_SECONDS;
        }
        safeSeconds = Math.ceil(safeSeconds);

        cooldownEndsAt = Date.now() + (safeSeconds * 1000);
        if (persistToStorage) {
            try {
                window.localStorage.setItem(cooldownStorageKey, String(cooldownEndsAt));
            } catch (error) {
                // Ignore storage errors
            }
        }

        hideKeypadAndActionButtons();
        showPrinterStage(true);
        setPrintLayoutActive(true);
        setTokensFullNoticeVisible(false);
        setEntrySectionVisible(true);
        setInteractionTitleVisible(false);
        setPhoneDisplayVisible(false);
        if (cooldownBlock) {
            cooldownBlock.classList.remove('hidden');
        }

        updateReceiptCooldown(safeSeconds);
        clearCooldownInterval();
        tickCooldown();
        cooldownIntervalId = window.setInterval(tickCooldown, 1000);
        if (typeof window.refreshTabletAvailabilityNow === 'function') {
            window.refreshTabletAvailabilityNow();
        } else {
            fetchTodayAvailability();
        }
        if (typeof window.rescheduleTabletAvailabilityRefresh === 'function') {
            window.rescheduleTabletAvailabilityRefresh();
        }
    }

    function restoreCooldown() {
        try {
            var savedCooldown = Number(window.localStorage.getItem(cooldownStorageKey));
            if (!Number.isFinite(savedCooldown) || savedCooldown <= Date.now()) {
                window.localStorage.removeItem(cooldownStorageKey);
                clearStoredReceipt();
                return;
            }

            cooldownEndsAt = savedCooldown;
            hideKeypadAndActionButtons();
            showPrinterStage(true);
            setPrintLayoutActive(true);
            setTokensFullNoticeVisible(false);
            setEntrySectionVisible(true);
            setInteractionTitleVisible(false);
            setPhoneDisplayVisible(false);

            if (cooldownBlock) {
                cooldownBlock.classList.remove('hidden');
            }

            var storedReceipt = readStoredReceipt();
            if (storedReceipt) {
                applyReceiptDetails(storedReceipt);
            } else {
                if (ticketProgressBlock) {
                    ticketProgressBlock.classList.add('hidden');
                }
                if (ticketDetailsBlock) {
                    ticketDetailsBlock.classList.remove('hidden');
                }
                printingTicket.classList.add('is-printed');
                if (printingStatus) {
                    printingStatus.textContent = '\u0915\u0943\u092a\u092f\u093e \u092a\u094d\u0930\u0924\u0940\u0915\u094d\u0937\u093e \u0915\u0930\u093e. \u092a\u0941\u0922\u0940\u0932 \u091f\u094b\u0915\u0928 \u0932\u0935\u0915\u0930\u091a \u0909\u092a\u0932\u092c\u094d\u0927 \u0939\u094b\u0908\u0932.';
                }
            }

            clearCooldownInterval();
            tickCooldown();
            cooldownIntervalId = window.setInterval(tickCooldown, 1000);
        } catch (error) {
            // Ignore restore errors
        }
    }

    function handleDigitPress(digit) {
        if (requestInFlight || tokensFullForToday || isCooldownActive()) {
            return;
        }
        if (!/^\d$/.test(digit)) {
            return;
        }
        if (phoneDigits.length >= MAX_PHONE_LENGTH) {
            return;
        }
        phoneDigits += digit;
        updatePhoneDisplay();
        updateGetTokenButton();
        if (statusMessage.classList.contains('status-error')) {
            setStatus('', null);
        }
    }

    function handleBackspacePress() {
        if (requestInFlight || tokensFullForToday || isCooldownActive()) {
            return;
        }
        if (phoneDigits.length === 0) {
            return;
        }
        phoneDigits = phoneDigits.slice(0, -1);
        updatePhoneDisplay();
        updateGetTokenButton();
        if (statusMessage.classList.contains('status-error')) {
            setStatus('', null);
        }
    }

    async function callGenerateTokenApi(phoneNumber) {
        var requestBody = new URLSearchParams();
        requestBody.set('location', location);
        requestBody.set('phone_number', phoneNumber);

        var response = await fetch('api/generate-tablet-token.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: requestBody.toString(),
            cache: 'no-store'
        });

        return response.json();
    }

    async function requestToken() {
        if (requestInFlight || tokensFullForToday || isCooldownActive()) {
            return;
        }
        if (!isValidPhone(phoneDigits)) {
            setStatus('\u0915\u0943\u092a\u092f\u093e \u092f\u094b\u0917\u094d\u092f \u0967\u0966 \u0905\u0902\u0915\u0940 \u092e\u094b\u092c\u093e\u0908\u0932 \u0928\u0902\u092c\u0930 \u091f\u093e\u0915\u093e.', 'error');
            return;
        }

        var submittedPhone = phoneDigits;
        requestInFlight = true;
        getTokenButton.disabled = true;
        setStatus('', null);
        playSound(tapAudio, {
            frequency: 860,
            duration: 90,
            type: 'square',
            volume: 0.06
        });

        var printAnimationPromise = Promise.resolve();

        try {
            var apiPromise = callGenerateTokenApi(submittedPhone);
            await animateButtonPress();
            hideKeypadAndActionButtons();
            setPrintLayoutActive(true);
            setTokensFullNoticeVisible(false);
            setEntrySectionVisible(true);
            setInteractionTitleVisible(false);
            setPhoneDisplayVisible(false);
            resetPrintedTicketContent();
            printAnimationPromise = playPrinterAnimation();

            var data = await apiPromise;
            await printAnimationPromise;

            if (data.success) {
                var generatedToken = data.token || data.token_no;
                var now = new Date();
                var details = {
                    token: generatedToken,
                    phone: submittedPhone,
                    location: toLocationLabel(data.location || location),
                    date: getFormattedDate(now),
                    time: getFormattedTime(now),
                    serviceTime: data.service_time || ''
                };

                applyReceiptDetails(details);
                persistReceipt(details);
                printThermalReceipt(details);
                var remainingAfterIssue = Number(data.remaining_tokens);
                var totalTokenCapacity = Number(data.total_tokens);
                if (Number.isFinite(remainingAfterIssue) && remainingAfterIssue >= 0) {
                    updateRemainingTokensDisplay(remainingAfterIssue, totalTokenCapacity);
                    if (remainingAfterIssue <= 0) {
                        tokensFullForToday = true;
                    }
                }
                if (typeof window.refreshTabletAvailabilityNow === 'function') {
                    window.refreshTabletAvailabilityNow();
                } else {
                    fetchTodayAvailability();
                }
                if (typeof window.rescheduleTabletAvailabilityRefresh === 'function') {
                    window.rescheduleTabletAvailabilityRefresh();
                }
                announceToken(generatedToken);
                startCooldown(Number(data.cooldown_seconds) || COOLDOWN_SECONDS, true);
                return;
            }

            if (data.cooldown) {
                setStatus('\u0915\u0943\u092a\u092f\u093e \u0925\u093e\u0902\u092c\u093e. \u092a\u0941\u0922\u0940\u0932 \u091f\u094b\u0915\u0928 \u0935\u0947\u0933\u0947\u0928\u0941\u0938\u093e\u0930 \u0909\u092a\u0932\u092c\u094d\u0927 \u0939\u094b\u0908\u0932.', 'info');
                startCooldown(Number(data.remaining_seconds) || COOLDOWN_SECONDS, true);
                return;
            }

            if (data.full) {
                updateRemainingTokensDisplay(0, null, '\u0906\u091c\u091a\u0947 \u0938\u0930\u094d\u0935 \u091f\u094b\u0915\u0928 \u0938\u0902\u092a\u0932\u0947 \u0906\u0939\u0947\u0924.');
                applyTokensFullState();
                return;
            }

            if (data.invalid_phone) {
                setStatus('\u0915\u0943\u092a\u092f\u093e \u092f\u094b\u0917\u094d\u092f \u0967\u0966 \u0905\u0902\u0915\u0940 \u092e\u094b\u092c\u093e\u0908\u0932 \u0928\u0902\u092c\u0930 \u091f\u093e\u0915\u093e.', 'error');
            } else {
                setStatus('\u0938\u0927\u094d\u092f\u093e \u091f\u094b\u0915\u0928 \u092e\u093f\u0933\u0924 \u0928\u093e\u0939\u0940. \u0915\u0943\u092a\u092f\u093e \u092a\u0941\u0928\u094d\u0939\u093e \u092a\u094d\u0930\u092f\u0924\u094d\u0928 \u0915\u0930\u093e.', 'error');
            }

            showPrinterStage(false);
            setPrintLayoutActive(false);
            setInteractionTitleVisible(true);
            setPhoneDisplayVisible(true);
            keypad.classList.remove('hidden');
        } catch (error) {
            await printAnimationPromise;
            showPrinterStage(false);
            setPrintLayoutActive(false);
            setInteractionTitleVisible(true);
            setPhoneDisplayVisible(true);
            keypad.classList.remove('hidden');
            stopPrinterSound();
            setStatus('\u0928\u0947\u091f\u0935\u0930\u094d\u0915 \u0938\u092e\u0938\u094d\u092f\u093e \u0906\u0939\u0947. \u0915\u0943\u092a\u092f\u093e \u092a\u0941\u0928\u094d\u0939\u093e \u092a\u094d\u0930\u092f\u0924\u094d\u0928 \u0915\u0930\u093e.', 'error');
        } finally {
            requestInFlight = false;
            if (!tokensFullForToday && !isCooldownActive()) {
                updateGetTokenButton();
            }
        }
    }

    entrySection.addEventListener('click', function (event) {
        unlockAudioPlayback();
        var targetButton = event.target.closest('button');
        if (!targetButton || !entrySection.contains(targetButton)) {
            return;
        }

        if (targetButton.id === 'getTokenButton') {
            requestToken();
            return;
        }

        if (targetButton.id === 'backspaceButton') {
            handleBackspacePress();
            return;
        }

        var digit = targetButton.getAttribute('data-digit');
        if (digit !== null) {
            handleDigitPress(digit);
        }
    });

    phoneDisplay.addEventListener('focus', function () {
        phoneDisplay.blur();
    });

    window.addEventListener('beforeunload', function () {
        stopExternalTts();
        if (availabilityRefreshIntervalId) {
            window.clearTimeout(availabilityRefreshIntervalId);
            availabilityRefreshIntervalId = null;
        }
        if (settingsRefreshIntervalId) {
            window.clearInterval(settingsRefreshIntervalId);
            settingsRefreshIntervalId = null;
        }
        if ('speechSynthesis' in window) {
            try {
                window.speechSynthesis.cancel();
            } catch (error) {
                // Ignore speech cleanup errors
            }
        }
    });

    if ('speechSynthesis' in window) {
        try {
            window.speechSynthesis.getVoices();
        } catch (error) {
            // Ignore voice priming errors
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            return;
        }
        if (typeof window.refreshTabletAvailabilityNow === 'function') {
            window.refreshTabletAvailabilityNow();
        } else {
            fetchTodayAvailability();
        }
        if (typeof window.rescheduleTabletAvailabilityRefresh === 'function') {
            window.rescheduleTabletAvailabilityRefresh();
        }
    });

    window.addEventListener('focus', function () {
        if (typeof window.refreshTabletAvailabilityNow === 'function') {
            window.refreshTabletAvailabilityNow();
        } else {
            fetchTodayAvailability();
        }
        if (typeof window.rescheduleTabletAvailabilityRefresh === 'function') {
            window.rescheduleTabletAvailabilityRefresh();
        }
    });

    resetPrintedTicketContent();
    updatePhoneDisplay();
    updateGetTokenButton();
    loadAnnouncementLanguage();
    startSettingsRefreshLoop();
    restoreCooldown();
    fetchTodayAvailability().then(function (availability) {
        if (!availability) {
            return;
        }
        if (availability.remaining <= 0 && !isCooldownActive()) {
            applyTokensFullState();
            return;
        }
        if (availability.remaining > 0 && !isCooldownActive()) {
            clearTokensFullStateForInput();
        }
    });
    startAvailabilityRefreshLoop();
})();
