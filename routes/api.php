<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\MailSettingsController;
use App\Http\Controllers\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VehicleCategoryController as AdminVehicleCategoryController;
use App\Http\Controllers\Admin\VehicleController as AdminVehicleController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\PublicConfigController;
use App\Http\Controllers\Public\LocationChipController;
use App\Http\Controllers\Public\TripController;
use App\Http\Controllers\Public\QuoteController;
use App\Http\Controllers\Public\VehicleController as PublicVehicleController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/* ----------------------------------------------------------------------
 | Public (customers, no accounts)
 * -------------------------------------------------------------------- */
Route::prefix('public')->group(function (): void {
    // This endpoint is deliberately limited to configuration safe for a public browser.
    Route::get('config', PublicConfigController::class);
    Route::get('locations/chips', LocationChipController::class)->middleware('throttle:quote-read');
    Route::get('trips', [TripController::class, 'index']);
    Route::get('trips/{slug}', [TripController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
    Route::get('trips/{slug}/availability', [TripController::class, 'availability'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->middleware('throttle:quote-read');
    Route::post('trips/{slug}/quotes', [TripController::class, 'quote'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->middleware('throttle:quotes');
    Route::post('trips/{slug}/enquiries', [TripController::class, 'enquiry'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->middleware('throttle:bookings');

    Route::get('vehicles', [PublicVehicleController::class, 'index']);
    Route::get('vehicles/{slug}', [PublicVehicleController::class, 'show'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');

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
        Route::post('media/uploads', [AdminMediaController::class, 'store'])
            ->middleware(['can:company.manage', 'throttle:30,1']);

        Route::get('integrations/mail', [MailSettingsController::class, 'show'])
            ->middleware('can:integrations.view');
        Route::put('integrations/mail', [MailSettingsController::class, 'update'])
            ->middleware('can:integrations.manage');
        Route::post('integrations/mail/test', [MailSettingsController::class, 'test'])
            ->middleware(['can:integrations.manage', 'throttle:5,60']);

        Route::get('company/sender', [MailSettingsController::class, 'showSender'])
            ->middleware('can:company.manage');
        Route::put('company/sender', [MailSettingsController::class, 'updateSender'])
            ->middleware('can:company.manage');

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

        Route::middleware('can:fleet.manage')->group(function (): void {
            Route::get('vehicle-categories', [AdminVehicleCategoryController::class, 'index']);
            Route::post('vehicle-categories', [AdminVehicleCategoryController::class, 'store']);
            Route::patch('vehicle-categories/{category}', [AdminVehicleCategoryController::class, 'update']);
            Route::delete('vehicle-categories/{category}', [AdminVehicleCategoryController::class, 'destroy']);
            Route::get('vehicles', [AdminVehicleController::class, 'index']);
            Route::post('vehicles', [AdminVehicleController::class, 'store']);
            Route::put('vehicles/reorder', [AdminVehicleController::class, 'reorder']);
            Route::get('vehicles/{vehicle}', [AdminVehicleController::class, 'show']);
            Route::patch('vehicles/{vehicle}', [AdminVehicleController::class, 'update']);
            Route::delete('vehicles/{vehicle}', [AdminVehicleController::class, 'destroy']);
            Route::post('vehicles/{vehicle}/images', [AdminVehicleController::class, 'storeImages']);
            Route::put('vehicles/{vehicle}/images/order', [AdminVehicleController::class, 'orderImages']);
            Route::delete('vehicles/{vehicle}/images/{mediaId}', [AdminVehicleController::class, 'destroyImage']);
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
