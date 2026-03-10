<?php
declare(strict_types=1);

/**
 * Authentication helpers.
 */

/** Start (or resume) a secure PHP session. */
function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/** Return true if the current visitor is logged in. */
function is_logged_in(): bool
{
    start_session();
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/** Redirect to login page if not logged in. */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . base_url('/auth/login.php'));
        exit;
    }
}

/** Redirect to user dashboard if not admin. */
function require_admin(): void
{
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ' . base_url('/user/dashboard.php'));
        exit;
    }
}

/** Log a user in (populate session). */
function login_user(array $user): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id']  = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];
    $_SESSION['email']    = $user['email'];
}

/** Log the current user out. */
function logout_user(): void
{
    start_session();
    $_SESSION = [];
    session_destroy();
}

/** Return the currently logged-in user's row, or null. */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    $db   = get_db();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

// ----------------------------------------------------------------
// CSRF helpers
// ----------------------------------------------------------------

/** Generate (or return cached) CSRF token for the current session. */
function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Render a hidden CSRF input field. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/** Verify the submitted CSRF token, die on failure. */
function verify_csrf(): void
{
    start_session();
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submitted)) {
        http_response_code(403);
        die('Invalid CSRF token. Please go back and try again.');
    }
}
