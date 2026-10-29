<?php

namespace App\Services\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingAdjustment;
use App\Models\FleetUnit;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use App\Services\Payments\PaymentService;
use App\Services\Pricing\VatCalculator;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Admin actions on bookings: review, unit assignment, cancellation with refund, and waiting time. */
final class BookingAdminService
{
    public function __construct(
        private readonly BookingLocker $locker,
        private readonly AvailabilityService $availability,
        private readonly PaymentService $payments,
        private readonly VatCalculator $vat,
        private readonly SettingsService $settings,
    ) {}

    public function markSeen(Booking $booking): void
    {
        if ($booking->admin_seen_at === null) {
            $booking->forceFill(['admin_seen_at' => CarbonImmutable::now('UTC')])->save();
        }
    }

    /** The only point where a physical plate is linked to a booking. */
    public function assignUnit(int $bookingId, int $fleetUnitId): Booking
    {
        return DB::transaction(function () use ($bookingId, $fleetUnitId): Booking {
            [$vehicle, $booking] = $this->locker->lock($bookingId);

            $unit = FleetUnit::query()->findOrFail($fleetUnitId);

            if ($unit->vehicle_id !== $booking->vehicle_id) {
                throw BookingException::unitMismatch();
            }

            if (! in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::Assigned], true)) {
                throw BookingException::invalidTransition($booking->status, BookingStatus::Assigned);
            }

            if ($vehicle->slot_check_enabled && $this->unitIsBusy($unit->id, $booking)) {
                throw BookingException::unitConflict();
            }

            $booking->blocks()->update(['fleet_unit_id' => $unit->id]);

            if ($booking->status === BookingStatus::Confirmed) {
                $booking->transitionTo(BookingStatus::Assigned);
            }

            $booking->fleet_unit_id = $unit->id;
            $booking->save();

            return $booking->load(['vehicle', 'fleetUnit', 'legs', 'payment']);
        });
    }

    /**
     * Cancels the booking and releases its slot. The refund runs after the commit.
     * If the refund fails, the booking stays cancelled and the refund can be retried from the payment screen.
     */
    public function cancel(int $bookingId, int $refundGross, string $reason, User $admin): Booking
    {
        $booking = DB::transaction(function () use ($bookingId, $reason): Booking {
            [, $booking] = $this->locker->lock($bookingId);

            if (! $booking->status->canTransitionTo(BookingStatus::Cancelled)) {
                throw BookingException::invalidTransition($booking->status, BookingStatus::Cancelled);
            }

            $booking->blocks()->delete();
            $booking->transitionTo(BookingStatus::Cancelled);
            $booking->forceFill([
                'cancelled_at' => CarbonImmutable::now('UTC'),
                'cancellation_reason' => $reason,
            ])->save();

            return $booking;
        });

        if ($refundGross > 0) {
            $this->payments->refund($booking, $refundGross, $reason, $admin);
        }

        return $booking->refresh()->load(['vehicle', 'fleetUnit', 'legs', 'payment.refunds']);
    }

    /** Records actual waiting minutes after the trip. The original price never changes. */
    public function recordWaiting(int $bookingId, int $minutes, User $admin): BookingAdjustment
    {
        return DB::transaction(function () use ($bookingId, $minutes, $admin): BookingAdjustment {
            [, $booking] = $this->locker->lock($bookingId);

            if (! in_array($booking->status, [BookingStatus::Assigned, BookingStatus::InProgress, BookingStatus::Completed], true)) {
                throw BookingException::waitingNotAllowed();
            }

            $charge = $this->waitingCharge($minutes);
            $split = $this->vat->calculate($charge, $booking->vat_rate_bp, $booking->vat_included);

            return $booking->adjustments()->create([
                'type' => 'waiting',
                'minutes' => $minutes,
                'gross_amount' => $split->gross,
                'vat_amount' => $split->vat,
                'status' => 'pending',
                'reason' => 'Waiting time after pickup',
                'created_by' => $admin->id,
            ]);
        });
    }

    /** ceil((minutes − free minutes) / block length) × rate per block. Zero or less means no charge. */
    private function waitingCharge(int $minutes): int
    {
        $free = $this->settings->int('waiting_free_minutes');
        $block = max(1, $this->settings->int('waiting_block_minutes'));
        $rate = $this->settings->int('waiting_rate_per_block');

        $billable = max(0, $minutes - $free);

        return intdiv($billable + $block - 1, $block) * $rate;
    }

    /** True if the unit is already linked to another booking whose blocks overlap this one. */
    private function unitIsBusy(int $unitId, Booking $booking): bool
    {
        $booking->loadMissing('legs');

        return $booking->legs->contains(function ($leg) use ($unitId, $booking): bool {
            $window = $this->availability->occupiedWindow($leg->starts_at, $leg->duration_s);

            return ScheduleBlock::query()
                ->where('fleet_unit_id', $unitId)
                ->where('booking_id', '!=', $booking->id)
                ->overlapping($window['from'], $window['to'])
                ->exists();
        });
    }
}
