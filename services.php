<?php include 'header.php'; ?>

<main class="services-main">
    <section class="service-categories">
        <h1 class="services-title">Choose a Service Type</h1>
        <div class="categories-grid home-top-cards-container">
            <!-- Book an Appointment Card -->
            <a class="category-card home-top-card" href="category.php?category=appointment">
                <img src="assets/images/religious-bg/appointment.png" alt="Book an Appointment Icon" class="home-top-card-icon" />
                <div class="home-top-card-title">Book an Appointment</div>
                <div class="home-top-card-desc">Schedule an online or offline appointment; final slot confirmed by our team.</div>
                <span class="category-badge guidance">Book Now</span>
            </a>
            <!-- Birth & Child Services Card -->
            <a class="category-card home-top-card" href="category.php?category=birth-child">
                <img src="assets/images/religious-bg/birthchild.png" alt="Birth & Child Services Icon" class="home-top-card-icon" />
                <div class="home-top-card-title">Birth & Child Services</div>
                <div class="home-top-card-desc">Janma Patrika, name suggestions, baby horoscope and child guidance.</div>
                <span class="category-badge guidance">Book Now</span>
            </a>
            <!-- Marriage & Matching Card -->
            <a class="category-card home-top-card" href="category.php?category=marriage-matching">
                <img src="assets/images/religious-bg/marriage.png" alt="Marriage & Matching Icon" class="home-top-card-icon" />
                <div class="home-top-card-title">Marriage & Matching</div>
                <div class="home-top-card-desc">Kundali Milan, marriage prediction and compatibility analysis.</div>
                <span class="category-badge guidance">Book Now</span>
            </a>
            <!-- Astrology Consultation Card -->
            <a class="category-card home-top-card" href="category.php?category=astrology-consultation">
                <img src="assets/images/religious-bg/astrology.png" alt="Astrology Consultation Icon" class="home-top-card-icon" />
                <div class="home-top-card-title">Astrology Consultation</div>
                <div class="home-top-card-desc">Career, marriage, health, finance and personal guidance.</div>
                <span class="category-badge guidance">Book Now</span>
            </a>
            <!-- Muhurat & Event Guidance Card -->
            <a class="category-card home-top-card" href="category.php?category=muhurat-event">
                <img src="assets/images/religious-bg/muhurat.png" alt="Muhurat & Event Guidance Icon" class="home-top-card-icon" />
                <div class="home-top-card-title">Muhurat & Event Guidance</div>
                <div class="home-top-card-desc">Marriage, griha pravesh, vehicle purchase and business start muhurat.</div>
                <span class="category-badge guidance">Book Now</span>
            </a>
            <!-- Pooja, Ritual & Vastu Enquiry Card -->
            <a class="category-card home-top-card" href="category.php?category=pooja-vastu-enquiry">
                <img src="assets/images/religious-bg/pooja.png" alt="Pooja, Ritual & Vastu Enquiry Icon" class="home-top-card-icon" />
                <div class="home-top-card-title">Pooja, Ritual & Vastu Enquiry</div>
                <div class="home-top-card-desc">Pooja, shanti, dosh nivaran, yagya and vastu consultation.</div>
                <span class="category-badge guidance">Book Now</span>
            </a>
        </div>
    </section>
</main>

