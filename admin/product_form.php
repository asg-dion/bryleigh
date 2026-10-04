<?php // OWNER: Member 1
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

const IMG_MAX_BYTES = 2 * 1024 * 1024; // 2 MB
const IMG_TYPES     = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$conditions = ['Like New', 'Good', 'Fair'];

$_SESSION['catalog_csrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['catalog_csrf'];

// ---- Edit mode? ----
$editId  = filter_var($_GET['id'] ?? $_POST['product_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$existing = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
    $stmt->execute([$editId]);
    $existing = $stmt->fetch() ?: null;
    if (!$existing) {
        flash_set('error', 'Product not found.');
        redirect('admin/products.php');
    }
}

$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();
$validCategoryIds = array_map('intval', array_column($categories, 'category_id'));

// Form values (defaults, existing product, or re-displayed POST).
$form = [
    'title'          => $existing['title']          ?? '',
    'category_id'    => $existing['category_id']    ?? '',
    'description'    => $existing['description']    ?? '',
    'price'          => $existing['price']          ?? '',
    'item_condition' => $existing['item_condition'] ?? 'Good',
    'stock'          => $existing['stock']          ?? '1',
    'status'         => $existing['status']         ?? 'active',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        flash_set('error', 'Your session expired. Please try again.');
        redirect('admin/products.php');
    }

    foreach ($form as $k => $_) { $form[$k] = trim((string)($_POST[$k] ?? '')); }

    // --- Validate ---
    if ($form['title'] === '')                                { $errors['title'] = 'Title is required.'; }
    elseif (mb_strlen($form['title']) > 150)                  { $errors['title'] = 'Title must be 150 characters or fewer.'; }

    if (!in_array((int)$form['category_id'], $validCategoryIds, true)) { $errors['category_id'] = 'Choose a category.'; }

    if ($form['price'] === '' || !is_numeric($form['price']))  { $errors['price'] = 'Enter a valid price.'; }
    elseif ((float)$form['price'] <= 0)                        { $errors['price'] = 'Price must be greater than zero.'; }
    elseif ((float)$form['price'] > 99999999.99)               { $errors['price'] = 'Price is too large.'; }

    if (!in_array($form['item_condition'], $conditions, true)) { $errors['item_condition'] = 'Choose a condition.'; }

    if (filter_var($form['stock'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]) === false) {
        $errors['stock'] = 'Stock must be a whole number, 0 or more.';
    }

    if (!in_array($form['status'], ['active', 'archived'], true)) { $form['status'] = 'active'; }
    if (mb_strlen($form['description']) > 5000)                { $errors['description'] = 'Description is too long (5000 characters max).'; }

    // --- Image upload (optional) ---
    $newImage = null;
    $file = $_FILES['image'] ?? null;
    if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['image'] = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Image is too large (2 MB max).' : 'Image upload failed. Please try again.';
        } elseif ($file['size'] > IMG_MAX_BYTES) {
            $errors['image'] = 'Image is too large (2 MB max).';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!isset(IMG_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
                $errors['image'] = 'Image must be a JPG, PNG, or WebP file.';
            } else {
                $newImage = ['tmp' => $file['tmp_name'], 'ext' => IMG_TYPES[$mime]];
            }
        }
    }

    if (!$errors) {
        $imagePath = $existing['image_path'] ?? null;
        $oldImage  = $imagePath;

        if ($newImage) {
            $dir = __DIR__ . '/../uploads/products';
            if (!is_dir($dir)) { mkdir($dir, 0755, true); }
            $filename = bin2hex(random_bytes(12)) . '.' . $newImage['ext'];
            if (move_uploaded_file($newImage['tmp'], $dir . '/' . $filename)) {
                $imagePath = $filename;
            } else {
                $errors['image'] = 'Could not save the image. Check that uploads/products is writable.';
            }
        } elseif ($existing && !empty($_POST['remove_image'])) {
            $imagePath = null;
        }
    }

    if (!$errors) {
        $data = [
            (int)$form['category_id'], $form['title'], $form['description'] !== '' ? $form['description'] : null,
            number_format((float)$form['price'], 2, '.', ''), $form['item_condition'], (int)$form['stock'],
            $imagePath, $form['status'],
        ];
        if ($existing) {
            $pdo->prepare('UPDATE products SET category_id=?, title=?, description=?, price=?, item_condition=?, stock=?, image_path=?, status=? WHERE product_id=?')
                ->execute([...$data, $existing['product_id']]);
            flash_set('success', 'Product updated.');
        } else {
            $pdo->prepare('INSERT INTO products (category_id, title, description, price, item_condition, stock, image_path, status) VALUES (?,?,?,?,?,?,?,?)')
                ->execute($data);
            flash_set('success', 'Product added.');
        }

        // Remove the replaced / removed image file from disk.
        if ($oldImage && $oldImage !== $imagePath) {
            $oldFile = realpath(__DIR__ . '/../uploads/products/' . basename($oldImage));
            if ($oldFile && is_file($oldFile)) { @unlink($oldFile); }
        }
        redirect('admin/products.php');
    }
}

