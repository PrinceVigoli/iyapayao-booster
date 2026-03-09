<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();

// Redirect already-logged-in users
if (is_logged_in()) {
    redirect(base_url($_SESSION['role'] === 'admin' ? 'admin/index.php' : 'user/dashboard.php'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $login    = trim($_POST['login']    ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $errors[] = 'Username/email and password are required.';
    } else {
        $db   = get_db();
        // Allow login with username OR email
        $stmt = $db->prepare(
            'SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1'
        );
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid username/email or password.';
        } elseif ($user['status'] === 'banned') {
            $errors[] = 'Your account has been banned. Please contact support.';
        } else {
            login_user($user);
            set_flash('success', 'Welcome back, ' . $user['username'] . '!');
            redirect(base_url($user['role'] === 'admin' ? 'admin/index.php' : 'user/dashboard.php'));
        }
    }
}

$page_title = 'Login';
$site_name  = get_setting('site_name', 'Iyapayao Booster');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= e($site_name) ?></title>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="brand-icon">⚡</span>
            <h1><?= e($site_name) ?></h1>
            <p>Sign in to your account</p>
        </div>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="" novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="login">Username or Email</label>
                <input type="text" id="login" name="login" class="form-control"
                       value="<?= e($_POST['login'] ?? '') ?>" required autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary w-100">Sign In</button>
        </form>

        <div class="auth-footer">
            Don't have an account? <a href="<?= e(base_url('auth/register.php')) ?>">Register</a>
        </div>
    </div>
</div>
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
</body>
</html>
