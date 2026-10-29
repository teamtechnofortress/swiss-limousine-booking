<?php

namespace App\Data;

/** All amounts are gross rappen (VAT is split later by VatCalculator). */
final readonly class PriceBreakdown
{
    /** @param array<string, int> $legs e.g. ['outbound' => 21000, 'return' => 21000] */
    public function __construct(
        public array $legs,
        public int $discount,
        public int $subtotal,
    ) {}

    /** @return array{legs: array<string, int>, discount: int, subtotal: int} */
    public function toArray(): array
    {
        return ['legs' => $this->legs, 'discount' => $this->discount, 'subtotal' => $this->subtotal];
    }
}
