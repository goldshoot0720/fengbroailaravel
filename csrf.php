<?php
declare(strict_types=1);

/**
 * 回傳目前 session 的 CSRF token，供前端在收到 419 之後即時換新 token 再重試。
 * 僅同源 JS 讀得到（沒有 CORS 標頭），內容與頁面上的 <meta name="csrf-token"> 相同。
 */
define('FENGBRO_PUBLIC_ENTRY', true);
require_once __DIR__ . '/includes/security.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');

echo json_encode(['success' => true, 'token' => fengbroCsrfToken()], JSON_UNESCAPED_UNICODE);
