<?php

namespace App\Gateways\Payments;

use App\Data\CheckoutSession;
use App\Models\Booking;
use App\Models\Payment;
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

    public function createCheckout(Booking $booking, CarbonImmutable $expiresAt): CheckoutSession
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
            'metadata' => ['booking_reference' => $booking->reference],
            'payment_intent_data' => ['metadata' => ['booking_reference' => $booking->reference]],
            'success_url' => $this->frontendUrl("/bookings/{$booking->reference}?payment=success"),
            'cancel_url' => $this->frontendUrl("/bookings/{$booking->reference}?payment=cancelled"),
        ]);

        return new CheckoutSession((string) $session->id, (string) $session->url);
    }

    public function refund(Payment $payment, int $amount, string $idempotencyKey): string
    {
        $refund = $this->client()->refunds->create(
            ['payment_intent' => $payment->provider_payment_intent, 'amount' => $amount],
            ['idempotency_key' => $idempotencyKey],
        );

        return (string) $refund->id;
    }

    public function parseWebhook(string $payload, string $signature): array
    {
        try {
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret());
        } catch (SignatureVerificationException | \UnexpectedValueException $e) {
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

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('limo.frontend_url'), '/').$path;
    }
}
