<?php

namespace App\Support;

class LegacyBridge
{
    public static function boot(): void
    {
        if (! defined('FENGBRO_LARAVEL')) {
            define('FENGBRO_LARAVEL', true);
        }

        // 頁面改由 Laravel 處理 CSRF。略過 security.php 在載入當下的 exit(419)。
        if (! defined('FENGBRO_PUBLIC_ENTRY')) {
            define('FENGBRO_PUBLIC_ENTRY', true);
        }

        if (! function_exists('getAll')) {
            require_once base_path('includes/functions.php');
        }
    }
}
