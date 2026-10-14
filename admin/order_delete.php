<?php

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
if (!$orderId || $orderId < 1) {
    flash_set('error', 'Invalid order.');
    redirect('admin/orders.php');
}

try {
    $pdo->beginTransaction();

    $itemStmt = $pdo->prepare('DELETE FROM order_items WHERE order_id = ?');
    $itemStmt->execute([$orderId]);
    $deletedItems = $itemStmt->rowCount();

    $orderStmt = $pdo->prepare('DELETE FROM orders WHERE order_id = ?');
    $orderStmt->execute([$orderId]);

    if ($orderStmt->rowCount() !== 1) {
        throw new RuntimeException('Order not found.');
    }

    $pdo->commit();
    flash_set(
        'success',
        sprintf(
            'Order #%d was deleted with %d order item(s). Stock was not restored.',
            $orderId,
            $deletedItems
        )
    );
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Order deletion failed for order #' . $orderId . ': ' . $error->getMessage());
    flash_set('error', 'Unable to delete the order. Please try again.');
}

redirect('admin/orders.php');
