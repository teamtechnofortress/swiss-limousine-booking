<?php

namespace App\Services\Rbac;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Makes the database match config/rbac.php.
 * Creates missing permissions and roles, then applies role grants from config.
 * Never deletes: permissions removed from config are reported so a person can clean them up.
 */
final class RbacSynchronizer
{
    public function __construct(private readonly RbacCatalog $catalog) {}

    /** @return array{permissions_created: int, roles: list<string>, orphaned_permissions: list<string>} */
    public function sync(): array
    {
        $this->catalog->assertValid();
        $guard = $this->catalog->guard();

        $result = DB::transaction(function () use ($guard): array {
            $created = 0;

            foreach ($this->catalog->permissionNames() as $name) {
                $permission = Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => $guard]);

                if ($permission->wasRecentlyCreated) {
                    $created++;
                }
            }

            foreach ($this->catalog->roleNames() as $name) {
                $role = Role::query()->firstOrCreate(['name' => $name, 'guard_name' => $guard]);
                $role->syncPermissions($this->catalog->permissionsFor($name));
            }

            $orphans = Permission::query()
                ->where('guard_name', $guard)
                ->whereNotIn('name', $this->catalog->permissionNames())
                ->pluck('name')
                ->all();

            return [
                'permissions_created' => $created,
                'roles' => $this->catalog->roleNames(),
                'orphaned_permissions' => $orphans,
            ];
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $result;
    }
}
