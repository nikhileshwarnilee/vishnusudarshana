<?php 
$pageTitle = 'Todays Rashi Bhavishya | Daily Horoscope';
// Force fresh fetch to avoid cached HTML served by PWA/service worker
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
include 'header.php';

require_once __DIR__ . '/config/db.php';

// Fetch all 12 zodiacs for English language only - DEBUG
$debugEnglishData = [];
$zodiacs = [
    1 => 'Aries', 2 => 'Taurus', 3 => 'Gemini', 4 => 'Cancer', 
    5 => 'Leo', 6 => 'Virgo', 7 => 'Libra', 8 => 'Scorpio', 
    9 => 'Sagittarius', 10 => 'Capricorn', 11 => 'Aquarius', 12 => 'Pisces'
];

try {
    $stmt = $pdo->prepare("SELECT zodiac_number, zodiac_name, horoscope_json FROM daily_horoscope WHERE lang = 'en' ORDER BY zodiac_number");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $debugEnglishData[$row['zodiac_number']] = [
            'zodiac_name' => $row['zodiac_name'],
            'json' => $row['horoscope_json']
        ];
    }
} catch (Exception $e) {
    echo "<div style='background:red;color:white;padding:20px;'>Error: " . $e->getMessage() . "</div>";
}

// Load horoscope data from DB for each zodiac and language
$horoscopeByZodiacLang = [];
$languages = ['en', 'hi', 'mr', 'gu', 'ka', 'te'];

