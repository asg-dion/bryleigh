<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
// Session contract: $_SESSION['user_id'], $_SESSION['role'], $_SESSION['full_name'],
// and $_SESSION['cart'] = [product_id => quantity].
function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
function is_admin(): bool { return ($_SESSION['role'] ?? '') === 'admin'; }
function current_user_id(): ?int { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; }
function require_login(): void { if (!is_logged_in()) { flash_set('error', 'Please log in to continue.'); redirect('customer/login.php'); } }
function require_admin(): void { if (!is_admin()) { flash_set('error', 'Administrator access is required.'); redirect('admin/login.php'); } }

