<?php

namespace App\Repositories\Eloquent;

use App\Exceptions\BookingException;
use App\Models\Vehicle;
use App\Repositories\Contracts\VehicleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class VehicleRepository implements VehicleRepositoryInterface
{
    public function bookableFor(int $passengers, int $luggage, ?string $vehicleSlug = null): Collection
    {
        return Vehicle::query()
            ->bookable()
            // Quotes display the cover thumbnail for every available vehicle.
            // Load it here so producing a quote does not issue one media query per offer.
            ->with(['category', 'media'])
            ->where('passenger_capacity', '>=', $passengers)
            ->where('luggage_capacity', '>=', $luggage)
            ->when($vehicleSlug !== null, fn ($query) => $query->where('slug', $vehicleSlug))
            ->orderBy('sort_order')
            ->get();
    }

    public function lockVehicle(int $vehicleId): Vehicle
    {
        return Vehicle::query()->whereKey($vehicleId)->lockForUpdate()->first()
            ?? throw BookingException::vehicleUnavailable();
    }
}
