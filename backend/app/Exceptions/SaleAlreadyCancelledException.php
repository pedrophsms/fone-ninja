<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleAlreadyCancelledException extends Exception
{
    private int $saleId;

    public static function forSale(int $saleId): self
    {
        $exception = new self('Venda já cancelada');
        $exception->saleId = $saleId;

        return $exception;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }

    public function context(): array
    {
        return ['sale_id' => $this->saleId];
    }
}
