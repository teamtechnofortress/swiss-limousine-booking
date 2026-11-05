<?php

namespace App\Http\Controllers\Webhooks;

use App\Gateways\Payments\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Verifies, stores and queues. Processing happens in ProcessPaymentWebhook. */
final class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PaymentEventLogger $events,
    ) {}

    public function __invoke(Request $request, string $provider): JsonResponse
    {
        if ($provider !== $this->gateway->name()) {
            abort(404);
        }

        try {
            $event = $this->gateway->parseWebhook(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
            );
        } catch (RuntimeException $e) {
            Log::warning('Rejected payment webhook', ['provider' => $provider, 'reason' => $e->getMessage()]);
            $this->events->webhook('Webhook rejected: signature verification failed.', [
                'provider' => $provider,
                'reason' => $e->getMessage(),
            ], 'warning');

            return response()->json(['error' => 'invalid_signature'], 400);
        }

        $this->events->webhook('Webhook signature verified.', [
            'provider' => $provider,
            'provider_event_id' => $event['id'],
            'event_type' => $event['type'],
        ]);

        $record = WebhookEvent::query()->firstOrCreate(
            ['provider_event_id' => $event['id']],
            ['provider' => $provider, 'type' => $event['type'], 'payload' => $event],
        );

        $shouldProcess = $record->processed_at === null;
        $this->events->webhook($record->wasRecentlyCreated ? 'Webhook event stored.' : 'Webhook event received again.', [
            'webhook_event_id' => $record->id,
            'provider_event_id' => $event['id'],
            'event_type' => $event['type'],
            'duplicate' => ! $record->wasRecentlyCreated,
            'already_processed' => ! $shouldProcess,
        ]);

        if ($shouldProcess) {
            ProcessPaymentWebhook::dispatch($record->id);
            $this->events->webhook('Webhook event queued for processing.', [
                'webhook_event_id' => $record->id,
                'provider_event_id' => $event['id'],
                'event_type' => $event['type'],
            ]);
        }

        return response()->json(['received' => true]);
    }
}
