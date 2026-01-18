<?php include 'header.php'; ?>

<main class="main-content panchang-page" style="background-color:var(--cream-bg);">
        <?php
        // Load latest Panchang JSON from DB for each language
        require_once __DIR__ . '/config/db.php';
        $panchangByLang = [];
        $languages = ['en', 'hi', 'mr', 'gu', 'ka', 'te'];
        try {
            foreach ($languages as $langCode) {
                $stmt = $pdo->prepare("SELECT panchang_json FROM panchang WHERE lang = ? ORDER BY request_date DESC, id DESC LIMIT 1");
                $stmt->execute([$langCode]);
                $row = $stmt->fetch();
                if ($row && $row['panchang_json']) {
                    $panchangByLang[$langCode] = json_decode($row['panchang_json'], true);
                }
            }
        } catch (Exception $e) {
            $panchangByLang = [];
        }
        ?>
        <style>
        .panchang-form-section {
            display: flex;
            justify-content: flex-start;
            align-items: flex-start;
            background: rgba(255,255,255,0.7);
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 24px 0 0 0;
            min-height: unset;
            width: 100%;
        }
        .panchang-form {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            padding: 18px 18px 10px 18px;
            width: 100%;
            max-width: 100%;
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 0 auto;
        }
        .panchang-form-row {
            display: flex;
            flex-direction: row;
            gap: 18px;
            align-items: flex-end;
            justify-content: flex-start;
            width: 100%;
        }
        .panchang-form .form-group {
            display: flex;
            flex-direction: column;
            min-width: 140px;
            flex: 1 1 0;
        }
        .panchang-form label {
            font-weight: 600;
            margin-bottom: 7px;
            color: #2d2d2d;
            letter-spacing: 0.01em;
            font-size: 0.98rem;
        }
        .panchang-form input[type="date"],
        .panchang-form input[type="time"],
        .panchang-form select {
            padding: 8px 10px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            background: #f9f9f9;
            transition: border 0.2s;
            outline: none;
            min-width: 120px;
        }
        .panchang-form input[type="date"]:focus,
        .panchang-form input[type="time"]:focus,
        .panchang-form select:focus {
            border: 1.5px solid #ffd700;
            background: #fffbe6;
        }
        /* Unified dropdown style for city, timezone, and language */
        .panchang-form select {
            background: #f9f9f9;
            border: 1.5px solid #e0e0e0;
            color: #2d2d2d;
            font-weight: 500;
            box-shadow: none;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 1rem;
            min-width: 120px;
            transition: border 0.2s, background 0.2s;
        }
        /* Modern style for city dropdown matching language dropdown */
        .panchang-form select#city {
            background: linear-gradient(90deg, #ffe066 0%, #fffbe6 100%);
            border: 2px solid #800000;
            color: #800000;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(255,215,0,0.08);
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 1.05rem;
            min-width: 140px;
            transition: border 0.2s, background 0.2s, box-shadow 0.2s;
        }
        .panchang-form select#city:focus {
            border: 2px solid #ffd700;
            background: #fffbe6;
            box-shadow: 0 4px 16px rgba(255,215,0,0.13);
        }
        /* Modern style for timezone dropdown matching language dropdown */
        .panchang-form select#tz {
            background: linear-gradient(90deg, #ffe066 0%, #fffbe6 100%);
            border: 2px solid #800000;
            color: #800000;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(255,215,0,0.08);
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 1.05rem;
            min-width: 140px;
            transition: border 0.2s, background 0.2s, box-shadow 0.2s;
        }
        .panchang-form select#tz:focus {
            border: 2px solid #ffd700;
            background: #fffbe6;
            box-shadow: 0 4px 16px rgba(255,215,0,0.13);
        }
        /* Style for language dropdown in form */
        .panchang-form select#lang {
            background: linear-gradient(90deg, #ffe066 0%, #fffbe6 100%);
            border: 2px solid #800000;
            color: #800000;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(255,215,0,0.08);
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 1.05rem;
            min-width: 140px;
            transition: border 0.2s, background 0.2s, box-shadow 0.2s;
        }
        .panchang-form select#lang:focus {
            border: 2px solid #ffd700;
            background: #fffbe6;
            box-shadow: 0 4px 16px rgba(255,215,0,0.13);
        }
        /* Select2 container styling to match */
        .panchang-form .select2-container--default .select2-selection--single {
            background: linear-gradient(90deg, #ffe066 0%, #fffbe6 100%);
            border: 2px solid #800000;
            border-radius: 12px;
            height: 44px;
            padding: 6px 8px;
        }
        .panchang-form .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #800000;
            font-weight: 600;
            line-height: 28px;
        }
        .panchang-form .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px;
        }
        .panchang-form .select2-container--default.select2-container--focus .select2-selection--single {
            border: 2px solid #ffd700;
            background: #fffbe6;
        }
        .panchang-form select:focus {
            border: 1.5px solid #ffd700;
            background-color: #fffbe6;
        }
        .panchang-form .btn-primary {
            background: linear-gradient(90deg, #ffd700 0%, #ffec80 100%);
            color: #2d2d2d;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-size: 1.1rem;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(255,215,0,0.08);
            transition: background 0.2s, box-shadow 0.2s;
            margin-top: 18px;
        }
        .panchang-form .btn-primary:hover {
            background: linear-gradient(90deg, #ffec80 0%, #ffd700 100%);
            box-shadow: 0 4px 16px rgba(255,215,0,0.13);
        }
        /* Responsive styles */
        @media (max-width: 900px) {
            .panchang-form-section {
                padding: 12px 0 0 0;
            }
            .panchang-form {
                max-width: 100%;
                padding: 12px 4vw 8px 4vw;
            }
            .panchang-form-row {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }
        }
        @media (max-width: 600px) {
            .panchang-form-section {
                padding: 6px 0 0 0;
            }
            .panchang-form {
                max-width: 100vw;
                padding: 8px 2vw 6px 2vw;
                border-radius: 10px;
            }
            .panchang-form label {
                font-size: 1rem;
            }
            .panchang-form input[type="date"],
            .panchang-form input[type="time"],
            .panchang-form select {
                font-size: 0.98rem;
                padding: 8px 8px;
            }
            .panchang-form select#city {
                font-size: 1rem;
                padding: 9px 10px;
            }
            .panchang-form .btn-primary {
                font-size: 1rem;
                padding: 10px 10px;
            }
        }
        </style>
    <div class="panchang-form-toggle-wrap" style="margin-bottom:1.2em;">
        <button id="panchangFormToggle" type="button" style="background:linear-gradient(90deg,#ffd700 0%,#ffec80 100%);color:#2d2d2d;font-weight:bold;border:none;border-radius:8px;padding:12px 24px;font-size:1.1rem;cursor:pointer;box-shadow:0 2px 8px rgba(255,215,0,0.08);transition:background 0.2s,box-shadow 0.2s;outline:none;width:100%;text-align:left;display:flex;align-items:center;justify-content:space-between;">
            <span>Get Panchang by Date, City, and Language</span>
            <span id="panchangFormToggleIcon" style="font-size:1.3em;transition:transform 0.2s;">&#x25BC;</span>
        </button>
    </div>
    <form id="panchangForm" class="panchang-form" method="POST" action="" style="display:none;">
                    <script>
                    // Collapsible Panchang Form
                    document.addEventListener('DOMContentLoaded', function() {
                        var toggleBtn = document.getElementById('panchangFormToggle');
                        var form = document.getElementById('panchangForm');
                        var icon = document.getElementById('panchangFormToggleIcon');
                        if(toggleBtn && form && icon) {
                            toggleBtn.addEventListener('click', function() {
                                if(form.style.display === 'none' || form.style.display === '') {
                                    form.style.display = 'flex';
                                    icon.style.transform = 'rotate(180deg)';
                                } else {
                                    form.style.display = 'none';
                                    icon.style.transform = 'rotate(0deg)';
                                }
                            });
                        }
                    });
                    </script>
            <input type="hidden" id="lat" name="lat" value="18.5204" required />
            <input type="hidden" id="lon" name="lon" value="73.8567" required />
            <div class="panchang-form-row">
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required />
                </div>
                <div class="form-group">
                    <label for="time">Time</label>
                    <input type="time" id="time" name="time" required />
                </div>
                <div class="form-group">
                    <label for="city">City</label>
                    <select id="city" name="city" required style="width:100%"></select>
                </div>
            </div>
            <div class="panchang-form-row">
                <div class="form-group">
                    <label for="tz">TimeZone</label>
                    <select id="tz" name="tz" required style="width:100%">
                        <?php
                        include_once __DIR__ . '/timezones.php';
                        $userTz = 'Asia/Kolkata';
                        usort($timezones, function($a, $b) {
                            return $a['offset'] <=> $b['offset'];
                        });
                        foreach ($timezones as $tz) {
                            $selected = ($tz['name'] === $userTz) ? 'selected' : '';
                            $label = $tz['name'] . ' (UTC ' . $tz['offset_str'] . ')';
                            echo "<option value=\"{$tz['name']}\" $selected>$label</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="lang">Language</label>
                    <select id="lang" name="lang" required>
                    <option value="en">English (English)</option>
                    <option value="hi">Hindi (हिन्दी)</option>
                    <option value="mr">Marathi (मराठी)</option>
                    <option value="gu">Gujarati (ગુજરાતી)</option>
                    <option value="ka">Kannada (ಕನ್ನಡ)</option>
                    <option value="te">Telugu (తెలుగు)</option>
                    </select>
                </div>
                <div class="form-group" style="align-self: flex-end;">
                    <button type="submit" class="btn btn-primary">Get Panchang</button>
                </div>
            </div>
        </form>
        <div class="todays-panchang-title-row" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;margin:1.5em 0 0.5em 0;">
            <div id="todays-panchang-title" style="font-size:1.35em;font-weight:bold;color:#800000;display:none;"></div>
            <div class="panchang-lang-static-select" style="min-width:160px;margin-top:0;">
                <select style="background-color: #f9f9f9; padding:7px 12px;border-radius:8px;border:1.5px solid #666666;font-size:1em;color:#b60a0a;">
                    <option value="en">English (English)</option>
                    <option value="hi">Hindi (हिन्दी)</option>
                    <option value="mr">Marathi (मराठी)</option>
                    <option value="gu">Gujarati (ગુજરાતી)</option>
                    <option value="ka">Kannada (ಕನ್ನಡ)</option>
                    <option value="te">Telugu (తెలుగు)</option>

                </select>
            </div>
        </div>
        <div id="panchang-blank-table" style="margin-top:2em;"></div>
                <script>
                // Panchang translations for all supported languages
                const panchangTranslations = {
                    en: {
                        "Tithi": "Tithi", "Tithi Name": "Tithi Name", "Tithi Number": "Tithi Number", "Tithi Next Tithi": "Tithi Next Tithi", "Tithi Type": "Tithi Type", "Tithi Diety": "Tithi Diety", "Tithi Start": "Tithi Start", "Tithi End": "Tithi End", "Tithi Meaning": "Tithi Meaning", "Tithi Special": "Tithi Special",
                        "Nakshatra": "Nakshatra", "Nakshatra Pada": "Nakshatra Pada", "Nakshatra Name": "Nakshatra Name", "Nakshatra Number": "Nakshatra Number", "Nakshatra Lord": "Nakshatra Lord", "Nakshatra Diety": "Nakshatra Diety", "Nakshatra Start": "Nakshatra Start", "Nakshatra Next Nakshatra": "Nakshatra Next Nakshatra", "Nakshatra End": "Nakshatra End", "Nakshatra Auspicious Disha": "Nakshatra Auspicious Disha", "Nakshatra Meaning": "Nakshatra Meaning", "Nakshatra Special": "Nakshatra Special", "Nakshatra Summary": "Nakshatra Summary",
                        "Yoga": "Yoga", "Yoga Name": "Yoga Name", "Yoga Number": "Yoga Number", "Yoga Start": "Yoga Start", "Yoga End": "Yoga End", "Yoga Next Yoga": "Yoga Next Yoga", "Yoga Meaning": "Yoga Meaning", "Yoga Special": "Yoga Special",
                        "Karana": "Karana", "Karana Name": "Karana Name", "Karana Number": "Karana Number", "Karana Type": "Karana Type", "Karana Lord": "Karana Lord", "Karana Diety": "Karana Diety", "Karana Start": "Karana Start", "Karana End": "Karana End", "Karana Special": "Karana Special", "Karana Next Karana": "Karana Next Karana",
                        "Sun": "Sun", "Sun Position Zodiac": "Sun Position Zodiac", "Sun Position Rasi No": "Sun Position Rasi No", "Sun Position Nakshatra No": "Sun Position Nakshatra No", "Sun Position Sun Degree At Rise": "Sun Position Sun Degree At Rise",
                        "Moon": "Moon", "Moon Position Moon Degree": "Moon Position Moon Degree",
                        "Gulika": "Gulika", "Gulika": "Gulika",
                        "Advanced Details": "Advanced Details", "Day Name": "Day Name", "Ayanamsa Name": "Ayanamsa Name", "Ayanamsa Number": "Ayanamsa Number", "Rasi Name": "Rasi Name", "Advanced Details Sun Rise": "Sun Rise", "Advanced Details Sun Set": "Sun Set", "Advanced Details Moon Rise": "Moon Rise", "Advanced Details Moon Set": "Moon Set", "Advanced Details Next Full Moon": "Next Full Moon", "Advanced Details Next New Moon": "Next New Moon", "Advanced Details Masa Amanta Number": "Masa Amanta Number", "Advanced Details Masa Amanta Date": "Masa Amanta Date", "Advanced Details Masa Amanta Name": "Masa Amanta Name", "Advanced Details Masa Alternate Amanta Name": "Masa Alternate Amanta Name", "Advanced Details Masa Amanta Start": "Masa Amanta Start", "Advanced Details Masa Amanta End": "Masa Amanta End", "Advanced Details Masa Adhik Maasa": "Masa Adhik Maasa", "Advanced Details Masa Ayana": "Masa Ayana", "Advanced Details Masa Real Ayana": "Masa Real Ayana", "Advanced Details Masa Tamil Month Num": "Masa Tamil Month Num", "Advanced Details Masa Tamil Month": "Masa Tamil Month", "Advanced Details Masa Tamil Day": "Masa Tamil Day", "Advanced Details Masa Purnimanta Date": "Masa Purnimanta Date", "Advanced Details Masa Purnimanta Number": "Masa Purnimanta Number", "Advanced Details Masa Purnimanta Name": "Masa Purnimanta Name", "Advanced Details Masa Alternate Purnimanta Name": "Masa Alternate Purnimanta Name", "Advanced Details Masa Purnimanta Start": "Masa Purnimanta Start", "Advanced Details Masa Purnimanta End": "Masa Purnimanta End", "Advanced Details Masa Moon Phase": "Masa Moon Phase", "Advanced Details Masa Paksha": "Masa Paksha", "Advanced Details Masa Ritu": "Masa Ritu", "Advanced Details Masa Ritu Tamil": "Masa Ritu Tamil", "Advanced Details Moon Yogini Nivas": "Moon Yogini Nivas", "Advanced Details Ahargana": "Ahargana", "Advanced Details Years Kali": "Years Kali", "Advanced Details Years Saka": "Years Saka", "Advanced Details Years Vikram Samvaat": "Years Vikram Samvaat", "Advanced Details Years Kali Samvaat Number": "Years Kali Samvaat Number", "Advanced Details Years Kali Samvaat Name": "Years Kali Samvaat Name", "Advanced Details Years Vikram Samvaat Number": "Years Vikram Samvaat Number", "Advanced Details Years Vikram Samvaat Name": "Years Vikram Samvaat Name", "Advanced Details Years Saka Samvaat Number": "Years Saka Samvaat Number", "Advanced Details Years Saka Samvaat Name": "Years Saka Samvaat Name", "Advanced Details Vaara": "Vaara", "Advanced Details Disha Shool": "Disha Shool", "Advanced Details Abhijit Muhurta Start": "Abhijit Muhurta Start", "Advanced Details Abhijit Muhurta End": "Abhijit Muhurta End",
                        "Rahukaal": "Rahukaal", "Rahukaal": "Rahukaal",
                        "Yamakanta": "Yamakanta", "Yamakanta": "Yamakanta"
                    },
                    hi: {
                        "Tithi": "तिथि", "Tithi Name": "तिथि नाम", "Tithi Number": "तिथि क्रमांक", "Tithi Next Tithi": "अगली तिथि", "Tithi Type": "तिथि प्रकार", "Tithi Diety": "तिथि देवता", "Tithi Start": "तिथि प्रारंभ", "Tithi End": "तिथि समाप्ति", "Tithi Meaning": "तिथि अर्थ", "Tithi Special": "विशेष तिथि",
                        "Nakshatra": "नक्षत्र", "Nakshatra Pada": "नक्षत्र पद", "Nakshatra Name": "नक्षत्र नाम", "Nakshatra Number": "नक्षत्र क्रमांक", "Nakshatra Lord": "नक्षत्र स्वामी", "Nakshatra Diety": "नक्षत्र देवता", "Nakshatra Start": "नक्षत्र प्रारंभ", "Nakshatra Next Nakshatra": "अगला नक्षत्र", "Nakshatra End": "नक्षत्र समाप्ति", "Nakshatra Auspicious Disha": "शुभ दिशा", "Nakshatra Meaning": "नक्षत्र अर्थ", "Nakshatra Special": "विशेष नक्षत्र", "Nakshatra Summary": "नक्षत्र सारांश",
                        "Yoga": "योग", "Yoga Name": "योग नाम", "Yoga Number": "योग क्रमांक", "Yoga Start": "योग प्रारंभ", "Yoga End": "योग समाप्ति", "Yoga Next Yoga": "अगला योग", "Yoga Meaning": "योग अर्थ", "Yoga Special": "विशेष योग",
                        "Karana": "करण", "Karana Name": "करण नाम", "Karana Number": "करण क्रमांक", "Karana Type": "करण प्रकार", "Karana Lord": "करण स्वामी", "Karana Diety": "करण देवता", "Karana Start": "करण प्रारंभ", "Karana End": "करण समाप्ति", "Karana Special": "विशेष करण", "Karana Next Karana": "अगला करण",
                        "Sun": "सूर्य", "Sun Position Zodiac": "सूर्य राशि", "Sun Position Rasi No": "राशि क्रमांक", "Sun Position Nakshatra No": "नक्षत्र क्रमांक", "Sun Position Sun Degree At Rise": "सूर्य उदय पर डिग्री",
                        "Moon": "चंद्र", "Moon Position Moon Degree": "चंद्र डिग्री",
                        "Gulika": "गुलिक", "Gulika": "गुलिक",
                        "Advanced Details": "विस्तृत विवरण", "Day Name": "दिन का नाम", "Ayanamsa Name": "अयनांश नाम", "Ayanamsa Number": "अयनांश क्रमांक", "Rasi Name": "राशि नाम", "Advanced Details Sun Rise": "सूर्य उदय", "Advanced Details Sun Set": "सूर्य अस्त", "Advanced Details Moon Rise": "चंद्र उदय", "Advanced Details Moon Set": "चंद्र अस्त", "Advanced Details Next Full Moon": "अगला पूर्णिमा", "Advanced Details Next New Moon": "अगला अमावस्या", "Advanced Details Masa Amanta Number": "मास अमांत क्रमांक", "Advanced Details Masa Amanta Date": "मास अमांत तिथि", "Advanced Details Masa Amanta Name": "मास अमांत नाम", "Advanced Details Masa Alternate Amanta Name": "वैकल्पिक अमांत नाम", "Advanced Details Masa Amanta Start": "मास अमांत प्रारंभ", "Advanced Details Masa Amanta End": "मास अमांत समाप्ति", "Advanced Details Masa Adhik Maasa": "अधिक मास", "Advanced Details Masa Ayana": "मास अयन", "Advanced Details Masa Real Ayana": "मास वास्तविक अयन", "Advanced Details Masa Tamil Month Num": "तमिल मास क्रमांक", "Advanced Details Masa Tamil Month": "तमिल मास", "Advanced Details Masa Tamil Day": "तमिल दिन", "Advanced Details Masa Purnimanta Date": "पूर्णिमांत तिथि", "Advanced Details Masa Purnimanta Number": "पूर्णिमांत क्रमांक", "Advanced Details Masa Purnimanta Name": "पूर्णिमांत नाम", "Advanced Details Masa Alternate Purnimanta Name": "वैकल्पिक पूर्णिमांत नाम", "Advanced Details Masa Purnimanta Start": "पूर्णिमांत प्रारंभ", "Advanced Details Masa Purnimanta End": "पूर्णिमांत समाप्ति", "Advanced Details Masa Moon Phase": "चंद्र चरण", "Advanced Details Masa Paksha": "पक्ष", "Advanced Details Masa Ritu": "ऋतु", "Advanced Details Masa Ritu Tamil": "तमिल ऋतु", "Advanced Details Moon Yogini Nivas": "योगिनी निवास", "Advanced Details Ahargana": "अहर्गण", "Advanced Details Years Kali": "कलियुग वर्ष", "Advanced Details Years Saka": "शक वर्ष", "Advanced Details Years Vikram Samvaat": "विक्रम संवत्", "Advanced Details Years Kali Samvaat Number": "कलियुग संवत् क्रमांक", "Advanced Details Years Kali Samvaat Name": "कलियुग संवत् नाम", "Advanced Details Years Vikram Samvaat Number": "विक्रम संवत् क्रमांक", "Advanced Details Years Vikram Samvaat Name": "विक्रम संवत् नाम", "Advanced Details Years Saka Samvaat Number": "शक संवत् क्रमांक", "Advanced Details Years Saka Samvaat Name": "शक संवत् नाम", "Advanced Details Vaara": "वार", "Advanced Details Disha Shool": "दिशा शूल", "Advanced Details Abhijit Muhurta Start": "अभिजीत मुहूर्त प्रारंभ", "Advanced Details Abhijit Muhurta End": "अभिजीत मुहूर्त समाप्ति",
                        "Rahukaal": "राहुकाल", "Rahukaal": "राहुकाल",
                        "Yamakanta": "यमकांता", "Yamakanta": "यमकांता"
                    },
                    mr: {
                        "Tithi": "तिथी", "Tithi Name": "तिथी नाव", "Tithi Number": "तिथी क्रमांक", "Tithi Next Tithi": "पुढील तिथी", "Tithi Type": "तिथी प्रकार", "Tithi Diety": "तिथी देवता", "Tithi Start": "तिथी प्रारंभ", "Tithi End": "तिथी समाप्ती", "Tithi Meaning": "तिथी अर्थ", "Tithi Special": "विशेष तिथी",
                        "Nakshatra": "नक्षत्र", "Nakshatra Pada": "नक्षत्र पाद", "Nakshatra Name": "नक्षत्र नाव", "Nakshatra Number": "नक्षत्र क्रमांक", "Nakshatra Lord": "नक्षत्र स्वामी", "Nakshatra Diety": "नक्षत्र देवता", "Nakshatra Start": "नक्षत्र प्रारंभ", "Nakshatra Next Nakshatra": "पुढील नक्षत्र", "Nakshatra End": "नक्षत्र समाप्ती", "Nakshatra Auspicious Disha": "शुभ दिशा", "Nakshatra Meaning": "नक्षत्र अर्थ", "Nakshatra Special": "विशेष नक्षत्र", "Nakshatra Summary": "नक्षत्र सारांश",
                        "Yoga": "योग", "Yoga Name": "योग नाव", "Yoga Number": "योग क्रमांक", "Yoga Start": "योग प्रारंभ", "Yoga End": "योग समाप्ती", "Yoga Next Yoga": "पुढील योग", "Yoga Meaning": "योग अर्थ", "Yoga Special": "विशेष योग",
                        "Karana": "करण", "Karana Name": "करण नाव", "Karana Number": "करण क्रमांक", "Karana Type": "करण प्रकार", "Karana Lord": "करण स्वामी", "Karana Diety": "करण देवता", "Karana Start": "करण प्रारंभ", "Karana End": "करण समाप्ती", "Karana Special": "विशेष करण", "Karana Next Karana": "पुढील करण",
                        "Sun": "सूर्य", "Sun Position Zodiac": "सूर्य राशी", "Sun Position Rasi No": "राशी क्रमांक", "Sun Position Nakshatra No": "नक्षत्र क्रमांक", "Sun Position Sun Degree At Rise": "सूर्य उदयावर डिग्री",
                        "Moon": "चंद्र", "Moon Position Moon Degree": "चंद्र डिग्री",
                        "Gulika": "गुलिक", "Gulika": "गुलिक",
                        "Advanced Details": "विस्तृत माहिती", "Day Name": "दिवसाचे नाव", "Ayanamsa Name": "अयनांश नाव", "Ayanamsa Number": "अयनांश क्रमांक", "Rasi Name": "राशी नाव", "Advanced Details Sun Rise": "सूर्य उदय", "Advanced Details Sun Set": "सूर्यास्त", "Advanced Details Moon Rise": "चंद्र उदय", "Advanced Details Moon Set": "चंद्रास्त", "Advanced Details Next Full Moon": "पुढील पौर्णिमा", "Advanced Details Next New Moon": "पुढील अमावस्या", "Advanced Details Masa Amanta Number": "मास अमांत क्रमांक", "Advanced Details Masa Amanta Date": "मास अमांत तारीख", "Advanced Details Masa Amanta Name": "मास अमांत नाव", "Advanced Details Masa Alternate Amanta Name": "पर्यायी अमांत नाव", "Advanced Details Masa Amanta Start": "मास अमांत प्रारंभ", "Advanced Details Masa Amanta End": "मास अमांत समाप्ती", "Advanced Details Masa Adhik Maasa": "अधिक मास", "Advanced Details Masa Ayana": "मास अयन", "Advanced Details Masa Real Ayana": "मास वास्तविक अयन", "Advanced Details Masa Tamil Month Num": "तमिळ मास क्रमांक", "Advanced Details Masa Tamil Month": "तमिळ मास", "Advanced Details Masa Tamil Day": "तमिळ दिवस", "Advanced Details Masa Purnimanta Date": "पूर्णिमांत तारीख", "Advanced Details Masa Purnimanta Number": "पूर्णिमांत क्रमांक", "Advanced Details Masa Purnimanta Name": "पूर्णिमांत नाव", "Advanced Details Masa Alternate Purnimanta Name": "पर्यायी पूर्णिमांत नाव", "Advanced Details Masa Purnimanta Start": "पूर्णिमांत प्रारंभ", "Advanced Details Masa Purnimanta End": "पूर्णिमांत समाप्ती", "Advanced Details Masa Moon Phase": "चंद्र फेज", "Advanced Details Masa Paksha": "पक्ष", "Advanced Details Masa Ritu": "ऋतु", "Advanced Details Masa Ritu Tamil": "तमिळ ऋतु", "Advanced Details Moon Yogini Nivas": "योगिनी निवास", "Advanced Details Ahargana": "अहर्गण", "Advanced Details Years Kali": "कली वर्ष", "Advanced Details Years Saka": "शक वर्ष", "Advanced Details Years Vikram Samvaat": "विक्रम संवत", "Advanced Details Years Kali Samvaat Number": "कली संवत क्रमांक", "Advanced Details Years Kali Samvaat Name": "कली संवत नाव", "Advanced Details Years Vikram Samvaat Number": "विक्रम संवत क्रमांक", "Advanced Details Years Vikram Samvaat Name": "विक्रम संवत नाव", "Advanced Details Years Saka Samvaat Number": "शक संवत क्रमांक", "Advanced Details Years Saka Samvaat Name": "शक संवत नाव", "Advanced Details Vaara": "वार", "Advanced Details Disha Shool": "दिशा शूल", "Advanced Details Abhijit Muhurta Start": "अभिजीत मुहूर्त प्रारंभ", "Advanced Details Abhijit Muhurta End": "अभिजीत मुहूर्त समाप्ती",
                        "Rahukaal": "राहुकाल", "Rahukaal": "राहुकाल",
                        "Yamakanta": "यमकांता", "Yamakanta": "यमकांता"
                    },
                    gu: {
                        "Tithi": "તિથિ", "Tithi Name": "તિથિ નામ", "Tithi Number": "તિથિ ક્રમાંક", "Tithi Next Tithi": "આગામી તિથિ", "Tithi Type": "તિથિ પ્રકાર", "Tithi Diety": "તિથિ દેવતા", "Tithi Start": "તિથિ આરંભ", "Tithi End": "તિથિ અંત", "Tithi Meaning": "તિથિ અર્થ", "Tithi Special": "વિશેષ તિથિ",
                        "Nakshatra": "નક્ષત્ર", "Nakshatra Pada": "નક્ષત્ર પાદ", "Nakshatra Name": "નક્ષત્ર નામ", "Nakshatra Number": "નક્ષત્ર ક્રમાંક", "Nakshatra Lord": "નક્ષત્ર સ્વામી", "Nakshatra Diety": "નક્ષત્ર દેવતા", "Nakshatra Start": "નક્ષત્ર આરંભ", "Nakshatra Next Nakshatra": "આગામી નક્ષત્ર", "Nakshatra End": "નક્ષત્ર અંત", "Nakshatra Auspicious Disha": "શુભ દિશા", "Nakshatra Meaning": "નક્ષત્ર અર્થ", "Nakshatra Special": "વિશેષ નક્ષત્ર", "Nakshatra Summary": "નક્ષત્ર સારાંશ",
                        "Yoga": "યોગ", "Yoga Name": "યોગ નામ", "Yoga Number": "યોગ ક્રમાંક", "Yoga Start": "યોગ આરંભ", "Yoga End": "યોગ અંત", "Yoga Next Yoga": "આગામી યોગ", "Yoga Meaning": "યોગ અર્થ", "Yoga Special": "વિશેષ યોગ",
                        "Karana": "કરણ", "Karana Name": "કરણ નામ", "Karana Number": "કરણ ક્રમાંક", "Karana Type": "કરણ પ્રકાર", "Karana Lord": "કરણ સ્વામી", "Karana Diety": "કરણ દેવતા", "Karana Start": "કરણ આરંભ", "Karana End": "કરણ અંત", "Karana Special": "વિશેષ કરણ", "Karana Next Karana": "આગામી કરણ",
                        "Sun": "સૂર્ય", "Sun Position Zodiac": "સૂર્ય રાશિ", "Sun Position Rasi No": "રાશિ ક્રમાંક", "Sun Position Nakshatra No": "નક્ષત્ર ક્રમાંક", "Sun Position Sun Degree At Rise": "સૂર્ય ઉદયે ડિગ્રી",
                        "Moon": "ચંદ્ર", "Moon Position Moon Degree": "ચંદ્ર ડિગ્રી",
                        "Gulika": "ગુલિક", "Gulika": "ગુલિક",
                        "Advanced Details": "વિસ્તૃત વિગતો", "Day Name": "દિવસનું નામ", "Ayanamsa Name": "અયનામ્સા નામ", "Ayanamsa Number": "અયનામ્સા ક્રમાંક", "Rasi Name": "રાશિ નામ", "Advanced Details Sun Rise": "સૂર્ય ઉદય", "Advanced Details Sun Set": "સૂર્યાસ્ત", "Advanced Details Moon Rise": "ચંદ્ર ઉદય", "Advanced Details Moon Set": "ચંદ્રાસ્ત", "Advanced Details Next Full Moon": "આગામી પૂર્ણિમા", "Advanced Details Next New Moon": "આગામી અમાવસ્યા", "Advanced Details Masa Amanta Number": "માસ અમાંત ક્રમાંક", "Advanced Details Masa Amanta Date": "માસ અમાંત તારીખ", "Advanced Details Masa Amanta Name": "માસ અમાંત નામ", "Advanced Details Masa Alternate Amanta Name": "વૈકલ્પિક અમાંત નામ", "Advanced Details Masa Amanta Start": "માસ અમાંત આરંભ", "Advanced Details Masa Amanta End": "માસ અમાંત અંત", "Advanced Details Masa Adhik Maasa": "અધિક માસ", "Advanced Details Masa Ayana": "માસ અયન", "Advanced Details Masa Real Ayana": "માસ વાસ્તવિક અયન", "Advanced Details Masa Tamil Month Num": "તમિલ માસ ક્રમાંક", "Advanced Details Masa Tamil Month": "તમિલ માસ", "Advanced Details Masa Tamil Day": "તમિલ દિવસ", "Advanced Details Masa Purnimanta Date": "પૂર્ણિમાંત તારીખ", "Advanced Details Masa Purnimanta Number": "પૂર્ણિમાંત ક્રમાંક", "Advanced Details Masa Purnimanta Name": "પૂર્ણિમાંત નામ", "Advanced Details Masa Alternate Purnimanta Name": "વૈકલ્પિક પૂર્ણિમાંત નામ", "Advanced Details Masa Purnimanta Start": "પૂર્ણિમાંત આરંભ", "Advanced Details Masa Purnimanta End": "પૂર્ણિમાંત અંત", "Advanced Details Masa Moon Phase": "ચંદ્ર ફેઝ", "Advanced Details Masa Paksha": "પક્ષ", "Advanced Details Masa Ritu": "ઋતુ", "Advanced Details Masa Ritu Tamil": "તમિલ ઋતુ", "Advanced Details Moon Yogini Nivas": "યોગિની નિવાસ", "Advanced Details Ahargana": "અહર્ગણ", "Advanced Details Years Kali": "કળી વર્ષ", "Advanced Details Years Saka": "શક વર્ષ", "Advanced Details Years Vikram Samvaat": "વિક્રમ સંવત", "Advanced Details Years Kali Samvaat Number": "કળી સંવત ક્રમાંક", "Advanced Details Years Kali Samvaat Name": "કળી સંવત નામ", "Advanced Details Years Vikram Samvaat Number": "વિક્રમ સંવત ક્રમાંક", "Advanced Details Years Vikram Samvaat Name": "વિક્રમ સંવત નામ", "Advanced Details Years Saka Samvaat Number": "શક સંવત ક્રમાંક", "Advanced Details Years Saka Samvaat Name": "શક સંવત નામ", "Advanced Details Vaara": "વાર", "Advanced Details Disha Shool": "દિશા શૂલ", "Advanced Details Abhijit Muhurta Start": "અભિજીત મુહૂર્ત આરંભ", "Advanced Details Abhijit Muhurta End": "અભિજીત મુહૂર્ત અંત",
                        "Rahukaal": "રાહુકાળ", "Rahukaal": "રાહુકાળ",
                        "Yamakanta": "યમકાંતા", "Yamakanta": "યમકાંતા"
                    },
                    ka: {
                        "Tithi": "ತಿಥಿ", "Tithi Name": "ತಿಥಿ ಹೆಸರು", "Tithi Number": "ತಿಥಿ ಸಂಖ್ಯೆ", "Tithi Next Tithi": "ಮುಂದಿನ ತಿಥಿ", "Tithi Type": "ತಿಥಿ ಪ್ರಕಾರ", "Tithi Diety": "ತಿಥಿ ದೇವತೆ", "Tithi Start": "ತಿಥಿ ಆರಂಭ", "Tithi End": "ತಿಥಿ ಅಂತ್ಯ", "Tithi Meaning": "ತಿಥಿ ಅರ್ಥ", "Tithi Special": "ವಿಶೇಷ ತಿಥಿ",
                        "Nakshatra": "ನಕ್ಷತ್ರ", "Nakshatra Pada": "ನಕ್ಷತ್ರ ಪಾದ", "Nakshatra Name": "ನಕ್ಷತ್ರ ಹೆಸರು", "Nakshatra Number": "ನಕ್ಷತ್ರ ಸಂಖ್ಯೆ", "Nakshatra Lord": "ನಕ್ಷತ್ರ ಸ್ವಾಮಿ", "Nakshatra Diety": "ನಕ್ಷತ್ರ ದೇವತೆ", "Nakshatra Start": "ನಕ್ಷತ್ರ ಆರಂಭ", "Nakshatra Next Nakshatra": "ಮುಂದಿನ ನಕ್ಷತ್ರ", "Nakshatra End": "ನಕ್ಷತ್ರ ಅಂತ್ಯ", "Nakshatra Auspicious Disha": "ಶುಭ ದಿಕ್ಕು", "Nakshatra Meaning": "ನಕ್ಷತ್ರ ಅರ್ಥ", "Nakshatra Special": "ವಿಶೇಷ ನಕ್ಷತ್ರ", "Nakshatra Summary": "ನಕ್ಷತ್ರ ಸಾರಾಂಶ",
                        "Yoga": "ಯೋಗ", "Yoga Name": "ಯೋಗ ಹೆಸರು", "Yoga Number": "ಯೋಗ ಸಂಖ್ಯೆ", "Yoga Start": "ಯೋಗ ಆರಂಭ", "Yoga End": "ಯೋಗ ಅಂತ್ಯ", "Yoga Next Yoga": "ಮುಂದಿನ ಯೋಗ", "Yoga Meaning": "ಯೋಗ ಅರ್ಥ", "Yoga Special": "ವಿಶೇಷ ಯೋಗ",
                        "Karana": "ಕರಣ", "Karana Name": "ಕರಣ ಹೆಸರು", "Karana Number": "ಕರಣ ಸಂಖ್ಯೆ", "Karana Type": "ಕರಣ ಪ್ರಕಾರ", "Karana Lord": "ಕರಣ ಸ್ವಾಮಿ", "Karana Diety": "ಕರಣ ದೇವತೆ", "Karana Start": "ಕರಣ ಆರಂಭ", "Karana End": "ಕರಣ ಅಂತ್ಯ", "Karana Special": "ವಿಶೇಷ ಕರಣ", "Karana Next Karana": "ಮುಂದಿನ ಕರಣ",
                        "Sun": "ಸೂರ್ಯ", "Sun Position Zodiac": "ಸೂರ್ಯ ರಾಶಿ", "Sun Position Rasi No": "ರಾಶಿ ಸಂಖ್ಯೆ", "Sun Position Nakshatra No": "ನಕ್ಷತ್ರ ಸಂಖ್ಯೆ", "Sun Position Sun Degree At Rise": "ಸೂರ್ಯ ಉದಯದಲ್ಲಿ ಡಿಗ್ರಿ",
                        "Moon": "ಚಂದ್ರ", "Moon Position Moon Degree": "ಚಂದ್ರ ಡಿಗ್ರಿ",
                        "Gulika": "ಗುಲಿಕ", "Gulika": "ಗುಲಿಕ",
                        "Advanced Details": "ವಿಸ್ತೃತ ವಿವರಗಳು", "Day Name": "ದಿನದ ಹೆಸರು", "Ayanamsa Name": "ಅಯನಾಂಶ ಹೆಸರು", "Ayanamsa Number": "ಅಯನಾಂಶ ಸಂಖ್ಯೆ", "Rasi Name": "ರಾಶಿ ಹೆಸರು", "Advanced Details Sun Rise": "ಸೂರ್ಯೋದಯ", "Advanced Details Sun Set": "ಸೂರ್ಯಾಸ್ತ", "Advanced Details Moon Rise": "ಚಂದ್ರೋದಯ", "Advanced Details Moon Set": "ಚಂದ್ರಾಸ್ತ", "Advanced Details Next Full Moon": "ಮುಂದಿನ ಪೂರ್ಣಚಂದ್ರ", "Advanced Details Next New Moon": "ಮುಂದಿನ ಅಮಾವಾಸ್ಯೆ", "Advanced Details Masa Amanta Number": "ಮಾಸ ಅಮಾಂತ ಸಂಖ್ಯೆ", "Advanced Details Masa Amanta Date": "ಮಾಸ ಅಮಾಂತ ದಿನಾಂಕ", "Advanced Details Masa Amanta Name": "ಮಾಸ ಅಮಾಂತ ಹೆಸರು", "Advanced Details Masa Alternate Amanta Name": "ಪರ್ಯಾಯ ಅಮಾಂತ ಹೆಸರು", "Advanced Details Masa Amanta Start": "ಮಾಸ ಅಮಾಂತ ಆರಂಭ", "Advanced Details Masa Amanta End": "ಮಾಸ ಅಮಾಂತ ಅಂತ್ಯ", "Advanced Details Masa Adhik Maasa": "ಅಧಿಕ ಮಾಸ", "Advanced Details Masa Ayana": "ಮಾಸ ಅಯನ", "Advanced Details Masa Real Ayana": "ಮಾಸ ನಿಜವಾದ ಅಯನ", "Advanced Details Masa Tamil Month Num": "ತಮಿಳು ಮಾಸ ಸಂಖ್ಯೆ", "Advanced Details Masa Tamil Month": "ತಮಿಳು ಮಾಸ", "Advanced Details Masa Tamil Day": "ತಮಿಳು ದಿನ", "Advanced Details Masa Purnimanta Date": "ಪೂರ್ಣಿಮಾಂತ ದಿನಾಂಕ", "Advanced Details Masa Purnimanta Number": "ಪೂರ್ಣಿಮಾಂತ ಸಂಖ್ಯೆ", "Advanced Details Masa Purnimanta Name": "ಪೂರ್ಣಿಮಾಂತ ಹೆಸರು", "Advanced Details Masa Alternate Purnimanta Name": "ಪರ್ಯಾಯ ಪೂರ್ಣಿಮಾಂತ ಹೆಸರು", "Advanced Details Masa Purnimanta Start": "ಪೂರ್ಣಿಮಾಂತ ಆರಂಭ", "Advanced Details Masa Purnimanta End": "ಪೂರ್ಣಿಮಾಂತ ಅಂತ್ಯ", "Advanced Details Masa Moon Phase": "ಚಂದ್ರ ಹಂತ", "Advanced Details Masa Paksha": "ಪಕ್ಷ", "Advanced Details Masa Ritu": "ಋತು", "Advanced Details Masa Ritu Tamil": "ತಮಿಳು ಋತು", "Advanced Details Moon Yogini Nivas": "ಯೋಗಿನಿ ನಿವಾಸ", "Advanced Details Ahargana": "ಅಹರ್ಗಣ", "Advanced Details Years Kali": "ಕಲಿ ವರ್ಷ", "Advanced Details Years Saka": "ಶಕ ವರ್ಷ", "Advanced Details Years Vikram Samvaat": "ವಿಕ್ರಮ ಸಂವತ್ಸರ", "Advanced Details Years Kali Samvaat Number": "ಕಲಿ ಸಂವತ್ಸರ ಸಂಖ್ಯೆ", "Advanced Details Years Kali Samvaat Name": "ಕಲಿ ಸಂವತ್ಸರ ಹೆಸರು", "Advanced Details Years Vikram Samvaat Number": "ವಿಕ್ರಮ ಸಂವತ್ಸರ ಸಂಖ್ಯೆ", "Advanced Details Years Vikram Samvaat Name": "ವಿಕ್ರಮ ಸಂವತ್ಸರ ಹೆಸರು", "Advanced Details Years Saka Samvaat Number": "ಶಕ ಸಂವತ್ಸರ ಸಂಖ್ಯೆ", "Advanced Details Years Saka Samvaat Name": "ಶಕ ಸಂವತ್ಸರ ಹೆಸರು", "Advanced Details Vaara": "ವಾರ", "Advanced Details Disha Shool": "ದಿಶಾ ಶೂಲ", "Advanced Details Abhijit Muhurta Start": "ಅಭಿಜಿತ್ ಮುಹೂರ್ತ ಆರಂಭ", "Advanced Details Abhijit Muhurta End": "ಅಭಿಜಿತ್ ಮುಹೂರ್ತ ಅಂತ್ಯ",
                        "Rahukaal": "ರಾಹುಕಾಲ", "Rahukaal": "ರಾಹುಕಾಲ",
                        "Yamakanta": "ಯಮಕಾಂತ", "Yamakanta": "ಯಮಕಾಂತ"
                    },
                    te: {
                        "Tithi": "తిథి", "Tithi Name": "తిథి పేరు", "Tithi Number": "తిథి సంఖ్య", "Tithi Next Tithi": "తదుపరి తిథి", "Tithi Type": "తిథి రకం", "Tithi Diety": "తిథి దేవత", "Tithi Start": "తిథి ప్రారంభం", "Tithi End": "తిథి ముగింపు", "Tithi Meaning": "తిథి అర్థం", "Tithi Special": "ప్రత్యేక తిథి",
                        "Nakshatra": "నక్షత్రం", "Nakshatra Pada": "నక్షత్ర పదం", "Nakshatra Name": "నక్షత్ర పేరు", "Nakshatra Number": "నక్షత్ర సంఖ్య", "Nakshatra Lord": "నక్షత్ర స్వామి", "Nakshatra Diety": "నక్షత్ర దేవత", "Nakshatra Start": "నక్షత్ర ప్రారంభం", "Nakshatra Next Nakshatra": "తదుపరి నక్షత్రం", "Nakshatra End": "నక్షత్ర ముగింపు", "Nakshatra Auspicious Disha": "శుభ దిశ", "Nakshatra Meaning": "నక్షత్ర అర్థం", "Nakshatra Special": "ప్రత్యేక నక్షత్రం", "Nakshatra Summary": "నక్షత్ర సారాంశం",
                        "Yoga": "యోగం", "Yoga Name": "యోగం పేరు", "Yoga Number": "యోగం సంఖ్య", "Yoga Start": "యోగం ప్రారంభం", "Yoga End": "యోగం ముగింపు", "Yoga Next Yoga": "తదుపరి యోగం", "Yoga Meaning": "యోగం అర్థం", "Yoga Special": "ప్రత్యేక యోగం",
                        "Karana": "కరణం", "Karana Name": "కరణం పేరు", "Karana Number": "కరణం సంఖ్య", "Karana Type": "కరణం రకం", "Karana Lord": "కరణం స్వామి", "Karana Diety": "కరణం దేవత", "Karana Start": "కరణం ప్రారంభం", "Karana End": "కరణం ముగింపు", "Karana Special": "ప్రత్యేక కరణం", "Karana Next Karana": "తదుపరి కరణం",
                        "Sun": "సూర్యుడు", "Sun Position Zodiac": "సూర్యుడు రాశి", "Sun Position Rasi No": "రాశి సంఖ్య", "Sun Position Nakshatra No": "నక్షత్ర సంఖ్య", "Sun Position Sun Degree At Rise": "సూర్యోదయ సమయంలో డిగ్రీ",
                        "Moon": "చంద్రుడు", "Moon Position Moon Degree": "చంద్రుడు డిగ్రీ",
                        "Gulika": "గులిక", "Gulika": "గులిక",
                        "Advanced Details": "విస్తృత వివరాలు", "Day Name": "రోజు పేరు", "Ayanamsa Name": "అయనాంశ పేరు", "Ayanamsa Number": "అయనాంశ సంఖ్య", "Rasi Name": "రాశి పేరు", "Advanced Details Sun Rise": "సూర్యోదయం", "Advanced Details Sun Set": "సూర్యాస్తమయం", "Advanced Details Moon Rise": "చంద్రోదయం", "Advanced Details Moon Set": "చంద్రాస్తమయం", "Advanced Details Next Full Moon": "తదుపరి పూర్ణచంద్రుడు", "Advanced Details Next New Moon": "తదుపరి అమావాస్య", "Advanced Details Masa Amanta Number": "మాస అమాంత సంఖ్య", "Advanced Details Masa Amanta Date": "మాస అమాంత తేదీ", "Advanced Details Masa Amanta Name": "మాస అమాంత పేరు", "Advanced Details Masa Alternate Amanta Name": "ప్రత్యామ్నాయ అమాంత పేరు", "Advanced Details Masa Amanta Start": "మాస అమాంత ప్రారంభం", "Advanced Details Masa Amanta End": "మాస అమాంత ముగింపు", "Advanced Details Masa Adhik Maasa": "అధిక మాసం", "Advanced Details Masa Ayana": "మాస అయన", "Advanced Details Masa Real Ayana": "మాస నిజమైన అయన", "Advanced Details Masa Tamil Month Num": "తమిళ మాస సంఖ్య", "Advanced Details Masa Tamil Month": "తమిళ మాసం", "Advanced Details Masa Tamil Day": "తమిళ రోజు", "Advanced Details Masa Purnimanta Date": "పూర్ణిమాంత తేదీ", "Advanced Details Masa Purnimanta Number": "పూర్ణిమాంత సంఖ్య", "Advanced Details Masa Purnimanta Name": "పూర్ణిమాంత పేరు", "Advanced Details Masa Alternate Purnimanta Name": "ప్రత్యామ్నాయ పూర్ణిమాంత పేరు", "Advanced Details Masa Purnimanta Start": "పూర్ణిమాంత ప్రారంభం", "Advanced Details Masa Purnimanta End": "పూర్ణిమాంత ముగింపు", "Advanced Details Masa Moon Phase": "చంద్రుడు దశ", "Advanced Details Masa Paksha": "పక్షం", "Advanced Details Masa Ritu": "ఋతువు", "Advanced Details Masa Ritu Tamil": "తమిళ ఋతువు", "Advanced Details Moon Yogini Nivas": "యోగిని నివాసం", "Advanced Details Ahargana": "అహర్గణ", "Advanced Details Years Kali": "కలి సంవత్సరం", "Advanced Details Years Saka": "శక సంవత్సరం", "Advanced Details Years Vikram Samvaat": "విక్రమ్ సంవత్", "Advanced Details Years Kali Samvaat Number": "కలి సంవత్ సంఖ్య", "Advanced Details Years Kali Samvaat Name": "కలి సంవత్ పేరు", "Advanced Details Years Vikram Samvaat Number": "విక్రమ్ సంవత్ సంఖ్య", "Advanced Details Years Vikram Samvaat Name": "విక్రమ్ సంవత్ పేరు", "Advanced Details Years Saka Samvaat Number": "శక సంవత్ సంఖ్య", "Advanced Details Years Saka Samvaat Name": "శక సంవత్ పేరు", "Advanced Details Vaara": "వారము", "Advanced Details Disha Shool": "దిశా శూల్", "Advanced Details Abhijit Muhurta Start": "అభిజిత్ ముహూర్త ప్రారంభం", "Advanced Details Abhijit Muhurta End": "అభిజిత్ ముహూర్త ముగింపు",
                        "Rahukaal": "రాహుకాలం", "Rahukaal": "రాహుకాలం",
                        "Yamakanta": "యమకాంత", "Yamakanta": "యమకాంత"
                    }
                };

                // Table structure definition
                const panchangTableStructure = [
                    { cat: "Tithi", keys: ["Tithi Name", "Tithi Number", "Tithi Next Tithi", "Tithi Type", "Tithi Diety", "Tithi Start", "Tithi End", "Tithi Meaning", "Tithi Special"] },
                    { cat: "Nakshatra", keys: ["Nakshatra Pada", "Nakshatra Name", "Nakshatra Number", "Nakshatra Lord", "Nakshatra Diety", "Nakshatra Start", "Nakshatra Next Nakshatra", "Nakshatra End", "Nakshatra Auspicious Disha", "Nakshatra Meaning", "Nakshatra Special", "Nakshatra Summary"] },
                    { cat: "Yoga", keys: ["Yoga Name", "Yoga Number", "Yoga Start", "Yoga End", "Yoga Next Yoga", "Yoga Meaning", "Yoga Special"] },
                    { cat: "Karana", keys: ["Karana Name", "Karana Number", "Karana Type", "Karana Lord", "Karana Diety", "Karana Start", "Karana End", "Karana Special", "Karana Next Karana"] },
                    { cat: "Sun", keys: ["Sun Position Zodiac", "Sun Position Rasi No", "Sun Position Nakshatra No", "Sun Position Sun Degree At Rise"] },
                    { cat: "Moon", keys: ["Moon Position Moon Degree"] },
                    { cat: "Gulika", keys: ["Gulika"] },
                    { cat: "Advanced Details", keys: ["Day Name", "Ayanamsa Name", "Ayanamsa Number", "Rasi Name", "Advanced Details Sun Rise", "Advanced Details Sun Set", "Advanced Details Moon Rise", "Advanced Details Moon Set", "Advanced Details Next Full Moon", "Advanced Details Next New Moon", "Advanced Details Masa Amanta Number", "Advanced Details Masa Amanta Date", "Advanced Details Masa Amanta Name", "Advanced Details Masa Alternate Amanta Name", "Advanced Details Masa Amanta Start", "Advanced Details Masa Amanta End", "Advanced Details Masa Adhik Maasa", "Advanced Details Masa Ayana", "Advanced Details Masa Real Ayana", "Advanced Details Masa Tamil Month Num", "Advanced Details Masa Tamil Month", "Advanced Details Masa Tamil Day", "Advanced Details Masa Purnimanta Date", "Advanced Details Masa Purnimanta Number", "Advanced Details Masa Purnimanta Name", "Advanced Details Masa Alternate Purnimanta Name", "Advanced Details Masa Purnimanta Start", "Advanced Details Masa Purnimanta End", "Advanced Details Masa Moon Phase", "Advanced Details Masa Paksha", "Advanced Details Masa Ritu", "Advanced Details Masa Ritu Tamil", "Advanced Details Moon Yogini Nivas", "Advanced Details Ahargana", "Advanced Details Years Kali", "Advanced Details Years Saka", "Advanced Details Years Vikram Samvaat", "Advanced Details Years Kali Samvaat Number", "Advanced Details Years Kali Samvaat Name", "Advanced Details Years Vikram Samvaat Number", "Advanced Details Years Vikram Samvaat Name", "Advanced Details Years Saka Samvaat Number", "Advanced Details Years Saka Samvaat Name", "Advanced Details Vaara", "Advanced Details Disha Shool", "Advanced Details Abhijit Muhurta Start", "Advanced Details Abhijit Muhurta End"] },
                    { cat: "Rahukaal", keys: ["Rahukaal"] },
                    { cat: "Yamakanta", keys: ["Yamakanta"] }
                ];

                // Helper: flatten nested JSON to key-value pairs
                function flatten(obj, prefix) {
                    let out = {};
                    for (let k in obj) {
                        if (!obj.hasOwnProperty(k)) continue;
                        let v = obj[k];
                        let key = prefix ? prefix + " " + k : k;
                        if (v && typeof v === "object" && !Array.isArray(v)) {
                            let flat = flatten(v, key);
                            for (let fk in flat) out[fk] = flat[fk];
                        } else {
                            out[key] = v;
                        }
                    }
                    return out;
                }

                // Helper: format key to match translation keys
                function formatTitle(key) {
                    return key.replace(/^Response[ ._]?/i, "").replace(/[._]/g, " ").replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                }

                // Render Panchang table with data (or blank if no data)
                function renderPanchangTable(lang, jsonData) {
                    const t = panchangTranslations[lang] || panchangTranslations['en'];
                    let html = `<style>
                        .panchang-table tr:not(.cat-row):hover { background: #fff7e6; transition: background 0.2s; }
                        .panchang-table td { padding: 12px 20px; font-size: 1.04em; color: #2d2d2d; border-bottom: 1px solid #f3e6c4; vertical-align: top; }
                        .panchang-table tr:last-child td { border-bottom: none; }
                        .panchang-table .panchang-key { font-weight: 600; color: #4f3a1a; letter-spacing: 0.01em; }
                        .panchang-table .panchang-value { color: #2d2d2d; }
                        .panchang-table .date-row td { background: #f7f7d7; color: #7c5a00; font-weight: 600; border-radius: 10px 10px 0 0; }
                        .panchang-table .cat-row td { background: #800000; color: #FFD700; font-weight: bold; text-align: left; padding: 13px 20px; font-size: 1.08em; letter-spacing: 0.5px; border-radius: 0; }
                    </style>`;
                    html += `<table class="panchang-table"><tbody>`;
                    // If data, flatten and map
                    let flat = jsonData ? flatten(jsonData, "") : null;
                    let cleanedFlat = {};
                    if (flat) {
                        for (let k in flat) {
                            cleanedFlat[formatTitle(k)] = flat[k];
                        }
                    }
                    // Extract date and day for Tithi header
                    let panchangDate = "";
                    let panchangDay = "";
                    if (cleanedFlat) {
                        // Try to get day name from various possible keys
                        panchangDay = cleanedFlat["Day Name"] || cleanedFlat["Advanced Details Day Name"] || 
                                      cleanedFlat["Response Day Name"] || cleanedFlat["Response Advanced Details Day Name"] ||
                                      cleanedFlat["Vaara"] || cleanedFlat["Advanced Details Vaara"] || "";
                        // Try to get date from various possible keys (API uses different formats)
                        panchangDate = cleanedFlat["Date"] || cleanedFlat["Response Date"] || 
                                       cleanedFlat["Advanced Details Date"] || cleanedFlat["Response Advanced Details Date"] ||
                                       cleanedFlat["Masa Amanta Date"] || cleanedFlat["Advanced Details Masa Amanta Date"] || "";
                    }
                    // If still no date/day found, try directly from jsonData
                    if (jsonData && !panchangDate) {
                        panchangDate = jsonData.date || (jsonData.response && jsonData.response.date) || "";
                    }
                    if (jsonData && !panchangDay) {
                        panchangDay = jsonData.day_name || jsonData.vaara || 
                                     (jsonData.response && (jsonData.response.day_name || jsonData.response.vaara)) ||
                                     (jsonData.response && jsonData.response.advanced_details && jsonData.response.advanced_details.day_name) || "";
                    }
                    for (const section of panchangTableStructure) {
                        let catTitle = t[section.cat] || section.cat;
                        // For Tithi section, append date and day
                        if (section.cat === "Tithi" && (panchangDate || panchangDay)) {
                            let dateDay = [];
                            if (panchangDate) dateDay.push(panchangDate);
                            if (panchangDay) dateDay.push(panchangDay);
                            if (dateDay.length > 0) {
                                catTitle += " - " + dateDay.join(", ");
                            }
                        }
                        html += `<tr class="cat-row"><td colspan="2">${catTitle}</td></tr>`;
                        for (const key of section.keys) {
                            let val = (cleanedFlat && cleanedFlat[key] !== undefined && cleanedFlat[key] !== null) ? cleanedFlat[key] : "";
                            if (Array.isArray(val)) val = JSON.stringify(val);
                            html += `<tr><td class="panchang-key">${t[key] || key}</td><td class="panchang-value">${val}</td></tr>`;
                        }
                    }
                    html += `</tbody></table>`;
                    document.getElementById('panchang-blank-table').innerHTML = html;
                }

                document.addEventListener('DOMContentLoaded', function() {
                    // Get Panchang JSON for all languages from PHP
                    let panchangByLang = <?php echo json_encode($panchangByLang); ?>;
                    // Initial render with default language
                    let lang = document.querySelector('.panchang-lang-static-select select').value || 'en';
                    renderPanchangTable(lang, panchangByLang[lang] || null);
                    // Listen for language change
                    document.querySelector('.panchang-lang-static-select select').addEventListener('change', function() {
                        renderPanchangTable(this.value, panchangByLang[this.value] || null);
                    });
                });
                </script>
        <div id="panchang-result" style="margin-top:2em;"></div>

        <!-- Share Section -->
        <div class="panchang-share-section">
            <h3>Share Today's Panchang</h3>
            <div class="share-buttons">
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://' . $_SERVER['HTTP_HOST'] . '/panchang.php') ?>" target="_blank" class="share-btn facebook">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    Facebook
                </a>
                <a href="https://twitter.com/intent/tweet?url=<?= urlencode('https://' . $_SERVER['HTTP_HOST'] . '/panchang.php') ?>&text=<?= urlencode("Today's Panchang - Check Daily Hindu Calendar, Tithi, Nakshatra & Muhurat") ?>" target="_blank" class="share-btn twitter">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                    Twitter
                </a>
                <a href="https://wa.me/?text=<?= urlencode("Today's Panchang - Check Daily Hindu Calendar, Tithi, Nakshatra & Muhurat\n" . 'https://' . $_SERVER['HTTP_HOST'] . '/panchang.php') ?>" target="_blank" class="share-btn whatsapp">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                    WhatsApp
                </a>
                <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?= urlencode('https://' . $_SERVER['HTTP_HOST'] . '/panchang.php') ?>&title=<?= urlencode("Today's Panchang - Daily Hindu Calendar") ?>" target="_blank" class="share-btn linkedin">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                    LinkedIn
                </a>
                <button type="button" class="share-btn copy-link" onclick="copyPanchangLink()">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                    Copy Link
                </button>
            </div>
        </div>
        <script>
        function copyPanchangLink() {
            var url = window.location.href;
            navigator.clipboard.writeText(url).then(function() {
                var btn = document.querySelector('.share-btn.copy-link');
                var originalText = btn.innerHTML;
                btn.innerHTML = '<svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> Copied!';
                btn.style.background = '#28a745';
                setTimeout(function() {
                    btn.innerHTML = originalText;
                    btn.style.background = '#800000';
                }, 2000);
            });
        }
        </script>

        <!-- Navigation Section -->
        <div class="panchang-nav-section">
            <a class="panchang-nav-link" href="din-vishesh.php">
                <span class="panchang-nav-label">&#8592; Today's Festivals</span>
            </a>
            <a class="panchang-nav-link" href="horoscope.php" style="text-align:right;">
                <span class="panchang-nav-label">Daily Horoscope &#8594;</span>
            </a>
        </div>

        <!-- jQuery and Select2 for searchable dropdown -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script>
        // Replace with your RapidAPI key
        const GEODB_API_KEY = 'a97887c188msh3ecd5008d8b0aeep16818ajsn8280d180ea53';
        $(document).ready(function() {
            // AJAX form submit for Panchang
            $('#panchangForm').on('submit', function(e) {
                e.preventDefault();
                var formDataArr = $(this).serializeArray();
                var formDataObj = {};
                formDataArr.forEach(function(item) {
                    if(item.name !== 'city') formDataObj[item.name] = item.value;
                });
                // If timezone is empty or not selected, set default to Asia/Kolkata
                if (!formDataObj.tz || formDataObj.tz === '') {
                    formDataObj.tz = 'Asia/Kolkata';
                }
                // Format date as DD/MM/YYYY
                if(formDataObj.date) {
                    var d = new Date(formDataObj.date);
                    var day = (d.getDate()).toString().padStart(2, '0');
                    var month = (d.getMonth()+1).toString().padStart(2, '0');
                    var year = d.getFullYear();
                    formDataObj.date = day + '/' + month + '/' + year;
                }
                // Convert timezone name to offset in 0.5 jumps using Intl API
                if(formDataObj.tz) {
                    try {
                        var tzName = formDataObj.tz;
                        // Get the offset in minutes for the selected timezone at the selected date
                        var dateForTz = formDataObj.date ? formDataObj.date.split('/').reverse().join('-') : undefined;
                        var refDate = dateForTz ? new Date(dateForTz + 'T12:00:00Z') : new Date();
                        // Use DateTimeFormat to get offset in minutes
                        var dtf = new Intl.DateTimeFormat('en-US', { timeZone: tzName, timeZoneName: 'short' });
                        var parts = dtf.formatToParts(refDate);
                        var tzPart = parts.find(function(p){return p.type==='timeZoneName'});
                        var match = tzPart && tzPart.value.match(/GMT([+-]\d{1,2})(?::(\d{2}))?/);
                        var offset = 0;
                        if(match) {
                            offset = parseInt(match[1],10);
                            if(match[2]) offset += parseInt(match[2],10)/60 * (offset<0?-1:1);
                        }
                        // Round to nearest 0.5
                        var offsetHalf = Math.round(offset * 2) / 2;
                        formDataObj.tz = offsetHalf;
                    } catch(e) {
                        formDataObj.tz = '';
                    }
                }
                // Remove any existing loading indicator
                $('#panchang-loading').remove();
                $('#panchang-blank-table').before('<div id="panchang-loading" style="text-align:center;padding:20px;"><em>Loading Panchang data...</em></div>');
                $.ajax({
                    url: 'scripts/panchang3rdparty.php',
                    method: 'POST',
                    data: formDataObj,
                    dataType: 'json',
                    success: function(data) {
                        $('#panchang-loading').remove();
                        if (data.error) {
                            $('#panchang-result').html('<span style="color:red">'+data.error+'</span>');
                        } else {
                            // Get the selected language from the form
                            var selectedLang = $('#lang').val() || 'en';
                            // Update the static language dropdown to match
                            $('.panchang-lang-static-select select').val(selectedLang);
                            // Render the API response in the existing table with translations
                            renderPanchangTable(selectedLang, data);
                            // Clear any previous error messages
                            $('#panchang-result').html('');
                            // Scroll to the table
                            $('html, body').animate({
                                scrollTop: $('#panchang-blank-table').offset().top - 100
                            }, 500);
                        }
                    },
                    error: function(xhr) {
                        $('#panchang-loading').remove();
                        $('#panchang-result').html('<span style="color:red">Error fetching Panchang data.</span>');
                    }
                });
            });
            // Set time and timezone from user's system
            var now = new Date();
            var hours = now.getHours();
            var minutes = now.getMinutes();
            // Always use 24-hour format
            var timeValue = hours.toString().padStart(2, '0') + ':' + minutes.toString().padStart(2, '0');
            $('#time').val(timeValue);
            var tzOffset = -now.getTimezoneOffset() / 60;
            var tzRounded = Math.round(tzOffset * 4) / 4;
            $('#tz').val(tzRounded);

            $('#city').select2({
                placeholder: 'Search for a city',
                allowClear: true,
                minimumInputLength: 2,
                ajax: {
                    url: 'https://wft-geo-db.p.rapidapi.com/v1/geo/cities',
                    dataType: 'json',
                    delay: 250,
                    beforeSend: function (jqXHR) {
                        jqXHR.setRequestHeader('X-RapidAPI-Key', GEODB_API_KEY);
                        jqXHR.setRequestHeader('X-RapidAPI-Host', 'wft-geo-db.p.rapidapi.com');
                    },
                    data: function(params) {
                        return {
                            namePrefix: params.term,
                            limit: 10
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.data.map(function(city) {
                                return {
                                    id: city.id,
                                    text: city.city + ', ' + city.country,
                                    lat: city.latitude,
                                    lon: city.longitude,
                                    tz: city.timezone
                                };
                            })
                        };
                    },
                    cache: true,
                    error: function(xhr, status, error) {
                        if (error !== 'abort') {
                            alert('City search error: ' + error);
                        }
                    }
                }
            });
            // Set Solapur as default city on load

            // Set Solapur as default city and fetch its lat/lon from GeoDB API
            var solapurOption = new Option('Solapur, India', 'Solapur, India', true, true);
            $('#city').append(solapurOption).trigger('change');
            // Fetch lat/lon for Solapur, India
            $.ajax({
                url: 'https://wft-geo-db.p.rapidapi.com/v1/geo/cities',
                method: 'GET',
                data: { namePrefix: 'Solapur', countryIds: 'IN', limit: 1 },
                dataType: 'json',
                headers: {
                    'X-RapidAPI-Key': GEODB_API_KEY,
                    'X-RapidAPI-Host': 'wft-geo-db.p.rapidapi.com'
                },
                success: function(data) {
                    if (data.data && data.data.length > 0) {
                        var city = data.data[0];
                        $('#lat').val(city.latitude);
                        $('#lon').val(city.longitude);
                    }
                }
            });

            $('#city').on('select2:select', function(e) {
                var data = e.params.data;
                $('#lat').val(data.lat);
                $('#lon').val(data.lon);
                // Do NOT update timezone field
            });
            $('#city').on('select2:clear', function(e) {
                $('#lat').val('');
                $('#lon').val('');
                $('#tz').val('');
            });
            // Enable search for timezone dropdown
            $('#tz').select2({
                placeholder: 'Select a timezone',
                allowClear: true,
                matcher: function(params, data) {
                    // If there are no search terms, return all data
                    if ($.trim(params.term) === '') {
                        return data;
                    }
                    if (typeof data.text === 'undefined') {
                        return null;
                    }
                    // Case-insensitive contains match
                    if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                        return data;
                    }
                    // Return `null` if the term should not be displayed
                    return null;
                }
            });
        });
        </script>
    </section>

    <!-- Panchang API response will be shown here after form submission -->
</main>

        <style>
        .todays-panchang-title-row { flex-wrap: wrap; }
        .todays-panchang-title-row > #todays-panchang-title { flex: 1 1 auto; }
        .panchang-lang-static-select { flex: 0 0 auto; margin-left: 1.5em; }
        .panchang-lang-static-select select {
            background: linear-gradient(90deg, #ffe066 0%, #fffbe6 100%);
            border: 2px solid #800000;
            color: #800000;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(255,215,0,0.08);
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 1.05rem;
            min-width: 140px;
            transition: border 0.2s, background 0.2s, box-shadow 0.2s;
        }
        .panchang-lang-static-select select:focus {
            border: 2px solid #ffd700;
            background: #fffbe6;
            outline: none;
        }
        /* Share Section Styles */
        .panchang-share-section {
            margin-top: 50px;
            padding: 30px;
            background: linear-gradient(135deg, #f9f3f0, #fef9f6);
            border-radius: 16px;
            text-align: center;
        }
        .panchang-share-section h3 {
            color: #800000;
            font-size: 1.5em;
            margin-bottom: 20px;
        }

        /* Navigation Section Styles */
        .panchang-nav-section {
            margin: 50px 0 0 0;
            padding: 24px 0 0 0;
            border-top: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        .panchang-nav-link {
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
        .panchang-nav-link:hover {
            color: #b36b00 !important;
            background: #ffe5d0;
        }
        .panchang-nav-label {
            font-size: 1em;
            color: #800000;
            font-weight: 600;
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
        @media (max-width: 700px) {
            .todays-panchang-title-row { flex-direction: column; align-items: flex-start; }
            .panchang-lang-static-select { margin-left: 0; margin-top: 0.7em; width: 100%; }
            .panchang-lang-static-select select { width: 100%; }
            .panchang-share-section {
                padding: 20px 15px;
            }
            .share-buttons {
                gap: 10px;
            }
            .share-btn {
                padding: 10px 16px;
                font-size: 0.9rem;
            }
        }
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html, body {
    font-family: 'Marcellus', serif !important;
}
</style>




<?php include 'footer.php'; ?>
