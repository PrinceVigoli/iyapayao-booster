<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_admin();

$db         = get_db();
$page_title = 'All Orders';
$active_nav = 'admin_orders';

// Filters
$status_filter = trim($_GET['status'] ?? '');
$user_filter   = trim($_GET['user']   ?? '');
$date_from     = trim($_GET['date_from'] ?? '');
$date_to       = trim($_GET['date_to']   ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));

$where   = '1=1';
$params  = [];

if ($status_filter !== '') {
    $where    .= ' AND o.status = ?';
    $params[]  = $status_filter;
}
if ($user_filter !== '') {
    $where    .= ' AND (u.username LIKE ? OR u.email LIKE ?)';
    $params[]  = '%' . $user_filter . '%';
    $params[]  = '%' . $user_filter . '%';
}
if ($date_from !== '') {
    $where    .= ' AND DATE(o.created_at) >= ?';
    $params[]  = $date_from;
}
if ($date_to !== '') {
    $where    .= ' AND DATE(o.created_at) <= ?';
    $params[]  = $date_to;
}

$result      = paginate(
    "SELECT o.*, u.username, s.name AS service_name
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     LEFT JOIN services s ON s.id = o.service_id
     WHERE $where
     ORDER BY o.created_at DESC",
    $params,
    $page,
    25
);
$orders        = $result['rows'];
$total_pages   = $result['pages'];
$current_page  = $result['page'];

require_once __DIR__ . '/../includes/header.php';
?>
<?php render_flash(); ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">All Orders (<?= $result['total'] ?>)</span>
    </div>

    <!-- Filters -->
    <form method="GET" class="filter-bar">
        <input type="text" name="user" class="form-control" placeholder="Search user…" value="<?= e($user_filter) ?>">
        <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <?php foreach (['Pending','Processing','In progress','Completed','Partial','Canceled'] as $st): ?>
                <option value="<?= e($st) ?>" <?= ($status_filter === $st) ? 'selected' : '' ?>><?= e($st) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" class="form-control" value="<?= e($date_from) ?>" placeholder="From">
        <input type="date" name="date_to"   class="form-control" value="<?= e($date_to) ?>"   placeholder="To">
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <a href="<?= e(base_url('admin/orders.php')) ?>" class="btn btn-sm">Clear</a>
    </form>

    <?php if (empty($orders)): ?>
        <p class="text-muted text-center" style="padding:20px 0">No orders found.</p>
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
                    <th>Remains</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= e($order['id']) ?></td>
                    <td><?= e($order['username'] ?? '—') ?></td>
                    <td style="max-width:160px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis"
                        title="<?= e($order['service_name'] ?? '') ?>">
                        <?= e($order['service_name'] ?? '—') ?>
                    </td>
                    <td>
                        <a href="<?= e($order['link']) ?>" target="_blank" rel="noopener noreferrer"
                           class="truncate" style="display:inline-block;max-width:140px"
                           title="<?= e($order['link']) ?>">
                            <?= e($order['link']) ?>
                        </a>
                    </td>
                    <td><?= e(number_format($order['quantity'])) ?></td>
                    <td><?= e(format_currency($order['charge'])) ?></td>
                    <td><?= e(number_format($order['remains'])) ?></td>
                    <td><span class="badge <?= e(order_status_class($order['status'])) ?>"><?= e($order['status']) ?></span></td>
                    <td><?= e(date('M j, Y', strtotime($order['created_at']))) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php
            $q = http_build_query([
                'page' => $i, 'status' => $status_filter,
                'user' => $user_filter, 'date_from' => $date_from, 'date_to' => $date_to,
            ]);
            ?>
            <?php if ($i === $current_page): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="?<?= e($q) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
