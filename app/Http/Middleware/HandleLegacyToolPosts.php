<?php

namespace App\Http\Middleware;

use App\Support\FengbroPages;
use App\Support\LegacyBridge;
use Closure;
use Illuminate\Http\Request;

class HandleLegacyToolPosts
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('POST') && (string) $request->query('page') === 'tools') {
            LegacyBridge::boot();
            require_once base_path('includes/tools_actions.php');
            $tool = (string) $request->query('tool', 'price');
            if (! in_array($tool, FengbroPages::tools(), true)) {
                $tool = 'price';
            }
            fengbroToolsHandlePostActions($tool);
        }

        return $next($request);
    }
}
