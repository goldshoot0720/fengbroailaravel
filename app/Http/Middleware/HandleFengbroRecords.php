<?php

namespace App\Http\Middleware;

use App\Support\LegacyBridge;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleFengbroRecords
{
    public function handle(Request $request, Closure $next): Response
    {
        $manual = (string) $request->query('fengbro_manual', '');
        $page = (string) $request->query('page', '');
        $action = (string) $request->query('action', '');
        $table = (string) $request->query('table', '');

        if ($manual !== '1' && ! ($page === '' && $action !== '' && $table !== '')) {
            return $next($request);
        }

        $blocked = $this->rejectUnsafeGetWithoutCsrf($request);
        if ($blocked instanceof Response) {
            return $blocked;
        }

        LegacyBridge::boot();
        $input = $request->isJson() ? $request->json()->all() : $request->request->all();
        if (! is_array($input)) {
            $input = [];
        }

        if ($manual === '1') {
            require_once base_path('includes/fengbro_manual_price_action.php');
            $result = fengbroPerformManualPriceAction($request->getMethod(), $request->query(), $input);
        } else {
            require_once base_path('includes/fengbro_record_action.php');
            $result = fengbroPerformRecordAction($request->query(), $input);
        }

        return response()->json($result['body'], $result['status']);
    }

    /**
     * GET delete / restore / empty_trash 在 Laravel 會被當成安全方法而略過 CSRF。
     * 沿用原本的不安全 GET 名單，沒有鋒兄 token 就回 419，不改資料。
     */
    private function rejectUnsafeGetWithoutCsrf(Request $request): ?Response
    {
        $method = strtoupper($request->getMethod());
        $action = strtolower((string) $request->query('action', ''));
        $unsafeGetActions = ['delete', 'restore', 'empty_trash', 'send', 'cleanup'];
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) || ! in_array($action, $unsafeGetActions, true)) {
            return null;
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

        $token = (string) ($request->header('X-CSRF-TOKEN') ?: $request->input('_csrf') ?: '');
        $expected = fengbroCsrfToken();
        if ($token !== '' && $expected !== '' && hash_equals($expected, $token)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => '安全驗證已過期，請重新整理頁面後再試一次。',
        ], 419);
    }
}
