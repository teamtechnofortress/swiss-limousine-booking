<?php

use App\Exceptions\BookingException;
use App\Exceptions\RbacException;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Services\Bookings\BookingService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.active' => EnsureAdminIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (BookingException $e) => response()->json([
            'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
        ], $e->status));

        $exceptions->render(fn (RbacException $e) => response()->json([
            'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
        ], $e->status));
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->call(fn () => app(BookingService::class)->expireStale())
            ->everyMinute()
            ->withoutOverlapping()
            ->name('expire-stale-bookings');
    })
    ->create();
