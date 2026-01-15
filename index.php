<?php 
include 'header.php';
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html, body, .main-content, .main-content * {
    font-family: 'Marcellus', serif !important;
    font-size: 1.08rem;
    color: #333;
    font-weight: 500;
    letter-spacing: 0.01em;
}
</style>
<link rel="stylesheet" href="assets/css/style.css">
<?php
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

    <!-- Home Top 4 Cards Section -->
    <section class="home-top-cards-section">
        <div class="home-top-cards-container">
            <!-- Card 1: Panchang -->
            <a href="panchang.php" class="home-top-card-link" aria-label="Panchang">
                <div class="home-top-card">
                    <img src="assets/images/religious-bg/panchang.png" alt="Panchang Icon" class="home-top-card-icon" />
                    <div class="home-top-card-title">Panchang</div>
                    <div class="home-top-card-desc">Daily Vedic Calendar</div>
                </div>
            </a>
            <!-- Card 2: Din Vishesh -->
            <a href="din-vishesh.php" class="home-top-card-link" aria-label="Din Vishesh">
                <div class="home-top-card">
                    <img src="assets/images/religious-bg/dinvishesh.png" alt="Din Vishesh Icon" class="home-top-card-icon" />
                    <div class="home-top-card-title">Din Vishesh</div>
                    <div class="home-top-card-desc">Today's Significance</div>
                </div>
            </a>
            <!-- Card 3: Muhurats -->
            <a href="muhurat.php" class="home-top-card-link" aria-label="Muhurat">
                <div class="home-top-card">
                    <img src="assets/images/religious-bg/muhurat.png" alt="Muhurat Icon" class="home-top-card-icon" />
                    <div class="home-top-card-title">Shubh Muhurats</div>
                    <div class="home-top-card-desc">Auspicious Timings</div>
                </div>
            </a>
            <!-- Card 4: Today's Knowledge -->
            <a href="blogs.php" class="home-top-card-link" aria-label="Today's Knowledge">
                <div class="home-top-card">
                    <img src="assets/images/religious-bg/todaysknowledge.png" alt="Today's Knowledge Icon" class="home-top-card-icon" />
                    <div class="home-top-card-title">Today's Knowledge</div>
                    <div class="home-top-card-desc">Spiritual Wisdom</div>
                </div>
            </a>
        </div>
    </section>

    <!-- Devotionals Section: Unified Header and Cards -->
    <section class="devotional-cards-section">
        <div class="devotional-section-header" style="text-align:center;">
            <h2 class="devotional-section-title">Why Vishnusudarshana &amp; How It Helps You</h2>
            <div class="devotional-section-subtext">Traditional Vedic services, made simple through a respectful online process.</div>
            <div class="devotional-section-divider"></div>
        </div>
        <div class="devotional-cards-container">
            <!-- Card 1 -->
            <div class="devotional-card">
                <svg class="devotional-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7v10c0 5.5 10 11 10 11s10-5.5 10-11V7L12 2z"/>
                </svg>
                <h3 class="devotional-card-title">Less Waiting, More Peace</h3>
                <p class="devotional-card-text">Avoid long queues and repeated visits.<br>Request services calmly and easily.</p>
            </div>
            
            <!-- Card 2 -->
            <div class="devotional-card">
                <svg class="devotional-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 6v6l4 2"/>
                </svg>
                <h3 class="devotional-card-title">Tradition, Made Simple</h3>
                <p class="devotional-card-text">Same Vedic process and Panditji.<br>Only the request method is online.</p>
            </div>
            
            <!-- Card 3 -->
            <div class="devotional-card">
                <svg class="devotional-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <h3 class="devotional-card-title">For Everyone, Everywhere</h3>
                <p class="devotional-card-text">Helpful for elders, families,<br>and devotees in any city.</p>
            </div>
            
            <!-- Card 4 -->
            <div class="devotional-card">
                <svg class="devotional-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/>
                    <rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/>
                </svg>
                <h3 class="devotional-card-title">Choose What You Need</h3>
                <p class="devotional-card-text">Select the required service<br>from our trusted list.</p>
            </div>
            
            <!-- Card 5 -->
            <div class="devotional-card">
                <svg class="devotional-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
                <h3 class="devotional-card-title">Share Details Online</h3>
                <p class="devotional-card-text">Fill a simple form yourself<br>or with family assistance.</p>
            </div>
            
            <!-- Card 6 -->
            <div class="devotional-card">
                <svg class="devotional-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <h3 class="devotional-card-title">We Take Care of Everything</h3>
                <p class="devotional-card-text">Panditji and our team prepare<br>and perform the service properly.</p>
            </div>
            
            <!-- Card 7 -->
            <div class="devotional-card">
                <svg class="devotional-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <h3 class="devotional-card-title">Stay Informed</h3>
                <p class="devotional-card-text">Get updates and guidance<br>without stress or confusion.</p>
            </div>
        </div>
    </section>

    <!-- Book Appointment Section -->
    <section class="cta-guidance-section" style="background: #fffbe6; border-radius: 22px; box-shadow: 0 4px 32px 0 rgba(212,175,55,0.08); padding: 44px 0 32px 0; margin-bottom: 44px; width: 100%; max-width: none; margin-left: 0; margin-right: 0;">
        <div class="cta-guidance-container" style="width: 100%; max-width: none; margin: 0 auto; text-align: center; background: transparent;">
            <h2 class="cta-guidance-title" style="font-family: 'Marcellus', serif; font-size: 2.1rem; color: var(--maroon); font-weight: 900; margin-bottom: 14px; letter-spacing: 0.7px; text-shadow: 0 2px 12px #fffbe6, 0 1px 0 #fff, 0 0 8px #fff7c2;">Book Your Appointment</h2>
            <div class="cta-guidance-divider" style="width: 90px; height: 4px; background: linear-gradient(90deg, #FFD700 0%, #fffbe6 100%); border-radius: 2px; margin: 0 auto 22px auto;"></div>
            <p class="cta-guidance-text" style="font-family: 'Marcellus', serif; font-size: 1.13rem; color: #bfa100; opacity: 0.82; font-weight: 500; margin-bottom: 28px; letter-spacing: 0.1px;">Reserve your time for a personal consultation or spiritual service. Choose online or in-person—our expert team is here to guide you in astrology, vastu, and more. Experience clarity and peace of mind with a simple booking process.</p>
            <div class="cta-guidance-btns" style="display: flex; justify-content: center; gap: 18px; flex-wrap: wrap;">
                <a href="services.php" class="cta-guidance-btn" style="background: linear-gradient(90deg, #FFD700 0%, #FFFACD 100%); color: var(--maroon); border: none; border-radius: 12px; font-weight: 700; font-size: 1.1rem; padding: 14px 36px; box-shadow: 0 2px 8px rgba(212,175,55,0.10); margin-bottom: 8px; text-decoration: none !important; display: inline-block; min-width: 160px; text-align: center; transition: all 0.18s cubic-bezier(.4,1.3,.6,1); cursor: pointer;">View Services</a>
                <a href="category.php?category=appointment" class="cta-guidance-btn secondary" style="background: #fffbe6; color: var(--maroon); border: 2px solid #FFD700; border-radius: 12px; font-weight: 700; font-size: 1.1rem; padding: 14px 36px; margin-bottom: 8px; text-decoration: none !important; display: inline-block; min-width: 160px; text-align: center; transition: all 0.18s cubic-bezier(.4,1.3,.6,1); cursor: pointer;">Book Appointment</a>
            </div>
        </div>
    </section>

</main>

<?php include 'footer.php'; ?>
