<?php
declare(strict_types=1);

/**
 * Utility / helper functions for Iyapayao Booster.
 */

/** Return the absolute URL for a site-relative path. */
function base_url(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/** Safely output a value as HTML-escaped text. */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Format a decimal as currency. */
function format_currency(float|string $amount, string $currency = ''): string
{
    if ($currency === '') {
        $currency = get_setting('currency', 'USD');
    }
    return $currency . ' ' . number_format((float)$amount, 2);
}

/** Format a price per 1,000 (common in SMM panels). */
function format_rate(float|string $rate): string
{
    $currency = get_setting('currency', 'USD');
    return $currency . ' ' . number_format((float)$rate, 5) . '/1k';
}

/** Redirect to a URL and stop execution. */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Flash a message into the session for display on next request. */
function set_flash(string $type, string $message): void
{
    start_session();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Retrieve and clear all flash messages. */
function get_flash(): array
{
    start_session();
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Render flash messages as Bootstrap-style alert divs. */
function render_flash(): void
{
    foreach (get_flash() as $flash) {
        $type = match($flash['type']) {
            'success' => 'alert-success',
            'error'   => 'alert-danger',
            'warning' => 'alert-warning',
            default   => 'alert-info',
        };
        echo '<div class="alert ' . $type . '">' . e($flash['message']) . '</div>';
    }
}

/**
 * Calculate selling price from provider rate + markup percent.
 */
function apply_markup(float $rate, float $markup_percent): float
{
    return $rate * (1 + $markup_percent / 100);
}

/**
 * Generate a random API key (64 hex chars).
 */
function generate_api_key(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Return a CSS class name for a given order status.
 */
function order_status_class(string $status): string
{
    return match(strtolower($status)) {
        'completed'    => 'badge-success',
        'processing'   => 'badge-info',
        'in progress'  => 'badge-info',
        'partial'      => 'badge-warning',
        'pending'      => 'badge-secondary',
        'canceled',
        'cancelled'    => 'badge-danger',
        'refunded'     => 'badge-primary',
        default        => 'badge-secondary',
    };
}

/**
 * Get the shared SMMApi instance using settings from the database.
 */
function get_api(): SMMApi
{
    static $api = null;
    if ($api === null) {
        $api = new SMMApi(
            get_setting('api_url', 'https://bigsmmserver.com/api/v2'),
            get_setting('api_key', '')
        );
    }
    return $api;
}

/**
 * Paginate a query: returns ['rows'=>[], 'total'=>int, 'pages'=>int, 'page'=>int].
 *
 * @param  array<mixed> $params
 * @return array{rows:array<mixed>, total:int, pages:int, page:int}
 */
function paginate(string $sql, array $params, int $page, int $per_page = 20): array
{
    $db = get_db();

    // Count total
    $count_sql  = 'SELECT COUNT(*) FROM (' . $sql . ') AS _count';
    $stmt       = $db->prepare($count_sql);
    $stmt->execute($params);
    $total      = (int)$stmt->fetchColumn();
    $pages      = max(1, (int)ceil($total / $per_page));
    $page       = max(1, min($page, $pages));
    $offset     = ($page - 1) * $per_page;

    // LIMIT and OFFSET must be integers; PDO doesn't support named params for LIMIT/OFFSET.
    // Values are explicitly cast to int above, making interpolation safe.
    $paged_sql  = $sql . ' LIMIT ' . (int)$per_page . ' OFFSET ' . (int)$offset;
    $stmt       = $db->prepare($paged_sql);
    $stmt->execute($params);
    $rows       = $stmt->fetchAll();

    return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
}
