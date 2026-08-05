<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdempotencyKeyConflictException extends Exception
{
    private string $key;

    public static function forKey(string $key): self
    {
        $exception = new self('Idempotency-Key já utilizada com um corpo de requisição diferente');
        $exception->key = $key;

        return $exception;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }

    public function context(): array
    {
        return ['idempotency_key' => $this->key];
    }
}
