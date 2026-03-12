<!--
    Base language of this platform is English. All content must be written in English only.
    Do not add or auto-generate Marathi or Hindi text in source files.
    Use Google Translate widget for user-facing translation only.
-->
<?php
require_once __DIR__ . '/helpers/favicon.php';
require_once __DIR__ . '/helpers/share.php';

$isFormsPath = (strpos($_SERVER['PHP_SELF'] ?? '', '/forms/') !== false);
if (!isset($assetPrefix)) {
    $assetPrefix = $isFormsPath ? '../' : '';
}

$styleHref = $assetPrefix . 'assets/css/style.css';
$welcomeIntroCssHref = $assetPrefix . 'assets/css/welcome-intro.css';
$languageJsSrc = $assetPrefix . 'assets/js/language.js';
$welcomeIntroJsSrc = $assetPrefix . 'assets/js/welcome-intro.js';
$dateFormatJsSrc = $assetPrefix . 'assets/js/date-format-global.js';

$styleFile = __DIR__ . '/assets/css/style.css';
$welcomeIntroCssFile = __DIR__ . '/assets/css/welcome-intro.css';
$languageJsFile = __DIR__ . '/assets/js/language.js';
$welcomeIntroJsFile = __DIR__ . '/assets/js/welcome-intro.js';
$dateFormatJsFile = __DIR__ . '/assets/js/date-format-global.js';

if (is_file($styleFile)) {
    $styleHref .= '?v=' . filemtime($styleFile);
}
if (is_file($welcomeIntroCssFile)) {
    $welcomeIntroCssHref .= '?v=' . filemtime($welcomeIntroCssFile);
}
if (is_file($languageJsFile)) {
    $languageJsSrc .= '?v=' . filemtime($languageJsFile);
}
if (is_file($welcomeIntroJsFile)) {
    $welcomeIntroJsSrc .= '?v=' . filemtime($welcomeIntroJsFile);
}
if (is_file($dateFormatJsFile)) {
    $dateFormatJsSrc .= '?v=' . filemtime($dateFormatJsFile);
}

$currentRequestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$currentPage = basename($currentRequestPath ?: ($_SERVER['PHP_SELF'] ?? ''));
$isActiveTopNav = static function (array $pages) use ($currentPage) {
    return in_array($currentPage, $pages, true);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Vishnusudarshana'; ?></title>
    <?php echo vs_favicon_tags(); ?>
    <?php echo vs_social_meta_tags(); ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($styleHref, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($welcomeIntroCssHref, ENT_QUOTES, 'UTF-8'); ?>">
    <script src="<?php echo htmlspecialchars($languageJsSrc, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <script src="<?php echo htmlspecialchars($dateFormatJsSrc, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <!-- <script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script> -->
    <script src="<?php echo htmlspecialchars($welcomeIntroJsSrc, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
        html, body {
            font-family: 'Marcellus', serif !important;
        }
    </style>
    <style>
        body.vs-loader-lock {
            overflow: hidden;
        }

        .vs-page-loader {
            position: fixed;
            inset: 0;
            z-index: 20000;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(circle at 12% 18%, rgba(255, 215, 0, 0.2), transparent 40%),
                radial-gradient(circle at 85% 6%, rgba(128, 0, 0, 0.2), transparent 45%),
                rgba(255, 255, 255, 0.98);
            transition: opacity 0.35s ease, visibility 0.35s ease;
        }

        .vs-page-loader.is-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .vs-page-loader-card {
            min-width: 230px;
            max-width: 86vw;
            border-radius: 16px;
            padding: 16px 18px 14px;
            text-align: center;
            background: #fffdf6;
            border: 1px solid rgba(128, 0, 0, 0.14);
            box-shadow: 0 16px 34px rgba(128, 0, 0, 0.14);
        }

        .vs-page-loader-ring {
            width: 52px;
            height: 52px;
            margin: 0 auto 10px;
            border: 4px solid rgba(128, 0, 0, 0.2);
            border-top-color: #800000;
            border-radius: 50%;
            animation: vsPageLoaderSpin 0.85s linear infinite;
        }

        .vs-page-loader-title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #800000;
        }

        .vs-page-loader-subtitle {
            margin: 4px 0 0;
            font-size: 0.92rem;
            color: #666;
        }

        @keyframes vsPageLoaderSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

</head>
<body class="body-homepage">
        <div id="vsPageLoader" class="vs-page-loader" role="status" aria-live="polite" aria-label="Loading page">
            <div class="vs-page-loader-card">
                <div class="vs-page-loader-ring" aria-hidden="true"></div>
                <p class="vs-page-loader-title" id="vsPageLoaderTitle">Loading...</p>
                <p class="vs-page-loader-subtitle">Please wait while we prepare your page</p>
            </div>
        </div>
        <script>
        (function () {
            var loader = document.getElementById('vsPageLoader');
            var loaderTitle = document.getElementById('vsPageLoaderTitle');
            if (!loader) {
                return;
            }

            var body = document.body;
            var HIDE_DELAY_MS = 120;

            var hideLoader = function () {
                loader.classList.add('is-hidden');
                if (body) {
                    body.classList.remove('vs-loader-lock');
                }
            };

            var showLoader = function (title) {
                if (typeof title === 'string' && title.trim() !== '' && loaderTitle) {
                    loaderTitle.textContent = title.trim();
                } else if (loaderTitle) {
                    loaderTitle.textContent = 'Loading...';
                }
                loader.classList.remove('is-hidden');
                if (body) {
                    body.classList.add('vs-loader-lock');
                }
            };

            window.vsShowGlobalLoader = showLoader;
            window.vsHideGlobalLoader = hideLoader;

            if (document.readyState === 'complete') {
                setTimeout(hideLoader, HIDE_DELAY_MS);
            } else {
                window.addEventListener('load', function () {
                    setTimeout(hideLoader, HIDE_DELAY_MS);
                }, { once: true });
            }

            window.addEventListener('pageshow', function () {
                hideLoader();
            });

            document.addEventListener('click', function (event) {
                var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
                if (!link) {
                    return;
                }
                if (link.hasAttribute('data-no-loader')) {
                    return;
                }
                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }
                var target = (link.getAttribute('target') || '').trim();
                if (target !== '' && target.toLowerCase() !== '_self') {
                    return;
                }
                if (link.hasAttribute('download')) {
                    return;
                }
                var href = (link.getAttribute('href') || '').trim();
                if (
                    href === '' ||
                    href.charAt(0) === '#' ||
                    href.indexOf('javascript:') === 0 ||
                    href.indexOf('tel:') === 0 ||
                    href.indexOf('mailto:') === 0
                ) {
                    return;
                }
                try {
                    var url = new URL(link.href, window.location.href);
                    if (url.origin !== window.location.origin) {
                        return;
                    }
                    if (url.href === window.location.href) {
                        return;
                    }
                } catch (err) {
                    return;
                }
                showLoader('Loading page...');
            });

            document.addEventListener('submit', function (event) {
                var form = event.target;
                if (!form || !form.tagName || form.tagName.toLowerCase() !== 'form') {
                    return;
                }
                if (event.defaultPrevented) {
                    return;
                }
                if (form.hasAttribute('data-no-loader')) {
                    return;
                }
                var formTarget = (form.getAttribute('target') || '').trim();
                if (formTarget !== '' && formTarget.toLowerCase() !== '_self') {
                    return;
                }
                showLoader('Please wait...');
            });
        })();
        </script>
        <script>
        // Register service worker (supports both root and subfolder installs)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                var path = window.location.pathname || '';
                var adminMarker = '/admin/';
                var formsMarker = '/forms/';
                var basePath = '';

                if (path.indexOf(adminMarker) >= 0) {
                    basePath = path.slice(0, path.indexOf(adminMarker));
                } else if (path.indexOf(formsMarker) >= 0) {
                    basePath = path.slice(0, path.indexOf(formsMarker));
                } else {
                    var lastSlash = path.lastIndexOf('/');
                    basePath = lastSlash > 0 ? path.slice(0, lastSlash) : '';
                }

                var swUrl = (basePath || '') + '/service-worker.js';
                var swScope = basePath ? (basePath + '/') : '/';

                navigator.serviceWorker.register(swUrl, { scope: swScope });
            });
        }
        // Show PWA install prompt for new users
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            if (!localStorage.getItem('pwaPromptShown')) {
                setTimeout(() => {
                    showPwaInstallPrompt();
                }, 1200);
            }
        });
        function showPwaInstallPrompt() {
            const div = document.createElement('div');
            div.innerHTML = '<div style="position:fixed;bottom:18px;left:0;right:0;z-index:9999;background:#FFD700;border-radius:12px;padding:18px 12px;text-align:center;box-shadow:0 2px 12px #80000022;font-family:Marcellus,serif;max-width:420px;margin:0 auto;">' +
                '<b style="color:#800000;font-size:1.08em;">Install Vishnusudarshana App</b><br>' +
                '<span style="color:#333;font-size:0.98em;">Get faster access and offline features.</span><br>' +
                '<button id="pwa-install-btn" style="margin-top:10px;background:#800000;color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:1em;cursor:pointer;">Install App</button>' +
                '<button id="pwa-dismiss-btn" style="margin-top:10px;margin-left:8px;background:#ccc;color:#800000;border:none;border-radius:8px;padding:8px 18px;font-size:1em;cursor:pointer;">Maybe Later</button>' +
                '</div>';
            document.body.appendChild(div);
            document.getElementById('pwa-install-btn').onclick = function() {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        if (choiceResult.outcome === 'accepted') {
                            localStorage.setItem('pwaPromptShown', '1');
                        }
                        div.remove();
                    });
                }
            };
            document.getElementById('pwa-dismiss-btn').onclick = function() {
                localStorage.setItem('pwaPromptShown', '1');
                div.remove();
            };
        }
        </script>
        <div id="google_translate_element" style="display:none;"></div>
        <script>
        function googleTranslateElementInit() {
            new google.translate.TranslateElement(
                {
                    pageLanguage: 'en',
                    includedLanguages: 'en,hi,mr',
                    autoDisplay: false
                },
                'google_translate_element'
            );
        }
        </script>

    <!-- Language Selection Popup (UI only) -->
    <div class="lang-popup-overlay" id="lang-popup-overlay"></div>
    <div class="lang-popup" id="lang-popup">
        <div class="lang-popup-content">
            <h2 class="lang-popup-title">Select Your Language</h2>
            <form class="lang-popup-form">
                <label class="lang-option">
                    <input type="radio" name="language" value="en" checked>
                    <span>English</span>
                </label>
                <label class="lang-option">
                    <input type="radio" name="language" value="hi">
                    <span>Hindi</span>
                </label>
                <label class="lang-option">
                    <input type="radio" name="language" value="mr">
                    <span>Marathi</span>
                </label>
                <button type="button" class="lang-popup-continue">Continue</button>
            </form>
        </div>
    </div>

    <!-- Welcome Intro Popup -->
    <div class="welcome-intro-overlay" id="welcome-intro-overlay"></div>
    <div class="welcome-intro-popup" id="welcome-intro-popup">
        <div class="welcome-intro-content">
            <h2 class="welcome-intro-title">Welcome to Vishnusudarshana!</h2>
            <p class="welcome-intro-subtitle">Let us guide you through our platform</p>

            <div class="welcome-intro-language">
                <div class="welcome-intro-lang-buttons">
                    <button type="button" class="welcome-lang-btn" data-lang="en">English</button>
                    <button type="button" class="welcome-lang-btn" data-lang="mr">मराठी</button>
                    <button type="button" class="welcome-lang-btn" data-lang="te">తెలుగు</button>
                    <button type="button" class="welcome-lang-btn" data-lang="ka">ಕನ್ನಡ</button>
                    <button type="button" class="welcome-lang-btn" data-lang="gu">ગુજરાતી</button>
                    <button type="button" class="welcome-lang-btn" data-lang="hi">हिन्दी</button>
                </div>
                <div class="welcome-intro-message" data-lang="en">
                    This website helps you book spiritual, astrological, and puja services online, saving time and effort. For office in-person visit, book a token. For online appointment, go to the Services section. You can also book many other services there.
                </div>
                <div class="welcome-intro-message" data-lang="mr">
                    ही वेबसाइट आध्यात्मिक, ज्योतिषीय आणि पूजा सेवा ऑनलाइन बुक करण्यासाठी आहे, ज्यामुळे वेळ आणि मेहनत वाचते. कार्यालयात प्रत्यक्ष भेटीसाठी टोकन बुक करा. ऑनलाइन अपॉइंटमेंटसाठी Services विभागात जा. Services मध्ये इतर अनेक सेवा बुक करता येतात.
                </div>
                <div class="welcome-intro-message" data-lang="te">
                    ఈ వెబ్‌సైట్ ద్వారా ఆధ్యాత్మిక, జ్యోతిష్య, పూజ సేవలను ఆన్‌లైన్‌లో బుక్ చేసి సమయం, శ్రమను ఆదా చేసుకోవచ్చు. కార్యాలయంలో ప్రత్యక్ష సందర్శన కోసం టోకెన్ బుక్ చేయండి. ఆన్‌లైన్ అపాయింట్‌మెంట్ కోసం Services విభాగానికి వెళ్లండి. Services లో మరెన్నో సేవలను బుక్ చేయవచ్చు.
                </div>
                <div class="welcome-intro-message" data-lang="ka">
                    ಈ ವೆಬ್‌ಸೈಟ್ ಮೂಲಕ ಆಧ್ಯಾತ್ಮಿಕ, ಜ್ಯೋತಿಷ್ಯ, ಪೂಜೆ ಸೇವೆಗಳನ್ನು ಆನ್‌ಲೈನ್‌ನಲ್ಲಿ ಬುಕ್ ಮಾಡಿ ಸಮಯ ಮತ್ತು ಶ್ರಮ ಉಳಿಸಬಹುದು. ಕಚೇರಿಯಲ್ಲಿ ನೇರ ಭೇಟಿ ಮಾಡಲು ಟೋಕನ್ ಬುಕ್ ಮಾಡಿ. ಆನ್‌ಲೈನ್ ಅಪಾಯಿಂಟ್‌ಮೆಂಟ್‌ಗಾಗಿ Services ವಿಭಾಗಕ್ಕೆ ಹೋಗಿ. Services ನಲ್ಲಿ ಇನ್ನೂ ಹಲವು ಸೇವೆಗಳ ಬುಕಿಂಗ್ ಸಾಧ್ಯ.
                </div>
                <div class="welcome-intro-message" data-lang="gu">
                    આ વેબસાઇટ દ્વારા આધ્યાત્મિક, જ્યોતિષ અને પૂજા સેવાઓ ઓનલાઈન બુક કરી સમય અને મહેનત બચાવો. ઓફિસમાં પ્રત્યક્ષ મુલાકાત માટે ટોકન બુક કરો. ઓનલાઈન અપોઇન્ટમેન્ટ માટે Services વિભાગમાં જાઓ. Services માં અન્ય ઘણી સેવાઓ પણ બુક કરી શકાય છે.
                </div>
                <div class="welcome-intro-message" data-lang="hi">
                    यह वेबसाइट आध्यात्मिक, ज्योतिष और पूजा सेवाओं को ऑनलाइन बुक करने के लिए है, जिससे समय और मेहनत बचती है। ऑफिस में प्रत्यक्ष दर्शन हेतु टोकन बुक करें। ऑनलाइन अपॉइंटमेंट के लिए Services सेक्शन में जाएं। Services में अन्य कई सेवाएँ भी बुक की जा सकती हैं।
                </div>
            </div>

            <button type="button" class="welcome-intro-btn" id="welcome-intro-btn">Get Started</button>
        </div>
    </div>
    <header class="header header--design-12">
        <?php $mobile_number = isset($mobile_number) ? $mobile_number : '98500 57444'; ?>
        <div class="header-12-content">
            <div class="header-12-logo">
                <a href="index.php" class="logo-link" aria-label="Vishnusudarshana Home">
                    <img src="assets/images/logo/logomain.png" alt="Vishnusudarshana Logo" class="logo-img" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
                    <span class="logo-text" style="display:none;">Vishnusudarshana</span>
                </a>
            </div>
            <div class="header-12-nav-icons-row">
                <nav class="header-12-nav" aria-label="Primary Navigation">
                    <ul class="nav-menu">
                        <li><a class="top-nav-link<?php echo $isActiveTopNav(['index.php', '']) ? ' is-active' : ''; ?>" href="index.php" data-i18n="nav_home">Home</a></li>
                        <li><a class="top-nav-link<?php echo $isActiveTopNav(['services.php', 'category.php', 'service-form.php', 'service-review.php', 'service-review2.php', 'payment-init.php', 'payment-success.php', 'payment-failed.php']) ? ' is-active' : ''; ?>" href="services.php">Online Services</a></li>
                        <li><a class="top-nav-link<?php echo $isActiveTopNav(['offlineservices.php', 'book-token.php', 'live-token.php']) ? ' is-active' : ''; ?>" href="offlineservices.php">Offline Services</a></li>
                        <li><a class="top-nav-link<?php echo $isActiveTopNav(['events.php', 'event-detail.php', 'event-register.php', 'event-payment.php', 'event-booking-confirmation.php']) ? ' is-active' : ''; ?>" href="events.php">Events</a></li>
                        <li><a class="top-nav-link<?php echo $isActiveTopNav(['blogs.php', 'blog-detail.php']) ? ' is-active' : ''; ?>" href="blogs.php" data-i18n="nav_blogs">Knowledge Centre</a></li>
                        <li><a class="top-nav-link<?php echo $isActiveTopNav(['track.php']) ? ' is-active' : ''; ?>" href="track.php" data-i18n="nav_track">Track</a></li>
                    </ul>
                </nav>
                <div class="header-icons-right">
                    <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $mobile_number); ?>" class="header-icon-btn" title="Call">
                        <span class="header-icon-svg call-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="12" fill="var(--maroon)"/>
                                <path d="M17.7 15.6l-2.2-1c-.3-.1-.7 0-.9.2l-1 1.3c-1.6-.8-2.9-2.1-3.7-3.7l1.3-1c.2-.2.3-.6.2-.9l-1-2.2c-.2-.4-.7-.6-1.1-.5l-1.7.4c-.4.1-.7.5-.7.9 0 5.1 4.1 9.2 9.2 9.2.4 0 .8-.3.9-.7l.4-1.7c.1-.4-.1-.9-.5-1.1z" fill="#FFD700"/>
                            </svg>
                        </span>
                    </a>
                    <button class="header-icon-btn" id="lang-header-btn" aria-label="Change Language" type="button" title="Change Language">
                        <span class="header-icon-svg lang-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="12" fill="var(--maroon)"/>
                                <circle cx="12" cy="12" r="5.5" stroke="#FFD700" stroke-width="1.2" fill="none"/>
                                <path d="M12 6.5v11M7 9.5h10M7.5 14.5h9" stroke="#FFD700" stroke-width="1.2" stroke-linecap="round"/>
                                <path d="M12 6.5c-1.5 0-2.8 2.5-2.8 5.5s1.3 5.5 2.8 5.5 2.8-2.5 2.8-5.5-1.3-5.5-2.8-5.5z" stroke="#FFD700" stroke-width="1.2" fill="none"/>
                            </svg>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </header>

