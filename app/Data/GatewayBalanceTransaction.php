<?php

namespace App\Data;

/** A provider balance transaction: the authoritative settlement and fee record. */
final readonly class GatewayBalanceTransaction
{
    /** @param list<array<string, mixed>> $feeDetails */
    public function __construct(
        public string $id,
        public int $amount,
        public string $currency,
        public int $feeAmount,
        public array $feeDetails,
        public int $netAmount,
        public ?string $exchangeRate,
    ) {}
}
