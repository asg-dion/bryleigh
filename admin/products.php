<?php // OWNER: Member 1
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// CSRF token shared with product_archive.php (same session key).
$_SESSION['catalog_csrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['catalog_csrf'];

$statusFilter = in_array($_GET['status'] ?? '', ['active', 'archived', 'all'], true) ? $_GET['status'] : 'active';
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);

$where = []; $params = [];
if ($statusFilter !== 'all') { $where[] = 'p.status = ?'; $params[] = $statusFilter; }
if ($q !== '') {
    $where[]  = '(p.title LIKE ? OR c.category_name LIKE ?)';
    $like     = '%' . addcslashes($q, '\\%_') . '%';
    $params[] = $like; $params[] = $like;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT p.product_id, p.title, p.price, p.item_condition, p.stock, p.image_path, p.status, c.category_name
       FROM products p
       JOIN categories c ON c.category_id = p.category_id
       $whereSql
      ORDER BY p.created_at DESC, p.product_id DESC"
);
$stmt->execute($params);
$products = $stmt->fetchAll();

$tabUrl = static fn(string $s): string => BASE_URL . '/admin/products.php?' . http_build_query(array_filter(['status' => $s, 'q' => $q]));

$page_title = 'Products';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="page-head">
    <h1>Product Management</h1>
    <a class="btn btn-dark" href="<?= BASE_URL ?>/admin/product_form.php">+ Add product</a>
</div>

<div class="toolbar">
    <div class="tabs">
        <?php foreach (['active' => 'Active', 'archived' => 'Archived', 'all' => 'All'] as $key => $label): ?>
            <a href="<?= e($tabUrl($key)) ?>" class="<?= $statusFilter === $key ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <form method="get">
        <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search title or category">
        <button class="btn btn-dark btn-sm" type="submit">Search</button>
    </form>
</div>

<div class="table-wrap">
<table class="table">
    <thead>
        <tr><th></th><th>Title</th><th>Category</th><th>Price</th><th>Condition</th><th>Stock</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php if (!$products): ?>
        <tr><td colspan="8" class="muted" style="text-align:center;padding:2rem">No products found.</td></tr>
    <?php endif; ?>
    <?php foreach ($products as $p): $stock = (int)$p['stock']; ?>
        <tr>
            <td><img class="admin-thumb" src="<?= e(product_image($p['image_path'])) ?>" alt=""></td>
            <td><strong><?= e($p['title']) ?></strong></td>
            <td><?= e($p['category_name']) ?></td>
            <td><?= e(format_price($p['price'])) ?></td>
            <td><?= e($p['item_condition']) ?></td>
            <td><span class="badge <?= $stock === 0 ? 'badge-out' : ($stock <= 2 ? 'badge-warn' : '') ?>"><?= $stock ?></span></td>
            <td><span class="badge <?= $p['status'] === 'active' ? 'badge-ok' : 'badge-archived' ?>"><?= e(ucfirst($p['status'])) ?></span></td>
            <td>
                <div class="row-actions">
                    <a class="btn btn-sm" href="<?= BASE_URL ?>/admin/product_form.php?id=<?= (int)$p['product_id'] ?>">Edit</a>
                    <?php if ($p['status'] === 'active'): ?>
                        <form method="post" action="<?= BASE_URL ?>/admin/product_archive.php"
                              onsubmit="return confirm('Archive “<?= e(addslashes($p['title'])) ?>”? It will no longer appear in the shop.');">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="product_id" value="<?= (int)$p['product_id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Archive</button>
                        </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php';
