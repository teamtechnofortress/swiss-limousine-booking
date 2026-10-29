<?php

namespace App\Data;

use App\Models\Booking;

final readonly class CreatedBooking
{
    public function __construct(
        public Booking $booking,
        /** Plain access token. Returned once to the creating browser and sent by email. Never stored. */
        public string $accessToken,
        /** Plain cash verification token. Sent by email only. Never stored. */
        public ?string $verificationToken,
    ) {}
}
