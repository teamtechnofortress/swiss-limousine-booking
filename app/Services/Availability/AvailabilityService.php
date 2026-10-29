<?php

namespace App\Services\Availability;

use App\Enums\BlockType;
use App\Exceptions\BookingException;
use App\Models\ScheduleBlock;
use App\Models\Vehicle;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Capacity rule: free units = units of the vehicle minus blocks that overlap the window
 * and are either bookings or unexpired holds. Vehicles with the slot check off are always free.
 * Callers that write blocks must hold the vehicle lock (see BookingLocker).
 */
final class AvailabilityService
{
    public function __construct(private readonly SettingsService $settings) {}

    /** @return array{from: CarbonImmutable, to: CarbonImmutable} */
    public function occupiedWindow(CarbonImmutable $start, int $durationS): array
    {
        $buffer = $this->settings->int('default_buffer_minutes');

        return [
            'from' => $start->subMinutes($buffer),
            'to' => $start->addSeconds($durationS)->addMinutes($buffer),
        ];
    }

    /** @param list<array{from: CarbonImmutable, to: CarbonImmutable}> $windows */
    public function windowsFree(Vehicle $vehicle, array $windows): bool
    {
        foreach ($windows as $window) {
            if (! $this->hasFreeUnit($vehicle, $window['from'], $window['to'])) {
                return false;
            }
        }

        return true;
    }

    /** @param list<array{from: CarbonImmutable, to: CarbonImmutable}> $windows */
    public function assertWindowsFree(Vehicle $vehicle, array $windows): void
    {
        if (! $this->windowsFree($vehicle, $windows)) {
            throw BookingException::slotUnavailable();
        }
    }

    public function hasFreeUnit(Vehicle $vehicle, CarbonImmutable $from, CarbonImmutable $to): bool
    {
        if (! $vehicle->slot_check_enabled) {
            return true;
        }

        $units = $vehicle->fleetUnits()->count();

        $occupied = ScheduleBlock::query()
            ->where('vehicle_id', $vehicle->id)
            ->overlapping($from, $to)
            ->where(function (Builder $query): void {
                $query->where('block_type', BlockType::Booking->value)
                    ->orWhere(fn (Builder $hold) => $hold
                        ->where('block_type', BlockType::Hold->value)
                        ->where('expires_at', '>', CarbonImmutable::now('UTC')));
            })
            ->count();

        return $units - $occupied > 0;
    }

    public function writeBlock(
        int $vehicleId,
        int $bookingId,
        int $bookingLegId,
        BlockType $type,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?CarbonImmutable $expiresAt,
    ): ScheduleBlock {
        return ScheduleBlock::query()->create([
            'vehicle_id' => $vehicleId,
            'booking_id' => $bookingId,
            'booking_leg_id' => $bookingLegId,
            'block_type' => $type,
            'starts_at' => $from,
            'ends_at' => $to,
            'expires_at' => $type === BlockType::Hold ? $expiresAt : null,
        ]);
    }
}
