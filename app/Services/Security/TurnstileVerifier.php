<?php

namespace App\Services\Security;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Fails closed: a missing token or an unreachable Cloudflare refuses the request. */
final class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private readonly SettingsService $settings) {}

    public function verify(?string $token, ?string $ip): bool
    {
        if (! $this->settings->bool('turnstile_enabled')) {
            return true;
        }

        if ($token === null || $token === '') {
            return false;
        }

        $secret = $this->settings->secret('turnstile_secret_key')
            ?? throw new RuntimeException('Turnstile secret key is not configured.');

        $response = Http::asForm()
            ->timeout(5)
            ->retry(1, 100)
            ->post(self::VERIFY_URL, array_filter([
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]))
            ->json();

        return (bool) ($response['success'] ?? false);
    }
}
