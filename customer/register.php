<?php
$page_title = 'Register'; require __DIR__ . '/../includes/header.php';
if (is_logged_in()) redirect('index.php');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? ''); $email = trim($_POST['email'] ?? ''); $password = $_POST['password'] ?? ''; $confirm = $_POST['confirm_password'] ?? '';
    if ($name === '' || strlen($name) > 100) $errors[] = 'Enter a valid full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) $errors[] = 'Enter a valid email.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (!$errors) { $check = $pdo->prepare('SELECT user_id FROM users WHERE email = ?'); $check->execute([$email]); if ($check->fetch()) $errors[] = 'That email is already registered.'; }
    if (!$errors) { $stmt = $pdo->prepare("INSERT INTO users (full_name,email,password_hash,role) VALUES (?,?,?,'customer')"); $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]); session_regenerate_id(true); $_SESSION['user_id'] = (int)$pdo->lastInsertId(); $_SESSION['role'] = 'customer'; $_SESSION['full_name'] = $name; $_SESSION['cart'] ??= []; flash_set('success', 'Welcome to Bryleigh!'); redirect('index.php'); }
}
?><div class="auth-card"><h1>Register</h1><?php foreach ($errors as $error): ?><p class="error"><?= e($error) ?></p><?php endforeach; ?><form method="post"><div class="form-group"><label>Full name<input name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>"></label></div><div class="form-group"><label>Email<input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label></div><div class="form-group"><label>Password<input type="password" name="password" required></label></div><div class="form-group"><label>Confirm password<input type="password" name="confirm_password" required></label></div><button class="btn btn-dark">Create account</button></form></div><?php require __DIR__ . '/../includes/footer.php';

