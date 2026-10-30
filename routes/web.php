<?php

use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\AdminBookingController;
use App\Http\Controllers\Web\AdminDashboardController;
use App\Http\Controllers\Web\AdminVehicleController;
use App\Http\Controllers\Web\AdminVehicleCategoryController;
use App\Http\Controllers\Web\AdminPaymentController;
use App\Http\Controllers\Web\AdminSettingsController;
use App\Http\Controllers\Web\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');
    });

    Route::middleware(['auth', 'admin.active'])->group(function (): void {
        Route::get('/', fn () => redirect()->route('admin.dashboard'));
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard')->middleware('can:dashboard.view');
        Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');

        Route::get('bookings', [AdminBookingController::class, 'index'])->name('bookings.index')->middleware('can:bookings.view');
        Route::get('bookings/{booking}', [AdminBookingController::class, 'show'])->name('bookings.show')->middleware('can:bookings.view');
        Route::post('bookings/{booking}/assign', [AdminBookingController::class, 'assign'])->name('bookings.assign')->middleware('can:bookings.assign');
        Route::post('bookings/{booking}/waiting', [AdminBookingController::class, 'waiting'])->name('bookings.waiting')->middleware('can:bookings.waiting');
        Route::post('bookings/{booking}/cancel', [AdminBookingController::class, 'cancel'])->name('bookings.cancel')->middleware('can:bookings.cancel');
        Route::post('bookings/{booking}/mark-paid', [AdminBookingController::class, 'markPaid'])->name('bookings.mark-paid')->middleware('can:payments.mark_paid');

        Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index')->middleware('can:payments.view');
        Route::post('payments/{payment}/refund', [AdminPaymentController::class, 'refund'])->name('payments.refund')->middleware('can:payments.refund');

        Route::get('fleet', fn () => redirect()->route('admin.vehicles.index'))->name('fleet.index')->middleware('can:fleet.manage');
        Route::middleware('can:fleet.manage')->group(function (): void {
            Route::get('vehicle-categories', [AdminVehicleCategoryController::class, 'index'])->name('vehicle-categories.index');
            Route::post('vehicle-categories', [AdminVehicleCategoryController::class, 'store'])->name('vehicle-categories.store');
            Route::patch('vehicle-categories/{category}', [AdminVehicleCategoryController::class, 'update'])->name('vehicle-categories.update');
            Route::delete('vehicle-categories/{category}', [AdminVehicleCategoryController::class, 'destroy'])->name('vehicle-categories.destroy');
            Route::get('vehicles', [AdminVehicleController::class, 'index'])->name('vehicles.index');
            Route::get('vehicles/create', [AdminVehicleController::class, 'create'])->name('vehicles.create');
            Route::post('vehicles', [AdminVehicleController::class, 'store'])->name('vehicles.store');
            Route::put('vehicles/reorder', [AdminVehicleController::class, 'reorder'])->name('vehicles.reorder');
            Route::get('vehicles/{vehicle}/edit', [AdminVehicleController::class, 'edit'])->name('vehicles.edit');
            Route::patch('vehicles/{vehicle}', [AdminVehicleController::class, 'update'])->name('vehicles.update');
            Route::delete('vehicles/{vehicle}', [AdminVehicleController::class, 'destroy'])->name('vehicles.destroy');
            Route::post('vehicles/{vehicle}/images', [AdminVehicleController::class, 'uploadImages'])->name('vehicles.images.store');
            Route::post('vehicles/{vehicle}/images/{mediaId}/primary', [AdminVehicleController::class, 'makePrimaryImage'])->name('vehicles.images.primary');
            Route::delete('vehicles/{vehicle}/images/{mediaId}', [AdminVehicleController::class, 'destroyImage'])->name('vehicles.images.destroy');
        });

        Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::get('settings/service-area/countries', [AdminSettingsController::class, 'searchCountries'])->name('settings.service-area.countries')->middleware(['can:service_area.manage', 'throttle:30,1']);
        Route::get('settings/service-area/countries/{placeId}', [AdminSettingsController::class, 'resolveCountry'])->name('settings.service-area.country')->middleware(['can:service_area.manage', 'throttle:30,1'])->where('placeId', '[A-Za-z0-9_-]+');
        Route::get('settings/{group}', [AdminSettingsController::class, 'show'])->name('settings.show');
        Route::put('settings/{group}', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::get('mail', [AdminSettingsController::class, 'mail'])->name('mail.show')->middleware('can:integrations.view');
        Route::put('mail', [AdminSettingsController::class, 'updateMail'])->name('mail.update')->middleware('can:integrations.manage');
        Route::put('sender', [AdminSettingsController::class, 'updateSender'])->name('sender.update')->middleware('can:company.manage');
        Route::post('mail/test', [AdminSettingsController::class, 'testMail'])->name('mail.test')->middleware('can:integrations.manage');

        Route::get('team', [AdminUserController::class, 'index'])->name('team.index')->middleware('can:users.manage');
        Route::post('team', [AdminUserController::class, 'store'])->name('team.store')->middleware('can:users.manage');
        Route::put('team/{user}', [AdminUserController::class, 'update'])->name('team.update')->middleware('can:users.manage');
        Route::put('team/{user}/roles', [AdminUserController::class, 'syncRoles'])->name('team.roles')->middleware('can:users.manage');
        Route::post('team/{user}/status', [AdminUserController::class, 'toggleStatus'])->name('team.status')->middleware('can:users.manage');
        Route::get('roles', [AdminUserController::class, 'roles'])->name('roles.index')->middleware('can:users.manage');

        Route::get('workspace/fleet', fn () => redirect()->route('admin.vehicles.index'));
        Route::get('workspace/{module}', [AdminDashboardController::class, 'workspace'])->name('workspace');
    });
});
