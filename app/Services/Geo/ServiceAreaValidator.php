<?php

namespace App\Services\Geo;

use App\Data\PlaceData;
use App\Exceptions\BookingException;
use App\Models\Location;
use App\Services\Settings\SettingsService;
use RuntimeException;

final class ServiceAreaValidator
{
    private const EARTH_RADIUS_M = 6371000;

    public function __construct(private readonly SettingsService $settings) {}

    public function assertAllowed(PlaceData $pickup, PlaceData $dropoff): void
    {
        if (! $this->settings->bool('service_area_enforced')) {
            return;
        }

        $allowed = $this->settings->list('allowed_countries');

        foreach ([$pickup, $dropoff] as $place) {
            if ($place->countryCode === null) {
                throw BookingException::unknownLocation();
            }

            if (! in_array($place->countryCode, $allowed, true)) {
                throw BookingException::countryNotAllowed();
            }
        }

        if (! $this->settings->bool('required_location_enabled')) {
            return;
        }

        $required = Location::query()->active()->find($this->settings->nullableInt('required_location_id'));

        if ($required === null) {
            throw new RuntimeException('The required location is not configured or is inactive.');
        }

        if (! $this->matches($pickup, $required) && ! $this->matches($dropoff, $required)) {
            throw BookingException::requiredLocationMissing($required->name);
        }
    }

    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }

    private function matches(PlaceData $place, Location $required): bool
    {
        return match ($this->settings->string('required_location_match_type')) {
            'city' => $place->countryCode === $required->country_code
                && $this->sameText($place->locality, $required->locality),
            'region' => $place->countryCode === $required->country_code
                && $this->sameText($place->adminAreaCode, $required->admin_area_code),
            'radius' => $this->distanceMeters(
                $place->latitude,
                $place->longitude,
                (float) $required->latitude,
                (float) $required->longitude,
            ) <= $this->settings->int('required_location_radius_km') * 1000,
            default => throw new RuntimeException('Unknown required location match type.'),
        };
    }

    private function sameText(?string $a, ?string $b): bool
    {
        return $a !== null && $b !== null && mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }
}
