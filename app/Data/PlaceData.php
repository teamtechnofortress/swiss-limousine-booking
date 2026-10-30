<?php

namespace App\Data;

final readonly class PlaceData
{
    public function __construct(
        public string $placeId,
        public ?string $countryCode,
        public ?string $locality,
        public ?string $adminAreaCode,
        public ?string $adminAreaName,
        public float $latitude,
        public float $longitude,
    ) {}

}
