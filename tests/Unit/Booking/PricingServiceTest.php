<?php

namespace Tests\Unit\Booking;

use App\Data\RouteMetrics;
use App\Models\Vehicle;
use App\Models\VehicleDistancePriceRange;
use App\Services\Pricing\PricingService;
use Tests\TestCase;

final class PricingServiceTest extends TestCase
{
    public function test_one_way_uses_the_per_km_rate(): void
    {
        $vehicle = new Vehicle(['price_per_km' => 350, 'minimum_fare' => 8000]);

        $price = (new PricingService)->price($vehicle, new RouteMetrics(60000, 3600), false, 0);

        $this->assertSame(21000, $price->subtotal);
    }

    public function test_minimum_fare_applies_to_short_trips(): void
    {
        $vehicle = new Vehicle(['price_per_km' => 350, 'minimum_fare' => 8000]);

        $price = (new PricingService)->price($vehicle, new RouteMetrics(5000, 600), false, 0);

        $this->assertSame(8000, $price->subtotal);
    }

    public function test_round_trip_discount_is_applied_to_the_total(): void
    {
        $vehicle = new Vehicle(['price_per_km' => 350, 'minimum_fare' => 8000]);

        $price = (new PricingService)->price($vehicle, new RouteMetrics(60000, 3600), true, 5);

        $this->assertSame(['outbound' => 21000, 'return' => 21000], $price->legs);
        $this->assertSame(2100, $price->discount);
        $this->assertSame(39900, $price->subtotal);
    }

    public function test_round_trip_prices_each_direction_independently(): void
    {
        $vehicle = new Vehicle(['price_per_km' => 200, 'minimum_fare' => 0]);
        $outbound = new RouteMetrics(10000, 600);
        $return = new RouteMetrics(12000, 720);

        $price = (new PricingService)->price($vehicle, $outbound, true, 0, $return);

        $this->assertSame(['outbound' => 2000, 'return' => 2400], $price->legs);
        $this->assertSame(4400, $price->subtotal);
    }

    public function test_range_pricing_uses_the_base_fare_before_the_included_distance(): void
    {
        $vehicle = $this->rangeVehicle();

        $price = (new PricingService)->price($vehicle, new RouteMetrics(30000, 1800), false, 0);

        $this->assertSame(8000, $price->subtotal);
    }

    public function test_range_pricing_uses_excess_distance_and_the_selected_range_minimum(): void
    {
        $vehicle = $this->rangeVehicle();
        $pricing = new PricingService;

        // 40 km total: 10 km beyond the 30 km base. The first tier's CHF 50.00
        // minimum is higher than the calculated CHF 30.00 tier amount.
        $this->assertSame(13000, $pricing->price($vehicle, new RouteMetrics(40000, 2400), false, 0)->subtotal);

        // 70 km total: 40 excess km selects the second tier (20–50 excess km).
        // Its CHF 6.00 minimum is lower than 40 × CHF 4.00, so CHF 160 is added.
        $this->assertSame(24000, $pricing->price($vehicle, new RouteMetrics(70000, 4200), false, 0)->subtotal);
    }

    private function rangeVehicle(): Vehicle
    {
        $vehicle = new Vehicle([
            'pricing_mode' => 'range',
            'range_base_distance_m' => 30000,
            'range_base_fare' => 8000,
        ]);
        $vehicle->setRelation('distancePriceRanges', collect([
            new VehicleDistancePriceRange(['start_distance_m' => 0, 'end_distance_m' => 20000, 'minimum_amount' => 5000, 'price_per_km' => 300, 'sort_order' => 0]),
            new VehicleDistancePriceRange(['start_distance_m' => 20000, 'end_distance_m' => null, 'minimum_amount' => 6000, 'price_per_km' => 400, 'sort_order' => 1]),
        ]));

        return $vehicle;
    }
}
