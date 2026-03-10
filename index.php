<?php
declare(strict_types=1);

/**
 * Landing page — redirect to login or dashboard.
 */
$config = __DIR__ . '/config/database.php';
if (!file_exists($config)) {
    header('Location: install/install.php');
    exit;
}

require_once $config;
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

start_session();

if (is_logged_in()) {
    redirect(base_url($_SESSION['role'] === 'admin' ? 'admin/index.php' : 'user/dashboard.php'));
}

redirect(base_url('auth/login.php'));
