<?php // OWNER: Member 2

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('customer/cart.php');
}

$quantities = $_POST['quantity'] ?? [];

if (!is_array($quantities)) {
    flash_set('error', 'Invalid cart data.');
    redirect('customer/cart.php');
}

foreach ($quantities as $productId => $quantity) {

    $productId = (int)$productId;
    $quantity = (int)$quantity;

    if ($productId <= 0) {
        continue;
    }

    if ($quantity <= 0) {
        unset($_SESSION['cart'][$productId]);
        continue;
    }

    $stmt = $pdo->prepare("
        SELECT product_id, stock, status
        FROM products
        WHERE product_id = ?
    ");

    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product || $product['status'] !== 'active') {
        unset($_SESSION['cart'][$productId]);
        continue;
    }

    $stock = (int)$product['stock'];

    if ($stock <= 0) {
        unset($_SESSION['cart'][$productId]);
        continue;
    }

    $_SESSION['cart'][$productId] = min($quantity, $stock);
}

flash_set('success', 'Cart updated successfully.');

redirect('customer/cart.php');