<?php
require_once (is_file(__DIR__ . '/includes/permissions.php') ? __DIR__ . '/includes/permissions.php' : dirname(__DIR__) . '/includes/permissions.php');
admin_enforce_mapped_permission('auto');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../helpers/product_variants.php';

vs_ensure_product_variant_schema($pdo);

$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_variant') {
            $variantName = trim((string)($_POST['variant_name'] ?? ''));
            $variantValuesRaw = trim((string)($_POST['variant_values'] ?? ''));

            if ($variantName === '') {
                throw new RuntimeException('Variant name is required.');
            }

            $createdAt = date('Y-m-d H:i:s');
            $pdo->beginTransaction();

            $insertVariant = $pdo->prepare("
                INSERT INTO product_variants (variant_name, is_active, created_at, updated_at)
                VALUES (?, 1, ?, ?)
            ");
            $insertVariant->execute([$variantName, $createdAt, $createdAt]);
            $variantId = (int)$pdo->lastInsertId();

            $values = vs_parse_variant_values_text($variantValuesRaw);
            if (!empty($values)) {
                $insertValue = $pdo->prepare("
                    INSERT INTO product_variant_values (variant_id, value_name, is_active, created_at, updated_at)
                    VALUES (?, ?, 1, ?, ?)
                ");
                foreach ($values as $value) {
                    $insertValue->execute([$variantId, $value, $createdAt, $createdAt]);
                }
            }

            $pdo->commit();
            $successMsg = 'Variant added successfully.';
        } elseif ($action === 'delete_variant') {
            $variantId = (int)($_POST['variant_id'] ?? 0);
            if ($variantId <= 0) {
                throw new RuntimeException('Invalid variant.');
            }

            $pdo->beginTransaction();
            $clearProducts = $pdo->prepare("UPDATE products SET variant_id = NULL WHERE variant_id = ?");
            $clearProducts->execute([$variantId]);

            $deleteVariant = $pdo->prepare("DELETE FROM product_variants WHERE id = ?");
            $deleteVariant->execute([$variantId]);
            $pdo->commit();

            $successMsg = 'Variant deleted successfully.';
        } elseif ($action === 'add_variant_value') {
            $variantId = (int)($_POST['variant_id'] ?? 0);
            $valueName = trim((string)($_POST['value_name'] ?? ''));

            if ($variantId <= 0 || $valueName === '') {
                throw new RuntimeException('Variant and value are required.');
            }

            $existsStmt = $pdo->prepare("SELECT id FROM product_variants WHERE id = ? LIMIT 1");
            $existsStmt->execute([$variantId]);
            if (!$existsStmt->fetchColumn()) {
                throw new RuntimeException('Variant does not exist.');
            }

            $createdAt = date('Y-m-d H:i:s');
            $insertValue = $pdo->prepare("
                INSERT INTO product_variant_values (variant_id, value_name, is_active, created_at, updated_at)
                VALUES (?, ?, 1, ?, ?)
            ");
            $insertValue->execute([$variantId, $valueName, $createdAt, $createdAt]);
            $successMsg = 'Variant value added successfully.';
        } elseif ($action === 'delete_variant_value') {
            $valueId = (int)($_POST['value_id'] ?? 0);
            if ($valueId <= 0) {
                throw new RuntimeException('Invalid variant value.');
            }

            $deleteValue = $pdo->prepare("DELETE FROM product_variant_values WHERE id = ?");
            $deleteValue->execute([$valueId]);
            $successMsg = 'Variant value deleted successfully.';
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((string)$e->getCode() === '23000') {
            $errorMsg = 'Duplicate entry. Use a different name/value.';
        } else {
            $errorMsg = 'Database error while saving variant data.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMsg = $e->getMessage();
    }
}

$variantRows = $pdo->query("
    SELECT
        v.id,
        v.variant_name,
        v.is_active,
        COUNT(DISTINCT p.id) AS product_count,
        COUNT(DISTINCT vv.id) AS value_count
    FROM product_variants v
    LEFT JOIN products p ON p.variant_id = v.id
    LEFT JOIN product_variant_values vv ON vv.variant_id = v.id
    GROUP BY v.id, v.variant_name, v.is_active
    ORDER BY v.variant_name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$valuesRows = $pdo->query("
    SELECT id, variant_id, value_name, is_active
    FROM product_variant_values
    ORDER BY variant_id ASC, value_name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$valuesByVariant = [];
foreach ($valuesRows as $row) {
    $variantId = (int)$row['variant_id'];
    if (!isset($valuesByVariant[$variantId])) {
        $valuesByVariant[$variantId] = [];
    }
    $valuesByVariant[$variantId][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Product Variants</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f7f7fa; margin: 0; }
        .admin-container { max-width: 980px; margin: 0 auto; padding: 24px 12px; }
        h1 { color: #800000; margin-bottom: 12px; }
        .top-links { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; }
        .link-btn { display: inline-block; padding: 8px 14px; border-radius: 8px; text-decoration: none; font-weight: 600; }
        .link-btn.back { background: #f1e7e7; color: #800000; }
        .link-btn.primary { background: #800000; color: #fff; }
        .alert { padding: 10px 12px; border-radius: 8px; margin-bottom: 14px; }
        .alert.error { background: #ffecec; color: #b00020; border: 1px solid #f3b3bf; }
        .alert.success { background: #ecfff0; color: #177c35; border: 1px solid #a5e2b7; }
        .panel { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px #e0bebe22; padding: 16px; margin-bottom: 16px; }
        .panel h2 { margin: 0 0 10px; color: #800000; font-size: 1.1em; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; align-items: end; }
        .form-grid .full { grid-column: 1 / span 2; }
        .form-label { font-weight: 600; color: #333; margin-bottom: 5px; display: block; }
        .form-input, .form-textarea {
            width: 100%;
            border: 1px solid #e0bebe;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.98em;
            font-family: inherit;
            box-sizing: border-box;
        }
        .form-textarea { min-height: 84px; resize: vertical; }
        .submit-btn {
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            background: #800000;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }
        .submit-btn.danger { background: #c62828; }
        .variant-card {
            border: 1px solid #f0d8d8;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 10px;
            background: #fffdfd;
        }
        .variant-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }
        .variant-title { font-weight: 700; color: #800000; }
        .variant-meta { color: #6a6a6a; font-size: 0.92em; }
        .value-list { display: flex; flex-wrap: wrap; gap: 8px; margin: 8px 0 10px; }
        .value-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f9eaea;
            border: 1px solid #efcccc;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 0.9em;
        }
        .chip-btn {
            border: none;
            background: transparent;
            color: #a10000;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            font-size: 0.95em;
        }
        .inline-form { display: inline; margin: 0; }
        .value-form { display: flex; gap: 8px; flex-wrap: wrap; }
        .value-form input { flex: 1; min-width: 200px; }
        .empty-text { color: #777; font-size: 0.95em; }
        @media (max-width: 720px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-grid .full { grid-column: auto; }
            .submit-btn { width: 100%; }
            .value-form input { min-width: 100%; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/top-menu.php'; ?>
<div class="admin-container">
    <h1>Product Variants</h1>
    <div class="top-links">
        <a href="index.php" class="link-btn back">&larr; Back to Product List</a>
        <a href="add.php" class="link-btn primary">+ Add Product</a>
    </div>

    <?php if ($errorMsg !== ''): ?>
        <div class="alert error"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>
    <?php if ($successMsg !== ''): ?>
        <div class="alert success"><?php echo htmlspecialchars($successMsg); ?></div>
    <?php endif; ?>

    <div class="panel">
        <h2>Add New Variant</h2>
        <form method="post">
            <input type="hidden" name="action" value="add_variant">
            <div class="form-grid">
                <div>
                    <label class="form-label" for="variant_name">Variant Name</label>
                    <input type="text" id="variant_name" name="variant_name" class="form-input" placeholder="Example: Size, Color, Language" required>
                </div>
                <div class="full">
                    <label class="form-label" for="variant_values">Variant Values (comma or new line separated)</label>
                    <textarea id="variant_values" name="variant_values" class="form-textarea" placeholder="Example: Small, Medium, Large"></textarea>
                </div>
                <div>
                    <button type="submit" class="submit-btn">Save Variant</button>
                </div>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2>Existing Variants</h2>
        <?php if (empty($variantRows)): ?>
            <div class="empty-text">No variants found yet.</div>
        <?php else: ?>
            <?php foreach ($variantRows as $variant): ?>
                <?php $variantId = (int)$variant['id']; ?>
                <div class="variant-card">
                    <div class="variant-header">
                        <div>
                            <div class="variant-title"><?php echo htmlspecialchars($variant['variant_name']); ?></div>
                            <div class="variant-meta">
                                Used in <?php echo (int)$variant['product_count']; ?> product(s),
                                <?php echo (int)$variant['value_count']; ?> value(s)
                            </div>
                        </div>
                        <form method="post" onsubmit="return confirm('Delete this variant and all its values? Products using it will be set to no variant.');">
                            <input type="hidden" name="action" value="delete_variant">
                            <input type="hidden" name="variant_id" value="<?php echo $variantId; ?>">
                            <button type="submit" class="submit-btn danger">Delete Variant</button>
                        </form>
                    </div>

                    <?php $variantValues = $valuesByVariant[$variantId] ?? []; ?>
                    <?php if (!empty($variantValues)): ?>
                        <div class="value-list">
                            <?php foreach ($variantValues as $value): ?>
                                <span class="value-chip">
                                    <?php echo htmlspecialchars($value['value_name']); ?>
                                    <form method="post" class="inline-form" onsubmit="return confirm('Delete this value?');">
                                        <input type="hidden" name="action" value="delete_variant_value">
                                        <input type="hidden" name="value_id" value="<?php echo (int)$value['id']; ?>">
                                        <button type="submit" class="chip-btn" title="Delete value">x</button>
                                    </form>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-text">No values added yet.</div>
                    <?php endif; ?>

                    <form method="post" class="value-form">
                        <input type="hidden" name="action" value="add_variant_value">
                        <input type="hidden" name="variant_id" value="<?php echo $variantId; ?>">
                        <input type="text" name="value_name" class="form-input" placeholder="Add value for <?php echo htmlspecialchars($variant['variant_name']); ?>" required>
                        <button type="submit" class="submit-btn">Add Value</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
