<?php
/**
 * 載入鋒兄資料庫連線。
 *
 * Laravel 的 config/database.php 是框架設定（回傳陣列）。
 * 鋒兄常數檔放在專案根目錄的 fengbro_database.php（不要放進 config/，Laravel 會載入該目錄）。
 */

function fengbroDbEnv(string ...$keys): string
{
    foreach ($keys as $key) {
        if (defined($key)) {
            $value = (string) constant($key);
            if ($value !== '' && !str_starts_with($value, '__')) {
                return $value;
            }
        }
        $fromEnv = $_ENV[$key] ?? getenv($key);
        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }
    }

    return '';
}

function fengbroLoadDatabaseConfig(): void
{
    $root = dirname(__DIR__);
    $dedicated = $root . '/fengbro_database.php';
    $shared = $root . '/config/database.php';
    $legacyDedicated = $root . '/config/fengbro_database.php';

    foreach ([$legacyDedicated, $shared] as $candidate) {
        if (is_file($dedicated) || !is_file($candidate)) {
            continue;
        }
        $head = (string) @file_get_contents($candidate, false, null, 0, 1600);
        if (str_contains($head, "define('DB_HOST'") || str_contains($head, 'define("DB_HOST"')) {
            @copy($candidate, $dedicated);
        }
    }

    if (is_file($dedicated)) {
        require_once $dedicated;
        return;
    }

    if (!is_file($shared)) {
        return;
    }

    $head = (string) @file_get_contents($shared, false, null, 0, 1600);
    if (str_contains($head, "define('DB_HOST'") || str_contains($head, 'define("DB_HOST"')) {
        require_once $shared;
    }
}

fengbroLoadDatabaseConfig();

if (!defined('DB_HOST')) {
    define('DB_HOST', fengbroDbEnv('DB_HOST') ?: 'localhost');
    define('DB_NAME', fengbroDbEnv('DB_NAME', 'DB_DATABASE'));
    define('DB_USER', fengbroDbEnv('DB_USER', 'DB_USERNAME'));
    define('DB_PASS', fengbroDbEnv('DB_PASS', 'DB_PASSWORD'));
    define('DB_CHARSET', fengbroDbEnv('DB_CHARSET') ?: 'utf8mb4');
}

if (!function_exists('fengbroFailDatabase')) {
    function fengbroFailDatabase(string $message): void
    {
        if (defined('FENGBRO_LARAVEL') || getenv('APP_ENV') === 'testing') {
            throw new RuntimeException($message);
        }

        http_response_code(500);
        exit($message);
    }
}

if (!function_exists('getConnection')) {
    function getConnection()
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        if (DB_NAME === '' || str_starts_with((string) DB_NAME, '__')) {
            fengbroFailDatabase('資料庫連線失敗，請檢查 fengbro_database.php 設定。');
        }

        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            fengbroFailDatabase('資料庫連線失敗，請檢查 fengbro_database.php 設定。');
        }

        return $pdo;
    }
}
