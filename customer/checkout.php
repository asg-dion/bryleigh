<?php // OWNER: Member 2

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/auth.php';

require_login();

$cart = $_SESSION['cart'] ?? [];
$errors = [];
$items = [];
$total = 0.00;

/*
 * Load the current cart from the database.
 * This makes sure the prices and stock are current.
 */
if (!empty($cart)) {
    $productIds = array_values(array_filter(
        array_map('intval', array_keys($cart)),
        fn($id) => $id > 0
    ));

    if (!empty($productIds)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));

        $stmt = $pdo->prepare("
            SELECT product_id, title, price, stock, status
            FROM products
            WHERE product_id IN ($placeholders)
              AND status = 'active'
        ");
        $stmt->execute($productIds);

        $products = [];
        foreach ($stmt->fetchAll() as $product) {
            $products[(int)$product['product_id']] = $product;
        }

        foreach ($productIds as $productId) {
            $quantity = (int)($cart[$productId] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            if (!isset($products[$productId])) {
                $errors[] = 'One of the products in your cart is no longer available.';
                continue;
            }

            $product = $products[$productId];

            if ((int)$product['stock'] < $quantity) {
                $errors[] = $product['title'] . ' does not have enough stock. Available: ' . $product['stock'] . '.';
                continue;
            }

            $subtotal = (float)$product['price'] * $quantity;
            $total += $subtotal;

            $items[] = [
                'product_id' => $productId,
                'title' => $product['title'],
                'price' => (float)$product['price'],
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                'stock' => (int)$product['stock']
            ];
        }
    }
}

if (empty($items) && empty($errors)) {
    $errors[] = 'Your cart is empty.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $shipName = trim($_POST['ship_name'] ?? '');
    $shipPhone = trim($_POST['ship_phone'] ?? '');
    $shipAddress = trim($_POST['ship_address'] ?? '');
    $shipCity = trim($_POST['ship_city'] ?? '');
    $shipProvince = trim($_POST['ship_province'] ?? '');
    $shipZip = trim($_POST['ship_zip'] ?? '');
    $shipRegion = trim($_POST['ship_region'] ?? '');

    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $paymentReference = trim($_POST['payment_reference'] ?? '');
    $deliveryOption = trim($_POST['delivery_option'] ?? '');

    if ($shipName === '') $errors[] = 'Please enter your name.';
    if ($shipPhone === '') $errors[] = 'Please enter your phone number.';
    if ($shipAddress === '') $errors[] = 'Please enter your address.';
    if ($shipCity === '') $errors[] = 'Please enter your city.';
    if ($shipProvince === '') $errors[] = 'Please enter your province.';
    if ($shipZip === '') $errors[] = 'Please enter your ZIP code.';
    if ($shipRegion === '') $errors[] = 'Please enter your region.';

    if (!in_array($paymentMethod, ['GCash', 'Cash on Delivery'], true)) {
        $errors[] = 'Please select a valid payment method.';
    }

    if (!in_array($deliveryOption, ['Standard', 'Express', 'Pick-up'], true)) {
        $errors[] = 'Please select a valid delivery option.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            /*
             * Lock the products while checking stock so another
             * checkout cannot purchase the same stock simultaneously.
             */
            $lockedItems = [];

            foreach ($items as $item) {
                $stmt = $pdo->prepare("
                    SELECT product_id, title, price, stock, status
                    FROM products
                    WHERE product_id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$item['product_id']]);
                $product = $stmt->fetch();

                if (!$product || $product['status'] !== 'active') {
                    throw new RuntimeException(
                        $item['title'] . ' is no longer available.'
                    );
                }

                if ((int)$product['stock'] < $item['quantity']) {
                    throw new RuntimeException(
                        'Not enough stock for ' . $product['title'] .
                        '. Available: ' . $product['stock'] . '.'
                    );
                }

                $lockedItems[] = [
                    'product_id' => (int)$product['product_id'],
                    'title' => $product['title'],
                    'price' => (float)$product['price'],
                    'quantity' => $item['quantity']
                ];
            }

            /*
             * Recalculate the total using the locked database prices.
             */
            $finalTotal = 0.00;

            foreach ($lockedItems as &$item) {
                $item['subtotal'] = $item['price'] * $item['quantity'];
                $finalTotal += $item['subtotal'];
            }
            unset($item);

            /*
             * Create the order.
             */
            $orderStmt = $pdo->prepare("
                INSERT INTO orders (
                    user_id,
                    total_amount,
                    payment_method,
                    payment_reference,
                    delivery_option,
                    payment_status,
                    shipping_status,
                    order_status,
                    ship_name,
                    ship_phone,
                    ship_address,
                    ship_city,
                    ship_province,
                    ship_zip,
                    ship_region
                )
                VALUES (?, ?, ?, ?, ?, 'pending', 'not_shipped', 'pending',
                        ?, ?, ?, ?, ?, ?, ?)
            ");

            $orderStmt->execute([
                current_user_id(),
                $finalTotal,
                $paymentMethod,
                $paymentReference !== '' ? $paymentReference : null,
                $deliveryOption,
                $shipName,
                $shipPhone,
                $shipAddress,
                $shipCity,
                $shipProvince,
                $shipZip,
                $shipRegion
            ]);

            $orderId = (int)$pdo->lastInsertId();

            /*
             * Create order items and reduce product stock.
             */
            $itemStmt = $pdo->prepare("
                INSERT INTO order_items (
                    order_id,
                    product_id,
                    quantity,
                    price_at_sale
                )
                VALUES (?, ?, ?, ?)
            ");

            $stockStmt = $pdo->prepare("
                UPDATE products
                SET stock = stock - ?
                WHERE product_id = ?
            ");

            foreach ($lockedItems as $item) {
                $itemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price']
                ]);

                $stockStmt->execute([
                    $item['quantity'],
                    $item['product_id']
                ]);
            }

            /*
             * Empty the customer's cart after successful checkout.
             */
            $_SESSION['cart'] = [];

            $pdo->commit();

            flash_set('success', 'Order #' . $orderId . ' has been placed successfully.');
            redirect('customer/order_history.php');

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = 'Unable to place your order. ' . $e->getMessage();
        }
    }
}

