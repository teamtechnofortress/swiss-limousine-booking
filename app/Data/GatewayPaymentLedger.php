<?php

namespace App\Data;

/** The successful provider charge and its balance transaction. */
final readonly class GatewayPaymentLedger
{
    public function __construct(
        public string $chargeId,
        public GatewayBalanceTransaction $balanceTransaction,
    ) {}
}
