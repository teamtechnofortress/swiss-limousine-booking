<?php

namespace App\Gateways\Payments;

use App\Data\CheckoutSession;
use App\Data\GatewayBalanceTransaction;
use App\Data\GatewayPaymentLedger;
use App\Data\GatewayRefund;
use App\Data\GatewayRefundLedger;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use RuntimeException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

final class StripeGateway implements PaymentGateway
{
    public function __construct(private readonly SettingsService $settings) {}

    public function name(): string
    {
        return 'stripe';
    }

    public function createCheckout(Booking $booking, PaymentAttempt $attempt, CarbonImmutable $expiresAt): CheckoutSession
    {
        $session = $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'client_reference_id' => $booking->reference,
            'customer_email' => $booking->customer_email,
            'expires_at' => $expiresAt->getTimestamp(),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($booking->currency),
                    'unit_amount' => $booking->total_amount,
                    'product_data' => ['name' => 'Chauffeur transfer '.$booking->reference],
                ],
            ]],
            'metadata' => [
                'booking_reference' => $booking->reference,
                'payment_attempt_id' => (string) $attempt->id,
            ],
            'payment_intent_data' => ['metadata' => [
                'booking_reference' => $booking->reference,
                'payment_attempt_id' => (string) $attempt->id,
            ]],
            'success_url' => $this->frontendUrl("/bookings/{$booking->reference}?payment=success"),
            'cancel_url' => $this->frontendUrl("/bookings/{$booking->reference}?payment=cancelled"),
        ], ['idempotency_key' => $attempt->idempotency_key]);

        return new CheckoutSession((string) $session->id, (string) $session->url);
    }

    public function retrieveCheckout(PaymentAttempt $attempt): CheckoutSession
    {
        $reference = $attempt->provider_reference
            ?? throw new RuntimeException('Cannot resume a Checkout Session without its provider reference.');

        $session = $this->client()->checkout->sessions->retrieve($reference);

        if (! is_string($session->url) || $session->url === '') {
            throw new RuntimeException('Stripe returned no Checkout URL.');
        }

        return new CheckoutSession((string) $session->id, $session->url);
    }

    public function refund(Payment $payment, Refund $localRefund): GatewayRefund
    {
        if ($payment->provider_payment_intent === null) {
            throw new RuntimeException('Cannot refund an online payment without a Stripe PaymentIntent.');
        }

        $stripeRefund = $this->client()->refunds->create(
            [
                'payment_intent' => $payment->provider_payment_intent,
                'amount' => $localRefund->gross_amount,
                'metadata' => ['limo_refund_id' => (string) $localRefund->id],
            ],
            ['idempotency_key' => $localRefund->idempotency_key],
        );

        $paymentIntent = $stripeRefund->payment_intent;

        return new GatewayRefund(
            id: (string) $stripeRefund->id,
            status: (string) $stripeRefund->status,
            paymentIntent: is_string($paymentIntent)
                ? $paymentIntent
                : (is_object($paymentIntent) && isset($paymentIntent->id) ? (string) $paymentIntent->id : null),
            amount: (int) $stripeRefund->amount,
            metadata: $stripeRefund->metadata?->toArray() ?? [],
        );
    }

    public function retrievePaymentLedger(Payment $payment): ?GatewayPaymentLedger
    {
        $paymentIntentId = $payment->provider_payment_intent
            ?? throw new RuntimeException('Cannot retrieve a payment ledger without a Stripe PaymentIntent.');

        $paymentIntent = $this->client()->paymentIntents->retrieve(
            $paymentIntentId,
            ['expand' => ['latest_charge.balance_transaction']],
        );

        $charge = $paymentIntent->latest_charge;
        if (is_string($charge)) {
            $charge = $this->client()->charges->retrieve($charge, ['expand' => ['balance_transaction']]);
        }

        if (! is_object($charge) || ! isset($charge->id)) {
            return null;
        }

        $balanceTransaction = $this->retrieveBalanceTransaction($charge->balance_transaction ?? null);

        return $balanceTransaction === null
            ? null
            : new GatewayPaymentLedger((string) $charge->id, $balanceTransaction);
    }

    public function retrieveRefundLedger(Refund $refund): ?GatewayRefundLedger
    {
        $refundId = $refund->provider_reference
            ?? throw new RuntimeException('Cannot retrieve a refund ledger without a Stripe refund ID.');

        $stripeRefund = $this->client()->refunds->retrieve($refundId, ['expand' => ['balance_transaction']]);
        $balanceTransaction = $this->retrieveBalanceTransaction($stripeRefund->balance_transaction ?? null);

        return $balanceTransaction === null ? null : new GatewayRefundLedger($balanceTransaction);
    }

    public function parseWebhook(string $payload, string $signature): array
    {
        try {
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret());
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            throw new RuntimeException('Invalid webhook signature.', 0, $e);
        }

        return [
            'id' => (string) $event->id,
            'type' => (string) $event->type,
            'object' => $event->data->object->toArray(),
        ];
    }

    private function client(): StripeClient
    {
        $key = $this->settings->secret('stripe_secret_key')
            ?? throw new RuntimeException('Stripe secret key is not configured.');

        return new StripeClient($key);
    }

    private function webhookSecret(): string
    {
        return $this->settings->secret('stripe_webhook_secret')
            ?? throw new RuntimeException('Stripe webhook secret is not configured.');
    }

    private function retrieveBalanceTransaction(mixed $value): ?GatewayBalanceTransaction
    {
        if (is_string($value) && $value !== '') {
            $value = $this->client()->balanceTransactions->retrieve($value);
        }

        if (! is_object($value) || ! isset($value->id, $value->amount, $value->currency, $value->fee, $value->net)) {
            return null;
        }

        $feeDetails = $value->fee_details ?? [];
        if (is_object($feeDetails) && method_exists($feeDetails, 'toArray')) {
            $feeDetails = $feeDetails->toArray();
        }

        return new GatewayBalanceTransaction(
            id: (string) $value->id,
            amount: (int) $value->amount,
            currency: strtoupper((string) $value->currency),
            feeAmount: (int) $value->fee,
            feeDetails: is_array($feeDetails) ? array_values($feeDetails) : [],
            netAmount: (int) $value->net,
            exchangeRate: isset($value->exchange_rate) ? (string) $value->exchange_rate : null,
        );
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('limo.frontend_url'), '/').$path;
    }
}
