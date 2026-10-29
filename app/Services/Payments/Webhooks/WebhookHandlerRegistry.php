<?php

namespace App\Services\Payments\Webhooks;

final class WebhookHandlerRegistry
{
    public function __construct(
        private readonly CheckoutCompletedHandler $checkoutCompleted,
        private readonly CheckoutExpiredHandler $checkoutExpired,
        private readonly ChargeRefundedHandler $chargeRefunded,
    ) {}

    public function for(string $type): ?WebhookHandler
    {
        return match ($type) {
            'checkout.session.completed' => $this->checkoutCompleted,
            'checkout.session.expired' => $this->checkoutExpired,
            'charge.refunded' => $this->chargeRefunded,
            default => null,
        };
    }
}
