<?php // OWNER: Member 2

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/auth.php';

require_admin();

$orderStmt = $pdo->query("
    SELECT
        o.order_id,
        o.user_id,
        o.total_amount,
        o.payment_method,
        o.payment_reference,
        o.delivery_option,
        o.payment_status,
        o.shipping_status,
        o.order_status,
        o.ship_name,
        o.created_at,
        u.full_name,
        u.email
    FROM orders o
    INNER JOIN users u ON u.user_id = o.user_id
    ORDER BY o.created_at DESC, o.order_id DESC
");

$orders = $orderStmt->fetchAll();

$itemStmt = $pdo->prepare("
    SELECT
        oi.quantity,
        oi.price_at_sale,
        p.title
    FROM order_items oi
    INNER JOIN products p ON p.product_id = oi.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.order_item_id ASC
");

$page_title = 'Orders';
require __DIR__ . '/../includes/admin_header.php';
?>

<h1>Order Management</h1>

<?php if (!$orders): ?>

    <p>No orders have been placed yet.</p>

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

            <div class="order-customer">

                <h3>Customer</h3>

                <p>
                    <strong>Name:</strong>
                    <?= e($order['full_name']) ?>
                </p>

                <p>
                    <strong>Email:</strong>
                    <?= e($order['email']) ?>
                </p>

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
                    <strong>Payment Method:</strong>
                    <?= e($order['payment_method']) ?>
                </p>

                <p>
                    <strong>Delivery:</strong>
                    <?= e($order['delivery_option']) ?>
                </p>

                <?php if (!empty($order['payment_reference'])): ?>

                    <p>
                        <strong>Payment Reference:</strong>
                        <?= e($order['payment_reference']) ?>
                    </p>

                <?php endif; ?>

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

            <div class="order-shipping">

                <h3>Shipping Information</h3>

                <p>
                    <strong>Name:</strong>
                    <?= e($order['ship_name']) ?>
                </p>

                <p>
                    <strong>Shipping Date:</strong>
                    <?= e(date('F j, Y g:i A', strtotime($order['created_at']))) ?>
                </p>

            </div>

            <form method="post" action="<?= BASE_URL ?>/admin/order_update.php">

                <input
                    type="hidden"
                    name="order_id"
                    value="<?= (int)$order['order_id'] ?>"
                >

                <button class="btn btn-dark" type="submit">
                    Manage Order
                </button>

            </form>

        </section>

    <?php endforeach; ?>

<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>