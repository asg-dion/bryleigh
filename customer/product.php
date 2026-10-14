<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

$product = null;
if ($id) {
    // Fetch regardless of status: archived products get their own "no longer available" page.
    $stmt = $pdo->prepare(
        "SELECT p.*, c.category_name
           FROM products p
           JOIN categories c ON c.category_id = p.category_id
          WHERE p.product_id = ?
          LIMIT 1"
    );
    $stmt->execute([$id]);
    $product = $stmt->fetch() ?: null;
}

$loadSuggestions = static function (?int $categoryId) use ($pdo): array {
    $suggestions = [];
    $selectedIds = [];

    $fetchProducts = static function (?int $preferredCategory, array $excludeIds, bool $inStockOnly) use ($pdo): array {
        $conditions = ["p.status = 'active'"];
        $params = [];

        if ($preferredCategory !== null) {
            $conditions[] = 'p.category_id = ?';
            $params[] = $preferredCategory;
        }
        if ($inStockOnly) {
            $conditions[] = 'p.stock > 0';
        }
        if ($excludeIds) {
            $conditions[] = 'p.product_id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')';
            array_push($params, ...$excludeIds);
        }

        $stmt = $pdo->prepare(
            "SELECT p.product_id, p.title, p.price, p.item_condition, p.stock, p.image_path, c.category_name
               FROM products p
               JOIN categories c ON c.category_id = p.category_id
              WHERE " . implode(' AND ', $conditions) . "
              ORDER BY p.created_at DESC, p.product_id DESC
              LIMIT 4"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    foreach ([
        [$categoryId, true],
        [null, true],
        [null, false],
    ] as [$preferredCategory, $inStockOnly]) {
        if (count($suggestions) >= 4) {
            break;
        }

        foreach ($fetchProducts($preferredCategory, $selectedIds, $inStockOnly) as $candidate) {
            $suggestions[] = $candidate;
            $selectedIds[] = (int)$candidate['product_id'];
            if (count($suggestions) >= 4) {
                break;
            }
        }
    }

    return $suggestions;
};