$page_title = $existing ? 'Edit Product' : 'Add Product';
require __DIR__ . '/../includes/admin_header.php';
$err = fn(string $k): string => isset($errors[$k]) ? '<div class="field-error">' . e($errors[$k]) . '</div>' : '';
?>
<div class="page-head">
    <h1><?= $existing ? 'Edit Product' : 'Add Product' ?></h1>
    <a class="btn" href="<?= BASE_URL ?>/admin/products.php">&larr; Back to products</a>
</div>

<?php if ($errors): ?><div class="error">Please fix the highlighted fields below.</div><?php endif; ?>

<form class="card product-form" method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <?php if ($existing): ?><input type="hidden" name="product_id" value="<?= (int)$existing['product_id'] ?>"><?php endif; ?>

    <div class="form-group">
        <label>Title
            <input type="text" name="title" maxlength="150" value="<?= e($form['title']) ?>" required>
        </label>
        <?= $err('title') ?>
    </div>

    <div class="row">
        <div class="form-group">
            <label>Category
                <select name="category_id" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['category_id'] ?>" <?= (string)$form['category_id'] === (string)$c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?= $err('category_id') ?>
        </div>
        <div class="form-group">
            <label>Condition
                <select name="item_condition" required>
                    <?php foreach ($conditions as $c): ?>
                        <option value="<?= e($c) ?>" <?= $form['item_condition'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?= $err('item_condition') ?>
        </div>
    </div>

    <div class="row">
        <div class="form-group">
            <label>Price (₱)
                <input type="number" name="price" step="0.01" min="0.01" value="<?= e($form['price']) ?>" required>
            </label>
            <?= $err('price') ?>
        </div>
        <div class="form-group">
            <label>Stock
                <input type="number" name="stock" step="1" min="0" value="<?= e($form['stock']) ?>" required>
            </label>
            <?= $err('stock') ?>
        </div>
    </div>

    <div class="form-group">
        <label>Description
            <textarea name="description" maxlength="5000"><?= e($form['description']) ?></textarea>
        </label>
        <?= $err('description') ?>
    </div>

    <div class="form-group">
        <label>Product image <span class="muted" style="font-weight:normal">(JPG, PNG or WebP, up to 2 MB)</span>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
        </label>
        <?= $err('image') ?>
        <?php if ($existing && $existing['image_path']): ?>
            <div class="current-image">
                <img src="<?= e(product_image($existing['image_path'])) ?>" alt="Current image">
                <label style="font-weight:normal"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($existing): ?>
        <div class="form-group">
            <label>Status
                <select name="status">
                    <option value="active"   <?= $form['status'] === 'active'   ? 'selected' : '' ?>>Active (visible in shop)</option>
                    <option value="archived" <?= $form['status'] === 'archived' ? 'selected' : '' ?>>Archived (hidden from shop)</option>
                </select>
            </label>
        </div>
    <?php endif; ?>

    <div class="form-actions">
        <button class="btn btn-dark" type="submit"><?= $existing ? 'Save changes' : 'Add product' ?></button>
        <a class="btn" href="<?= BASE_URL ?>/admin/products.php">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/../includes/admin_footer.php';
