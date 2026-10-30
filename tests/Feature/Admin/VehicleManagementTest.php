<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
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

    private function manager(): \App\Models\User
    {
        $user = \App\Models\User::factory()->create(['is_active' => true]);
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
