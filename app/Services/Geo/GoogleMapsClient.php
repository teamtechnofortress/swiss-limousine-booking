<?php

namespace App\Services\Geo;

use App\Data\PlaceData;
use App\Data\RouteMetrics;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Server-side Google calls only. The browser key is never used here. */
final class GoogleMapsClient
{
    private const PLACES_URL = 'https://places.googleapis.com/v1/places/';

    private const PLACES_AUTOCOMPLETE_URL = 'https://places.googleapis.com/v1/places:autocomplete';

    private const ROUTES_URL = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Place Details content is deliberately never put in a shared cache or database. Place IDs
     * can be retained, but other Places content has stricter Google storage restrictions.
     */
    public function placeDetails(string $placeId, ?string $sessionToken = null): PlaceData
    {
        return $this->fetchPlace($placeId, $sessionToken);
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

    /**
     * @return list<array{place_id: string, name: string}>
     */
    public function countryPredictions(string $input): array
    {
        $predictions = $this->http()
            ->withHeaders(['X-Goog-FieldMask' => 'suggestions.placePrediction.placeId,suggestions.placePrediction.text.text'])
            ->post(self::PLACES_AUTOCOMPLETE_URL, [
                'input' => $input,
                'includedPrimaryTypes' => ['country'],
            ])
            ->throw()
            ->json('suggestions', []);

        return collect($predictions)
            ->map(fn (array $suggestion): ?array => $suggestion['placePrediction'] ?? null)
            ->filter(fn (?array $prediction): bool => isset($prediction['placeId'], $prediction['text']['text']))
            ->map(fn (array $prediction): array => [
                'place_id' => (string) $prediction['placeId'],
                'name' => (string) $prediction['text']['text'],
            ])
            ->values()
            ->all();
    }

    /** @return list<array{place_id: string, name: string}> */
    public function locationPredictions(string $input): array
    {
        $predictions = $this->http()
            ->withHeaders(['X-Goog-FieldMask' => 'suggestions.placePrediction.placeId,suggestions.placePrediction.text.text'])
            ->post(self::PLACES_AUTOCOMPLETE_URL, ['input' => $input])
            ->throw()
            ->json('suggestions', []);

        return collect($predictions)
            ->map(fn (array $suggestion): ?array => $suggestion['placePrediction'] ?? null)
            ->filter(fn (?array $prediction): bool => isset($prediction['placeId'], $prediction['text']['text']))
            ->map(fn (array $prediction): array => ['place_id' => (string) $prediction['placeId'], 'name' => (string) $prediction['text']['text']])
            ->values()->all();
    }

    /** @return array{code: string, name: string}|null */
    public function countryForPlace(string $placeId): ?array
    {
        $place = $this->http()
            ->withHeaders(['X-Goog-FieldMask' => 'addressComponents,types'])
            ->get(self::PLACES_URL.rawurlencode($placeId))
            ->throw()
            ->json();

        if (! is_array($place) || ! in_array('country', $place['types'] ?? [], true)) {
            return null;
        }

        $components = $place['addressComponents'] ?? [];

        $country = collect($components)->first(
            fn (array $component): bool => in_array('country', $component['types'] ?? [], true),
        );

        $code = strtoupper((string) ($country['shortText'] ?? ''));
        $name = trim((string) ($country['longText'] ?? ''));

        if (! preg_match('/^[A-Z]{2}$/', $code) || $name === '') {
            return null;
        }

        return ['code' => $code, 'name' => $name];
    }

    /**
     * Place details intentionally fetched only after an administrator chooses a suggestion.
     * Customer searches do not create catalogue locations.
     *
     * @return array{google_place_id:string,name:string,formatted_address:string,country_code:string,locality:?string,admin_area_code:?string,admin_area_name:?string,latitude:float,longitude:float}
     */
    public function catalogueLocationDetails(string $placeId): array
    {
        $response = $this->http()
            ->withHeaders(['X-Goog-FieldMask' => 'id,displayName,formattedAddress,location,addressComponents'])
            ->get(self::PLACES_URL.rawurlencode($placeId))
            ->throw()
            ->json();

        if (! is_array($response) || ! isset($response['location'])) {
            throw new RuntimeException('Google returned no usable details for this location.');
        }

        $components = collect($response['addressComponents'] ?? []);
        $find = fn (string $type): ?array => $components->first(fn (array $component): bool => in_array($type, $component['types'] ?? [], true));
        $country = $find('country');
        $locality = $find('locality');
        $adminArea = $find('administrative_area_level_1');
        $countryCode = strtoupper((string) ($country['shortText'] ?? ''));

        if (! preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new RuntimeException('Google did not return a country code for this location.');
        }

        return [
            'google_place_id' => (string) ($response['id'] ?? $placeId),
            'name' => trim((string) ($response['displayName']['text'] ?? $response['formattedAddress'] ?? '')),
            'formatted_address' => trim((string) ($response['formattedAddress'] ?? '')),
            'country_code' => $countryCode,
            'locality' => $locality['longText'] ?? null,
            'admin_area_code' => $adminArea['shortText'] ?? null,
            'admin_area_name' => $adminArea['longText'] ?? null,
            'latitude' => (float) $response['location']['latitude'],
            'longitude' => (float) $response['location']['longitude'],
        ];
    }

    private function fetchPlace(string $placeId, ?string $sessionToken): PlaceData
    {
        $response = $this->http()
            // Request only what validation needs; never request a display address from Google.
            ->withHeaders(['X-Goog-FieldMask' => 'id,location,addressComponents'])
            ->get(self::PLACES_URL.rawurlencode($placeId), array_filter([
                'sessionToken' => $sessionToken,
            ]))
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
