<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_login();

$user       = current_user();
$db         = get_db();
$page_title = 'Dashboard';
$active_nav = 'dashboard';

// Stats
$stmt = $db->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
$stmt->execute([$user['id']]);
$total_orders = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'Pending'");
$stmt->execute([$user['id']]);
$pending_orders = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'Completed'");
$stmt->execute([$user['id']]);
$completed_orders = (int)$stmt->fetchColumn();

// Recent orders
$stmt = $db->prepare(
    'SELECT o.*, s.name AS service_name
     FROM orders o
     LEFT JOIN services s ON s.id = o.service_id
     WHERE o.user_id = ?
     ORDER BY o.created_at DESC
     LIMIT 5'
);
$stmt->execute([$user['id']]);
$recent_orders = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<?php render_flash(); ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div>
            <div class="stat-value"><?= e(format_currency($user['balance'])) ?></div>
            <div class="stat-label">Current Balance</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📦</div>
        <div>
            <div class="stat-value"><?= $total_orders ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⏳</div>
        <div>
            <div class="stat-value"><?= $pending_orders ?></div>
            <div class="stat-label">Pending Orders</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div>
            <div class="stat-value"><?= $completed_orders ?></div>
            <div class="stat-label">Completed Orders</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Recent Orders</span>
        <a href="<?= e(base_url('user/orders.php')) ?>" class="btn btn-sm btn-outline">View All</a>
    </div>
    <?php if (empty($recent_orders)): ?>
        <p class="text-muted text-center" style="padding:20px 0">No orders yet.
            <a href="<?= e(base_url('user/new-order.php')) ?>">Place your first order →</a>
        </p>
    <?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
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
                    <td><?= e($order['service_name'] ?? '—') ?></td>
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
