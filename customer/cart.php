<?php // OWNER: Member 2

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/auth.php';

require_login();

$cart = $_SESSION['cart'] ?? [];
$items = [];
$total = 0.00;

$productIds = array_values(array_filter(
    array_map('intval', array_keys($cart)),
    fn($id) => $id > 0
));

if (!empty($productIds)) {

    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    $stmt = $pdo->prepare("
        SELECT
            product_id,
            title,
            price,
            stock,
            status,
            image_path
        FROM products
        WHERE product_id IN ($placeholders)
    ");

    $stmt->execute($productIds);

    $products = [];

    foreach ($stmt->fetchAll() as $product) {
        $products[(int)$product['product_id']] = $product;
    }

    foreach ($productIds as $productId) {

        $quantity = (int)($cart[$productId] ?? 0);

        if ($quantity <= 0 || !isset($products[$productId])) {
            continue;
        }

        $product = $products[$productId];

        if ($product['status'] !== 'active') {
            continue;
        }

        $stock = (int)$product['stock'];

        if ($stock <= 0) {
            continue;
        }

        if ($quantity > $stock) {
            $quantity = $stock;
            $_SESSION['cart'][$productId] = $stock;
        }

        $price = (float)$product['price'];
        $subtotal = $price * $quantity;

        $items[] = [
            'product_id' => $productId,
            'title' => $product['title'],
            'price' => $price,
            'quantity' => $quantity,
            'stock' => $stock,
            'image_path' => $product['image_path'],
            'subtotal' => $subtotal
        ];

        $total += $subtotal;
    }
}

$page_title = 'Cart';
require __DIR__ . '/../includes/header.php';
?>

<section class="cart-page">
    <h1>Your Cart</h1>

    <?php if (!$items): ?>
        <div class="cart-empty-state">
            <svg class="cart-empty-icon" viewBox="0 0 48 48" aria-hidden="true">
                <path d="M8 17h32l-3 24H11L8 17Z"></path>
                <path d="M17 18v-5a7 7 0 0 1 14 0v5"></path>
            </svg>
            <h2>Your cart is empty</h2>
            <p class="muted">Find something you love and give it a second life.</p>
            <a class="btn btn-dark" href="<?= BASE_URL ?>/customer/shop.php">Continue Shopping</a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <form class="cart-main" method="post" action="<?= BASE_URL ?>/customer/cart_update.php">
                <div class="cart-items">
                    <?php foreach ($items as $item): ?>
                        <article class="cart-item">
                            <img class="cart-item-image" src="<?= e(product_image($item['image_path'])) ?>" alt="<?= e($item['title']) ?>">
                            <div class="cart-item-details">
                                <h2><?= e($item['title']) ?></h2>
                                <p><?= format_price($item['price']) ?> each</p>
                            </div>
                            <div class="cart-item-quantity">
                                <label for="quantity-<?= (int)$item['product_id'] ?>">Quantity</label>
                                <div class="cart-quantity-control">
                                    <button type="button" data-quantity-change="-1" aria-label="Decrease quantity">−</button>
                                    <input
                                        id="quantity-<?= (int)$item['product_id'] ?>"
                                        type="number"
                                        name="quantity[<?= (int)$item['product_id'] ?>]"
                                        value="<?= (int)$item['quantity'] ?>"
                                        min="1"
                                        max="<?= (int)$item['stock'] ?>"
                                    >
                                    <button type="button" data-quantity-change="1" aria-label="Increase quantity">+</button>
                                </div>
                            </div>
                            <strong class="cart-item-subtotal"><?= format_price($item['subtotal']) ?></strong>
                            <button
                                class="cart-remove"
                                type="submit"
                                formaction="<?= BASE_URL ?>/customer/cart_remove.php"
                                name="product_id"
                                value="<?= (int)$item['product_id'] ?>"
                                formmethod="post"
                            >
                                Remove
                            </button>
                        </article>
                    <?php endforeach; ?>
                </div>

                <button class="btn cart-update" type="submit">Update Cart</button>
            </form>

            <aside class="cart-summary">
                <h2>Order Summary</h2>
                <div class="cart-summary-items">
                    <?php foreach ($items as $item): ?>
                        <div class="cart-summary-item">
                            <span><?= e($item['title']) ?> <span class="muted">× <?= (int)$item['quantity'] ?></span></span>
                            <strong><?= format_price($item['subtotal']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="cart-summary-total">
                    <span>Total</span>
                    <strong><?= format_price($total) ?></strong>
                </div>
                <a class="btn btn-dark cart-checkout" href="<?= BASE_URL ?>/customer/checkout.php">Proceed to Checkout</a>
                <a class="cart-continue" href="<?= BASE_URL ?>/customer/shop.php">Continue Shopping</a>
            </aside>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>