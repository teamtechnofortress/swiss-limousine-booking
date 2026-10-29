<?php

namespace App\Services\Quotes;

use App\Data\QuoteSnapshot;
use Illuminate\Support\Facades\Redis;

/** Redis-backed quotes. Consuming a quote is atomic, so a double submit cannot create two bookings. */
final class QuoteStore
{
    private const PREFIX = 'limo:quote:';

    private const CONSUME_SCRIPT = <<<'LUA'
local value = redis.call('GET', KEYS[1])
if value then
    redis.call('DEL', KEYS[1])
end
return value
LUA;

    public function put(QuoteSnapshot $snapshot, int $ttlSeconds): void
    {
        Redis::setex(
            self::PREFIX.$snapshot->token,
            $ttlSeconds,
            json_encode($snapshot->toArray(), JSON_THROW_ON_ERROR),
        );
    }

    public function find(string $token): ?QuoteSnapshot
    {
        return $this->hydrate(Redis::get(self::PREFIX.$token));
    }

    public function consume(string $token): ?QuoteSnapshot
    {
        return $this->hydrate(Redis::eval(self::CONSUME_SCRIPT, 1, self::PREFIX.$token));
    }

    private function hydrate(mixed $raw): ?QuoteSnapshot
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $snapshot = QuoteSnapshot::fromArray(json_decode($raw, true, 512, JSON_THROW_ON_ERROR));

        return $snapshot->expiresAt->isFuture() ? $snapshot : null;
    }
}
