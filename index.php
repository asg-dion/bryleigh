<?php // OWNER: Member 1
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

// Newest in-stock-or-not active products for the "New arrivals" strip.
$featured = $pdo->query(
    "SELECT p.product_id, p.title, p.price, p.item_condition, p.stock, p.image_path, c.category_name
       FROM products p
       JOIN categories c ON c.category_id = p.category_id
      WHERE p.status = 'active'
      ORDER BY p.created_at DESC, p.product_id DESC
      LIMIT 8"
)->fetchAll();

$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();

$page_title = 'Home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <h1>Welcome to Bryleigh</h1>
    <p>Secondhand clothing with a fresh point of view. Every piece is preloved, one of a kind, and ready for a second life.</p>
    <a class="btn" href="<?= BASE_URL ?>/customer/shop.php">Shop all products</a>
</section>

<?php if ($categories): ?>
    <h2 class="section-title">Shop by category</h2>
    <div class="category-links">
        <?php foreach ($categories as $cat): ?>
            <a href="<?= BASE_URL ?>/customer/shop.php?category=<?= (int)$cat['category_id'] ?>"><?= e($cat['category_name']) ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2 class="section-title">New arrivals</h2>
<?php if (!$featured): ?>
    <div class="empty-state"><p>No products yet. Please check back soon.</p></div>
<?php else: ?>
    <div class="product-grid">
        <?php foreach ($featured as $p): $soldOut = (int)$p['stock'] <= 0; ?>
            <article class="product-card<?= $soldOut ? ' sold-out' : '' ?>">
                <a href="<?= BASE_URL ?>/customer/product.php?id=<?= (int)$p['product_id'] ?>">
                    <div class="thumb">
                        <img src="<?= e(product_image($p['image_path'])) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
                        <?php if ($soldOut): ?><span class="badge badge-out">Sold out</span><?php endif; ?>
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
    <p style="text-align:center;margin-top:1.5rem"><a class="btn" href="<?= BASE_URL ?>/customer/shop.php">View all products</a></p>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php';
