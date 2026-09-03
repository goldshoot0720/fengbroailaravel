<?php
declare(strict_types=1);

function fengbroIsHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function fengbroStartSecureSession(): void
{
    if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('fengbro_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => fengbroIsHttps(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function fengbroSecurityHeaders(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), geolocation=(), payment=(), usb=()');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    if (fengbroIsHttps()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

function fengbroCsrfToken(): string
{
    fengbroStartSecureSession();
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return (string) $_SESSION['csrf_token'];
}

function fengbroRequestCsrfToken(): string
{
    return (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '');
}

function fengbroRequireCsrf(): void
{
    if (PHP_SAPI === 'cli' || defined('FENGBRO_PUBLIC_ENTRY')) return;
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = strtolower((string) ($_GET['action'] ?? ''));
    $unsafeGetActions = ['delete', 'restore', 'empty_trash', 'send', 'cleanup'];
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !in_array($action, $unsafeGetActions, true)) return;
    $token = fengbroRequestCsrfToken();
    if ($token !== '' && hash_equals(fengbroCsrfToken(), $token)) return;
    http_response_code(419);
    if (str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json') || str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), 'api')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Security token expired. Reload the page and try again.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo 'Security token expired. Please reload and try again.';
    }
    exit;
}

fengbroSecurityHeaders();
fengbroStartSecureSession();
fengbroRequireCsrf();
