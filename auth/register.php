<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();

if (is_logged_in()) {
    redirect(base_url('user/dashboard.php'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']       ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Validate
    if ($username === '' || $email === '' || $password === '') {
        $errors[] = 'All fields are required.';
    }
    if (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = 'Username must be between 3 and 50 characters.';
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username may only contain letters, numbers, and underscores.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $db = get_db();

        // Check uniqueness
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Username or email is already taken.';
        }
    }

    if (empty($errors)) {
        $db          = get_db();
        $hashed      = password_hash($password, PASSWORD_BCRYPT);
        $api_key     = generate_api_key();

        $stmt = $db->prepare(
            'INSERT INTO users (username, email, password, api_key, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$username, $email, $hashed, $api_key]);

        set_flash('success', 'Account created! Please sign in.');
        redirect(base_url('auth/login.php'));
    }
}

$site_name = get_setting('site_name', 'Iyapayao Booster');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — <?= e($site_name) ?></title>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand-icon">⚡</span>
            <h1><?= e($site_name) ?></h1>
            <p>Create a new account</p>
        </div>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="" novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control"
                       value="<?= e($_POST['username'] ?? '') ?>" required autofocus maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= e($_POST['email'] ?? '') ?>" required maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       required minlength="8" autocomplete="new-password">
                <div class="form-text">Minimum 8 characters.</div>
            </div>
            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                       required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary w-100">Create Account</button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="<?= e(base_url('auth/login.php')) ?>">Sign In</a>
        </div>
    </div>
</div>
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
</body>
</html>
