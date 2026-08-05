<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AssignAuthenticatedUserToLogContext
{
    /**
     * Adds user_id to the shared log context. Must run AFTER auth:sanctum
     * so $request->user() is resolved (AssignRequestId runs on the global
     * middleware stack, before route-level auth, so it cannot do this).
     */
    public function handle(Request $request, Closure $next): Response
    {
        Log::shareContext([
            'user_id' => optional($request->user())->id,
        ]);

        return $next($request);
    }
}
