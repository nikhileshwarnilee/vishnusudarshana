<?php 
require_once __DIR__ . '/helpers/share.php';

$todayDateText = date('d F Y');
$dinVisheshShareUrl = vs_project_absolute_url('din-vishesh.php');
$dinVisheshWhatsAppUrl = vs_project_absolute_url('din-vishesh.php?share=wa');

$pageTitle = 'Todays Festivals and Yoga - ' . $todayDateText;
$shareTitle = 'Din Vishesh | ' . $todayDateText;
$shareDescription = "Today's festivals and significance from the Hindu calendar.";
$shareUrl = $dinVisheshShareUrl;
$shareType = 'website';
$shareImage = vs_project_absolute_url('assets/images/logo/dinvishesh.png');

$dinVisheshWhatsAppText = "📿 Din Vishesh ({$todayDateText})\n\nMarathi:\n📿 आजचा दिनविशेष - पवित्र माहिती एका क्लिकमध्ये.\n\nEnglish:\n📿 Today's significance - divine insights in one click.\n\n{$dinVisheshWhatsAppUrl}";

// Prevent browsers (especially mobile/PWA) from serving a cached copy that might have empty data
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
include 'header.php';
require_once __DIR__ . '/config/db.php';

// Load festival data from DB for each language
$festivalByLang = [];
$languages = ['en', 'hi', 'mr', 'gu', 'ka', 'te'];
try {
    foreach ($languages as $langCode) {
        $stmt = $pdo->prepare("SELECT festival_json FROM festivals WHERE lang = ? ORDER BY request_date DESC LIMIT 1");
        $stmt->execute([$langCode]);
        $row = $stmt->fetch();
        if ($row && $row['festival_json']) {
            $festivalByLang[$langCode] = json_decode($row['festival_json'], true);
        }
    }
} catch (Exception $e) {
    $festivalByLang = [];
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
    html, body {
        font-family: 'Marcellus', serif !important;
    }
    
    .din-vishesh-page {
        background-color: var(--cream-bg);
    }
    
    .din-container {
        max-width: 1200px;
        margin: 60px auto;
        padding: 0 20px;
    }
    
    .din-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2em;
        gap: 20px;
    }
    
    .din-title {
        font-size: 2.2em;
        font-weight: bold;
        color: #800000;
    }
    
    .din-lang-select {
        min-width: 200px;
    }
    
    .din-lang-select select {
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
    
    .din-lang-select select:focus {
        border: 2px solid #ffd700;
        background: #fffbe6;
        outline: none;
    }
    
    .festival-content {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 2px 16px rgba(0,0,0,0.07);
        padding: 30px;
        line-height: 1.8;
        color: #333;
        font-size: 1.1em;
    }
    
    .festival-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    
    .festival-table tbody tr:not(.festival-cat-row):hover {
        background: #fff7e6;
        transition: background 0.2s;
    }
    
    .festival-table td {
        padding: 14px 18px;
        font-size: 1em;
        color: #2d2d2d;
        border-bottom: 1px solid #f3e6c4;
        vertical-align: top;
    }
    
    .festival-table tr:last-child td {
        border-bottom: none;
    }
    
    .festival-key {
        font-weight: 700;
        color: #4f3a1a;
        letter-spacing: 0.01em;
        width: 35%;
    }
    
    .festival-value {
        color: #2d2d2d;
        word-break: break-word;
    }
    
    .festival-cat-row td {
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
    .din-share-section {
        margin-top: 50px;
        padding: 30px;
        background: linear-gradient(135deg, #f9f3f0, #fef9f6);
        border-radius: 16px;
        text-align: center;
    }
    .din-share-section h3 {
        color: #800000;
        font-size: 1.5em;
        margin-bottom: 20px;
    }

    /* Navigation Section Styles */
    .din-nav-section {
        margin: 50px 0 0 0;
        padding: 24px 0 0 0;
        border-top: 2px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
    }
    .din-nav-link {
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
    .din-nav-link:hover {
        color: #b36b00 !important;
        background: #ffe5d0;
    }
    .din-nav-label {
        font-size: 1em;
        color: #800000;
        font-weight: 600;
        margin-right: 4px;
    }

    .share-buttons {
        display: flex;
        justify-content: center;
        gap: 14px;
        flex-wrap: wrap;
        margin-top: 8px;
    }
    .share-btn {
        --btn-bg: linear-gradient(135deg, #27d367, #17a34a);
        --btn-border: rgba(16, 102, 50, 0.4);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        min-width: 190px;
        height: 48px;
        padding: 0 20px;
        border-radius: 999px;
        text-decoration: none;
        font-weight: 700;
        letter-spacing: 0.2px;
        line-height: 1;
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        color: #fff;
        border: 1px solid var(--btn-border);
        background: var(--btn-bg);
        box-shadow: 0 8px 18px rgba(0, 0, 0, 0.14);
        cursor: pointer;
        font-size: 1rem;
        font-family: inherit;
    }
    .share-btn svg {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
    }
    .share-btn.whatsapp {
        --btn-bg: linear-gradient(135deg, #27d367, #17a34a);
        --btn-border: rgba(16, 102, 50, 0.4);
    }
    .share-btn.copy-link {
        --btn-bg: linear-gradient(135deg, #8d0012, #5b0010);
        --btn-border: rgba(102, 0, 20, 0.55);
        color: #fff !important;
    }
    .share-btn.copy-link svg {
        color: #fff !important;
        fill: #fff !important;
    }
    .share-btn.copy-link svg path {
        fill: #fff !important;
    }
    .share-btn.copy-link:hover,
    .share-btn.copy-link:focus-visible {
        --btn-bg: linear-gradient(135deg, #ffe7a3, #ffd267);
        --btn-border: rgba(179, 107, 0, 0.55);
        color: #4a1f00 !important;
        filter: none;
    }
    .share-btn.copy-link:hover svg,
    .share-btn.copy-link:focus-visible svg {
        color: #4a1f00 !important;
        fill: #4a1f00 !important;
    }
    .share-btn.copy-link:hover svg path,
    .share-btn.copy-link:focus-visible svg path {
        fill: #4a1f00 !important;
    }
    .share-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);
        filter: saturate(1.05);
    }
    .share-btn:active {
        transform: translateY(0);
        box-shadow: 0 6px 14px rgba(0, 0, 0, 0.18);
    }
    .share-btn:focus-visible {
        outline: 3px solid rgba(255, 215, 0, 0.45);
        outline-offset: 2px;
    }
    
    @media (max-width: 768px) {
        .din-container {
            margin: 40px auto;
            padding: 0 16px;
        }
        
        .din-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .din-title {
            font-size: 1.8em;
        }
        
        .din-lang-select {
            min-width: auto;
            width: 100%;
        }
        
        .festival-content {
            padding: 20px;
            font-size: 1em;
        }

        .din-share-section {
            padding: 20px 15px;
        }
        .share-buttons {
            gap: 10px;
        }
        .share-btn {
            width: min(100%, 280px);
            min-width: 220px;
            height: 46px;
            font-size: 0.95rem;
        }
    }
</style>

<main class="main-content din-vishesh-page">
    <div class="din-container">
        <div class="din-header">
            <h1 class="din-title">Today's Festivals and Its Significance</h1>
            <div style="font-size:0.95em;color:#555;margin-top:2px;margin-bottom:10px;text-align:left;">
                <?php echo date('l, d F Y'); ?>
            </div>
            <div class="din-lang-select">
                <select id="dinVisheshLang" onchange="displayFestival()">
                    <option value="en">English (English)</option>
                    <option value="hi">Hindi (हिन्दी)</option>
                    <option value="mr">Marathi (मराठी)</option>
                    <option value="gu">Gujarati (ગુજરાતી)</option>
                    <option value="ka">Kannada (ಕನ್ನಡ)</option>
                    <option value="te">Telugu (తెలుగు)</option>
                </select>
            </div>
        </div>
        
        <div id="festival-display" class="festival-content">
            <div class="no-data">No festival data available</div>
        </div>

        <!-- Share Section -->
        <div class="din-share-section">
            <h3>Share Today's Festivals</h3>
            <div class="share-buttons">
                <a href="https://wa.me/?text=<?= urlencode($dinVisheshWhatsAppText) ?>" target="_blank" class="share-btn whatsapp">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                    WhatsApp
                </a>
                <button type="button" class="share-btn copy-link" onclick="copyDinVisheshLink()">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                    Copy Link
                </button>
            </div>
        </div>

        <!-- Navigation Section -->
        <div class="din-nav-section">
            <a class="din-nav-link" href="panchang.php">
                <span class="din-nav-label">&#8592; Today's Panchang</span>
            </a>
            <a class="din-nav-link" href="horoscope.php" style="text-align:right;">
                <span class="din-nav-label">Daily Horoscope &#8594;</span>
            </a>
        </div>
    </div>
</main>

<script>
    function copyDinVisheshLink() {
        var url = window.location.href;
        navigator.clipboard.writeText(url).then(function() {
            var btn = document.querySelector('.share-btn.copy-link');
            var originalText = btn.innerHTML;
            btn.innerHTML = '<svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> Copied!';
            btn.style.background = 'linear-gradient(135deg, #2dbf67, #1b944d)';
            setTimeout(function() {
                btn.innerHTML = originalText;
                btn.style.background = '';
            }, 2000);
        });
    }
</script>

<script>
    // Festival data from PHP
    const festivalByLang = <?php echo json_encode($festivalByLang); ?>;

    // Label translations for specific keys
    const labelTranslations = {
        en: {
            festival_list: "Today's Festivals",
            yogas: "Today's Yog"
        },
        hi: {
            festival_list: "आज के त्योहार",
            yogas: "आज का योग"
        },
        mr: {
            festival_list: "आजचे सण",
            yogas: "आजचा योग"
        },
        gu: {
            festival_list: "આજના તહેવારો",
            yogas: "આજનો યોગ"
        },
        ka: {
            festival_list: "ಇಂದಿನ ಹಬ್ಬಗಳು",
            yogas: "ಇಂದಿನ ಯೋಗ"
        },
        te: {
            festival_list: "ఈరోజు పండుగలు",
            yogas: "ఈరోజు యోగం"
        }
    };
    
    function flattenObject(obj, prefix = '') {
        let result = {};
        for (let key in obj) {
            if (!obj.hasOwnProperty(key)) continue;
            let value = obj[key];
            let newKey = prefix ? prefix + ' ' + key : key;
            if (value && typeof value === 'object' && !Array.isArray(value)) {
                Object.assign(result, flattenObject(value, newKey));
            } else {
                result[newKey] = value;
            }
        }
        return result;
    }
    
    function formatValue(val) {
        if (val === null || val === undefined) return '';
        
        // Helper function to format keys nicely
        function formatKey(k) {
            return String(k)
                .replace(/_/g, ' ')
                .replace(/\b\w/g, (l) => l.toUpperCase());
        }
        
        // Helper function to apply bold styling to important keys
        function styleKey(k, v) {
            const formattedKey = formatKey(k);
            const importantKeys = ['Festival Name', 'Significance', 'Yoga Name'];
            
            if (importantKeys.includes(formattedKey)) {
                return `<strong style="font-weight: 700; color: #800000;">${formattedKey}:</strong> ${v}`;
            }
            return `<strong>${formattedKey}:</strong> ${v}`;
        }
        
        // Handle arrays
        if (Array.isArray(val)) {
            if (val.length === 0) return '';
            // If array contains only primitives, join with bullet points
            if (val.every(item => typeof item !== 'object' || item === null)) {
                return val.map(item => `• ${item}`).join('<br>');
            }
            // If array contains objects, format each object recursively
            return val.map(item => formatValue(item)).join('<br><br>');
        }

        // Handle objects (but not null)
        if (typeof val === 'object' && val !== null) {
            // If object is Date, format as string
            if (val instanceof Date) return val.toLocaleString();
            // If object has custom toString, use it
            if (typeof val.toString === 'function' && val.toString !== Object.prototype.toString) {
                return val.toString();
            }
            // Recursively format each key-value pair
            return Object.entries(val)
                .map(([k, v]) => styleKey(k, formatValue(v)))
                .join('<br>');
        }

        // Handle strings that might be JSON
        if (typeof val === 'string') {
            try {
                const parsed = JSON.parse(val);
                return formatValue(parsed);
            } catch (e) {
                // Not JSON, return as is
            }
        }

        // Fallback: convert to string
        return String(val);
    }
    
    function displayFestival() {
        const lang = document.getElementById('dinVisheshLang').value;
        const displayDiv = document.getElementById('festival-display');
        
        if (!festivalByLang[lang]) {
            displayDiv.innerHTML = '<div class="no-data">No festivals for today</div>';
            return;
        }
        
        const data = festivalByLang[lang];
        const flattened = flattenObject(data);

        let rows = '';
        for (let key in flattened) {
            let value = formatValue(flattened[key]);
            if (!value || value === '—') continue;

            const keyLower = key.trim().toLowerCase();
            if (keyLower === 'remaining_api_calls' || keyLower === 'status') continue;

            // Remove "response " prefix and normalize key for translation lookup
            const normKey = keyLower
                .replace(/^response\s+/i, '')
                .replace(/\s+/g, '_');
            const translatedLabel = (labelTranslations[lang] && labelTranslations[lang][normKey]) || null;

            let formattedKey = translatedLabel || key
                .replace(/^response\s+/i, '')
                .replace(/\b\w/g, (l) => l.toUpperCase());
            rows += `<tr><td class="festival-key">${formattedKey}</td><td class="festival-value">${value}</td></tr>`;
        }

        if (!rows) {
            displayDiv.innerHTML = '<div class="no-data">No festival data available for ' + lang + '</div>';
            return;
        }

        displayDiv.innerHTML = `<table class="festival-table"><tbody>${rows}</tbody></table>`;
    }
    
    // Display on page load with default language
    document.addEventListener('DOMContentLoaded', function() {
        displayFestival();
    });
</script>

<?php include 'footer.php'; ?>
