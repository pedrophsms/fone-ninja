<?php

namespace App\Http\Middleware;

use App\Exceptions\IdempotencyKeyConflictException;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return response()->json(['message' => 'Cabeçalho Idempotency-Key é obrigatório'], 400);
        }

        $requestHash = hash('sha256', $request->getContent());
        $existing = IdempotencyKey::where('key', $key)->first();

        if ($existing) {
            if ($existing->request_hash !== $requestHash) {
                throw IdempotencyKeyConflictException::forKey($key);
            }

            return response()->json($existing->response_body, $existing->response_status);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response->getStatusCode() < 400) {
            IdempotencyKey::create([
                'key' => $key,
                'route' => $request->path(),
                'request_hash' => $requestHash,
                'response_status' => $response->getStatusCode(),
                'response_body' => json_decode($response->getContent(), true),
                'user_id' => $request->user()->id,
            ]);
        }

        return $response;
    }
}
