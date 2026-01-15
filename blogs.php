<?php
include 'header.php';
require_once __DIR__ . '/config/db.php';

// Fetch published blogs from database

// Pagination logic
$perPage = 9;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// Get total count
$countStmt = $pdo->query("SELECT COUNT(*) FROM blogs WHERE status = 'published'");
$totalBlogs = $countStmt->fetchColumn();
$totalPages = ceil($totalBlogs / $perPage);

// Fetch paginated blogs
$stmt = $pdo->prepare("SELECT * FROM blogs WHERE status = 'published' ORDER BY publish_date DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Extract unique tags from all published blogs
$allTags = [];
foreach ($blogs as $blog) {
    if (!empty($blog['tags'])) {
        $blogTags = array_map('trim', explode(',', $blog['tags']));
        foreach ($blogTags as $tag) {
            $tag = strtolower($tag);
            if (!in_array($tag, $allTags)) {
                $allTags[] = $tag;
            }
        }
    }
}
sort($allTags);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
html,body{font-family:'Marcellus',serif!important;}
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
            <?php foreach ($allTags as $tag): ?>
                <button class="tag-btn" data-tag="<?= htmlspecialchars($tag) ?>"><?= htmlspecialchars(ucwords($tag)) ?></button>
            <?php endforeach; ?>
        </div>

        <!-- Mobile/Tablet Dropdown Filter -->
        <div class="mobile-filter-dropdown">
            <select id="mobileFilterSelect">
                <option value="all">All Articles</option>
                <?php foreach ($allTags as $tag): ?>
                    <option value="<?= htmlspecialchars($tag) ?>"><?= htmlspecialchars(ucwords($tag)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Blog Grid -->
    <div class="blog-grid" id="blogGrid">
        <!-- Blog cards will be rendered here -->
    </div>

    <!-- Pagination -->
    <div class="pagination" style="text-align:center; margin-bottom:40px;">
        <?php if ($totalPages > 1): ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="page-btn" style="display:inline-block; margin:0 6px; padding:8px 16px; border-radius:6px; background:<?= $i == $page ? '#800000' : '#f9f3f0' ?>; color:<?= $i == $page ? '#fff' : '#800000' ?>; font-weight:600; text-decoration:none; border:1px solid #f3d5c8;"> <?= $i ?> </a>
            <?php endfor; ?>
        <?php endif; ?>
    </div>

    <!-- Empty State -->
    <div class="empty-state" id="emptyState" style="display: none;">
        <div class="empty-state-icon">📚</div>
        <h3>No Articles Found</h3>
        <p>Try selecting different topics or check back soon for new content.</p>
    </div>
</div>

<!-- Video Modal -->
<div id="videoModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.7); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; border-radius:12px; max-width:90vw; max-height:80vh; padding:0; position:relative; box-shadow:0 8px 32px rgba(0,0,0,0.2);">
        <button id="closeVideoModal" style="position:absolute; top:10px; right:10px; background:#800000; color:#fff; border:none; border-radius:50%; width:32px; height:32px; font-size:1.3em; cursor:pointer;">&times;</button>
        <div id="videoModalContent" style="width:100%; height:60vh; min-width:320px; min-height:200px; display:flex; justify-content:center; align-items:center;"></div>
    </div>
</div>

<script>
    // Blog data from database
    const blogs = <?php echo json_encode(array_map(function($blog) {
        // Parse tags
        $tags = !empty($blog['tags']) ? array_map('trim', explode(',', $blog['tags'])) : ['general'];
        
        // Format date
        $date = date('d M Y', strtotime($blog['publish_date']));
        
        // Get excerpt or truncate body
        $description = !empty($blog['excerpt']) ? $blog['excerpt'] : '';
        if (empty($description) && !empty($blog['body'])) {
            $bodyData = json_decode($blog['body'], true);
            $bodyText = isset($bodyData['html']) ? strip_tags($bodyData['html']) : strip_tags($blog['body']);
            $description = mb_substr($bodyText, 0, 150) . '...';
        }
        
        // Get cover image or use icon
        $icon = '📝';
        $hasImage = !empty($blog['cover_image']);
        $imagePath = $hasImage ? 'uploads/blogs/' . $blog['cover_image'] : '';
        
        return [
            'id' => $blog['id'],
            'title' => $blog['title'],
            'date' => $date,
            'description' => $description,
            'tags' => $tags,
            'icon' => $icon,
            'hasImage' => $hasImage,
            'imagePath' => $imagePath,
            'link' => 'blog-detail.php?slug=' . urlencode($blog['slug']),
            'videoUrl' => $blog['video_url'] ?? ''
        ];
    }, $blogs)); ?>;

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
            <div class="blog-card" onclick="window.location.href='${blog.link}'">
                <div class="blog-image" style="${blog.hasImage ? `background: url('${blog.imagePath}') center/cover; height: 240px;` : ''}">
                    ${!blog.hasImage ? blog.icon : ''}
                </div>
                <div class="blog-content">
                    <div class="blog-date">${blog.date}</div>
                    <h2 class="blog-title">${blog.title}</h2>
                    <p class="blog-description">${blog.description}</p>
                    <div class="blog-tags">
                        ${blog.tags.map(tag => `<span class="tag" onclick="event.stopPropagation(); filterByTag('${tag}')">${tag}</span>`).join('')}
                    </div>
                    <div style="display:flex; gap:10px; align-items:center; margin-top:8px;">
                        <a href="${blog.link}" class="read-more" onclick="event.stopPropagation()">Read More</a>
                        ${blog.videoUrl && blog.videoUrl.match(/(youtube\.com\/watch\?v=|youtu\.be\/)/) ? `
                            <button class="play-video-btn" onclick="event.stopPropagation(); openVideoModal('${blog.videoUrl}');" style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1px solid #e0bebe; color:#ff0000; font-weight:600; padding:7px 14px; border-radius:8px; font-size:0.98em; text-decoration:none; cursor:pointer;">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="#ff0000" style="vertical-align:middle;"><path d="M23.498 6.186a2.994 2.994 0 0 0-2.112-2.112C19.136 3.5 12 3.5 12 3.5s-7.136 0-9.386.574a2.994 2.994 0 0 0-2.112 2.112C0 8.436 0 12 0 12s0 3.564.502 5.814a2.994 2.994 0 0 0 2.112 2.112C4.864 20.5 12 20.5 12 20.5s7.136 0 9.386-.574a2.994 2.994 0 0 0 2.112-2.112C24 15.564 24 12 24 12s0-3.564-.502-5.814zM9.75 15.02V8.98l6.5 3.02-6.5 3.02z"/></svg>
                                Play Video
                            </button>
                        ` : `
                            <button class="play-video-btn" style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1px solid #e0bebe; color:#aaa; font-weight:600; padding:7px 14px; border-radius:8px; font-size:0.98em; text-decoration:none; cursor:not-allowed; opacity:0.6;">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="#ff0000" style="vertical-align:middle;"><path d="M23.498 6.186a2.994 2.994 0 0 0-2.112-2.112C19.136 3.5 12 3.5 12 3.5s-7.136 0-9.386.574a2.994 2.994 0 0 0-2.112 2.112C0 8.436 0 12 0 12s0 3.564.502 5.814a2.994 2.994 0 0 0 2.112 2.112C4.864 20.5 12 20.5 12 20.5s7.136 0 9.386-.574a2.994 2.994 0 0 0 2.112-2.112C24 15.564 24 12 24 12s0-3.564-.502-5.814zM9.75 15.02V8.98l6.5 3.02-6.5 3.02z"/></svg>
                                Play Video
                            </button>
                        `}
                    </div>
                </div>
            </div>
        `).join('');
    }

    function getYouTubeEmbedUrl(url) {
        const match = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/);
        if (match && match[1]) {
            return `https://www.youtube.com/embed/${match[1]}?autoplay=1`;
        }
        return null;
    }

    function openVideoModal(videoUrl) {
        const embedUrl = getYouTubeEmbedUrl(videoUrl);
        if (!embedUrl) return;
        document.getElementById('videoModalContent').innerHTML = `<iframe width='100%' height='100%' src='${embedUrl}' frameborder='0' allowfullscreen></iframe>`;
        document.getElementById('videoModal').style.display = 'flex';
    }

    document.getElementById('closeVideoModal').onclick = function() {
        document.getElementById('videoModal').style.display = 'none';
        document.getElementById('videoModalContent').innerHTML = '';
    };

    document.addEventListener('click', function(e) {
        if (e.target.id === 'videoModal') {
            document.getElementById('videoModal').style.display = 'none';
            document.getElementById('videoModalContent').innerHTML = '';
        }
    });

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