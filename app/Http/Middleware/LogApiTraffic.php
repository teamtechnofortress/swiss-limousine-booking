<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class LogApiTraffic
{
    /** @var array<int, string> */
    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'turnstile_token',
        'cf-turnstile-response',
        'authorization',
        'card_number',
        'cvc',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldLog()) {
            $request->attributes->set('_api_log_started_at', microtime(true));
        }

        return $next($request);
    }

    /**
     * Log after Laravel has created the final response, including validation
     * errors and other exceptions rendered by the exception handler.
     */
    public function terminate(Request $request, Response $response): void
    {
        $startedAt = $request->attributes->get('_api_log_started_at');

        if (! is_float($startedAt)) {
            return;
        }

        $this->logger()->info('API request completed', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route' => $request->route()?->getName(),
            'ip' => $request->ip(),
            'request' => $this->redact($request->all()),
            'response_status' => $response->getStatusCode(),
            'response' => $this->responseBody($response),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }

    private function shouldLog(): bool
    {
        return app()->environment(['local', 'development', 'dev']);
    }

    private function logger(): \Psr\Log\LoggerInterface
    {
        return Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/api-dev.log'),
            'level' => 'debug',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($this->isSensitiveField((string) $key)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }

    private function isSensitiveField(string $key): bool
    {
        $key = strtolower($key);

        return in_array($key, self::SENSITIVE_FIELDS, true)
            || str_contains($key, 'token')
            || str_contains($key, 'password')
            || str_contains($key, 'secret')
            || str_contains($key, 'authorization');
    }

    private function responseBody(Response $response): mixed
    {
        $content = $response->getContent();

        if ($content === false || $content === '') {
            return null;
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $this->redact($decoded) : $decoded;
        } catch (Throwable) {
            return mb_strimwidth($content, 0, 10000, '…');
        }
    }
}
