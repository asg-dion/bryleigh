<?php // OWNER: Member 2
require __DIR__.'/../includes/auth.php'; require_admin(); if ($_SERVER['REQUEST_METHOD'] === 'POST') { /* TODO: validate order status fields and update the order. */ } redirect('admin/orders.php');

