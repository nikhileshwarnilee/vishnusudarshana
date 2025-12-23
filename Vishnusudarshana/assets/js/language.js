console.log('language.js loaded');
// --- Translation object ---
const TRANSLATIONS = {
    en: {
        // All non-English translations removed. English base language only.
        home_why_title: 'विष्णुसुदर्शन धर्मिक मंच क्यों?',
        home_how_title: 'इस मंच का उपयोग कैसे करें',
    },
    mr: {
        nav_home: 'मुख्यपृष्ठ',
        nav_services: 'सेवा',
        nav_reels: 'रील्स',
        nav_track: 'ट्रॅक करा',
        home_why_title: 'विष्णुसुदर्शन धार्मिक मंच का?',
        home_how_title: 'हा मंच कसा वापरावा',
    }
};

// --- Google Translate integration for dynamic content ---
function translateElements(targetLang) {
    if (targetLang === 'en') {
        // Restore original English text
        document.querySelectorAll('[data-translate="true"]').forEach(function(el) {
            if (el.hasAttribute('data-original-text')) {
                el.textContent = el.getAttribute('data-original-text');
            }
        });
        return;
    }
    var elements = document.querySelectorAll('[data-translate="true"]');
    elements.forEach(function(el) {
        // Store original text only once
        if (!el.hasAttribute('data-original-text')) {
            el.setAttribute('data-original-text', el.innerText);
        }
        var originalText = el.getAttribute('data-original-text');
        // Skip numbers, dates, and names (simple heuristic)
        if (/^\s*\d+[\d\s\-\/:,.]*\s*$/.test(originalText)) return;
        if (/\d{1,2}\/\d{1,2}\/\d{2,4}/.test(originalText)) return;
        if (/^[A-Z][a-z]+( [A-Z][a-z]+)*$/.test(originalText)) return;
        fetch('api/translate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'text=' + encodeURIComponent(originalText) + '&target_lang=' + encodeURIComponent(targetLang)
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data && data.translatedText) {
                el.textContent = data.translatedText;
            } else {
                el.textContent = originalText;
            }
        })
        .catch(function() {
            el.textContent = originalText;
        });
    });
}


// Google Translate Widget integration
function applyLanguage(lang) {
    // Map to Google Translate codes
    var googleLang = lang === 'hi' ? 'hi' : (lang === 'mr' ? 'mr' : 'en');
    // Set Google Translate language via widget
    if (window.google && window.google.translate && window.google.translate.TranslateElement) {
        var select = document.querySelector('.goog-te-combo');
        if (select) {
            select.value = googleLang;
            select.dispatchEvent(new Event('change'));
        }
    }
}

// --- Language selection popup logic (existing, cleaned) ---
window.addEventListener('DOMContentLoaded', function () {
    var popup = document.getElementById('lang-popup');
    var overlay = document.getElementById('lang-popup-overlay');
    var continueBtn = document.querySelector('.lang-popup-continue');
    var langRadios = document.querySelectorAll('.lang-popup-form input[name="language"]');

    // Manual open: header icon
    var langHeaderBtn = document.getElementById('lang-header-btn');
    if (langHeaderBtn) {
        langHeaderBtn.addEventListener('click', function () {
            if (popup) popup.style.display = 'block';
            if (overlay) overlay.style.display = 'block';
        });
    }

    // Check localStorage for preferred_language
    var storedLang = localStorage.getItem('preferred_language');
    if (!storedLang) {
        // Show popup and overlay
        if (popup) popup.style.display = 'block';
        if (overlay) overlay.style.display = 'block';
        // Ensure English is selected by default
        if (langRadios.length) {
            var found = false;
            langRadios.forEach(function(radio) {
                if (radio.value === 'en') {
                    radio.checked = true;
                    found = true;
                }
            });
            if (!found) langRadios[0].checked = true;
        }
    }

    if (continueBtn) {
        continueBtn.addEventListener('click', function () {
            var selected = document.querySelector('.lang-popup-form input[name="language"]:checked');
            var lang = selected ? selected.value : 'en';
            localStorage.setItem('preferred_language', lang);
            if (popup) popup.style.display = 'none';
            if (overlay) overlay.style.display = 'none';
            applyLanguage(lang);
        });
    }

    // On page load, apply language
    var initialLang = localStorage.getItem('preferred_language') || 'en';
    applyLanguage(initialLang);
});
