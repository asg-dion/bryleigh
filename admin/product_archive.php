<?php // OWNER: Member 1
require __DIR__.'/../includes/auth.php'; require_admin(); if ($_SERVER['REQUEST_METHOD'] === 'POST') { /* TODO: validate product id and set status to archived. */ } redirect('admin/products.php');

