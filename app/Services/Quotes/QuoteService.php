<?php

namespace App\Services\Quotes;

use App\Data\QuoteRequestData;
use App\Data\QuoteSnapshot;
use App\Data\RouteMetrics;
use App\Enums\PaymentMethod;
use App\Exceptions\BookingException;
use App\Repositories\Contracts\VehicleRepositoryInterface;
use App\Services\Availability\AvailabilityService;
use App\Services\Geo\GoogleMapsClient;
use App\Services\Geo\RouteProvider;
use App\Services\Geo\ServiceAreaValidator;
use App\Services\Pricing\PricingService;
use App\Services\Pricing\VatCalculator;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;

final class QuoteService
{
    public function __construct(
        private readonly GoogleMapsClient $maps,
        private readonly RouteProvider $routes,
        private readonly ServiceAreaValidator $serviceArea,
        private readonly PricingService $pricing,
        private readonly VatCalculator $vat,
        private readonly AvailabilityService $availability,
        private readonly VehicleRepositoryInterface $vehicles,
        private readonly QuoteStore $store,
        private readonly SettingsService $settings,
    ) {}

    public function create(QuoteRequestData $request): QuoteSnapshot
    {
        $now = CarbonImmutable::now('UTC');
        $this->assertSchedule($request->pickupAt, $now);

        $pickup = $this->maps->placeDetails($request->pickupPlaceId, $request->pickupSessionToken);
        $dropoff = $this->maps->placeDetails($request->dropoffPlaceId, $request->dropoffSessionToken);
        $this->serviceArea->assertAllowed($pickup, $dropoff);

        $outboundRoute = $this->routes->metrics($pickup->placeId, $dropoff->placeId);
        $legs = [$this->leg(
            'outbound', $pickup->placeId, $dropoff->placeId, $request->pickupAddress, $request->dropoffAddress,
            $request->pickupAt, $outboundRoute,
        )];

        $returnAt = $request->returnAt;

        if ($returnAt !== null) {
            $this->assertReturnGap($request->pickupAt->addSeconds($outboundRoute->durationS), $returnAt);
            $returnRoute = $this->routes->metrics($dropoff->placeId, $pickup->placeId);
            $legs[] = $this->leg(
                'return', $dropoff->placeId, $pickup->placeId, $request->dropoffAddress, $request->pickupAddress,
                $returnAt, $returnRoute,
            );
        }

        $windows = array_map(
            fn (array $leg) => $this->availability->occupiedWindow(
                CarbonImmutable::parse($leg['starts_at']),
                (int) $leg['duration_s'],
            ),
            $legs,
        );

        $discountPercent = $this->settings->int('round_trip_discount_percent');
        $offers = [];

        $vehicles = $this->vehicles->bookableFor($request->passengers, $request->luggage, $request->vehicleSlug);

        // A stale or invalid vehicle URL should still let the customer see the
        // vehicles that can serve their trip instead of returning an empty quote.
        if ($request->vehicleSlug !== null && $vehicles->isEmpty()) {
            $vehicles = $this->vehicles->bookableFor($request->passengers, $request->luggage);
        }

        foreach ($vehicles as $vehicle) {
            if (! $this->availability->windowsFree($vehicle, $windows)) {
                continue;
            }

            $price = $this->pricing->price(
                $vehicle,
                $outboundRoute,
                $returnAt !== null,
                $discountPercent,
                $returnAt !== null ? $returnRoute : null,
            );
            $split = $this->vat->split($price->subtotal);

            $offers[$vehicle->id] = [
                'vehicle_id' => $vehicle->id,
                'name' => $vehicle->name,
                'slug' => $vehicle->slug,
                'category' => $vehicle->category->name,
                'price' => $price->toArray(),
                'vat' => $split->toArray(),
            ];
        }

        if ($offers === []) {
            throw BookingException::noVehiclesAvailable();
        }

        $ttlMinutes = $this->settings->int('quote_ttl_minutes');
        $snapshot = new QuoteSnapshot(
            token: bin2hex(random_bytes(32)),
            expiresAt: $now->addMinutes($ttlMinutes),
            currency: $this->settings->string('currency'),
            passengers: $request->passengers,
            luggage: $request->luggage,
            legs: $legs,
            offers: $offers,
            paymentMethods: $this->availablePaymentMethods(),
        );

        $this->store->put($snapshot, $ttlMinutes * 60);

        return $snapshot;
    }

    private function assertSchedule(CarbonImmutable $pickupAt, CarbonImmutable $now): void
    {
        $minHours = $this->settings->int('min_lead_time_hours');

        if ($pickupAt->lt($now->addHours($minHours))) {
            throw BookingException::leadTime($minHours);
        }

        $maxDays = $this->settings->int('max_advance_days');

        if ($pickupAt->gt($now->addDays($maxDays))) {
            throw BookingException::tooFarAhead($maxDays);
        }
    }

    private function assertReturnGap(CarbonImmutable $outboundEnd, CarbonImmutable $returnAt): void
    {
        if (! $this->settings->bool('round_trip_enabled')) {
            throw BookingException::roundTripDisabled();
        }

        $minGap = $this->settings->int('round_trip_min_return_gap_minutes');

        if ($returnAt->lt($outboundEnd->addMinutes($minGap))) {
            throw BookingException::returnTooEarly($minGap);
        }
    }

    /** @return array<string, mixed> */
    private function leg(
        string $direction,
        string $fromPlaceId,
        string $toPlaceId,
        string $pickupAddress,
        string $dropoffAddress,
        CarbonImmutable $startsAt,
        RouteMetrics $route,
    ): array {
        return [
            'direction' => $direction,
            // Only the Place ID comes from Google. Address text is customer-confirmed input.
            'pickup' => ['place_id' => $fromPlaceId, 'address' => $pickupAddress],
            'dropoff' => ['place_id' => $toPlaceId, 'address' => $dropoffAddress],
            'starts_at' => $startsAt->toIso8601String(),
            'distance_m' => $route->distanceM,
            'duration_s' => $route->durationS,
        ];
    }

    /** @return list<string> */
    private function availablePaymentMethods(): array
    {
        $methods = [];

        if ($this->settings->bool('online_payment_enabled')) {
            $methods[] = PaymentMethod::Online->value;
        }

        if ($this->settings->bool('cash_on_arrival_enabled')) {
            $methods[] = PaymentMethod::CashOnArrival->value;
        }

        return $methods;
    }
}
