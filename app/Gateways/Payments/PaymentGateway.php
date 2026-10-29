<?php

namespace App\Gateways\Payments;

use App\Data\CheckoutSession;
use App\Models\Booking;
use App\Models\Payment;
use Carbon\CarbonImmutable;

/** Provider-neutral contract. Booking code depends only on this interface. */
interface PaymentGateway
{
    public function name(): string;

    public function createCheckout(Booking $booking, CarbonImmutable $expiresAt): CheckoutSession;

    /** Returns the provider's refund id. The idempotency key stops a retried request from refunding twice. */
    public function refund(Payment $payment, int $amount, string $idempotencyKey): string;

    /**
     * Verifies the signature and returns the event in a provider-neutral shape.
     *
     * @return array{id: string, type: string, object: array<string, mixed>}
     */
    public function parseWebhook(string $payload, string $signature): array;
}
