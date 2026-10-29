<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->registerRateLimiters();
        $this->registerPermissionGates();
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('quotes', fn (Request $r) => [
            Limit::perMinute(20)->by($r->ip()),
            Limit::perHour(200)->by($r->ip()),
        ]);

        RateLimiter::for('bookings', fn (Request $r) => [
            Limit::perMinute(5)->by($r->ip()),
            Limit::perHour(10)->by($r->ip()),
        ]);

        RateLimiter::for('quote-read', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));
        RateLimiter::for('booking-read', fn (Request $r) => Limit::perMinute(30)->by($r->ip()));

        RateLimiter::for('admin-login', function (Request $r) {
            $email = $r->input('email');

            return Limit::perMinute(5)->by($r->ip().'|'.(is_string($email) ? mb_strtolower($email) : ''));
        });

        RateLimiter::for('admin', fn (Request $r) => Limit::perMinute(300)->by($r->user()?->id ?? $r->ip()));
    }

    /**
     * One gate per permission in config/rbac.php, so `can:bookings.view` works on routes
     * and `$user->can('bookings.view')` works in code. Requires `php artisan rbac:sync` first.
     */
    private function registerPermissionGates(): void
    {
        $catalog = app(RbacCatalog::class);

        foreach ($catalog->permissionNames() as $permission) {
            Gate::define(
                $permission,
                fn (User $user): bool => $user->hasPermissionTo($permission, $catalog->guard()),
            );
        }
    }
}
