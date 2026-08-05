<?php

use App\Services\ProfitCalculatorService;
use App\ValueObjects\Money;

test('calculate returns positive profit when selling above cost', function () {
    $service = new ProfitCalculatorService();

    $result = $service->calculate(
        unitPrice: Money::fromCents(5000),
        averageCost: Money::fromCents(3000),
        quantity: 3,
    );

    expect($result->toCents())->toBe(6000);
});

test('calculate returns negative profit when selling below cost', function () {
    $service = new ProfitCalculatorService();

    $result = $service->calculate(
        unitPrice: Money::fromCents(1000),
        averageCost: Money::fromCents(1500),
        quantity: 2,
    );

    expect($result->toCents())->toBe(-1000);
    expect($result->isNegative())->toBeTrue();
});
