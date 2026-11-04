<?php

namespace App\Services\Trips;

use App\Data\QuoteSnapshot;
use App\Enums\PaymentMethod;
use App\Exceptions\BookingException;
use App\Models\Trip;
use App\Models\TripLocation;
use App\Models\Vehicle;
use App\Services\Availability\AvailabilityService;
use App\Services\Geo\GoogleMapsClient;
use App\Services\Geo\ServiceAreaValidator;
use App\Services\Locations\PickupRestrictionService;
use App\Services\Pricing\PricingService;
use App\Services\Pricing\VatCalculator;
use App\Services\Quotes\QuoteStore;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;

final class TripQuoteService
{
    public function __construct(
        private readonly SettingsService $settings, private readonly GoogleMapsClient $maps,
        private readonly PickupRestrictionService $pickupRestriction, private readonly ServiceAreaValidator $serviceArea,
        private readonly TripSlotService $slots, private readonly AvailabilityService $availability,
        private readonly PricingService $pricing, private readonly VatCalculator $vat, private readonly QuoteStore $store,
    ) {}

    /** @param array<int, int> $addonQuantities */
    public function create(Trip $trip, ?int $departureId, ?string $customDepartureTime, CarbonImmutable $date, int $travellers, string $pickupPlaceId, string $pickupAddress, array $addonQuantities): QuoteSnapshot
    {
        if (! $this->settings->bool('trips_enabled')) throw new BookingException('trips_disabled', 'Trips are not available at the moment.', 404);
        $trip->loadMissing(['departures', 'blackouts', 'addons', 'prices.vehicle.category.media', 'prices.vehicle.media', 'tripLocations.location']);
        if ($trip->pricing_mode === 'enquiry') throw new BookingException('trip_enquiry_only', 'This trip is available by enquiry only.', 422);
        if ($trip->traveller_limits_enabled && ($travellers < $trip->min_travellers || $travellers > $trip->max_travellers)) {
            throw new BookingException('trip_traveller_limit', "This trip is available for {$trip->min_travellers} to {$trip->max_travellers} travellers.", 422);
        }
        $departure = $trip->fixed_departures_only && $departureId !== null ? $trip->departures->firstWhere('id', $departureId) : null;
        if ($trip->fixed_departures_only && ($departure === null || ! $departure->is_active)) throw new BookingException('departure_unavailable', 'This departure is not available.');
        if (! $trip->fixed_departures_only && ($customDepartureTime === null || ! preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $customDepartureTime))) throw new BookingException('departure_time_required', 'Choose your preferred departure time.', 422);
        if ($trip->blackouts->contains(fn ($blackout) => $blackout->date->isSameDay($date))) throw new BookingException('trip_blackout', 'This trip is not available on the selected date.');

        $pickup = $this->maps->placeDetails($pickupPlaceId);
        $pickupLink = null;
        $durationMinutes = 0;
        if ($trip->pickup_locations_only) {
            $allowedPickups = $trip->tripLocations->where('role', TripLocation::PICKUP)->filter(fn ($link) => $link->location?->is_active);
            $allowedPickupIds = $allowedPickups->map(fn ($link) => $link->location)->pluck('google_place_id');
            if (! $allowedPickupIds->contains($pickup->placeId)) {
                throw new BookingException('trip_pickup_not_allowed', 'Please choose one of this trip\'s available pickup locations.');
            }
            $pickupLink = $allowedPickups->first(fn ($link) => $link->location?->google_place_id === $pickup->placeId);
            $durationMinutes = (int) ($pickupLink?->duration_minutes ?? 0);
            if ($durationMinutes < 1) {
                throw new BookingException('trip_duration_missing', 'This pickup location does not have a trip duration configured yet.', 422);
            }
        }
        $this->pickupRestriction->assertAllowed($pickup->placeId);
        $this->serviceArea->assertAllowed($pickup, $pickup);
        $window = $this->slots->window($departure, $date, $durationMinutes, $customDepartureTime);
        if ($window['start']->lt(CarbonImmutable::now('UTC')->addHours($this->settings->int('trip_min_notice_hours')))) throw new BookingException('trip_too_soon', 'This departure is inside the minimum booking notice period.');
        $destination = $trip->tripLocations->firstWhere('role', TripLocation::MAIN_DESTINATION)?->location;
        if ($destination === null || ! $destination->google_place_id) throw new BookingException('trip_destination_missing', 'This trip has no primary destination configured.', 422);
        $route = $this->maps->computeRoute($pickup->placeId, $destination->google_place_id);
        $travelDistanceM = $route->distanceM * 2;
        $travelDurationS = $route->durationS * 2;
        $availabilityWindow = $this->availability->occupiedWindow($window['start'], $durationMinutes * 60 + $travelDurationS);

