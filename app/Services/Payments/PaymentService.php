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
    public function __construct(private readonly PaymentGateway $gateway) {}

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
        $attempt->forceFill([
            'status' => PaymentAttemptStatus::Paid,
            'provider_payment_intent' => $paymentIntent,
            'paid_at' => CarbonImmutable::now('UTC'),
        ])->save();

        $booking->payment()->update([
            'status' => PaymentStatus::Paid->value,
            'provider_reference' => $attempt->provider_reference,
            'provider_payment_intent' => $paymentIntent,
            'paid_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    public function markFailed(Booking $booking): void
    {
        $booking->payment()->update(['status' => PaymentStatus::Failed->value]);
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

        $payment->forceFill([
            'status' => PaymentStatus::Paid,
            'paid_at' => CarbonImmutable::now('UTC'),
            'recorded_by' => $admin->id,
        ])->save();

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

            $payment->forceFill([
                'status' => $grossAmount === $refundable ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
            ])->save();

            return $refund;
        });
    }
}
