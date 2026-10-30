<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class QuoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $quote = $this->resource->toArray();
        $totalDistanceM = 0;
        $totalDurationS = 0;

        $quote['legs'] = array_map(function (array $leg) use (&$totalDistanceM, &$totalDurationS): array {
            $distanceM = (int) $leg['distance_m'];
            $durationS = (int) $leg['duration_s'];
            $totalDistanceM += $distanceM;
            $totalDurationS += $durationS;

            return [
                'direction' => $leg['direction'],
                'pickup' => $leg['pickup']['address'],
                'dropoff' => $leg['dropoff']['address'],
                'starts_at' => $leg['starts_at'],
                'route' => [
                    // Base units are precise and should be used for calculations.
                    'distance_m' => $distanceM,
                    'duration_s' => $durationS,
                    // Convenience values are for customer-facing display only.
                    'distance_km' => round($distanceM / 1000, 1),
                    'duration_minutes' => (int) ceil($durationS / 60),
                ],
            ];
        }, $quote['legs']);

        $quote['route_summary'] = [
            'distance_m' => $totalDistanceM,
            'duration_s' => $totalDurationS,
            'distance_km' => round($totalDistanceM / 1000, 1),
            'duration_minutes' => (int) ceil($totalDurationS / 60),
        ];

        return $quote;
    }
}
