<?php

namespace Tests\Unit\Booking;

use App\Data\RouteMetrics;
use App\Models\Vehicle;
use App\Services\Pricing\PricingService;
use Tests\TestCase;

final class PricingServiceTest extends TestCase
{
    public function test_one_way_uses_the_per_km_rate(): void
    {
        $vehicle = new Vehicle(['price_per_km' => 350, 'minimum_fare' => 8000]);

        $price = (new PricingService())->price($vehicle, new RouteMetrics(60000, 3600), false, 0);

        $this->assertSame(21000, $price->subtotal);
    }

    public function test_minimum_fare_applies_to_short_trips(): void
    {
        $vehicle = new Vehicle(['price_per_km' => 350, 'minimum_fare' => 8000]);

        $price = (new PricingService())->price($vehicle, new RouteMetrics(5000, 600), false, 0);

        $this->assertSame(8000, $price->subtotal);
    }

    public function test_round_trip_discount_is_applied_to_the_total(): void
    {
        $vehicle = new Vehicle(['price_per_km' => 350, 'minimum_fare' => 8000]);

        $price = (new PricingService())->price($vehicle, new RouteMetrics(60000, 3600), true, 5);

        $this->assertSame(['outbound' => 21000, 'return' => 21000], $price->legs);
        $this->assertSame(2100, $price->discount);
        $this->assertSame(39900, $price->subtotal);
    }
}
