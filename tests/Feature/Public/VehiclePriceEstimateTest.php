<?php

namespace Tests\Feature\Public;

use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class VehiclePriceEstimateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    public function test_it_returns_prices_for_all_bookable_vehicles_at_the_requested_distance(): void
    {
        $category = VehicleCategory::query()->create([
            'name' => 'Executive',
            'slug' => 'executive',
            'is_active' => true,
        ]);
        $simpleVehicle = $this->vehicle($category, [
            'name' => 'Mercedes E-Class',
            'slug' => 'mercedes-e-class',
            'price_per_km' => 350,
            'minimum_fare' => 8000,
        ]);
        $rangeVehicle = $this->vehicle($category, [
            'name' => 'Mercedes S-Class',
            'slug' => 'mercedes-s-class',
            'pricing_mode' => 'range',
            'range_base_distance_m' => 30000,
            'range_base_fare' => 8000,
        ]);
        $rangeVehicle->distancePriceRanges()->createMany([
            ['start_distance_m' => 0, 'end_distance_m' => 20000, 'minimum_amount' => 5000, 'price_per_km' => 300, 'sort_order' => 0],
            ['start_distance_m' => 20000, 'end_distance_m' => null, 'minimum_amount' => 6000, 'price_per_km' => 400, 'sort_order' => 1],
        ]);
        $this->vehicle($category, [
            'name' => 'Inactive vehicle',
            'slug' => 'inactive-vehicle',
            'price_per_km' => 350,
            'is_active' => false,
        ]);

        $this->getJson('/api/public/vehicle-price-estimates?distance_km=70')
            ->assertOk()
            ->assertJsonPath('data.distance_km', 70)
            ->assertJsonPath('data.currency', 'CHF')
            ->assertJsonCount(2, 'data.vehicles')
            ->assertJsonPath('data.vehicles.0.id', $simpleVehicle->id)
            ->assertJsonPath('data.vehicles.0.thumbnail_url', null)
            ->assertJsonPath('data.vehicles.0.amount_minor', 24500)
            ->assertJsonPath('data.vehicles.0.formatted_amount', 'CHF 245.00')
            ->assertJsonPath('data.vehicles.1.id', $rangeVehicle->id)
            ->assertJsonPath('data.vehicles.1.amount_minor', 24000)
            ->assertJsonPath('data.vehicles.1.formatted_amount', 'CHF 240.00');
    }

    public function test_distance_is_required_and_must_be_non_negative(): void
    {
        $this->getJson('/api/public/vehicle-price-estimates')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('distance_km');

        $this->getJson('/api/public/vehicle-price-estimates?distance_km=-1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('distance_km');
    }

    public function test_it_optionally_filters_vehicles_by_passenger_and_luggage_capacity(): void
    {
        $category = VehicleCategory::query()->create([
            'name' => 'Executive',
            'slug' => 'executive',
            'is_active' => true,
        ]);
        $this->vehicle($category, [
            'name' => 'Mercedes E-Class',
            'slug' => 'mercedes-e-class',
            'price_per_km' => 350,
            'passenger_capacity' => 3,
            'luggage_capacity' => 2,
        ]);
        $suitableVehicle = $this->vehicle($category, [
            'name' => 'Mercedes V-Class',
            'slug' => 'mercedes-v-class',
            'price_per_km' => 500,
            'passenger_capacity' => 6,
            'luggage_capacity' => 6,
        ]);

        $this->getJson('/api/public/vehicle-price-estimates?distance_km=70&passengers=4&luggage=4')
            ->assertOk()
            ->assertJsonPath('data.passengers', 4)
            ->assertJsonPath('data.luggage', 4)
            ->assertJsonCount(1, 'data.vehicles')
            ->assertJsonPath('data.vehicles.0.id', $suitableVehicle->id);
    }

    /** @param array<string, mixed> $overrides */
    private function vehicle(VehicleCategory $category, array $overrides): Vehicle
    {
        return Vehicle::query()->create($overrides + [
            'category_id' => $category->id,
            'passenger_capacity' => 3,
            'luggage_capacity' => 3,
            'pricing_mode' => 'simple',
            'slot_check_enabled' => false,
            'is_active' => true,
        ]);
    }
}
