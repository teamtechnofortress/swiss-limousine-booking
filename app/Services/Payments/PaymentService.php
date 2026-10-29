<?php

namespace App\Services\Payments;

use App\Data\CheckoutSession;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingException;
use App\Gateways\Payments\PaymentGateway;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Money movement only. Booking state changes live in BookingService. */
final class PaymentService
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function createCheckout(Booking $booking, CarbonImmutable $expiresAt): CheckoutSession
    {
        $session = $this->gateway->createCheckout($booking, $expiresAt);

        $booking->payment()->update([
            'provider' => $this->gateway->name(),
            'provider_reference' => $session->id,
            'status' => PaymentStatus::Pending->value,
        ]);

        return $session;
    }

    public function recordPaid(Booking $booking, string $sessionId, ?string $paymentIntent): void
    {
        $booking->payment()->update([
            'status' => PaymentStatus::Paid->value,
            'provider_reference' => $sessionId,
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
            $refundable = $payment->gross_amount - $alreadyRefunded;

            if ($grossAmount <= 0 || $grossAmount > $refundable) {
                throw BookingException::refundTooHigh($refundable);
            }

            $providerReference = $payment->method === PaymentMethod::Online
                ? $this->gateway->refund($payment, $grossAmount, "refund:{$payment->id}:{$alreadyRefunded}:{$grossAmount}")
                : null;

            $refund = $payment->refunds()->create([
                'gross_amount' => $grossAmount,
                'vat_amount' => (int) round($payment->vat_amount * $grossAmount / $payment->gross_amount),
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
