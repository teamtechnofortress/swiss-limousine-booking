<?php

namespace App\Services\Payments;

use App\Data\CheckoutSession;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingException;
use App\Gateways\Payments\PaymentGateway;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Money movement only. Booking state changes live in BookingService. */
final class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PaymentEventLogger $events,
    ) {}

    /**
     * The attempt already exists in MySQL before calling Stripe. If PHP dies after Stripe accepts
     * the request, a retry repeats this request with the same key and Stripe returns the same session.
     */
    public function createCheckout(Booking $booking, PaymentAttempt $attempt, CarbonImmutable $expiresAt): CheckoutSession
    {
        $session = $this->gateway->createCheckout($booking, $attempt, $expiresAt);

        DB::transaction(function () use ($booking, $attempt, $session): void {
            $locked = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->provider_reference !== null && $locked->provider_reference !== $session->id) {
                throw new \RuntimeException('Stripe returned a different session for an existing idempotency key.');
            }

            if ($locked->status->isOpen()) {
                $locked->forceFill([
                    'provider_reference' => $session->id,
                    'status' => PaymentAttemptStatus::Pending,
                ])->save();
            }

            if ($locked->is_current && $locked->status->isOpen()) {
                $booking->payment()->update([
                    'provider' => $this->gateway->name(),
                    'provider_reference' => $session->id,
                    'status' => PaymentStatus::Pending->value,
                ]);
            }
        });

        return $session;
    }

    public function resumeCheckout(PaymentAttempt $attempt): CheckoutSession
    {
        return $this->gateway->retrieveCheckout($attempt);
    }

    /** Called only after the webhook has been matched to the current, exact Checkout Session. */
    public function recordPaid(Booking $booking, PaymentAttempt $attempt, ?string $paymentIntent): void
    {
        $previousStatus = $booking->payment?->status?->value;

        $attempt->forceFill([
            'status' => PaymentAttemptStatus::Paid,
            'provider_payment_intent' => $paymentIntent,
            'paid_at' => CarbonImmutable::now('UTC'),
        ])->save();

        $paymentAttributes = [
            'provider' => $attempt->provider,
            'status' => PaymentStatus::Paid->value,
            'provider_payment_intent' => $paymentIntent,
            'paid_at' => CarbonImmutable::now('UTC'),
        ];

        // A payment_intent.succeeded webhook can arrive before the Checkout Session response
        // is persisted. Do not overwrite a known Checkout Session with null in that race.
        if ($attempt->provider_reference !== null) {
            $paymentAttributes['provider_reference'] = $attempt->provider_reference;
        }

        $booking->payment()->update($paymentAttributes);
        $this->events->payment('Payment marked as paid.', [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->reference,
            'payment_attempt_id' => $attempt->id,
            'checkout_session_id' => $attempt->provider_reference,
            'payment_intent_id' => $paymentIntent,
            'from_status' => $previousStatus,
            'to_status' => PaymentStatus::Paid->value,
        ]);
    }

    public function markFailed(Booking $booking): void
    {
        $previousStatus = $booking->payment?->status?->value;
        $booking->payment()->update(['status' => PaymentStatus::Failed->value]);
        $this->events->payment('Payment marked as failed.', [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->reference,
            'from_status' => $previousStatus,
            'to_status' => PaymentStatus::Failed->value,
        ], 'warning');
    }

    /** Reconcile an individual Stripe refund, including refunds made from the Stripe Dashboard. */
    public function reconcileStripeRefund(array $providerRefund): void
    {
        $providerReference = $providerRefund['id'] ?? null;
        $paymentIntent = $providerRefund['payment_intent'] ?? null;

        if (! is_string($providerReference) || $providerReference === '' || ! is_string($paymentIntent) || $paymentIntent === '') {
            return;
        }

        DB::transaction(function () use ($providerRefund, $providerReference, $paymentIntent): void {
            $payment = Payment::query()
                ->where('provider_payment_intent', $paymentIntent)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                return;
            }

            $providerStatus = (string) ($providerRefund['status'] ?? 'pending');
            $refund = Refund::query()->where('provider_reference', $providerReference)->lockForUpdate()->first();

            // A late pending/failed event must never downgrade a refund already known to be successful.
            if ($refund?->status === 'succeeded' && $providerStatus !== 'succeeded') {
                return;
            }

            $grossAmount = (int) ($providerRefund['amount'] ?? 0);
            if ($grossAmount <= 0) {
                return;
            }

            $vatAmount = 0;
            if ($providerStatus === 'succeeded') {
                $previousGross = (int) $payment->refunds()
                    ->where('status', 'succeeded')
                    ->when($refund !== null, fn ($query) => $query->where('id', '!=', $refund->id))
                    ->sum('gross_amount');
                $previousVat = (int) $payment->refunds()
                    ->where('status', 'succeeded')
                    ->when($refund !== null, fn ($query) => $query->where('id', '!=', $refund->id))
                    ->sum('vat_amount');
                $remainingGross = $payment->gross_amount - $previousGross;

                if ($grossAmount > $remainingGross) {
                    return;
                }

                $vatAmount = $grossAmount === $remainingGross
                    ? $payment->vat_amount - $previousVat
                    : min(
                        $payment->vat_amount - $previousVat,
                        (int) round($payment->vat_amount * $grossAmount / $payment->gross_amount),
                    );
            }

            $attributes = [
                'payment_id' => $payment->id,
                'gross_amount' => $grossAmount,
                'vat_amount' => $vatAmount,
                'status' => $providerStatus,
            ];

            if ($refund === null) {
                Refund::query()->create($attributes + ['provider_reference' => $providerReference]);
            } else {
                $refund->forceFill($attributes)->save();
            }

            if ($providerStatus !== 'succeeded') {
                $this->events->payment('Stripe refund status received.', [
                    'payment_id' => $payment->id,
                    'payment_intent_id' => $paymentIntent,
                    'refund_id' => $providerReference,
                    'refund_status' => $providerStatus,
                    'refund_amount' => $grossAmount,
                ], 'warning');

                return;
            }

            $refundedGross = (int) $payment->refunds()->where('status', 'succeeded')->sum('gross_amount');
            $payment->forceFill([
                'status' => $refundedGross >= $payment->gross_amount
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded,
            ])->save();

            $this->events->payment('Stripe refund reconciled.', [
                'payment_id' => $payment->id,
                'payment_intent_id' => $paymentIntent,
                'refund_id' => $providerReference,
                'refund_status' => $providerStatus,
                'refund_amount' => $grossAmount,
                'to_status' => $payment->status->value,
            ]);
        });
    }

    /** Cash or bank payment recorded by staff. */
    public function markOfflinePaid(Booking $booking, User $admin): Payment
    {
        $payment = $booking->payment;

        if ($payment === null || $payment->method === PaymentMethod::Online) {
            throw BookingException::paymentMethodUnavailable();
        }

        if ($payment->status === PaymentStatus::Paid) {
            return $payment;
        }

        $previousStatus = $payment->status->value;
        $payment->forceFill([
            'status' => PaymentStatus::Paid,
            'paid_at' => CarbonImmutable::now('UTC'),
            'recorded_by' => $admin->id,
        ])->save();
        $this->events->payment('Offline payment marked as paid by an administrator.', [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->reference,
            'payment_id' => $payment->id,
            'from_status' => $previousStatus,
            'to_status' => PaymentStatus::Paid->value,
            'recorded_by' => $admin->id,
        ]);

        return $payment;
    }

    /**
     * Admin-only refund, capped at what is still refundable. VAT is returned in proportion.
     * The payment row is locked so two refunds cannot overlap.
     */
    public function refund(Booking $booking, int $grossAmount, string $reason, User $admin): Refund
    {
        return DB::transaction(function () use ($booking, $grossAmount, $reason, $admin): Refund {
            $payment = Payment::query()->whereKey($booking->payment?->id)->lockForUpdate()->first()
                ?? throw BookingException::notRefundable();

            if (! in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
                throw BookingException::notRefundable();
            }

            $alreadyRefunded = (int) $payment->refunds()->sum('gross_amount');
            $alreadyRefundedVat = (int) $payment->refunds()->sum('vat_amount');
            $refundable = $payment->gross_amount - $alreadyRefunded;

            if ($grossAmount <= 0 || $grossAmount > $refundable) {
                throw BookingException::refundTooHigh($refundable);
            }

            // For partial refunds use the proportional VAT split. The final refund receives the
            // remainder so all refund lines reconcile exactly to the original VAT amount.
            $vatAmount = $grossAmount === $refundable
                ? $payment->vat_amount - $alreadyRefundedVat
                : min(
                    $payment->vat_amount - $alreadyRefundedVat,
                    (int) round($payment->vat_amount * $grossAmount / $payment->gross_amount),
                );

            $providerReference = $payment->method === PaymentMethod::Online
                ? $this->gateway->refund($payment, $grossAmount, "refund:{$payment->id}:{$alreadyRefunded}:{$grossAmount}")
                : null;

            $refund = $payment->refunds()->create([
                'gross_amount' => $grossAmount,
                'vat_amount' => $vatAmount,
                'provider_reference' => $providerReference,
                'status' => 'succeeded',
                'reason' => $reason,
                'requested_by' => $admin->id,
            ]);

            $previousStatus = $payment->status->value;
            $payment->forceFill([
                'status' => $grossAmount === $refundable ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
            ])->save();
            $this->events->payment('Refund completed and payment status updated.', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference,
                'payment_id' => $payment->id,
                'refund_id' => $refund->id,
                'refund_amount' => $grossAmount,
                'from_status' => $previousStatus,
                'to_status' => $payment->status->value,
            ]);

            return $refund;
        });
    }
}
