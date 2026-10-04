<?php

// 主機若還沒有 composer install，或 PHP 低於 8.3，先走原本的頁面，避免網站直接致命錯誤。
$fengbroAutoload = __DIR__.'/vendor/autoload.php';
if (! is_file($fengbroAutoload) || PHP_VERSION_ID < 80300) {
    require __DIR__.'/legacy_index.php';
    return;
}

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $fengbroAutoload;

/** @var Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

$app->handleRequest(Illuminate\Http\Request::capture());
