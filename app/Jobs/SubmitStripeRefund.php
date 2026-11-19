<?php

namespace App\Jobs;

use App\Services\Payments\PaymentService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Sends a persisted refund request to Stripe. The stable idempotency key makes retries safe. */
final class SubmitStripeRefund implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly int $refundId) {}

    public function uniqueId(): string
    {
        return 'stripe-refund-'.$this->refundId;
    }

    public function handle(PaymentService $payments): void
    {
        $payments->submitOnlineRefund($this->refundId);
    }
}
