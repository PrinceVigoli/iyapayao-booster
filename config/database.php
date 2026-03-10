<?php
declare(strict_types=1);

// Database configuration — override these values in a local .env or set environment variables
define('DB_HOST',     getenv('DB_HOST')     ?: 'localhost');
define('DB_PORT',     getenv('DB_PORT')     ?: '3306');
define('DB_NAME',     getenv('DB_NAME')     ?: 'iyapayao_booster');
define('DB_USER',     getenv('DB_USER')     ?: 'root');
define('DB_PASS',     getenv('DB_PASS')     ?: '');
define('DB_CHARSET',  'utf8mb4');

// Application constants
define('APP_ROOT',    dirname(__DIR__));
define('APP_URL',     (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
                      '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

/**
 * Returns a shared PDO instance (singleton).
 */
function get_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Do not expose credentials / stack trace in production
            error_log('DB connection failed: ' . $e->getMessage());
            die('Database connection error. Please contact the administrator.');
        }
    }
    return $pdo;
}

/**
 * Retrieves a single setting value by key.
 */
function get_setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    try {
        $db   = get_db();
        $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row  = $stmt->fetch();
        $cache[$key] = $row ? (string)$row['setting_value'] : $default;
    } catch (PDOException $e) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

/**
 * Updates a setting value.
 */
function set_setting(string $key, string $value): void
{
    $db = get_db();
    $db->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute([$key, $value]);
}
