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
}
