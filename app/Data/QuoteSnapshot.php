<?php

namespace App\Data;

use App\Exceptions\BookingException;
use Carbon\CarbonImmutable;

/** The complete quote stored in Redis. Everything needed to create a booking is in here. */
final readonly class QuoteSnapshot
{
    /**
     * @param list<array<string, mixed>> $legs
     * @param array<int, array<string, mixed>> $offers keyed by vehicle id
     * @param list<string> $paymentMethods
     */
    public function __construct(
        public string $token,
        public CarbonImmutable $expiresAt,
        public string $currency,
        public int $passengers,
        public int $luggage,
        public array $legs,
        public array $offers,
        public array $paymentMethods,
    ) {}

    /** @return array<string, mixed> */
    public function offerFor(int $vehicleId): array
    {
        return $this->offers[$vehicleId] ?? throw BookingException::vehicleNotInQuote();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'currency' => $this->currency,
            'passengers' => $this->passengers,
            'luggage' => $this->luggage,
            'legs' => $this->legs,
            'offers' => $this->offers,
            'payment_methods' => $this->paymentMethods,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            token: (string) $data['token'],
            expiresAt: CarbonImmutable::parse($data['expires_at']),
            currency: (string) $data['currency'],
            passengers: (int) $data['passengers'],
            luggage: (int) $data['luggage'],
            legs: $data['legs'],
            offers: $data['offers'],
            paymentMethods: $data['payment_methods'],
        );
    }
}
