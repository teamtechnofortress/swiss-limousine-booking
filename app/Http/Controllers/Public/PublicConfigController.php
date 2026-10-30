<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Browser-safe configuration needed before a customer can request a quote.
 *
 * Keep this deliberately allow-listed. In particular, google_server_key and
 * every other secret setting must never be added to this response.
 */
final class PublicConfigController extends Controller
{
    public function __invoke(SettingsService $settings): JsonResponse
    {
        $turnstileEnabled = $settings->bool('turnstile_enabled');

        $config = [
            'google_browser_key' => $settings->string('google_browser_key'),
            'currency' => $settings->string('currency'),
            'turnstile_enabled' => $turnstileEnabled,
            // A Turnstile site key is public by design. Never expose turnstile_secret_key.
            'turnstile_site_key' => $turnstileEnabled ? $settings->string('turnstile_site_key') : '',
        ];

        Log::debug('Public booking configuration sent to browser.', ['config' => $config]);

        return response()->json([
            'data' => $config,
        ])->header('Cache-Control', 'public, max-age=300');
    }
}
