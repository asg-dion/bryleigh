
<?php // OWNER: Member 2

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/auth.php';

require_login();

$userId = (int) current_user_id();

// Generate a CSRF token.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle cancellation in this same file.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['cancel_order'])
) {
    $orderId = filter_input(
        INPUT_POST,
        'order_id',
        FILTER_VALIDATE_INT
    );

    $cancellationReason = trim(
        (string) ($_POST['cancellation_reason'] ?? '')
    );

    $reasonLength = function_exists('mb_strlen')
        ? mb_strlen($cancellationReason, 'UTF-8')
        : strlen($cancellationReason);

    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');

    if (!$orderId) {
        flash_set('error', 'Invalid order. Please try again.');
        redirect('customer/order_history.php');
    }

    if (
        $submittedToken === ''
        || $sessionToken === ''
        || !hash_equals($sessionToken, $submittedToken)
    ) {
        flash_set('error', 'Invalid request. Please try again.');
        redirect('customer/order_history.php');
    }

    if (
        $cancellationReason === ''
        || $reasonLength < 10
        || $reasonLength > 500
    ) {
        flash_set(
            'error',
            'Please provide a cancellation reason between 10 and 500 characters.'
        );
        redirect('customer/order_history.php');
    }

    try {
        $pdo->beginTransaction();

        // Verify ownership and lock the order.
        $cancelStmt = $pdo->prepare("
            SELECT order_id, order_status
            FROM orders
            WHERE order_id = ? AND user_id = ?
            FOR UPDATE
        ");

        $cancelStmt->execute([$orderId, $userId]);
        $cancelOrder = $cancelStmt->fetch();

        if (!$cancelOrder) {
            throw new RuntimeException(
                'Order not found or you do not have permission to cancel it.'
            );
        }

        // Only pending orders can be cancelled.
        if ($cancelOrder['order_status'] !== 'pending') {
            throw new RuntimeException(
                'Only pending orders can be cancelled.'
            );
        }

        // Retrieve the items to restore their stock.
        $itemsStmt = $pdo->prepare("
            SELECT product_id, quantity
            FROM order_items
            WHERE order_id = ?
        ");

        $itemsStmt->execute([$orderId]);
        $cancelItems = $itemsStmt->fetchAll();

        if (!$cancelItems) {
            throw new RuntimeException(
                'No order items were found. The order was not cancelled.'
            );
        }

        // Restore stock inside the transaction.
        $restoreStmt = $pdo->prepare("
            UPDATE products
            SET stock = stock + ?
            WHERE product_id = ?
        ");

        foreach ($cancelItems as $item) {
            $productId = (int) $item['product_id'];
            $quantity = (int) $item['quantity'];

            if ($productId <= 0 || $quantity <= 0) {
                throw new RuntimeException(
                    'Invalid order item data. No changes were saved.'
                );
            }

            $restoreStmt->execute([$quantity, $productId]);

            if ($restoreStmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'A product could not be restocked. No changes were saved.'
                );
            }
        }

        // Save the reason and change the status.
        $updateStmt = $pdo->prepare("
            UPDATE orders
            SET order_status = 'cancelled',
                cancellation_reason = ?
            WHERE order_id = ?
              AND user_id = ?
              AND order_status = 'pending'
        ");

        $updateStmt->execute([
            $cancellationReason,
            $orderId,
            $userId
        ]);

        if ($updateStmt->rowCount() !== 1) {
            throw new RuntimeException(
                'The order status changed. Please refresh and try again.'
            );
        }

        $pdo->commit();

        flash_set(
            'success',
            'Your order has been cancelled successfully.'
        );

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof RuntimeException) {
            flash_set('error', $e->getMessage());
        } else {
            error_log(
                'Order cancellation failed: ' . $e->getMessage()
            );

            flash_set(
                'error',
                'The order could not be cancelled. Please try again.'
            );
        }
    }

    redirect('customer/order_history.php');
}

// Retrieve the customer's orders.
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
        cancellation_reason,
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
                    <h2>Order #<?= (int) $order['order_id'] ?></h2>

                    <p>
                        <?= e(date(
                            'F j, Y g:i A',
                            strtotime($order['created_at'])
                        )) ?>
                    </p>
                </div>

                <strong>
                    <?= format_price($order['total_amount']) ?>
                </strong>
            </div>

            <div class="order-status">

                <p>
                    <strong>Order Status:</strong>
                    <?= e(ucwords(str_replace(
                        '_',
                        ' ',
                        $order['order_status']
                    ))) ?>
                </p>

                <p>
                    <strong>Payment Status:</strong>
                    <?= e(ucwords(str_replace(
                        '_',
                        ' ',
                        $order['payment_status']
                    ))) ?>
                </p>

                <p>
                    <strong>Shipping Status:</strong>
                    <?= e(ucwords(str_replace(
                        '_',
                        ' ',
                        $order['shipping_status']
                    ))) ?>
                </p>

                <p>
                    <strong>Delivery:</strong>
                    <?= e($order['delivery_option']) ?>
                </p>

                <p>
                    <strong>Payment Method:</strong>
                    <?= e($order['payment_method']) ?>
                </p>

                <?php if (
                    $order['order_status'] === 'cancelled'
                    && !empty($order['cancellation_reason'])
                ): ?>
                    <p>
                        <strong>Cancellation Reason:</strong><br>
                        <?= nl2br(e($order['cancellation_reason'])) ?>
                    </p>
                <?php endif; ?>

                <?php if ($order['order_status'] === 'pending'): ?>

                    <form
                        method="POST"
                        action="<?= BASE_URL ?>/customer/order_history.php"
                        onsubmit="return confirm('Are you sure you want to cancel this order?');"
                        class="cancel-order-form"
                    >
                        <input
                            type="hidden"
                            name="order_id"
                            value="<?= (int) $order['order_id'] ?>"
                        >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION['csrf_token']) ?>"
                        >

                        <div class="form-group">
                            <label
                                for="cancellation_reason_<?= (int) $order['order_id'] ?>"
                            >
                                Why do you want to cancel this order?
                            </label>

                            <textarea
                                id="cancellation_reason_<?= (int) $order['order_id'] ?>"
                                name="cancellation_reason"
                                rows="3"
                                minlength="10"
                                maxlength="500"
                                required
                                placeholder="Explain your reason (10–500 characters)."
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            name="cancel_order"
                            value="1"
                            class="cancel-order-btn"
                        >
                            Cancel Order
                        </button>
                    </form>

                <?php endif; ?>

            </div>

            <h3>Items</h3>

            <?php
            $itemStmt->execute([(int) $order['order_id']]);
            $items = $itemStmt->fetchAll();
            ?>

            <?php if ($items): ?>

                <div class="order-items">

                    <?php foreach ($items as $item): ?>

                        <?php
                        $quantity = (int) $item['quantity'];
                        $price = (float) $item['price_at_sale'];
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
