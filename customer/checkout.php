
<?php // OWNER: Member 2

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/auth.php';

require_login();

$userId = (int) current_user_id();
$cart = $_SESSION['cart'] ?? [];
$errors = [];
$items = [];
$addresses = [];
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
 * Save a new address to this customer's address book.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'save_address'
) {
    if (!hash_equals(
        $csrfToken,
        (string)($_POST['csrf_token'] ?? '')
    )) {
        $errors[] = 'Your session token is invalid. Please refresh the page and try again.';
    } else {
        $label = trim($_POST['address_label'] ?? '');
        $name = trim($_POST['new_ship_name'] ?? '');
        $phone = trim($_POST['new_ship_phone'] ?? '');
        $street = trim($_POST['new_ship_address'] ?? '');
        $city = trim($_POST['new_ship_city'] ?? '');
        $province = trim($_POST['new_ship_province'] ?? '');
        $zip = trim($_POST['new_ship_zip'] ?? '');
        $region = trim($_POST['new_ship_region'] ?? '');

        if ($label === '') {
            $errors[] = 'Please give this address a label, such as Home or Dorm.';
        }
        if ($name === '') {
            $errors[] = 'Please enter the recipient name.';
        }
        if ($phone === '') {
            $errors[] = 'Please enter the phone number.';
        }
        if ($street === '') {
            $errors[] = 'Please enter the street address.';
        }
        if ($city === '') {
            $errors[] = 'Please enter the city.';
        }
        if ($province === '') {
            $errors[] = 'Please enter the province.';
        }
        if ($zip === '') {
            $errors[] = 'Please enter the ZIP code.';
        }
        if ($region === '') {
            $errors[] = 'Please enter the region.';
        }

        if (strlen($label) > 60) {
            $errors[] = 'The address label must be 60 characters or fewer.';
        }
        if (strlen($name) > 150) {
            $errors[] = 'The recipient name must be 150 characters or fewer.';
        }
        if (strlen($phone) > 40) {
            $errors[] = 'The phone number must be 40 characters or fewer.';
        }
        if (strlen($street) > 255) {
            $errors[] = 'The street address must be 255 characters or fewer.';
        }
        if (strlen($city) > 100) {
            $errors[] = 'The city must be 100 characters or fewer.';
        }
        if (strlen($province) > 100) {
            $errors[] = 'The province must be 100 characters or fewer.';
        }
        if (strlen($zip) > 20) {
            $errors[] = 'The ZIP code must be 20 characters or fewer.';
        }
        if (strlen($region) > 100) {
            $errors[] = 'The region must be 100 characters or fewer.';
        }

        if (!$errors) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO customer_addresses (
                        user_id,
                        address_label,
                        recipient_name,
                        phone,
                        street_address,
                        city,
                        province,
                        zip_code,
                        region
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $userId,
                    $label,
                    $name,
                    $phone,
                    $street,
                    $city,
                    $province,
                    $zip,
                    $region
                ]);

                flash_set(
                    'success',
                    'Address saved. You can now select it for checkout.'
                );

                redirect('customer/checkout.php');
            } catch (Throwable $e) {
                $errors[] = 'Unable to save the address. Please check that the customer_addresses table has been created.';
            }
        }
    }
}

/*
 * Place the order using an address belonging to the signed-in customer.
 * Shipping information is copied into the order so later address changes
 * do not alter existing orders.
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

    $addressId = (int)($_POST['address_id'] ?? 0);
    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $paymentReference = trim($_POST['payment_reference'] ?? '');
    $deliveryOption = trim($_POST['delivery_option'] ?? '');

    if ($addressId <= 0) {
        $errors[] = 'Please select a saved delivery address.';
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

            // Verify that the selected address belongs to this customer.
            $addressStmt = $pdo->prepare("
                SELECT
                    recipient_name,
                    phone,
                    street_address,
                    city,
                    province,
                    zip_code,
                    region
                FROM customer_addresses
                WHERE address_id = ?
                  AND user_id = ?
                FOR UPDATE
            ");

            $addressStmt->execute([$addressId, $userId]);
            $address = $addressStmt->fetch(PDO::FETCH_ASSOC);

            if (!$address) {
                throw new RuntimeException(
                    'The selected address was not found in your address book. Please select it again.'
                );
            }

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
             * Create the order using a snapshot of the selected address.
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
                $address['recipient_name'],
                $address['phone'],
                $address['street_address'],
                $address['city'],
                $address['province'],
                $address['zip_code'],
                $address['region']
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

/*
 * Load only addresses belonging to the signed-in customer.
 */
