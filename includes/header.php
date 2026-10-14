<?php
require_once __DIR__ . '/db.php'; require_once __DIR__ . '/helpers.php'; require_once __DIR__ . '/auth.php';
$page_title = $page_title ?? 'Bryleigh'; $flash = flash_get();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($page_title) ?> | Bryleigh</title><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/catalog.css"><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/orders.css"></head><body>
<header class="site-header">
    <div class="announcement-bar">Preloved finds, free pickup for orders over ₱1,500</div>
    <div class="header-inner">
        <a class="logo" href="<?= BASE_URL ?>/index.php">BRYLEIGH</a>
        <input class="menu-toggle" type="checkbox" id="site-menu-toggle">
        <label class="menu-toggle-button" for="site-menu-toggle" aria-label="Toggle navigation">
            <span></span><span></span><span></span>
        </label>
        <nav class="site-nav" aria-label="Main navigation">
            <div class="nav-links">
                <a href="<?= BASE_URL ?>/index.php">Home</a>
                <a href="<?= BASE_URL ?>/customer/shop.php">Products</a>
            </div>
            <div class="header-actions">
                <?php if (!is_logged_in()): ?>
                    <form class="search" method="get" action="<?= BASE_URL ?>/customer/shop.php">
                        <input name="q" placeholder="Search" value="<?= e($_GET['q'] ?? '') ?>">
                        <button class="search-button" type="submit" aria-label="Search">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 5 5"></path></svg>
                        </button>
                    </form>
                    <a href="<?= BASE_URL ?>/customer/login.php">Login</a>
                    <a href="<?= BASE_URL ?>/customer/register.php">Register</a>
                <?php elseif (is_admin()): ?>
                    <small class="account-indicator">Logged in as: <?= e($_SESSION['full_name'] ?? 'Admin') ?> (admin)</small>
                    <a href="<?= BASE_URL ?>/admin/dashboard.php">Go to Admin Dashboard</a>
                    <a href="<?= BASE_URL ?>/admin/logout.php">Logout</a>
                <?php elseif (($_SESSION['role'] ?? '') === 'customer'): ?>
                    <small class="account-indicator">Logged in as: <?= e($_SESSION['full_name'] ?? 'Customer') ?> (customer)</small>
                    <form class="search" method="get" action="<?= BASE_URL ?>/customer/shop.php">
                        <input name="q" placeholder="Search" value="<?= e($_GET['q'] ?? '') ?>">
                        <button class="search-button" type="submit" aria-label="Search">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"></circle><path d="m16 16 5 5"></path></svg>
                        </button>
                    </form>
                    <a class="cart-link" href="<?= BASE_URL ?>/customer/cart.php">Cart <span class="cart-count"><?= cart_count() ?></span></a>
                    <a href="<?= BASE_URL ?>/customer/order_history.php">Order History</a>
                    <a href="<?= BASE_URL ?>/customer/logout.php">Logout</a>
                <?php else: ?>
                    <small class="account-indicator">Logged in as: <?= e($_SESSION['full_name'] ?? 'User') ?> (<?= e($_SESSION['role'] ?? 'unknown') ?>)</small>
                    <a href="<?= BASE_URL ?>/customer/logout.php">Logout</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>
<main class="container"><?php if ($flash): ?><div class="flash <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
