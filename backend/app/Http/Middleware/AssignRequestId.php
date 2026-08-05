<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();

        // Runs on the global middleware stack, before route-level
        // auth:sanctum resolves the authenticated user, so user_id cannot be
        // set here yet. It is added later by
        // AssignAuthenticatedUserToLogContext, which runs inside the
        // auth:sanctum route group.
        Log::shareContext([
            'request_id' => $requestId,
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
