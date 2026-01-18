<?php 
include 'header.php';

// Load today's Panchang JSON
$today = date('Y-m-d');
$jsonFile = __DIR__ . '/data/panchang-' . $today . '.json';
$panchangData = null;

if (file_exists($jsonFile)) {
    $panchangData = json_decode(file_get_contents($jsonFile), true);
}

// Helper function to get muhurat value from shubh_muhurat object
function getMuhurat($key, $default = '—') {
    global $panchangData;
    return ($panchangData && isset($panchangData[$key])) ? htmlspecialchars($panchangData[$key]) : $default;
}
?>

<style>@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');html,body{font-family:'Marcellus',serif!important;}</style>

<main class="main-content muhurat-page">
    <section class="muhurat-title">
        <h1>Today's Auspicious Timings</h1>
        <p class="subtitle">(Placeholder indicators — Please consult an expert for advice)</p>
    </section>

    <section class="muhurat-list">
        <div class="muhurat-row">
            <div class="muhurat-label">Marriage</div>
            <div class="muhurat-status">
                <span class="indicator indicator-green" aria-hidden="true"></span>
                <span class="status-text"><?php echo getMuhurat('vivahmuhurat'); ?></span>
            </div>
        </div>

        <div class="muhurat-row">
                <div class="muhurat-label">Housewarming</div>
            <div class="muhurat-status">
                <span class="indicator indicator-orange" aria-hidden="true"></span>
                <span class="status-text"><?php echo getMuhurat('gruhapraveshmuhurat'); ?></span>
            </div>
        </div>

        <div class="muhurat-row">
                <div class="muhurat-label">Vehicle Purchase</div>
            <div class="muhurat-status">
                <span class="indicator indicator-green" aria-hidden="true"></span>
                <span class="status-text"><?php echo getMuhurat('vehiclepurchasemuhurat'); ?></span>
            </div>
        </div>

        <div class="muhurat-row">
                <div class="muhurat-label">Business Start</div>
            <div class="muhurat-status">
                <span class="indicator indicator-red" aria-hidden="true"></span>
                <span class="status-text"><?php echo getMuhurat('businessstartmuhurat'); ?></span>
            </div>
        </div>
    </section>

    <section class="muhurat-note">
        <p>Please consult an expert for exact timings.</p>
    </section>
</main>

<?php include 'footer.php'; ?>
