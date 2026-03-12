(function () {
    'use strict';

    if (window.__vsDateFormatGlobalInitialized) {
        return;
    }
    window.__vsDateFormatGlobalInitialized = true;

    var MONTH_LOOKUP = {
        jan: 1,
        january: 1,
        feb: 2,
        february: 2,
        mar: 3,
        march: 3,
        apr: 4,
        april: 4,
        may: 5,
        jun: 6,
        june: 6,
        jul: 7,
        july: 7,
        aug: 8,
        august: 8,
        sep: 9,
        sept: 9,
        september: 9,
        oct: 10,
        october: 10,
        nov: 11,
        november: 11,
        dec: 12,
        december: 12
    };

    var SKIP_TAGS = {
        SCRIPT: true,
        STYLE: true,
        NOSCRIPT: true,
        TEXTAREA: true,
        INPUT: true,
        SELECT: true,
        OPTION: true,
        CODE: true,
        PRE: true
    };

    var MONTH_PATTERN = '(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t|tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)';
    var RX_ISO_DATE_TIME = /\b(19\d{2}|20\d{2})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?\b/g;
    var RX_NUMERIC_DMY = /\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})(?:\s+(\d{1,2}:\d{2}(?::\d{2})?\s*(?:[APap][Mm])?))?\b/g;
    var RX_DAY_MONTH_NAME = new RegExp('\\b(\\d{1,2})\\s+(' + MONTH_PATTERN + ')\\s+(\\d{4})(?:,?\\s*(\\d{1,2}:\\d{2}(?::\\d{2})?\\s*(?:[APap][Mm])?))?\\b', 'g');
    var RX_MONTH_NAME_DAY = new RegExp('\\b(' + MONTH_PATTERN + ')\\s+(\\d{1,2}),\\s*(\\d{4})(?:\\s+(\\d{1,2}:\\d{2}(?::\\d{2})?\\s*(?:[APap][Mm])?))?\\b', 'g');

    function pad2(value) {
        return String(value).padStart(2, '0');
    }

    function isValidDate(year, month, day) {
        var y = Number(year);
        var m = Number(month);
        var d = Number(day);
        if (!Number.isInteger(y) || !Number.isInteger(m) || !Number.isInteger(d)) {
            return false;
        }
        if (m < 1 || m > 12 || d < 1 || d > 31) {
            return false;
        }
        var test = new Date(Date.UTC(y, m - 1, d));
        return test.getUTCFullYear() === y && (test.getUTCMonth() + 1) === m && test.getUTCDate() === d;
    }

    function normalizeTime(rawTime) {
        var timeText = String(rawTime || '').trim();
        if (timeText === '') {
            return '';
        }
        return ' ' + timeText;
    }

    function toDmy(year, month, day) {
        return pad2(day) + ' ' + pad2(month) + ' ' + String(year);
    }

    function replaceIsoDateTime(text) {
        return text.replace(RX_ISO_DATE_TIME, function (match, year, month, day, hh, mm, ss) {
            if (!isValidDate(year, month, day)) {
                return match;
            }
            var formatted = toDmy(year, month, day);
            if (typeof hh === 'string' && typeof mm === 'string') {
                formatted += ' ' + pad2(hh) + ':' + pad2(mm);
                if (typeof ss === 'string' && ss !== '') {
                    formatted += ':' + pad2(ss);
                }
            }
            return formatted;
        });
    }

    function replaceNumericDmy(text) {
        return text.replace(RX_NUMERIC_DMY, function (match, day, month, year, rawTime) {
            if (!isValidDate(year, month, day)) {
                return match;
            }
            return toDmy(year, month, day) + normalizeTime(rawTime);
        });
    }

    function replaceDayMonthName(text) {
        return text.replace(RX_DAY_MONTH_NAME, function (match, day, monthName, year, rawTime) {
            var month = MONTH_LOOKUP[String(monthName || '').toLowerCase()];
            if (!month || !isValidDate(year, month, day)) {
                return match;
            }
            return toDmy(year, month, day) + normalizeTime(rawTime);
        });
    }

    function replaceMonthNameDay(text) {
        return text.replace(RX_MONTH_NAME_DAY, function (match, monthName, day, year, rawTime) {
            var month = MONTH_LOOKUP[String(monthName || '').toLowerCase()];
            if (!month || !isValidDate(year, month, day)) {
                return match;
            }
            return toDmy(year, month, day) + normalizeTime(rawTime);
        });
    }

    function normalizeDateText(text) {
        if (typeof text !== 'string' || text === '') {
            return text;
        }

        var out = text;
        out = replaceIsoDateTime(out);
        out = replaceDayMonthName(out);
        out = replaceMonthNameDay(out);
        out = replaceNumericDmy(out);
        return out;
    }

    function shouldSkipNode(textNode) {
        if (!textNode || !textNode.parentNode || textNode.nodeType !== Node.TEXT_NODE) {
            return true;
        }
        var parent = textNode.parentNode;
        var tagName = parent.tagName;
        if (tagName && SKIP_TAGS[tagName]) {
            return true;
        }
        if (parent.closest && parent.closest('[data-no-date-format="1"]')) {
            return true;
        }
        return false;
    }

    function processTextNode(textNode) {
        if (shouldSkipNode(textNode)) {
            return;
        }
        var current = textNode.nodeValue;
        var normalized = normalizeDateText(current);
        if (normalized !== current) {
            textNode.nodeValue = normalized;
        }
    }

    function walkAndNormalize(root) {
        if (!root) {
            return;
        }

        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
        var node;
        while ((node = walker.nextNode())) {
            processTextNode(node);
        }
    }

    function annotateDateInputs(root) {
        if (!root || !root.querySelectorAll) {
            return;
        }
        var inputs = root.querySelectorAll('input[type="date"]');
        inputs.forEach(function (input) {
            if (!input || input.dataset.vsDateFormatApplied === '1') {
                return;
            }
            input.dataset.vsDateFormatApplied = '1';
            input.setAttribute('lang', 'en-GB');
            if (!input.getAttribute('placeholder')) {
                input.setAttribute('placeholder', 'DD MM YYYY');
            }
            var title = (input.getAttribute('title') || '').trim();
            if (title === '') {
                input.setAttribute('title', 'Date format: DD MM YYYY');
            } else if (title.toLowerCase().indexOf('dd mm yyyy') === -1) {
                input.setAttribute('title', title + ' | Date format: DD MM YYYY');
            }
        });
    }

    function processRoot(root) {
        walkAndNormalize(root);
        annotateDateInputs(root && root.nodeType === Node.DOCUMENT_NODE ? document : root);
    }

    var mutationQueued = false;
    function queueMutationSweep() {
        if (mutationQueued) {
            return;
        }
        mutationQueued = true;
        requestAnimationFrame(function () {
            mutationQueued = false;
            processRoot(document.body || document.documentElement);
        });
    }

    function setupObserver() {
        if (typeof MutationObserver !== 'function') {
            return;
        }
        var observer = new MutationObserver(function () {
            queueMutationSweep();
        });
        observer.observe(document.documentElement || document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }

    function init() {
        processRoot(document.body || document.documentElement);
        setupObserver();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
