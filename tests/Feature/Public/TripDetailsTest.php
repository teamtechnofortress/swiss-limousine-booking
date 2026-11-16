<?php

namespace Tests\Feature\Public;

use App\Models\Location;
use App\Models\Trip;
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

        $this->getJson("/api/public/trips/{$trip->slug}/journey-config")
            ->assertOk()
            ->assertJsonPath('data.start_booking_allowed', true)
            ->assertJsonPath('data.reason', null)
            ->assertJsonPath('data.pickup.mode', 'selected')
            ->assertJsonPath('data.pickup.custom_allowed', false)
            ->assertJsonPath('data.pickup.locations.0.duration_minutes', 420)
            ->assertJsonPath('data.pickup.locations.0.place_id', 'place-zurich-airport')
            ->assertJsonPath('data.schedule.mode', 'custom')
            ->assertJsonPath('data.travellers.selection_required', true)
            ->assertJsonPath('data.travellers.limits_applied', true)
            ->assertJsonPath('data.travellers.min', 1)
            ->assertJsonPath('data.travellers.max', 8)
            ->assertJsonPath('data.pricing.mode', 'fixed')
            ->assertJsonPath('data.pricing.starting_from_price_minor', 42500)
            ->assertJsonMissingPath('data.addons')
            ->assertJsonPath('data.quote_endpoint', "/api/public/trips/{$trip->slug}/quotes");

        $trip->update(['fixed_departures_only' => true]);

        $this->getJson("/api/public/trips/{$trip->slug}")
            ->assertOk()
            ->assertJsonPath('data.booking.start_booking_allowed', false)
            ->assertJsonPath('data.booking.reason', 'schedule_configuration_required');

        $trip->departures()->create(['start_time' => '09:00', 'is_active' => true, 'sort_order' => 0]);

        $this->getJson("/api/public/trips/{$trip->slug}")
            ->assertOk()
            ->assertJsonPath('data.booking.start_booking_allowed', true)
            ->assertJsonPath('data.booking.reason', null);

        $vehicle->delete();

        $this->getJson("/api/public/trips/{$trip->slug}")
            ->assertOk()
            ->assertJsonPath('data.booking.start_booking_allowed', false)
            ->assertJsonPath('data.booking.reason', 'fixed_pricing_configuration_required');
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
        $category = VehicleCategory::query()->create(['name' => 'Van', 'slug' => 'van', 'is_active' => true]);
        Vehicle::query()->create([
            'name' => 'Mercedes V-Class',
            'slug' => 'mercedes-v-class',
            'category_id' => $category->id,
            'passenger_capacity' => 7,
            'luggage_capacity' => 7,
            'price_per_km' => 380,
            'minimum_fare' => 8000,
            'slot_check_enabled' => false,
            'is_active' => true,
        ]);

        $this->getJson("/api/public/trips/{$trip->slug}/journey-config")
            ->assertOk()
            ->assertJsonPath('data.start_booking_allowed', true)
            ->assertJsonPath('data.pickup.mode', 'custom')
            ->assertJsonPath('data.pickup.custom_allowed', true)
            ->assertJsonPath('data.pickup.locations', [])
            ->assertJsonPath('data.travellers.limits_applied', false)
            ->assertJsonPath('data.travellers.min', 1)
            ->assertJsonPath('data.travellers.max', 7)
            ->assertJsonPath('data.travellers.max_by_pickup_location', [])
            ->assertJsonPath('data.pricing.mode', 'route_based')
            ->assertJsonPath('data.quote_endpoint', "/api/public/trips/{$trip->slug}/quotes");
    }

    public function test_an_unrestricted_fixed_trip_exposes_the_highest_eligible_capacity_for_each_pickup(): void
    {
        $trip = $this->publishedTrip(['traveller_limits_enabled' => false]);
        $zurich = $this->pickup($trip, 'Zurich Airport', 'zurich-airport');
        $lucerne = $this->pickup($trip, 'Lucerne Station', 'lucerne-station');
        $category = VehicleCategory::query()->create(['name' => 'Van', 'slug' => 'van', 'is_active' => true]);
        $saloon = $this->vehicle($category, 'Mercedes E-Class', 'mercedes-e-class', 3);
        $van = $this->vehicle($category, 'Mercedes V-Class', 'mercedes-v-class', 7);
        TripPrice::query()->create(['trip_id' => $trip->id, 'pickup_location_id' => $zurich->id, 'vehicle_id' => $saloon->id, 'price_minor' => 42500, 'is_active' => true]);
        TripPrice::query()->create(['trip_id' => $trip->id, 'pickup_location_id' => $zurich->id, 'vehicle_id' => $van->id, 'price_minor' => 55000, 'is_active' => true]);
        TripPrice::query()->create(['trip_id' => $trip->id, 'pickup_location_id' => $lucerne->id, 'vehicle_id' => $saloon->id, 'price_minor' => 40000, 'is_active' => true]);

        $this->getJson("/api/public/trips/{$trip->slug}/journey-config")
            ->assertOk()
            ->assertJsonPath('data.travellers.limits_applied', false)
            ->assertJsonPath('data.travellers.min', 1)
            ->assertJsonPath('data.travellers.max', 7)
            ->assertJsonPath("data.travellers.max_by_pickup_location.{$zurich->id}", 7)
            ->assertJsonPath("data.travellers.max_by_pickup_location.{$lucerne->id}", 3);
    }

    public function test_a_distance_priced_trip_without_an_eligible_vehicle_cannot_start_booking(): void
    {
        $trip = $this->publishedTrip(['pricing_mode' => 'distance', 'pickup_locations_only' => false, 'traveller_limits_enabled' => false]);
        $destination = Location::query()->create([
            'name' => 'Lake Lucerne',
            'slug' => 'lake-lucerne-no-vehicle',
            'type' => 'landmark',
            'google_place_id' => 'place-lake-lucerne-no-vehicle',
            'formatted_address' => 'Lake Lucerne, Switzerland',
            'country_code' => 'CH',
            'latitude' => 47.0502,
            'longitude' => 8.3093,
            'is_active' => true,
        ]);
        $trip->tripLocations()->create(['location_id' => $destination->id, 'role' => 'main_destination']);

        $this->getJson("/api/public/trips/{$trip->slug}/journey-config")
            ->assertOk()
            ->assertJsonPath('data.start_booking_allowed', false)
            ->assertJsonPath('data.reason', 'vehicle_configuration_required')
            ->assertJsonPath('data.travellers.max', null)
            ->assertJsonPath('data.quote_endpoint', null);
    }

    private function pickup(Trip $trip, string $name, string $slug): Location
    {
        $location = Location::query()->create([
            'name' => $name,
            'slug' => $slug.'-'.str()->random(6),
            'type' => 'station',
            'google_place_id' => 'place-'.$slug,
            'formatted_address' => $name.', Switzerland',
            'country_code' => 'CH',
            'latitude' => 47.0,
            'longitude' => 8.0,
            'is_active' => true,
        ]);
        $trip->tripLocations()->create(['location_id' => $location->id, 'role' => 'pickup', 'duration_minutes' => 120]);

        return $location;
    }

    private function vehicle(VehicleCategory $category, string $name, string $slug, int $passengerCapacity): Vehicle
    {
        return Vehicle::query()->create([
            'name' => $name,
            'slug' => $slug.'-'.str()->random(6),
            'category_id' => $category->id,
            'passenger_capacity' => $passengerCapacity,
            'luggage_capacity' => $passengerCapacity,
            'price_per_km' => 350,
            'minimum_fare' => 8000,
            'slot_check_enabled' => false,
            'is_active' => true,
        ]);
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
            'traveller_limits_enabled' => true,
            'published_at' => now(),
        ]);
    }
}
