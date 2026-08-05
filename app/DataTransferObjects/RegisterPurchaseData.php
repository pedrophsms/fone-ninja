<?php

namespace App\DataTransferObjects;

final class RegisterPurchaseData
{
    /** @param PurchaseItemData[] $items */
    public function __construct(
        public readonly string $supplier,
        public readonly array $items,
    ) {
    }

    public static function fromValidated(array $validated): self
    {
        return new self(
            supplier: $validated['fornecedor'],
            items: array_map(
                fn (array $item) => new PurchaseItemData(
                    productId: (int) $item['id'],
                    quantity: (int) $item['quantidade'],
                    unitPrice: \App\ValueObjects\Money::fromDecimalString((string) $item['preco_unitario']),
                ),
                $validated['produtos'],
            ),
        );
    }
}
