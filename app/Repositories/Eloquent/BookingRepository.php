<?php

namespace App\Repositories\Eloquent;

use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Repositories\Contracts\BookingRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class BookingRepository implements BookingRepositoryInterface
{
    public function create(array $attributes): Booking
    {
        return Booking::query()->create($attributes);
    }

    public function vehicleIdOf(int $bookingId): int
    {
        $vehicleId = Booking::query()->whereKey($bookingId)->value('vehicle_id');

        return $vehicleId !== null ? (int) $vehicleId : throw BookingException::notFound();
    }

    public function lockById(int $bookingId): Booking
    {
        return Booking::query()->whereKey($bookingId)->lockForUpdate()->first()
            ?? throw BookingException::notFound();
    }

    public function findByReference(string $reference): ?Booking
    {
        return Booking::query()
            ->with(['legs', 'payment', 'vehicle'])
            ->where('reference', $reference)
            ->first();
    }

    public function findByVerificationHash(string $hash): ?Booking
    {
        return Booking::query()->where('verification_token_hash', $hash)->first();
    }

    public function referenceExists(string $reference): bool
    {
        return Booking::withTrashed()->where('reference', $reference)->exists();
    }

    public function expirationCandidates(CarbonImmutable $now, int $limit): Collection
    {
        return Booking::query()
            ->select(['id'])
            ->whereIn('status', [
                BookingStatus::PendingPayment->value,
                BookingStatus::PendingVerification->value,
            ])
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<', $now)
            ->orderBy('hold_expires_at')
            ->limit($limit)
            ->get();
    }

    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Booking::query()
            ->with(['vehicle', 'fleetUnit', 'payment', 'legs'])
            ->adminQueue();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (! empty($filters['vehicle_id'])) {
            $query->where('vehicle_id', (int) $filters['vehicle_id']);
        }

        if (! empty($filters['unseen'])) {
            $query->unseen();
        }

        // Date filters follow the Swiss business day, converted to UTC for the query.
        if (! empty($filters['from']) || ! empty($filters['to'])) {
            $from = ! empty($filters['from'])
                ? CarbonImmutable::parse($filters['from'], 'Europe/Zurich')->startOfDay()->utc()
                : null;
            $to = ! empty($filters['to'])
                ? CarbonImmutable::parse($filters['to'], 'Europe/Zurich')->endOfDay()->utc()
                : null;

            $query->whereHas('legs', function ($legs) use ($from, $to): void {
                $legs->where('direction', 'outbound');

                if ($from !== null) {
                    $legs->where('starts_at', '>=', $from);
                }

                if ($to !== null) {
                    $legs->where('starts_at', '<=', $to);
                }
            });
        }

        if (! empty($filters['q'])) {
            $term = '%'.addcslashes((string) $filters['q'], '%_\\').'%';

            $query->where(function ($q) use ($term): void {
                $q->where('reference', 'like', $term)
                    ->orWhere('customer_email', 'like', $term)
                    ->orWhere('customer_name', 'like', $term);
            });
        }

        return $query->paginate($perPage);
    }

    public function countUnseen(): int
    {
        return Booking::query()->unseen()->count();
    }
}
