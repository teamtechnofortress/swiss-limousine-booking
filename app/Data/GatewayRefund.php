<?php

namespace App\Data;

/** Provider-neutral refund response used by both the API call and webhook reconciliation. */
final readonly class GatewayRefund
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $id,
        public string $status,
        public ?string $paymentIntent,
        public int $amount,
        public array $metadata = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'payment_intent' => $this->paymentIntent,
            'amount' => $this->amount,
            'metadata' => $this->metadata,
        ];
    }
}
