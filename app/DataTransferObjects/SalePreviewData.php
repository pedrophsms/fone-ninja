<?php

namespace App\DataTransferObjects;

use App\ValueObjects\Money;

final class SalePreviewData
{
    /** @param SaleItemData[] $items */
    public function __construct(
        public readonly array $items,
    ) {
    }

    public static function fromValidated(array $validated): self
    {
        return new self(
            items: array_map(
                fn (array $item) => new SaleItemData(
                    productId: (int) $item['id'],
                    quantity: (int) $item['quantidade'],
                    unitPrice: Money::fromDecimalString((string) $item['preco_unitario']),
                ),
                $validated['produtos'],
            ),
        );
    }
}
