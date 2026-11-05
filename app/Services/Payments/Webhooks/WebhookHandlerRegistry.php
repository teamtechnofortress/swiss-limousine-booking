<?php

namespace App\Services\Payments\Webhooks;

final class WebhookHandlerRegistry
{
    public function __construct(
        private readonly CheckoutCompletedHandler $checkoutCompleted,
        private readonly CheckoutExpiredHandler $checkoutExpired,
        private readonly CheckoutAsyncPaymentFailedHandler $checkoutAsyncPaymentFailed,
        private readonly PaymentIntentSucceededHandler $paymentIntentSucceeded,
        private readonly PaymentIntentUnsuccessfulHandler $paymentIntentUnsuccessful,
        private readonly RefundUpdatedHandler $refundUpdated,
        private readonly ChargeRefundedHandler $chargeRefunded,
    ) {}

    public function for(string $type): ?WebhookHandler
    {
        return match ($type) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $this->checkoutCompleted,
            'checkout.session.expired' => $this->checkoutExpired,
            'checkout.session.async_payment_failed' => $this->checkoutAsyncPaymentFailed,
            'payment_intent.succeeded' => $this->paymentIntentSucceeded,
            'payment_intent.payment_failed', 'payment_intent.canceled' => $this->paymentIntentUnsuccessful,
            'refund.created', 'refund.updated' => $this->refundUpdated,
            'charge.refunded' => $this->chargeRefunded,
            default => null,
        };
    }
}
