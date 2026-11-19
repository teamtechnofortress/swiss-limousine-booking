<?php

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Services\Bookings\BookingLocker;
use App\Services\Payments\PaymentEventLogger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/** Cancels a booking only after Stripe's refund-success webhook has been reconciled. */
final class CancelBookingAfterRefundSucceeded implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $refundId) {}

    public function uniqueId(): string
    {
        return 'cancel-booking-after-refund-'.$this->refundId;
    }

    public function handle(BookingLocker $locker, PaymentEventLogger $events): void
    {
        $bookingId = Refund::query()
            ->with('payment:id,booking_id')
            ->find($this->refundId)?->payment?->booking_id;

        if ($bookingId === null) {
            return;
        }

        $outcome = DB::transaction(function () use ($bookingId, $locker): ?string {
            // Keep the established vehicle-then-booking lock order used by all booking mutations.
            [, $booking] = $locker->lock($bookingId);
            $refund = Refund::query()->whereKey($this->refundId)->lockForUpdate()->first();

            if ($refund === null || ! $refund->cancel_booking_on_success || $refund->status !== RefundStatus::Succeeded) {
                return null;
            }

            if ($refund->booking_cancelled_at !== null) {
                return 'already_recorded';
            }

            if ($booking->status === BookingStatus::Cancelled) {
                $refund->forceFill(['booking_cancelled_at' => CarbonImmutable::now('UTC')])->save();

                return 'already_cancelled';
            }

            if (! $booking->status->canTransitionTo(BookingStatus::Cancelled)) {
                return 'not_cancellable';
            }

            $booking->blocks()->delete();
            $booking->transitionTo(BookingStatus::Cancelled);
            $booking->forceFill([
                'cancelled_at' => CarbonImmutable::now('UTC'),
                'cancellation_reason' => $refund->reason,
            ])->save();
            $refund->forceFill(['booking_cancelled_at' => CarbonImmutable::now('UTC')])->save();

            return 'cancelled';
        });

        if ($outcome === 'cancelled') {
            $events->payment('Booking cancelled after Stripe refund webhook confirmation.', [
                'refund_id' => $this->refundId,
                'booking_id' => $bookingId,
            ]);
        }

        if ($outcome === 'not_cancellable') {
            $events->payment('Booking was not cancelled after refund confirmation because its current status is not cancellable.', [
                'refund_id' => $this->refundId,
                'booking_id' => $bookingId,
            ], 'warning');
        }
    }
}
