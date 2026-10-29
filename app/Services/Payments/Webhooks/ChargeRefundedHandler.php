<?php

namespace App\Services\Payments\Webhooks;

use Illuminate\Support\Facades\Log;

/** Refunds made in this system are already recorded. This only logs refunds made in the Stripe dashboard. */
final class ChargeRefundedHandler implements WebhookHandler
{
    public function handle(array $object): void
    {
        Log::info('Stripe charge refunded', [
            'charge' => $object['id'] ?? null,
            'payment_intent' => $object['payment_intent'] ?? null,
            'amount_refunded' => $object['amount_refunded'] ?? null,
        ]);
    }
}
