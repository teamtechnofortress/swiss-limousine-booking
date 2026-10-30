<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

final class AdminSeeder extends Seeder
{
    /**
     * Create the initial admin account used to access the admin console.
     *
     * Re-running the seeder leaves an existing account unchanged, including
     * its password, while ensuring it retains super-admin access.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $email = mb_strtolower((string) env('LOCAL_ADMIN_EMAIL', 'admin@swisslimousine.test'));

        $admin = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => (string) env('LOCAL_ADMIN_NAME', 'Swiss Limousine Admin'),
                'password' => (string) env('LOCAL_ADMIN_PASSWORD', 'ChangeMe123!'),
                'is_active' => true,
            ],
        );

        $admin->syncRoles(['super_admin']);
    }
}
