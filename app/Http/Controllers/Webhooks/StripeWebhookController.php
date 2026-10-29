<?php

namespace App\Http\Controllers\Webhooks;

use App\Gateways\Payments\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Verifies, stores and queues. Processing happens in ProcessPaymentWebhook. */
final class StripeWebhookController extends Controller
{
    public function __construct(private readonly PaymentGateway $gateway) {}

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

            return response()->json(['error' => 'invalid_signature'], 400);
        }

        $record = WebhookEvent::query()->firstOrCreate(
            ['provider_event_id' => $event['id']],
            ['provider' => $provider, 'type' => $event['type'], 'payload' => $event],
        );

        if ($record->processed_at === null) {
            ProcessPaymentWebhook::dispatch($record->id);
        }

        return response()->json(['received' => true]);
    }
}
