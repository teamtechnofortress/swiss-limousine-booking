<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class QuoteRequestData
{
    public function __construct(
        public string $pickupPlaceId,
        public string $dropoffPlaceId,
        public CarbonImmutable $pickupAt,
        public ?CarbonImmutable $returnAt,
        public int $passengers,
        public int $luggage,
    ) {}
}
