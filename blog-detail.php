<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/blog-media.php';
require_once __DIR__ . '/helpers/share.php';

// Get slug from URL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    header('Location: blogs.php');
    exit;
}

// Fetch blog from database
$stmt = $pdo->prepare("SELECT * FROM blogs WHERE slug = ? AND status = 'published' LIMIT 1");
$stmt->execute([$slug]);
$blog = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$blog) {
    header('Location: blogs.php');
    exit;
}

// Parse body content
$bodyData = json_decode($blog['body'], true);
$bodyContent = isset($bodyData['html']) ? $bodyData['html'] : $blog['body'];
$basePrefix = vs_get_base_url_prefix();
$bodyContent = vs_blog_normalize_content_media_urls($bodyContent, $basePrefix);
$coverImageUrl = vs_blog_cover_image_url($blog['cover_image'] ?? '', $basePrefix);

// Parse tags
$tags = !empty($blog['tags']) ? array_map('trim', explode(',', $blog['tags'])) : [];

// Format date
$publishDate = date('d F Y', strtotime($blog['publish_date']));
$blogTitlePlain = trim((string)preg_replace('/\s+/', ' ', strip_tags((string)($blog['title'] ?? ''))));
$primaryTag = isset($tags[0]) && $tags[0] !== '' ? $tags[0] : 'Spiritual Knowledge';
$blogShareTitleText = $blogTitlePlain !== '' ? $blogTitlePlain : 'Knowledge article';
$blogCanonicalUrl = vs_project_absolute_url('blog-detail.php?slug=' . rawurlencode($slug));
$blogWhatsAppShareUrl = vs_project_absolute_url('blog-detail.php?slug=' . rawurlencode($slug) . '&share=wa');

$pageTitle = $blogShareTitleText . ' | Knowledge Centre';
$shareTitle = $blogShareTitleText . ' | Vishnusudarshana';
$shareDescription = !empty($blog['excerpt'])
    ? trim((string)preg_replace('/\s+/', ' ', strip_tags((string)$blog['excerpt'])))
    : ('Read this knowledge article on ' . $primaryTag . '.');
$shareUrl = $blogCanonicalUrl;
$shareType = 'article';
$shareImage = $coverImageUrl !== '' ? $coverImageUrl : vs_project_absolute_url('assets/images/logo/logo-iconpwa512.png');

$blogWhatsAppText = "📚 Knowledge Blog ({$publishDate})\n\nMarathi:\n📖 आजचा ज्ञानलेख वाचा - {$blogShareTitleText}.\n\nEnglish:\n📖 Read today's blog - {$blogShareTitleText}.\n\nTopic: {$primaryTag}\n{$blogWhatsAppShareUrl}";

