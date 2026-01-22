<?php
require_once 'header.php';
$category = $_GET['category'] ?? '';

$categories = [
    'appointment' => [
        'title' => 'Appointment Booking',
        'description' => 'Book your appointment online at your convenience without calls or visits. Choose a suitable time and receive guided support from anywhere. Each appointment is limited to one topic only. Additional questions can be added before checkout at ₹50 per question. We respect your preferred appointment date; however, the final schedule will be confirmed after the appointment is accepted.',
        'services' => [
            'Online Appointment',
                
        ],
        'delivery' => 'Online or In-person',
        'type' => 'Paid Appointment',
    ],
    'birth-child' => [
        'title' => 'Birth & Child Services',
        'description' => 'Online guidance for naming, samskaras, and important child-related rituals. Simple digital process to plan sacred rites with comfort and clarity.',
        'services' => [
            'Namkaran',
            'Samskaras',
            'Child Rituals',
            'Janma Kundali',
                'many more',
        ],
        'delivery' => 'Online (reports delivered digitally)',
        'type' => 'Paid Services',
    ],
    'marriage-matching' => [
        'title' => 'Marriage & Matching',
        'description' => 'Kundali matching and marriage guidance provided through online consultation. Get accurate insights and advice easily without physical meetings.',
        'services' => [
            'Kundali Matching',
            'Guna Milan',
            'Marriage Guidance',
            'Compatibility Check',
                'many more',
        ],
        'delivery' => 'Online Consultation',
        'type' => 'Paid Services',
    ],
    'astrology-consultation' => [
        'title' => 'Astrology Consultation',
        'description' => 'Personalised astrology consultations conducted fully online for your ease. Receive trusted guidance for life matters from the comfort of your home.',
        'services' => [
            'Horoscope Reading',
            'Career Guidance',
            'Health Insights',
            'Life Predictions',
                'many more',
        ],
        'delivery' => 'Online or In-person Consultation',
        'type' => 'Paid Consultation',
    ],
    'muhurat-event' => [
        'title' => 'Muhurat & Event Guidance',
        'description' => 'Get auspicious timings and event guidance through online consultation. Plan important moments correctly without unnecessary delays or travel.',
        'services' => [
            'Auspicious Timing',
            'Wedding Muhurat',
            'Griha Pravesh',
            'Event Planning',
                'many more',
        ],
        'delivery' => 'Online Guidance',
        'type' => 'Free or Paid (based on requirement)',
    ],
    'pooja-vastu-enquiry' => [
        'title' => 'Pooja, Ritual & Vastu Enquiry',
        'description' => 'Enquire online for pooja, shanti rituals, dosh nivaran, yagya, and vastu. All guidance and coordination handled digitally for a smooth experience.',
        'services' => [
            'Pooja Enquiry',
            'Shanti Rituals',
            'Dosh Nivaran',
            'Vastu Guidance',
                'many more',
        ],
        'delivery' => 'Offline or On-site Visit',
        'type' => 'Enquiry Based (No Online Payment)',
    ],
];

if (!$category || !isset($categories[$category])) {
    echo '<h2>Invalid or missing category</h2>';
    echo '<a href="services.php">&larr; Back to Services</a>';
    require_once 'footer.php';
    exit;
}

