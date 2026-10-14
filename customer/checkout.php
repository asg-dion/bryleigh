
<?php // OWNER: Member 2

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/auth.php';

require_login();

$userId = (int) current_user_id();
$cart = $_SESSION['cart'] ?? [];
$errors = [];
$items = [];
$total = 0.00;

if (empty($_SESSION['checkout_csrf_token'])) {
    $_SESSION['checkout_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['checkout_csrf_token'];

/*
 * Load the current cart from the database so prices and stock are current.
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
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $product) {
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
                $errors[] = $product['title'] .
                    ' does not have enough stock. Available: ' .
                    $product['stock'] . '.';
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

/*
 * Place the order using the delivery information submitted with checkout.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'place_order' &&
    empty($errors)
) {
    if (!hash_equals(
        $csrfToken,
        (string)($_POST['csrf_token'] ?? '')
    )) {
        $errors[] = 'Your session token is invalid. Please refresh the page and try again.';
    }

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

    foreach ([
        'Recipient name' => $shipName,
        'Phone number' => $shipPhone,
        'Street address' => $shipAddress,
        'City' => $shipCity,
        'Province' => $shipProvince,
        'ZIP code' => $shipZip,
        'Region' => $shipRegion,
    ] as $field => $value) {
        if ($value === '') {
            $errors[] = 'Please enter the ' . strtolower($field) . '.';
        }
    }

    if (
        strlen($shipName) > 150 ||
        strlen($shipPhone) > 50 ||
        strlen($shipAddress) > 255 ||
        strlen($shipCity) > 100 ||
        strlen($shipProvince) > 100 ||
        strlen($shipZip) > 20 ||
        strlen($shipRegion) > 100
    ) {
        $errors[] = 'Please check the length of your delivery information.';
    }

    if (!in_array(
        $paymentMethod,
        ['GCash', 'Cash on Delivery'],
        true
    )) {
        $errors[] = 'Please select a valid payment method.';
    }

    if (!in_array(
        $deliveryOption,
        ['Standard', 'Express', 'Pick-up'],
        true
    )) {
        $errors[] = 'Please select a valid delivery option.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            /*
             * Lock products while checking stock to prevent overselling.
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
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

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
             * Create the order using the submitted delivery information.
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
                VALUES (
                    ?, ?, ?, ?, ?,
                    'pending',
                    'not_shipped',
                    'pending',
                    ?, ?, ?, ?, ?, ?, ?
                )
            ");

            $orderStmt->execute([
                $userId,
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

            // Clear the cart only after a successful checkout.
            $_SESSION['cart'] = [];

            $pdo->commit();

            flash_set(
                'success',
                'Order #' . $orderId . ' has been placed successfully.'
            );

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

<div class="checkout-page">
    <h1>Checkout</h1>

<?php
if (
    function_exists('flash_get') &&
    ($successMessage = flash_get('success'))
):
?>
    <div class="flash success">
        <p><?= e($successMessage) ?></p>
</div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="flash error">
        <?php foreach ($errors as $error): ?>
            <p><?= e($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($items): ?>
    <div class="checkout-layout">
        <div class="checkout-main">
            <section class="checkout-card">
                <form method="post" class="checkout-form" id="place-order-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="place_order">

                    <div class="checkout-section">
                        <h2>Delivery Information</h2>
                        <div class="form-group">
                            <label for="ship_name">Recipient Name</label>
                            <input type="text" id="ship_name" name="ship_name" required maxlength="150" value="<?= e($_POST['ship_name'] ?? ($_SESSION['full_name'] ?? '')) ?>">
                        </div>
                        <div class="form-group">
                            <label for="ship_phone">Phone Number</label>
                            <input type="text" id="ship_phone" name="ship_phone" required maxlength="50" value="<?= e($_POST['ship_phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="ship_address">Street Address</label>
                            <input type="text" id="ship_address" name="ship_address" required maxlength="255" value="<?= e($_POST['ship_address'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="ship_city">City</label>
                            <input type="text" id="ship_city" name="ship_city" required maxlength="100" value="<?= e($_POST['ship_city'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="ship_province">Province</label>
                            <input type="text" id="ship_province" name="ship_province" required maxlength="100" value="<?= e($_POST['ship_province'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="ship_zip">ZIP Code</label>
                            <input type="text" id="ship_zip" name="ship_zip" required maxlength="20" value="<?= e($_POST['ship_zip'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="ship_region">Region</label>
                            <input type="text" id="ship_region" name="ship_region" required maxlength="100" value="<?= e($_POST['ship_region'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="checkout-section">
                        <h2>Delivery Option</h2>
                        <div class="form-group">
                            <label for="delivery_option">Delivery Option</label>
                            <select name="delivery_option" id="delivery_option" required>
                                <option value="">Select delivery option</option>
                                <?php foreach (['Standard', 'Express', 'Pick-up'] as $option): ?>
                                    <option value="<?= e($option) ?>" <?= ($_POST['delivery_option'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="checkout-section">
                        <h2>Payment Details</h2>
                        <div class="form-group">
                            <label for="payment_method">Payment Method</label>
                            <select name="payment_method" id="payment_method" required>
                                <option value="">Select payment method</option>
                                <?php foreach (['GCash', 'Cash on Delivery'] as $method): ?>
                                    <option value="<?= e($method) ?>" <?= ($_POST['payment_method'] ?? '') === $method ? 'selected' : '' ?>><?= e($method) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="payment_reference">Payment Reference</label>
                            <input type="text" id="payment_reference" name="payment_reference" value="<?= e($_POST['payment_reference'] ?? '') ?>" placeholder="Optional">
                        </div>
                    </div>
                </form>
            </section>
        </div>

        <aside class="order-summary checkout-summary">
            <h2>Order Summary</h2>
            <?php foreach ($items as $item): ?>
                <div class="order-item">
                    <div>
                        <strong><?= e($item['title']) ?></strong>
                        <p><?= e($item['quantity']) ?> × <?= format_price($item['price']) ?></p>
                    </div>
                    <strong><?= format_price($item['subtotal']) ?></strong>
                </div>
            <?php endforeach; ?>
            <div class="checkout-summary-total">
                <span>Total</span>
                <strong><?= format_price($total) ?></strong>
            </div>
            <button class="btn btn-dark checkout-place-order" type="submit" form="place-order-form">
                Place Order
            </button>
        </aside>
    </div>
<?php else: ?>

    <div class="checkout-empty">
        <h2>Your cart is empty</h2>
        <p class="muted">Add products to your cart before checking out.</p>
        <a class="btn btn-dark" href="<?= BASE_URL ?>/customer/shop.php">Continue Shopping</a>
    </div>

<?php endif; ?>
 </div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