<style>
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html,body{
    font-family:'Marcellus',serif!important;
}
.services-main {
    padding: 1.5rem 0 4.5rem 0;
    background: var(--cream-bg);
    min-height: 100vh;
}
.services-title {
    text-align: center;
    font-size: 2rem;
    margin-bottom: 1.5rem;
    color: var(--text-dark);
}
/* Responsive grid and card design improvements */
.categories-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    max-width: 900px;
    margin: 0 auto;
    padding: 0 1rem;
}
.category-card {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    background: linear-gradient(135deg, #fff7f7 0%, #f7f7fa 100%);
    border-radius: 1.2rem;
    box-shadow: 0 4px 18px rgba(128,0,0,0.08);
    padding: 1.6rem 1.2rem 1.2rem 1.2rem;
    text-decoration: none;
    color: var(--text-dark);
    transition: box-shadow 0.2s, transform 0.2s;
    cursor: pointer;
    min-height: 160px;
    border: 1px solid #e0bebe;
    position: relative;
}
.category-card:hover, .category-card:focus {
    box-shadow: 0 8px 32px rgba(128,0,0,0.14);
    transform: translateY(-2px) scale(1.03);
    border-color: var(--maroon);
}
.category-icon {
    font-size: 2.6rem;
    margin-bottom: 0.8rem;
    margin-right: 0;
    background: #fff0f0;
    border-radius: 50%;
    width: 3.6rem;
    height: 3.6rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px #e0bebe33;
}
.category-info {
    width: 100%;
}
.category-info h2 {
    font-size: 1.22rem;
    margin: 0 0 0.3rem 0;
    font-weight: 700;
    color: var(--maroon);
    letter-spacing: 0.01em;
}
.category-info p {
    font-size: 1.08rem;
    margin: 0 0 0.7rem 0;
    color: #555;
    line-height: 1.5;
}
.category-badge {
    display: inline-block;
    font-size: 0.98rem;
    padding: 0.22em 1em;
    border-radius: 0.8em;
    margin-top: 0.3em;
    font-weight: 600;
    letter-spacing: 0.01em;
    box-shadow: 0 1px 4px #e0bebe22;
}
.category-badge.paid {
    background: #ffe5e5;
    color: #b00020;
}
.category-badge.consult {
    background: #e5f0ff;
    color: #0056b3;
}
.category-badge.guidance {
    background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
    color: #fff;
    font-weight: 700;
    font-size: 1.08rem;
    border: none;
    box-shadow: 0 2px 8px rgba(67,233,123,0.10);
    padding: 0.55em 1.6em;
    border-radius: 24px;
    margin: 14px auto 0 auto;
    display: block;
    text-align: center;
    letter-spacing: 0.03em;
    transition: background 0.18s, box-shadow 0.18s, color 0.18s;
    cursor: pointer;
}
.category-badge.guidance:hover, .category-badge.guidance:focus {
    background: linear-gradient(90deg, #38f9d7 0%, #43e97b 100%);
    color: #fff;
    box-shadow: 0 6px 18px rgba(67,233,123,0.18);
}
.category-badge.enquiry {
    background: #f3e5ff;
    color: #6a1b9a;
}
.home-top-card-icon {
    display: block;
    margin: 0 auto 18px auto;
    width: 150px;
    height: 150px;
    max-width: 100%;
    object-fit: contain;
    border-radius: 18px;
    background: linear-gradient(135deg, #fffbe6 0%, #fff9e0 60%, #f7e9c7 100%);
    box-shadow: 0 2px 8px rgba(212,175,55,0.08);
}
@media (max-width: 600px) {
    .categories-grid {
        grid-template-columns: 1fr;
        gap: 22px;
        padding: 0 4px;
    }
    .category-card {
        align-items: center;
        padding: 1.2rem 0.7rem 1.2rem 0.7rem;
        min-height: 140px;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(212,175,55,0.10);
    }
    .home-top-card-icon {
        width: 90px;
        height: 90px;
        margin-bottom: 10px;
    }
    .home-top-card-title {
        font-size: 1.13rem;
        text-align: center;
        margin-bottom: 4px;
    }
    .home-top-card-desc {
        font-size: 0.98rem;
        text-align: center;
        margin-bottom: 6px;
    }
    .category-badge {
        font-size: 0.92rem;
        margin-top: 0.2em;
    }
    .category-badge.guidance {
        font-size: 1rem;
        padding: 0.45em 1.1em;
        margin-top: 10px;
    }
}
@media (min-width: 600px) {
    .categories-grid {
        grid-template-columns: 1fr 1fr;
    }
    .category-card {
        min-height: 180px;
        padding: 2rem 1.5rem 1.5rem 1.5rem;
    }
    .category-icon {
        font-size: 2.8rem;
        width: 4rem;
        height: 4rem;
    }
    .category-info h2 {
        font-size: 1.32rem;
    }
    .category-info p {
        font-size: 1.12rem;
    }
}
@media (min-width: 900px) {
    .categories-grid {
        grid-template-columns: 1fr 1fr 1fr;
    }
    .category-card {
        min-height: 200px;
        padding: 2.2rem 1.8rem 1.6rem 1.8rem;
    }
    .category-icon {
        font-size: 3rem;
        width: 4.4rem;
        height: 4.4rem;
    }
}
</style>

<?php include 'footer.php'; ?>
