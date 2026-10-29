<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Models\Booking;
use App\Services\Notifications\BookingMailer;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendBookingConfirmedEmail implements ShouldQueue
{
    public string $queue = 'mail';

    public function __construct(private readonly BookingMailer $mailer) {}

    public function handle(BookingConfirmed $event): void
    {
        $booking = Booking::query()->with('legs')->find($event->bookingId);

        if ($booking !== null) {
            $this->mailer->confirmed($booking);
        }
    }
}
