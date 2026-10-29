<?php

namespace App\Console\Commands;

use App\Exceptions\RbacException;
use App\Services\Rbac\AdminUserService;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Creates an admin from the server. Used for the first super admin and for account recovery. */
final class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create {email} {name} {--role=super_admin : Role to assign}';

    protected $description = 'Create an admin user from the server (first super admin, or recovery)';

    public function handle(AdminUserService $admins, RbacCatalog $catalog): int
    {
        $role = (string) $this->option('role');

        if (! in_array($role, $catalog->roleNames(), true)) {
            $this->error("Unknown role [{$role}]. Available: ".implode(', ', $catalog->roleNames()));

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');
        $name = (string) $this->argument('name');
        $password = (string) $this->secret('Password (min. 12 characters, mixed case, numbers and symbols)');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            [
                'email' => $email,
                'name' => $name,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ],
            [
                'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
                'name' => ['required', 'string', 'max:120'],
                'password' => [
                    'required',
                    'confirmed',
                    Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised(),
                ],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        try {
            $user = $admins->create(
                ['name' => $name, 'email' => $email, 'password' => $password],
                [$role],
                null,
            );
        } catch (RbacException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Admin [{$user->email}] created with role [{$role}].");

        return self::SUCCESS;
    }
}
