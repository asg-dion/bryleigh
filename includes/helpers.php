<?php
if (!defined('BASE_URL')) define('BASE_URL', '/bryleigh-main');
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . (str_starts_with($path, 'http') ? $path : BASE_URL . '/' . ltrim($path, '/'))); exit; }
function flash_set(string $type, string $msg): void { $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; }
function flash_get(): ?array { $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $flash; }
function format_price($n): string { return '₱' . number_format((float)$n, 2); }
function cart_count(): int { return array_sum(array_map('intval', $_SESSION['cart'] ?? [])); }
function product_image(?string $path): string { return $path ? BASE_URL . '/uploads/products/' . rawurlencode(basename($path)) : BASE_URL . '/assets/images/product-placeholder.svg'; }

