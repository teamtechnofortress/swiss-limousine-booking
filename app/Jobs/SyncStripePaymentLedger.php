<?php

namespace App\Jobs;

use App\Services\Payments\PaymentService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/** Fetches Stripe's settled Charge/BalanceTransaction after payment confirmation. */
final class SyncStripePaymentLedger implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly int $paymentId) {}

    public function uniqueId(): string
    {
        return 'stripe-payment-ledger-'.$this->paymentId;
    }

    public function handle(PaymentService $payments): void
    {
        if (! $payments->syncOnlinePaymentLedger($this->paymentId)) {
            throw new RuntimeException('Stripe payment settlement data is not available yet.');
        }
    }
}
