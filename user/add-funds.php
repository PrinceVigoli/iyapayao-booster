<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_login();

$user       = current_user();
$db         = get_db();
$page_title = 'Add Funds';
$active_nav = 'add_funds';

// Transaction history (last 20)
$stmt = $db->prepare(
    'SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20'
);
$stmt->execute([$user['id']]);
$transactions = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<?php render_flash(); ?>

<div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr))">
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div>
            <div class="stat-value"><?= e(format_currency($user['balance'])) ?></div>
            <div class="stat-label">Current Balance</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Add Funds</span>
    </div>
    <div class="alert alert-info">
        <strong>Payment methods coming soon.</strong><br>
        To add funds to your account, please contact the administrator or use the payment method
        that will be configured on this platform. For now, an admin can manually credit your account.
    </div>
    <p class="text-muted" style="font-size:.9rem">
        Contact support with your username <strong><?= e($user['username']) ?></strong>
        and the amount you wish to add.
    </p>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Transaction History</span>
    </div>
    <?php if (empty($transactions)): ?>
        <p class="text-muted text-center" style="padding:20px 0">No transactions yet.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Before</th>
                    <th>After</th>
                    <th>Description</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td><?= e($tx['id']) ?></td>
                    <td>
                        <span class="badge <?= $tx['type'] === 'add_funds' ? 'badge-success' : ($tx['type'] === 'refund' ? 'badge-info' : 'badge-secondary') ?>">
                            <?= e(ucfirst(str_replace('_', ' ', $tx['type']))) ?>
                        </span>
                    </td>
                    <td style="color: <?= (float)$tx['amount'] >= 0 ? '#155724' : '#721c24' ?>; font-weight:600">
                        <?= (float)$tx['amount'] >= 0 ? '+' : '' ?><?= e(format_currency($tx['amount'])) ?>
                    </td>
                    <td><?= e(format_currency($tx['balance_before'])) ?></td>
                    <td><?= e(format_currency($tx['balance_after'])) ?></td>
                    <td><?= e($tx['description']) ?></td>
                    <td><?= e(date('M j, Y g:i A', strtotime($tx['created_at']))) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
