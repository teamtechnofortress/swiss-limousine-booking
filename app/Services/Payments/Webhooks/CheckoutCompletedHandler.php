<?php

namespace App\Services\Payments\Webhooks;

use App\Models\PaymentAttempt;
use App\Services\Bookings\BookingService;
use Illuminate\Support\Facades\Log;

final class CheckoutCompletedHandler implements WebhookHandler
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    public function handle(array $object): void
    {
        // Delayed payment methods report "unpaid" here. They are not supported in v1.
        if (($object['payment_status'] ?? null) !== 'paid') {
            return;
        }

        $attemptId = $object['metadata']['payment_attempt_id'] ?? null;
        $attempt = is_numeric($attemptId) ? PaymentAttempt::query()->find((int) $attemptId) : null;

        if ($attempt === null) {
            Log::error('Checkout completed for an unknown payment attempt', ['session' => $object['id'] ?? null]);

            return;
        }

        $this->bookingService->confirmOnlinePayment(
            $attempt->id,
            (string) $object['id'],
            isset($object['payment_intent']) ? (string) $object['payment_intent'] : null,
        );
    }
}
