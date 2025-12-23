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
</head>
<body>
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
    <header class="header">
        <div class="header-content">
            <div class="header-top">
                <a href="index.php" class="logo-link" aria-label="Vishnusudarshana Home">
                    <img src="assets/images/logo/logomain.png" alt="Vishnusudarshana Logo" class="logo-img" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
                    <span class="logo-text" style="display:none;">Vishnusudarshana</span>
                </a>
            </div>
            <nav class="navbar desktop-nav">
                <ul>
                    <li><a href="index.php" data-i18n="nav_home">Home</a></li>
                    <li><a href="services.php" data-i18n="nav_services">Services</a></li>
                    <li><a href="reels.php" data-i18n="nav_reels">Reels</a></li>
                    <li><a href="track.php" data-i18n="nav_track">Track</a></li>
                </ul>
            </nav>
            <button class="lang-header-btn" id="lang-header-btn" aria-label="Change Language" type="button">🌐</button>
        </div>
    </header>
