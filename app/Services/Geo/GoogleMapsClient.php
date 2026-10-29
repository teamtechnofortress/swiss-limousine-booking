<?php

namespace App\Services\Geo;

use App\Data\PlaceData;
use App\Data\RouteMetrics;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Server-side Google calls only. The browser key is never used here. */
final class GoogleMapsClient
{
    private const PLACES_URL = 'https://places.googleapis.com/v1/places/';
    private const ROUTES_URL = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    public function __construct(private readonly SettingsService $settings) {}

    public function placeDetails(string $placeId): PlaceData
    {
        return Cache::remember('place:'.$placeId, now()->addDay(), fn () => $this->fetchPlace($placeId));
    }

    public function computeRoute(string $originPlaceId, string $destinationPlaceId): RouteMetrics
    {
        $route = $this->http()
            ->withHeaders(['X-Goog-FieldMask' => 'routes.distanceMeters,routes.duration'])
            ->post(self::ROUTES_URL, [
                'origin' => ['placeId' => $originPlaceId],
                'destination' => ['placeId' => $destinationPlaceId],
                'travelMode' => 'DRIVE',
                'routingPreference' => 'TRAFFIC_UNAWARE',
                'units' => 'METRIC',
            ])
            ->throw()
            ->json('routes.0');

        if (! is_array($route) || ! isset($route['distanceMeters'], $route['duration'])) {
            throw new RuntimeException('Google returned no route between these places.');
        }

        return new RouteMetrics(
            distanceM: (int) $route['distanceMeters'],
            durationS: (int) rtrim((string) $route['duration'], 's'),
        );
    }

    private function fetchPlace(string $placeId): PlaceData
    {
        $response = $this->http()
            ->withHeaders(['X-Goog-FieldMask' => 'id,formattedAddress,location,addressComponents'])
            ->get(self::PLACES_URL.rawurlencode($placeId))
            ->throw()
            ->json();

        if (! isset($response['location'])) {
            throw new RuntimeException('Google returned no coordinates for this place.');
        }

        $components = collect($response['addressComponents'] ?? []);
        $find = fn (string $type): ?array => $components->first(
            fn (array $component) => in_array($type, $component['types'] ?? [], true),
        );

        $country = $find('country');
        $locality = $find('locality');
        $adminArea = $find('administrative_area_level_1');

        return new PlaceData(
            placeId: (string) ($response['id'] ?? $placeId),
            formattedAddress: (string) ($response['formattedAddress'] ?? ''),
            countryCode: $country['shortText'] ?? null,
            locality: $locality['longText'] ?? null,
            adminAreaCode: $adminArea['shortText'] ?? null,
            adminAreaName: $adminArea['longText'] ?? null,
            latitude: (float) $response['location']['latitude'],
            longitude: (float) $response['location']['longitude'],
        );
    }

    private function http(): PendingRequest
    {
        $key = $this->settings->secret('google_server_key')
            ?? throw new RuntimeException('Google server key is not configured.');

        return Http::withHeaders(['X-Goog-Api-Key' => $key])
            ->timeout(5)
            ->retry(2, 200);
    }
}