$page_title = 'Checkout';
require __DIR__ . '/../includes/header.php';
?>

<h1>Checkout</h1>

<?php if ($errors): ?>
    <div class="flash error">
        <?php foreach ($errors as $error): ?>
            <p><?= e($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($items): ?>

    <section class="order-summary">
        <h2>Order Summary</h2>

        <?php foreach ($items as $item): ?>
            <div class="order-item">
                <div>
                    <strong><?= e($item['title']) ?></strong>
                    <p>
                        <?= e($item['quantity']) ?> ×
                        <?= format_price($item['price']) ?>
                    </p>
                </div>

                <strong><?= format_price($item['subtotal']) ?></strong>
            </div>
        <?php endforeach; ?>

        <hr>

        <p>
            <strong>Total: <?= format_price($total) ?></strong>
        </p>
    </section>

    <form method="post">

        <h2>Shipping Information</h2>

        <div class="form-group">
            <label>
                Full Name
                <input
                    type="text"
                    name="ship_name"
                    required
                    value="<?= e($_POST['ship_name'] ?? $_SESSION['full_name'] ?? '') ?>"
                >
            </label>
        </div>

        <div class="form-group">
            <label>
                Phone
                <input
                    type="text"
                    name="ship_phone"
                    required
                    value="<?= e($_POST['ship_phone'] ?? '') ?>"
                >
            </label>
        </div>

        <div class="form-group">
            <label>
                Address
                <input
                    type="text"
                    name="ship_address"
                    required
                    value="<?= e($_POST['ship_address'] ?? '') ?>"
                >
            </label>
        </div>

        <div class="form-group">
            <label>
                City
                <input
                    type="text"
                    name="ship_city"
                    required
                    value="<?= e($_POST['ship_city'] ?? '') ?>"
                >
            </label>
        </div>

        <div class="form-group">
            <label>
                Province
                <input
                    type="text"
                    name="ship_province"
                    required
                    value="<?= e($_POST['ship_province'] ?? '') ?>"
                >
            </label>
        </div>

        <div class="form-group">
            <label>
                ZIP Code
                <input
                    type="text"
                    name="ship_zip"
                    required
                    value="<?= e($_POST['ship_zip'] ?? '') ?>"
                >
            </label>
        </div>

        <div class="form-group">
            <label>
                Region
                <input
                    type="text"
                    name="ship_region"
                    required
                    value="<?= e($_POST['ship_region'] ?? '') ?>"
                >
            </label>
        </div>

        <h2>Delivery</h2>

        <div class="form-group">
            <label>
                Delivery Option
                <select name="delivery_option" required>
                    <option value="">Select delivery option</option>
                    <option value="Standard" <?= ($_POST['delivery_option'] ?? '') === 'Standard' ? 'selected' : '' ?>>
                        Standard
                    </option>
                    <option value="Express" <?= ($_POST['delivery_option'] ?? '') === 'Express' ? 'selected' : '' ?>>
                        Express
                    </option>
                    <option value="Pick-up" <?= ($_POST['delivery_option'] ?? '') === 'Pick-up' ? 'selected' : '' ?>>
                        Pick-up
                    </option>
                </select>
            </label>
        </div>

        <h2>Payment</h2>

        <div class="form-group">
            <label>
                Payment Method
                <select name="payment_method" required>
                    <option value="">Select payment method</option>
                    <option value="GCash" <?= ($_POST['payment_method'] ?? '') === 'GCash' ? 'selected' : '' ?>>
                        GCash
                    </option>
                    <option value="Cash on Delivery" <?= ($_POST['payment_method'] ?? '') === 'Cash on Delivery' ? 'selected' : '' ?>>
                        Cash on Delivery
                    </option>
                </select>
            </label>
        </div>

        <div class="form-group">
            <label>
                Payment Reference
                <input
                    type="text"
                    name="payment_reference"
                    value="<?= e($_POST['payment_reference'] ?? '') ?>"
                    placeholder="Optional"
                >
            </label>
        </div>

        <button class="btn btn-dark" type="submit">
            Place Order
        </button>

    </form>

<?php else: ?>

    <p>Your cart is empty.</p>
    <a class="btn btn-dark" href="<?= BASE_URL ?>/customer/shop.php">
        Continue Shopping
    </a>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>