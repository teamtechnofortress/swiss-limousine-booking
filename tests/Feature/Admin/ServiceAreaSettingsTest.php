<?php

namespace Tests\Feature\Admin;

use App\Models\Location;
use App\Models\Setting;
use App\Models\User;
use App\Services\Rbac\RbacSynchronizer;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

final class ServiceAreaSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        app(RbacSynchronizer::class)->sync();
        $this->seed(SettingsSeeder::class);
    }

    public function test_required_location_selector_uses_the_live_saved_location_picker(): void
    {
        $this->location('Zurich Airport', true);
        $this->location('Retired Depot', false);

        $this->actingAs($this->serviceAreaManager())
            ->get('/admin/settings/service-area')
            ->assertOk()
            ->assertSee('data-saved-location-picker', false)
            ->assertSee(route('admin.locations.saved-search'), false)
            ->assertDontSee('Retired Depot');
    }

    public function test_saved_location_search_returns_matching_active_catalogue_locations(): void
    {
        $active = $this->location('Zurich Airport', true);
        $this->location('Zurich Retired Depot', false);

        $this->actingAs($this->serviceAreaManager())
            ->getJson('/admin/locations/saved-search?q=zurich')
            ->assertOk()
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_required_location_rule_rejects_an_inactive_or_unknown_location(): void
    {
        $inactive = $this->location('Inactive location', false);

        $this->actingAs($this->serviceAreaManager())
            ->from('/admin/settings/service-area')
            ->put('/admin/settings/service-area', ['settings' => $this->serviceAreaPayload($inactive->id)])
            ->assertRedirect('/admin/settings/service-area')
            ->assertSessionHasErrors('settings.required_location_id');
    }

    public function test_required_location_rule_saves_an_active_saved_location(): void
    {
        $active = $this->location('Zurich Airport', true);

        $this->actingAs($this->serviceAreaManager())
            ->put('/admin/settings/service-area', ['settings' => $this->serviceAreaPayload($active->id)])
            ->assertRedirect('/admin/settings/service-area')
            ->assertSessionHasNoErrors();

        $this->assertSame((string) $active->id, Setting::query()->where('key', 'required_location_id')->value('value'));
    }

    private function serviceAreaManager(): User
    {
        $user = User::query()->create([
            'name' => 'Service Area Manager',
            'email' => 'service-area-manager@example.test',
            'password' => 'Test-Password-2026!',
            'is_active' => true,
        ]);
        $user->assignRole('manager');

        return $user;
    }

    private function location(string $name, bool $active): Location
    {
        return Location::query()->create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'type' => 'place',
            'google_place_id' => 'place-'.str($name)->slug(),
            'formatted_address' => $name.', Switzerland',
            'country_code' => 'CH',
            'locality' => 'Zurich',
            'admin_area_code' => 'ZH',
            'admin_area_name' => 'Zurich',
            'latitude' => 47.4581,
            'longitude' => 8.5555,
            'is_active' => $active,
        ]);
    }

    /** @return array<string, mixed> */
    private function serviceAreaPayload(int $requiredLocationId): array
    {
        return [
            'service_area_enforced' => '1',
            'allowed_countries' => json_encode(['CH']),
            'allowed_country_names' => json_encode(['CH' => 'Switzerland']),
            'required_location_enabled' => '1',
            'required_location_id' => (string) $requiredLocationId,
            'required_location_match_type' => 'radius',
            'required_location_radius_km' => '25',
        ];
    }
}
