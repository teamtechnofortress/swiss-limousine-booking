<?php

namespace Tests\Feature\Public;

use App\Models\Trip;
use App\Models\Location;
use App\Models\TripPrice;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TripDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    public function test_an_instantly_bookable_trip_exposes_the_online_booking_action(): void
    {
        $trip = $this->publishedTrip(['pricing_mode' => 'fixed', 'pickup_locations_only' => true]);
        $pickup = Location::query()->create([
            'name' => 'Zurich Airport',
            'slug' => 'zurich-airport',
            'type' => 'airport',
            'google_place_id' => 'place-zurich-airport',
            'formatted_address' => 'Zurich Airport, Switzerland',
            'country_code' => 'CH',
            'latitude' => 47.4581,
            'longitude' => 8.5555,
            'is_active' => true,
        ]);
        $trip->tripLocations()->create([
            'location_id' => $pickup->id,
            'role' => 'pickup',
            'duration_minutes' => 420,
        ]);
        $category = VehicleCategory::query()->create(['name' => 'Executive', 'slug' => 'executive', 'is_active' => true]);
        $vehicle = Vehicle::query()->create([
            'name' => 'Mercedes E-Class',
            'slug' => 'mercedes-e-class',
            'category_id' => $category->id,
            'passenger_capacity' => 3,
            'luggage_capacity' => 3,
            'price_per_km' => 350,
            'minimum_fare' => 8000,
            'slot_check_enabled' => false,
            'is_active' => true,
        ]);
        TripPrice::query()->create([
            'trip_id' => $trip->id,
            'pickup_location_id' => $pickup->id,
            'vehicle_id' => $vehicle->id,
            'price_minor' => 42500,
            'is_active' => true,
        ]);

        $this->getJson("/api/public/trips/{$trip->slug}")
            ->assertOk()
            ->assertJsonPath('data.pricing_mode', 'fixed')
            ->assertJsonPath('data.booking.start_booking_allowed', true)
            ->assertJsonPath('data.booking.reason', null)
            ->assertJsonPath('data.booking.departure_locations.0.id', $pickup->id)
            ->assertJsonPath('data.booking.departure_locations.0.duration_minutes', 420)
            ->assertJsonPath('data.booking.departure_locations.0.starting_from_price_minor', 42500)
            ->assertJsonPath('data.booking.starting_from_price_minor', 42500);

        $this->getJson("/api/public/trips/{$trip->slug}/pickup-config")
            ->assertOk()
            ->assertJsonPath('data.mode', 'selected')
            ->assertJsonPath('data.custom_allowed', false)
            ->assertJsonPath('data.locations.0.duration_minutes', 420)
            ->assertJsonPath('data.locations.0.place_id', 'place-zurich-airport');

        $this->getJson("/api/public/trips/{$trip->slug}/journey-config")
            ->assertOk()
            ->assertJsonPath('data.start_booking_allowed', true)
            ->assertJsonPath('data.reason', null)
            ->assertJsonPath('data.schedule.mode', 'custom')
            ->assertJsonPath('data.travellers.selection_required', true)
            ->assertJsonPath('data.travellers.limits_applied', true)
            ->assertJsonPath('data.travellers.min', 1)
            ->assertJsonPath('data.travellers.max', 8)
            ->assertJsonPath('data.pricing.mode', 'fixed')
            ->assertJsonPath('data.pricing.starting_from_price_minor', 42500)
            ->assertJsonPath('data.quote_endpoint', "/api/public/trips/{$trip->slug}/quotes");
    }

    public function test_an_enquiry_only_trip_hides_online_booking_but_still_accepts_enquiries(): void
    {
        $trip = $this->publishedTrip(['pricing_mode' => 'enquiry']);

        $this->getJson("/api/public/trips/{$trip->slug}")
            ->assertOk()
            ->assertJsonPath('data.pricing_mode', 'enquiry')
            ->assertJsonPath('data.booking.start_booking_allowed', false)
            ->assertJsonPath('data.booking.reason', 'enquiry_only');

        $this->postJson("/api/public/trips/{$trip->slug}/enquiries", [
            'name' => 'Test Customer',
            'email' => 'customer@example.test',
            'phone' => '+41 44 123 45 67',
            'message' => 'Please send me a proposal.',
        ])->assertCreated();

        $this->assertDatabaseHas('enquiries', [
            'trip_id' => $trip->id,
            'customer_email' => 'customer@example.test',
        ]);
    }

    public function test_a_distance_priced_trip_with_custom_pickup_can_start_online_booking(): void
    {
        $trip = $this->publishedTrip(['pricing_mode' => 'distance', 'pickup_locations_only' => false, 'traveller_limits_enabled' => false]);
        $destination = Location::query()->create([
            'name' => 'Lake Lucerne',
            'slug' => 'lake-lucerne-destination',
            'type' => 'landmark',
            'google_place_id' => 'place-lake-lucerne',
            'formatted_address' => 'Lake Lucerne, Switzerland',
            'country_code' => 'CH',
            'latitude' => 47.0502,
            'longitude' => 8.3093,
            'is_active' => true,
        ]);
        $trip->tripLocations()->create(['location_id' => $destination->id, 'role' => 'main_destination']);

        $this->getJson("/api/public/trips/{$trip->slug}/pickup-config")
            ->assertOk()
            ->assertJsonPath('data.mode', 'custom')
            ->assertJsonPath('data.custom_allowed', true)
            ->assertJsonPath('data.locations', []);

        $this->getJson("/api/public/trips/{$trip->slug}/journey-config")
            ->assertOk()
            ->assertJsonPath('data.start_booking_allowed', true)
            ->assertJsonPath('data.travellers.limits_applied', false)
            ->assertJsonPath('data.travellers.min', 1)
            ->assertJsonPath('data.travellers.max', 99)
            ->assertJsonPath('data.pricing.mode', 'route_based')
            ->assertJsonPath('data.quote_endpoint', "/api/public/trips/{$trip->slug}/quotes");
    }

    /** @param array<string, mixed> $overrides */
    private function publishedTrip(array $overrides = []): Trip
    {
        return Trip::query()->create($overrides + [
            'slug' => 'lake-lucerne-'.str()->random(8),
            'title' => 'Lake Lucerne Experience',
            'summary' => 'A private day trip around Lake Lucerne.',
            'region' => 'Lucerne',
            'status' => 'published',
            'pricing_mode' => 'fixed',
            'min_travellers' => 1,
            'max_travellers' => 8,
            'published_at' => now(),
        ]);
    }
}