try {
    foreach ($languages as $langCode) {
        $horoscopeByZodiacLang[$langCode] = [];
        foreach ($zodiacs as $zodiacNum => $zodiacName) {
            $stmt = $pdo->prepare("SELECT horoscope_json FROM daily_horoscope WHERE zodiac_number = ? AND lang = ? ORDER BY request_date DESC LIMIT 1");
            $result = $stmt->execute([$zodiacNum, $langCode]);
            $row = $stmt->fetch();
            
            if ($row && $row['horoscope_json']) {
                $decoded = json_decode($row['horoscope_json'], true);
                if ($decoded) {
                    $horoscopeByZodiacLang[$langCode][$zodiacNum] = $decoded;
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Horoscope fetch error: " . $e->getMessage());
    $horoscopeByZodiacLang = [];
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
    html, body {
        font-family: 'Marcellus', serif !important;
    }
    
    .horoscope-page {
        background-color: var(--cream-bg);
    }
    
    .horoscope-container {
        max-width: 1200px;
        margin: 60px auto;
        padding: 0 20px;
    }
    
    .horoscope-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2em;
        gap: 20px;
    }
    
    .horoscope-title {
        font-size: 2.2em;
        font-weight: bold;
        color: #800000;
    }

    .header-controls {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .header-controls > div {
        flex: 1;
        min-width: 180px;
    }
    
    .horoscope-lang-select, .horoscope-zodiac-select {
        width: 100%;
    }
    
    .horoscope-lang-select select, .horoscope-zodiac-select select {
        background: linear-gradient(90deg, #ffe066 0%, #fffbe6 100%);
        border: 2px solid #800000;
        color: #800000;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(255,215,0,0.08);
        padding: 10px 14px;
        border-radius: 12px;
        font-size: 1.05rem;
        width: 100%;
        transition: border 0.2s, background 0.2s, box-shadow 0.2s;
    }
    
    .horoscope-lang-select select:focus, .horoscope-zodiac-select select:focus {
        border: 2px solid #ffd700;
        background: #fffbe6;
        outline: none;
    }
    
    .horoscope-content {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 2px 16px rgba(0,0,0,0.07);
        padding: 30px;
        line-height: 1.8;
        color: #333;
        font-size: 1.1em;
    }
    
    .horoscope-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    
    .horoscope-table tbody tr:not(.horoscope-cat-row):hover {
        background: #fff7e6;
        transition: background 0.2s;
    }
    
    .horoscope-table td {
        padding: 14px 18px;
        font-size: 1em;
        color: #2d2d2d;
        border-bottom: 1px solid #f3e6c4;
        vertical-align: top;
    }
    
    .horoscope-table tr:last-child td {
        border-bottom: none;
    }
    
    .horoscope-key {
        font-weight: 700;
        color: #4f3a1a;
        letter-spacing: 0.01em;
        width: 35%;
    }
    
    .horoscope-value {
        color: #2d2d2d;
        word-break: break-word;
    }
    
    .horoscope-cat-row td {
        background: #800000;
        color: #FFD700;
        font-weight: bold;
        text-align: left;
        padding: 14px 18px;
        font-size: 1.08em;
        letter-spacing: 0.5px;
        border-radius: 0;
    }

    .no-data {
        text-align: center;
        color: #666;
        padding: 40px 20px;
    }

    /* Share Section Styles */
    .horoscope-share-section {
        margin-top: 50px;
        padding: 30px;
        background: linear-gradient(135deg, #f9f3f0, #fef9f6);
        border-radius: 16px;
        text-align: center;
    }
    .horoscope-share-section h3 {
        color: #800000;
        font-size: 1.5em;
        margin-bottom: 20px;
    }

    /* Navigation Section Styles */
    .horoscope-nav-section {
        margin: 50px 0 0 0;
        padding: 24px 0 0 0;
        border-top: 2px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
    }
    .horoscope-nav-link {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #800000 !important;
        text-decoration: none;
        font-weight: 700;
        font-size: 1.15em;
        transition: color 0.3s ease;
        max-width: 48%;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        background: #f9f3f0;
        border-radius: 8px;
        padding: 10px 16px;
        box-shadow: 0 2px 8px rgba(128,0,0,0.07);
    }
    .horoscope-nav-link:hover {
        color: #b36b00 !important;
        background: #ffe5d0;
    }
    .horoscope-nav-label {
        font-size: 1em;
        color: #800000;
        font-weight: 700;
        margin-right: 4px;
    }

    .share-buttons {
        display: flex;
        justify-content: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    .share-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        color: #fff;
        border: none;
        cursor: pointer;
        font-size: 1rem;
    }
    .share-btn.facebook {
        background: #1877f2;
    }
    .share-btn.twitter {
        background: #1da1f2;
    }
    .share-btn.whatsapp {
        background: #25d366;
    }
    .share-btn.linkedin {
        background: #0077b5;
    }
    .share-btn.copy-link {
        background: #800000;
    }
    .share-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }
    
    @media (max-width: 768px) {
        .din-container {
            margin: 40px auto;
            padding: 0 16px;
        }
        
        .horoscope-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .horoscope-title {
            font-size: 1.8em;
        }
        
        .horoscope-lang-select {
            min-width: auto;
            width: 100%;
        }
        
        .horoscope-content {
            padding: 20px;
            font-size: 1em;
        }

        .horoscope-share-section {
            padding: 20px 15px;
        }
        .share-buttons {
            gap: 10px;
        }
        .share-btn {
            padding: 10px 16px;
            font-size: 0.9rem;
        }
        
        .zodiac-cards-grid {
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        
        .zodiac-card {
            padding: 10px 6px;
        }
        
        .zodiac-icon {
            font-size: 1.6em;
            margin-bottom: 2px;
        }
        
        .zodiac-name {
            font-size: 0.75em;
        }
        
        .zodiac-hindi {
            font-size: 0.65em;
        }
    }

    /* Zodiac Cards Grid */
    .zodiac-cards-container {
        margin-bottom: 40px;
    }
    
    .zodiac-cards-grid {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 10px;
        margin-top: 30px;
    }
    
    .zodiac-card {
        background: linear-gradient(135deg, #fff 0%, #fffef7 100%);
        border: 2px solid #e6d5b8;
        border-radius: 12px;
        padding: 12px 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(128, 0, 0, 0.08);
    }
    
    .zodiac-card:hover {
        transform: translateY(-3px);
        border-color: #800000;
        box-shadow: 0 4px 12px rgba(128, 0, 0, 0.15);
        background: linear-gradient(135deg, #fffbe6 0%, #fff7e6 100%);
    }
    
    .zodiac-card.active {
        background: linear-gradient(135deg, #800000 0%, #a52a2a 100%);
        border-color: #FFD700;
        color: #FFD700;
        box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3);
    }
    
    .zodiac-icon {
        font-size: 1.8em;
        margin-bottom: 4px;
        display: block;
    }
    
    .zodiac-card.active .zodiac-icon {
        transform: scale(1.15);
    }
    
    .zodiac-name {
        font-size: 0.85em;
        font-weight: 700;
        color: #800000;
        margin-bottom: 2px;
    }
    
    .zodiac-card.active .zodiac-name {
        color: #FFD700;
    }
    
    .zodiac-hindi {
        font-size: 0.75em;
        color: #666;
    }
    
    .zodiac-card.active .zodiac-hindi {
        color: #fff;
    }

    /* Horoscope UI */
    .horoscope-hero {
        background: linear-gradient(135deg, #f9f3ff 0%, #f6f9ff 50%, #fffaf2 100%);
        border: 1px solid #ede7ff;
        border-radius: 18px;
        padding: 22px;
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
        align-items: start;
        box-shadow: 0 12px 30px rgba(45, 0, 90, 0.06);
    }

    .hero-content h2 {
        margin: 0 0 8px 0;
        font-size: 1.4rem;
        color: #1f1534;
    }
    .hero-message {
        margin: 0 0 12px 0;
        color: #4a4066;
        line-height: 1.6;
    }
    .hero-badges {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
    }
    .pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        background: #f3f0ff;
        color: #4a3c6b;
        font-weight: 600;
        font-size: 0.95rem;
    }
    .color-dot {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        border: 1px solid rgba(0,0,0,0.08);
        box-shadow: 0 0 0 3px rgba(0,0,0,0.03);
    }
    .number-pill {
        background: #fff6e6;
        border: 1px solid #ffe1a8;
        color: #8a5b00;
    }

    .areas-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-top: 22px;
    }
    .area-card {
        background: #fff;
        border: 1px solid #f0ecff;
        border-radius: 14px;
        padding: 14px 16px;
        box-shadow: 0 8px 20px rgba(20, 10, 50, 0.04);
    }
    .area-card.warning {
        border-color: #ffe0e0;
        background: linear-gradient(135deg, #fff7f7, #fffaf5);
        box-shadow: 0 10px 22px rgba(190, 30, 30, 0.08);
    }
    .area-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        font-weight: 700;
        color: #2d1b4e;
    }
    .area-score {
        font-weight: 700;
        color: #5b2ca1;
    }
    .area-text {
        color: #544a6b;
        line-height: 1.5;
        margin: 0 0 10px 0;
        font-size: 0.98rem;
    }
    .progress-bar {
        width: 100%;
        height: 8px;
        border-radius: 999px;
        background: #f2edf9;
        overflow: hidden;
        position: relative;
    }
    .progress-fill {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #7a5af8, #ff9bd1);
        width: 0%;
        transition: width 0.4s ease;
    }
    .area-card.warning .progress-fill {
        background: linear-gradient(90deg, #ff7a7a, #ffb199);
    }

    /* Tablet view: 6 cards per row */
    @media (min-width: 769px) and (max-width: 1024px) {
        .zodiac-cards-grid {
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
        }
    }

    /* Mobile view: 4 cards per row (override after base+tablet) */
    @media (max-width: 768px) {
        .zodiac-cards-grid {
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
        }
        .zodiac-card {
            padding: 8px 4px;
        }
        .zodiac-icon {
            font-size: 1.4em;
            margin-bottom: 2px;
        }
        .zodiac-name {
            font-size: 0.7em;
        }
        .zodiac-hindi {
            font-size: 0.6em;
        }
    }
</style>

<main class="main-content horoscope-page">
    <div class="horoscope-container">
        <!-- Header with Title and Language Selector -->
        <div class="horoscope-header">
            <h1 class="horoscope-title">Your Daily Horoscope</h1>
            <div style="font-size:0.95em;color:#555;margin-top:2px;margin-bottom:10px;text-align:left;">
                <?php echo date('l, d F Y'); ?>
            </div>
            <div class="header-controls">
                <div class="horoscope-lang-select">
                    <select id="horoscopeLang" onchange="displayHoroscope()">
                        <option value="en">English (English)</option>
                        <option value="hi">Hindi (हिन्दी)</option>
                        <option value="mr">Marathi (मराठी)</option>
                        <option value="gu">Gujarati (ગુજરાતી)</option>
                        <option value="ka">Kannada (ಕನ್ನಡ)</option>
                        <option value="te">Telugu (తెలుగు)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Zodiac Cards Grid -->
        <div class="zodiac-cards-container">
            <div class="zodiac-cards-grid">
                <div class="zodiac-card active" data-zodiac="1" onclick="selectZodiac(1)">
                    <span class="zodiac-icon">♈</span>
                    <div class="zodiac-name">Aries</div>
                    <div class="zodiac-hindi">मेष</div>
                </div>
                <div class="zodiac-card" data-zodiac="2" onclick="selectZodiac(2)">
                    <span class="zodiac-icon">♉</span>
                    <div class="zodiac-name">Taurus</div>
                    <div class="zodiac-hindi">वृष</div>
                </div>
                <div class="zodiac-card" data-zodiac="3" onclick="selectZodiac(3)">
                    <span class="zodiac-icon">♊</span>
                    <div class="zodiac-name">Gemini</div>
                    <div class="zodiac-hindi">मिथुन</div>
                </div>
                <div class="zodiac-card" data-zodiac="4" onclick="selectZodiac(4)">
                    <span class="zodiac-icon">♋</span>
                    <div class="zodiac-name">Cancer</div>
                    <div class="zodiac-hindi">कर्क</div>
                </div>
                <div class="zodiac-card" data-zodiac="5" onclick="selectZodiac(5)">
                    <span class="zodiac-icon">♌</span>
                    <div class="zodiac-name">Leo</div>
                    <div class="zodiac-hindi">सिंह</div>
                </div>
                <div class="zodiac-card" data-zodiac="6" onclick="selectZodiac(6)">
                    <span class="zodiac-icon">♍</span>
                    <div class="zodiac-name">Virgo</div>
                    <div class="zodiac-hindi">कन्या</div>
                </div>
                <div class="zodiac-card" data-zodiac="7" onclick="selectZodiac(7)">
                    <span class="zodiac-icon">♎</span>
                    <div class="zodiac-name">Libra</div>
                    <div class="zodiac-hindi">तुला</div>
                </div>
                <div class="zodiac-card" data-zodiac="8" onclick="selectZodiac(8)">
                    <span class="zodiac-icon">♏</span>
                    <div class="zodiac-name">Scorpio</div>
                    <div class="zodiac-hindi">वृश्चिक</div>
                </div>
                <div class="zodiac-card" data-zodiac="9" onclick="selectZodiac(9)">
                    <span class="zodiac-icon">♐</span>
                    <div class="zodiac-name">Sagittarius</div>
                    <div class="zodiac-hindi">धनु</div>
                </div>
                <div class="zodiac-card" data-zodiac="10" onclick="selectZodiac(10)">
                    <span class="zodiac-icon">♑</span>
                    <div class="zodiac-name">Capricorn</div>
                    <div class="zodiac-hindi">मकर</div>
                </div>
                <div class="zodiac-card" data-zodiac="11" onclick="selectZodiac(11)">
                    <span class="zodiac-icon">♒</span>
                    <div class="zodiac-name">Aquarius</div>
                    <div class="zodiac-hindi">कुंभ</div>
                </div>
                <div class="zodiac-card" data-zodiac="12" onclick="selectZodiac(12)">
                    <span class="zodiac-icon">♓</span>
                    <div class="zodiac-name">Pisces</div>
                    <div class="zodiac-hindi">मीन</div>
                </div>
            </div>
        </div>
        
        <div id="horoscope-display" class="horoscope-content">
            <div class="no-data">Select a zodiac to view today's horoscope.</div>
        </div>

        <!-- Share Section -->
        <div class="horoscope-share-section">
            <h3>Check Your Today's Horoscope | Rashifal</h3>
            <div class="share-buttons">
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://' . $_SERVER['HTTP_HOST'] . '/horoscope.php') ?>" target="_blank" class="share-btn facebook">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    Facebook
                </a>
                <a href="https://twitter.com/intent/tweet?url=<?= urlencode('https://' . $_SERVER['HTTP_HOST'] . '/horoscope.php') ?>&text=<?= urlencode("Check out my Daily Horoscope") ?>" target="_blank" class="share-btn twitter">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                    Twitter
                </a>
                <a href="https://wa.me/?text=<?= urlencode("Check out my Daily Horoscope\n" . 'https://' . $_SERVER['HTTP_HOST'] . '/horoscope.php') ?>" target="_blank" class="share-btn whatsapp">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                    WhatsApp
                </a>
                <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?= urlencode('https://' . $_SERVER['HTTP_HOST'] . '/horoscope.php') ?>&title=<?= urlencode("Your Daily Horoscope") ?>" target="_blank" class="share-btn linkedin">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                    LinkedIn
                </a>
                <button type="button" class="share-btn copy-link" onclick="copyHoroscopeLink()">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                    Copy Link
                </button>
            </div>
        </div>

        <!-- Navigation Section -->
        <div class="horoscope-nav-section">
            <a class="horoscope-nav-link" href="din-vishesh.php">
                <span class="horoscope-nav-label">&#8592; Today's Festivals</span>
            </a>
            <a class="horoscope-nav-link" href="panchang.php" style="text-align:right;">
                <span class="horoscope-nav-label">Panchang &#8594;</span>
            </a>
        </div>
    </div>
</main>

<script>
    const horoscopeData = <?= json_encode($horoscopeByZodiacLang) ?>;
    console.log('Horoscope Data:', horoscopeData);
    
    // Translation object for all labels
    const translations = {
        en: {
            todaysGuidance: "Today's Guidance",
            luckyColor: "Lucky Color:",
            luckyNumbers: "Lucky Numbers:",
            totalScore: "Total Score",
            career: "Career",
            family: "Family",
            friends: "Friends",
            health: "Health",
            relationship: "Relationship",
            finance: "Finance",
            travel: "Travel",
            finances: "Finances",
            physique: "Physique",
            status: "Status",
            overall: "Overall",
            general: "General"
        },
        hi: {
            todaysGuidance: "आज का मार्गदर्शन",
            luckyColor: "भाग्यशाली रंग:",
            luckyNumbers: "भाग्यशाली संख्या:",
            totalScore: "कुल स्कोर",
            career: "कैरियर",
            family: "परिवार",
            friends: "दोस्त",
            health: "स्वास्थ्य",
            relationship: "संबंध",
            finance: "वित्त",
            travel: "यात्रा",
            finances: "वित्त",
            physique: "शारीरिक",
            status: "स्थिति",
            overall: "समग्र",
            general: "सामान्य"
        },
        mr: {
            todaysGuidance: "आजचे मार्गदर्शन",
            luckyColor: "भाग्यवान रंग:",
            luckyNumbers: "भाग्यवान संख्या:",
            totalScore: "एकूण स्कोर",
            career: "करिअर",
            family: "कुटुंब",
            friends: "मित्र",
            health: "आरोग्य",
            relationship: "संबंध",
            finance: "वित्त",
            travel: "प्रवास",
            finances: "वित्त",
            physique: "शारीरिक",
            status: "स्थिती",
            overall: "एकूण",
            general: "सामान्य"
        },
        gu: {
            todaysGuidance: "આજનું માર્ગદર્શન",
            luckyColor: "ભાગ્યશાળી રંગ:",
            luckyNumbers: "ભાગ્યશાળી નંબર:",
            totalScore: "કુલ સ્કોર",
            career: "કેરિયર",
            family: "પરિવાર",
            friends: "મિત્રો",
            health: "આરોગ્ય",
            relationship: "સંબંધ",
            finance: "ફાયનાન્સ",
            travel: "ટ્રાવેલ",
            finances: "ફાયનાન્સ",
            physique: "શારીરિક",
            status: "સ્થિતિ",
            overall: "સમગ્ર",
            general: "સામાન્ય"
        },
        ka: {
            todaysGuidance: "ಇಂದಿನ ಮಾರ್ಗದರ್ಶನ",
            luckyColor: "ಭಾಗ್ಯವಂತ ಬಣ್ಣ:",
            luckyNumbers: "ಭಾಗ್ಯವಂತ ಸಂಖ್ಯೆಗಳು:",
            totalScore: "ಒಟ್ಟು ಸ್ಕೋರ್",
            career: "ವೃತ್ತಿ",
            family: "ಕುಟುಂಬ",
            friends: "ಸ್ನೇಹಿತರು",
            health: "ಆರೋಗ್ಯ",
            relationship: "ಸಂಬಂಧ",
            finance: "ಹಣಕಾಸು",
            travel: "ಯಾತ್ರೆ",
            finances: "ಹಣಕಾಸು",
            physique: "ಶಾರೀರಿಕ",
            status: "ಸ್ಥಿತಿ",
            overall: "ಒಟ್ಟು",
            general: "ಸಾಮಾನ್ಯ"
        },
        te: {
            todaysGuidance: "ఈ రోజు మార్గదర్శన",
            luckyColor: "అదృష్ట రంగు:",
            luckyNumbers: "అదృష్ట సంఖ్యలు:",
            totalScore: "మొత్తం స్కోర్",
            career: "కెరీర్",
            family: "కుటుంబం",
            friends: "స్నేహితులు",
            health: "ఆరోగ్యం",
            relationship: "సంబంధం",
            finance: "ఆర్థిక",
            travel: "ప్రయాణం",
            finances: "ఆర్థిక",
            physique: "శారీరక",
            status: "స్థితి",
            overall: "మొత్తం",
            general: "సాధారణ"
        }
    };
    
    let selectedZodiac = 1; // Default to Aries

    function selectZodiac(zodiacNum) {
        selectedZodiac = zodiacNum;
        
        // Remove active class from all cards
        document.querySelectorAll('.zodiac-card').forEach(card => {
            card.classList.remove('active');
        });
        
        // Add active class to selected card
        document.querySelector(`.zodiac-card[data-zodiac="${zodiacNum}"]`).classList.add('active');
        
        // Display horoscope
        displayHoroscope();
    }

    function formatValue(key, value) {
        if (typeof value === 'object') {
            if (Array.isArray(value)) {
                return value.join(', ');
            }
            return JSON.stringify(value);
        }
        return String(value);
    }

    function formatKey(key, lang = 'en') {
        // Check if translation exists
        const keyLower = key.toLowerCase();
        if (translations[lang] && translations[lang][keyLower]) {
            return translations[lang][keyLower];
        }
        
        // Fallback: format the key
        return key
            .replace(/_/g, ' ')
            .replace(/^./, str => str.toUpperCase())
            .replace(/\b\w/g, str => str.toUpperCase());
    }

    function styleKey(key) {
        const boldKeys = ['lucky_color', 'lucky_number', 'bot_response', 'physique', 'status', 'finances', 'relationship', 'career', 'travel', 'family', 'friends', 'health', 'total_score'];
        return boldKeys.some(bk => key.toLowerCase().includes(bk));
    }

    function displayHoroscope() {
        const lang = document.getElementById('horoscopeLang').value;
        const zodiac = selectedZodiac;
        const displayDiv = document.getElementById('horoscope-display');

        if (!horoscopeData || Object.keys(horoscopeData).length === 0) {
            displayDiv.innerHTML = '<div class="no-data"><p>❌ No horoscope data available.</p></div>';
            return;
        }

        if (!horoscopeData[lang] || !horoscopeData[lang][zodiac]) {
            displayDiv.innerHTML = '<div class="no-data"><p>❌ No horoscope data available for this zodiac sign.</p></div>';
            return;
        }

        const data = horoscopeData[lang][zodiac];
        const response = data.response || {};
        const botResponse = response.bot_response || {};
        const totalScore = response.total_score || 0;
        const luckyColor = response.lucky_color || '#c5a3ff';
        const luckyNumber = response.lucky_number || '';
        const mainMessage = botResponse.status?.split_response || botResponse.overall?.split_response || botResponse.general?.split_response || 'Have a balanced day ahead.';

        // Build hero summary without score ring
        let html = `
            <div class="horoscope-hero">
                <div class="hero-content">
                    <h2>${translations[lang].todaysGuidance}</h2>
                    <p class="hero-message">${htmlspecialchars(mainMessage)}</p>
                    <div class="hero-badges">
                        <span class="pill"><span class="color-dot" style="background:${htmlspecialchars(luckyColor)}"></span><strong>${translations[lang].luckyColor}</strong> ${htmlspecialchars(luckyColor)}</span>
                        <span class="pill number-pill"><strong>${translations[lang].luckyNumbers}</strong> ${htmlspecialchars(Array.isArray(luckyNumber) ? luckyNumber.join(', ') : luckyNumber)}</span>
                        <span class="pill number-pill"><strong>${translations[lang].totalScore}</strong> ${Math.round(totalScore)}/100</span>
                    </div>
                </div>
            </div>
        `;

        // Life areas grid
        const lifeAreas = Object.entries(botResponse)
            .filter(([key, value]) => value && typeof value === 'object' && (value.split_response || value.score !== undefined));

        if (lifeAreas.length > 0) {
            html += '<div class="areas-grid">';
            lifeAreas.forEach(([key, value]) => {
                const score = value.score ?? 0;
                const text = value.split_response || '';
                const isHealth = key.toLowerCase().includes('health');
                const isLowHealth = isHealth && score < 40;
                const cardClass = isLowHealth ? 'area-card warning' : 'area-card';
                const progressWidth = Math.max(0, Math.min(100, score));
                const translatedKey = formatKey(key, lang);
                html += `
                    <div class="${cardClass}">
                        <div class="area-title">
                            <span><strong>${translatedKey}</strong></span>
                            <span class="area-score">${score}/100</span>
                        </div>
                        <p class="area-text">${htmlspecialchars(text)}</p>
                        <div class="progress-bar"><div class="progress-fill" style="width:${progressWidth}%"></div></div>
                    </div>
                `;
            });
            html += '</div>';
        }

        displayDiv.innerHTML = html;
    }
    
    function htmlspecialchars(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function copyHoroscopeLink() {
        const link = window.location.href;
        navigator.clipboard.writeText(link).then(() => {
            alert('Link copied to clipboard!');
        });
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        selectZodiac(1); // Select Aries by default
    });
</script>

<?php include 'footer.php'; ?>
