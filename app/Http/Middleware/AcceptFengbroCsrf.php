<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

class AcceptFengbroCsrf extends PreventRequestForgery
{
    /**
     * 瀏覽器同源請求已由父類別放行。
     * 這裡再接受鋒兄原本的 _csrf / X-CSRF-TOKEN，讓 Livewire 與舊表單共用同一個 meta token。
     */
    protected function tokensMatch($request)
    {
        if (parent::tokensMatch($request)) {
            return true;
        }

        $token = (string) ($request->input('_csrf') ?: $request->header('X-CSRF-TOKEN') ?: '');
        if ($token === '') {
            return false;
        }

        if (! defined('FENGBRO_PUBLIC_ENTRY')) {
            define('FENGBRO_PUBLIC_ENTRY', true);
        }
        if (! defined('FENGBRO_LARAVEL')) {
            define('FENGBRO_LARAVEL', true);
        }
        if (! function_exists('fengbroCsrfToken')) {
            require_once base_path('includes/security.php');
        }

        $expected = fengbroCsrfToken();

        return $expected !== '' && hash_equals($expected, $token);
    }
}
