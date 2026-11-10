<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Rbac\RbacSynchronizer;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TaxSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RbacSynchronizer::class)->sync();
        $this->seed(SettingsSeeder::class);
    }

    public function test_vat_configuration_is_grouped_under_the_vat_master_switch(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin/settings/tax')
            ->assertOk()
            ->assertSeeInOrder(['Currency', 'Invoice Number Prefix', 'Vat Enabled', 'VAT configuration'])
            ->assertSee('data-vat-master', false)
            ->assertSee('data-vat-scope', false)
            ->assertSee('data-vat-disabled-notice', false)
            ->assertSee('VAT is disabled. These saved settings are retained', false);
    }

    private function superAdmin(): User
    {
        $user = User::query()->create([
            'name' => 'Tax Administrator',
            'email' => 'tax-admin@example.test',
            'password' => 'Test-Password-2026!',
            'is_active' => true,
        ]);
        $user->assignRole('super_admin');

        return $user;
    }
}
