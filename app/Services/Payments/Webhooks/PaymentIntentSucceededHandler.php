<?php

namespace App\Services\Payments\Webhooks;

use App\Services\Bookings\BookingService;
use Illuminate\Support\Facades\Log;

final class PaymentIntentSucceededHandler implements WebhookHandler
{
    public function __construct(private readonly BookingService $bookingService) {}

    public function handle(array $object): void
    {
        $attemptId = $object['metadata']['payment_attempt_id'] ?? null;
        $paymentIntent = $object['id'] ?? null;

        if (! is_numeric($attemptId) || ! is_string($paymentIntent) || $paymentIntent === '') {
            Log::warning('Stripe PaymentIntent success is missing its payment-attempt correlation data.');

            return;
        }

        $this->bookingService->confirmOnlinePaymentIntent((int) $attemptId, $paymentIntent);
    }
}
