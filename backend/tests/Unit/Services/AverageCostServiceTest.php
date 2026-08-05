<?php

use App\Services\AverageCostService;
use App\ValueObjects\Money;

test('recalculate computes the weighted average when stock already exists', function () {
    $service = new AverageCostService();

    // 10 units @ 20.00 already in stock, buying 5 more @ 30.00
    // (10 * 2000 + 5 * 3000) / 15 = (20000 + 15000) / 15 = 2333.33 -> 2333 cents
    $result = $service->recalculate(
        currentStock: 10,
        currentAverageCost: Money::fromCents(2000),
        incomingQuantity: 5,
        incomingUnitPrice: Money::fromCents(3000),
    );

    expect($result->toCents())->toBe(2333);
});

test('recalculate returns the incoming unit price when there was no stock', function () {
    $service = new AverageCostService();

    $result = $service->recalculate(
        currentStock: 0,
        currentAverageCost: Money::zero(),
        incomingQuantity: 10,
        incomingUnitPrice: Money::fromCents(1500),
    );

    expect($result->toCents())->toBe(1500);
});
