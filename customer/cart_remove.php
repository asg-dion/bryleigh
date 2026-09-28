<?php // OWNER: Member 2
require __DIR__.'/../includes/auth.php'; require_login(); if ($_SERVER['REQUEST_METHOD'] === 'POST') { /* TODO: validate product id and remove it from cart. */ } redirect('customer/cart.php');

