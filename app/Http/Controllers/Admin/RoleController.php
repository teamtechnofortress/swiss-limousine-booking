<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Http\JsonResponse;

/** Read-only: lets the admin UI render role and permission labels. Changes go through config/rbac.php. */
final class RoleController extends Controller
{
    public function index(RbacCatalog $catalog): JsonResponse
    {
        $roles = collect($catalog->roles())
            ->map(fn (array $definition, string $name) => [
                'name' => $name,
                'label' => $definition['label'] ?? $name,
                'description' => $definition['description'] ?? null,
                'permissions' => $catalog->permissionsFor($name),
            ])
            ->values();

        $groups = collect($catalog->permissionGroups())
            ->map(fn (array $permissions, string $group) => [
                'group' => $group,
                'permissions' => collect($permissions)
                    ->map(fn (string $label, string $key) => ['name' => $key, 'label' => $label])
                    ->values(),
            ])
            ->values();

        return response()->json(['data' => ['roles' => $roles, 'permission_groups' => $groups]]);
    }
}
