<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_admin();

$db         = get_db();
$page_title = 'Users';
$active_nav = 'admin_users';

// ----------------------------------------------------------------
// Handle actions
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($action === 'toggle_ban') {
        $stmt = $db->prepare('SELECT id, status FROM users WHERE id = ? AND role != ? LIMIT 1');
        $stmt->execute([$user_id, 'admin']);
        $target = $stmt->fetch();
        if ($target) {
            $new_status = $target['status'] === 'active' ? 'banned' : 'active';
            $db->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?')
               ->execute([$new_status, $user_id]);
            set_flash('success', 'User status updated.');
        }
    } elseif ($action === 'edit_balance') {
        $amount  = (float)($_POST['amount'] ?? 0);
        $op      = $_POST['operation'] ?? 'add';  // 'add' or 'subtract'
        $note    = trim($_POST['note'] ?? 'Admin balance adjustment');
        $note    = $note !== '' ? $note : 'Admin balance adjustment';

        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$user_id]);
        $target = $stmt->fetch();

        if ($target && $amount > 0) {
            $before = (float)$target['balance'];
            $after  = $op === 'subtract'
                ? max(0, $before - $amount)
                : $before + $amount;

            $db->beginTransaction();
            try {
                $db->prepare('UPDATE users SET balance = ?, updated_at = NOW() WHERE id = ?')
                   ->execute([$after, $user_id]);
                $db->prepare(
                    'INSERT INTO transactions
                     (user_id, type, amount, balance_before, balance_after, description, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())'
                )->execute([
                    $user_id, 'add_funds',
                    $op === 'subtract' ? -$amount : $amount,
                    $before, $after,
                    $note,
                ]);
                $db->commit();
                set_flash('success', 'Balance updated for user #' . $user_id);
            } catch (PDOException $e) {
                $db->rollBack();
                set_flash('error', 'Failed to update balance.');
            }
        } else {
            set_flash('error', 'Invalid amount or user.');
        }
    }
    redirect(base_url('admin/users.php'));
}

// ----------------------------------------------------------------
// List users
// ----------------------------------------------------------------
$page   = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['q'] ?? '');

$where   = '1=1';
$params  = [];
if ($search !== '') {
    $where    .= ' AND (username LIKE ? OR email LIKE ?)';
    $params[]  = '%' . $search . '%';
    $params[]  = '%' . $search . '%';
}

$result      = paginate(
    "SELECT * FROM users WHERE $where ORDER BY created_at DESC",
    $params,
    $page,
    25
);
$users         = $result['rows'];
$total_pages   = $result['pages'];
$current_page  = $result['page'];

require_once __DIR__ . '/../includes/header.php';
?>
<?php render_flash(); ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Users (<?= $result['total'] ?>)</span>
    </div>

    <form method="GET" class="filter-bar">
        <input type="text" name="q" class="form-control" placeholder="Search username or email…"
               value="<?= e($search) ?>">
        <button type="submit" class="btn btn-secondary btn-sm">Search</button>
        <?php if ($search): ?>
            <a href="<?= e(base_url('admin/users.php')) ?>" class="btn btn-sm">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($users)): ?>
        <p class="text-muted text-center" style="padding:20px 0">No users found.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Balance</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['id']) ?></td>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e(format_currency($u['balance'])) ?></td>
                    <td>
                        <span class="badge <?= $u['role'] === 'admin' ? 'badge-primary' : 'badge-secondary' ?>">
                            <?= e(ucfirst($u['role'])) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                            <?= e(ucfirst($u['status'])) ?>
                        </span>
                    </td>
                    <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
                    <td style="white-space:nowrap">
                        <!-- Edit Balance -->
                        <button type="button" class="btn btn-xs btn-outline"
                                data-modal-open="modal_balance"
                                data-user_id="<?= e($u['id']) ?>"
                                data-username="<?= e($u['username']) ?>">
                            Edit Balance
                        </button>

                        <?php if ($u['role'] !== 'admin'): ?>
                        <!-- Ban / Unban -->
                        <form method="POST" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action"  value="toggle_ban">
                            <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                            <button type="submit"
                                    class="btn btn-xs <?= $u['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>"
                                    data-confirm="<?= $u['status'] === 'active' ? 'Ban' : 'Unban' ?> this user?">
                                <?= $u['status'] === 'active' ? 'Ban' : 'Unban' ?>
                            </button>
                        </form>
                        <?php endif; ?>

                        <!-- View Orders -->
                        <a href="<?= e(base_url('admin/orders.php')) ?>?user=<?= urlencode($u['username']) ?>"
                           class="btn btn-xs btn-secondary">Orders</a>
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
            <?php $q = http_build_query(['page' => $i, 'q' => $search]); ?>
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

<!-- Edit Balance Modal -->
<div class="modal-backdrop" id="modal_balance">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit Balance</span>
            <button class="modal-close" data-modal-close="modal_balance">✕</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action"  value="edit_balance">
            <input type="hidden" name="user_id" id="modal_user_id">
            <div class="form-group">
                <label class="form-label">User: <strong id="modal_username_display"></strong></label>
            </div>
            <div class="form-group">
                <label class="form-label" for="modal_operation">Operation</label>
                <select name="operation" id="modal_operation" class="form-select">
                    <option value="add">Add Funds</option>
                    <option value="subtract">Subtract Funds</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="modal_amount">Amount</label>
                <input type="number" id="modal_amount" name="amount" class="form-control"
                       step="0.01" min="0.01" required placeholder="0.00">
            </div>
            <div class="form-group">
                <label class="form-label" for="modal_note">Note</label>
                <input type="text" id="modal_note" name="note" class="form-control"
                       placeholder="Admin balance adjustment" maxlength="255">
            </div>
            <button type="submit" class="btn btn-primary w-100">Update Balance</button>
        </form>
    </div>
</div>

<script>
// Populate modal username display
document.querySelectorAll('[data-modal-open="modal_balance"]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('modal_username_display').textContent = btn.dataset.username || '';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
