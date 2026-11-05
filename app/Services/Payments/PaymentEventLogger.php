<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Log;

/**
 * Development diagnostics for Stripe processing.
 *
 * Payment providers must never receive secrets, signatures, or full payloads in logs.
 */
final class PaymentEventLogger
{
    /** @param array<string, mixed> $context */
    public function webhook(string $message, array $context = [], string $level = 'info'): void
    {
        $this->write('webhooks', $message, $context, $level);
    }

    /** @param array<string, mixed> $context */
    public function payment(string $message, array $context = [], string $level = 'info'): void
    {
        $this->write('payments', $message, $context, $level);
    }

    /** @param array<string, mixed> $context */
    private function write(string $file, string $message, array $context, string $level): void
    {
        if (! app()->environment(['local', 'development'])) {
            return;
        }

        Log::build([
            'driver' => 'single',
            'path' => storage_path("logs/{$file}.log"),
            'level' => 'debug',
        ])->log($level, $message, ['environment' => app()->environment()] + $context);
    }
}
