<?php

namespace App\Data;

final readonly class VatBreakdown
{
    public function __construct(
        public int $net,
        public int $vat,
        public int $gross,
        public int $rateBp,
        public bool $included,
    ) {}

    /** @return array{net: int, vat: int, gross: int, rate_bp: int, included: bool} */
    public function toArray(): array
    {
        return [
            'net' => $this->net,
            'vat' => $this->vat,
            'gross' => $this->gross,
            'rate_bp' => $this->rateBp,
            'included' => $this->included,
        ];
    }
}
