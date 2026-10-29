<?php

namespace App\Services\Rbac;

use App\Exceptions\RbacException;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin account lifecycle. Enforces the rules that keep the admin panel safe and recoverable:
 * - at least one active super admin always exists
 * - nobody changes their own roles or status
 * - only a super admin can grant the super admin role
 * - deactivation revokes every token immediately
 *
 * $actor is null only for trusted server-side commands (admin:create), which need server access.
 */
final class AdminUserService
{
    private const SUPER_ADMIN = 'super_admin';

    public function __construct(private readonly RbacCatalog $catalog) {}

    /**
     * @param array{name: string, email: string, password: string} $data
     * @param list<string> $roles
     */
    public function create(array $data, array $roles, ?User $actor): User
    {
        $this->assertRolesExist($roles);
        $this->assertCanGrant($roles, $actor);

        return DB::transaction(function () use ($data, $roles, $actor): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'password' => $data['password'], // hashed by the model cast
                'is_active' => true,
            ]);

            $user->syncRoles($roles);
            $this->audit('admin_user_created', $user, $actor, ['roles' => $roles]);

            return $user->load('roles');
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $target, array $data, ?User $actor): User
    {
        return DB::transaction(function () use ($target, $data, $actor): User {
            $target->fill([
                'name' => $data['name'] ?? $target->name,
                'email' => isset($data['email']) ? mb_strtolower((string) $data['email']) : $target->email,
            ]);

            $passwordChanged = ! empty($data['password']);

            if ($passwordChanged) {
                $target->password = (string) $data['password'];
            }

            $target->save();

            if ($passwordChanged) {
                $target->tokens()->delete(); // a password change signs the account out everywhere
            }

            $this->audit('admin_user_updated', $target, $actor, ['password_changed' => $passwordChanged]);

            return $target->load('roles');
        });
    }

    /** @param list<string> $roles */
    public function syncRoles(User $target, array $roles, ?User $actor): User
    {
        $this->assertNotSelf($target, $actor);
        $this->assertRolesExist($roles);
        $this->assertCanGrant($roles, $actor);

        return DB::transaction(function () use ($target, $roles, $actor): User {
            $before = $target->getRoleNames()->values()->all();
            $removesSuperAdmin = in_array(self::SUPER_ADMIN, $before, true)
                && ! in_array(self::SUPER_ADMIN, $roles, true);

            if ($removesSuperAdmin && $target->is_active) {
                $this->activeSuperAdmins(lock: true);
                $this->assertAnotherActiveSuperAdminExists($target);
            }

            $target->syncRoles($roles);
            $this->forgetPermissionCache();
            $this->audit('admin_roles_changed', $target, $actor, ['before' => $before, 'after' => $roles]);

            return $target->load('roles');
        });
    }

    public function deactivate(User $target, ?User $actor): User
    {
        $this->assertNotSelf($target, $actor);

        return DB::transaction(function () use ($target, $actor): User {
            $this->activeSuperAdmins(lock: true);

            $locked = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->is_active) {
                return $locked; // already deactivated
            }

            if ($locked->hasRole(self::SUPER_ADMIN)) {
                $this->assertAnotherActiveSuperAdminExists($locked);
            }

            $locked->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();
            $locked->tokens()->delete();
            $this->audit('admin_user_deactivated', $locked, $actor, []);

            return $locked;
        });
    }

    public function activate(User $target, ?User $actor): User
    {
        $target->forceFill(['is_active' => true, 'deactivated_at' => null])->save();
        $this->audit('admin_user_activated', $target, $actor, []);

        return $target;
    }

    /** @return Collection<int, User> */
    private function activeSuperAdmins(bool $lock = false): Collection
    {
        $query = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($roles) => $roles
                ->where('name', self::SUPER_ADMIN)
                ->where('guard_name', $this->catalog->guard()));

        return ($lock ? $query->lockForUpdate() : $query)->get();
    }

    private function assertAnotherActiveSuperAdminExists(User $target): void
    {
        $others = $this->activeSuperAdmins()->reject(fn (User $user) => $user->is($target))->count();

        if ($others === 0) {
            throw RbacException::lastSuperAdmin();
        }
    }

    private function assertNotSelf(User $target, ?User $actor): void
    {
        if ($actor !== null && $actor->is($target)) {
            throw RbacException::selfModification();
        }
    }

    /** @param list<string> $roles */
    private function assertRolesExist(array $roles): void
    {
        $known = $this->catalog->roleNames();

        foreach ($roles as $role) {
            if (! in_array($role, $known, true)) {
                throw RbacException::unknownRole($role);
            }
        }
    }

    /** @param list<string> $roles */
    private function assertCanGrant(array $roles, ?User $actor): void
    {
        if ($actor === null) {
            return;
        }

        if (in_array(self::SUPER_ADMIN, $roles, true) && ! $actor->hasRole(self::SUPER_ADMIN)) {
            throw RbacException::superAdminRequired();
        }
    }

    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @param array<string, mixed> $properties */
    private function audit(string $event, User $subject, ?User $actor, array $properties): void
    {
        $logger = activity('rbac')->performedOn($subject)->withProperties($properties);

        if ($actor !== null) {
            $logger->causedBy($actor);
        }

        $logger->log($event);
    }
}
