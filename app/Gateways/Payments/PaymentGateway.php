<?php

namespace App\Gateways\Payments;

use App\Data\CheckoutSession;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Carbon\CarbonImmutable;

/** Provider-neutral contract. Booking code depends only on this interface. */
interface PaymentGateway
{
    public function name(): string;

    /** The persisted attempt supplies a stable provider idempotency key and webhook correlation id. */
    public function createCheckout(Booking $booking, PaymentAttempt $attempt, CarbonImmutable $expiresAt): CheckoutSession;

    /** Returns an existing hosted Checkout Session so a retry resumes rather than creates another. */
    public function retrieveCheckout(PaymentAttempt $attempt): CheckoutSession;

    /** Returns the provider's refund id. The idempotency key stops a retried request from refunding twice. */
    public function refund(Payment $payment, int $amount, string $idempotencyKey): string;

    /**
     * Verifies the signature and returns the event in a provider-neutral shape.
     *
     * @return array{id: string, type: string, object: array<string, mixed>}
     */
    public function parseWebhook(string $payload, string $signature): array;
}
