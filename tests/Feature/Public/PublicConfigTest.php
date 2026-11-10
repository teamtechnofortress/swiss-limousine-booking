<?php

namespace Tests\Feature\Public;

use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
    }

    public function test_it_exposes_the_service_area_country_configuration_for_autocomplete(): void
    {
        $settings = app(SettingsService::class);
        $settings->update('service_area_enforced', false);
        $settings->update('allowed_countries', ['CH', 'DE', 'FR']);

        $this->getJson('/api/public/config')
            ->assertOk()
            ->assertJsonPath('data.service_area.enforced', false)
            ->assertJsonPath('data.service_area.allowed_countries', ['CH', 'DE', 'FR']);
    }
}
