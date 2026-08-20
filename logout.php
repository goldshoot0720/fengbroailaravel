<?php
define('FENGBRO_PUBLIC_ENTRY', true);
require_once __DIR__ . '/includes/security.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(fengbroCsrfToken(), (string) ($_POST['_csrf'] ?? ''))) {
    http_response_code(405);
    exit('Invalid request');
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
}
session_destroy();
header('Location: login.php');
