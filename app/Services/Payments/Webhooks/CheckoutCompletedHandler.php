<?php

namespace App\Services\Payments\Webhooks;

use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Bookings\BookingService;
use Illuminate\Support\Facades\Log;

final class CheckoutCompletedHandler implements WebhookHandler
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookings,
        private readonly BookingService $bookingService,
    ) {}

    public function handle(array $object): void
    {
        // Delayed payment methods report "unpaid" here. They are not supported in v1.
        if (($object['payment_status'] ?? null) !== 'paid') {
            return;
        }

        $reference = $object['client_reference_id'] ?? ($object['metadata']['booking_reference'] ?? null);
        $booking = is_string($reference) ? $this->bookings->findByReference($reference) : null;

        if ($booking === null) {
            Log::error('Checkout completed for an unknown booking', ['session' => $object['id'] ?? null]);

            return;
        }

        $this->bookingService->confirmOnlinePayment(
            $booking->id,
            (string) $object['id'],
            isset($object['payment_intent']) ? (string) $object['payment_intent'] : null,
        );
    }
}
