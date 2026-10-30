<?php

use App\Exceptions\BookingException;
use App\Exceptions\RbacException;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\LogApiTraffic;
use App\Services\Bookings\BookingService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request) => route('admin.login'));

        // Writes API requests and responses only when APP_ENV is local/development.
        $middleware->api(append: [LogApiTraffic::class]);

        $middleware->alias([
            'admin.active' => EnsureAdminIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (BookingException $e, Request $request) {
            if (! $request->expectsJson()) {
                return back()->withInput()->withErrors(['booking' => $e->getMessage()]);
            }

            return response()->json([
                'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
            ], $e->status);
        });

        $exceptions->render(function (RbacException $e, Request $request) {
            if (! $request->expectsJson()) {
                return back()->withInput()->withErrors(['admin' => $e->getMessage()]);
            }

            return response()->json([
                'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
            ], $e->status);
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->call(fn () => app(BookingService::class)->expireStale())
            ->everyMinute()
            ->name('expire-stale-bookings')
            ->withoutOverlapping();
    })
    ->create();
