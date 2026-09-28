<?php
require_once __DIR__ . '/db.php'; require_once __DIR__ . '/helpers.php'; require_once __DIR__ . '/auth.php'; require_admin();
$page_title = $page_title ?? 'Admin'; $flash = flash_get();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($page_title) ?> | Bryleigh Admin</title><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"></head><body><div class="admin-layout"><aside class="sidebar"><a class="logo" href="<?= BASE_URL ?>/admin/dashboard.php">BRYLEIGH</a><p>ADMIN</p><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a><a href="<?= BASE_URL ?>/admin/products.php">Product Management</a><a href="<?= BASE_URL ?>/admin/orders.php">Order Management</a><a href="<?= BASE_URL ?>/admin/logout.php">Logout</a></aside><main class="container admin-main"><?php if ($flash): ?><div class="flash <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>

