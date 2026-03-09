<?php
declare(strict_types=1);
/**
 * Common HTML header / navigation.
 *
 * Expected variables provided by the including file:
 *   $page_title  (string)  – page <title> suffix
 *   $active_nav  (string)  – active nav item key
 */
if (!isset($page_title)) { $page_title = 'Iyapayao Booster'; }

$site_name  = get_setting('site_name', 'Iyapayao Booster');
$user       = current_user();
$is_admin   = ($user['role'] ?? '') === 'admin';
$base_path  = base_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($page_title) ?> — <?= e($site_name) ?></title>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
</head>
<body>

<?php if ($user): ?>
<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="layout">
    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon">⚡</span>
            <span class="brand-name"><?= e($site_name) ?></span>
        </div>
        <nav class="sidebar-nav">
            <?php if ($is_admin): ?>
            <div class="nav-section-label">Admin</div>
            <a href="<?= e(base_url('admin/index.php')) ?>"
               class="nav-link <?= ($active_nav ?? '') === 'admin_dashboard' ? 'active' : '' ?>">
                <span class="nav-icon">📊</span> Dashboard
            </a>
            <a href="<?= e(base_url('admin/services.php')) ?>"
               class="nav-link <?= ($active_nav ?? '') === 'admin_services' ? 'active' : '' ?>">
                <span class="nav-icon">⚙️</span> Services
            </a>
            <a href="<?= e(base_url('admin/orders.php')) ?>"
               class="nav-link <?= ($active_nav ?? '') === 'admin_orders' ? 'active' : '' ?>">
                <span class="nav-icon">📋</span> All Orders
            </a>
            <a href="<?= e(base_url('admin/users.php')) ?>"
               class="nav-link <?= ($active_nav ?? '') === 'admin_users' ? 'active' : '' ?>">
                <span class="nav-icon">👥</span> Users
            </a>
            <a href="<?= e(base_url('admin/settings.php')) ?>"
               class="nav-link <?= ($active_nav ?? '') === 'admin_settings' ? 'active' : '' ?>">
                <span class="nav-icon">🔧</span> Settings
            </a>
            <div class="nav-divider"></div>
            <div class="nav-section-label">User Panel</div>
            <?php endif; ?>

            <a href="<?= e(base_url('user/dashboard.php')) ?>"
               class="nav-link <?= ($active_nav ?? '') === 'dashboard' ? 'active' : '' ?>">
                <span class="nav-icon">🏠</span> Dashboard
            </a>
            <a href="<?= e(base_url('user/new-order.php')) ?>"
               class="nav-link <?= ($active_nav ?? '') === 'new_order' ? 'active' : '' ?>">
                <span class="nav-icon">➕</span> New Order
            </a>
            <a href="<?= e(base_url('user/orders.php')) ?>"
               class="nav-link <?= ($active_nav ?? '') === 'orders' ? 'active' : '' ?>">
                <span class="nav-icon">📦</span> My Orders
            </a>
            <a href="<?= e(base_url('user/add-funds.php')) ?>"
               class="nav-link <?= ($active_nav ?? '') === 'add_funds' ? 'active' : '' ?>">
                <span class="nav-icon">💳</span> Add Funds
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= e($user['username']) ?></div>
                    <div class="user-balance"><?= e(format_currency($user['balance'])) ?></div>
                </div>
            </div>
            <a href="<?= e(base_url('auth/logout.php')) ?>" class="btn-logout">Logout</a>
        </div>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">
        <!-- Top bar -->
        <header class="topbar">
            <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">
                <span></span><span></span><span></span>
            </button>
            <div class="topbar-title"><?= e($page_title) ?></div>
            <div class="topbar-actions">
                <span class="balance-chip">💰 <?= e(format_currency($user['balance'])) ?></span>
            </div>
        </header>

        <!-- Page content -->
        <div class="page-content">
<?php else: ?>
<div class="auth-wrapper">
<?php endif; ?>
