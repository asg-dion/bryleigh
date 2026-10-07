<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

const SHOP_PER_PAGE = 12;
$conditions = ['Like New', 'Good', 'Fair'];
$sorts = [
    'newest'     => ['Newest first',       'p.created_at DESC, p.product_id DESC'],
    'price_asc'  => ['Price: low to high', 'p.price ASC, p.product_id ASC'],
    'price_desc' => ['Price: high to low', 'p.price DESC, p.product_id ASC'],
    'title'      => ['Name: A to Z',       'p.title ASC'],
];

// ---- Read + sanitise filters (all via GET) ----
$q        = trim((string)($_GET['q'] ?? ''));
$q        = mb_substr($q, 0, 100);
$category = filter_var($_GET['category'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$cond     = in_array($_GET['condition'] ?? '', $conditions, true) ? $_GET['condition'] : '';
$sort     = array_key_exists($_GET['sort'] ?? '', $sorts) ? $_GET['sort'] : 'newest';
$inStock  = !empty($_GET['in_stock']);
$page     = max(1, (int)($_GET['page'] ?? 1));

// ---- Build the WHERE clause with prepared-statement parameters ----
$where  = ["p.status = 'active'"];
$params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '\\%_') . '%';
    $where[]  = '(p.title LIKE ? OR p.description LIKE ?)';
    $params[] = $like;
    $params[] = $like;
}
if ($category) { $where[] = 'p.category_id = ?';   $params[] = $category; }
if ($cond !== '') { $where[] = 'p.item_condition = ?'; $params[] = $cond; }
if ($inStock) { $where[] = 'p.stock > 0'; }
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / SHOP_PER_PAGE));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * SHOP_PER_PAGE;

// LIMIT/OFFSET are cast integers, so they are safe to inline.
$stmt = $pdo->prepare(
    "SELECT p.product_id, p.title, p.price, p.item_condition, p.stock, p.image_path, c.category_name
       FROM products p
       JOIN categories c ON c.category_id = p.category_id
      WHERE $whereSql
      ORDER BY {$sorts[$sort][1]}
      LIMIT " . SHOP_PER_PAGE . " OFFSET $offset"
);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();

// Query-string helper that keeps the active filters when paging.
$baseQuery = array_filter(
    ['q' => $q, 'category' => $category ?: '', 'condition' => $cond, 'sort' => $sort !== 'newest' ? $sort : '', 'in_stock' => $inStock ? 1 : ''],
    static fn($v) => $v !== '' && $v !== null
);
$pageUrl = static fn(int $n): string => BASE_URL . '/customer/shop.php?' . http_build_query($baseQuery + ['page' => $n]);

$hasFilters = $q !== '' || $category || $cond !== '' || $inStock;

$page_title = 'Products';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Products</h1>
    <span class="muted"><?= $total ?> item<?= $total === 1 ? '' : 's' ?><?= $q !== '' ? ' for “' . e($q) . '”' : '' ?></span>
</div>

<form class="filters" method="get" action="<?= BASE_URL ?>/customer/shop.php">
    <div class="field" style="flex-basis:220px">
        <label for="f-q">Search</label>
        <input id="f-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Title or description">
    </div>
    <div class="field">
        <label for="f-cat">Category</label>
        <select id="f-cat" name="category">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['category_id'] ?>" <?= $category === (int)$c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-cond">Condition</label>
        <select id="f-cond" name="condition">
            <option value="">Any condition</option>
            <?php foreach ($conditions as $c): ?>
                <option value="<?= e($c) ?>" <?= $cond === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-sort">Sort by</label>
        <select id="f-sort" name="sort">
            <?php foreach ($sorts as $key => [$label]): ?>
                <option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field" style="flex:0 0 auto">
        <label style="display:flex;gap:.4rem;align-items:center;font-weight:normal">
            <input type="checkbox" name="in_stock" value="1" <?= $inStock ? 'checked' : '' ?> style="width:auto;margin:0"> In stock only
        </label>
    </div>
    <div class="actions">
        <button class="btn btn-dark" type="submit">Apply</button>
        <a class="btn" href="<?= BASE_URL ?>/customer/shop.php">Reset</a>
    </div>
</form>

<?php if (!$products): ?>
    <div class="empty-state">
        <?php if ($hasFilters): ?>
            <p><strong>No products match your <?= $q !== '' ? 'search' : 'filters' ?>.</strong></p>
            <p class="muted">
                <?php if ($q !== ''): ?>Nothing found for “<?= e($q) ?>”. <?php endif; ?>
                Try different keywords or clear your filters.
            </p>
            <a class="btn btn-dark" href="<?= BASE_URL ?>/customer/shop.php">Clear search &amp; filters</a>
        <?php else: ?>
            <p><strong>No products are available right now.</strong></p>
            <p class="muted">Please check back soon for new arrivals.</p>
            <a class="btn btn-dark" href="<?= BASE_URL ?>/index.php">Back to home</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="product-grid">
        <?php foreach ($products as $p): $soldOut = (int)$p['stock'] <= 0; ?>
            <article class="product-card<?= $soldOut ? ' sold-out' : '' ?>">
                <a href="<?= BASE_URL ?>/customer/product.php?id=<?= (int)$p['product_id'] ?>">
                    <div class="thumb">
                        <img src="<?= e(product_image($p['image_path'])) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
                        <?php if ($soldOut): ?>
                            <span class="badge badge-out">Sold out</span>
                        <?php elseif ((int)$p['stock'] <= 2): ?>
                            <span class="badge badge-warn">Only <?= (int)$p['stock'] ?> left</span>
                        <?php endif; ?>
                    </div>
                    <div class="info">
                        <span class="title"><?= e($p['title']) ?></span>
                        <span class="meta"><?= e($p['category_name']) ?> · <?= e($p['item_condition']) ?></span>
                        <span class="price"><?= e(format_price($p['price'])) ?></span>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Pagination">
            <?php if ($page > 1): ?><a href="<?= e($pageUrl($page - 1)) ?>">&laquo; Prev</a><?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i === $page): ?><span class="current"><?= $i ?></span>
                <?php else: ?><a href="<?= e($pageUrl($i)) ?>"><?= $i ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="<?= e($pageUrl($page + 1)) ?>">Next &raquo;</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
