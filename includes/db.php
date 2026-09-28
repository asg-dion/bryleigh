<?php
$configPath = __DIR__ . '/../config/db.local.php';
if (!is_file($configPath)) {
    die('Database configuration is missing. Copy config/db.sample.php to config/db.local.php first.');
}
$config = require $configPath;
$dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die('Database connection failed. Check config/db.local.php and make sure MySQL is running.');
}

