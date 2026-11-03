<?php

namespace App\Services\Trips;

use App\Models\Trip;
use App\Models\TripDeparture;
use Carbon\CarbonImmutable;

/** Converts a customer-facing Swiss departure into the UTC window used by AvailabilityService. */
final class TripSlotService
{
    /** @return array{start: CarbonImmutable, from: CarbonImmutable, to: CarbonImmutable} */
    public function window(Trip $trip, ?TripDeparture $departure, CarbonImmutable $date, ?string $customDepartureTime = null): array
    {
        $time = substr((string) ($departure?->start_time ?? $customDepartureTime), 0, 8);
        $local = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $date->toDateString().' '.$time, 'Europe/Zurich');
        if ($local->format('H:i:s') !== $time) {
            throw new \RuntimeException('This departure time does not exist on the selected daylight-saving date.');
        }
        $start = $local->utc();
        $fromTo = app(\App\Services\Availability\AvailabilityService::class)->occupiedWindow($start, $trip->duration_minutes * 60);
        return ['start' => $start, ...$fromTo];
    }
}
