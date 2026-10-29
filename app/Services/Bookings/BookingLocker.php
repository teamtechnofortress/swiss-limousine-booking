<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\Vehicle;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\VehicleRepositoryInterface;

/**
 * Takes the row locks for a booking: vehicle first, then booking. Every write path uses this,
 * so the lock order is the same everywhere and deadlocks cannot occur. Call inside a transaction.
 */
final class BookingLocker
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookings,
        private readonly VehicleRepositoryInterface $vehicles,
    ) {}

    /** @return array{0: Vehicle, 1: Booking} */
    public function lock(int $bookingId): array
    {
        $vehicle = $this->vehicles->lockVehicle($this->bookings->vehicleIdOf($bookingId));
        $booking = $this->bookings->lockById($bookingId);

        return [$vehicle, $booking];
    }
}
