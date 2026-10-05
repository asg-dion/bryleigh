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

<h1>Your Cart</h1>

<?php if (!$items): ?>

    <p>Your cart is empty.</p>

    <a
        class="btn btn-dark"
        href="<?= BASE_URL ?>/customer/shop.php"
    >
        Continue Shopping
    </a>

<?php else: ?>

    <form method="post" action="<?= BASE_URL ?>/customer/cart_update.php">

        <div class="cart-items">

            <?php foreach ($items as $item): ?>

                <div class="cart-item">

                    <div>
                        <h2><?= e($item['title']) ?></h2>

                        <p>
                            <?= format_price($item['price']) ?> each
                        </p>
                    </div>

                    <div>

                        <label>
                            Quantity

                            <input
                                type="number"
                                name="quantity[<?= (int)$item['product_id'] ?>]"
                                value="<?= (int)$item['quantity'] ?>"
                                min="1"
                                max="<?= (int)$item['stock'] ?>"
                            >
                        </label>

                    </div>

                    <strong>
                        <?= format_price($item['subtotal']) ?>
                    </strong>

                    <button
                        type="submit"
                        formaction="<?= BASE_URL ?>/customer/cart_remove.php"
                        name="product_id"
                        value="<?= (int)$item['product_id'] ?>"
                        formmethod="post"
                    >
                        Remove
                    </button>

                </div>

            <?php endforeach; ?>

        </div>

        <button class="btn btn-dark" type="submit">
            Update Cart
        </button>

    </form>

    <div class="cart-total">

        <h2>
            Total: <?= format_price($total) ?>
        </h2>

        <a
            class="btn btn-dark"
            href="<?= BASE_URL ?>/customer/checkout.php"
        >
            Proceed to Checkout
        </a>

    </div>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>