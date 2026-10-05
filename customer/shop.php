<?php // TEMPORARY TEST VERSION

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/auth.php';

$page_title = 'Products';

$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT
            product_id,
            title,
            description,
            price,
            item_condition,
            stock,
            image_path
        FROM products
        WHERE status = 'active'
          AND (
              title LIKE ?
              OR description LIKE ?
          )
        ORDER BY product_id ASC
    ");

    $term = '%' . $search . '%';
    $stmt->execute([$term, $term]);
} else {
    $stmt = $pdo->query("
        SELECT
            product_id,
            title,
            description,
            price,
            item_condition,
            stock,
            image_path
        FROM products
        WHERE status = 'active'
        ORDER BY product_id ASC
    ");
}

$products = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<h1>Products</h1>

<p>Temporary product page for testing the cart and order system.</p>

<?php if (!$products): ?>

    <p>No products found.</p>

<?php else: ?>

    <div class="product-grid">

        <?php foreach ($products as $product): ?>

            <div class="product-card">

                <div class="product-image">
                    <img
                        src="<?= e(product_image($product['image_path'])) ?>"
                        alt="<?= e($product['title']) ?>"
                    >
                </div>

                <h2><?= e($product['title']) ?></h2>

                <p>
                    <?= e($product['description'] ?? '') ?>
                </p>

                <p>
                    <strong>
                        <?= format_price($product['price']) ?>
                    </strong>
                </p>

                <p>
                    Condition:
                    <?= e($product['item_condition']) ?>
                </p>

                <p>
                    Stock:
                    <?= (int)$product['stock'] ?>
                </p>

                <?php if ((int)$product['stock'] > 0): ?>

                    <?php if (is_logged_in()): ?>

                        <form
                            method="post"
                            action="<?= BASE_URL ?>/customer/cart_add.php"
                        >

                            <input
                                type="hidden"
                                name="product_id"
                                value="<?= (int)$product['product_id'] ?>"
                            >

                            <label>
                                Quantity:
                                <input
                                    type="number"
                                    name="quantity"
                                    value="1"
                                    min="1"
                                    max="<?= (int)$product['stock'] ?>"
                                >
                            </label>

                            <button class="btn btn-dark" type="submit">
                                Add to Cart
                            </button>

                        </form>

                    <?php else: ?>

                        <a
                            class="btn btn-dark"
                            href="<?= BASE_URL ?>/customer/login.php"
                        >
                            Login to Buy
                        </a>

                    <?php endif; ?>

                <?php else: ?>

                    <p><strong>Out of Stock</strong></p>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>