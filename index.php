<?php // OWNER: Member 1
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/category-icons.php';

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
<div class="home-page">
    <section class="hero">
        <div class="hero-content">
            <p class="hero-tagline">Secondhand. Fresh point of view.</p>
            <h1>Give Fashion a Second Life</h1>
            <p class="hero-description">Discover one-of-a-kind preloved pieces, thoughtfully chosen and ready for their next chapter.</p>
            <a class="btn" href="<?= BASE_URL ?>/customer/shop.php">Shop Now</a>
        </div>
        <div class="hero-image-placeholder">
            <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Bryleigh - preloved clothing">
        </div>
    </section>

    <?php if ($categories): ?>
        <section class="home-section category-section">
            <h2 class="section-title">Shop by Category</h2>
            <div class="category-links">
                <?php foreach ($categories as $cat): ?>
                    <a href="<?= BASE_URL ?>/customer/shop.php?category=<?= (int)$cat['category_id'] ?>">
                        <span class="category-icon" aria-hidden="true">
                            <?= category_icon(strtolower($cat['category_name'])) ?>
                        </span>
                        <span class="category-name"><?= e($cat['category_name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="home-section arrivals-section">
        <div class="arrivals-heading">
            <h2 class="section-title">New Arrivals</h2>
            <a href="<?= BASE_URL ?>/customer/shop.php">View all <span aria-hidden="true">→</span></a>
        </div>
        <?php if (!$featured): ?>
            <div class="empty-state"><p>No products yet. Please check back soon.</p></div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($featured as $p): $soldOut = (int)$p['stock'] <= 0; ?>
                    <article class="product-card<?= $soldOut ? ' sold-out' : '' ?>">
                        <div class="thumb">
                            <a class="product-image-link" href="<?= BASE_URL ?>/customer/product.php?id=<?= (int)$p['product_id'] ?>">
                                <img src="<?= e(product_image($p['image_path'])) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
                            </a>
                            <a class="quick-view" href="<?= BASE_URL ?>/customer/product.php?id=<?= (int)$p['product_id'] ?>">Quick view</a>
                            <?php if ($soldOut): ?><span class="badge badge-out stock-badge">Sold out</span><?php endif; ?>
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
        <?php endif; ?>
    </section>

</div>
<?php require __DIR__ . '/includes/footer.php';
