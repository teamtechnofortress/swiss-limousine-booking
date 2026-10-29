<?php

namespace App\Repositories\Contracts;

use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface BookingRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Booking;

    public function vehicleIdOf(int $bookingId): int;

    /** Must run inside a transaction. */
    public function lockById(int $bookingId): Booking;

    public function findByReference(string $reference): ?Booking;

    public function findByVerificationHash(string $hash): ?Booking;

    public function referenceExists(string $reference): bool;

    /** @return Collection<int, Booking> Only ids are loaded. */
    public function expirationCandidates(CarbonImmutable $now, int $limit): Collection;

    /** @param array<string, mixed> $filters */
    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator;

    public function countUnseen(): int;
}
