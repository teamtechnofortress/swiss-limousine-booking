<?php

namespace App\Services\Rbac;

use App\Exceptions\RbacException;

/** Read-only view of config/rbac.php. The one place that knows the permission and role layout. */
final class RbacCatalog
{
    /** @return array<string, array<string, string>> group => [permission => label] */
    public function permissionGroups(): array
    {
        return config('rbac.permissions', []);
    }

    /** @return list<string> */
    public function permissionNames(): array
    {
        return collect($this->permissionGroups())
            ->flatMap(fn (array $permissions) => array_keys($permissions))
            ->values()
            ->all();
    }

    /** @return array<string, array<string, mixed>> */
    public function roles(): array
    {
        return config('rbac.roles', []);
    }

    /** @return list<string> */
    public function roleNames(): array
    {
        return array_keys($this->roles());
    }

    /** @return list<string> Permissions for a role, with ['*'] expanded to the whole catalog. */
    public function permissionsFor(string $role): array
    {
        $grants = $this->roles()[$role]['permissions'] ?? [];

        return in_array('*', $grants, true)
            ? $this->permissionNames()
            : array_values($grants);
    }

    /** @return list<string> */
    public function twoFactorRequiredRoles(): array
    {
        return config('rbac.two_factor_required_roles', []);
    }

    public function guard(): string
    {
        return (string) config('rbac.guard', 'sanctum');
    }

    /** Fails loudly on a broken config so it can't reach production silently. */
    public function assertValid(): void
    {
        $names = $this->permissionNames();

        if (count($names) !== count(array_unique($names))) {
            throw RbacException::invalidConfiguration('A permission key is defined more than once in config/rbac.php.');
        }

        foreach ($this->roles() as $role => $definition) {
            foreach ($definition['permissions'] ?? [] as $permission) {
                if ($permission !== '*' && ! in_array($permission, $names, true)) {
                    throw RbacException::invalidConfiguration(
                        "Role [{$role}] references unknown permission [{$permission}].",
                    );
                }
            }
        }

        foreach ($this->twoFactorRequiredRoles() as $role) {
            if (! array_key_exists($role, $this->roles())) {
                throw RbacException::invalidConfiguration("Two-factor role [{$role}] does not exist.");
            }
        }
    }
}
