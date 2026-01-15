<?php
include 'header.php';
require_once __DIR__ . '/config/db.php';

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

// Parse tags
$tags = !empty($blog['tags']) ? array_map('trim', explode(',', $blog['tags'])) : [];

// Format date
$publishDate = date('d F Y', strtotime($blog['publish_date']));
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
        font-size: 2.8em;
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
        gap: 15px;
        flex-wrap: wrap;
    }

    .share-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        color: #fff;
    }

    .share-btn.facebook {
        background: #1877f2;
    }

    .share-btn.twitter {
        background: #1da1f2;
    }

    .share-btn.whatsapp {
        background: #25d366;
    }

    .share-btn.linkedin {
        background: #0077b5;
    }

    .share-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }

    @media (max-width: 768px) {
        .blog-detail-container {
            margin: 40px auto;
            padding: 0 16px;
        }

        .blog-title {
            font-size: 2em;
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
            width: 100%;
            justify-content: center;
        }
    }

    @media (max-width: 600px) {
        .blog-detail-container {
            margin: 20px auto;
            padding: 0 8px;
        }
        .blog-title {
            font-size: 1.2em;
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
            padding: 10px 8px;
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
            font-size: 1.6em;
        }

        .blog-cover-image {
            height: 200px;
        }

        .share-section {
            padding: 20px;
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

    <?php if (!empty($blog['cover_image'])): ?>
        <div class="blog-cover-image">
            <img src="uploads/blogs/<?= htmlspecialchars($blog['cover_image']) ?>" alt="<?= htmlspecialchars($blog['title']) ?>">
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
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>" target="_blank" class="share-btn facebook">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                Facebook
            </a>
            <a href="https://twitter.com/intent/tweet?url=<?= urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>&text=<?= urlencode($blog['title']) ?>" target="_blank" class="share-btn twitter">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                Twitter
            </a>
            <a href="https://wa.me/?text=<?= urlencode($blog['title'] . ' - https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>" target="_blank" class="share-btn whatsapp">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                WhatsApp
            </a>
            <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?= urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>&title=<?= urlencode($blog['title']) ?>" target="_blank" class="share-btn linkedin">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                LinkedIn
            </a>
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

<?php include 'footer.php'; ?>
