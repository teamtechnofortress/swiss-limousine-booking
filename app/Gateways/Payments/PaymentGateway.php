<?php

namespace App\Gateways\Payments;

use App\Data\CheckoutSession;
use App\Data\GatewayPaymentLedger;
use App\Data\GatewayRefund;
use App\Data\GatewayRefundLedger;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use Carbon\CarbonImmutable;

/** Provider-neutral contract. Booking code depends only on this interface. */
interface PaymentGateway
{
    public function name(): string;

    /** The persisted attempt supplies a stable provider idempotency key and webhook correlation id. */
    public function createCheckout(Booking $booking, PaymentAttempt $attempt, CarbonImmutable $expiresAt): CheckoutSession;

    /** Returns an existing hosted Checkout Session so a retry resumes rather than creates another. */
    public function retrieveCheckout(PaymentAttempt $attempt): CheckoutSession;

    /** Returns the provider's current refund state. The stored key makes a retried request safe. */
    public function refund(Payment $payment, Refund $refund): GatewayRefund;

    /** Retrieves the provider's settled charge and fee data, or null while settlement data is unavailable. */
    public function retrievePaymentLedger(Payment $payment): ?GatewayPaymentLedger;

    /** Retrieves the provider's settled refund and fee data, or null while settlement data is unavailable. */
    public function retrieveRefundLedger(Refund $refund): ?GatewayRefundLedger;

    /**
     * Verifies the signature and returns the event in a provider-neutral shape.
     *
     * @return array{id: string, type: string, object: array<string, mixed>}
     */
    public function parseWebhook(string $payload, string $signature): array;
}
