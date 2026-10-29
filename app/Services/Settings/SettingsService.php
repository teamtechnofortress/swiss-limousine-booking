<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Single access point for runtime configuration.
 * Non-secret values are cached. Secrets are read from the database on demand and never cached.
 */
final class SettingsService
{
    private const CACHE_KEY = 'settings:public-values';
    private const CACHE_TTL = 3600;

    /** @return array<string, mixed> */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            return Setting::query()
                ->where('is_secret', false)
                ->get()
                ->mapWithKeys(fn (Setting $setting) => [$setting->key => $this->cast($setting->type, $setting->value)])
                ->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key, false);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key, 0);
    }

    public function nullableInt(string $key): ?int
    {
        $value = $this->get($key);

        return $value === null || $value === '' ? null : (int) $value;
    }

    public function string(string $key): string
    {
        return (string) $this->get($key, '');
    }

    /** @return list<string> */
    public function list(string $key): array
    {
        return array_values((array) $this->get($key, []));
    }

    /** Decrypted secret, or null when unset. Read from the database each time. */
    public function secret(string $key): ?string
    {
        $setting = Setting::query()->where('key', $key)->where('is_secret', true)->first();

        if ($setting === null || $setting->value === null || $setting->value === '') {
            return null;
        }

        $plain = Crypt::decryptString($setting->value);

        return $plain === '' ? null : $plain;
    }

    public function update(string $key, mixed $value, ?User $actor = null): void
    {
        DB::transaction(function () use ($key, $value, $actor): void {
            $setting = Setting::query()->where('key', $key)->lockForUpdate()->firstOrFail();

            $before = $setting->is_secret ? '[redacted]' : $setting->value;

            $setting->value = $setting->is_secret
                ? Crypt::encryptString((string) $value)
                : $this->serialize($setting->type, $value);
            $setting->updated_by = $actor?->id;
            $setting->save();

            $logger = activity('settings')
                ->performedOn($setting)
                ->withProperties([
                    'key' => $key,
                    'before' => $before,
                    'after' => $setting->is_secret ? '[redacted]' : $setting->value,
                ]);

            if ($actor !== null) {
                $logger->causedBy($actor);
            }

            $logger->log('setting_updated');

            Cache::forget(self::CACHE_KEY);
        });
    }

    private function cast(string $type, ?string $raw): mixed
    {
        return match ($type) {
            'bool' => $raw === '1',
            'int', 'money' => (int) $raw,
            'nullable_int', 'nullable_money' => $raw === null || $raw === '' ? null : (int) $raw,
            'json' => json_decode($raw ?? '[]', true, 512, JSON_THROW_ON_ERROR),
            default => $raw,
        };
    }

    private function serialize(string $type, mixed $value): string
    {
        return match ($type) {
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) ($value ?? ''),
        };
    }
}
