<?php

namespace App\Http\Middleware;

use App\Exceptions\IdempotencyKeyConflictException;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $userId = $request->user()->id;

        // Insert a placeholder row FIRST, before running the route logic. The
        // unique(user_id, key) constraint atomically arbitrates between
        // concurrent requests carrying the same key: only one insert can
        // succeed, so only one request can ever proceed to execute the
        // financial write below. This closes the race where two concurrent
        // requests both find "no existing row" and both commit a duplicate
        // purchase/sale before either one's INSERT of the key row occurs.
        try {
            $record = IdempotencyKey::create([
                'key' => $key,
                'route' => $request->path(),
                'request_hash' => $requestHash,
                'response_status' => null,
                'response_body' => null,
                'user_id' => $userId,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = IdempotencyKey::where('user_id', $userId)->where('key', $key)->first();

            if (! $existing || $existing->request_hash !== $requestHash) {
                throw IdempotencyKeyConflictException::forKey($key);
            }

            if ($existing->response_status === null) {
                // Another request with the identical key+body is still
                // in-flight (its INSERT won the race but $next() hasn't
                // finished yet). Rather than risk replaying a partial/absent
                // response, or racing the in-flight request to write the
                // financial record, we reject with 409 so the client retries.
                return response()->json([
                    'message' => 'Uma requisição com esta Idempotency-Key já está em processamento',
                ], 409);
            }

            return response()->json($existing->response_body, $existing->response_status);
        }

        /** @var Response $response */
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // If the downstream handler throws, delete the placeholder row so
            // a retry with the same key can succeed once the client fixes the
            // issue that caused the failure. Without this, the row remains
            // with response_status = null forever, poisoning future requests.
            $record->delete();
            throw $e;
        }

        $record->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => json_decode($response->getContent(), true),
        ]);

        return $response;
    }
}
