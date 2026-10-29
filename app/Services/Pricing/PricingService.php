<?php

namespace App\Services\Pricing;

use App\Data\PriceBreakdown;
use App\Data\RouteMetrics;
use App\Models\Vehicle;

/** Pure pricing: no database and no settings. Inputs in, breakdown out. */
final class PricingService
{
    public function price(Vehicle $vehicle, RouteMetrics $route, bool $roundTrip, int $discountPercent): PriceBreakdown
    {
        $oneWay = $this->legAmount($vehicle, $route);

        if (! $roundTrip) {
            return new PriceBreakdown(['outbound' => $oneWay], 0, $oneWay);
        }

        $gross = $oneWay * 2;
        $discount = $discountPercent > 0 ? (int) round($gross * $discountPercent / 100) : 0;

        return new PriceBreakdown(
            legs: ['outbound' => $oneWay, 'return' => $oneWay],
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
