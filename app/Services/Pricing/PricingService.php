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
        $perKm = (int) ceil($route->distanceM * (int) $vehicle->price_per_km / 1000);

        return max($perKm, (int) $vehicle->minimum_fare);
    }
}
