<?php // OWNER: Member 2

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('customer/cart.php');
}

$productId = (int)($_POST['product_id'] ?? 0);

if ($productId <= 0) {
    flash_set('error', 'Invalid product.');
    redirect('customer/cart.php');
}

if (isset($_SESSION['cart'][$productId])) {
    unset($_SESSION['cart'][$productId]);
    flash_set('success', 'Item removed from your cart.');
} else {
    flash_set('error', 'Item was not found in your cart.');
}

redirect('customer/cart.php');