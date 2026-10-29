<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\QuoteController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/* ----------------------------------------------------------------------
 | Public (customers, no accounts)
 * -------------------------------------------------------------------- */
Route::prefix('public')->group(function (): void {
    Route::post('quotes', [QuoteController::class, 'store'])
        ->middleware('throttle:quotes');

    Route::get('quotes/{token}', [QuoteController::class, 'show'])
        ->where('token', '[a-f0-9]{64}')
        ->middleware('throttle:quote-read');

    Route::post('bookings', [BookingController::class, 'store'])
        ->middleware('throttle:bookings');

    // Declared before bookings/{reference} so "verify" is not read as a reference.
    Route::get('bookings/verify/{token}', [BookingController::class, 'verify'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->middleware('throttle:booking-read');

    Route::get('bookings/{reference}', [BookingController::class, 'show'])
        ->where('reference', 'LX-[A-Z0-9]{5}')
        ->middleware('throttle:booking-read');

    Route::post('bookings/{reference}/checkout', [BookingController::class, 'checkout'])
        ->where('reference', 'LX-[A-Z0-9]{5}')
        ->middleware('throttle:bookings');

    Route::post('bookings/{reference}/cancel-request', [BookingController::class, 'cancelRequest'])
        ->where('reference', 'LX-[A-Z0-9]{5}')
        ->middleware('throttle:bookings');
});

/* ----------------------------------------------------------------------
 | Webhooks (signature-verified, no session auth)
 * -------------------------------------------------------------------- */
Route::post('webhooks/payments/{provider}', StripeWebhookController::class)
    ->where('provider', 'stripe')
    ->name('webhooks.payments');

/* ----------------------------------------------------------------------
 | Admin
 | Login is public but rate limited. Everything else needs a token, an
 | active account, and the matching permission gate (config/rbac.php).
 * -------------------------------------------------------------------- */
Route::prefix('admin')->group(function (): void {
    Route::post('auth/login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:admin-login');

    Route::middleware(['auth:sanctum', 'admin.active', 'throttle:admin'])->group(function (): void {
        Route::get('me', [AdminAuthController::class, 'me']);
        Route::post('auth/logout', [AdminAuthController::class, 'logout']);

        Route::middleware('can:users.manage')->group(function (): void {
            Route::get('roles', [RoleController::class, 'index']);
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::get('users/{user}', [UserController::class, 'show']);
            Route::patch('users/{user}', [UserController::class, 'update']);
            Route::put('users/{user}/roles', [UserController::class, 'syncRoles']);
            Route::post('users/{user}/deactivate', [UserController::class, 'deactivate']);
            Route::post('users/{user}/activate', [UserController::class, 'activate']);
        });

        // Booking and payment routes (from the booking core). Each one is guarded by its permission.
        // "new-count" is declared before {booking} so it is not read as an ID.
        Route::get('bookings', [AdminBookingController::class, 'index'])
            ->middleware('can:bookings.view');
        Route::get('bookings/new-count', [AdminBookingController::class, 'newCount'])
            ->middleware('can:bookings.view');
        Route::get('bookings/{booking}', [AdminBookingController::class, 'show'])
            ->middleware('can:bookings.view');
        Route::post('bookings/{booking}/assign', [AdminBookingController::class, 'assign'])
            ->middleware('can:bookings.assign');
        Route::post('bookings/{booking}/waiting', [AdminBookingController::class, 'waiting'])
            ->middleware('can:bookings.waiting');
        Route::post('bookings/{booking}/cancel', [AdminBookingController::class, 'cancel'])
            ->middleware('can:bookings.cancel');
        Route::post('bookings/{booking}/mark-paid', [AdminBookingController::class, 'markPaid'])
            ->middleware('can:payments.mark_paid');
        Route::post('payments/{payment}/refund', [AdminPaymentController::class, 'refund'])
            ->middleware('can:payments.refund');
    });
});
