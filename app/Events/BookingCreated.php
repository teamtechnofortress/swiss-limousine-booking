<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Dispatched after a booking is saved. The listener sends the "booking received" email. */
final class BookingCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $accessToken,
        public readonly ?string $verificationToken,
    ) {}
}
