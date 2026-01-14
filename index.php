<?php 
include 'header.php';

// Load today's Panchang JSON
$today = date('Y-m-d');
$jsonFile = __DIR__ . '/data/panchang-' . $today . '.json';
$panchangData = null;

if (file_exists($jsonFile)) {
    $panchangData = json_decode(file_get_contents($jsonFile), true);
}

// Helper function to get panchang value
function getPanchangValue($key, $default = '—') {
    global $panchangData;
    return ($panchangData && isset($panchangData[$key])) ? htmlspecialchars($panchangData[$key]) : $default;
}
?>

<main class="main-content">

        <!-- Top 3 Spiritual Cards Section -->
        <section class="spiritual-cards-wrapper">
            <div class="spiritual-cards-container">
                <!-- CARD 1: Panchang -->
                <div class="spiritual-card-group">
                    <a href="panchang.php" class="spiritual-card-link">
                        <article class="spiritual-card">
                            <img src="assets/images/religious-bg/panchang.png" alt="Panchang" class="spiritual-card-img" />
                            <div class="spiritual-card-title-overlay">Today's Panchang</div>
                        </article>
                    </a>
                </div>
                <!-- CARD 2: Din Vishesh -->
                <div class="spiritual-card-group">
                    <a href="din-vishesh.php" class="spiritual-card-link">
                        <article class="spiritual-card">
                            <img src="assets/images/religious-bg/dinvishesh.png" alt="Din Vishesh" class="spiritual-card-img" />
                            <div class="spiritual-card-title-overlay">Today's Significance</div>
                        </article>
                    </a>
                </div>
                <!-- CARD 3: Shubh Muhurat -->
                <div class="spiritual-card-group">
                    <a href="muhurat.php" class="spiritual-card-link">
                        <article class="spiritual-card">
                            <img src="assets/images/religious-bg/muhurat.png" alt="Muhurat" class="spiritual-card-img" />
                            <div class="spiritual-card-title-overlay">Today's Auspicious Timings</div>
                        </article>
                    </a>
                </div>
            </div>
        </section>

        <!-- Why This Platform Exists Section -->
        <section class="why-vishnusudarshana-section">
            <h2 class="why-title">Why This Platform Exists</h2>
            <div class="why-cards">
                <div class="why-card">
                    <div class="why-icon" aria-label="Long Waiting & Repeated Visits">😓</div>
                    <div class="why-card-content">
                        <h3>Less Waiting, Less Trouble</h3>
                        <p>Many people visit temples and service centers every day. This can mean long lines and waiting, even for simple needs. We want to make things easier for everyone.</p>
                    </div>
                </div>
                <div class="why-card">
                    <div class="why-icon" aria-label="Simple Digital Solution">📱</div>
                    <div class="why-card-content">
                        <h3>Simple and Safe</h3>
                        <p>You can send your details and requests online. This helps you avoid crowds and saves your time. You can do this from home or with help from family.</p>
                    </div>
                </div>
                <div class="why-card">
                    <div class="why-icon" aria-label="Peaceful & Organized Service">🙏</div>
                    <div class="why-card-content">
                        <h3>Peaceful and Organized</h3>
                        <p>Panditji and our team will take care of your service with respect. We keep you informed and make sure everything is done properly.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Who Is This Platform For Section -->
        <section class="who-for-section">
            <h2 class="who-for-title">Who Is This Platform For?</h2>
            <ul class="who-for-list">
                <li class="who-for-item">
                    <span class="who-for-icon" aria-label="Families with newborns">👶</span>
                    <span class="who-for-content"><strong>Families with Newborns</strong><br><span class="who-for-desc">For Janma Patrika, naming, and sanskar services.</span></span>
                </li>
                <li class="who-for-item">
                    <span class="who-for-icon" aria-label="Marriage-related guidance">💍</span>
                    <span class="who-for-content"><strong>Marriage Guidance</strong><br><span class="who-for-desc">For Kundali Milan and marriage advice.</span></span>
                </li>
                <li class="who-for-item">
                    <span class="who-for-icon" aria-label="Working professionals">💼</span>
                    <span class="who-for-content"><strong>Working Professionals</strong><br><span class="who-for-desc">For those with limited time who want easy online access.</span></span>
                </li>
                <li class="who-for-item">
                    <span class="who-for-icon" aria-label="Devotees from other cities">🏙</span>
                    <span class="who-for-content"><strong>Devotees from Other Cities</strong><br><span class="who-for-desc">For those who cannot travel but want to request services.</span></span>
                </li>
                <li class="who-for-item">
                    <span class="who-for-icon" aria-label="Elderly people">👴</span>
                    <span class="who-for-content"><strong>Elderly People</strong><br><span class="who-for-desc">For elders who want less waiting and simple tracking.</span></span>
                </li>
            </ul>
        </section>

        <!-- How This Platform Works Section -->
        <section class="how-to-use-section">
            <h2 class="how-title">How This Platform Works</h2>
            <div class="how-steps">
                <div class="how-step-card">
                    <div class="how-step-icon" aria-label="Choose Service">1️⃣</div>
                    <div class="how-step-content">
                        <h3>Choose a Service</h3>
                        <p>Look through the list of services and pick what you need.</p>
                    </div>
                </div>
                <div class="how-step-card">
                    <div class="how-step-icon" aria-label="Submit Details">2️⃣</div>
                    <div class="how-step-content">
                        <h3>Share Your Details</h3>
                        <p>Fill out a simple form or ask a family member to help. You can also book an appointment online.</p>
                    </div>
                </div>
                <div class="how-step-card">
                    <div class="how-step-icon" aria-label="We Process">3️⃣</div>
                    <div class="how-step-content">
                        <h3>We Arrange the Service</h3>
                        <p>Our staff and Panditji will prepare and perform your chosen service with care.</p>
                    </div>
                </div>
                <div class="how-step-card">
                    <div class="how-step-icon" aria-label="Get Updates">4️⃣</div>
                    <div class="how-step-content">
                        <h3>Get Updates</h3>
                        <p>You can check the status of your service or get a call or delivery update from us.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Services Available Online Section -->
        <section class="services-online-section">
            <h2 class="services-online-title">Services You Can Request Online</h2>
            <div class="services-online-cards">
                <a href="services.php" class="service-online-card" aria-label="Janma Patrika">
                    <span class="service-online-icon">📜</span>
                    <span class="service-online-label">Janma Patrika</span>
                </a>
                <a href="services.php" class="service-online-card" aria-label="Kundali Milan">
                    <span class="service-online-icon">💑</span>
                    <span class="service-online-label">Kundali Milan</span>
                </a>
                <a href="services.php" class="service-online-card" aria-label="Astrology Consultation">
                    <span class="service-online-icon">🧠</span>
                    <span class="service-online-label">Astrology Consultation</span>
                </a>
                <a href="services.php" class="service-online-card" aria-label="Vastu Services">
                    <span class="service-online-icon">🏠</span>
                    <span class="service-online-label">Vastu Services</span>
                </a>
                <a href="services.php" class="service-online-card" aria-label="Pooja & Sanskar">
                    <span class="service-online-icon">🪔</span>
                    <span class="service-online-label">Pooja & Sanskar</span>
                </a>
            </div>
            <div class="services-online-btn-wrap">
                <a href="services.php" class="services-online-btn">See All Services &rarr;</a>
            </div>
        </section>

        <!-- Book Appointment Section -->
        <section class="cta-guidance-section">
            <div class="cta-guidance-container">
                <h2 class="cta-guidance-title">Book Appointment</h2>
                <p class="cta-guidance-text">You can book an appointment for a consultation or service. Choose online or in-person, as you prefer. We are here to help with astrology, vastu, or other important matters.</p>
                <div class="cta-guidance-btns">
                    <a href="services.php" class="cta-guidance-btn">Book Consultation</a>
                    <a href="category.php?category=appointment" class="cta-guidance-btn secondary">Book Appointment</a>
                </div>
            </div>
        </section>

</main>

<?php include 'footer.php'; ?>
