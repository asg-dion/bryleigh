<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("This cleanup script can only be run from the command line.\n");
}

require __DIR__ . '/../includes/db.php';

$ordersStmt = $pdo->query("
    SELECT o.order_id, u.email, o.total_amount, o.created_at
    FROM orders o
    JOIN users u ON u.user_id = o.user_id
    WHERE u.role = 'admin'
    ORDER BY o.order_id
");
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$orders) {
    fwrite(STDOUT, "No admin-placed orders found\n");
    exit(0);
}

fwrite(STDOUT, "Admin-placed orders scheduled for deletion:\n");
foreach ($orders as $order) {
    fwrite(
        STDOUT,
        sprintf(
            "Order #%d | %s | %s | %s\n",
            (int)$order['order_id'],
            $order['email'],
            $order['total_amount'],
            $order['created_at']
        )
    );
}

$orderIds = array_map('intval', array_column($orders, 'order_id'));
$placeholders = implode(', ', array_fill(0, count($orderIds), '?'));

try {
    $pdo->beginTransaction();

    $itemsStmt = $pdo->prepare(
        "DELETE FROM order_items WHERE order_id IN ($placeholders)"
    );
    $itemsStmt->execute($orderIds);
    $deletedItems = $itemsStmt->rowCount();

    $deleteOrdersStmt = $pdo->prepare(
        "DELETE FROM orders WHERE order_id IN ($placeholders)"
    );
    $deleteOrdersStmt->execute($orderIds);
    $deletedOrders = $deleteOrdersStmt->rowCount();

    $pdo->commit();

    fwrite(
        STDOUT,
        sprintf(
            "Deleted %d order(s) and %d order_item row(s).\n",
            $deletedOrders,
            $deletedItems
        )
    );
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Cleanup failed; transaction rolled back: {$error->getMessage()}\n");
    exit(1);
}
