
<?php // OWNER: Member 2

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/auth.php';

require_admin();

$q = trim((string)($_GET['q'] ?? ''));
$q = mb_substr($q, 0, 100);

$allowedStatuses = ['all', 'pending', 'completed', 'cancelled'];
$statusFilter = $_GET['status'] ?? 'all';

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(
        CAST(o.order_id AS CHAR) LIKE ?
        OR u.full_name LIKE ?
    )";

    $search = '%' . $q . '%';
    $params[] = $search;
    $params[] = $search;
}

if ($statusFilter !== 'all') {
    $where[] = 'o.order_status = ?';
    $params[] = $statusFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$orderStmt = $pdo->prepare("
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
    $whereSql
    ORDER BY o.created_at DESC, o.order_id DESC
");

$orderStmt->execute($params);
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

<div class="admin-orders-page">
<div class="page-head">
    <h1>Order Management</h1>
</div>

<form method="get" class="order-filters admin-order-filters">
    <input
        type="search"
        name="q"
        value="<?= e($q) ?>"
        placeholder="Search order number or customer name"
        maxlength="100"
    >

    <select name="status">
        <?php foreach ([
            'all' => 'All statuses',
            'pending' => 'Pending',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled'
        ] as $value => $label): ?>
            <option
                value="<?= e($value) ?>"
                <?= $statusFilter === $value ? 'selected' : '' ?>
            >
                <?= e($label) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button class="btn btn-dark" type="submit">Search</button>

    <a class="btn btn-outline" href="<?= BASE_URL ?>/admin/orders.php">Clear</a>
</form>

<?php if (!$orders): ?>

    <p class="admin-orders-empty">
        <?= ($q !== '' || $statusFilter !== 'all')
            ? 'No orders match your search.'
            : 'No orders have been placed yet.' ?>
    </p>

<?php else: ?>

    <?php foreach ($orders as $order): ?>

        <section class="order-card">
            <div class="order-header">
                <div>
                    <h2>Order #<?= (int)$order['order_id'] ?></h2>
                    <p><?= e(date('F j, Y g:i A', strtotime($order['created_at']))) ?></p>
                </div>
                <strong><?= format_price($order['total_amount']) ?></strong>
            </div>

            <div class="order-statuses admin-order-statuses">
                <span class="order-status-badge badge-<?= e(str_replace('_', '-', $order['order_status'])) ?>">
                    <span class="status-label">Order</span><?= e(ucwords(str_replace('_', ' ', $order['order_status']))) ?>
                </span>
                <span class="order-status-badge badge-<?= e(str_replace('_', '-', $order['payment_status'])) ?>">
                    <span class="status-label">Payment</span><?= e(ucwords(str_replace('_', ' ', $order['payment_status']))) ?>
                </span>
                <span class="order-status-badge badge-<?= $order['shipping_status'] === 'not_shipped' ? 'pending' : e(str_replace('_', '-', $order['shipping_status'])) ?>">
                    <span class="status-label">Shipping</span><?= e(ucwords(str_replace('_', ' ', $order['shipping_status']))) ?>
                </span>
            </div>

            <div class="order-body admin-order-body">
                <div class="order-items-column">
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
                                    <div class="order-item-thumbnail" aria-hidden="true">
                                        <svg viewBox="0 0 24 24">
                                            <path d="m8 4 4 2 4-2 4 3-2 4-2-1v10H8V10l-2 1-2-4 4-3Z"></path>
                                        </svg>
                                    </div>
                                    <div class="order-item-info">
                                        <strong><?= e($item['title']) ?></strong>
                                        <p><?= $quantity ?> × <?= format_price($price) ?></p>
                                    </div>
                                    <strong class="order-item-total"><?= format_price($subtotal) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No items found for this order.</p>
                    <?php endif; ?>
                </div>

                <div class="order-details-column admin-order-details">
                    <div class="admin-order-info-box">
                        <h3>Customer</h3>
                        <div class="order-meta">
                            <div><span class="meta-label">Name</span><span class="meta-value"><?= e($order['full_name']) ?></span></div>
                            <div><span class="meta-label">Email</span><span class="meta-value"><?= e($order['email']) ?></span></div>
                        </div>
                    </div>
                    <div class="admin-order-info-box">
                        <h3>Payment</h3>
                        <div class="order-meta">
                            <div><span class="meta-label">Method</span><span class="meta-value"><?= e($order['payment_method']) ?></span></div>
                            <?php if (!empty($order['payment_reference'])): ?>
                                <div><span class="meta-label">Reference</span><span class="meta-value"><?= e($order['payment_reference']) ?></span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="admin-order-info-box">
                        <h3>Delivery</h3>
                        <div class="order-meta">
                            <div><span class="meta-label">Type</span><span class="meta-value"><?= e($order['delivery_option']) ?></span></div>
                            <div><span class="meta-label">Recipient</span><span class="meta-value"><?= e($order['ship_name']) ?></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <form class="order-footer admin-order-update" method="post" action="<?= BASE_URL ?>/admin/order_update.php">
                <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                <div class="admin-order-control">
                    <label for="payment-status-<?= (int)$order['order_id'] ?>">Payment</label>
                    <select id="payment-status-<?= (int)$order['order_id'] ?>" name="payment_status" required>
                        <?php foreach (['pending' => 'Pending', 'paid' => 'Paid'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $order['payment_status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-order-control">
                    <label for="shipping-status-<?= (int)$order['order_id'] ?>">Shipping</label>
                    <select id="shipping-status-<?= (int)$order['order_id'] ?>" name="shipping_status" required>
                        <?php foreach (['not_shipped' => 'Not shipped', 'shipped' => 'Shipped', 'delivered' => 'Delivered'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $order['shipping_status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-order-control">
                    <label for="order-status-<?= (int)$order['order_id'] ?>">Order</label>
                    <select id="order-status-<?= (int)$order['order_id'] ?>" name="order_status" required <?= $order['order_status'] === 'cancelled' ? 'disabled' : '' ?>>
                        <?php foreach (['pending' => 'Pending', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $order['order_status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($order['order_status'] === 'cancelled'): ?>
                        <input type="hidden" name="order_status" value="cancelled">
                    <?php endif; ?>
                </div>
                <button class="btn btn-dark" type="submit" name="update_order" value="1">Update</button>
            </form>
        </section>

    <?php endforeach; ?>

<?php endif; ?>

</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
