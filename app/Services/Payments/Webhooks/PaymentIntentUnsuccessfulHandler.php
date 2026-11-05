<?php

namespace App\Services\Payments\Webhooks;

use Illuminate\Support\Facades\Log;

final class PaymentIntentUnsuccessfulHandler implements WebhookHandler
{
    public function handle(array $object): void
    {
        // Do not mark the Checkout attempt failed here: Stripe Checkout can retry another
        // payment method within the same open Checkout Session.
        Log::warning('Stripe PaymentIntent did not complete.', [
            'payment_intent' => $object['id'] ?? null,
            'status' => $object['status'] ?? null,
            'attempt_id' => $object['metadata']['payment_attempt_id'] ?? null,
            'failure_code' => $object['last_payment_error']['code'] ?? null,
        ]);
    }
}
