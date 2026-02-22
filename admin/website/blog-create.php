<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/blog-media.php';

$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($editId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM blogs WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $blog = $stmt->fetch();
    if ($blog) {
        $title = $blog['title'];
        $slug = $blog['slug'];
        $excerpt = $blog['excerpt'];
        // Decode body JSON
        $bodyJson = $blog['body'];
        $bodyArr = json_decode($bodyJson, true);
        $body = isset($bodyArr['html']) ? $bodyArr['html'] : $bodyJson;
        $tags = $blog['tags'];
        $video_url = $blog['video_url'];
        $publish_date = $blog['publish_date'];
        $status = $blog['status'];
        $cover_image = vs_blog_normalize_cover_image_for_storage($blog['cover_image']);
        $_SESSION['cover_image'] = $cover_image;
    }
}

if ($editId <= 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    unset($_SESSION['cover_image']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    // Auto-generate slug from title if not provided or if changed
    if ($slug === '' && $title !== '') {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
        $slug = trim($slug, '-');
    }
    $excerpt = trim($_POST['excerpt'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $body = vs_blog_normalize_content_media_urls($body);
    // Store body as JSON
    $bodyJson = json_encode(['html' => $body], JSON_UNESCAPED_UNICODE);
    $tags = trim($_POST['tags'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $publish_date = $_POST['publish_date'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'draft';
    $cover_image = '';
    $errors = [];

    // Validate required fields
    if ($title === '' || $slug === '') {
        $errors[] = 'Title and Slug are required.';
    }
    // Check for duplicate slug
    if ($slug !== '') {
        if ($editId > 0) {
            $stmt = $pdo->prepare("SELECT id FROM blogs WHERE slug = ? AND id != ? LIMIT 1");
            $stmt->execute([$slug, $editId]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM blogs WHERE slug = ? LIMIT 1");
            $stmt->execute([$slug]);
        }
        if ($stmt->fetch()) {
            $errors[] = 'Slug already exists. Please choose a unique slug.';
        }
    }

    // Handle cover image upload (separate action)
    if (isset($_POST['upload_image']) && isset($_FILES['cover_image_file']) && $_FILES['cover_image_file']['error'] === UPLOAD_ERR_OK) {
        $imgTmp = $_FILES['cover_image_file']['tmp_name'];
        $imgName = basename($_FILES['cover_image_file']['name']);
        $imgExt = strtolower(pathinfo($imgName, PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($imgExt, $allowed)) {
            $newName = uniqid('blog_', true) . '.' . $imgExt;
            $uploadDir = __DIR__ . '/../../uploads/blogs/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $uploadPath = $uploadDir . $newName;
            if (move_uploaded_file($imgTmp, $uploadPath)) {
                $cover_image = $newName;
                $_SESSION['cover_image'] = $cover_image;
            } else {
                $errors[] = 'Failed to upload image.';
            }
        } else {
            $errors[] = 'Invalid image format.';
        }
    }

    // Use uploaded image from session if available
    if (isset($_SESSION['cover_image']) && $_SESSION['cover_image'] !== '') {
        $cover_image = vs_blog_normalize_cover_image_for_storage($_SESSION['cover_image']);
    }

    // If no errors, insert or update in DB
    if (empty($errors)) {
        $cover_image = vs_blog_normalize_cover_image_for_storage($cover_image);
        if ($editId > 0) {
            // Update existing blog
            $stmt = $pdo->prepare("UPDATE blogs SET title = ?, slug = ?, excerpt = ?, body = ?, tags = ?, cover_image = ?, video_url = ?, publish_date = ?, status = ? WHERE id = ?");
            $stmt->execute([
                $title,
                $slug,
                $excerpt,
                $bodyJson,
                $tags,
                $cover_image,
                $video_url,
                $publish_date,
                $status,
                $editId
            ]);
        } else {
            // Insert new blog
            $stmt = $pdo->prepare("INSERT INTO blogs (title, slug, excerpt, body, tags, cover_image, video_url, publish_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title,
                $slug,
                $excerpt,
                $bodyJson,
                $tags,
                $cover_image,
                $video_url,
                $publish_date,
                $status
            ]);
        }
        unset($_SESSION['cover_image']);
        $success = true;
        // Redirect to management page after save
        header('Location: blogs-management.php?success=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Blog - Admin</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f7f7fa;
            margin: 0;
            padding: 0;
        }

        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 20px;
        }

        h1 {
            color: #800000;
            margin-bottom: 22px;
            font-family: inherit;
            font-size: 2.1em;
            font-weight: 700;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .back-link {
            color: #800000;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .content-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(224, 190, 190, 0.15);
            padding: 28px;
            margin-bottom: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        label {
            font-weight: 700;
            color: #800000;
            font-size: 0.95em;
        }

        input[type="text"],
        input[type="date"],
        input[type="url"],
        input[type="file"],
        select,
        textarea {
            padding: 12px 14px;
            border: 1px solid #e0bebe;
            border-radius: 10px;
            font-size: 1em;
            font-family: inherit;
            background: #fff;
            transition: border 0.2s ease, box-shadow 0.2s ease;
        }

        textarea {
            min-height: 140px;
            resize: vertical;
        }

        input[type="file"] {
            padding: 10px;
            background: #fff;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #800000;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.12);
        }

        .helper-text {
            color: #666;
            font-size: 0.9em;
        }

        .pill-note {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f9eaea;
            color: #800000;
            padding: 10px 14px;
            border-radius: 12px;
            font-weight: 600;
            margin-bottom: 16px;
            border: 1px solid #f3caca;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .btn-primary,
        .btn-secondary {
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 1em;
        }

        .btn-primary {
            background: linear-gradient(135deg, #800000, #b36b00);
            color: #fff;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.22);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(128, 0, 0, 0.28);
        }

        .btn-secondary {
            background: #f9eaea;
            color: #800000;
            border: 1px solid #f3caca;
        }

        .btn-secondary:hover {
            background: #f4dada;
        }

        .section-title {
            font-size: 1.1em;
            color: #800000;
            margin-bottom: 14px;
            font-weight: 700;
        }

        .two-col {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 18px;
        }

        .editor-wrapper {
            border: 1px solid #e0bebe;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            box-shadow: inset 0 1px 0 #f7f0f0;
        }

        .editor-area {
            min-height: 260px;
            padding: 14px;
            outline: none;
            font-size: 1em;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .admin-container {
                padding: 22px 12px;
            }

            .content-card {
                padding: 20px;
            }

            h1 {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <div class="page-header">
        <h1>Create Blog</h1>
        <a class="back-link" href="blogs-management.php">← Back to Blogs Management</a>
    </div>

    <div class="content-card">
        <?php if (!empty($errors)): ?>
            <div class="pill-note" style="background:#fef2f2; color:#800000; border-color:#f5c6cb;">
                <?= implode('<br>', $errors); ?>
            </div>
        <?php elseif (isset($success) && $success): ?>
            <div class="pill-note" style="background:#d1e7dd; color:#0f5132; border-color:#badbcc;">
                Blog saved successfully! Redirecting...
            </div>
        <?php endif; ?>

        <form id="blogCreateForm" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="post" enctype="multipart/form-data">
            <div class="section-title">Basic Details</div>
            <div class="form-grid">
                <div class="form-group">
                    <label for="title">Blog Title</label>
                    <input type="text" id="title" name="title" placeholder="Enter blog title" value="<?= htmlspecialchars($title ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="slug">Slug (URL)</label>
                    <input type="text" id="slug" name="slug" placeholder="e.g. how-to-read-kundali" value="<?= htmlspecialchars($slug ?? '') ?>" required>
                    <div class="helper-text">Use lowercase with hyphens; auto-generate later.</div>
                </div>
                <div class="form-group">
                    <label for="publish_date">Publish Date</label>
                    <input type="date" id="publish_date" name="publish_date" value="<?= htmlspecialchars($publish_date ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?= (isset($status) && $status === 'draft') ? 'selected' : '' ?>>Draft</option>
                        <option value="scheduled" <?= (isset($status) && $status === 'scheduled') ? 'selected' : '' ?>>Scheduled</option>
                        <option value="published" <?= (isset($status) && $status === 'published') ? 'selected' : '' ?>>Published</option>
                    </select>
                </div>
            </div>

            <div class="section-title" style="margin-top:24px;">Content</div>
            <div class="form-group">
                <label for="excerpt">Short Excerpt</label>
                <textarea id="excerpt" name="excerpt" placeholder="A quick summary shown on blog cards" maxlength="240"><?= htmlspecialchars($excerpt ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="body">Full Content</label>
                <div class="editor-wrapper">
                    <textarea id="bodyEditor" class="editor-area" aria-label="Blog content editor"></textarea>
                </div>
                <textarea id="body" name="body" style="display:none;"></textarea>
                <div class="helper-text">Supports headings, tables, links, quotes, media embeds, and inline image upload.</div>
            </div>

            <div class="section-title" style="margin-top:24px;">Media & Tags</div>
            <div class="two-col">
                <div class="form-group">
                    <label for="cover_image_file">Cover Image Upload</label>
                    <input type="file" id="cover_image_file" name="cover_image_file" accept="image/*">
                    <button type="button" id="uploadImageBtn" class="btn-secondary" style="margin-top:8px;">Upload Image</button>
                    <?php
                    $coverImageUrl = vs_blog_cover_image_url($cover_image ?? '');
                    $coverImageFsPath = vs_blog_cover_image_file_path($cover_image ?? '');
                    $canShowCoverImage = $coverImageUrl !== '' && ($coverImageFsPath === '' || file_exists($coverImageFsPath));
                    ?>
                    <div id="imagePreviewDiv" style="margin-top:10px;">
                        <?php if ($canShowCoverImage): ?>
                            <img src="<?= htmlspecialchars($coverImageUrl) ?>" alt="Cover Image" style="max-width:220px; border-radius:10px; box-shadow:0 2px 8px #e0bebe;">
                        <?php endif; ?>
                    </div>
                    <div class="helper-text">Upload a high-quality image. Preferred 1200x630 or better.</div>
                </div>
                <div class="form-group">
                    <label for="tags">Tags (comma separated)</label>
                    <input type="text" id="tags" name="tags" placeholder="kundali, astrology, tips" value="<?= htmlspecialchars($tags ?? '') ?>">
                    <div class="helper-text">These should match website topics for filtering.</div>
                </div>
            </div>

            <div class="form-group">
                <label for="video_url">YouTube Video URL (optional)</label>
                <input type="url" id="video_url" name="video_url" placeholder="https://www.youtube.com/watch?v=..." value="<?= htmlspecialchars($video_url ?? '') ?>">
                <div class="helper-text">Paste a full YouTube link to embed in the blog.</div>
            </div>

            <div class="actions">
                <button type="submit" class="btn-primary" id="saveBlogBtn">Save Blog</button>
                <button type="button" class="btn-secondary" onclick="window.history.back();">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.1/tinymce.min.js" referrerpolicy="origin"></script>
<script>
                // Auto-generate slug from title on the client side
                const titleInput = document.getElementById('title');
                const slugInput = document.getElementById('slug');
                titleInput.addEventListener('input', function() {
                    let slug = titleInput.value.toLowerCase()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                    slugInput.value = slug;
                });
        // AJAX image upload and preview
        const coverInput = document.getElementById('cover_image_file');
        const uploadBtn = document.getElementById('uploadImageBtn');
        const saveBlogBtn = document.getElementById('saveBlogBtn');
        let uploadedImagePath = '';

        uploadBtn.addEventListener('click', function() {
            if (!coverInput.files.length) return;
            const formData = new FormData();
            formData.append('cover_image_file', coverInput.files[0]);
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
            fetch('upload-blog-image.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Upload Image';
                if (data.success) {
                    uploadedImagePath = data.path;
                    // Show preview, only one image
                    let previewDiv = document.getElementById('imagePreviewDiv');
                    if (previewDiv) {
                        let webPath = data.url || ('../../uploads/blogs/' + data.path);
                        previewDiv.innerHTML = `<img src="${webPath}" alt="Cover Image" style="max-width:220px; border-radius:10px; box-shadow:0 2px 8px #e0bebe;">`;
                    }
                } else {
                    alert(data.error || 'Upload failed');
                }
                saveBlogBtn.disabled = false; // Always enable after upload
            })
            .catch(() => {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Upload Image';
                alert('Upload failed');
                saveBlogBtn.disabled = false; // Always enable after error
            });
        });

        // Always enable Save Blog button on file change
        coverInput.addEventListener('change', function() {
            saveBlogBtn.disabled = false;
        });

        // On page unload, delete image if not saved
        let blogSaved = false;
        document.getElementById('blogCreateForm').addEventListener('submit', function() {
            if (window.tinymce && window.tinymce.get('bodyEditor')) {
                document.getElementById('body').value = window.tinymce.get('bodyEditor').getContent();
            } else {
                document.getElementById('body').value = document.getElementById('bodyEditor').value || '';
            }
            blogSaved = true;
        });
        window.addEventListener('beforeunload', function(e) {
            if (!blogSaved && uploadedImagePath) {
                navigator.sendBeacon('delete-blog-image.php', JSON.stringify({ path: uploadedImagePath }));
            }
        });
    // TinyMCE rich text editor with inline image/media tools and uploads
    const bodyTextarea = document.getElementById('body');
    const bodyEditorEl = document.getElementById('bodyEditor');
    const initialEditorHtml = <?= json_encode($body ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    bodyEditorEl.value = initialEditorHtml || '';

    const uploadEditorFile = file => new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'upload-editor-image.php', true);
        xhr.responseType = 'json';
        xhr.onerror = () => reject('Upload failed');
        xhr.onload = () => {
            const response = xhr.response || {};
            if (!response.url) {
                reject(response.error || 'Upload failed');
                return;
            }
            resolve(response.url);
        };
        const data = new FormData();
        data.append('upload', file);
        xhr.send(data);
    });

    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '#bodyEditor',
            height: 560,
            menubar: 'file edit view insert format tools table help',
            plugins: 'advlist autolink lists link image media table code fullscreen preview searchreplace visualblocks wordcount charmap emoticons codesample autoresize anchor',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table blockquote codesample | removeformat | preview code fullscreen',
            toolbar_mode: 'sliding',
            branding: false,
            promotion: false,
            content_style: 'body { font-family:Segoe UI,Tahoma,Verdana,sans-serif; font-size:16px } img { max-width:100%; height:auto; }',
            object_resizing: 'img,table,iframe,video',
            image_advtab: true,
            image_caption: true,
            image_dimensions: true,
            media_dimensions: true,
            media_live_embeds: true,
            link_default_target: '_blank',
            extended_valid_elements: 'iframe[src|frameborder|style|scrolling|class|width|height|name|align|allow|allowfullscreen],video[*],source[*]',
            setup: function(editor) {
                editor.on('init', function() {
                    editor.setContent(initialEditorHtml || '');
                    bodyTextarea.value = editor.getContent();
                });
                editor.on('change input undo redo', function() {
                    bodyTextarea.value = editor.getContent();
                });
            },
            images_upload_handler: (blobInfo, progress) => new Promise((resolve, reject) => {
                const file = blobInfo.blob();
                uploadEditorFile(file).then(resolve).catch(reject);
            }),
            file_picker_types: 'image media',
            file_picker_callback: (callback, value, meta) => {
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = meta.filetype === 'media'
                    ? 'video/*,audio/*'
                    : 'image/*';
                input.onchange = async () => {
                    const file = input.files && input.files[0];
                    if (!file) {
                        return;
                    }
                    try {
                        const url = await uploadEditorFile(file);
                        callback(url, { title: file.name });
                    } catch (err) {
                        alert(typeof err === 'string' ? err : 'Upload failed');
                    }
                };
                input.click();
            }
        });
    } else {
        alert('Failed to load rich text editor. Please refresh and try again.');
    }
</script>

</body>
</html>




