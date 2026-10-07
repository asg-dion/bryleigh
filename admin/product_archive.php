<?php // OWNER: Member 1
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// Archiving is a state change, so it is POST-only and CSRF-protected.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    if (empty($_SESSION['catalog_csrf']) || !hash_equals($_SESSION['catalog_csrf'], $token)) {
        flash_set('error', 'Your session expired. Please try again.');
        redirect('admin/products.php');
    }

    $id = filter_var($_POST['product_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        flash_set('error', 'Invalid product.');
        redirect('admin/products.php');
    }

    $stmt = $pdo->prepare('SELECT title, status FROM products WHERE product_id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product) {
        flash_set('error', 'Product not found.');
    } elseif ($product['status'] === 'archived') {
        flash_set('notice', '“' . $product['title'] . '” is already archived.');
    } else {
        // Soft delete only: order_items still references products, so rows are never removed.
        $pdo->prepare("UPDATE products SET status = 'archived' WHERE product_id = ?")->execute([$id]);
        flash_set('success', '“' . $product['title'] . '” was archived.');
    }
}

redirect('admin/products.php');
