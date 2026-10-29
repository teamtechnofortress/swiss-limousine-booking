<?php

namespace App\Services\Payments\Webhooks;

use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Bookings\BookingService;
use Illuminate\Support\Facades\Log;

final class CheckoutExpiredHandler implements WebhookHandler
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookings,
        private readonly BookingService $bookingService,
    ) {}

    public function handle(array $object): void
    {
        $reference = $object['client_reference_id'] ?? ($object['metadata']['booking_reference'] ?? null);
        $booking = is_string($reference) ? $this->bookings->findByReference($reference) : null;

        if ($booking === null) {
            Log::warning('Checkout expired for an unknown booking', ['session' => $object['id'] ?? null]);

            return;
        }

        $this->bookingService->expireOnlineCheckout($booking->id, (string) $object['id']);
    }
}
