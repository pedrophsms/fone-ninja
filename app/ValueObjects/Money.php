<?php

namespace App\ValueObjects;

use InvalidArgumentException;

final class Money
{
    private function __construct(private readonly int $cents)
    {
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function fromDecimalString(string $amount): self
    {
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException("Invalid decimal amount: {$amount}");
        }

        return new self((int) round(((float) $amount) * 100));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(Money $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(Money $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function multiply(float $factor): self
    {
        return new self((int) round($this->cents * $factor));
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function equals(Money $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function toCents(): int
    {
        return $this->cents;
    }

    public function formatted(): string
    {
        return number_format($this->cents / 100, 2, '.', '');
    }
}
