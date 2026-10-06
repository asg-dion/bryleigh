<?php // OWNER: Member 1
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

$product = null;
if ($id) {
    // Archived products are never shown to customers.
    $stmt = $pdo->prepare(
        "SELECT p.*, c.category_name
           FROM products p
           JOIN categories c ON c.category_id = p.category_id
          WHERE p.product_id = ? AND p.status = 'active'
          LIMIT 1"
    );
    $stmt->execute([$id]);
    $product = $stmt->fetch() ?: null;
}

if (!$product) {
    http_response_code(404);
    $page_title = 'Product not found';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="empty-state">
        <h1>Product not found</h1>
        <p class="muted">This item may have been sold or removed.</p>
        <a class="btn btn-dark" href="<?= BASE_URL ?>/customer/shop.php">Back to products</a>
    </div>
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
<nav class="breadcrumbs">
    <a href="<?= BASE_URL ?>/index.php">Home</a> /
    <a href="<?= BASE_URL ?>/customer/shop.php">Products</a> /
    <a href="<?= BASE_URL ?>/customer/shop.php?category=<?= (int)$product['category_id'] ?>"><?= e($product['category_name']) ?></a> /
    <?= e($product['title']) ?>
</nav>

<section class="product-detail">
    <div class="photo">
        <img src="<?= e(product_image($product['image_path'])) ?>" alt="<?= e($product['title']) ?>">
    </div>
    <div>
        <h1><?= e($product['title']) ?></h1>
        <div class="facts">
            <span class="badge"><?= e($product['category_name']) ?></span>
            <span class="badge">Condition: <?= e($product['item_condition']) ?></span>
            <?php if ($stock <= 0): ?>
                <span class="badge badge-out">Sold out</span>
            <?php elseif ($stock <= 2): ?>
                <span class="badge badge-warn">Only <?= $stock ?> left</span>
            <?php else: ?>
                <span class="badge badge-ok">In stock (<?= $stock ?>)</span>
            <?php endif; ?>
        </div>
        <div class="price"><?= e(format_price($product['price'])) ?></div>

        <?php if (trim((string)$product['description']) !== ''): ?>
            <div class="description"><?= e($product['description']) ?></div>
        <?php endif; ?>

        <?php if ($stock > 0): ?>
            <form class="add-to-cart" method="post" action="<?= BASE_URL ?>/customer/cart_add.php">
                <input type="hidden" name="product_id" value="<?= (int)$product['product_id'] ?>">
                <div class="qty">
                    <label for="qty" style="font-size:.8rem;font-weight:bold">Quantity</label>
                    <input id="qty" type="number" name="quantity" value="1" min="1" max="<?= $stock ?>" required>
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
    <h2 class="section-title">More from <?= e($product['category_name']) ?></h2>
    <div class="product-grid">
        <?php foreach ($related as $p): $soldOut = (int)$p['stock'] <= 0; ?>
            <article class="product-card<?= $soldOut ? ' sold-out' : '' ?>">
                <a href="<?= BASE_URL ?>/customer/product.php?id=<?= (int)$p['product_id'] ?>">
                    <div class="thumb">
                        <img src="<?= e(product_image($p['image_path'])) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
                        <?php if ($soldOut): ?><span class="badge badge-out">Sold out</span><?php endif; ?>
                    </div>
                    <div class="info">
                        <span class="title"><?= e($p['title']) ?></span>
                        <span class="meta"><?= e($p['item_condition']) ?></span>
                        <span class="price"><?= e(format_price($p['price'])) ?></span>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php';
