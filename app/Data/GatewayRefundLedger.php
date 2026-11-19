<?php

namespace App\Data;

/** The balance transaction created by a provider refund. */
final readonly class GatewayRefundLedger
{
    public function __construct(public GatewayBalanceTransaction $balanceTransaction) {}
}
