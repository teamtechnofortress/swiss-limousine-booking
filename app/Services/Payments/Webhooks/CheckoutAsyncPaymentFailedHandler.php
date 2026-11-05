<?php

namespace App\Services\Payments\Webhooks;

use App\Services\Bookings\BookingService;
use Illuminate\Support\Facades\Log;

final class CheckoutAsyncPaymentFailedHandler implements WebhookHandler
{
    public function __construct(private readonly BookingService $bookingService) {}

    public function handle(array $object): void
    {
        $attemptId = $object['metadata']['payment_attempt_id'] ?? null;
        $sessionId = $object['id'] ?? null;

        if (! is_numeric($attemptId) || ! is_string($sessionId) || $sessionId === '') {
            Log::warning('Stripe async payment failure is missing its Checkout Session correlation data.');

            return;
        }

        $this->bookingService->failOnlineCheckout((int) $attemptId, $sessionId);
    }
}
