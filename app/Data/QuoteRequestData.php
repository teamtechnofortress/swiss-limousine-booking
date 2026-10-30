<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class QuoteRequestData
{
    public function __construct(
        public string $pickupPlaceId,
        public string $dropoffPlaceId,
        /** Customer-confirmed address text. This is business data, not a cached Places response. */
        public string $pickupAddress,
        /** Customer-confirmed address text. This is business data, not a cached Places response. */
        public string $dropoffAddress,
        /** Autocomplete sessions are distinct for the two fields. Sent to the terminating Details call. */
        public ?string $pickupSessionToken,
        public ?string $dropoffSessionToken,
        public CarbonImmutable $pickupAt,
        public ?CarbonImmutable $returnAt,
        public int $passengers,
        public int $luggage,
        /** Optional public vehicle identifier for a vehicle-detail booking flow. */
        public ?string $vehicleSlug = null,
    ) {}
}
