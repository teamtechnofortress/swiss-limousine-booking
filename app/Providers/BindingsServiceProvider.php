<?php

namespace App\Providers;

use App\Gateways\Payments\PaymentGateway;
use App\Gateways\Payments\StripeGateway;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\VehicleRepositoryInterface;
use App\Repositories\Eloquent\BookingRepository;
use App\Repositories\Eloquent\VehicleRepository;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class BindingsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        BookingRepositoryInterface::class => BookingRepository::class,
        VehicleRepositoryInterface::class => VehicleRepository::class,
    ];

    public function register(): void
    {
        // The provider is chosen from settings. Adding another gateway means one new case here.
        $this->app->bind(PaymentGateway::class, function (Application $app): PaymentGateway {
            return match ($app->make(SettingsService::class)->string('payment_gateway')) {
                'stripe' => $app->make(StripeGateway::class),
                default => throw new RuntimeException('Unsupported payment gateway.'),
            };
        });
    }
}
