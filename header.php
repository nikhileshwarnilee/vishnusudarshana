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
    <title>Mobile Service Platform</title>
    <link rel="icon" type="image/png" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/forms/') === false ? 'assets/images/logo/logo-icon.png' : '../assets/images/logo/logo-icon.png'); ?>">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/forms/') === false ? 'assets/css/style.css' : '../assets/css/style.css'); ?>">
    <script src="<?php echo (strpos($_SERVER['PHP_SELF'], '/forms/') === false ? 'assets/js/language.js' : '../assets/js/language.js'); ?>" defer></script>
    <script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
        html, body {
            font-family: 'Marcellus', serif !important;
        }
    </style>
</head>
<body class="body-homepage">
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
                        <li><a href="blogs.php" data-i18n="nav_blogs">Blogs</a></li>
                        <li><a href="track.php" data-i18n="nav_track">Track</a></li>
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
                                <path d="M12 6v12M6 12h12M8.5 8.5l7 7M8.5 15.5l7-7" stroke="#FFD700" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </header>
