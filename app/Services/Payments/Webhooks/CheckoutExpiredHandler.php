<?php

namespace App\Services\Payments\Webhooks;

use App\Models\PaymentAttempt;
use App\Services\Bookings\BookingService;
use Illuminate\Support\Facades\Log;

final class CheckoutExpiredHandler implements WebhookHandler
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    public function handle(array $object): void
    {
        $attemptId = $object['metadata']['payment_attempt_id'] ?? null;
        $attempt = is_numeric($attemptId) ? PaymentAttempt::query()->find((int) $attemptId) : null;

        if ($attempt === null) {
            Log::warning('Checkout expired for an unknown payment attempt', ['session' => $object['id'] ?? null]);

            return;
        }

        $this->bookingService->expireOnlineCheckout($attempt->id, (string) $object['id']);
    }
}
