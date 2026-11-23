<?php

namespace App\Services\Pricing;

use App\Data\PriceBreakdown;
use App\Data\RouteMetrics;
use App\Models\Vehicle;

/** Pure pricing: no database and no settings. Inputs in, breakdown out. */
final class PricingService
{
    public function price(
        Vehicle $vehicle,
        RouteMetrics $outboundRoute,
        bool $roundTrip,
        int $discountPercent,
        ?RouteMetrics $returnRoute = null,
    ): PriceBreakdown {
        $outbound = $this->legAmount($vehicle, $outboundRoute);

        if (! $roundTrip) {
            return new PriceBreakdown(['outbound' => $outbound], 0, $outbound);
        }

        $return = $this->legAmount($vehicle, $returnRoute ?? $outboundRoute);
        $gross = $outbound + $return;
        $discount = $discountPercent > 0 ? (int) round($gross * $discountPercent / 100) : 0;

        return new PriceBreakdown(
            legs: ['outbound' => $outbound, 'return' => $return],
            discount: $discount,
            subtotal: $gross - $discount,
        );
    }

    /** max(distance × rate, minimum fare), rounded up to the next rappen. */
    public function legAmount(Vehicle $vehicle, RouteMetrics $route): int
    {
        if ($vehicle->pricing_mode === 'range') {
            return $this->rangeLegAmount($vehicle, $route);
        }

        $perKm = (int) ceil($route->distanceM * (int) $vehicle->price_per_km / 1000);

        return max($perKm, (int) $vehicle->minimum_fare);
    }

    /**
     * The base fare covers the included base distance. Tiers are evaluated against
     * only the excess distance. Their minimum protects the tier amount, then the
     * base fare is added once.
     */
    private function rangeLegAmount(Vehicle $vehicle, RouteMetrics $route): int
    {
        $baseDistanceM = (int) $vehicle->range_base_distance_m;
        $baseFare = (int) $vehicle->range_base_fare;
        if ($route->distanceM <= $baseDistanceM) {
            return $baseFare;
        }

        $excessM = $route->distanceM - $baseDistanceM;
        $range = $vehicle->distancePriceRanges->first(
            fn ($item) => $item->end_distance_m === null || $excessM <= $item->end_distance_m,
        );
        if ($range === null) {
            throw new \LogicException('Range-priced vehicles require a final open-ended distance range.');
        }

        $calculated = (int) ceil($excessM * (int) $range->price_per_km / 1000);

        return $baseFare + max($calculated, (int) $range->minimum_amount);
    }
}