        $extras = []; $extrasTotal = 0;
        foreach ($addonQuantities as $addonId => $quantity) {
            if ($quantity < 1) continue;
            $addon = $trip->addons->firstWhere('id', $addonId);
            if ($addon === null || ! $addon->is_active || $quantity > $addon->max_quantity) throw new BookingException('trip_addon_invalid', 'One of the selected add-ons is not available in that quantity.');
            $total = $addon->price_minor * $quantity; $extrasTotal += $total;
            $extras[] = ['trip_addon_id' => $addon->id, 'name' => $addon->name, 'price_minor' => $addon->price_minor, 'quantity' => $quantity, 'total_minor' => $total];
        }

        $offers = [];
        $candidates = $trip->pricing_mode === 'distance'
            ? Vehicle::query()->with(['category.media', 'media'])->bookable()->get()->map(fn ($vehicle) => ['vehicle' => $vehicle, 'fixed_price' => null])
            : $trip->prices->where('pickup_location_id', $pickupLink?->location_id)->map(fn ($price) => ['vehicle' => $price->vehicle, 'fixed_price' => $price]);
        foreach ($candidates as $candidate) {
            $vehicle = $candidate['vehicle']; $fixedPrice = $candidate['fixed_price'];
            if ($vehicle === null || ($fixedPrice !== null && ! $fixedPrice->is_active) || ! $vehicle->is_active || ! $vehicle->category?->is_active || $vehicle->passenger_capacity < $travellers || ! $this->availability->hasFreeUnit($vehicle, $availabilityWindow['from'], $availabilityWindow['to'])) continue;
            $base = $fixedPrice?->price_minor ?? $this->pricing->price($vehicle, $route, true, 0)->subtotal;
            $subtotal = $base + $extrasTotal; $split = $this->vat->split($subtotal);
            $offers[$vehicle->id] = [
                'vehicle_id' => $vehicle->id,
                // Retained for existing quote consumers; new trip screens should use vehicle.
                'name' => $vehicle->name,
                'slug' => $vehicle->slug,
                'category' => $vehicle->category->name,
                'vehicle' => $this->vehicleCard($vehicle),
                'price' => [
                    'legs' => ['outbound' => $subtotal],
                    'discount' => 0,
                    'subtotal' => $subtotal,
                    'addons' => $extrasTotal,
                    'base_minor' => $base,
                    'addons_minor' => $extrasTotal,
                    'subtotal_minor' => $subtotal,
                    'total_minor' => $split->gross,
                ],
                'vat' => $split->toArray(),
            ];
        }
        if ($offers === []) throw new BookingException('trip_sold_out', 'No vehicle is available for this trip departure.', 409);

        $ttl = $this->settings->int('quote_ttl_minutes');
        $snapshot = new QuoteSnapshot(bin2hex(random_bytes(32)), CarbonImmutable::now('UTC')->addMinutes($ttl), $this->settings->string('currency'), $travellers, 0, [['direction' => 'outbound', 'pickup' => ['place_id' => $pickup->placeId, 'address' => $pickupAddress], 'dropoff' => ['place_id' => $pickup->placeId, 'address' => $pickupAddress], 'starts_at' => $window['start']->toIso8601String(), 'distance_m' => $travelDistanceM, 'duration_s' => $durationMinutes * 60 + $travelDurationS]], $offers, $this->paymentMethods(), ['type' => 'trip', 'trip_id' => $trip->id, 'departure_id' => $departure?->id, 'pickup_location_id' => $pickupLink?->location_id, 'addons' => $extras]);
        $this->store->put($snapshot, $ttl * 60);
        return $snapshot;
    }

    /** @return list<string> */
    private function paymentMethods(): array { return array_values(array_filter([ $this->settings->bool('online_payment_enabled') ? PaymentMethod::Online->value : null, $this->settings->bool('cash_on_arrival_enabled') ? PaymentMethod::CashOnArrival->value : null ])); }

    /** @return array<string, mixed> */
    private function vehicleCard(Vehicle $vehicle): array
    {
        $primaryImage = $vehicle->media->where('collection_name', 'images')->sortBy('order_column')->first();
        $imageUrl = $primaryImage?->hasGeneratedConversion('web') ? $primaryImage->getUrl('web') : $primaryImage?->getUrl();
        $thumbnailUrl = $primaryImage?->hasGeneratedConversion('thumbnail') ? $primaryImage->getUrl('thumbnail') : $imageUrl;
        $category = $vehicle->category;

        return [
            'id' => $vehicle->id,
            'name' => $vehicle->name,
            'model_name' => $vehicle->model_name,
            'slug' => $vehicle->slug,
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'logo_url' => $category->getFirstMediaUrl('logo', 'icon') ?: null,
            ],
            'description' => $vehicle->description,
            'image' => $primaryImage === null ? null : [
                'url' => $imageUrl,
                'thumbnail_url' => $thumbnailUrl,
            ],
            'passenger_capacity' => $vehicle->passenger_capacity,
            'luggage_capacity' => $vehicle->luggage_capacity,
            'amenities' => array_values($vehicle->amenities ?? []),
        ];
    }
}
