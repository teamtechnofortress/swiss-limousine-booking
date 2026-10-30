<?php

namespace App\Services\Quotes;

use App\Data\QuoteSnapshot;
use Illuminate\Support\Facades\Cache;

/** Short-lived quote snapshots held in Laravel's configured cache store. */
final class QuoteStore
{
    private const PREFIX = 'limo:quote:';

    public function put(QuoteSnapshot $snapshot, int $ttlSeconds): void
    {
        Cache::put(
            self::PREFIX.$snapshot->token,
            $snapshot->toArray(),
            now()->addSeconds($ttlSeconds),
        );
    }

    public function find(string $token): ?QuoteSnapshot
    {
        return $this->hydrate(Cache::get(self::PREFIX.$token));
    }

    private function hydrate(mixed $raw): ?QuoteSnapshot
    {
        if (! is_array($raw)) {
            return null;
        }

        $snapshot = QuoteSnapshot::fromArray($raw);

        return $snapshot->expiresAt->isFuture() ? $snapshot : null;
    }
}
