<?php

namespace App\Services\Geo;

use App\Data\RouteMetrics;

/**
 * One live, traffic-unaware route calculation per quote direction. Route results are not cached
 * across requests because Routes content has restrictive storage rules and stale durations are
 * unsuitable for fleet availability.
 */
final class RouteProvider
{
    public function __construct(private readonly GoogleMapsClient $google) {}

    public function metrics(string $originPlaceId, string $destinationPlaceId): RouteMetrics
    {
        return $this->google->computeRoute($originPlaceId, $destinationPlaceId);
    }
}
