<?php

use App\DataTransferObjects\RegisterPurchaseData;
use App\ValueObjects\Money;

test('fromValidated maps the Portuguese payload into typed items', function () {
    $data = RegisterPurchaseData::fromValidated([
        'fornecedor' => 'Fornecedor X',
        'produtos' => [
            ['id' => 1, 'quantidade' => 50, 'preco_unitario' => '20.00'],
            ['id' => 2, 'quantidade' => 30, 'preco_unitario' => '10.50'],
        ],
    ]);

    expect($data->supplier)->toBe('Fornecedor X');
    expect($data->items)->toHaveCount(2);
    expect($data->items[0]->productId)->toBe(1);
    expect($data->items[0]->quantity)->toBe(50);
    expect($data->items[0]->unitPrice)->toBeInstanceOf(Money::class);
    expect($data->items[0]->unitPrice->toCents())->toBe(2000);
    expect($data->items[1]->unitPrice->toCents())->toBe(1050);
});
