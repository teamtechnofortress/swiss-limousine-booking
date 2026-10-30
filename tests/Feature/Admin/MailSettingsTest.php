<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Services\Rbac\RbacSynchronizer;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        app(RbacSynchronizer::class)->sync();
        $this->seed(SettingsSeeder::class);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_manager_cannot_read_or_change_smtp_settings(): void
    {
        Sanctum::actingAs($this->user('manager'));

        $this->getJson('/api/admin/integrations/mail')->assertForbidden();
        $this->putJson('/api/admin/integrations/mail', ['mailer' => 'log', 'encryption' => 'none'])
            ->assertForbidden();
    }

    public function test_super_admin_saves_smtp_settings_and_the_password_is_encrypted_and_hidden(): void
    {
        Sanctum::actingAs($this->user('super_admin'));

        $this->putJson('/api/admin/integrations/mail', [
            'mailer' => 'smtp',
            'host' => 'smtp.example.ch',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'bookings@example.ch',
            'password' => 'Very-Secret-Pass-123!',
        ])
            ->assertOk()
            ->assertJsonPath('data.host', 'smtp.example.ch')
            ->assertJsonPath('data.password_set', true)
            ->assertJsonMissingPath('data.password');

        $stored = Setting::query()->where('key', 'smtp_password')->value('value');
        $this->assertNotSame('Very-Secret-Pass-123!', $stored);
    }

    public function test_an_empty_password_keeps_the_saved_one(): void
    {
        Sanctum::actingAs($this->user('super_admin'));

        $this->putJson('/api/admin/integrations/mail', [
            'mailer' => 'smtp', 'host' => 'smtp.example.ch', 'port' => 587, 'encryption' => 'tls',
            'password' => 'First-Password-123!',
        ])->assertOk();

        $before = Setting::query()->where('key', 'smtp_password')->value('value');

        $this->putJson('/api/admin/integrations/mail', [
            'mailer' => 'smtp', 'host' => 'smtp.example.ch', 'port' => 587, 'encryption' => 'tls',
            'password' => '',
        ])->assertOk();

        $this->assertSame($before, Setting::query()->where('key', 'smtp_password')->value('value'));
    }

    public function test_manager_can_update_sender_identity_without_smtp_access(): void
    {
        Sanctum::actingAs($this->user('manager'));

        $this->putJson('/api/admin/company/sender', [
            'from_address' => 'bookings@example.ch',
            'from_name' => 'Swiss Limousine',
        ])
            ->assertOk()
            ->assertJsonPath('data.from_address', 'bookings@example.ch')
            ->assertJsonPath('data.from_name', 'Swiss Limousine');
    }

    public function test_web_settings_preserve_blank_secrets_and_accept_blank_optional_values(): void
    {
        $this->actingAs($this->user('super_admin'));

        $this->put('/admin/settings/integrations', [
            'settings' => [
                'stripe_mode' => 'test',
                'stripe_secret_key' => '',
                'stripe_publishable_key' => '',
                'stripe_webhook_secret' => '',
                'google_browser_key' => 'dummy-browser-key',
                'google_server_key' => '',
                'turnstile_secret_key' => '',
                'mail_mailer' => 'smtp',
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'smtp_username' => '',
                'smtp_password' => '',
            ],
        ])->assertRedirect('/admin/settings/integrations')
            ->assertSessionHasNoErrors();
    }
}
