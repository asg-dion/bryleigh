<?php // OWNER: Member 2

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/auth.php';

require_login();

$userId = current_user_id();

$orderStmt = $pdo->prepare("
    SELECT
        order_id,
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
        ship_region,
        created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC, order_id DESC
");

$orderStmt->execute([$userId]);
$orders = $orderStmt->fetchAll();

$itemStmt = $pdo->prepare("
    SELECT
        oi.product_id,
        oi.quantity,
        oi.price_at_sale,
        p.title
    FROM order_items oi
    INNER JOIN products p ON p.product_id = oi.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.order_item_id ASC
");

$page_title = 'Order History';
require __DIR__ . '/../includes/header.php';
?>

<h1>Order History</h1>

<?php if (!$orders): ?>

    <p>You have no orders yet.</p>

    <a class="btn btn-dark" href="<?= BASE_URL ?>/customer/shop.php">
        Continue Shopping
    </a>

<?php else: ?>

    <?php foreach ($orders as $order): ?>

        <section class="order-card">

            <div class="order-card-header">
                <div>
                    <h2>Order #<?= (int)$order['order_id'] ?></h2>
                    <p>
                        <?= e(date('F j, Y g:i A', strtotime($order['created_at']))) ?>
                    </p>
                </div>

                <strong>
                    <?= format_price($order['total_amount']) ?>
                </strong>
            </div>

            <div class="order-status">
                <p>
                    <strong>Order Status:</strong>
                    <?= e(ucwords(str_replace('_', ' ', $order['order_status']))) ?>
                </p>

                <p>
                    <strong>Payment Status:</strong>
                    <?= e(ucwords(str_replace('_', ' ', $order['payment_status']))) ?>
                </p>

                <p>
                    <strong>Shipping Status:</strong>
                    <?= e(ucwords(str_replace('_', ' ', $order['shipping_status']))) ?>
                </p>

                <p>
                    <strong>Delivery:</strong>
                    <?= e($order['delivery_option']) ?>
                </p>

                <p>
                    <strong>Payment Method:</strong>
                    <?= e($order['payment_method']) ?>
                </p>

            </div>

            <h3>Items</h3>

            <?php
            $itemStmt->execute([(int)$order['order_id']]);
            $items = $itemStmt->fetchAll();
            ?>

            <?php if ($items): ?>

                <div class="order-items">

                    <?php foreach ($items as $item): ?>

                        <?php
                        $quantity = (int)$item['quantity'];
                        $price = (float)$item['price_at_sale'];
                        $subtotal = $quantity * $price;
                        ?>

                        <div class="order-item">

                            <div>
                                <strong><?= e($item['title']) ?></strong>

                                <p>
                                    <?= $quantity ?> ×
                                    <?= format_price($price) ?>
                                </p>
                            </div>

                            <strong>
                                <?= format_price($subtotal) ?>
                            </strong>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <p>No items found for this order.</p>

            <?php endif; ?>

            <h3>Shipping Information</h3>

            <div class="shipping-info">

                <p>
                    <strong>Name:</strong>
                    <?= e($order['ship_name']) ?>
                </p>

                <p>
                    <strong>Phone:</strong>
                    <?= e($order['ship_phone']) ?>
                </p>

                <p>
                    <strong>Address:</strong>
                    <?= e($order['ship_address']) ?>
                </p>

                <p>
                    <strong>City:</strong>
                    <?= e($order['ship_city']) ?>
                </p>

                <p>
                    <strong>Province:</strong>
                    <?= e($order['ship_province']) ?>
                </p>

                <p>
                    <strong>ZIP:</strong>
                    <?= e($order['ship_zip']) ?>
                </p>

                <p>
                    <strong>Region:</strong>
                    <?= e($order['ship_region']) ?>
                </p>

            </div>

        </section>

    <?php endforeach; ?>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>