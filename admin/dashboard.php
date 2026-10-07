<?php 
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// Active products with 1 to LOW_STOCK_THRESHOLD units left count as "low stock".
// 2 matches the "Only N left" badge used on the shop and product pages.
const LOW_STOCK_THRESHOLD = 2;

// ---- Product counts (archived products are excluded from all of them) ----
$stmt = $pdo->prepare(
    "SELECT COUNT(*)                                          AS active_total,
            COALESCE(SUM(stock = 0), 0)                       AS out_of_stock,
            COALESCE(SUM(stock BETWEEN 1 AND ?), 0)           AS low_stock
       FROM products
      WHERE status = 'active'"
);
$stmt->execute([LOW_STOCK_THRESHOLD]);
$counts = $stmt->fetch();

$activeProducts = (int)$counts['active_total'];
$outOfStock     = (int)$counts['out_of_stock'];
$lowStock       = (int)$counts['low_stock'];

// ---- Orders ----
$pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();

$recentOrders = $pdo->query(
    "SELECT o.order_id, o.total_amount, o.order_status, o.payment_status, o.shipping_status, o.created_at, u.full_name
       FROM orders o
       JOIN users u ON u.user_id = o.user_id
      ORDER BY o.created_at DESC, o.order_id DESC
      LIMIT 5"
)->fetchAll();

$label = static fn(string $s): string => ucwords(str_replace('_', ' ', $s));

$page_title = 'Dashboard';
require __DIR__ . '/../includes/admin_header.php';
?>
<?php /* Stopgap: admin_header.php does not load catalog.css yet. Remove once it does. */ ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/catalog.css">

<div class="page-head">
    <h1>Admin Dashboard</h1>
</div>

<div class="stat-grid">
    <a class="stat-card" href="<?= BASE_URL ?>/admin/products.php?status=active">
        <span class="stat-label">Active products</span>
        <span class="stat-num"><?= $activeProducts ?></span>
    </a>
    <a class="stat-card<?= $lowStock > 0 ? ' warn' : '' ?>" href="<?= BASE_URL ?>/admin/products.php?status=active">
        <span class="stat-label">Low stock (1&ndash;<?= LOW_STOCK_THRESHOLD ?> left)</span>
        <span class="stat-num"><?= $lowStock ?></span>
    </a>
    <a class="stat-card<?= $outOfStock > 0 ? ' danger' : '' ?>" href="<?= BASE_URL ?>/admin/products.php?status=active">
        <span class="stat-label">Out of stock</span>
        <span class="stat-num"><?= $outOfStock ?></span>
    </a>
    <a class="stat-card<?= $pendingOrders > 0 ? ' warn' : '' ?>" href="<?= BASE_URL ?>/admin/orders.php">
        <span class="stat-label">Pending orders</span>
        <span class="stat-num"><?= $pendingOrders ?></span>
    </a>
</div>

<div class="page-head" style="margin-top:2rem">
    <h2 style="margin:0">Recent orders</h2>
    <a class="btn btn-sm" href="<?= BASE_URL ?>/admin/orders.php">View all orders</a>
</div>

<div class="table-wrap">
<table class="table">
    <thead>
        <tr><th>Order</th><th>Customer</th><th>Date</th><th>Total</th><th>Order status</th><th>Payment</th><th>Shipping</th></tr>
    </thead>
    <tbody>
    <?php if (!$recentOrders): ?>
        <tr><td colspan="7" class="muted" style="text-align:center;padding:2rem">No orders yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($recentOrders as $o): ?>
        <tr>
            <td><strong>#<?= (int)$o['order_id'] ?></strong></td>
            <td><?= e($o['full_name']) ?></td>
            <td><?= e(date('M j, Y g:i A', strtotime($o['created_at']))) ?></td>
            <td><?= e(format_price($o['total_amount'])) ?></td>
            <td><span class="badge <?= $o['order_status'] === 'pending' ? 'badge-warn' : ($o['order_status'] === 'cancelled' ? 'badge-out' : 'badge-ok') ?>"><?= e($label($o['order_status'])) ?></span></td>
            <td><?= e($label($o['payment_status'])) ?></td>
            <td><?= e($label($o['shipping_status'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php';
