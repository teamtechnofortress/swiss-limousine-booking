<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\Payments\PaymentEventLogger;
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

    public function handle(WebhookHandlerRegistry $registry, PaymentEventLogger $events): void
    {
        $event = WebhookEvent::query()->findOrFail($this->webhookEventId);

        if ($event->processed_at !== null) {
            $events->webhook('Webhook job skipped: event was already processed.', [
                'webhook_event_id' => $event->id,
                'provider_event_id' => $event->provider_event_id,
                'event_type' => $event->type,
            ]);

            return;
        }

        $events->webhook('Webhook processing started.', [
            'webhook_event_id' => $event->id,
            'provider_event_id' => $event->provider_event_id,
            'event_type' => $event->type,
        ]);

        $handler = $registry->for($event->type);

        if ($handler === null) {
            $events->webhook('Webhook event has no registered handler; recorded without state changes.', [
                'webhook_event_id' => $event->id,
                'provider_event_id' => $event->provider_event_id,
                'event_type' => $event->type,
            ], 'warning');
        } else {
            try {
                $handler->handle($event->payload['object'] ?? []);
            } catch (\Throwable $e) {
                $events->webhook('Webhook processing failed; the queue will retry it.', [
                    'webhook_event_id' => $event->id,
                    'provider_event_id' => $event->provider_event_id,
                    'event_type' => $event->type,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ], 'error');

                throw $e;
            }
        }

        $event->forceFill(['processed_at' => now()])->save();
        $events->webhook('Webhook processing completed.', [
            'webhook_event_id' => $event->id,
            'provider_event_id' => $event->provider_event_id,
            'event_type' => $event->type,
            'processed_at' => $event->processed_at?->toIso8601String(),
        ]);
    }
}
