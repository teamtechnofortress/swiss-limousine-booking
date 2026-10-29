<?php

namespace App\Services\Payments\Webhooks;

interface WebhookHandler
{
    /** @param array<string, mixed> $object The event's data object. */
    public function handle(array $object): void;
}
