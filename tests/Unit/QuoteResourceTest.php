<?php

namespace Tests\Unit;

use App\Data\QuoteSnapshot;
use App\Http\Resources\QuoteResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Tests\TestCase;

final class QuoteResourceTest extends TestCase
{
    public function test_it_includes_per_leg_and_total_route_metrics(): void
    {
        $quote = new QuoteSnapshot(
            token: str_repeat('a', 64),
            expiresAt: CarbonImmutable::parse('2026-11-02 10:00:00 UTC'),
            currency: 'CHF',
            passengers: 1,
            luggage: 1,
            legs: [
                [
                    'direction' => 'outbound',
                    'pickup' => ['place_id' => 'pickup-id', 'address' => 'Zürich, Switzerland'],
                    'dropoff' => ['place_id' => 'dropoff-id', 'address' => 'Bern, Switzerland'],
                    'starts_at' => '2026-11-02T09:00:00+00:00',
                    'distance_m' => 125_400,
                    'duration_s' => 5_130,
                ],
            ],
            offers: [
                7 => [
                    'vehicle_id' => 7,
                    'name' => 'BMW 7 series',
                    'thumbnail_url' => 'https://example.test/media/bmw-7-series-thumb.webp',
                    'passenger_capacity' => 3,
                    'luggage_capacity' => 2,
                    'capacity' => ['passengers' => 3, 'luggage' => 2],
                ],
            ],
            paymentMethods: ['online'],
        );

        $response = (new QuoteResource($quote))->toArray(Request::create('/'));

        $this->assertSame([
            'distance_m' => 125_400,
            'duration_s' => 5_130,
            'distance_km' => 125.4,
            'duration_minutes' => 86,
        ], $response['legs'][0]['route']);
        $this->assertSame($response['legs'][0]['route'], $response['route_summary']);
        $this->assertSame(
            'https://example.test/media/bmw-7-series-thumb.webp',
            $response['offers'][7]['thumbnail_url'],
        );
        $this->assertSame(3, $response['offers'][7]['passenger_capacity']);
        $this->assertSame(2, $response['offers'][7]['luggage_capacity']);
        $this->assertSame(['passengers' => 3, 'luggage' => 2], $response['offers'][7]['capacity']);
    }
}
