<?php

namespace App\Repositories\Eloquent;

use App\Exceptions\BookingException;
use App\Models\Vehicle;
use App\Repositories\Contracts\VehicleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class VehicleRepository implements VehicleRepositoryInterface
{
    public function bookableFor(int $passengers, int $luggage): Collection
    {
        return Vehicle::query()
            ->bookable()
            ->where('passenger_capacity', '>=', $passengers)
            ->where('luggage_capacity', '>=', $luggage)
            ->orderBy('sort_order')
            ->get();
    }

    public function lockVehicle(int $vehicleId): Vehicle
    {
        return Vehicle::query()->whereKey($vehicleId)->lockForUpdate()->first()
            ?? throw BookingException::vehicleUnavailable();
    }
}
