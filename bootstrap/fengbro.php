<?php

/**
 * Laravel 會載入 config 目錄裡每個 .php。
 * 若線上的 config/database.php 仍是鋒兄常數檔，先把連線資訊移到專案根目錄，
 * 再換回框架用的 config/database.php。
 */
$fengbroRoot = dirname(__DIR__);
$fengbroSecret = $fengbroRoot.'/fengbro_database.php';
$fengbroLaravelDatabase = __DIR__.'/laravel-database.php';

foreach ([
    $fengbroRoot.'/config/database.php',
    $fengbroRoot.'/config/fengbro_database.php',
] as $fengbroCandidate) {
    if (! is_file($fengbroCandidate)) {
        continue;
    }

    $fengbroHead = (string) file_get_contents($fengbroCandidate, false, null, 0, 2000);
    $fengbroIsLegacy = str_contains($fengbroHead, "define('DB_HOST'")
        || str_contains($fengbroHead, 'define("DB_HOST"');
    if (! $fengbroIsLegacy) {
        continue;
    }

    if (! is_file($fengbroSecret) && ! @copy($fengbroCandidate, $fengbroSecret)) {
        continue;
    }

    if (basename($fengbroCandidate) === 'database.php' && is_file($fengbroLaravelDatabase)) {
        @copy($fengbroLaravelDatabase, $fengbroCandidate);
    } elseif (basename($fengbroCandidate) === 'fengbro_database.php') {
        @unlink($fengbroCandidate);
    }
}
