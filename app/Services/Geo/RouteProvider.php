<?php

namespace App\Services\Geo;

use App\Data\RouteMetrics;
use Illuminate\Support\Facades\Cache;

/** Route distance and duration, cached for 30 days. */
final class RouteProvider
{
    private const TTL_DAYS = 30;

    public function __construct(private readonly GoogleMapsClient $google) {}

    public function metrics(string $originPlaceId, string $destinationPlaceId): RouteMetrics
    {
        $key = "route:{$originPlaceId}:{$destinationPlaceId}";

        $cached = Cache::get($key);

        if (is_array($cached)) {
            return RouteMetrics::fromArray($cached);
        }

        $metrics = $this->google->computeRoute($originPlaceId, $destinationPlaceId);
        Cache::put($key, $metrics->toArray(), now()->addDays(self::TTL_DAYS));

        return $metrics;
    }
}
