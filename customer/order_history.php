
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

<section class="container order-history-page">
    <h1 class="order-history-title">Order History</h1>

    <?php if (!$orders): ?>
        <div class="order-empty-state">
            <span class="order-empty-icon" aria-hidden="true">♡</span>
            <h2>No orders yet</h2>
            <p>Your next favorite find is waiting to be discovered.</p>
            <a class="btn btn-dark" href="<?= BASE_URL ?>/customer/shop.php">
                Start shopping
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <article class="order-card">
                <header class="order-header">
                    <div>
                        <h2>Order #<?= (int) $order['order_id'] ?></h2>
                        <p>
                            <?= e(date(
                                'F j, Y g:i A',
                                strtotime($order['created_at'])
                            )) ?>
                        </p>
                    </div>
                    <strong><?= format_price($order['total_amount']) ?></strong>
                </header>

                <div class="order-body">
                    <section class="order-items-column">
                        <h3>Items</h3>
                        <?php
                        $itemStmt->execute([(int) $order['order_id']]);
                        $items = $itemStmt->fetchAll();
                        ?>

                        <?php if ($items): ?>
                            <div class="order-items">
                                <div class="order-item-list">
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $quantity = (int) $item['quantity'];
                                        $price = (float) $item['price_at_sale'];
                                        $subtotal = $quantity * $price;
                                        ?>
                                        <div class="order-item">
                                            <span class="order-item-thumbnail" aria-hidden="true">
                                                <svg viewBox="0 0 48 48">
                                                    <path d="m16 10-9 5 4 9 5-2v17h16V22l5 2 4-9-9-5-4 5h-8l-4-5Z"></path>
                                                    <path d="m17 10 3 5h8l3-5"></path>
                                                </svg>
                                            </span>
                                            <div class="order-item-info">
                                                <strong><?= e($item['title']) ?></strong>
                                                <p><?= $quantity ?> × <?= format_price($price) ?></p>
                                            </div>
                                            <strong class="order-item-total">
                                                <?= format_price($subtotal) ?>
                                            </strong>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="order-total">
                                    <span>Order total</span>
                                    <strong><?= format_price($order['total_amount']) ?></strong>
                                </div>
                            </div>
                        <?php else: ?>
                            <p>No items found for this order.</p>
                        <?php endif; ?>
                    </section>

                    <section class="order-details-column">
                        <h3>Order Details</h3>
                        <div class="order-statuses" aria-label="Order statuses">
                            <span class="order-status-badge badge-<?= e($order['order_status']) ?>">
                                <span class="status-label">Order</span>
                                <span><?= e(ucwords(str_replace('_', ' ', $order['order_status']))) ?></span>
                            </span>
                            <span class="order-status-badge badge-<?= e($order['payment_status']) ?>">
                                <span class="status-label">Payment</span>
                                <span><?= e(ucwords(str_replace('_', ' ', $order['payment_status']))) ?></span>
                            </span>
                            <span class="order-status-badge badge-<?= $order['shipping_status'] === 'not_shipped' ? 'pending' : e($order['shipping_status']) ?>">
                                <span class="status-label">Shipping</span>
                                <span><?= e(ucwords(str_replace('_', ' ', $order['shipping_status']))) ?></span>
                            </span>
                        </div>

                        <div class="order-meta">
                            <div>
                                <span class="meta-label">Delivery</span>
                                <span class="meta-value"><?= e($order['delivery_option']) ?></span>
                            </div>
                            <div>
                                <span class="meta-label">Payment Method</span>
                                <span class="meta-value"><?= e($order['payment_method']) ?></span>
                            </div>
                        </div>

                        <?php if (
                            $order['order_status'] === 'cancelled'
                            && !empty($order['cancellation_reason'])
                        ): ?>
                            <p class="cancellation-reason">
                                <strong>Cancellation Reason:</strong><br>
                                <?= nl2br(e($order['cancellation_reason'])) ?>
                            </p>
                        <?php endif; ?>

                        <h3 class="shipping-heading">Shipping to</h3>
                        <div class="shipping-info">
                            <p class="shipping-recipient">
                                <strong><?= e($order['ship_name']) ?></strong>
                                <span><?= e($order['ship_phone']) ?></span>
                            </p>
                            <p class="shipping-address">
                                <?= e($order['ship_address']) ?>,
                                <?= e($order['ship_city']) ?>,
                                <?= e($order['ship_province']) ?>
                                <?= e($order['ship_zip']) ?>,
                                <?= e($order['ship_region']) ?>
                            </p>
                        </div>
                    </section>
                </div>

                <?php if ($order['order_status'] === 'pending'): ?>
                    <footer class="order-footer">
                        <details class="cancel-order-details">
                            <summary class="cancel-order-summary">Cancel order</summary>
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
                                    class="confirm-cancellation"
                                >
                                    Confirm cancellation
                                </button>
                            </form>
                        </details>
                    </footer>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
