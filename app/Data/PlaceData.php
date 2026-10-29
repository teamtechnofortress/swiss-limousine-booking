<?php

namespace App\Data;

final readonly class PlaceData
{
    public function __construct(
        public string $placeId,
        public string $formattedAddress,
        public ?string $countryCode,
        public ?string $locality,
        public ?string $adminAreaCode,
        public ?string $adminAreaName,
        public float $latitude,
        public float $longitude,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'place_id' => $this->placeId,
            'address' => $this->formattedAddress,
            'country_code' => $this->countryCode,
            'locality' => $this->locality,
            'admin_area_code' => $this->adminAreaCode,
            'admin_area_name' => $this->adminAreaName,
            'lat' => $this->latitude,
            'lng' => $this->longitude,
        ];
    }
}
