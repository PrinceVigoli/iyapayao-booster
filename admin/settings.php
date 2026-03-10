<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

start_session();
require_admin();

$page_title = 'Settings';
$active_nav = 'admin_settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $allowed = ['site_name', 'api_key', 'api_url', 'currency', 'price_markup_percent'];
    foreach ($allowed as $key) {
        if (isset($_POST[$key])) {
            $value = trim($_POST[$key]);
            // Basic validation
            if ($key === 'price_markup_percent') {
                $value = max('0', min('500', (string)(float)$value));
            }
            if ($key === 'api_url' && !filter_var($value, FILTER_VALIDATE_URL)) {
                set_flash('error', 'Invalid API URL.');
                redirect(base_url('admin/settings.php'));
            }
            set_setting($key, $value);
        }
    }
    set_flash('success', 'Settings saved successfully.');
    redirect(base_url('admin/settings.php'));
}

// Load current values
$settings = [
    'site_name'            => get_setting('site_name',            'Iyapayao Booster'),
    'api_key'              => get_setting('api_key',              ''),
    'api_url'              => get_setting('api_url',              'https://bigsmmserver.com/api/v2'),
    'currency'             => get_setting('currency',             'USD'),
    'price_markup_percent' => get_setting('price_markup_percent', '20'),
];

require_once __DIR__ . '/../includes/header.php';
?>
<?php render_flash(); ?>

<div class="card" style="max-width:620px">
    <div class="card-header">
        <span class="card-title">Site Settings</span>
    </div>
    <form method="POST" novalidate>
        <?= csrf_field() ?>

        <div class="form-group">
            <label class="form-label" for="site_name">Site Name</label>
            <input type="text" id="site_name" name="site_name" class="form-control"
                   value="<?= e($settings['site_name']) ?>" required maxlength="100">
        </div>

        <div class="form-group">
            <label class="form-label" for="api_url">API URL</label>
            <input type="url" id="api_url" name="api_url" class="form-control"
                   value="<?= e($settings['api_url']) ?>" required>
            <div class="form-text">Default: https://bigsmmserver.com/api/v2</div>
        </div>

        <div class="form-group">
            <label class="form-label" for="api_key">API Key</label>
            <input type="text" id="api_key" name="api_key" class="form-control"
                   value="<?= e($settings['api_key']) ?>" maxlength="200">
            <div class="form-text">Your BigSMMServer API key.</div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="currency">Currency</label>
                <input type="text" id="currency" name="currency" class="form-control"
                       value="<?= e($settings['currency']) ?>" maxlength="10">
                <div class="form-text">e.g. USD, EUR, GBP</div>
            </div>
            <div class="form-group">
                <label class="form-label" for="price_markup_percent">Price Markup (%)</label>
                <input type="number" id="price_markup_percent" name="price_markup_percent" class="form-control"
                       value="<?= e($settings['price_markup_percent']) ?>" step="0.1" min="0" max="500">
                <div class="form-text">Applied to provider rates when syncing services.</div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