if (!$product) {
    http_response_code(404);
    $suggest = $loadSuggestions(null);
    $page_title = 'Product not found';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="product-page">
        <div class="empty-state">
            <h1>Product not found</h1>
            <p class="muted">This item may have been sold or removed.</p>
            <a class="btn btn-dark" href="<?= BASE_URL ?>/customer/shop.php">Back to products</a>
        </div>
        <?php if ($suggest): ?>
            <section class="related-products">
                <h2 class="section-title">You might also like</h2>
                <div class="product-grid">
                    <?php foreach ($suggest as $p): $soldOut = (int)$p['stock'] <= 0; ?>
                        <article class="product-card<?= $soldOut ? ' sold-out' : '' ?>">
                            <div class="thumb">
                                <a class="product-image-link" href="<?= BASE_URL ?>/customer/product.php?id=<?= (int)$p['product_id'] ?>">
                                    <img src="<?= e(product_image($p['image_path'])) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
                                </a>
                                <a class="quick-view" href="<?= BASE_URL ?>/customer/product.php?id=<?= (int)$p['product_id'] ?>">Quick view</a>
                                <?php if ($soldOut): ?>
                                    <span class="badge badge-out stock-badge">Sold out</span>
                                <?php elseif ((int)$p['stock'] <= 2): ?>
                                    <span class="badge badge-warn stock-badge">Only <?= (int)$p['stock'] ?> left</span>
                                <?php endif; ?>
                            </div>
                            <div class="info">
                                <span class="category-label"><?= e($p['category_name']) ?></span>
                                <span class="title"><?= e($p['title']) ?></span>
                                <span class="price"><?= e(format_price($p['price'])) ?></span>
                                <span class="condition-label"><?= e($p['item_condition']) ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
<?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// ---- Archived: the product exists but is no longer sold ----
if ($product['status'] !== 'active') {
    http_response_code(410); // Gone
    $suggest = $loadSuggestions((int)$product['category_id']);

    $page_title = 'Product no longer available';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="product-page">
        <div class="empty-state">
            <h1>Product no longer available</h1>
            <p class="muted">“<?= e($product['title']) ?>” is no longer available. It may have been sold or taken down.</p>
            <a class="btn btn-dark" href="<?= BASE_URL ?>/customer/shop.php">Back to products</a>
        </div>
        <?php if ($suggest): ?>
        <section class="related-products">
            <h2 class="section-title">You might also like</h2>
            <div class="product-grid">
            <?php foreach ($suggest as $p): $soldOut = (int)$p['stock'] <= 0; ?>
                <article class="product-card<?= $soldOut ? ' sold-out' : '' ?>">
                    <div class="thumb">
                        <a class="product-image-link" href="<?= BASE_URL ?>/customer/product.php?id=<?= (int)$p['product_id'] ?>">
                            <img src="<?= e(product_image($p['image_path'])) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
                        </a>
                        <a class="quick-view" href="<?= BASE_URL ?>/customer/product.php?id=<?= (int)$p['product_id'] ?>">Quick view</a>
                        <?php if ($soldOut): ?>
                            <span class="badge badge-out stock-badge">Sold out</span>
                        <?php elseif ((int)$p['stock'] <= 2): ?>
                            <span class="badge badge-warn stock-badge">Only <?= (int)$p['stock'] ?> left</span>
                        <?php endif; ?>
                    </div>
                    <div class="info">
                        <span class="category-label"><?= e($p['category_name']) ?></span>
                        <span class="title"><?= e($p['title']) ?></span>
                        <span class="price"><?= e(format_price($p['price'])) ?></span>
                        <span class="condition-label"><?= e($p['item_condition']) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        </section>
        </div>
        <?php endif; ?>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$stock   = (int)$product['stock'];
$inCart  = (int)($_SESSION['cart'][$product['product_id']] ?? 0);

// A few more items from the same category.
$related = $pdo->prepare(
    "SELECT p.product_id, p.title, p.price, p.item_condition, p.stock, p.image_path, c.category_name
       FROM products p
       JOIN categories c ON c.category_id = p.category_id
      WHERE p.status = 'active' AND p.category_id = ? AND p.product_id <> ?
      ORDER BY p.created_at DESC
      LIMIT 4"
);
$related->execute([$product['category_id'], $product['product_id']]);
$related = $related->fetchAll();

$page_title = $product['title'];
require __DIR__ . '/../includes/header.php';
?>
<div class="product-page container">
<nav class="breadcrumbs">
    <a href="<?= BASE_URL ?>/index.php">Home</a>
    <span class="breadcrumb-separator" aria-hidden="true">/</span>
    <a href="<?= BASE_URL ?>/customer/shop.php">Products</a>
    <span class="breadcrumb-separator" aria-hidden="true">/</span>
    <a href="<?= BASE_URL ?>/customer/shop.php?category=<?= (int)$product['category_id'] ?>"><?= e($product['category_name']) ?></a>
    <span class="breadcrumb-separator" aria-hidden="true">/</span>
    <?= e($product['title']) ?>
</nav>

<section class="product-detail">
    <div class="product-gallery">
        <a class="back-to-products" href="<?= BASE_URL ?>/customer/shop.php">← Back to Products</a>
        <div class="photo">
            <img src="<?= e(product_image($product['image_path'])) ?>" alt="<?= e($product['title']) ?>">
        </div>
    </div>
    <div class="product-info">
        <div class="facts detail-tags">
            <span class="badge"><?= e($product['category_name']) ?></span>
            <span class="badge"><?= e($product['item_condition']) ?></span>
        </div>
        <h1><?= e($product['title']) ?></h1>
        <div class="price"><?= e(format_price($product['price'])) ?></div>

        <?php if (trim((string)$product['description']) !== ''): ?>
            <div class="description"><?= e($product['description']) ?></div>
        <?php endif; ?>

        <div class="product-facts">
            <div><span>Category</span><strong><?= e($product['category_name']) ?></strong></div>
            <div><span>Condition</span><strong><?= e($product['item_condition']) ?></strong></div>
            <div>
                <span>Available Stock</span>
                <strong>
                    <?php if ($stock <= 0): ?>
                        <span class="badge badge-out">Sold out</span>
                    <?php elseif ($stock <= 2): ?>
                        <span class="badge badge-warn">Only <?= $stock ?> left</span>
                    <?php else: ?>
                        <span class="badge badge-ok">In stock (<?= $stock ?>)</span>
                    <?php endif; ?>
                </strong>
            </div>
        </div>

        <?php if ($stock > 0): ?>
            <form class="add-to-cart" method="post" action="<?= BASE_URL ?>/customer/cart_add.php">
                <input type="hidden" name="product_id" value="<?= (int)$product['product_id'] ?>">
                <div class="qty">
                    <label for="qty">Quantity</label>
                    <div class="quantity-control">
                        <button class="quantity-step" type="button" data-quantity-change="-1" aria-label="Decrease quantity">−</button>
                        <input id="qty" type="number" name="quantity" value="1" min="1" max="<?= $stock ?>" required>
                        <button class="quantity-step" type="button" data-quantity-change="1" aria-label="Increase quantity">+</button>
                    </div>
                </div>
                <button class="btn btn-dark" type="submit">Add to cart</button>
            </form>
            <?php if ($inCart > 0): ?>
                <p class="muted">You already have <?= $inCart ?> of this item in your cart.</p>
            <?php endif; ?>
        <?php else: ?>
            <button class="btn" type="button" disabled>Sold out</button>
        <?php endif; ?>
    </div>
</section>

<?php if ($related): ?>
    <section class="related-products">
    <h2 class="section-title">More from <?= e($product['category_name']) ?></h2>
    <div class="product-grid">
        <?php foreach ($related as $p): $soldOut = (int)$p['stock'] <= 0; ?>
            <article class="product-card<?= $soldOut ? ' sold-out' : '' ?>">
                <div class="thumb">
                    <a class="product-image-link" href="<?= BASE_URL ?>/customer/product.php?id=<?= (int)$p['product_id'] ?>">
                        <img src="<?= e(product_image($p['image_path'])) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
                    </a>
                    <a class="quick-view" href="<?= BASE_URL ?>/customer/product.php?id=<?= (int)$p['product_id'] ?>">Quick view</a>
                    <?php if ($soldOut): ?><span class="badge badge-out stock-badge">Sold out</span>
                    <?php elseif ((int)$p['stock'] <= 2): ?><span class="badge badge-warn stock-badge">Only <?= (int)$p['stock'] ?> left</span><?php endif; ?>
                </div>
                <div class="info">
                    <span class="category-label"><?= e($p['category_name']) ?></span>
                    <span class="title"><?= e($p['title']) ?></span>
                    <span class="price"><?= e(format_price($p['price'])) ?></span>
                    <span class="condition-label"><?= e($p['item_condition']) ?></span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    </section>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php';
