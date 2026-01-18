<?php 
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
    }
</style>

<main class="main-content din-vishesh-page">
    <div class="din-container">
        <div class="din-header">
            <h1 class="din-title">Today's Special Day</h1>
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
    </div>
</main>

<script>
    // Festival data from PHP
    const festivalByLang = <?php echo json_encode($festivalByLang); ?>;
    
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
        if (Array.isArray(val)) return JSON.stringify(val);
        return String(val);
    }
    
    function displayFestival() {
        const lang = document.getElementById('dinVisheshLang').value;
        const displayDiv = document.getElementById('festival-display');
        
        if (!festivalByLang[lang]) {
            displayDiv.innerHTML = '<div class="no-data">No festival data available for ' + lang + '</div>';
            return;
        }
        
        const data = festivalByLang[lang];
        const flattened = flattenObject(data);

        let rows = '';
        for (let key in flattened) {
            let value = formatValue(flattened[key]);
            if (value && value !== '—') {
                let formattedKey = key
                    .replace(/^response\s+/i, '')
                    .replace(/\b\w/g, (l) => l.toUpperCase());
                rows += `<tr><td class="festival-key">${formattedKey}</td><td class="festival-value">${value}</td></tr>`;
            }
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
