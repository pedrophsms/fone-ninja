<?php

use App\DataTransferObjects\RegisterSaleData;
use App\ValueObjects\Money;

test('fromValidated maps the Portuguese sale payload into typed items', function () {
    $data = RegisterSaleData::fromValidated([
        'cliente' => 'Fulano da Silva',
        'produtos' => [
            ['id' => 1, 'quantidade' => 2, 'preco_unitario' => '50.00'],
        ],
    ]);

    expect($data->customer)->toBe('Fulano da Silva');
    expect($data->items)->toHaveCount(1);
    expect($data->items[0]->unitPrice->toCents())->toBe(5000);
});
