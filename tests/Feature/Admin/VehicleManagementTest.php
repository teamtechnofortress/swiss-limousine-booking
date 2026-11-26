<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\Rbac\RbacSynchronizer;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class VehicleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        app(RbacSynchronizer::class)->sync();
        $this->seed(SettingsSeeder::class);
        Sanctum::actingAs($this->manager());
    }

    private function manager(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('manager');

        return $user;
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        $category = VehicleCategory::query()->where('slug', 'executive')->firstOrFail();

        return $overrides + [
            'name' => 'Mercedes E-Class',
            'category_id' => $category->id,
            'passenger_capacity' => 3,
            'luggage_capacity' => 3,
            'price_per_km' => 350,
            'minimum_fare' => 8000,
            'slot_check_enabled' => true,
            'units' => ['zh 123 456'],
        ];
    }

    public function test_vehicle_creation_normalises_units_and_starts_as_a_draft(): void
    {
        $this->postJson('/api/admin/vehicles', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.units.0.plate', 'ZH123456');
    }

    public function test_new_vehicles_disable_physical_slot_checks_by_default(): void
    {
        $payload = $this->payload();
        unset($payload['slot_check_enabled']);

        $this->postJson('/api/admin/vehicles', $payload)
            ->assertCreated()
            ->assertJsonPath('data.slot_check_enabled', false);
    }

    public function test_vehicle_can_use_base_fare_and_continuous_excess_distance_ranges(): void
    {
        $payload = $this->payload([
            'range_pricing_enabled' => true,
            'range_base_distance_km' => 30,
            'range_base_fare' => 8000,
            'pricing_ranges' => [
                ['end_km' => 50, 'price_per_km' => 300, 'minimum_amount' => 5000],
                ['end_km' => null, 'price_per_km' => 400],
            ],
        ]);

        $this->postJson('/api/admin/vehicles', $payload)
            ->assertCreated()
            ->assertJsonPath('data.pricing_mode', 'range')
            ->assertJsonPath('data.range_base_distance_m', 30000)
            ->assertJsonPath('data.range_base_fare', 8000)
            ->assertJsonPath('data.distance_price_ranges.0.start_distance_m', 0)
            ->assertJsonPath('data.distance_price_ranges.1.start_distance_m', 20000)
            ->assertJsonPath('data.distance_price_ranges.1.minimum_amount', 6000)
            ->assertJsonPath('data.distance_price_ranges.1.end_distance_m', null);
    }

    public function test_price_tester_returns_the_saved_vehicle_price_for_the_selected_distance(): void
    {
        $this->postJson('/api/admin/vehicles', $this->payload())
            ->assertCreated();
        $simpleVehicle = Vehicle::query()->firstOrFail();

        $this->postJson('/api/admin/vehicles', $this->payload([
            'name' => 'Mercedes S-Class',
            'units' => ['ZH 654 321'],
            'range_pricing_enabled' => true,
            'range_base_distance_km' => 30,
            'range_base_fare' => 8000,
            'pricing_ranges' => [
                ['end_km' => 50, 'price_per_km' => 300, 'minimum_amount' => 5000],
                ['end_km' => null, 'price_per_km' => 400],
            ],
        ]))->assertCreated();
        $rangeVehicle = Vehicle::query()->where('name', 'Mercedes S-Class')->firstOrFail();
        $this->actingAs($this->manager());

        // 60 km × CHF 3.50/km is CHF 210.00, above the CHF 80.00 minimum.
        $this->postJson("/admin/vehicles/{$simpleVehicle->id}/pricing-test", ['distance_km' => 60])
            ->assertOk()
            ->assertJsonPath('data.distance_km', 60)
            ->assertJsonPath('data.amount_minor', 21000)
            ->assertJsonPath('data.formatted_amount', 'CHF 210.00');

        // 70 km has 40 km beyond the base and uses the CHF 4.00/km range.
        $this->postJson("/admin/vehicles/{$rangeVehicle->id}/pricing-test", ['distance_km' => 70])
            ->assertOk()
            ->assertJsonPath('data.amount_minor', 24000)
            ->assertJsonPath('data.formatted_amount', 'CHF 240.00');
    }

    public function test_slot_checks_require_a_unit_before_publishing(): void
    {
        $this->postJson('/api/admin/vehicles', $this->payload(['units' => []]))->assertCreated();
        $vehicle = Vehicle::query()->firstOrFail();

        $this->patchJson("/api/admin/vehicles/{$vehicle->id}", ['is_active' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('units');
    }

    public function test_slot_check_off_vehicle_is_bookable_without_a_unit_but_no_price_is_not(): void
    {
        $bookable = Vehicle::query()->create([
            'name' => 'Manual availability', 'slug' => 'manual-availability', 'category_id' => VehicleCategory::query()->where('slug', 'executive')->value('id'),
            'passenger_capacity' => 3, 'luggage_capacity' => 3, 'price_per_km' => 350,
            'slot_check_enabled' => false, 'is_active' => true,
        ]);
        Vehicle::query()->create([
            'name' => 'Price request', 'slug' => 'price-request', 'category_id' => VehicleCategory::query()->where('slug', 'executive')->value('id'),
            'passenger_capacity' => 3, 'luggage_capacity' => 3, 'price_per_km' => null,
            'slot_check_enabled' => false, 'is_active' => true,
        ]);

        $this->assertTrue(Vehicle::query()->bookable()->whereKey($bookable->id)->exists());
        $this->assertSame(1, Vehicle::query()->bookable()->count());
    }

    public function test_public_fleet_detail_exposes_customer_safe_media_pricing_and_availability(): void
    {
        $vehicle = Vehicle::query()->create([
            'name' => 'Public S-Class', 'slug' => 'public-s-class', 'category_id' => VehicleCategory::query()->where('slug', 'executive')->value('id'),
            'passenger_capacity' => 3, 'luggage_capacity' => 2, 'price_per_km' => 475, 'minimum_fare' => 12000,
            'amenities' => ['Wi-Fi', 'Bottled water'], 'slot_check_enabled' => false, 'is_active' => true,
        ]);

        $this->getJson("/api/public/vehicles/{$vehicle->slug}")
            ->assertOk()
            ->assertJsonPath('data.capacity.passengers', 3)
            ->assertJsonPath('data.pricing.mode', 'distance')
            ->assertJsonPath('data.display_fare_minor', 12000)
            ->assertJsonPath('data.display_fare_type', 'minimum')
            ->assertJsonPath('data.pricing.minimum_fare_minor', 12000)
            ->assertJsonMissingPath('data.price_per_km')
            ->assertJsonMissingPath('data.pricing.price_per_km_minor')
            ->assertJsonPath('data.availability.online_booking_enabled', true)
            ->assertJsonPath('data.media.cover', null)
            ->assertJsonCount(0, 'data.media.gallery');
    }

    public function test_duplicate_plates_are_rejected_after_normalisation(): void
    {
        $this->postJson('/api/admin/vehicles', $this->payload())->assertCreated();

        $this->postJson('/api/admin/vehicles', $this->payload([
            'name' => 'Mercedes V-Class',
            'units' => ['ZH123456'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('units');
    }

    public function test_vehicle_with_booking_history_cannot_be_deleted_and_inactive_vehicle_is_not_public(): void
    {
        $vehicle = Vehicle::query()->create([
            'name' => 'Hidden S-Class', 'slug' => 'hidden-s-class', 'category_id' => VehicleCategory::query()->where('slug', 'first-class')->value('id'),
            'passenger_capacity' => 3, 'luggage_capacity' => 3, 'price_per_km' => 470,
            'slot_check_enabled' => false, 'is_active' => false,
        ]);
        Booking::query()->create([
            'reference' => 'LX-ABCDE', 'status' => 'confirmed', 'source' => 'website', 'payment_method' => 'cash',
            'vehicle_id' => $vehicle->id, 'customer_name' => 'Test Customer', 'customer_email' => 'test@example.com',
            'customer_phone' => '41000000000', 'passengers' => 1, 'luggage' => 0, 'currency' => 'CHF',
            'taxable_amount' => 100, 'vat_rate_bp' => 0, 'vat_amount' => 0, 'total_amount' => 100, 'vat_included' => true,
            'price_breakdown' => [], 'access_token_hash' => str_repeat('a', 64),
        ]);

        $this->deleteJson("/api/admin/vehicles/{$vehicle->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle');
        $this->getJson('/api/public/vehicles/hidden-s-class')->assertNotFound();
    }

    public function test_category_is_resolved_by_vehicle_id_and_cannot_be_deleted_while_in_use(): void
    {
        $category = VehicleCategory::query()->where('slug', 'executive')->firstOrFail();
        $vehicle = Vehicle::query()->create([
            'name' => 'Category test', 'slug' => 'category-test', 'category_id' => $category->id,
            'passenger_capacity' => 3, 'luggage_capacity' => 3, 'price_per_km' => 350,
            'slot_check_enabled' => false, 'is_active' => true,
        ]);

        $this->getJson("/api/admin/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.category.name', 'Executive');
        $this->deleteJson("/api/admin/vehicle-categories/{$category->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');
    }
}
