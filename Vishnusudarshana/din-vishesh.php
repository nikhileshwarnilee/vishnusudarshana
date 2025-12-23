<?php 
include 'header.php';

// Load today's Panchang JSON
$today = date('Y-m-d');
$jsonFile = __DIR__ . '/data/panchang-' . $today . '.json';
$panchangData = null;
$fileNotFound = false;

if (file_exists($jsonFile)) {
    $panchangData = json_decode(file_get_contents($jsonFile), true);
} else {
    $fileNotFound = true;
}

// Helper function to get panchang value
function getDinVishesh($default = '—') {
    global $panchangData;
    return ($panchangData && isset($panchangData['dinvishesh aajchya divsache dharmik sanskrutik mahatva 10 to 20 oli'])) ? htmlspecialchars($panchangData['dinvishesh aajchya divsache dharmik sanskrutik mahatva 10 to 20 oli']) : $default;
}
?>

<main class="main-content din-vishesh-content">
    <section>
        <h1>Today's Significance</h1>
        <div class="din-meta">
            <div class="din-day"><strong>Date:</strong> <?php echo $today; ?></div>
        </div>

        <?php if ($fileNotFound): ?>
            <div style="background-color: #fffbe6; border: 1px solid #ffc069; border-radius: 8px; padding: 16px; color: #8B1538;">
                <p style="margin: 0;">Today's significance information is not available.</p>
            </div>
        <?php else: ?>
            <article class="din-article">
                <p><?php echo getDinVishesh('Today is considered special as per local tradition. Detailed information will be updated soon.'); ?></p>
            </article>
        <?php endif; ?>
    </section>
</main>

<?php include 'footer.php'; ?>
