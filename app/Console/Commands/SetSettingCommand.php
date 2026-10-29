<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;

/** Sets a setting from the server. Use this for secrets such as Stripe and Google keys. */
final class SetSettingCommand extends Command
{
    protected $signature = 'settings:set {key : The setting key} {value? : The value (omit to type it hidden)}';

    protected $description = 'Set a setting value. Secrets are encrypted and never printed.';

    public function handle(SettingsService $settings): int
    {
        $key = (string) $this->argument('key');
        $setting = Setting::query()->where('key', $key)->first();

        if ($setting === null) {
            $this->error("Unknown setting [{$key}]. Run the SettingsSeeder first.");

            return self::FAILURE;
        }

        $value = $this->argument('value') ?? $this->secret("New value for {$key}");
        $settings->update($key, (string) $value, null);

        $this->info($setting->is_secret ? "Secret [{$key}] updated." : "Setting [{$key}] updated.");

        return self::SUCCESS;
    }
}
