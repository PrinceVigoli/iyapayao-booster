<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api.php';

start_session();
require_login();

$user       = current_user();
$db         = get_db();
$page_title = 'New Order';
$active_nav = 'new_order';
$errors     = [];
$success    = '';

// Load active services
$stmt     = $db->prepare("SELECT * FROM services WHERE status = 'active' ORDER BY category, name");
$stmt->execute();
$services = $stmt->fetchAll();

// Group categories
$categories = array_unique(array_column($services, 'category'));
sort($categories);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $service_id_local = (int)($_POST['service_id'] ?? 0);
    $link             = trim($_POST['link'] ?? '');
    $quantity         = (int)($_POST['quantity'] ?? 0);
    $runs             = isset($_POST['runs']) && $_POST['runs'] !== '' ? (int)$_POST['runs'] : null;
    $interval         = isset($_POST['interval']) && $_POST['interval'] !== '' ? (int)$_POST['interval'] : null;

    // Validate
    if ($service_id_local <= 0) {
        $errors[] = 'Please select a service.';
    }
    if ($link === '') {
        $errors[] = 'Link is required.';
    }
    if (strlen($link) > 500) {
        $errors[] = 'Link is too long (max 500 characters).';
    }

    // Load service
    $service = null;
    if (empty($errors)) {
        $stmt = $db->prepare("SELECT * FROM services WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$service_id_local]);
        $service = $stmt->fetch();
        if (!$service) {
            $errors[] = 'Invalid or unavailable service.';
        }
    }

    if ($service && empty($errors)) {
        if ($quantity < (int)$service['min'] || $quantity > (int)$service['max']) {
            $errors[] = sprintf(
                'Quantity must be between %s and %s for this service.',
                number_format($service['min']),
                number_format($service['max'])
            );
        }
    }

    // Calculate charge: price is stored per 1,000 units (standard SMM panel convention)
    $charge = 0.0;
    if ($service && empty($errors)) {
        $charge = round((float)$service['price'] * $quantity / 1000, 5);
        if ((float)$user['balance'] < $charge) {
            $errors[] = sprintf(
                'Insufficient balance. This order costs %s but your balance is %s.',
                format_currency($charge),
                format_currency($user['balance'])
            );
        }
    }

    // Place order
    if (empty($errors) && $service) {
        $api    = get_api();
        $result = $api->addOrder(
            (int)$service['service_id'],
            $link,
            $quantity,
            $runs,
            $interval
        );

        if (isset($result['error'])) {
            $errors[] = 'API error: ' . $result['error'];
        } else {
            $ext_order_id = isset($result['order']) ? (int)$result['order'] : null;

            // Deduct balance (transaction)
            $new_balance = round((float)$user['balance'] - $charge, 5);

            $db->beginTransaction();
            try {
                // Save order
                $stmt = $db->prepare(
                    'INSERT INTO orders
                     (user_id, service_id, external_order_id, link, quantity, charge, status, runs, interval_minutes, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $stmt->execute([
                    $user['id'], $service['id'], $ext_order_id,
                    $link, $quantity, $charge,
                    'Pending', $runs, $interval,
                ]);
                $order_id = (int)$db->lastInsertId();

                // Deduct user balance
                $db->prepare('UPDATE users SET balance = ?, updated_at = NOW() WHERE id = ?')
                   ->execute([$new_balance, $user['id']]);

                // Record transaction
                $db->prepare(
                    'INSERT INTO transactions
                     (user_id, type, amount, balance_before, balance_after, description, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())'
                )->execute([
                    $user['id'], 'order', -$charge,
                    $user['balance'], $new_balance,
                    'Order #' . $order_id . ' — ' . $service['name'],
                ]);

                $db->commit();

                set_flash('success', 'Order #' . $order_id . ' placed successfully!');
                redirect(base_url('user/orders.php'));
            } catch (PDOException $e) {
                $db->rollBack();
                $errors[] = 'Failed to save order. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<?php render_flash(); ?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Place New Order</span>
    </div>

    <?php if (empty($services)): ?>
        <div class="alert alert-warning">
            No services are currently available. Please check back later or contact support.
        </div>
    <?php else: ?>

    <form method="POST" action="" novalidate>
        <?= csrf_field() ?>

        <div class="form-group">
            <label class="form-label" for="category_select">Category</label>
            <select id="category_select" name="category" class="form-select">
                <option value="">— Select Category —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>"
                        <?= (($_POST['category'] ?? '') === $cat) ? 'selected' : '' ?>>
                        <?= e($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="service_select">Service</label>
            <select id="service_select" name="service_id" class="form-select">
                <option value="">— Select Service —</option>
            </select>
        </div>

        <!-- Service details -->
        <div id="service_info" class="service-info mb-16">
            <dl>
                <dt>Rate</dt>      <dd id="info_rate">—</dd>
                <dt>Min Order</dt> <dd id="info_min">—</dd>
                <dt>Max Order</dt> <dd id="info_max">—</dd>
                <dt>Description</dt><dd id="info_desc">—</dd>
            </dl>
        </div>

        <div class="form-group">
            <label class="form-label" for="link">Link</label>
            <input type="url" id="link" name="link" class="form-control"
                   placeholder="https://" value="<?= e($_POST['link'] ?? '') ?>" required maxlength="500">
        </div>

        <div class="form-group">
            <label class="form-label" for="quantity">Quantity</label>
            <input type="number" id="quantity" name="quantity" class="form-control"
                   placeholder="Enter quantity" value="<?= e($_POST['quantity'] ?? '') ?>" required min="1">
        </div>

        <!-- Drip feed (hidden by default) -->
        <div id="dripfeed_section" style="display:none">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="runs">Runs</label>
                    <input type="number" id="runs" name="runs" class="form-control"
                           placeholder="Number of runs" value="<?= e($_POST['runs'] ?? '') ?>" min="1">
                    <div class="form-text">For drip-feed orders only.</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="interval">Interval (minutes)</label>
                    <input type="number" id="interval" name="interval" class="form-control"
                           placeholder="Minutes between runs" value="<?= e($_POST['interval'] ?? '') ?>" min="1">
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Place Order</button>
    </form>

    <?php endif; ?>
</div>

<!-- Pass services data to JS -->
<script>
window.servicesData = <?= json_encode(array_values($services), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
