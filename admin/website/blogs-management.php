<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../../config/db.php';
// --- Search and Tag Filter Logic ---

$search = trim($_GET['search'] ?? '');
$selectedTag = trim($_GET['tag'] ?? '');
$selectedStatus = trim($_GET['status'] ?? '');

// Fetch all blogs
$stmt = $pdo->query("SELECT * FROM blogs ORDER BY created_at DESC");
$blogs = $stmt->fetchAll();

// Collect all unique tags
$allTags = [];
foreach ($blogs as $blog) {
    $tagsArr = array_map('trim', explode(',', $blog['tags']));
    foreach ($tagsArr as $tag) {
        if ($tag !== '') $allTags[$tag] = true;
    }
}
$allTags = array_keys($allTags);

// Filter blogs by search and tag
$filteredBlogs = array_filter($blogs, function($blog) use ($search, $selectedTag, $selectedStatus) {
    $matchesSearch = $search === '' || stripos($blog['title'], $search) !== false;
    $matchesTag = $selectedTag === '' || in_array($selectedTag, array_map('trim', explode(',', $blog['tags'])));
    $matchesStatus = $selectedStatus === '' || $blog['status'] === $selectedStatus;
    return $matchesSearch && $matchesTag && $matchesStatus;
});
// Handle status update
if (isset($_POST['update_status']) && isset($_POST['blog_id']) && isset($_POST['new_status'])) {
    $blogId = (int)$_POST['blog_id'];
    $newStatus = $_POST['new_status'];
    if ($blogId > 0 && in_array($newStatus, ['draft', 'scheduled', 'published'])) {
        $stmt = $pdo->prepare("UPDATE blogs SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $blogId]);
        header('Location: blogs-management.php?status_updated=1');
        exit;
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId > 0) {
        $stmt = $pdo->prepare("DELETE FROM blogs WHERE id = ?");
        $stmt->execute([$deleteId]);
        header('Location: blogs-management.php?deleted=1');
        exit;
    }
}

// Fetch all blogs
$stmt = $pdo->query("SELECT * FROM blogs ORDER BY created_at DESC");
$blogs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blogs Management - Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
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
            margin-bottom: 24px;
            font-family: inherit;
            font-size: 2.2em;
            font-weight: 700;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .add-btn {
            display: inline-block;
            background: linear-gradient(135deg, #800000, #b36b00);
            color: #fff;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1em;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.2);
            border: none;
            cursor: pointer;
        }

        .add-btn:hover {
            background: linear-gradient(135deg, #a00000, #d47d00);
            box-shadow: 0 6px 20px rgba(128, 0, 0, 0.3);
            transform: translateY(-2px);
        }

        .content-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(224, 190, 190, 0.15);
            padding: 32px;
            margin-bottom: 24px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state-icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            color: #800000;
            font-size: 1.5em;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .empty-state p {
            color: #666;
            font-size: 1.05em;
            line-height: 1.6;
        }

        /* Table Styles */
        .blog-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(224, 190, 190, 0.15);
        }

        .blog-table th,
        .blog-table td {
            padding: 16px 14px;
            border-bottom: 1px solid #f3caca;
            text-align: left;
            font-size: 1em;
        }

        .blog-table th {
            background: #f9eaea;
            color: #800000;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .blog-table tr:last-child td {
            border-bottom: none;
        }

        .blog-table tbody tr {
            transition: background 0.2s ease;
        }

        .blog-table tbody tr:hover {
            background: #fafafa;
        }

        .action-btn {
            background: #007bff;
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9em;
            margin-right: 6px;
            transition: all 0.2s ease;
            display: inline-block;
            border: none;
            cursor: pointer;
        }

        .action-btn.edit {
            background: #28a745;
        }

        .action-btn.delete {
            background: #dc3545;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .action-btn.edit:hover {
            background: #218838;
        }

        .action-btn.delete:hover {
            background: #c82333;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85em;
            display: inline-block;
            text-align: center;
        }

        .status-published {
            background: #e5ffe5;
            color: #1a8917;
        }

        .status-draft {
            background: #fff3cd;
            color: #856404;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-container {
                padding: 20px 12px;
            }

            h1 {
                font-size: 1.8em;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .add-btn {
                width: 100%;
                text-align: center;
            }

            .content-card {
                padding: 20px;
            }

            .blog-table {
                font-size: 0.9em;
            }

            .blog-table th,
            .blog-table td {
                padding: 12px 10px;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>

<div class="admin-container">
    <div class="page-header">
        <h1>Blogs Management</h1>
        <a class="add-btn" href="blog-create.php">+ Add New Blog</a>
    </div>
    <div class="content-card" style="margin-bottom:18px;">
        <form method="get" style="display:flex; gap:18px; flex-wrap:wrap; align-items:center;">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by title..." style="padding:10px 14px; border-radius:8px; border:1px solid #e0bebe; font-size:1em; min-width:220px;">
            <select name="tag" onchange="this.form.submit();" style="padding:10px 14px; border-radius:8px; border:1px solid #e0bebe; font-size:1em; min-width:180px;">
                <option value="">-- Filter by Tag --</option>
                <?php foreach ($allTags as $tag): ?>
                    <option value="<?= htmlspecialchars($tag) ?>" <?= $selectedTag === $tag ? 'selected' : '' ?>><?= htmlspecialchars($tag) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" onchange="this.form.submit();" style="padding:10px 14px; border-radius:8px; border:1px solid #e0bebe; font-size:1em; min-width:150px;">
                <option value="">-- Filter by Status --</option>
                <option value="draft" <?= $selectedStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="scheduled" <?= $selectedStatus === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                <option value="published" <?= $selectedStatus === 'published' ? 'selected' : '' ?>>Published</option>
            </select>
            <button type="submit" class="add-btn" style="background:#800000; padding:10px 18px; font-size:1em; border-radius:8px;">Filter</button>
        </form>
    </div>
    <div class="content-card">
        <?php if (empty($filteredBlogs)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📝</div>
                <h3>No Blogs Found</h3>
                <p>Add a new blog to get started or adjust your filters.</p>
            </div>
        <?php else: ?>
            <table class="blog-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Tags</th>
                        <th>Status</th>
                        <th>Publish Date</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filteredBlogs as $blog): ?>
                        <tr>
                            <td><?= $blog['id'] ?></td>
                            <td><?= htmlspecialchars($blog['title']) ?></td>
                            <td><?= htmlspecialchars($blog['tags']) ?></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return false;">
                                    <input type="hidden" name="blog_id" value="<?= $blog['id'] ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <select name="new_status" onchange="this.form.submit();" style="padding:6px 12px; border-radius:8px; border:1px solid #e0bebe; font-weight:600; color:#800000; background:#f9eaea;">
                                        <option value="draft" <?= $blog['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                        <option value="scheduled" <?= $blog['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                        <option value="published" <?= $blog['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                                    </select>
                                </form>
                            </td>
                            <td><?= htmlspecialchars($blog['publish_date']) ?></td>
                            <td><?= date('d M Y', strtotime($blog['created_at'])) ?></td>
                            <td>
                                <a href="blog-create.php?id=<?= $blog['id'] ?>" class="action-btn edit">Edit</a>
                                <a href="blogs-management.php?delete=<?= $blog['id'] ?>" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this blog?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
