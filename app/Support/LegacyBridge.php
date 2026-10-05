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

        if (function_exists('request') && app()->bound('request')) {
            $current = request();
            $_SERVER['REQUEST_METHOD'] = $current->getMethod();
            $posted = $current->request->all();
            if (is_array($posted) && $posted !== []) {
                $_POST = array_merge($posted, is_array($_POST) ? $_POST : []);
            }
            $query = $current->query->all();
            if (is_array($query) && $query !== []) {
                $_GET = array_merge($query, is_array($_GET) ? $_GET : []);
            }
        }
    }
}
