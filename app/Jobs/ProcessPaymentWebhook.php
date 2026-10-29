<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\Payments\Webhooks\WebhookHandlerRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessPaymentWebhook implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly int $webhookEventId) {}

    public function uniqueId(): string
    {
        return 'payment-webhook-'.$this->webhookEventId;
    }

    public function handle(WebhookHandlerRegistry $registry): void
    {
        $event = WebhookEvent::query()->findOrFail($this->webhookEventId);

        if ($event->processed_at !== null) {
            return;
        }

        $registry->for($event->type)?->handle($event->payload['object'] ?? []);

        $event->forceFill(['processed_at' => now()])->save();
    }
}
