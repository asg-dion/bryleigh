<?php // OWNER: Member 2
require __DIR__.'/../includes/auth.php'; require_login(); if ($_SERVER['REQUEST_METHOD'] === 'POST') { /* TODO: validate posted quantities and update cart. */ } redirect('customer/cart.php');

