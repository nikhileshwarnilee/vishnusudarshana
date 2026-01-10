// Nikhil
<?php
require_once 'header.php';
require_once __DIR__ . '/config/db.php';

// Fetch categories ordered by sequence
$stmt = $pdo->query("SELECT * FROM service_categories ORDER BY sequence ASC, id ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<main class="services-main" style="background-color:#FFD700;">
    <section class="service-categories">
        <h1 class="services-title">Choose a Service Type</h1>
        <div class="categories-grid">
            <?php foreach ($categories as $cat): ?>
                <a class="category-card" href="category.php?category=<?= htmlspecialchars($cat['category_slug']) ?>">
                    <?php if (!empty($cat['logo'])): ?>
                        <div class="category-icon" aria-label="<?= htmlspecialchars($cat['category_name']) ?>">
                            <img src="assets/images/logo/<?= htmlspecialchars($cat['logo']) ?>" alt="<?= htmlspecialchars($cat['category_name']) ?>" style="width:2.6rem;height:2.6rem;object-fit:contain;border-radius:50%;background:#fff;">
                        </div>
                    <?php else: ?>
                        <div class="category-icon" aria-label="<?= htmlspecialchars($cat['category_name']) ?>">📁</div>
                    <?php endif; ?>
                    <div class="category-info">
                        <h2><?= htmlspecialchars($cat['category_name']) ?></h2>
                        <p><?= htmlspecialchars($cat['description']) ?></p>
                        <?php if (!empty($cat['services_include'])): ?>
                            <span class="category-badge guidance"><?= htmlspecialchars($cat['services_include']) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<style>
.services-main {
    padding: 1.5rem 0 4.5rem 0;
    background: #f8f9fa;
    min-height: 100vh;
}
.services-title {
    text-align: center;
    font-size: 2rem;
    margin-bottom: 1.5rem;
    color: #222;
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
    color: #222;
    transition: box-shadow 0.2s, transform 0.2s;
    cursor: pointer;
    min-height: 160px;
    border: 1px solid #e0bebe;
    position: relative;
}
.category-card:hover, .category-card:focus {
    box-shadow: 0 8px 32px rgba(128,0,0,0.14);
    transform: translateY(-2px) scale(1.03);
    border-color: #800000;
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
    color: #800000;
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
    background: #e5ffe5;
    color: #1b5e20;
}
.category-badge.enquiry {
    background: #f3e5ff;
    color: #6a1b9a;
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
