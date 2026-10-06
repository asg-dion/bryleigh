<?php // OWNER: Member 2

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('customer/cart.php');
}

$productId = (int)($_POST['product_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 0);

if ($productId <= 0 || $quantity <= 0) {
    flash_set('error', 'Invalid product or quantity.');
    redirect('customer/shop.php');
}

$stmt = $pdo->prepare("
    SELECT product_id, title, stock, status
    FROM products
    WHERE product_id = ?
");

$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product || $product['status'] !== 'active') {
    flash_set('error', 'Product is not available.');
    redirect('customer/shop.php');
}

$stock = (int)$product['stock'];

if ($stock <= 0) {
    flash_set('error', 'This product is out of stock.');
    redirect('customer/shop.php');
}

$currentQuantity = (int)($_SESSION['cart'][$productId] ?? 0);
$newQuantity = $currentQuantity + $quantity;

if ($newQuantity > $stock) {
    $newQuantity = $stock;
}

$_SESSION['cart'][$productId] = $newQuantity;

flash_set(
    'success',
    $product['title'] . ' has been added to your cart.'
);

redirect('customer/cart.php');