<?php

// 本機預覽：php -S 127.0.0.1:8000 router.php
// 網站根目錄就是專案根目錄，既有的 api.php、assets、uploads 維持原位。

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$file = __DIR__.$uri;

if ($uri !== '/' && is_file($file)) {
    return false;
}

require __DIR__.'/index.php';