$cat = $categories[$category];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($cat['title']); ?> - Service Details</title>
    <style>
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html,body{font-family:'Marcellus',serif!important;}
body {
    font-family: Arial, sans-serif;
    margin: 0;
    background: #FFD700;
    min-height: 10vh;
}
.main-content {
    max-width: 480px;
    margin: 0 auto;
    background: transparent;
    padding: 16px 8px 24px 8px;
    min-height: 10vh;
}
.detail-header {
    text-align: center;
    margin-bottom: 12px;
}
.detail-icon-large {
    font-size: 2.1em;
    margin-bottom: 4px;
}
.detail-title {
    font-size: 1.18em;
    font-weight: bold;
    margin: 0;
}
.detail-section {
    margin-bottom: 14px;
}
.detail-description {
    color: #444;
    font-size: 0.98em;
    margin: 0 0 6px 0;
}
.meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 0.93em;
    color: #444;
    margin-bottom: 10px;
    align-items: center;
    justify-content: flex-start;
}
.meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
    background: #fff0f0;
    border-radius: 12px;
    padding: 4px 14px;
    font-size: 0.93em;
    box-shadow: 0 2px 8px #e0bebe33;
    border: 1px solid #f3caca;
}
.meta-icon { font-size: 1em; opacity: 0.7; }
.tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 4px;
}
.tag {
    background: #f9eaea;
    border: 1px solid #e0bebe;
    color: #a03c3c;
    border-radius: 14px;
    padding: 4px 12px;
    font-size: 0.93em;
    box-shadow: 0 2px 8px #e0bebe33;
    margin-bottom: 1px;
}
.how-works-section { margin-bottom: 10px; }
.how-works-inline {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: flex-start;
    align-items: center;
    font-size: 0.93em;
}
.how-step {
    display: flex;
    align-items: center;
    gap: 3px;
    background: #fffbe7;
    border-radius: 12px;
    padding: 3px 10px 3px 6px;
    font-size: 0.93em;
    box-shadow: 0 2px 8px #e0bebe33;
    white-space: nowrap;
}
.how-dot.maroon-dot {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    background: #800000;
    border: 1.5px solid #800000;
    color: #fff;
    border-radius: 50%;
    font-weight: 600;
    font-size: 0.93em;
    margin-right: 4px;
    box-shadow: 0 1px 2px #80000022;
}
.proceed-btn.maroon-btn {
    display: inline-block;
    background: #800000;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 12px 28px;
    font-size: 1.05em;
    margin-top: 10px;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.15s;
    font-weight: 600;
    box-shadow: 0 2px 8px #80000022;
}
.proceed-btn.maroon-btn:active {
    background: #5a0000;
}
.cat-helper-text {
    color: #888;
    font-size: 0.93em;
    margin-top: 8px;
    margin-bottom: 0;
}
@media (max-width: 700px) {
    .main-content { padding: 8px 2px 16px 2px; }
    .meta-row { flex-direction: column; gap: 4px; align-items: flex-start; }
    .how-works-inline { flex-direction: ; gap: 4px; align-items: flex-start; }
}
    </style>
</head>
<body>
<main class="main-content" style="background-color:var(--cream-bg);">
    <!-- Service Detail Header -->
    <section class="detail-header">
        <div class="detail-icon-large">
            <?php // Use same icon as services.php for each category
            $icons = [
                'appointment' => '📅',
                'birth-child' => '👶',
                'marriage-matching' => '💍',
                'astrology-consultation' => '🗣️',
                'muhurat-event' => '⏰',
                'pooja-vastu-enquiry' => '🏠',
            ];
            echo $icons[$category] ?? '🗂️';
            ?>
        </div>
        <h1 class="detail-title"><?php echo htmlspecialchars($cat['title']); ?></h1>
    </section>

    <!-- Service Description -->
    <section class="detail-section">
        <h3>Description</h3>
        <p class="detail-description"><?php echo htmlspecialchars($cat['description']); ?></p>
    </section>

    

    <!-- Services Included -->
    <section class="detail-section">
        <h3>Services Included</h3>
        <div class="tags">
            <?php foreach ($cat['services'] as $service): ?>
                <span class="tag"><?php echo htmlspecialchars($service); ?></span>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Procedure Section (inline, no cards) -->
    <section class="how-works-section">
        <h3>How This Service Works</h3>
        <div class="how-works-inline">
            <span class="how-step"><span class="how-dot maroon-dot">1</span>Fill the form</span>
            <span class="how-step"><span class="how-dot maroon-dot">2</span>Submit details</span>
            <span class="how-step"><span class="how-dot maroon-dot">3</span>Select options</span>
            <span class="how-step"><span class="how-dot maroon-dot">4</span>Pay Service Fee</span>
            <span class="how-step"><span class="how-dot maroon-dot">5</span>Track status</span>
        </div>
    </section>
    <!-- Proceed Button -->
    <section class="detail-section detail-section-center">
        <a class="proceed-btn btn-soft-yellow" href="service-form.php?category=<?php echo urlencode($category); ?>">Proceed</a>
        <div class="cat-helper-text">You will be asked to fill a simple form in the next step.</div>
    </section>
    <a href="services.php" class="btn-soft-yellow btn-block" style="margin:18px auto 0 auto;">&larr; Back to Services</a>
</main>
<?php require_once 'footer.php'; ?>