try {
    $addressListStmt = $pdo->prepare("
        SELECT
            address_id,
            address_label,
            recipient_name,
            phone,
            street_address,
            city,
            province,
            zip_code,
            region
        FROM customer_addresses
        WHERE user_id = ?
        ORDER BY address_id DESC
    ");

    $addressListStmt->execute([$userId]);
    $addresses = $addressListStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $errors[] = 'The address book could not be loaded. Make sure the customer_addresses table has been created.';
}

$selectedAddressId = (int)(
    $_POST['address_id'] ?? ($addresses[0]['address_id'] ?? 0)
);

$page_title = 'Checkout';
require __DIR__ . '/../includes/header.php';
?>

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

                <strong>
                    <?= format_price($item['subtotal']) ?>
                </strong>
            </div>
        <?php endforeach; ?>

        <hr>

        <p>
            <strong>Total: <?= format_price($total) ?></strong>
        </p>
    </section>

    <section class="address-book">
        <h2>Saved Delivery Addresses</h2>

        <?php if ($addresses): ?>
            <form method="post" class="checkout-form">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="place_order"
                >

                <div class="form-group">
                    <label for="address_id">Choose an address</label>

                    <select name="address_id" id="address_id" required>
                        <option value="">Select a saved address</option>

                        <?php foreach ($addresses as $address): ?>
                            <?php
                            $addressText =
                                $address['address_label'] . ' — ' .
                                $address['recipient_name'] . ', ' .
                                $address['street_address'] . ', ' .
                                $address['city'] . ', ' .
                                $address['province'] . ' ' .
                                $address['zip_code'] . ', ' .
                                $address['region'] . ' (' .
                                $address['phone'] . ')';
                            ?>

                            <option
                                value="<?= (int)$address['address_id'] ?>"
                                <?= $selectedAddressId === (int)$address['address_id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($addressText) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="delivery_option">Delivery Option</label>

                    <select
                        name="delivery_option"
                        id="delivery_option"
                        required
                    >
                        <option value="">Select delivery option</option>

                        <?php foreach (['Standard', 'Express', 'Pick-up'] as $option): ?>
                            <option
                                value="<?= e($option) ?>"
                                <?= ($_POST['delivery_option'] ?? '') === $option
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <h2>Payment</h2>

                <div class="form-group">
                    <label for="payment_method">Payment Method</label>

                    <select
                        name="payment_method"
                        id="payment_method"
                        required
                    >
                        <option value="">Select payment method</option>

                        <?php foreach (['GCash', 'Cash on Delivery'] as $method): ?>
                            <option
                                value="<?= e($method) ?>"
                                <?= ($_POST['payment_method'] ?? '') === $method
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($method) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="payment_reference">
                        Payment Reference
                    </label>

                    <input
                        type="text"
                        id="payment_reference"
                        name="payment_reference"
                        value="<?= e($_POST['payment_reference'] ?? '') ?>"
                        placeholder="Optional"
                    >
                </div>

                <button class="btn btn-dark" type="submit">
                    Place Order
                </button>
            </form>
        <?php else: ?>
            <p>
                You have no saved addresses yet.
                Add one below before placing your order.
            </p>
        <?php endif; ?>
    </section>

    <section class="add-address">
        <h2>Add a New Address</h2>

        <form method="post" class="checkout-form">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="save_address"
            >

            <div class="form-group">
                <label for="address_label">Address Label</label>

                <input
                    type="text"
                    id="address_label"
                    name="address_label"
                    required
                    maxlength="60"
                    value="<?= e($_POST['address_label'] ?? '') ?>"
                    placeholder="e.g. Home, Dorm, Work"
                >
            </div>

            <div class="form-group">
                <label for="new_ship_name">Recipient Name</label>

                <input
                    type="text"
                    id="new_ship_name"
                    name="new_ship_name"
                    required
                    maxlength="150"
                    value="<?= e(
                        $_POST['new_ship_name'] ??
                        ($_SESSION['full_name'] ?? '')
                    ) ?>"
                >
            </div>

            <div class="form-group">
                <label for="new_ship_phone">Phone Number</label>

                <input
                    type="text"
                    id="new_ship_phone"
                    name="new_ship_phone"
                    required
                    maxlength="40"
                    value="<?= e($_POST['new_ship_phone'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="new_ship_address">Street Address</label>

                <input
                    type="text"
                    id="new_ship_address"
                    name="new_ship_address"
                    required
                    maxlength="255"
                    value="<?= e($_POST['new_ship_address'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="new_ship_city">City</label>

                <input
                    type="text"
                    id="new_ship_city"
                    name="new_ship_city"
                    required
                    maxlength="100"
                    value="<?= e($_POST['new_ship_city'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="new_ship_province">Province</label>

                <input
                    type="text"
                    id="new_ship_province"
                    name="new_ship_province"
                    required
                    maxlength="100"
                    value="<?= e($_POST['new_ship_province'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="new_ship_zip">ZIP Code</label>

                <input
                    type="text"
                    id="new_ship_zip"
                    name="new_ship_zip"
                    required
                    maxlength="20"
                    value="<?= e($_POST['new_ship_zip'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="new_ship_region">Region</label>

                <input
                    type="text"
                    id="new_ship_region"
                    name="new_ship_region"
                    required
                    maxlength="100"
                    value="<?= e($_POST['new_ship_region'] ?? '') ?>"
                >
            </div>

            <button class="btn btn-dark" type="submit">
                Save Address
            </button>
        </form>
    </section>

<?php else: ?>

    <p>Your cart is empty.</p>

    <a
        class="btn btn-dark"
        href="<?= BASE_URL ?>/customer/shop.php"
    >
        Continue Shopping
    </a>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
