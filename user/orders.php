<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

start_session();
require_login();

$user = current_user();
$db   = get_db();

// ----------------------------------------------------------------
// Handle AJAX actions (refill / cancel)
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();

    $order_id = (int)($_POST['order_id'] ?? 0);

    // Fetch the order & ensure it belongs to the current user
    $stmt = $db->prepare('SELECT o.*, s.refill AS svc_refill, s.cancel AS svc_cancel
                          FROM orders o
                          LEFT JOIN services s ON s.id = o.service_id
                          WHERE o.id = ? AND o.user_id = ?');
    $stmt->execute([$order_id, $user['id']]);
    $order = $stmt->fetch();

    if (!$order) {
        set_flash('error', 'Order not found.');
        redirect(base_url('user/orders.php'));
    }

    $api = get_api();

    if ($_POST['action'] === 'refill' && $order['svc_refill']) {
        $result = $api->refill((int)$order['external_order_id']);
        if (isset($result['error'])) {
            set_flash('error', 'Refill failed: ' . $result['error']);
        } else {
            set_flash('success', 'Refill request submitted for order #' . $order_id);
        }
    } elseif ($_POST['action'] === 'cancel' && $order['svc_cancel']) {
        $result = $api->cancel([(int)$order['external_order_id']]);
        if (isset($result['error'])) {
            set_flash('error', 'Cancel failed: ' . $result['error']);
        } else {
            $db->prepare("UPDATE orders SET status = 'Canceled', updated_at = NOW() WHERE id = ?")
               ->execute([$order_id]);
            set_flash('success', 'Order #' . $order_id . ' cancel request submitted.');
        }
    }
    redirect(base_url('user/orders.php'));
}

// ----------------------------------------------------------------
// Sync status of pending/in-progress orders (batch)
// ----------------------------------------------------------------
$pending_stmt = $db->prepare(
    "SELECT id, external_order_id FROM orders
     WHERE user_id = ? AND external_order_id IS NOT NULL
       AND status NOT IN ('Completed','Canceled','Partial')
     LIMIT 100"
);
$pending_stmt->execute([$user['id']]);
$pending = $pending_stmt->fetchAll();

if (!empty($pending)) {
    $api      = get_api();
    $id_map   = array_column($pending, 'id', 'external_order_id');
    $ext_ids  = array_keys($id_map);
    $statuses = count($ext_ids) === 1
        ? [$ext_ids[0] => $api->orderStatus((int)$ext_ids[0])]
        : $api->multiOrderStatus($ext_ids);

    foreach ($statuses as $ext_id => $data) {
        if (!isset($id_map[$ext_id])) continue;
        if (isset($data['error']))    continue;
        $local_id = $id_map[$ext_id];
        $db->prepare(
            'UPDATE orders SET status = ?, start_count = ?, remains = ?, updated_at = NOW() WHERE id = ?'
        )->execute([
            $data['status']      ?? 'Pending',
            $data['start_count'] ?? 0,
            $data['remains']     ?? 0,
            $local_id,
        ]);
    }
}

// ----------------------------------------------------------------
// Paginated order list
// ----------------------------------------------------------------
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 20;
$status_filter = trim($_GET['status'] ?? '');

$where  = 'WHERE o.user_id = ?';
$params = [$user['id']];
if ($status_filter !== '') {
    $where   .= ' AND o.status = ?';
    $params[] = $status_filter;
}

$result     = paginate(
    "SELECT o.*, s.name AS service_name, s.refill AS svc_refill, s.cancel AS svc_cancel
     FROM orders o LEFT JOIN services s ON s.id = o.service_id $where
     ORDER BY o.created_at DESC",
    $params,
    $page,
    $per_page
);
$orders     = $result['rows'];
$total_pages= $result['pages'];
$current_page = $result['page'];

$page_title = 'My Orders';
$active_nav = 'orders';

require_once __DIR__ . '/../includes/header.php';
?>
<?php render_flash(); ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">My Orders (<?= $result['total'] ?>)</span>
        <a href="<?= e(base_url('user/new-order.php')) ?>" class="btn btn-sm btn-primary">+ New Order</a>
    </div>

    <!-- Filter bar -->
    <form method="GET" class="filter-bar">
        <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <?php foreach (['Pending','Processing','In progress','Completed','Partial','Canceled'] as $st): ?>
                <option value="<?= e($st) ?>" <?= ($status_filter === $st) ? 'selected' : '' ?>><?= e($st) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <?php if ($status_filter): ?>
            <a href="<?= e(base_url('user/orders.php')) ?>" class="btn btn-sm">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($orders)): ?>
        <p class="text-muted text-center" style="padding:20px 0">No orders found.</p>
    <?php else: ?>
    <div class="table-responsive" data-page="orders">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Service</th>
                    <th>Link</th>
                    <th>Qty</th>
                    <th>Charge</th>
                    <th>Start</th>
                    <th>Remains</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= e($order['id']) ?></td>
                    <td style="max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis"
                        title="<?= e($order['service_name'] ?? '') ?>">
                        <?= e($order['service_name'] ?? '—') ?>
                    </td>
                    <td>
                        <a href="<?= e($order['link']) ?>" target="_blank" rel="noopener noreferrer"
                           class="truncate" style="display:inline-block; max-width:160px"
                           title="<?= e($order['link']) ?>">
                            <?= e($order['link']) ?>
                        </a>
                    </td>
                    <td><?= e(number_format($order['quantity'])) ?></td>
                    <td><?= e(format_currency($order['charge'])) ?></td>
                    <td><?= e(number_format($order['start_count'])) ?></td>
                    <td><?= e(number_format($order['remains'])) ?></td>
                    <td><span class="badge <?= e(order_status_class($order['status'])) ?>"><?= e($order['status']) ?></span></td>
                    <td><?= e(date('M j, Y', strtotime($order['created_at']))) ?></td>
                    <td>
                        <?php if ($order['svc_refill'] && $order['external_order_id']): ?>
                        <form method="POST" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action"   value="refill">
                            <input type="hidden" name="order_id" value="<?= e($order['id']) ?>">
                            <button type="submit" class="btn btn-xs btn-warning"
                                    data-confirm="Request refill for order #<?= e($order['id']) ?>?">
                                Refill
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php if ($order['svc_cancel'] && !in_array($order['status'], ['Completed','Canceled'], true) && $order['external_order_id']): ?>
                        <form method="POST" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action"   value="cancel">
                            <input type="hidden" name="order_id" value="<?= e($order['id']) ?>">
                            <button type="submit" class="btn btn-xs btn-danger"
                                    data-confirm="Cancel order #<?= e($order['id']) ?>?">
                                Cancel
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
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
            $q = http_build_query(['page' => $i, 'status' => $status_filter]);
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
