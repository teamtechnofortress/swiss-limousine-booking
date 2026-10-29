<?php

namespace App\Data;

final readonly class RouteMetrics
{
    public function __construct(
        public int $distanceM,
        public int $durationS,
    ) {}

    /** @return array{distance_m: int, duration_s: int} */
    public function toArray(): array
    {
        return ['distance_m' => $this->distanceM, 'duration_s' => $this->durationS];
    }

    /** @param array{distance_m: int, duration_s: int} $data */
    public static function fromArray(array $data): self
    {
        return new self((int) $data['distance_m'], (int) $data['duration_s']);
    }
}
