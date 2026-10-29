<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Dispatched after a booking is paid or verified. Carries only the id. */
final class BookingConfirmed
{
    use Dispatchable;

    public function __construct(public readonly int $bookingId) {}
}
