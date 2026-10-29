<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Services\Notifications\BookingMailer;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendBookingReservedEmail implements ShouldQueue
{
    public string $queue = 'mail';

    public function __construct(private readonly BookingMailer $mailer) {}

    public function handle(BookingCreated $event): void
    {
        $this->mailer->received($event->booking, $event->accessToken, $event->verificationToken);
    }
}
