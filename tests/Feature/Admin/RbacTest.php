<?php

namespace Tests\Feature\Admin;

use App\Exceptions\RbacException;
use App\Models\User;
use App\Services\Rbac\AdminUserService;
use App\Services\Rbac\RbacSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        app(RbacSynchronizer::class)->sync();
    }

    /** @param array<string, mixed> $attributes */
    private function adminWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_manager_cannot_manage_admin_users(): void
    {
        Sanctum::actingAs($this->adminWithRole('manager'));

        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_super_admin_can_list_admin_users(): void
    {
        Sanctum::actingAs($this->adminWithRole('super_admin'));

        $this->getJson('/api/admin/users')->assertOk();
    }

    public function test_viewer_cannot_create_admin_users(): void
    {
        Sanctum::actingAs($this->adminWithRole('viewer'));

        $this->postJson('/api/admin/users', [])->assertForbidden();
    }

    public function test_only_a_super_admin_can_grant_the_super_admin_role(): void
    {
        $manager = $this->adminWithRole('manager');
        $target = User::factory()->create();

        $this->expectException(RbacException::class);

        app(AdminUserService::class)->syncRoles($target, ['super_admin'], $manager);
    }

    public function test_the_last_active_super_admin_cannot_be_deactivated(): void
    {
        $onlySuperAdmin = $this->adminWithRole('super_admin');
        $manager = $this->adminWithRole('manager');

        $this->expectException(RbacException::class);

        app(AdminUserService::class)->deactivate($onlySuperAdmin, $manager);
    }

    public function test_deactivation_revokes_tokens_and_blocks_further_requests(): void
    {
        $actor = $this->adminWithRole('super_admin');
        $target = $this->adminWithRole('manager');
        $token = $target->createToken('admin')->plainTextToken;

        app(AdminUserService::class)->deactivate($target, $actor);

        $this->assertSame(0, $target->tokens()->count());
        $this->withToken($token)->getJson('/api/admin/me')->assertUnauthorized();
    }

    public function test_inactive_flag_blocks_access_even_with_a_live_session(): void
    {
        $user = $this->adminWithRole('manager');
        Sanctum::actingAs($user);
        $user->forceFill(['is_active' => false])->save();

        $this->getJson('/api/admin/me')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'account_inactive');
    }

    public function test_active_admin_can_log_in_and_receives_their_permissions(): void
    {
        $user = User::factory()->create([
            'email' => 'ops@example.com',
            'password' => 'Ops-Password-2026!',
        ]);
        $user->assignRole('dispatcher');

        $this->postJson('/api/admin/auth/login', [
            'email' => 'ops@example.com',
            'password' => 'Ops-Password-2026!',
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'expires_at', 'user' => ['roles', 'permissions']]]);
    }

    public function test_deactivated_admin_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => 'Old-Password-2026!',
            'is_active' => false,
        ]);
        $user->assignRole('manager');

        $this->postJson('/api/admin/auth/login', [
            'email' => 'old@example.com',
            'password' => 'Old-Password-2026!',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_credentials');
    }
}
