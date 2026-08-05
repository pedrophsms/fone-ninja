<?php

use App\DataTransferObjects\SalePreviewData;
use App\ValueObjects\Money;

test('fromValidated maps the Portuguese sale payload into typed items without a cliente', function () {
    $data = SalePreviewData::fromValidated([
        'produtos' => [
            ['id' => 1, 'quantidade' => 2, 'preco_unitario' => '50.00'],
        ],
    ]);

    expect($data->items)->toHaveCount(1);
    expect($data->items[0]->productId)->toBe(1);
    expect($data->items[0]->quantity)->toBe(2);
    expect($data->items[0]->unitPrice)->toBeInstanceOf(Money::class);
    expect($data->items[0]->unitPrice->toCents())->toBe(5000);
});
