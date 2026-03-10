<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

start_session();
require_admin();

$db         = get_db();
$page_title = 'Services';
$active_nav = 'admin_services';
$messages   = [];

// ----------------------------------------------------------------
// Sync services from API
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sync') {
    verify_csrf();

    $api    = get_api();
    $result = $api->services();

    if (isset($result['error'])) {
        set_flash('error', 'API error: ' . $result['error']);
    } else {
        $markup  = (float)get_setting('price_markup_percent', '20');
        $synced  = 0;

        $stmt = $db->prepare(
            'INSERT INTO services
             (service_id, name, type, category, rate, price, min, max, description, dripfeed, refill, cancel, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'active\', NOW(), NOW())
             ON DUPLICATE KEY UPDATE
               name        = VALUES(name),
               type        = VALUES(type),
               category    = VALUES(category),
               rate        = VALUES(rate),
               price       = VALUES(price),
               min         = VALUES(min),
               max         = VALUES(max),
               description = VALUES(description),
               dripfeed    = VALUES(dripfeed),
               refill      = VALUES(refill),
               cancel      = VALUES(cancel),
               updated_at  = NOW()'
        );

        foreach ($result as $svc) {
            if (!isset($svc['service'])) continue;
            $rate  = (float)($svc['rate'] ?? 0);
            $price = apply_markup($rate, $markup);
            $stmt->execute([
                (int)$svc['service'],
                $svc['name']     ?? '',
                $svc['type']     ?? '',
                $svc['category'] ?? '',
                $rate,
                $price,
                (int)($svc['min']  ?? 0),
                (int)($svc['max']  ?? 0),
                $svc['desc']     ?? '',
                (int)($svc['dripfeed'] ?? 0),
                (int)($svc['refill']   ?? 0),
                (int)($svc['cancel']   ?? 0),
            ]);
            $synced++;
        }

        set_flash('success', $synced . ' services synced successfully.');
    }
    redirect(base_url('admin/services.php'));
}

// ----------------------------------------------------------------
// Toggle service status
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    verify_csrf();
    $id     = (int)($_POST['service_db_id'] ?? 0);
    $status = $_POST['new_status'] ?? 'active';
    if (!in_array($status, ['active', 'disabled'], true)) $status = 'active';
    $db->prepare('UPDATE services SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $id]);
    set_flash('success', 'Service updated.');
    redirect(base_url('admin/services.php'));
}

// ----------------------------------------------------------------
// Update service price
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_price') {
    verify_csrf();
    $id    = (int)($_POST['service_db_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    if ($price < 0) $price = 0;
    $db->prepare('UPDATE services SET price = ?, updated_at = NOW() WHERE id = ?')->execute([$price, $id]);
    set_flash('success', 'Price updated.');
    redirect(base_url('admin/services.php'));
}

// ----------------------------------------------------------------
// List services (paginated, with search/filter)
// ----------------------------------------------------------------
$page    = max(1, (int)($_GET['page'] ?? 1));
$search  = trim($_GET['q'] ?? '');
$cat     = trim($_GET['category'] ?? '');
$status  = trim($_GET['status']   ?? '');

$where   = '1=1';
$params  = [];

if ($search !== '') {
    $where    .= ' AND (name LIKE ? OR category LIKE ?)';
    $params[]  = '%' . $search . '%';
    $params[]  = '%' . $search . '%';
}
if ($cat !== '') {
    $where    .= ' AND category = ?';
    $params[]  = $cat;
}
if ($status !== '') {
    $where    .= ' AND status = ?';
    $params[]  = $status;
}

$result    = paginate("SELECT * FROM services WHERE $where ORDER BY category, name", $params, $page, 25);
$services  = $result['rows'];
$total_pages = $result['pages'];
$current_page = $result['page'];

// All categories for the filter
$categories = $db->query('SELECT DISTINCT category FROM services ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../includes/header.php';
?>
<?php render_flash(); ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Services (<?= $result['total'] ?>)</span>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="sync">
            <button type="submit" class="btn btn-primary btn-sm"
                    data-confirm="Sync all services from the API? This may take a moment.">
                🔄 Sync Services
            </button>
        </form>
    </div>

    <!-- Filter bar -->
    <form method="GET" class="filter-bar">
        <input type="text" name="q" class="form-control" placeholder="Search…" value="<?= e($search) ?>">
        <select name="category" class="form-select">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= e($c) ?>" <?= ($cat === $c) ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <option value="active"   <?= ($status === 'active')   ? 'selected' : '' ?>>Active</option>
            <option value="disabled" <?= ($status === 'disabled') ? 'selected' : '' ?>>Disabled</option>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        <a href="<?= e(base_url('admin/services.php')) ?>" class="btn btn-sm">Clear</a>
    </form>

    <?php if (empty($services)): ?>
        <p class="text-muted text-center" style="padding:20px 0">
            No services found. Click "Sync Services" to import from the API.
        </p>
    <?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Provider Rate</th>
                    <th>Selling Price</th>
                    <th>Min</th>
                    <th>Max</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $svc): ?>
                <tr>
                    <td><?= e($svc['service_id']) ?></td>
                    <td style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis"
                        title="<?= e($svc['name']) ?>">
                        <?= e($svc['name']) ?>
                    </td>
                    <td><?= e($svc['category']) ?></td>
                    <td><?= e(format_rate($svc['rate'])) ?></td>
                    <td>
                        <!-- Inline price editor -->
                        <form method="POST" style="display:flex; gap:6px; align-items:center; min-width:140px">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action"         value="update_price">
                            <input type="hidden" name="service_db_id"  value="<?= e($svc['id']) ?>">
                            <input type="number" name="price" class="form-control" step="0.00001" min="0"
                                   value="<?= e(number_format((float)$svc['price'], 5, '.', '')) ?>"
                                   style="width:100px; padding:5px 8px; font-size:.8rem">
                            <button type="submit" class="btn btn-xs btn-outline">Save</button>
                        </form>
                    </td>
                    <td><?= e(number_format($svc['min'])) ?></td>
                    <td><?= e(number_format($svc['max'])) ?></td>
                    <td>
                        <span class="badge <?= $svc['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>">
                            <?= e(ucfirst($svc['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action"         value="toggle">
                            <input type="hidden" name="service_db_id"  value="<?= e($svc['id']) ?>">
                            <input type="hidden" name="new_status"
                                   value="<?= $svc['status'] === 'active' ? 'disabled' : 'active' ?>">
                            <button type="submit" class="btn btn-xs <?= $svc['status'] === 'active' ? 'btn-secondary' : 'btn-success' ?>">
                                <?= $svc['status'] === 'active' ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>
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
            $q = http_build_query(['page' => $i, 'q' => $search, 'category' => $cat, 'status' => $status]);
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