include 'header.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Marcellus&display=swap');
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html, body {
        font-family: 'Marcellus', serif !important;
    }

    .blog-detail-container {
           max-width: 1200px;
           margin: 60px auto;
           padding: 0 20px;
           min-height: 80vh;
    }

    .blog-detail-header {
        margin-bottom: 30px;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #800000;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.95em;
        margin-bottom: 30px;
        transition: all 0.3s ease;
    }

    .back-link:hover {
        gap: 12px;
        color: #b36b00;
    }

    .back-link svg {
        width: 20px;
        height: 20px;
    }

    .blog-title {
        font-size: 2.2em;
        color: #800000;
        margin-bottom: 20px;
        line-height: 1.2;
        font-weight: 700;
    }

    .blog-meta {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #f0f0f0;
    }

    .blog-date {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #666;
        font-size: 0.95em;
    }

    .blog-date svg {
        width: 18px;
        height: 18px;
        color: #800000;
    }

    .blog-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .tag {
        background: linear-gradient(135deg, #f9f3f0, #fef9f6);
        color: #800000;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 600;
        border: 1px solid #f3d5c8;
        transition: all 0.3s ease;
    }

    .tag:hover {
        background: linear-gradient(135deg, #800000, #b36b00);
        color: #fff;
        border-color: transparent;
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(128, 0, 0, 0.2);
    }

    .blog-cover-image {
        width: 100%;
        height: 450px;
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 40px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    }

    .blog-cover-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .blog-cover-image:hover img {
        transform: scale(1.05);
    }

    .blog-content {
        font-size: 1.1em;
        line-height: 1.8;
        color: #333;
        margin-bottom: 40px;
    }

    .blog-content h1,
    .blog-content h2,
    .blog-content h3,
    .blog-content h4 {
        color: #800000;
        margin: 30px 0 16px 0;
        font-weight: 700;
    }

    .blog-content h1 {
        font-size: 2em;
    }

    .blog-content h2 {
        font-size: 1.6em;
    }

    .blog-content h3 {
        font-size: 1.3em;
    }

    .blog-content p {
        margin-bottom: 20px;
    }

    .blog-content ul,
    .blog-content ol {
        margin: 20px 0 20px 30px;
    }

    .blog-content li {
        margin-bottom: 10px;
    }

    .blog-content a {
        color: #800000;
        text-decoration: underline;
        transition: color 0.3s ease;
    }

    .blog-content a:hover {
        color: #b36b00;
    }

    .blog-content blockquote {
        border-left: 4px solid #800000;
        padding: 20px 24px;
        margin: 30px 0;
        background: #f9f3f0;
        border-radius: 0 8px 8px 0;
        font-style: italic;
        color: #555;
    }

    .blog-content img {
        max-width: 100%;
        height: auto;
        border-radius: 12px;
        margin: 24px 0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        cursor: zoom-in;
    }

    .blog-cover-image img {
        cursor: zoom-in;
    }

    .image-lightbox {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.88);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 28px;
        z-index: 100000;
    }

    .image-lightbox.open {
        display: flex;
    }

    .image-lightbox img {
        max-width: min(96vw, 1600px);
        max-height: 92vh;
        width: auto;
        height: auto;
        object-fit: contain;
        border-radius: 10px;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.45);
        background: #111;
    }

    .image-lightbox-close {
        position: absolute;
        top: 18px;
        right: 22px;
        width: 44px;
        height: 44px;
        border: 1px solid rgba(255, 255, 255, 0.45);
        border-radius: 999px;
        background: rgba(0, 0, 0, 0.45);
        color: #fff;
        font-size: 30px;
        line-height: 1;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .blog-content code {
        background: #f5f5f5;
        padding: 2px 6px;
        border-radius: 4px;
        font-family: 'Courier New', monospace;
        font-size: 0.9em;
        color: #800000;
    }

    .blog-content pre {
        background: #f5f5f5;
        padding: 20px;
        border-radius: 8px;
        overflow-x: auto;
        margin: 24px 0;
    }

    .blog-video {
        margin: 40px 0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    }

    .blog-video iframe {
        width: 100%;
        height: 450px;
        border: none;
    }

    .share-section {
        margin-top: 50px;
        padding: 30px;
        background: linear-gradient(135deg, #f9f3f0, #fef9f6);
        border-radius: 16px;
        text-align: center;
    }

        .blog-nav-section {
            margin: 50px 0 0 0;
            padding: 24px 0 0 0;
            border-top: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        .blog-nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #800000 !important;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.15em;
            transition: color 0.3s ease;
            max-width: 48%;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            background: #f9f3f0;
            border-radius: 8px;
            padding: 10px 16px;
            box-shadow: 0 2px 8px rgba(128,0,0,0.07);
        }
        .blog-nav-link:hover {
            color: #b36b00 !important;
            background: #ffe5d0;
        }
        .blog-nav-label {
            font-size: 1em;
            color: #800000;
            font-weight: 600;
            margin-right: 4px;
        }

    .share-section h3 {
        color: #800000;
        font-size: 1.5em;
        margin-bottom: 20px;
    }

    .share-buttons {
        display: flex;
        justify-content: center;
        gap: 14px;
        flex-wrap: wrap;
        margin-top: 8px;
    }

    .share-btn {
        --btn-bg: linear-gradient(135deg, #27d367, #17a34a);
        --btn-border: rgba(16, 102, 50, 0.4);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        min-width: 190px;
        height: 48px;
        padding: 0 20px;
        border-radius: 999px;
        text-decoration: none;
        font-weight: 700;
        letter-spacing: 0.2px;
        line-height: 1;
        transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        color: #fff;
        border: 1px solid var(--btn-border);
        background: var(--btn-bg);
        box-shadow: 0 8px 18px rgba(0, 0, 0, 0.14);
        cursor: pointer;
        font-family: inherit;
    }

    .share-btn svg {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
    }

    .share-btn.whatsapp {
        --btn-bg: linear-gradient(135deg, #27d367, #17a34a);
        --btn-border: rgba(16, 102, 50, 0.4);
    }

    .share-btn.copy-link {
        --btn-bg: linear-gradient(135deg, #8d0012, #5b0010);
        --btn-border: rgba(102, 0, 20, 0.55);
        color: #fff !important;
    }

    .share-btn.copy-link svg {
        color: #fff !important;
        fill: #fff !important;
    }

    .share-btn.copy-link svg path {
        fill: #fff !important;
    }
    .share-btn.copy-link:hover,
    .share-btn.copy-link:focus-visible {
        --btn-bg: linear-gradient(135deg, #ffe7a3, #ffd267);
        --btn-border: rgba(179, 107, 0, 0.55);
        color: #4a1f00 !important;
        filter: none;
    }

    .share-btn.copy-link:hover svg,
    .share-btn.copy-link:focus-visible svg {
        color: #4a1f00 !important;
        fill: #4a1f00 !important;
    }

    .share-btn.copy-link:hover svg path,
    .share-btn.copy-link:focus-visible svg path {
        fill: #4a1f00 !important;
    }

    .share-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2);
        filter: saturate(1.05);
    }

    .share-btn:active {
        transform: translateY(0);
        box-shadow: 0 6px 14px rgba(0, 0, 0, 0.18);
    }

    .share-btn:focus-visible {
        outline: 3px solid rgba(255, 215, 0, 0.45);
        outline-offset: 2px;
    }

    @media (max-width: 768px) {
        .blog-detail-container {
            margin: 40px auto;
            padding: 0 16px;
        }

        .blog-title {
            font-size: 1.8em;
        }

        .blog-meta {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }

        .blog-cover-image {
            height: 250px;
        }

        .blog-content {
            font-size: 1em;
        }

        .blog-video iframe {
            height: 250px;
        }

        .share-buttons {
            flex-direction: column;
        }

        .share-btn {
            width: min(100%, 280px);
            justify-content: center;
        }
    }

    @media (max-width: 600px) {
        .blog-detail-container {
            margin: 20px auto;
            padding: 0 8px;
        }
        .blog-title {
            font-size: 1.3em;
            word-break: break-word;
        }
        .blog-meta {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.95em;
        }
        .blog-cover-image {
            height: 120px;
            border-radius: 10px;
        }
        .blog-content {
            font-size: 0.98em;
            padding: 0;
        }
        .blog-content img {
            border-radius: 8px;
            margin: 12px 0;
        }
        .blog-video iframe {
            height: 120px;
            border-radius: 10px;
        }
        .share-section {
            padding: 10px;
            font-size: 0.95em;
        }
        .share-buttons {
            flex-direction: column;
            gap: 8px;
        }
        .share-btn {
            width: 100%;
            min-width: 0;
            height: 46px;
            padding: 0 12px;
            font-size: 0.95em;
        }
            .blog-nav-section {
                flex-direction: column;
                gap: 10px;
                padding: 16px 0 0 0;
            }
            .blog-nav-link {
                max-width: 100%;
                font-size: 0.98em;
            }
    }

    @media (max-width: 480px) {
        .blog-title {
            font-size: 1.2em;
        }

        .blog-cover-image {
            height: 200px;
        }

        .share-section {
            padding: 20px;
        }

        .image-lightbox {
            padding: 16px;
        }

        .image-lightbox-close {
            top: 10px;
            right: 10px;
            width: 40px;
            height: 40px;
            font-size: 26px;
        }
    }
</style>

<div class="blog-detail-container">
    <a href="blogs.php" class="back-link">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        Back to Blogs
    </a>

    <div class="blog-detail-header">
        <h1 class="blog-title"><?= htmlspecialchars($blog['title']) ?></h1>
        
        <div class="blog-meta">
            <div class="blog-date">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <?= $publishDate ?>
            </div>
            
            <?php if (!empty($tags)): ?>
                <div class="blog-tags">
                    <?php foreach ($tags as $tag): ?>
                        <span class="tag"><?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($coverImageUrl !== ''): ?>
        <div class="blog-cover-image">
            <img src="<?= htmlspecialchars($coverImageUrl) ?>" alt="<?= htmlspecialchars($blog['title']) ?>">
        </div>
    <?php endif; ?>

    <?php if (!empty($blog['excerpt'])): ?>
        <div style="font-size: 1.2em; color: #666; font-style: italic; margin-bottom: 30px; padding: 20px; background: #f9f3f0; border-radius: 12px; border-left: 4px solid #800000;">
            <?= htmlspecialchars($blog['excerpt']) ?>
        </div>
    <?php endif; ?>

    <div class="blog-content">
        <?= $bodyContent ?>
    </div>

    <?php if (!empty($blog['video_url'])): ?>
        <div class="blog-video">
            <?php
            // Convert YouTube URL to embed format
            $videoUrl = $blog['video_url'];
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $videoUrl, $matches)) {
                $videoId = $matches[1];
                echo '<iframe src="https://www.youtube.com/embed/' . htmlspecialchars($videoId) . '" allowfullscreen></iframe>';
            }
            ?>
        </div>
    <?php endif; ?>

    <div class="share-section">
        <h3>Share This Article</h3>
        <div class="share-buttons">
            <a href="https://wa.me/?text=<?= urlencode($blogWhatsAppText) ?>" target="_blank" class="share-btn whatsapp">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                WhatsApp
            </a>
            <button type="button" class="share-btn copy-link" onclick="copyBlogLink()">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                Copy Link
            </button>
        </div>
    </div>

    <?php
        // Fetch next published blog by ID
        $nextStmt = $pdo->prepare("SELECT slug, title FROM blogs WHERE status = 'published' AND id > ? ORDER BY id ASC LIMIT 1");
        $nextStmt->execute([$blog['id']]);
        $nextBlog = $nextStmt->fetch(PDO::FETCH_ASSOC);

        // Fetch previous published blog by ID
        $prevStmt = $pdo->prepare("SELECT slug, title FROM blogs WHERE status = 'published' AND id < ? ORDER BY id DESC LIMIT 1");
        $prevStmt->execute([$blog['id']]);
        $prevBlog = $prevStmt->fetch(PDO::FETCH_ASSOC);
    ?>

    <div class="blog-nav-section">
        <?php if ($prevBlog): ?>
            <a class="blog-nav-link" href="blog-detail.php?slug=<?= urlencode($prevBlog['slug']) ?>">
                <span class="blog-nav-label">&#8592; Previous:</span>
                <?= htmlspecialchars($prevBlog['title']) ?>
            </a>
        <?php else: ?>
            <span style="color:#ccc;max-width:45%;">&nbsp;</span>
        <?php endif; ?>

        <?php if ($nextBlog): ?>
            <a class="blog-nav-link" href="blog-detail.php?slug=<?= urlencode($nextBlog['slug']) ?>">
                <span class="blog-nav-label">Next: </span>
                <?= htmlspecialchars($nextBlog['title']) ?> &#8594;
            </a>
        <?php else: ?>
            <span style="color:#ccc;max-width:45%;">&nbsp;</span>
        <?php endif; ?>
    </div>
</div>

<div id="imageLightbox" class="image-lightbox" aria-hidden="true">
    <button type="button" id="imageLightboxClose" class="image-lightbox-close" aria-label="Close image preview">&times;</button>
    <img id="imageLightboxImg" src="" alt="">
</div>

<script>
function copyBlogLink() {
    const url = window.location.href;
    navigator.clipboard.writeText(url).then(function() {
        const btn = document.querySelector('.share-btn.copy-link');
        if (!btn) return;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> Copied!';
        btn.style.background = 'linear-gradient(135deg, #2dbf67, #1b944d)';
        setTimeout(function() {
            btn.innerHTML = originalText;
            btn.style.background = '';
        }, 2000);
    });
}

(() => {
    const lightbox = document.getElementById('imageLightbox');
    const lightboxImg = document.getElementById('imageLightboxImg');
    const closeBtn = document.getElementById('imageLightboxClose');
    const images = document.querySelectorAll('.blog-cover-image img, .blog-content img');
    if (!lightbox || !lightboxImg || !closeBtn || !images.length) {
        return;
    }

    const openLightbox = (imgEl) => {
        lightboxImg.src = imgEl.currentSrc || imgEl.src;
        lightboxImg.alt = imgEl.alt || 'Image preview';
        lightbox.classList.add('open');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    const closeLightbox = () => {
        lightbox.classList.remove('open');
        lightbox.setAttribute('aria-hidden', 'true');
        lightboxImg.src = '';
        document.body.style.overflow = '';
    };

    images.forEach((imgEl) => {
        imgEl.addEventListener('click', (event) => {
            event.preventDefault();
            openLightbox(imgEl);
        });
    });

    closeBtn.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox) {
            closeLightbox();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && lightbox.classList.contains('open')) {
            closeLightbox();
        }
    });
})();
</script>

<?php include 'footer.php'; ?>
