<?php

namespace App\DataTransferObjects;

final class RegisterSaleData
{
    /** @param SaleItemData[] $items */
    public function __construct(
        public readonly string $customer,
        public readonly array $items,
    ) {
    }

    public static function fromValidated(array $validated): self
    {
        return new self(
            customer: $validated['cliente'],
            items: array_map(
                fn (array $item) => new SaleItemData(
                    productId: (int) $item['id'],
                    quantity: (int) $item['quantidade'],
                    unitPrice: \App\ValueObjects\Money::fromDecimalString((string) $item['preco_unitario']),
                ),
                $validated['produtos'],
            ),
        );
    }
}
