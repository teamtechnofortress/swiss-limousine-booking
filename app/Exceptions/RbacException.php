<?php

namespace App\Exceptions;

use RuntimeException;

/** Access and admin-account errors. Safe to show to the caller. */
final class RbacException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function invalidCredentials(): self
    {
        return new self('invalid_credentials', 'The email or password is incorrect.', 401);
    }

    public static function accountInactive(): self
    {
        return new self('account_inactive', 'This account has been deactivated.', 403);
    }

    public static function lastSuperAdmin(): self
    {
        return new self('last_super_admin', 'At least one active super admin must remain.', 409);
    }

    public static function selfModification(): self
    {
        return new self('self_modification_forbidden', 'You cannot change your own roles or account status.', 409);
    }

    public static function superAdminRequired(): self
    {
        return new self('super_admin_required', 'Only a super admin can grant the super admin role.', 403);
    }

    public static function unknownRole(string $role): self
    {
        return new self('unknown_role', "The role [{$role}] does not exist.", 422);
    }

    public static function invalidConfiguration(string $message): self
    {
        return new self('invalid_rbac_configuration', $message, 500);
    }
}
