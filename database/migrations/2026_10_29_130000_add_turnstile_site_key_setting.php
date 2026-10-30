<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('app_settings')->where('key', 'turnstile_site_key')->exists()) {
            Cache::forget('settings:public-values');

            return;
        }

        $now = now();

        DB::table('app_settings')->insert([
            'key' => 'turnstile_site_key',
            'value' => '',
            'type' => 'string',
            'group' => 'integrations',
            'description' => 'Cloudflare Turnstile site key used in the customer browser. This is safe to publish.',
            'is_secret' => false,
            'editable_by' => 'integrations.manage',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Cache::forget('settings:public-values');
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'turnstile_site_key')->delete();
        Cache::forget('settings:public-values');
    }
};
