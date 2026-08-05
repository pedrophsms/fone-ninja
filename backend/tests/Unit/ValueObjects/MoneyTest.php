<?php

use App\ValueObjects\Money;

test('fromCents stores the exact integer amount', function () {
    expect(Money::fromCents(1050)->toCents())->toBe(1050);
});

test('fromDecimalString converts decimal strings to cents', function () {
    expect(Money::fromDecimalString('10.50')->toCents())->toBe(1050);
    expect(Money::fromDecimalString('10')->toCents())->toBe(1000);
    expect(Money::fromDecimalString('0.01')->toCents())->toBe(1);
});

test('fromDecimalString rejects malformed input', function () {
    Money::fromDecimalString('not-a-number');
})->throws(InvalidArgumentException::class);

test('zero is 0 cents', function () {
    expect(Money::zero()->toCents())->toBe(0);
});

test('add sums two amounts', function () {
    $result = Money::fromCents(1000)->add(Money::fromCents(250));
    expect($result->toCents())->toBe(1250);
});

test('subtract can go negative', function () {
    $result = Money::fromCents(100)->subtract(Money::fromCents(300));
    expect($result->toCents())->toBe(-200);
    expect($result->isNegative())->toBeTrue();
});

test('multiply rounds to the nearest cent', function () {
    $result = Money::fromCents(333)->multiply(3);
    expect($result->toCents())->toBe(999);

    $result = Money::fromCents(10)->multiply(0.335);
    expect($result->toCents())->toBe(3);
});

test('equals compares by cent value', function () {
    expect(Money::fromCents(500)->equals(Money::fromCents(500)))->toBeTrue();
    expect(Money::fromCents(500)->equals(Money::fromCents(501)))->toBeFalse();
});

test('formatted renders a two-decimal string', function () {
    expect(Money::fromCents(1005)->formatted())->toBe('10.05');
    expect(Money::fromCents(5)->formatted())->toBe('0.05');
    expect(Money::fromCents(-1050)->formatted())->toBe('-10.50');
});
