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
        <div class="hero-image-placeholder" aria-hidden="true">
            <svg viewBox="0 0 160 160">
                <path d="M80 30a12 12 0 1 1 12 12"></path>
                <path d="m80 54-34 21 12 15 12-7v45h40V83l12 7 12-15-34-21"></path>
                <path d="m65 67 15 12 15-12"></path>
            </svg>
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

    <section class="home-section why-section" aria-labelledby="why-heading">
        <h2 class="section-title" id="why-heading">Why shop Bryleigh</h2>
        <div class="why-grid">
            <article class="why-card">
                <span class="why-icon" aria-hidden="true">
                    <svg viewBox="0 0 48 48"><path d="m24 7 14 6v10c0 9-5.8 15-14 18-8.2-3-14-9-14-18V13l14-6Z"></path><path d="m17 24 5 5 10-11"></path></svg>
                </span>
                <h3>Quality checked</h3>
                <p>Every piece is reviewed before it reaches you.</p>
            </article>
            <article class="why-card">
                <span class="why-icon" aria-hidden="true">
                    <svg viewBox="0 0 48 48"><path d="M12 17h24l3 24H9l3-24Z"></path><path d="M18 18v-4a6 6 0 0 1 12 0v4M17 27h14"></path></svg>
                </span>
                <h3>Affordable prices</h3>
                <p>Find pieces you love at prices that feel good.</p>
            </article>
            <article class="why-card">
                <span class="why-icon" aria-hidden="true">
                    <svg viewBox="0 0 48 48"><path d="M37 12c-12-2-24 4-25 16-.5 6 3 10 9 9 12-1 18-13 16-25Z"></path><path d="M12 39c4-9 10-15 20-21"></path></svg>
                </span>
                <h3>Sustainable fashion</h3>
                <p>Give pre-loved clothing a fresh new chapter.</p>
            </article>
            <article class="why-card">
                <span class="why-icon" aria-hidden="true">
                    <svg viewBox="0 0 48 48"><rect x="10" y="20" width="28" height="21" rx="3"></rect><path d="M16 20v-6a8 8 0 0 1 16 0v6M24 28v5"></path></svg>
                </span>
                <h3>Secure checkout</h3>
                <p>Shop with confidence from cart to checkout.</p>
            </article>
        </div>
    </section>

    <section class="home-section promo-grid" aria-label="Explore Bryleigh">
        <article class="promo-card promo-card-shop">
            <div>
                <h2>Good style, better value</h2>
                <p>Discover one-of-a-kind finds and make them yours.</p>
            </div>
            <a class="btn" href="<?= BASE_URL ?>/customer/shop.php">Explore the shop</a>
        </article>
        <article class="promo-card promo-card-sustainable">
            <div>
                <h2>Wear it well. Wear it again.</h2>
                <p>Choose pre-loved fashion and make every piece count.</p>
            </div>
            <a class="btn" href="<?= BASE_URL ?>/customer/shop.php">Shop sustainably</a>
        </article>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php';
