<?php

namespace App\Services\Trips;

use App\Models\Trip;
use App\Models\TripLocation;
use App\Models\Vehicle;
use App\Services\Settings\SettingsService;

/** Builds the single source of truth for public trip booking readiness and configuration. */
final class TripBookingConfiguration
{
    public function __construct(private readonly SettingsService $settings) {}

    /** @return array<string, mixed> */
    public function forTrip(Trip $trip): array
    {
        $trip->load([
            'tripLocations.location',
            'departures' => fn ($departures) => $departures->where('is_active', true),
            'prices' => fn ($prices) => $prices->where('is_active', true)
                ->whereHas('vehicle', fn ($vehicles) => $vehicles->where('is_active', true)->whereHas('category', fn ($categories) => $categories->where('is_active', true)))
                ->with('vehicle'),
        ]);

        $allPickups = $trip->tripLocations->where('role', TripLocation::PICKUP)->values();
        $validPickups = $allPickups
            ->filter(fn ($link) => $link->location?->is_active && $link->location?->google_place_id && $link->duration_minutes > 0)
            ->values();
        $pickupConfigured = ! $trip->pickup_locations_only
            || ($validPickups->isNotEmpty() && $validPickups->count() === $allPickups->count());
        $fixedPricesByPickup = $trip->pricing_mode === 'fixed' && $trip->pickup_locations_only && $pickupConfigured
            ? $trip->prices->groupBy('pickup_location_id')
            : collect();

        $departureLocations = $trip->pickup_locations_only && $pickupConfigured
            ? $validPickups->map(function ($link) use ($fixedPricesByPickup): array {
                $location = [
                    'id' => $link->location_id,
                    'name' => $link->location->name,
                    'place_id' => $link->location->google_place_id,
                    'address' => $link->location->formatted_address,
                    'duration_minutes' => (int) $link->duration_minutes,
                ];
                if ($fixedPricesByPickup->isNotEmpty()) {
                    $location['starting_from_price_minor'] = $fixedPricesByPickup->get($link->location_id, collect())->min('price_minor');
                }

                return $location;
            })->values()
            : collect();
        $fixedPricingConfigured = $trip->pricing_mode !== 'fixed'
            || ($departureLocations->isNotEmpty() && $departureLocations->every(fn (array $location) => ($location['starting_from_price_minor'] ?? null) !== null));
        $fixedScheduleConfigured = ! $trip->fixed_departures_only || $trip->departures->isNotEmpty();
        $destination = $trip->tripLocations->firstWhere('role', TripLocation::MAIN_DESTINATION)?->location;
        $destinationConfigured = $destination?->is_active && $destination->google_place_id;
        $maximumBookableFleetCapacity = $trip->pricing_mode === 'distance'
            ? Vehicle::query()->bookable()->max('passenger_capacity')
            : null;
        $distanceFleetConfigured = $trip->pricing_mode !== 'distance' || $maximumBookableFleetCapacity !== null;

        $reason = match (true) {
            $trip->pricing_mode === 'enquiry' => 'enquiry_only',
            ! $pickupConfigured => 'pickup_configuration_required',
            ! $fixedScheduleConfigured => 'schedule_configuration_required',
            $trip->pricing_mode === 'distance' && ! $destinationConfigured => 'destination_configuration_required',
            ! $distanceFleetConfigured => 'vehicle_configuration_required',
            ! $fixedPricingConfigured => 'fixed_pricing_configuration_required',
            default => null,
        };
        $onlineBookingAllowed = $reason === null;
        $baseEndpoint = '/api/public/trips/'.$trip->slug;

        // When the trip has no explicit traveller limit, only advertise capacities
        // that can actually be offered by this trip. Fixed-price trips can vary by
        // pickup; distance-priced trips use the online-bookable fleet.
        $maxCapacityByPickup = collect();
        $maximumCapacity = null;
        if ($trip->traveller_limits_enabled) {
            $maximumCapacity = (int) $trip->max_travellers;
        } elseif ($trip->pricing_mode === 'fixed') {
            $maxCapacityByPickup = $trip->prices
                ->groupBy('pickup_location_id')
                ->map(fn ($prices) => $prices->max(fn ($price) => (int) $price->vehicle->passenger_capacity));
            $maximumCapacity = $maxCapacityByPickup->max();
        } elseif ($trip->pricing_mode === 'distance') {
            $maximumCapacity = $maximumBookableFleetCapacity;
        }

        return [
            'start_booking_allowed' => $onlineBookingAllowed,
            'reason' => $reason,
            'reason_message' => $this->reasonMessage($reason),
            'pricing_mode' => $trip->pricing_mode,
            'pickup' => [
                'mode' => $trip->pickup_locations_only ? 'selected' : 'custom',
                'custom_allowed' => ! $trip->pickup_locations_only,
                'locations' => $departureLocations,
            ],
            'schedule' => [
                'mode' => $trip->fixed_departures_only ? 'fixed' : 'custom',
                'date_required' => true,
                'fixed_departures' => $trip->fixed_departures_only
                    ? $trip->departures->map(fn ($departure) => ['id' => $departure->id, 'start_time' => substr((string) $departure->start_time, 0, 5)])->values()
                    : [],
                'min_notice_hours' => $this->settings->int('trip_min_notice_hours'),
            ],
            'travellers' => [
                'selection_required' => true,
                'limits_applied' => $trip->traveller_limits_enabled,
                'min' => $trip->traveller_limits_enabled ? (int) $trip->min_travellers : 1,
                'max' => $maximumCapacity === null ? null : (int) $maximumCapacity,
                // Keys are pickup-location IDs. This is populated only for
                // unrestricted, fixed-price trips, where each pickup can offer a
                // different set of vehicles.
                'max_by_pickup_location' => (object) $maxCapacityByPickup->all(),
            ],
            'pricing' => [
                'mode' => match ($trip->pricing_mode) {
                    'fixed' => 'fixed',
                    'distance' => 'route_based',
                    default => 'enquiry',
                },
                'currency' => $this->settings->string('currency'),
                'starting_from_price_minor' => $trip->pricing_mode === 'fixed' ? $fixedPricesByPickup->flatten(1)->min('price_minor') : null,
                'quote_required' => $trip->pricing_mode === 'distance',
            ],
            'flow' => [
                'next_step' => $onlineBookingAllowed ? 'quote' : 'enquiry',
                'quote_endpoint' => $onlineBookingAllowed ? $baseEndpoint.'/quotes' : null,
                'availability_endpoint' => $onlineBookingAllowed && $trip->pickup_locations_only && $trip->fixed_departures_only ? $baseEndpoint.'/availability' : null,
                'enquiry_endpoint' => $baseEndpoint.'/enquiries',
            ],
        ];
    }

    private function reasonMessage(?string $reason): ?string
    {
        return match ($reason) {
            'enquiry_only' => 'This trip is enquiry-only. Choose fixed or route-based pricing to enable online bookings.',
            'pickup_configuration_required' => 'Add at least one selected pickup location. Every selected pickup must be active, linked to Google, and have a duration.',
            'schedule_configuration_required' => 'Add at least one active departure time, or switch this trip to a custom departure schedule.',
            'destination_configuration_required' => 'Choose an active primary destination that is linked to Google for route-based pricing.',
            'vehicle_configuration_required' => 'Add at least one active, online-bookable vehicle to enable route-based trip bookings.',
            'fixed_pricing_configuration_required' => 'Add an active vehicle price for every selected pickup location.',
            default => null,
        };
    }
}
