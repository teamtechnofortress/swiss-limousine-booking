<?php

namespace App\Services\Payments\Webhooks;

use App\Services\Payments\PaymentService;

final class RefundUpdatedHandler implements WebhookHandler
{
    public function __construct(private readonly PaymentService $payments) {}

    public function handle(array $object): void
    {
        $this->payments->reconcileStripeRefund($object);
    }
}
