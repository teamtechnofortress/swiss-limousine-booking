<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        $codes = json_decode((string) DB::table('app_settings')->where('key', 'allowed_countries')->value('value'), true);
        $names = [];
        foreach (is_array($codes) ? $codes : [] as $code) {
            $code = strtoupper((string) $code);
            $names[$code] = $code === 'CH' ? 'Switzerland' : $code;
        }

        DB::table('app_settings')->insertOrIgnore([
            'key' => 'allowed_country_names',
            'value' => json_encode($names === [] ? ['CH' => 'Switzerland'] : $names, JSON_THROW_ON_ERROR),
            'type' => 'json',
            'group' => 'service_area',
            'description' => 'Country names paired with the allowed country codes for administration display.',
            'is_secret' => false,
            'editable_by' => 'service_area.manage',
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('app_settings')) {
            DB::table('app_settings')->where('key', 'allowed_country_names')->delete();
        }
    }
};
