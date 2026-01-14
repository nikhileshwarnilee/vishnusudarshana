<?php
include 'header.php';
?>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    .blog-container {
        max-width: 1200px;
        margin: 60px auto;
        padding: 0 20px;
        min-height: 80vh;
    }

    /* Header Section */
    .blog-header {
        text-align: center;
        margin-bottom: 50px;
        animation: slideDown 0.6s ease-out;
    }

    .blog-header h1 {
        font-size: 3.5em;
        color: #800000;
        margin-bottom: 15px;
        font-weight: 700;
        letter-spacing: -1px;
    }

    .blog-header p {
        font-size: 1.1em;
        color: #666;
        font-weight: 300;
    }

    /* Filter Section */
    .filter-section {
        margin-bottom: 50px;
        animation: slideUp 0.6s ease-out;
    }

    .filter-label {
        font-size: 1em;
        color: #800000;
        font-weight: 600;
        margin-bottom: 15px;
        display: block;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .tag-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .tag-btn {
        padding: 10px 20px;
        border: 2px solid #ddd;
        background: #fff;
        color: #333;
        border-radius: 50px;
        cursor: pointer;
        font-size: 0.95em;
        font-weight: 500;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .tag-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #800000, #b36b00);
        transition: left 0.3s ease;
        z-index: -1;
        border-radius: 50px;
    }

    .tag-btn:hover {
        border-color: #800000;
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(128, 0, 0, 0.2);
    }

    .tag-btn:hover::before {
        left: 0;
    }

    .tag-btn.active {
        background: linear-gradient(135deg, #800000, #b36b00);
        color: #fff;
        border-color: transparent;
        box-shadow: 0 8px 20px rgba(128, 0, 0, 0.3);
    }

    /* Mobile Dropdown Filter */
    .mobile-filter-dropdown {
        display: none;
        width: 100%;
        position: relative;
    }

    .mobile-filter-dropdown::before {
        content: '';
        position: absolute;
        top: 50%;
        right: 16px;
        transform: translateY(-50%);
        width: 0;
        height: 0;
        border-left: 6px solid transparent;
        border-right: 6px solid transparent;
        border-top: 7px solid #800000;
        pointer-events: none;
        z-index: 2;
        transition: all 0.3s ease;
    }

    .mobile-filter-dropdown select {
        width: 100%;
        padding: 16px 45px 16px 20px;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        font-size: 1em;
        color: #333;
        background: linear-gradient(135deg, #ffffff 0%, #fafafa 100%);
        cursor: pointer;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        font-weight: 500;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        position: relative;
        z-index: 1;
    }

    .mobile-filter-dropdown select:hover {
        border-color: #800000;
        box-shadow: 0 4px 12px rgba(128, 0, 0, 0.08);
        background: #fff;
    }

    .mobile-filter-dropdown select:focus {
        outline: none;
        border-color: #800000;
        box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.08), 0 4px 12px rgba(128, 0, 0, 0.12);
        background: #fff;
    }

    .mobile-filter-dropdown select option {
        padding: 14px;
        font-weight: 500;
        color: #333;
        background: #fff;
    }

    .mobile-filter-dropdown select option:checked {
        background: linear-gradient(135deg, #800000, #b36b00);
        color: #fff;
    }

    /* Blog Grid */
    .blog-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 30px;
        margin-bottom: 50px;
        animation: fadeIn 0.8s ease-out;
    }

    .blog-card {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        display: flex;
        flex-direction: column;
        cursor: pointer;
        position: relative;
    }

    .blog-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #800000, #b36b00);
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.4s ease;
        z-index: 1;
    }

    .blog-card:hover::before {
        transform: scaleX(1);
    }

    .blog-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 40px rgba(128, 0, 0, 0.15);
    }

    .blog-image {
        width: 100%;
        height: 220px;
        background: linear-gradient(135deg, #f5e6d3, #ffe8cc);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3em;
        color: #b36b00;
        position: relative;
        overflow: hidden;
    }

    .blog-image::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1), transparent 70%);
        animation: shimmer 3s infinite;
    }

    .blog-content {
        padding: 28px 24px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .blog-date {
        font-size: 0.85em;
        color: #999;
        font-weight: 500;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .blog-title {
        font-size: 1.4em;
        color: #800000;
        margin-bottom: 12px;
        font-weight: 700;
        line-height: 1.4;
        transition: color 0.3s ease;
    }

    .blog-card:hover .blog-title {
        color: #b36b00;
    }

    .blog-description {
        color: #666;
        font-size: 0.95em;
        line-height: 1.6;
        margin-bottom: 16px;
        flex-grow: 1;
    }

    .blog-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }

    .tag {
        display: inline-block;
        background: #f0f0f0;
        color: #800000;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8em;
        font-weight: 600;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .tag:hover {
        background: #800000;
        color: #fff;
    }

    .read-more {
        display: inline-flex;
        align-items: center;
        color: #800000;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 0.95em;
        gap: 8px;
    }

    .read-more:hover {
        color: #b36b00;
        transform: translateX(4px);
    }

    .read-more::after {
        content: '→';
        transition: transform 0.3s ease;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        animation: fadeIn 0.5s ease-out;
    }

    .empty-state-icon {
        font-size: 4em;
        margin-bottom: 20px;
    }

    .empty-state h3 {
        font-size: 1.5em;
        color: #800000;
        margin-bottom: 10px;
    }

    .empty-state p {
        color: #999;
        font-size: 1em;
    }

    /* Animations */
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes shimmer {
        0% {
            transform: translateX(-100%);
        }
        100% {
            transform: translateX(100%);
        }
    }

    /* Responsive Design */
    @media (max-width: 992px) {
        .blog-container {
            margin: 40px auto;
            padding: 0 16px;
        }

        .blog-header h1 {
            font-size: 2.8em;
        }

        .blog-grid {
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }
    }

    @media (max-width: 768px) {
        .blog-container {
            margin: 30px auto;
        }

        .blog-header {
            margin-bottom: 35px;
        }

        .blog-header h1 {
            font-size: 2.2em;
        }

        .blog-header p {
            font-size: 1em;
        }

        .filter-section {
            margin-bottom: 35px;
        }

        /* Hide button filters, show dropdown on tablet and mobile */
        .tag-filters {
            display: none !important;
        }

        .mobile-filter-dropdown {
            display: block;
        }

        .blog-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .blog-image {
            height: 200px;
            font-size: 2.5em;
        }

        .blog-content {
            padding: 22px 20px;
        }

        .blog-title {
            font-size: 1.25em;
        }

        .blog-description {
            font-size: 0.92em;
            line-height: 1.5;
        }
    }

    @media (max-width: 600px) {
        .blog-grid {
            grid-template-columns: 1fr;
            gap: 18px;
        }

        .blog-card {
            max-width: 100%;
        }
    }

    @media (max-width: 480px) {
        .blog-container {
            margin: 20px auto;
            padding: 0 12px;
        }

        .blog-header {
            margin-bottom: 30px;
        }

        .blog-header h1 {
            font-size: 1.9em;
            margin-bottom: 12px;
        }

        .blog-header p {
            font-size: 0.95em;
        }

        .filter-section {
            margin-bottom: 30px;
        }

        .filter-label {
            font-size: 0.9em;
            margin-bottom: 12px;
        }

        .mobile-filter-dropdown select {
            padding: 12px 16px;
            font-size: 0.95em;
        }

        .blog-grid {
            gap: 16px;
            margin-bottom: 40px;
        }

        .blog-image {
            height: 180px;
            font-size: 2.2em;
        }

        .blog-content {
            padding: 20px 16px;
        }

        .blog-date {
            font-size: 0.8em;
            margin-bottom: 8px;
        }

        .blog-title {
            font-size: 1.15em;
            margin-bottom: 10px;
        }

        .blog-description {
            font-size: 0.88em;
            margin-bottom: 14px;
            line-height: 1.5;
        }

        .blog-tags {
            gap: 6px;
            margin-bottom: 14px;
        }

        .tag {
            padding: 4px 10px;
            font-size: 0.75em;
        }

        .read-more {
            font-size: 0.9em;
        }

        .empty-state {
            padding: 50px 15px;
        }

        .empty-state-icon {
            font-size: 3em;
        }

        .empty-state h3 {
            font-size: 1.3em;
        }

        .empty-state p {
            font-size: 0.95em;
        }
    }
</style>

<div class="blog-container">
    <!-- Header -->
    <div class="blog-header">
        <h1>Our Blog</h1>
        <p>Explore articles on astrology, horoscopes, and spiritual wisdom</p>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <label class="filter-label">Filter by Topic</label>
        
        <!-- Desktop Filter Buttons -->
        <div class="tag-filters" id="tagFilters">
            <button class="tag-btn active" data-tag="all">All Articles</button>
            <button class="tag-btn" data-tag="kundali">Kundali</button>
            <button class="tag-btn" data-tag="astrology">Astrology</button>
            <button class="tag-btn" data-tag="muhurat">Muhurat</button>
            <button class="tag-btn" data-tag="horoscope">Horoscope</button>
            <button class="tag-btn" data-tag="panchang">Panchang</button>
            <button class="tag-btn" data-tag="tips">Tips & Advice</button>
        </div>

        <!-- Mobile/Tablet Dropdown Filter -->
        <div class="mobile-filter-dropdown">
            <select id="mobileFilterSelect">
                <option value="all">All Articles</option>
                <option value="kundali">Kundali</option>
                <option value="astrology">Astrology</option>
                <option value="muhurat">Muhurat</option>
                <option value="horoscope">Horoscope</option>
                <option value="panchang">Panchang</option>
                <option value="tips">Tips & Advice</option>
            </select>
        </div>
    </div>

    <!-- Blog Grid -->
    <div class="blog-grid" id="blogGrid">
        <!-- Blog cards will be rendered here -->
    </div>

    <!-- Empty State -->
    <div class="empty-state" id="emptyState" style="display: none;">
        <div class="empty-state-icon">📚</div>
        <h3>No Articles Found</h3>
        <p>Try selecting different topics or check back soon for new content.</p>
    </div>
</div>

<script>
    // Blog data
    const blogs = [
        {
            id: 1,
            title: "How to Read Your Kundali: A Beginner's Guide",
            date: "10 Jan 2026",
            description: "Learn the basics of reading your own kundali and understanding the key elements that shape your astrological profile. This guide covers the essentials for beginners.",
            tags: ["kundali", "astrology", "tips"],
            icon: "🌙",
            link: "#"
        },
        {
            id: 2,
            title: "5 Common Myths About Astrology",
            date: "2 Jan 2026",
            description: "Astrology is often misunderstood. We debunk five common myths and explain what astrology really is—and isn't.",
            tags: ["astrology", "tips"],
            icon: "✨",
            link: "#"
        },
        {
            id: 3,
            title: "Choosing the Right Muhurat for Your Event",
            date: "28 Dec 2025",
            description: "Find out how to select the most auspicious muhurat for your important life events, with tips from our expert astrologers.",
            tags: ["muhurat", "tips"],
            icon: "🎯",
            link: "#"
        },
        {
            id: 4,
            title: "Understanding Your Moon Sign",
            date: "25 Dec 2025",
            description: "Your moon sign is just as important as your sun sign! Discover what your moon sign reveals about your emotional nature and inner world.",
            tags: ["astrology", "horoscope"],
            icon: "🌕",
            link: "#"
        },
        {
            id: 5,
            title: "Panchang Predictions for 2026",
            date: "20 Dec 2025",
            description: "Get insights into the year ahead with our comprehensive panchang analysis. Learn about planetary movements and their influence on your life.",
            tags: ["panchang", "horoscope"],
            icon: "📅",
            link: "#"
        },
        {
            id: 6,
            title: "Marriage Compatibility Through Kundali Matching",
            date: "15 Dec 2025",
            description: "Understand the traditional methods of kundali matching and how it helps determine compatibility between couples before marriage.",
            tags: ["kundali", "astrology"],
            icon: "💑",
            link: "#"
        },
        {
            id: 7,
            title: "Daily Astrology: How to Use It for Success",
            date: "10 Dec 2025",
            description: "Learn how to incorporate daily horoscopes into your routine for better decision-making and personal growth throughout the year.",
            tags: ["horoscope", "tips"],
            icon: "⭐",
            link: "#"
        },
        {
            id: 8,
            title: "Lucky Numbers and Their Significance",
            date: "5 Dec 2025",
            description: "Explore the numerological significance of numbers in astrology and how to use your lucky numbers to enhance prosperity.",
            tags: ["astrology", "tips"],
            icon: "🔢",
            link: "#"
        }
    ];

    let currentFilter = 'all';

    // Render blog cards
    function renderBlogs(filter = 'all') {
        const blogGrid = document.getElementById('blogGrid');
        const emptyState = document.getElementById('emptyState');

        const filteredBlogs = filter === 'all' 
            ? blogs 
            : blogs.filter(blog => blog.tags.includes(filter));

        if (filteredBlogs.length === 0) {
            blogGrid.innerHTML = '';
            emptyState.style.display = 'block';
            return;
        }

        emptyState.style.display = 'none';
        blogGrid.innerHTML = filteredBlogs.map(blog => `
            <div class="blog-card">
                <div class="blog-image">${blog.icon}</div>
                <div class="blog-content">
                    <div class="blog-date">${blog.date}</div>
                    <h2 class="blog-title">${blog.title}</h2>
                    <p class="blog-description">${blog.description}</p>
                    <div class="blog-tags">
                        ${blog.tags.map(tag => `<span class="tag" onclick="filterByTag('${tag}')">${tag}</span>`).join('')}
                    </div>
                    <a href="${blog.link}" class="read-more">Read More</a>
                </div>
            </div>
        `).join('');
    }

    // Filter by tag
    function filterByTag(tag) {
        currentFilter = tag;
        updateFilterButtons();
        updateMobileDropdown();
        renderBlogs(tag);
        document.querySelector('.filter-section').scrollIntoView({ behavior: 'smooth' });
    }

    // Update filter button states
    function updateFilterButtons() {
        document.querySelectorAll('.tag-btn').forEach(btn => {
            if (btn.dataset.tag === currentFilter) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }

    // Update mobile dropdown state
    function updateMobileDropdown() {
        const mobileSelect = document.getElementById('mobileFilterSelect');
        if (mobileSelect) {
            mobileSelect.value = currentFilter;
        }
    }

    // Filter button click handler
    document.addEventListener('DOMContentLoaded', function() {
        // Desktop button filters
        document.querySelectorAll('.tag-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                currentFilter = this.dataset.tag;
                updateFilterButtons();
                updateMobileDropdown();
                renderBlogs(currentFilter);
            });
        });

        // Mobile dropdown filter
        const mobileSelect = document.getElementById('mobileFilterSelect');
        if (mobileSelect) {
            mobileSelect.addEventListener('change', function() {
                currentFilter = this.value;
                updateFilterButtons();
                renderBlogs(currentFilter);
            });
        }

        // Initial render
        renderBlogs();
    });
</script>

<?php
include 'footer.php';
?>