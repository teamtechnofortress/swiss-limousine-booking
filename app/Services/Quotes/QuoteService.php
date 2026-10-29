<?php

namespace App\Services\Quotes;

use App\Data\PlaceData;
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

        $pickup = $this->maps->placeDetails($request->pickupPlaceId);
        $dropoff = $this->maps->placeDetails($request->dropoffPlaceId);
        $this->serviceArea->assertAllowed($pickup, $dropoff);

        $route = $this->routes->metrics($pickup->placeId, $dropoff->placeId);
        $legs = [$this->leg('outbound', $pickup, $dropoff, $request->pickupAt, $route)];

        $returnAt = $request->returnAt;

        if ($returnAt !== null) {
            $this->assertReturnGap($request->pickupAt->addSeconds($route->durationS), $returnAt);
            // Distance is symmetric, so the return leg reuses the outbound metrics.
            $legs[] = $this->leg('return', $dropoff, $pickup, $returnAt, $route);
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

        foreach ($this->vehicles->bookableFor($request->passengers, $request->luggage) as $vehicle) {
            if (! $this->availability->windowsFree($vehicle, $windows)) {
                continue;
            }

            $price = $this->pricing->price($vehicle, $route, $returnAt !== null, $discountPercent);
            $split = $this->vat->split($price->subtotal);

            $offers[$vehicle->id] = [
                'vehicle_id' => $vehicle->id,
                'name' => $vehicle->name,
                'category' => $vehicle->category->value,
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
    private function leg(string $direction, PlaceData $from, PlaceData $to, CarbonImmutable $startsAt, RouteMetrics $route): array
    {
        return [
            'direction' => $direction,
            'pickup' => $from->toArray(),
            'dropoff' => $to->toArray(),
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
