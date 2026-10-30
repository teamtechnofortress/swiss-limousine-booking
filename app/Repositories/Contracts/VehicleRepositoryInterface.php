<?php

namespace App\Repositories\Contracts;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;

interface VehicleRepositoryInterface
{
    /** @return Collection<int, Vehicle> */
    public function bookableFor(int $passengers, int $luggage, ?string $vehicleSlug = null): Collection;

    /** Must run inside a transaction. */
    public function lockVehicle(int $vehicleId): Vehicle;
}
