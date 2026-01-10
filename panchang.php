<?php include 'header.php';

// Load today's panchang JSON file
$today = date('Y-m-d');
$jsonFile = __DIR__ . '/data/panchang-' . $today . '.json';

$jsonFilePath = $jsonFile;

$panchangData = null;
$fileNotFound = false;

if (file_exists($jsonFile)) {
    $panchangData = json_decode(file_get_contents($jsonFile), true);
} else {
    $fileNotFound = true;
}

// Helper function to get value from JSON
function getPanchangValue($key, $default = '—') {
    global $panchangData;
    return ($panchangData && isset($panchangData[$key])) ? htmlspecialchars($panchangData[$key]) : $default;
}

?>

<main class="main-content panchang-page" style="background-color:#FFD700;">
    <header class="panchang-title">
        <h1>Today's Complete Panchang</h1>
        <p class="subtitle">(Information — For reference only)</p>
    </header>

    <?php if ($fileNotFound): ?>
        <section class="panchang-error">
            <p>Panchang not available for today.</p>
        </section>
    <?php else: ?>
        <div class="panchang-details">
            <div class="panchang-row">
                <div class="label">Date</div>
                <div class="value"><?php echo getPanchangValue('date'); ?></div>
            </div>
            <div class="panchang-row">
                <div class="label">Weekday</div>
                <div class="value"><?php echo getPanchangValue('weekday'); ?></div>
            </div>
            <div class="panchang-row">
                <div class="label">Shaka</div>
                <div class="value"><?php echo getPanchangValue('shaka'); ?></div>
            </div>
            <div class="panchang-row">
                <div class="label">Samvatsar</div>
                <div class="value"><?php echo getPanchangValue('samvatsar'); ?></div>
            </div>
            <div class="panchang-row">
                <div class="label">Ayan</div>
                <div class="value"><?php echo getPanchangValue('ayan'); ?></div>
            </div>
            <div class="panchang-row">
                <div class="label">Rutu</div>
                <div class="value"><?php echo getPanchangValue('rutu'); ?></div>
            </div>
            <div class="panchang-row">
                <div class="label">Month</div>
                <div class="value"><?php echo getPanchangValue('maas'); ?></div>
            </div>
            <div class="panchang-row">
                <div class="label">Paksha</div>
                <div class="value"><?php echo getPanchangValue('paksha'); ?></div>
            </div>
            <div class="panchang-row">
                <div class="label">Tithi</div>
                <div class="value"><?php echo getPanchangValue('tithi'); ?></div>
            </div>
            <div class="panchang-row">
                <div class="label">Nakshatra</div>
                <div class="value"><?php echo getPanchangValue('nakshatra'); ?></div>
            </div>
            <div class="panchang-row">
                <div class="label">Yoga</div>
                <div class="value"><?php echo getPanchangValue('yog'); ?></div>
            </div>
            <div class="panchang-row">
                <div class="label">Karana</div>
                <div class="value"><?php echo getPanchangValue('karan'); ?></div>
            </div>
            <div class="panchang-row">
                <div class="label">Sunrise</div>
                <div class="value"><?php echo getPanchangValue('sunrise Pune Solapur sathi andajit suryoday vel'); ?></div>
            </div>
            <div class="panchang-row">
                <div class="label">Sunset</div>
                <div class="value"><?php echo getPanchangValue('sunset Pune Solapur sathi andajit suryast vel'); ?></div>
            </div>
            <div class="panchang-row">
                <div class="label">Inauspicious Timings</div>
                <div class="value"><?php echo getPanchangValue('rahukaal aajcha rahukal vel Pune Solapur sathi andajit'); ?></div>
            </div>
            <div class="panchang-row">
                <div class="label">Auspicious/ Inauspicious Summary</div>
                <div class="value"><?php echo getPanchangValue('shubhashubh aajcha shubh ashubh divas saransh'); ?></div>
            </div>
        </div>
    <?php endif; ?>

    <footer class="panchang-disclaimer">
        <hr class="divider" />
        <p>“The above Panchang information is for general guidance only. Please consult an expert for rituals.”</p>
    </footer>
</main>

<?php include 'footer.php'; ?>
