<!--
    Base language of this platform is English. All content must be written in English only.
    Do not add or auto-generate Marathi or Hindi text in source files.
    Use Google Translate widget for user-facing translation only.
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Vishnusudarshana'; ?></title>
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#FFD700">
        <link rel="icon" href="/assets/images/icon-192.png" sizes="192x192">
        <link rel="apple-touch-icon" href="/assets/images/icon-512.png">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/forms/') === false ? 'assets/css/style.css' : '../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/forms/') === false ? 'assets/css/welcome-intro.css' : '../assets/css/welcome-intro.css'); ?>">
    <script src="<?php echo (strpos($_SERVER['PHP_SELF'], '/forms/') === false ? 'assets/js/language.js' : '../assets/js/language.js'); ?>" defer></script>
    <!-- <script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script> -->
    <script src="<?php echo (strpos($_SERVER['PHP_SELF'], '/forms/') === false ? 'assets/js/welcome-intro.js' : '../assets/js/welcome-intro.js'); ?>" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
        html, body {
            font-family: 'Marcellus', serif !important;
        }
    </style>

    <!-- Firebase Cloud Messaging Configuration -->
    <script src="<?php echo (strpos($_SERVER['PHP_SELF'], '/forms/') === false ? 'config/firebase-config.js' : '../config/firebase-config.js'); ?>"></script>
    
    <!-- Firebase SDK -->
    <script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js"></script>
    
    <!-- Firebase Cloud Messaging Service -->
    <script src="<?php echo (strpos($_SERVER['PHP_SELF'], '/forms/') === false ? 'assets/js/firebase-messaging.js' : '../assets/js/firebase-messaging.js'); ?>" defer></script>
</head>
<body class="body-homepage">
        <script>
        // Register service worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/service-worker.js');
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
                        <li><a href="index.php" data-i18n="nav_home">Home</a></li>
                        <li><a href="services.php" data-i18n="nav_services">Services</a></li>
                        <li><a href="blogs.php" data-i18n="nav_blogs">Knowledge Centre</a></li>
                        <li><a href="track.php" data-i18n="nav_track">Track</a></li>
                        <li><a href="about-us.php" data-i18n="nav_about">About Us</a></li>
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

