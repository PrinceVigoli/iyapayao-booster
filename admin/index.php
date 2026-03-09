<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

start_session();
require_admin();

$db         = get_db();
$page_title = 'Admin Dashboard';
$active_nav = 'admin_dashboard';

// Stats
$total_users   = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$total_orders  = (int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$pending_orders= (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'")->fetchColumn();

$revenue_row   = $db->query('SELECT COALESCE(SUM(charge),0) FROM orders')->fetchColumn();
$total_revenue = (float)$revenue_row;

// API balance
$api_balance = null;
try {
    $api         = get_api();
    $bal_result  = $api->balance();
    if (!isset($bal_result['error'])) {
        $api_balance = $bal_result;
    }
} catch (Throwable $e) {
    // silently ignore
}

// Recent 10 orders
$recent_orders = $db->query(
    'SELECT o.*, u.username, s.name AS service_name
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     LEFT JOIN services s ON s.id = o.service_id
     ORDER BY o.created_at DESC
     LIMIT 10'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<?php render_flash(); ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div>
            <div class="stat-value"><?= e(number_format($total_users)) ?></div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📦</div>
        <div>
            <div class="stat-value"><?= e(number_format($total_orders)) ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⏳</div>
        <div>
            <div class="stat-value"><?= e(number_format($pending_orders)) ?></div>
            <div class="stat-label">Pending Orders</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💵</div>
        <div>
            <div class="stat-value"><?= e(format_currency($total_revenue)) ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>
    <?php if ($api_balance): ?>
    <div class="stat-card">
        <div class="stat-icon">🏦</div>
        <div>
            <div class="stat-value"><?= e($api_balance['currency'] ?? '') ?> <?= e(number_format((float)($api_balance['balance'] ?? 0), 2)) ?></div>
            <div class="stat-label">API Balance</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Recent Orders</span>
        <a href="<?= e(base_url('admin/orders.php')) ?>" class="btn btn-sm btn-outline">View All</a>
    </div>
    <?php if (empty($recent_orders)): ?>
        <p class="text-muted text-center" style="padding:20px 0">No orders yet.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Service</th>
                    <th>Link</th>
                    <th>Qty</th>
                    <th>Charge</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_orders as $order): ?>
                <tr>
                    <td><?= e($order['id']) ?></td>
                    <td><?= e($order['username'] ?? '—') ?></td>
                    <td style="max-width:160px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis">
                        <?= e($order['service_name'] ?? '—') ?>
                    </td>
                    <td><span class="truncate"><?= e($order['link']) ?></span></td>
                    <td><?= e(number_format($order['quantity'])) ?></td>
                    <td><?= e(format_currency($order['charge'])) ?></td>
                    <td><span class="badge <?= e(order_status_class($order['status'])) ?>"><?= e($order['status']) ?></span></td>
                    <td><?= e(date('M j, Y', strtotime($order['created_at']))) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
