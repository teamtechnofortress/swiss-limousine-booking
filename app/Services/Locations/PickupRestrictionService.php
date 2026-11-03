<?php

namespace App\Services\Locations;

use App\Exceptions\BookingException;
use App\Models\Location;
use App\Services\Settings\SettingsService;

/** Server-side boundary: UI chips are helpful, but never the security decision. */
final class PickupRestrictionService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function assertAllowed(string $placeId): ?Location
    {
        if (! $this->settings->bool('restrict_pickups_enabled')) return null;

        $location = Location::query()->allowedPickup()->where('google_place_id', $placeId)->first();
        if ($location === null) throw BookingException::pickupNotAllowed($this->settings->string('restricted_pickup_message'));

        return $location;
    }

    public function matchingLocation(string $placeId): ?Location
    {
        return Location::query()->active()->where('google_place_id', $placeId)->first();
    }
}
