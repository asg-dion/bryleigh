<?php // OWNER: Member 2

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/auth.php';

require_admin();

$orderId = (int)($_POST['order_id'] ?? $_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    flash_set('error', 'Invalid order.');
    redirect('admin/orders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {

    $paymentStatus = $_POST['payment_status'] ?? '';
    $shippingStatus = $_POST['shipping_status'] ?? '';
    $orderStatus = $_POST['order_status'] ?? '';

    $validPaymentStatuses = ['pending', 'paid'];
    $validShippingStatuses = ['not_shipped', 'shipped', 'delivered'];
    $validOrderStatuses = ['pending', 'completed', 'cancelled'];

    if (
        !in_array($paymentStatus, $validPaymentStatuses, true) ||
        !in_array($shippingStatus, $validShippingStatuses, true) ||
        !in_array($orderStatus, $validOrderStatuses, true)
    ) {
        flash_set('error', 'Invalid order status.');
        redirect('admin/order_update.php?order_id=' . $orderId);
    }

    $stmt = $pdo->prepare("
        UPDATE orders
        SET
            payment_status = ?,
            shipping_status = ?,
            order_status = ?
        WHERE order_id = ?
    ");

    $stmt->execute([
        $paymentStatus,
        $shippingStatus,
        $orderStatus,
        $orderId
    ]);

    flash_set('success', 'Order #' . $orderId . ' has been updated.');
    redirect('admin/orders.php');
}

$orderStmt = $pdo->prepare("
    SELECT
        o.order_id,
        o.total_amount,
        o.payment_method,
        o.payment_reference,
        o.delivery_option,
        o.payment_status,
        o.shipping_status,
        o.order_status,
        o.ship_name,
        o.ship_phone,
        o.ship_address,
        o.ship_city,
        o.ship_province,
        o.ship_zip,
        o.ship_region,
        o.created_at,
        u.full_name,
        u.email
    FROM orders o
    INNER JOIN users u ON u.user_id = o.user_id
    WHERE o.order_id = ?
");

$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    flash_set('error', 'Order not found.');
    redirect('admin/orders.php');
}

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

$itemStmt->execute([$orderId]);
$items = $itemStmt->fetchAll();

$page_title = 'Manage Order #' . $orderId;
require __DIR__ . '/../includes/admin_header.php';
?>

<h1>Manage Order #<?= (int)$order['order_id'] ?></h1>

<section class="order-card">

    <div class="order-card-header">

        <div>
            <h2>Customer</h2>

            <p>
                <strong>Name:</strong>
                <?= e($order['full_name']) ?>
            </p>

            <p>
                <strong>Email:</strong>
                <?= e($order['email']) ?>
            </p>
        </div>

        <strong>
            <?= format_price($order['total_amount']) ?>
        </strong>

    </div>

    <h2>Items</h2>

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

    <h2>Shipping Information</h2>

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

    <h2>Order Status</h2>

    <form method="post">

        <input
            type="hidden"
            name="order_id"
            value="<?= (int)$order['order_id'] ?>"
        >

        <div class="form-group">

            <label>
                Payment Status

                <select name="payment_status" required>

                    <option
                        value="pending"
                        <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>
                    >
                        Pending
                    </option>

                    <option
                        value="paid"
                        <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>
                    >
                        Paid
                    </option>

                </select>

            </label>

        </div>

        <div class="form-group">

            <label>
                Shipping Status

                <select name="shipping_status" required>

                    <option
                        value="not_shipped"
                        <?= $order['shipping_status'] === 'not_shipped' ? 'selected' : '' ?>
                    >
                        Not Shipped
                    </option>

                    <option
                        value="shipped"
                        <?= $order['shipping_status'] === 'shipped' ? 'selected' : '' ?>
                    >
                        Shipped
                    </option>

                    <option
                        value="delivered"
                        <?= $order['shipping_status'] === 'delivered' ? 'selected' : '' ?>
                    >
                        Delivered
                    </option>

                </select>

            </label>

        </div>

        <div class="form-group">

            <label>
                Order Status

                <select name="order_status" required>

                    <option
                        value="pending"
                        <?= $order['order_status'] === 'pending' ? 'selected' : '' ?>
                    >
                        Pending
                    </option>

                    <option
                        value="completed"
                        <?= $order['order_status'] === 'completed' ? 'selected' : '' ?>
                    >
                        Completed
                    </option>

                    <option
                        value="cancelled"
                        <?= $order['order_status'] === 'cancelled' ? 'selected' : '' ?>
                    >
                        Cancelled
                    </option>

                </select>

            </label>

        </div>

        <button
            class="btn btn-dark"
            type="submit"
            name="update_order"
            value="1"
        >
            Update Order
        </button>

        <a
            class="btn"
            href="<?= BASE_URL ?>/admin/orders.php"
        >
            Back to Orders
        </a>

    </form>

</section>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>