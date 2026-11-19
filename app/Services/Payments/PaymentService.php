<?php

namespace App\Services\Payments;

use App\Data\CheckoutSession;
use App\Data\GatewayBalanceTransaction;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Exceptions\BookingException;
use App\Gateways\Payments\PaymentGateway;
use App\Jobs\CancelBookingAfterRefundSucceeded;
use App\Jobs\SubmitStripeRefund;
use App\Jobs\SyncStripePaymentLedger;
use App\Jobs\SyncStripeRefundLedger;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use App\Models\User;
use App\Services\Admin\FinancialReportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        $this->forgetFinancialOverviewAfterCommit();

        $paymentId = (int) $booking->payment()->value('id');
        if ($paymentId > 0 && $paymentIntent !== null) {
            // Fee settlement must not delay the authoritative payment/booking confirmation transaction.
            DB::afterCommit(fn () => SyncStripePaymentLedger::dispatch($paymentId));
        }

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
        $this->forgetFinancialOverviewAfterCommit();
        $this->events->payment('Payment marked as failed.', [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->reference,
            'from_status' => $previousStatus,
            'to_status' => PaymentStatus::Failed->value,
        ], 'warning');
    }

    /** Reconcile an individual Stripe refund, including refunds made from the Stripe Dashboard. */
    public function reconcileStripeRefund(array $providerRefund, bool $fromWebhook = false): void
    {
        $providerReference = $providerRefund['id'] ?? null;
        $paymentIntent = $providerRefund['payment_intent'] ?? null;

        if (! is_string($providerReference) || $providerReference === '' || ! is_string($paymentIntent) || $paymentIntent === '') {
            return;
        }

        $providerStatus = RefundStatus::tryFrom((string) ($providerRefund['status'] ?? ''));

        if ($providerStatus === null) {
            $this->events->payment('Stripe refund received with an unsupported status.', [
                'refund_id' => $providerReference,
                'payment_intent_id' => $paymentIntent,
                'refund_status' => $providerRefund['status'] ?? null,
            ], 'warning');

            return;
        }

        DB::transaction(function () use ($providerRefund, $providerReference, $paymentIntent, $providerStatus): void {
            $payment = Payment::query()
                ->where('provider_payment_intent', $paymentIntent)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                return;
            }

            $refund = Refund::query()->where('provider_reference', $providerReference)->lockForUpdate()->first();

            $internalRefundId = data_get($providerRefund, 'metadata.limo_refund_id');
            if ($refund === null && is_numeric($internalRefundId)) {
                $refund = Refund::query()
                    ->whereKey((int) $internalRefundId)
                    ->where('payment_id', $payment->id)
                    ->lockForUpdate()
                    ->first();
            }

            // Webhooks may arrive out of order. A terminal provider state is never replaced by an older event.
            if ($refund?->status?->isTerminal() && $refund->status !== $providerStatus) {
                $this->events->payment('Ignored out-of-order Stripe refund status.', [
                    'payment_id' => $payment->id,
                    'refund_id' => $providerReference,
                    'existing_status' => $refund->status->value,
                    'received_status' => $providerStatus->value,
                ], 'warning');

                return;
            }

            $grossAmount = (int) ($providerRefund['amount'] ?? 0);
            if ($grossAmount <= 0) {
                return;
            }

            if ($refund !== null && $refund->gross_amount !== $grossAmount) {
                $this->events->payment('Stripe refund amount did not match the stored refund request.', [
                    'payment_id' => $payment->id,
                    'refund_id' => $providerReference,
                    'stored_amount' => $refund->gross_amount,
                    'provider_amount' => $grossAmount,
                ], 'error');

                return;
            }

            $vatAmount = $refund?->vat_amount ?? 0;
            if ($providerStatus === RefundStatus::Succeeded) {
                $previousGross = (int) $payment->refunds()
                    ->where('status', RefundStatus::Succeeded->value)
                    ->when($refund !== null, fn ($query) => $query->where('id', '!=', $refund->id))
                    ->sum('gross_amount');
                $previousVat = (int) $payment->refunds()
                    ->where('status', RefundStatus::Succeeded->value)
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
                'provider_reference' => $providerReference,
            ];

            if ($refund === null) {
                $refund = Refund::query()->create($attributes);
            } else {
                $refund->forceFill($attributes)->save();
            }

            if ($providerStatus !== RefundStatus::Succeeded) {
                $this->events->payment('Stripe refund status received.', [
                    'payment_id' => $payment->id,
                    'payment_intent_id' => $paymentIntent,
                    'refund_id' => $providerReference,
                    'refund_status' => $providerStatus->value,
                    'refund_amount' => $grossAmount,
                ], 'warning');

                return;
            }

            $this->refreshSettledRefundStatus($payment);

            // Refund settlement is an accounting concern and must not hold up webhook reconciliation.
            DB::afterCommit(fn () => SyncStripeRefundLedger::dispatch($refund->id));

            // A booking cancellation is deliberately tied to Stripe's signed success webhook, never
            // to the initial refund request or to the immediate API response from refund submission.
            if ($fromWebhook && $refund->cancel_booking_on_success) {
                DB::afterCommit(fn () => CancelBookingAfterRefundSucceeded::dispatch($refund->id));
            }

            $this->events->payment('Stripe refund reconciled.', [
                'payment_id' => $payment->id,
                'payment_intent_id' => $paymentIntent,
                'refund_id' => $providerReference,
                'refund_status' => $providerStatus->value,
                'refund_amount' => $grossAmount,
                'to_status' => $payment->status->value,
            ]);
        });
    }

    /** Sends one persisted online refund to Stripe. Retried calls use the same provider idempotency key. */
    public function submitOnlineRefund(int $refundId): void
    {
        $refund = Refund::query()->with('payment')->findOrFail($refundId);

        if (! $refund->status->reservesAmount() || $refund->provider_reference !== null) {
            return;
        }

        $payment = $refund->payment;
        if ($payment->method !== PaymentMethod::Online) {
            return;
        }

        // A missing fee record must never hold up an approved customer refund. Try to enrich the
        // original payment first, then leave a retrying job behind if Stripe has not exposed it yet.
        $this->ensurePaymentLedgerForRefund($payment);

        try {
            $result = $this->gateway->refund($payment, $refund);
        } catch (\Throwable $e) {
            // The outcome may be unknown after a network failure. Keep the reservation and retry with
            // the exact same idempotency key; marking it failed could permit a duplicate refund.
            $this->events->payment('Stripe refund submission failed; it will be retried.', [
                'payment_id' => $payment->id,
                'refund_id' => $refund->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ], 'error');

            throw $e;
        }

        $this->reconcileStripeRefund($result->toArray());
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
     * Admin-only refund request. Pending provider refunds reserve their amount; only completed
     * refunds change the payment's settled status.
     */
    public function refund(Booking $booking, int $grossAmount, string $reason, User $admin, bool $cancelBookingOnSuccess = false): Refund
    {
        [$refund, $isOnline] = DB::transaction(function () use ($booking, $grossAmount, $reason, $admin, $cancelBookingOnSuccess): array {
            $payment = Payment::query()->whereKey($booking->payment?->id)->lockForUpdate()->first()
                ?? throw BookingException::notRefundable();

            if (! in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
                throw BookingException::notRefundable();
            }

            $alreadyRefunded = (int) $payment->refunds()
                ->where('status', RefundStatus::Succeeded->value)
                ->sum('gross_amount');
            $reservedForPendingRefunds = (int) $payment->refunds()
                ->whereIn('status', [RefundStatus::Pending->value, RefundStatus::RequiresAction->value])
                ->sum('gross_amount');
            $alreadyRefundedVat = (int) $payment->refunds()
                ->where('status', RefundStatus::Succeeded->value)
                ->sum('vat_amount');
            $refundable = $payment->gross_amount - $alreadyRefunded - $reservedForPendingRefunds;

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

            $isOnline = $payment->method === PaymentMethod::Online;

            $refund = $payment->refunds()->create([
                'gross_amount' => $grossAmount,
                'vat_amount' => $vatAmount,
                'idempotency_key' => $isOnline ? 'refund:'.$payment->id.':'.Str::uuid() : null,
                'status' => $isOnline ? RefundStatus::Pending : RefundStatus::Succeeded,
                'reason' => $reason,
                'cancel_booking_on_success' => $isOnline && $cancelBookingOnSuccess,
                'requested_by' => $admin->id,
            ]);

            if (! $isOnline) {
                $this->refreshSettledRefundStatus($payment);
            }

            $this->events->payment($isOnline ? 'Stripe refund requested.' : 'Offline refund recorded and payment status updated.', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference,
                'payment_id' => $payment->id,
                'refund_id' => $refund->id,
                'refund_amount' => $grossAmount,
                'refund_status' => $refund->status->value,
                'payment_status' => $payment->status->value,
            ]);

            return [$refund, $isOnline];
        });

        if ($isOnline) {
            SubmitStripeRefund::dispatch($refund->id);
        }

        return $refund;
    }

    /**
     * Saves Stripe's charge balance transaction after payment confirmation. Returns false while
     * Stripe has not made the settlement record available so the queued job can retry.
     */
    public function syncOnlinePaymentLedger(int $paymentId): bool
    {
        $payment = Payment::query()->find($paymentId);

        if ($payment === null || $payment->provider_ledger_synced_at !== null || $payment->method !== PaymentMethod::Online || $payment->provider !== $this->gateway->name()) {
            return true;
        }

        $ledger = $this->gateway->retrievePaymentLedger($payment);

        if ($ledger === null) {
            return false;
        }

        DB::transaction(function () use ($payment, $ledger): void {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if ($locked === null || $locked->provider_payment_intent !== $payment->provider_payment_intent) {
                return;
            }

            $locked->forceFill([
                'provider_charge_reference' => $ledger->chargeId,
                ...$this->providerLedgerAttributes($ledger->balanceTransaction),
            ])->save();
        });

        $this->events->payment('Stripe payment settlement ledger synchronised.', [
            'payment_id' => $payment->id,
            'payment_intent_id' => $payment->provider_payment_intent,
            'charge_id' => $ledger->chargeId,
            'balance_transaction_id' => $ledger->balanceTransaction->id,
            'fee_amount' => $ledger->balanceTransaction->feeAmount,
            'net_amount' => $ledger->balanceTransaction->netAmount,
            'balance_currency' => $ledger->balanceTransaction->currency,
        ]);

        return true;
    }

    /**
     * Saves Stripe's balance transaction for a succeeded refund. Returns false while Stripe has
     * not made the settlement record available so the queued job can retry.
     */
    public function syncOnlineRefundLedger(int $refundId): bool
    {
        $refund = Refund::query()->with('payment')->find($refundId);

        if ($refund === null || $refund->provider_ledger_synced_at !== null || $refund->status !== RefundStatus::Succeeded || $refund->payment?->method !== PaymentMethod::Online || $refund->payment->provider !== $this->gateway->name()) {
            return true;
        }

        $ledger = $this->gateway->retrieveRefundLedger($refund);

        if ($ledger === null) {
            return false;
        }

        DB::transaction(function () use ($refund, $ledger): void {
            $locked = Refund::query()->whereKey($refund->id)->lockForUpdate()->first();

            if ($locked === null || $locked->provider_reference !== $refund->provider_reference) {
                return;
            }

            $locked->forceFill($this->providerLedgerAttributes($ledger->balanceTransaction))->save();
        });

        $this->events->payment('Stripe refund settlement ledger synchronised.', [
            'refund_id' => $refund->id,
            'payment_id' => $refund->payment_id,
            'balance_transaction_id' => $ledger->balanceTransaction->id,
            'fee_amount' => $ledger->balanceTransaction->feeAmount,
            'net_amount' => $ledger->balanceTransaction->netAmount,
            'balance_currency' => $ledger->balanceTransaction->currency,
        ]);

        return true;
    }

    /** Applies the payment-level status derived solely from settled refund money. */
    private function refreshSettledRefundStatus(Payment $payment): void
    {
        $refundedGross = (int) $payment->refunds()
            ->where('status', RefundStatus::Succeeded->value)
            ->sum('gross_amount');

        if ($refundedGross <= 0) {
            return;
        }

        $payment->forceFill([
            'status' => $refundedGross >= $payment->gross_amount
                ? PaymentStatus::Refunded
                : PaymentStatus::PartiallyRefunded,
        ])->save();
    }

    /** @return array<string, mixed> */
    private function providerLedgerAttributes(GatewayBalanceTransaction $transaction): array
    {
        return [
            'provider_balance_transaction_reference' => $transaction->id,
            'provider_balance_amount' => $transaction->amount,
            'provider_balance_currency' => $transaction->currency,
            'provider_fee_amount' => $transaction->feeAmount,
            'provider_fee_details' => $transaction->feeDetails,
            'provider_net_amount' => $transaction->netAmount,
            'provider_exchange_rate' => $transaction->exchangeRate,
            'provider_ledger_synced_at' => CarbonImmutable::now('UTC'),
        ];
    }

    private function ensurePaymentLedgerForRefund(Payment $payment): void
    {
        if ($payment->provider_ledger_synced_at !== null) {
            return;
        }

        try {
            if (! $this->syncOnlinePaymentLedger($payment->id)) {
                SyncStripePaymentLedger::dispatch($payment->id);
            }
        } catch (\Throwable $e) {
            $this->events->payment('Stripe payment ledger lookup was unavailable before refund; refund will continue.', [
                'payment_id' => $payment->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ], 'warning');

            SyncStripePaymentLedger::dispatch($payment->id);
        }
    }

    private function forgetFinancialOverviewAfterCommit(): void
    {
        DB::afterCommit(fn () => app(FinancialReportService::class)->forget());
    }
}
